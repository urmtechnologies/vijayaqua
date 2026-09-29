<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Support\{CustomerWallet, SaleMoney};
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

        $approved = (clone $query)->where('approval_status', 'approved')->whereHas('sale', fn ($sale) => $sale->where('approval_status', 'approved'));
        $summary = ['count' => (clone $query)->count(), 'received' => SaleMoney::decimal(
            SaleMoney::paise((string) (clone $approved)->where('entry_type', 'credit')->sum('amount_rupees'))
            - SaleMoney::paise((string) (clone $approved)->where('entry_type', 'debit')->sum('amount_rupees')),
        )];
        $payments = $query->with(['sale.customer', 'creator', 'editor', 'approver'])
            ->orderByDesc('payment_date')->orderByDesc('id')->paginate(10)->withQueryString();

        return $request->ajax()
            ? view('admin.payments.partials.results', compact('payments', 'summary'))
            : view('admin.payments.index', compact('payments', 'summary'));
    }

    public function create(Request $request): View
    {
        $invoice = $request->query('invoice');
        $sale = is_string($invoice) ? $this->availableSales()->with(['customer'])
            ->where('invoice_no', $invoice)->first() : null;

        return view('admin.payments.create', compact('sale'));
    }

    public function lookup(Request $request)
    {
        $data = $request->validate(['invoice' => ['required', 'string', 'max:32']]);
        $sale = $this->availableSales()->with('customer')
            ->where('invoice_no', trim($data['invoice']))->first();
        if (! $sale) return response()->json(['found' => false]);

        return response()->json([
            'found' => true,
            'id' => $sale->id,
            'invoice' => $sale->invoice_no,
            'party' => $sale->customer->name,
            'mobile' => $sale->customer->mobile,
            'sale_date' => $sale->sale_date->format('Y-m-d'),
            'due' => SaleMoney::decimal(max(0, SaleMoney::paise($sale->total_rupees) - $sale->netPaidPaise())),
            'url' => Access::record('sales', 'invoice', $sale) ? route('sales.show', $sale) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer', Rule::exists('sales', 'id')],
            'entry_type' => ['nullable', Rule::in(['credit', 'debit'])],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'amount_rupees' => ['required', 'regex:/^(?!(?:0|0\.0{1,2})$)(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/'],
            'method' => ['required', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);
        $amount = SaleMoney::paise($data['amount_rupees'], 'amount_rupees');

        $sale = DB::transaction(function () use ($data, $amount): Sale {
            $sale = Sale::whereKey($data['sale_id'])->lockForUpdate()->firstOrFail();
            if ($data['payment_date'] < $sale->sale_date->format('Y-m-d')) {
                throw ValidationException::withMessages(['payment_date' => 'Payment date cannot be before the sale date.']);
            }
            $entryType = $data['entry_type'] ?? 'credit';
            abort_unless($sale->approval_status === 'approved'
                && ! $sale->is_draft
                && (Access::all('payments') || Access::all('sales') || (int) $sale->user_id === auth()->id()), 403);
            if ($entryType === 'credit' && $amount > SaleMoney::paise($sale->total_rupees)
                - max($sale->netPaidPaise(false), $sale->netPaidPaise())) {
                throw ValidationException::withMessages(['amount_rupees' => 'Credit exceeds the remaining invoice due.']);
            }
            $pendingRefunds = SaleMoney::paise((string) $sale->payments()->where('approval_status', 'pending')->where('entry_type', 'debit')->sum('amount_rupees'));
            if ($entryType === 'debit' && $amount > $sale->netPaidPaise() - $pendingRefunds) {
                throw ValidationException::withMessages(['amount_rupees' => 'Debit cannot exceed the approved payments received.']);
            }
            $sale->payments()->create([
                'payment_date' => $data['payment_date'],
                'entry_type' => $entryType,
                'amount_rupees' => SaleMoney::decimal($amount),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
            ]);

            CustomerWallet::refresh($sale->customer_id);
            return $sale;
        }, 3);

        return redirect()->to($this->saleReturnUrl($sale))->with('success', 'Payment entry recorded.');
    }

    public function edit(SalePayment $payment): View
    {
        $payment->load('sale.customer');
        return view('admin.payments.edit', compact('payment'));
    }

    public function update(Request $request, SalePayment $payment): RedirectResponse
    {
        $data = $request->validate([
            'entry_type' => ['nullable', Rule::in(['credit', 'debit'])],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'amount_rupees' => ['required', 'regex:/^(?!(?:0|0\.0{1,2})$)(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/'],
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
            $other = $sale->payments()->where('id', '!=', $record->id);
            $allCredits = SaleMoney::paise((string) (clone $other)->where('entry_type', 'credit')->sum('amount_rupees'));
            $allDebits = SaleMoney::paise((string) (clone $other)->where('entry_type', 'debit')->sum('amount_rupees'));
            $amount = SaleMoney::paise($data['amount_rupees']);
            $type = $data['entry_type'] ?? $record->entry_type;
            $approvedNet = SaleMoney::paise((string) (clone $other)->where('approval_status', 'approved')->where('entry_type', 'credit')->sum('amount_rupees'))
                - SaleMoney::paise((string) (clone $other)->where('approval_status', 'approved')->where('entry_type', 'debit')->sum('amount_rupees'));
            if ($type === 'credit' && ($amount > SaleMoney::paise($sale->total_rupees) - max($allCredits - $allDebits, $approvedNet)
                || $amount + $allCredits < $allDebits)) {
                throw ValidationException::withMessages(['amount_rupees' => 'Credit exceeds the remaining invoice due.']);
            }
            $approvedCredits = SaleMoney::paise((string) (clone $other)->where('approval_status', 'approved')->where('entry_type', 'credit')->sum('amount_rupees'));
            $reservedDebits = SaleMoney::paise((string) (clone $other)->where('entry_type', 'debit')->sum('amount_rupees'));
            if ($type === 'debit' && $amount > $approvedCredits - $reservedDebits) {
                throw ValidationException::withMessages(['amount_rupees' => 'Debit cannot exceed the approved payments received.']);
            }
            $record->update([...$data, 'entry_type' => $type]);
            CustomerWallet::refresh($sale->customer_id);
        }, 3);

        return redirect()->to($this->saleReturnUrl($payment->sale))->with('success', 'Payment updated.');
    }

    public function destroy(SalePayment $payment): RedirectResponse
    {
        DB::transaction(function () use ($payment): void {
            Sale::whereKey($payment->sale_id)->lockForUpdate()->firstOrFail();
            $record = SalePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('payments', $record), 403);
            $netAfterDelete = $record->sale->netPaidPaise(false)
                + ($record->entry_type === 'debit' ? 1 : -1) * SaleMoney::paise($record->amount_rupees);
            if ($netAfterDelete < 0 || $netAfterDelete > SaleMoney::paise($record->sale->total_rupees)) {
                throw ValidationException::withMessages(['payment' => 'This entry cannot be deleted while later entries depend on it.']);
            }
            $record->delete();
            CustomerWallet::refresh($record->sale->customer_id);
        }, 3);

        return back()->with('success', 'Payment removed.');
    }

    private function availableSales(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Sale::query()->where('approval_status', 'approved')->where('is_draft', false);
        if (! Access::all('payments') && ! Access::all('sales')) $query->where('user_id', auth()->id());
        return $query;
    }

    private function saleReturnUrl(Sale $sale): string
    {
        if (Access::all('sales') || (int) $sale->user_id === auth()->id())
            return route('customers.show', $sale->customer_id).'#payments';
        if (Access::record('sales', 'invoice', $sale)) return route('sales.show', $sale);
        return route('sales.index');
    }
}
