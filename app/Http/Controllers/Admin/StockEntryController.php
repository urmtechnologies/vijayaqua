<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\StockEntryItem;
use App\Support\StockBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockEntryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['manufacture', 'purchase'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = StockEntry::query();

        $search = trim($filters['search'] ?? '');
        $type = $filters['type'] ?? null;
        $matchedItem = function ($item) use ($search, $type): void {
            if ($type) $item->where('type', $type);
            if ($search) $item->whereHas('product', fn ($product) => $product->withTrashed()->where('name', 'like', '%'.$search.'%'));
        };
        if ($search || $type) {
            $query->whereHas('items', $matchedItem);
        }
        if ($from = $filters['from'] ?? null) {
            $query->whereDate('entry_date', '>=', $from);
        }
        if ($to = $filters['to'] ?? null) {
            $query->whereDate('entry_date', '<=', $to);
        }

        $summary = [
            'entries' => (clone $query)->count(),
            'cartons' => StockEntryItem::query()
                ->whereIn('stock_entry_id', (clone $query)->select('stock_entries.id'))
                ->when($search || $type, $matchedItem)
                ->sum('cartons'),
        ];

        $query->with(['creator', 'editor', 'items:id,stock_entry_id,type'])
            ->withCount(['items' => $matchedItem])
            ->withSum(['items as carton_total' => $matchedItem], 'cartons');
        if (($filters['sort'] ?? 'newest') === 'oldest') {
            $query->orderBy('entry_date')->orderBy('id');
        } else {
            $query->orderByDesc('entry_date')->orderByDesc('id');
        }
        $entries = $query->paginate(10)->withQueryString();

        return $request->ajax()
            ? view('admin.stock.partials.results', compact('entries', 'summary'))
            : view('admin.stock.index', compact('entries', 'summary'));
    }

    public function create(): View
    {
        return view('admin.stock.create', [
            'entry' => null,
            'products' => $this->availableProducts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $entry = StockEntry::create(['entry_date' => $data['entry_date'], 'type' => $this->singleType($data['items'])]);
            $entry->items()->createMany($data['items']);
        });

        return redirect()->route('stock-entries.index')->with('success', 'Stock entry saved successfully.');
    }

    public function show(StockEntry $stockEntry): View
    {
        $stockEntry->load(['creator', 'editor', 'items.product']);

        return view('admin.stock.show', ['entry' => $stockEntry]);
    }

    public function edit(StockEntry $stockEntry): View
    {
        $stockEntry->load('items');

        return view('admin.stock.edit', [
            'entry' => $stockEntry,
            'products' => $this->availableProducts($stockEntry),
        ]);
    }

    public function update(Request $request, StockEntry $stockEntry): RedirectResponse
    {
        $data = $this->validated($request, $stockEntry);

        DB::transaction(function () use ($stockEntry, $data): void {
            $entry = StockEntry::query()->lockForUpdate()->findOrFail($stockEntry->id);
            $old = $entry->items()->pluck('cartons', 'product_id');
            $new = collect($data['items'])->pluck('cartons', 'product_id');
            $productIds = $old->keys()->merge($new->keys())->unique()->sort()->values();
            Product::withTrashed()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();

            foreach ($productIds as $productId) {
                $receivedAfterEdit = StockBalance::received((int) $productId)
                    - (int) $old->get($productId, 0) + (int) $new->get($productId, 0);
                if ($receivedAfterEdit < StockBalance::sold((int) $productId)) {
                    throw ValidationException::withMessages([
                        'items' => 'This edit would reduce stock below cartons already sold.',
                    ]);
                }
            }
            $entry->update(['entry_date' => $data['entry_date'], 'type' => $this->singleType($data['items'])]);
            $entry->items()->delete();
            $entry->items()->createMany($data['items']);
            $entry->forceFill(['updated_by' => auth()->id(), 'updated_at' => now()])->save();
        });

        return redirect()->route('stock-entries.show', $stockEntry)->with('success', 'Stock entry updated successfully.');
    }

    public function destroy(StockEntry $stockEntry): RedirectResponse
    {
        DB::transaction(function () use ($stockEntry): void {
            $entry = StockEntry::whereKey($stockEntry->id)->lockForUpdate()->firstOrFail();
            $old = $entry->items()->pluck('cartons', 'product_id');
            Product::withTrashed()->whereIn('id', $old->keys())->orderBy('id')->lockForUpdate()->get();
            foreach ($old as $productId => $cartons) {
                if (StockBalance::received((int) $productId) - (int) $cartons < StockBalance::sold((int) $productId)) {
                    throw ValidationException::withMessages(['stock_entry' => 'Cannot delete stock already used by sales.']);
                }
            }
            $entry->delete();
        }, 3);

        return redirect()->route('stock-entries.index')->with('success', 'Stock entry removed.');
    }

    private function singleType(array $items): ?string
    {
        $types = array_unique(array_column($items, 'type'));

        return count($types) === 1 ? $types[0] : null;
    }

    private function validated(Request $request, ?StockEntry $entry = null): array
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.cartons' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'items.*.type' => ['required', Rule::in(['manufacture', 'purchase'])],
        ]);

        $existingIds = $entry ? $entry->items()->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [];
        $selected = Product::withTrashed()
            ->whereIn('id', array_column($data['items'], 'product_id'))
            ->get()->keyBy('id');

        foreach ($data['items'] as $index => $item) {
            $product = $selected->get($item['product_id']);
            if (! $product || (($product->status !== 'active' || $product->trashed())
                && ! in_array($product->id, $existingIds, true))) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" => 'Select an active product.',
                ]);
            }
        }

        return $data;
    }

    private function availableProducts(?StockEntry $entry = null)
    {
        $existingIds = $entry ? $entry->items()->pluck('product_id')->all() : [];

        return Product::withTrashed()
            ->where(function ($query) use ($existingIds): void {
                $query->where(function ($active): void {
                    $active->where('status', 'active')->whereNull('deleted_at');
                })->orWhereIn('id', $existingIds);
            })
            ->orderBy('name')->get(['id', 'name', 'status', 'deleted_at']);
    }
}
