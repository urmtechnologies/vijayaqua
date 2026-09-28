<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Support\RupeeAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'method' => ['nullable', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Access::scope(SalePayment::query(), 'payments')->whereHas('sale');
        if ($approval = $filters['approval'] ?? null) $query->where('approval_status', $approval);
        if ($search = trim($filters['search'] ?? '')) {
            $query->whereHas('sale', function ($sale) use ($search): void {
                $sale->where('invoice_no', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customer) use ($search): void {
                        $customer->where(function ($matches) use ($search): void {
                            $matches->where('name', 'like', '%'.$search.'%')
                                ->orWhere('mobile', 'like', '%'.$search.'%');
                        });
                    });
            });
        }
        if ($from = $filters['from'] ?? null) $query->whereDate('payment_date', '>=', $from);
        if ($to = $filters['to'] ?? null) $query->whereDate('payment_date', '<=', $to);
        if ($method = $filters['method'] ?? null) $query->where('method', $method);

        $summary = ['count' => (clone $query)->count(), 'received' => (string) (clone $query)->where('approval_status', 'approved')->whereHas('sale', fn ($sale) => $sale->where('approval_status', 'approved'))->sum('amount_rupees')];
        $payments = $query->with(['sale.customer', 'creator', 'editor', 'approver'])
            ->orderByDesc('payment_date')->orderByDesc('id')->paginate(10)->withQueryString();

        return $request->ajax()
            ? view('admin.payments.partials.results', compact('payments', 'summary'))
            : view('admin.payments.index', compact('payments', 'summary'));
    }

    public function create(Request $request): View
    {
        $invoice = $request->query('invoice');
        $sale = is_string($invoice) ? $this->availableSales()->with(['customer'])->withSum(['payments as paid_total' => fn ($q) => $q->where('approval_status', 'approved')], 'amount_rupees')
            ->where('invoice_no', $invoice)->first() : null;

        return view('admin.payments.create', compact('sale'));
    }

    public function lookup(Request $request)
    {
        $data = $request->validate(['invoice' => ['required', 'string', 'max:32']]);
        $sale = $this->availableSales()->with('customer')->withSum(['payments as paid_total' => fn ($q) => $q->where('approval_status', 'approved')], 'amount_rupees')
            ->where('invoice_no', trim($data['invoice']))->first();
        if (! $sale) return response()->json(['found' => false]);

        return response()->json([
            'found' => true,
            'id' => $sale->id,
            'invoice' => $sale->invoice_no,
            'party' => $sale->customer->name,
            'mobile' => $sale->customer->mobile,
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'due' => (int) $sale->total_rupees - (int) ($sale->paid_total ?? 0),
            'url' => Access::record('sales', 'invoice', $sale) ? route('sales.show', $sale) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer', Rule::exists('sales', 'id')],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'amount_rupees' => ['required', 'regex:/^[1-9][0-9]{0,15}$/'],
            'method' => ['required', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);
        $amount = RupeeAmount::int($data['amount_rupees'], 'amount_rupees');

        $sale = DB::transaction(function () use ($data, $amount): Sale {
            $sale = Sale::whereKey($data['sale_id'])->lockForUpdate()->firstOrFail();
            if ($data['payment_date'] < $sale->sale_date->format('Y-m-d')) {
                throw ValidationException::withMessages(['payment_date' => 'Payment date cannot be before the sale date.']);
            }
            $alreadyPaid = (int) $sale->payments()->sum('amount_rupees');
            abort_unless($sale->approval_status === 'approved'
                && (Access::all('payments') || Access::all('sales') || (int) $sale->user_id === auth()->id()), 403);
            if ($amount > (int) $sale->total_rupees - $alreadyPaid) {
                throw ValidationException::withMessages(['amount_rupees' => 'Payment exceeds the remaining due amount.']);
            }
            $sale->payments()->create([
                'payment_date' => $data['payment_date'],
                'amount_rupees' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
            ]);

            return $sale;
        }, 3);

        return redirect()->route(Access::allowed('sales', 'invoice') ? 'sales.show' : 'payments.index',
            Access::allowed('sales', 'invoice') ? [$sale] : [])->with('success', 'Payment recorded successfully.');
    }

    public function edit(SalePayment $payment): View
    {
        $payment->load('sale.customer');
        return view('admin.payments.edit', compact('payment'));
    }

    public function update(Request $request, SalePayment $payment): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'amount_rupees' => ['required', 'regex:/^[1-9][0-9]{0,15}$/'],
            'method' => ['required', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);
        DB::transaction(function () use ($payment, $data): void {
            $sale = Sale::whereKey($payment->sale_id)->lockForUpdate()->firstOrFail();
            $record = SalePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canEdit('payments', $record), 403);
            if ($data['payment_date'] < $sale->sale_date->format('Y-m-d')) {
                throw ValidationException::withMessages(['payment_date' => 'Payment date cannot be before the sale date.']);
            }
            $otherPayments = (int) $sale->payments()->where('id', '!=', $record->id)->sum('amount_rupees');
            if ((int) $data['amount_rupees'] > (int) $sale->total_rupees - $otherPayments) {
                throw ValidationException::withMessages(['amount_rupees' => 'Payment exceeds the remaining invoice balance.']);
            }
            $record->update($data);
        }, 3);

        return redirect()->route('payments.index')->with('success', 'Payment updated.');
    }

    public function destroy(SalePayment $payment): RedirectResponse
    {
        DB::transaction(function () use ($payment): void {
            Sale::whereKey($payment->sale_id)->lockForUpdate()->firstOrFail();
            $record = SalePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('payments', $record), 403);
            $record->delete();
        }, 3);

        return back()->with('success', 'Payment removed.');
    }

    private function availableSales(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Sale::query()->where('approval_status', 'approved');
        if (! Access::all('payments') && ! Access::all('sales')) $query->where('user_id', auth()->id());
        return $query;
    }
}
