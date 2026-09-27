<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\StockEntryItem;
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = StockEntry::query();

        if ($search = trim($filters['search'] ?? '')) {
            $query->whereHas('items.product', function ($product) use ($search): void {
                $product->withTrashed()->where('name', 'like', '%'.$search.'%');
            });
        }
        if ($from = $filters['from'] ?? null) {
            $query->where('entry_date', '>=', $from);
        }
        if ($to = $filters['to'] ?? null) {
            $query->where('entry_date', '<=', $to);
        }

        $summary = [
            'entries' => (clone $query)->count(),
            'cartons' => StockEntryItem::query()
                ->whereIn('stock_entry_id', (clone $query)->select('stock_entries.id'))
                ->sum('cartons'),
        ];

        $query->with('creator')->withCount('items')->withSum('items as carton_total', 'cartons');
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
            $entry = StockEntry::create(['entry_date' => $data['entry_date']]);
            $entry->items()->createMany($data['items']);
        });

        return redirect()->route('stock-entries.index')->with('success', 'Stock entry saved successfully.');
    }

    public function show(StockEntry $stockEntry): View
    {
        $stockEntry->load(['creator', 'items.product']);

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
            $entry->update(['entry_date' => $data['entry_date']]);
            $entry->items()->delete();
            $entry->items()->createMany($data['items']);
        });

        return redirect()->route('stock-entries.show', $stockEntry)->with('success', 'Stock entry updated successfully.');
    }

    private function validated(Request $request, ?StockEntry $entry = null): array
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.cartons' => ['required', 'integer', 'min:1', 'max:1000000000'],
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
