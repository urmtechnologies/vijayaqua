<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Support\RupeeAmount;
use App\Support\StockBalance;
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'status' => ['nullable', Rule::in(['paid', 'due'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Sale::query();
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($q) use ($search): void {
                $q->where('invoice_no', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customer) use ($search): void {
                        $customer->where(function ($matches) use ($search): void {
                            $matches->where('name', 'like', '%'.$search.'%')
                                ->orWhere('mobile', 'like', '%'.$search.'%');
                        });
                    });
            });
        }
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
            'paid' => (string) SalePayment::query()->whereIn('sale_id', (clone $query)->select('sales.id'))->sum('amount_rupees'),
        ];
        $summary['due'] = RupeeAmount::difference($summary['total'], $summary['paid']);

        $sales = $query->with(['customer', 'creator', 'editor'])
            ->withSum('payments as paid_total', 'amount_rupees')
            ->orderByDesc('sale_date')->orderByDesc('id')->paginate(10)->withQueryString();

        return $request->ajax()
            ? view('admin.sales.partials.results', compact('sales', 'summary'))
            : view('admin.sales.index', compact('sales', 'summary'));
    }

    public function create(Request $request): View
    {
        $mobile = $request->query('mobile');
        $customer = is_string($mobile) && preg_match('/^[0-9]{10}$/', $mobile)
            ? Customer::where('mobile', $mobile)->first() : null;

        return view('admin.sales.create', [
            'customer' => $customer,
            'products' => Product::where('status', 'active')
                ->withSum('stockItems as stock_received', 'cartons')
                ->withSum('soldItems as stock_sold', 'cartons')
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'party_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^[0-9]{10}$/'],
            'sale_date' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.cartons' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'items.*.rate_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})$/'],
            'discount_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,15})$/'],
            'vehicle_charge_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,15})$/'],
            'initial_paid_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,15})$/'],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'payment_reference' => ['nullable', 'string', 'max:150'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:sale_date'],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);

        $lineItems = [];
        $subtotal = 0;
        foreach ($data['items'] as $index => $item) {
            $rate = RupeeAmount::int($item['rate_rupees'], "items.$index.rate_rupees");
            $cartons = (int) $item['cartons'];
            $lineTotal = RupeeAmount::multiply($cartons, $rate, "items.$index.rate_rupees");
            $subtotal = RupeeAmount::add($subtotal, $lineTotal, 'items');
            $lineItems[] = ['product_id' => (int) $item['product_id'], 'cartons' => $cartons,
                'rate_rupees' => $rate, 'line_total_rupees' => $lineTotal];
        }

        $discount = RupeeAmount::int($data['discount_rupees'], 'discount_rupees');
        $vehicle = RupeeAmount::int($data['vehicle_charge_rupees'], 'vehicle_charge_rupees');
        $paid = RupeeAmount::int($data['initial_paid_rupees'], 'initial_paid_rupees');
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount_rupees' => 'Discount cannot exceed the product subtotal.']);
        }
        $total = RupeeAmount::add($subtotal - $discount, $vehicle, 'vehicle_charge_rupees');
        if ($paid > $total) {
            throw ValidationException::withMessages(['initial_paid_rupees' => 'Payment cannot exceed the invoice total.']);
        }
        if ($paid > 0 && empty($data['payment_method'])) {
            throw ValidationException::withMessages(['payment_method' => 'Choose how the payment was received.']);
        }
        if ($paid < $total && empty($data['due_date'])) {
            throw ValidationException::withMessages(['due_date' => 'Choose a due date for the remaining amount.']);
        }

        $sale = DB::transaction(function () use ($data, $lineItems, $subtotal, $discount, $vehicle, $total, $paid): Sale {
            $customer = Customer::createOrFirst(
                ['mobile' => $data['mobile']],
                ['name' => trim($data['party_name'])]
            );

            $ids = collect($lineItems)->pluck('product_id')->sort()->values();
            $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($lineItems as $index => &$item) {
                $product = $products->get($item['product_id']);
                if (! $product || $product->status !== 'active') {
                    throw ValidationException::withMessages(["items.$index.product_id" => 'Select an active product.']);
                }
                if (StockBalance::available($product->id) < $item['cartons']) {
                    throw ValidationException::withMessages(["items.$index.cartons" => "Not enough stock for {$product->name}."]);
                }
                $item['product_name'] = $product->name;
            }
            unset($item);

            $sale = Sale::create([
                'customer_id' => $customer->id,
                'sale_date' => $data['sale_date'],
                'subtotal_rupees' => $subtotal,
                'discount_rupees' => $discount,
                'vehicle_charge_rupees' => $vehicle,
                'total_rupees' => $total,
                'due_date' => $paid < $total ? $data['due_date'] : null,
                'reference' => $data['reference'] ?? null,
            ]);
            $sale->forceFill(['invoice_no' => 'VA-INV-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)])->save();
            $sale->items()->createMany($lineItems);
            if ($paid > 0) {
                $sale->payments()->create([
                    'payment_date' => $data['sale_date'],
                    'amount_rupees' => $paid,
                    'method' => $data['payment_method'],
                    'reference' => $data['payment_reference'] ?? null,
                ]);
            }

            return $sale;
        }, 3);

        return redirect()->route('sales.show', $sale)->with('success', 'Invoice created successfully.');
    }

    public function show(Sale $sale): View
    {
        $sale->load(['customer', 'creator', 'editor', 'items', 'payments.creator']);

        return view('admin.sales.show', [
            'sale' => $sale,
            'paid' => (int) $sale->payments->sum('amount_rupees'),
        ]);
    }

    public function edit(Sale $sale): View
    {
        $sale->load(['customer', 'items']);
        $existingIds = $sale->items->pluck('product_id');

        return view('admin.sales.edit', [
            'sale' => $sale,
            'customer' => $sale->customer,
            'paid' => (int) $sale->payments()->sum('amount_rupees'),
            'products' => Product::withTrashed()->where(function ($query) use ($existingIds): void {
                $query->where(fn ($active) => $active->where('status', 'active')->whereNull('deleted_at'))
                    ->orWhereIn('id', $existingIds);
            })->withSum('stockItems as stock_received', 'cartons')
                ->withSum('soldItems as stock_sold', 'cartons')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate([
            'party_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^[0-9]{10}$/'],
            'sale_date' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.cartons' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'items.*.rate_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})$/'],
            'discount_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,15})$/'],
            'vehicle_charge_rupees' => ['required', 'regex:/^(0|[1-9][0-9]{0,15})$/'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:sale_date'],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);

        $lines = [];
        $subtotal = 0;
        foreach ($data['items'] as $index => $item) {
            $rate = RupeeAmount::int($item['rate_rupees'], "items.$index.rate_rupees");
            $cartons = (int) $item['cartons'];
            $lineTotal = RupeeAmount::multiply($cartons, $rate, "items.$index.rate_rupees");
            $subtotal = RupeeAmount::add($subtotal, $lineTotal, 'items');
            $lines[] = ['product_id' => (int) $item['product_id'], 'cartons' => $cartons,
                'rate_rupees' => $rate, 'line_total_rupees' => $lineTotal];
        }
        $discount = RupeeAmount::int($data['discount_rupees'], 'discount_rupees');
        $vehicle = RupeeAmount::int($data['vehicle_charge_rupees'], 'vehicle_charge_rupees');
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount_rupees' => 'Discount cannot exceed the product subtotal.']);
        }
        $total = RupeeAmount::add($subtotal - $discount, $vehicle, 'vehicle_charge_rupees');

        DB::transaction(function () use ($sale, $data, $lines, $subtotal, $discount, $vehicle, $total): void {
            $invoice = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $paid = (int) $invoice->payments()->sum('amount_rupees');
            if ($total < $paid) {
                throw ValidationException::withMessages(['items' => 'Invoice total cannot be lower than payments already received.']);
            }
            if ($paid < $total && empty($data['due_date'])) {
                throw ValidationException::withMessages(['due_date' => 'Choose a due date for the remaining amount.']);
            }
            if ($invoice->payments()->whereDate('payment_date', '<', $data['sale_date'])->exists()) {
                throw ValidationException::withMessages(['sale_date' => 'Sale date cannot be after an existing payment date.']);
            }

            $old = $invoice->items()->pluck('cartons', 'product_id');
            $ids = $old->keys()->merge(collect($lines)->pluck('product_id'))->unique()->sort()->values();
            $products = Product::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($lines as $index => &$line) {
                $product = $products->get($line['product_id']);
                $wasOnInvoice = $old->has($line['product_id']);
                if (! $product || (($product->status !== 'active' || $product->trashed()) && ! $wasOnInvoice)) {
                    throw ValidationException::withMessages(["items.$index.product_id" => 'Select an active product.']);
                }
                if (StockBalance::available($product->id) + (int) $old->get($product->id, 0) < $line['cartons']) {
                    throw ValidationException::withMessages(["items.$index.cartons" => "Not enough stock for {$product->name}."]);
                }
                $line['product_name'] = $product->name;
            }
            unset($line);

            $customer = Customer::createOrFirst(['mobile' => $data['mobile']], ['name' => trim($data['party_name'])]);
            $invoice->update([
                'customer_id' => $customer->id, 'sale_date' => $data['sale_date'],
                'subtotal_rupees' => $subtotal, 'discount_rupees' => $discount,
                'vehicle_charge_rupees' => $vehicle, 'total_rupees' => $total,
                'due_date' => $paid < $total ? $data['due_date'] : null,
                'reference' => $data['reference'] ?? null,
            ]);
            $invoice->items()->delete();
            $invoice->items()->createMany($lines);
            $invoice->forceFill(['updated_by' => auth()->id(), 'updated_at' => now()])->save();
        }, 3);

        return redirect()->route('sales.show', $sale)->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        DB::transaction(function () use ($sale): void {
            $invoice = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($invoice->payments()->exists()) {
                throw ValidationException::withMessages(['sale' => 'Invoice has payments. Keep the payment history; paid invoices cannot be deleted.']);
            }
            Product::withTrashed()->whereIn('id', $invoice->items()->pluck('product_id'))
                ->orderBy('id')->lockForUpdate()->get();
            $invoice->delete();
        }, 3);

        return redirect()->route('sales.index')->with('success', 'Unpaid invoice removed. Its stock is available again.');
    }
}
