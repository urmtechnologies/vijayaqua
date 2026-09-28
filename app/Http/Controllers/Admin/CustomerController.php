<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalePayment;
use App\Support\RupeeAmount;
use App\Support\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $input = $request->validate(['mobile' => ['required', 'regex:/^[0-9]{10}$/']]);
        $customer = Customer::where('mobile', $input['mobile'])->first();
        if (! $customer) return response()->json(['found' => false]);

        if (! Access::allowed('sales') && ! Access::allowed('sales', 'create')) {
            // Upcoming-order creation may look up a customer without gaining access to their sales ledger.
            return response()->json(['found' => false]);
        }

        $visible = Access::scope($customer->sales()->getQuery(), 'sales');
        if (! Access::all('sales') && ! (clone $visible)->exists()) return response()->json(['found' => false]);
        $invoiced = (string) (clone $visible)->where('approval_status', 'approved')->sum('total_rupees');
        $received = (string) SalePayment::where('approval_status', 'approved')->whereIn('sale_id', (clone $visible)->select('sales.id'))->sum('amount_rupees');
        $recent = (clone $visible)->withSum(['payments as paid_total' => fn ($q) => $q->where('approval_status', 'approved')], 'amount_rupees')
            ->orderByDesc('id')->limit(5)->get()->map(fn ($sale) => [
                'invoice' => $sale->invoice_no,
                'url' => Access::record('sales', 'invoice', $sale) ? route('sales.show', $sale) : null,
                'date' => $sale->sale_date->format('d M Y'),
                'due' => (int) $sale->total_rupees - (int) ($sale->paid_total ?? 0),
            ])->values();

        return response()->json([
            'found' => true,
            'name' => $customer->name,
            'account_url' => Access::allowed('sales') ? route('customers.show', $customer) : null,
            'invoices' => (clone $visible)->count(),
            'due' => RupeeAmount::difference($invoiced, $received),
            'recent' => $recent,
        ]);
    }

    public function show(Request $request, Customer $customer): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'status' => ['nullable', Rule::in(['paid', 'due'])],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Access::scope($customer->sales()->getQuery(), 'sales');
        abort_unless((clone $query)->exists(), 404);
        if ($approval = $filters['approval'] ?? null) $query->where('approval_status', $approval);
        if ($search = trim($filters['search'] ?? '')) $query->where('invoice_no', 'like', '%'.$search.'%');
        if ($from = $filters['from'] ?? null) $query->whereDate('sale_date', '>=', $from);
        if ($to = $filters['to'] ?? null) $query->whereDate('sale_date', '<=', $to);
        if (($filters['status'] ?? null) === 'paid') {
            $query->whereRaw("sales.total_rupees <= (SELECT COALESCE(SUM(amount_rupees), 0) FROM sale_payments WHERE sale_payments.deleted_at IS NULL AND sale_payments.approval_status = 'approved' AND sale_payments.sale_id = sales.id)");
        } elseif (($filters['status'] ?? null) === 'due') {
            $query->whereRaw("sales.total_rupees > (SELECT COALESCE(SUM(amount_rupees), 0) FROM sale_payments WHERE sale_payments.deleted_at IS NULL AND sale_payments.approval_status = 'approved' AND sale_payments.sale_id = sales.id)");
        }

        $summary = [
            'invoices' => (clone $query)->count(),
            'total' => (string) (clone $query)->where('approval_status', 'approved')->sum('total_rupees'),
            'paid' => (string) SalePayment::where('approval_status', 'approved')->whereIn('sale_id', (clone $query)->select('sales.id'))->sum('amount_rupees'),
        ];
        $summary['due'] = RupeeAmount::difference($summary['total'], $summary['paid']);
        $sales = $query->with(['customer', 'creator'])->withSum(['payments as paid_total' => fn ($q) => $q->where('approval_status', 'approved')], 'amount_rupees')
            ->orderByDesc('sale_date')->orderByDesc('id')->paginate(10)->withQueryString();

        return $request->ajax()
            ? view('admin.sales.partials.results', compact('sales', 'summary'))
            : view('admin.customers.show', compact('customer', 'sales', 'summary'));
    }
}
