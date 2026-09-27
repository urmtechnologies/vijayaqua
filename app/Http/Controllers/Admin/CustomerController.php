<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalePayment;
use App\Support\RupeeAmount;
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

        $invoiced = (string) $customer->sales()->sum('total_rupees');
        $received = (string) SalePayment::whereHas('sale', fn ($q) => $q->where('customer_id', $customer->id))->sum('amount_rupees');
        $recent = $customer->sales()->withSum('payments as paid_total', 'amount_rupees')
            ->orderByDesc('id')->limit(5)->get()->map(fn ($sale) => [
                'invoice' => $sale->invoice_no,
                'url' => route('sales.show', $sale),
                'date' => $sale->sale_date->format('d M Y'),
                'due' => (int) $sale->total_rupees - (int) ($sale->paid_total ?? 0),
            ])->values();

        return response()->json([
            'found' => true,
            'name' => $customer->name,
            'account_url' => route('customers.show', $customer),
            'invoices' => $customer->sales()->count(),
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
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $customer->sales();
        if ($search = trim($filters['search'] ?? '')) $query->where('invoice_no', 'like', '%'.$search.'%');
        if ($from = $filters['from'] ?? null) $query->whereDate('sale_date', '>=', $from);
        if ($to = $filters['to'] ?? null) $query->whereDate('sale_date', '<=', $to);
        if (($filters['status'] ?? null) === 'paid') {
            $query->whereRaw('sales.total_rupees <= (SELECT COALESCE(SUM(amount_rupees), 0) FROM sale_payments WHERE sale_payments.sale_id = sales.id)');
        } elseif (($filters['status'] ?? null) === 'due') {
            $query->whereRaw('sales.total_rupees > (SELECT COALESCE(SUM(amount_rupees), 0) FROM sale_payments WHERE sale_payments.sale_id = sales.id)');
        }

        $summary = [
            'invoices' => (clone $query)->count(),
            'total' => (string) (clone $query)->sum('total_rupees'),
            'paid' => (string) SalePayment::whereIn('sale_id', (clone $query)->select('sales.id'))->sum('amount_rupees'),
        ];
        $summary['due'] = RupeeAmount::difference($summary['total'], $summary['paid']);
        $sales = $query->with(['customer', 'creator'])->withSum('payments as paid_total', 'amount_rupees')
            ->orderByDesc('sale_date')->orderByDesc('id')->paginate(10)->withQueryString();

        return $request->ajax()
            ? view('admin.sales.partials.results', compact('sales', 'summary'))
            : view('admin.customers.show', compact('customer', 'sales', 'summary'));
    }
}
