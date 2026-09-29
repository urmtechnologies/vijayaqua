<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Customer, Product, Sale, SalePayment, User};
use App\Support\{Access, CustomerWallet, SaleMoney, StockBalance};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Access::scope(Sale::query(), 'sales');
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('invoice_no', 'like', '%'.$search.'%')
                ->orWhereHas('customer', fn ($party) => $party->where('name', 'like', '%'.$search.'%')
                    ->orWhere('mobile', 'like', '%'.$search.'%')->orWhere('business_name', 'like', '%'.$search.'%')));
        }
        if ($approval = $filters['approval'] ?? null) {
            $query->where('is_draft', false)->where('approval_status', $approval);
        }
        $customers = Customer::query()->whereIn('id', (clone $query)->select('customer_id'))
            ->orderBy('name')->paginate(10)->withQueryString();
        $customers->getCollection()->each(function (Customer $customer): void {
            $customer->setAttribute('workspace_totals', CustomerWallet::totals($customer->id));
            $customer->setRelation('visibleSales', Access::scope($customer->sales()->getQuery(), 'sales')
                ->orderBy('sale_date')->orderBy('id')->get(['id', 'customer_id', 'invoice_no', 'approval_status', 'is_draft']));
        });
        $approved = (clone $query)->where('is_draft', false)->where('approval_status', 'approved');
        $allIds = (clone $approved)->select('sales.id');
        $billed = (string) $approved->sum('total_rupees');
        $payments = SalePayment::whereIn('sale_id', $allIds)->where('approval_status', 'approved');
        $received = SaleMoney::decimal(SaleMoney::paise((string) (clone $payments)->where('entry_type', 'credit')->sum('amount_rupees'))
            - SaleMoney::paise((string) (clone $payments)->where('entry_type', 'debit')->sum('amount_rupees')));
        $summary = [
            'sales' => (clone $query)->where('is_draft', false)->count(), 'billed' => $billed, 'received' => $received,
            'balance' => SaleMoney::decimal(max(0, SaleMoney::paise($billed) - SaleMoney::paise($received))),
        ];

        return $request->ajax()
            ? view('admin.sales.partials.results', compact('customers', 'summary'))
            : view('admin.sales.index', compact('customers', 'summary'));
    }

    public function create(Request $request): View
    {
        $mobile = $request->query('mobile');
        $customer = is_string($mobile) && preg_match('/^[0-9]{10}$/', $mobile)
            ? Customer::where('mobile', $mobile)->first() : null;
        if ($customer && ! Access::all('sales')
            && ! $customer->sales()->where('user_id', auth()->id())->exists()) $customer = null;
        return view('admin.sales.create', compact('customer'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Accept earlier invoice forms while the new UI uses the short party-first flow.
        if ($request->has('items')) return $this->storeCompleteSale($request);
        $data = $request->validate([
            'party_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^[0-9]{10}$/'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'sale_date' => ['required', 'date_format:Y-m-d'],
        ]);
        $sale = DB::transaction(function () use ($data): Sale {
            $customer = Customer::createOrFirst(['mobile' => $data['mobile']], [
                'name' => trim($data['party_name']), 'business_name' => trim($data['business_name'] ?? '') ?: null,
            ]);
            $existing = $customer->sales()->where('user_id', auth()->id())->where('is_draft', true)->latest('id')->first();
            if ($existing) {
                $existing->update(['sale_date' => $data['sale_date']]);
                return $existing;
            }
            // A draft lets the short first screen lead straight to product entry without booking empty stock/sales.
            $draft = Sale::create([
                'customer_id' => $customer->id, 'sale_date' => $data['sale_date'],
                'subtotal_rupees' => '0.00', 'discount_rupees' => '0.00',
                'vehicle_charge_rupees' => '0.00', 'total_rupees' => '0.00', 'is_draft' => true,
            ]);
            $draft->forceFill(['invoice_no' => 'VA-INV-'.str_pad((string) $draft->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
            return $draft;
        }, 3);
        return redirect()->route('customers.show', $sale->customer_id)->with('success', 'Party ready. Add products below.');
    }

    private function storeCompleteSale(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'party_name' => ['required', 'string', 'max:150'], 'mobile' => ['required', 'regex:/^[0-9]{10}$/'],
            'sale_date' => ['required', 'date_format:Y-m-d'], 'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.cartons' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'items.*.rate_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/'],
            'discount_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/'],
            'vehicle_charge_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/'],
            'initial_paid_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/'],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'payment_reference' => ['nullable', 'string', 'max:150'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:sale_date'],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);
        $lines = []; $subtotal = 0;
        foreach ($data['items'] as $index => $item) {
            $rate = SaleMoney::paise($item['rate_rupees'], "items.$index.rate_rupees");
            $line = SaleMoney::multiply((int) $item['cartons'], $rate, "items.$index.rate_rupees");
            $subtotal = SaleMoney::add($subtotal, $line, 'items');
            $lines[] = ['product_id' => (int) $item['product_id'], 'cartons' => (int) $item['cartons'],
                'rate_rupees' => SaleMoney::decimal($rate), 'line_total_rupees' => SaleMoney::decimal($line)];
        }
        $discount = SaleMoney::paise($data['discount_rupees']);
        $vehicle = SaleMoney::paise($data['vehicle_charge_rupees']);
        $paid = SaleMoney::paise($data['initial_paid_rupees']);
        if ($discount > $subtotal) throw ValidationException::withMessages(['discount_rupees' => 'Discount exceeds the subtotal.']);
        $total = SaleMoney::add($subtotal - $discount, $vehicle, 'vehicle_charge_rupees');
        if ($paid > $total) throw ValidationException::withMessages(['initial_paid_rupees' => 'Payment exceeds sale total.']);
        if ($paid && empty($data['payment_method'])) throw ValidationException::withMessages(['payment_method' => 'Choose a payment method.']);
        if ($paid < $total && empty($data['due_date'])) throw ValidationException::withMessages(['due_date' => 'Choose a due date.']);

        $sale = DB::transaction(function () use ($data, $lines, $subtotal, $discount, $vehicle, $total, $paid): Sale {
            $customer = Customer::createOrFirst(['mobile' => $data['mobile']], ['name' => trim($data['party_name'])]);
            $products = Product::whereIn('id', collect($lines)->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($lines as $index => &$line) {
                $product = $products->get($line['product_id']);
                if (! $product || $product->status !== 'active' || $product->approval_status !== 'approved') {
                    throw ValidationException::withMessages(["items.$index.product_id" => 'Select an active product.']);
                }
                if (StockBalance::available($product->id) < $line['cartons']) {
                    throw ValidationException::withMessages(["items.$index.cartons" => "Not enough stock for {$product->name}."]);
                }
                $line['product_name'] = $product->name;
            }
            unset($line);
            $sale = Sale::create([
                'customer_id' => $customer->id, 'sale_date' => $data['sale_date'],
                'subtotal_rupees' => SaleMoney::decimal($subtotal), 'discount_rupees' => SaleMoney::decimal($discount),
                'vehicle_charge_rupees' => SaleMoney::decimal($vehicle), 'total_rupees' => SaleMoney::decimal($total),
                'due_date' => $paid < $total ? $data['due_date'] : null, 'reference' => $data['reference'] ?? null,
            ]);
            $sale->forceFill(['invoice_no' => 'VA-INV-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
            $sale->items()->createMany($lines);
            if ($paid) $sale->payments()->create([
                'payment_date' => $data['sale_date'], 'amount_rupees' => SaleMoney::decimal($paid),
                'method' => $data['payment_method'], 'reference' => $data['payment_reference'] ?? null,
            ]);
            CustomerWallet::refresh($customer->id);
            return $sale;
        }, 3);
        return redirect()->route('customers.show', $sale->customer_id)->with('success', 'Sale saved.');
    }

    public function show(Sale $sale): View
    {
        abort_if($sale->is_draft, 404);
        $sale->load(['customer', 'creator', 'editor', 'approver', 'referenceUser', 'items.referenceUser', 'payments.creator', 'payments.approver']);
        $paid = SaleMoney::decimal($sale->netPaidPaise());
        return view('admin.sales.show', compact('sale', 'paid'));
    }

    public function edit(Request $request, Sale $sale): View|RedirectResponse
    {
        if (! $request->boolean('popup')) return redirect()->route('customers.show', $sale->customer_id);
        abort_unless(auth()->user()->role === 'admin' && ! $sale->is_draft, 403);
        $sale->load(['customer', 'creator', 'items.referenceUser', 'payments.creator']);
        $existingIds = $sale->items->pluck('product_id');
        $products = Product::withTrashed()->where(function ($query) use ($existingIds): void {
            $query->where(fn ($active) => $active->where('status', 'active')->where('approval_status', 'approved')->whereNull('deleted_at'))
                ->orWhereIn('id', $existingIds);
        })->withSum('stockItems as stock_received', 'cartons')
            ->withSum('soldItems as stock_sold', 'cartons')->orderBy('name')->get();
        $staff = User::query()->where('role', '!=', 'admin')->where('approval_status', 'approved')
            ->orderBy('name')->get(['id', 'name', 'mobile']);
        $paid = SaleMoney::decimal($sale->netPaidPaise());
        $due = SaleMoney::decimal(max(0, SaleMoney::paise($sale->total_rupees) - SaleMoney::paise($paid)));
        $payments = $sale->payments->sortByDesc(fn ($payment) => $payment->payment_date->format('Y-m-d').str_pad($payment->id, 12, '0', STR_PAD_LEFT));
        return view('admin.sales.popup', compact('sale', 'products', 'staff'));
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate([
            'party_name' => ['sometimes', 'required', 'string', 'max:150'],
            'mobile' => ['sometimes', 'required', 'regex:/^[0-9]{10}$/'],
            'sale_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.cartons' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'items.*.rate_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/'],
            'items.*.discount_rupees' => ['nullable', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/'],
            'items.*.reference_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', '!=', 'admin')->where('approval_status', 'approved')->whereNull('deleted_at'))],
            'reference_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', '!=', 'admin')->where('approval_status', 'approved')->whereNull('deleted_at'))],
            'discount_rupees' => ['sometimes', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/'],
            'vehicle_charge_rupees' => ['sometimes', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);
        $lines = [];
        $subtotal = 0; $lineDiscounts = 0;
        foreach ($data['items'] as $index => $item) {
            $rate = SaleMoney::paise($item['rate_rupees'], "items.$index.rate_rupees");
            $gross = SaleMoney::multiply((int) $item['cartons'], $rate, "items.$index.rate_rupees");
            $discount = SaleMoney::paise((string) ($item['discount_rupees'] ?? '0'), "items.$index.discount_rupees");
            if ($discount > $gross) throw ValidationException::withMessages(["items.$index.discount_rupees" => 'Discount cannot exceed this product amount.']);
            $subtotal = SaleMoney::add($subtotal, $gross, 'items');
            $lineDiscounts = SaleMoney::add($lineDiscounts, $discount, 'items');
            $lines[] = ['product_id' => (int) $item['product_id'], 'cartons' => (int) $item['cartons'],
                'rate_rupees' => SaleMoney::decimal($rate), 'discount_rupees' => SaleMoney::decimal($discount),
                'reference_user_id' => $item['reference_user_id'] ?? $data['reference_user_id'] ?? null,
                'line_total_rupees' => SaleMoney::decimal($gross - $discount)];
        }

        DB::transaction(function () use ($sale, $data, $lines, $subtotal, $lineDiscounts): void {
            $invoice = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canEdit('sales', $invoice), 403);
            $discount = SaleMoney::paise($data['discount_rupees'] ?? $invoice->discount_rupees);
            $vehicle = SaleMoney::paise($data['vehicle_charge_rupees'] ?? $invoice->vehicle_charge_rupees);
            if ($discount > $subtotal - $lineDiscounts) throw ValidationException::withMessages(['items' => 'Products must cover the existing invoice discount.']);
            $total = SaleMoney::add($subtotal - $lineDiscounts - $discount, $vehicle, 'items');
            $paid = max($invoice->netPaidPaise(false), $invoice->netPaidPaise());
            if ($total < $paid) throw ValidationException::withMessages(['items' => 'Invoice total cannot be lower than payments already recorded.']);
            if (isset($data['sale_date']) && $invoice->payments()->whereDate('payment_date', '<', $data['sale_date'])->exists()) {
                throw ValidationException::withMessages(['sale_date' => 'Sale date cannot be after a payment date.']);
            }
            $old = $invoice->items()->pluck('cartons', 'product_id');
            $ids = $old->keys()->merge(collect($lines)->pluck('product_id'))->unique()->sort()->values();
            $products = Product::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($lines as $index => &$line) {
                $product = $products->get($line['product_id']);
                $wasOnInvoice = $old->has($line['product_id']);
                if (! $product || (($product->status !== 'active' || $product->approval_status !== 'approved' || $product->trashed()) && ! $wasOnInvoice)) {
                    throw ValidationException::withMessages(["items.$index.product_id" => 'Select an active product.']);
                }
                if (StockBalance::available($product->id) + ($invoice->approval_status === 'approved' && ! $invoice->is_draft ? (int) $old->get($product->id, 0) : 0) < $line['cartons']) {
                    throw ValidationException::withMessages(["items.$index.cartons" => "Not enough stock for {$product->name}."]);
                }
                $line['product_name'] = $product->name;
            }
            unset($line);
            $newCustomer = isset($data['mobile']) ? Customer::createOrFirst(
                ['mobile' => $data['mobile']], ['name' => trim($data['party_name'] ?? $invoice->customer->name)]
            ) : null;
            $oldCustomerId = $invoice->customer_id;
            $invoice->update([
                'customer_id' => $newCustomer?->id ?? $oldCustomerId,
                'sale_date' => $data['sale_date'] ?? $invoice->sale_date->format('Y-m-d'),
                'subtotal_rupees' => SaleMoney::decimal($subtotal),
                'discount_rupees' => SaleMoney::decimal($discount),
                'vehicle_charge_rupees' => SaleMoney::decimal($vehicle),
                'total_rupees' => SaleMoney::decimal($total),
                'reference_user_id' => $data['reference_user_id'] ?? $invoice->reference_user_id,
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $invoice->due_date?->format('Y-m-d'),
                'reference' => array_key_exists('reference', $data) ? $data['reference'] : $invoice->reference,
                'is_draft' => false,
            ]);
            $invoice->items()->delete();
            $invoice->items()->createMany($lines);
            CustomerWallet::refresh($invoice->customer_id);
            if ($oldCustomerId !== $invoice->customer_id) CustomerWallet::refresh($oldCustomerId);
        }, 3);
        return redirect()->route('customers.show', $sale->fresh()->customer_id)->with('success', 'Sale saved.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $customerId = $sale->customer_id;
        DB::transaction(function () use ($sale, $customerId): void {
            $invoice = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('sales', $invoice), 403);
            if ($invoice->payments()->where('approval_status', 'approved')->exists()) {
                throw ValidationException::withMessages(['sale' => 'Paid invoices cannot be deleted.']);
            }
            Product::withTrashed()->whereIn('id', $invoice->items()->pluck('product_id'))
                ->orderBy('id')->lockForUpdate()->get();
            $invoice->payments()->where('approval_status', 'pending')->update(['deleted_at' => now(), 'updated_by' => auth()->id()]);
            $invoice->delete();
            CustomerWallet::refresh($customerId);
        }, 3);
        return redirect()->route('sales.index')->with('success', 'Sale removed.');
    }
}
