<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\UpcomingOrder;
use App\Models\UpcomingOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UpcomingOrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'sort' => ['nullable', Rule::in(['soonest', 'latest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = UpcomingOrder::query();
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($q) use ($search): void {
                $q->whereHas('customer', function ($customer) use ($search): void {
                    $customer->where(fn ($matches) => $matches->where('name', 'like', '%'.$search.'%')
                        ->orWhere('mobile', 'like', '%'.$search.'%'));
                })->orWhereHas('items', fn ($items) => $items->where('product_name', 'like', '%'.$search.'%'));
            });
        }
        if ($from = $filters['from'] ?? null) $query->whereDate('scheduled_date', '>=', $from);
        if ($to = $filters['to'] ?? null) $query->whereDate('scheduled_date', '<=', $to);

        $summary = [
            'orders' => (clone $query)->count(),
            'cartons' => (string) UpcomingOrderItem::query()
                ->whereIn('upcoming_order_id', (clone $query)->select('upcoming_orders.id'))->sum('cartons'),
        ];
        $query->with(['customer', 'creator', 'editor'])->withCount('items')->withSum('items as carton_total', 'cartons');
        if (($filters['sort'] ?? 'soonest') === 'latest') $query->orderByDesc('scheduled_date')->orderByDesc('id');
        else $query->orderBy('scheduled_date')->orderBy('id');
        $orders = $query->paginate(10)->withQueryString();

        return $request->ajax()
            ? view('admin.upcoming-orders.partials.results', compact('orders', 'summary'))
            : view('admin.upcoming-orders.index', compact('orders', 'summary'));
    }

    public function create(Request $request): View
    {
        $mobile = $request->query('mobile');

        return view('admin.upcoming-orders.create', [
            'order' => null,
            'customer' => is_string($mobile) && preg_match('/^[0-9]{10}$/', $mobile)
                ? Customer::where('mobile', $mobile)->first() : null,
            'products' => Product::where('status', 'active')->orderBy('name')->get(['id', 'name', 'status', 'deleted_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $lines = $this->lines($data['items'], []);

        $order = DB::transaction(function () use ($data, $lines): UpcomingOrder {
            $customer = Customer::createOrFirst(
                ['mobile' => $data['mobile']], ['name' => trim($data['customer_name'])]
            );
            $order = UpcomingOrder::create([
                'customer_id' => $customer->id, 'scheduled_date' => $data['scheduled_date'],
                'note' => $data['note'] ?? null,
            ]);
            $order->items()->createMany($lines);

            return $order;
        }, 3);

        return redirect()->route('upcoming-orders.show', $order)->with('success', 'Upcoming order saved.');
    }

    public function show(UpcomingOrder $upcomingOrder): View
    {
        $upcomingOrder->load(['customer', 'creator', 'editor', 'items']);

        return view('admin.upcoming-orders.show', ['order' => $upcomingOrder]);
    }

    public function edit(UpcomingOrder $upcomingOrder): View
    {
        $upcomingOrder->load(['customer', 'items']);
        $ids = $upcomingOrder->items->pluck('product_id');

        return view('admin.upcoming-orders.edit', [
            'order' => $upcomingOrder, 'customer' => $upcomingOrder->customer,
            'products' => Product::withTrashed()->where(function ($q) use ($ids): void {
                $q->where(fn ($active) => $active->where('status', 'active')->whereNull('deleted_at'))
                    ->orWhereIn('id', $ids);
            })->orderBy('name')->get(['id', 'name', 'status', 'deleted_at']),
        ]);
    }

    public function update(Request $request, UpcomingOrder $upcomingOrder): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($upcomingOrder, $data): void {
            $order = UpcomingOrder::whereKey($upcomingOrder->id)->lockForUpdate()->firstOrFail();
            $oldIds = $order->items()->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $lines = $this->lines($data['items'], $oldIds);
            $customer = Customer::createOrFirst(
                ['mobile' => $data['mobile']], ['name' => trim($data['customer_name'])]
            );
            $order->update([
                'customer_id' => $customer->id, 'scheduled_date' => $data['scheduled_date'],
                'note' => $data['note'] ?? null,
            ]);
            $order->items()->delete();
            $order->items()->createMany($lines);
            $order->forceFill(['updated_by' => auth()->id(), 'updated_at' => now()])->save();
        }, 3);

        return redirect()->route('upcoming-orders.show', $upcomingOrder)->with('success', 'Upcoming order updated.');
    }

    public function destroy(UpcomingOrder $upcomingOrder): RedirectResponse
    {
        $upcomingOrder->delete();

        return redirect()->route('upcoming-orders.index')->with('success', 'Upcoming order removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'regex:/^[0-9]{10}$/'],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.cartons' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ]);
    }

    private function lines(array $items, array $existingIds): array
    {
        $products = Product::withTrashed()->whereIn('id', array_column($items, 'product_id'))
            ->get()->keyBy('id');
        $lines = [];
        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);
            if (! $product || (($product->status !== 'active' || $product->trashed())
                && ! in_array($product->id, $existingIds, true))) {
                throw ValidationException::withMessages(["items.$index.product_id" => 'Select an active product.']);
            }
            $lines[] = [
                'product_id' => $product->id, 'product_name' => $product->name,
                'cartons' => (int) $item['cartons'],
            ];
        }

        return $lines;
    }
}
