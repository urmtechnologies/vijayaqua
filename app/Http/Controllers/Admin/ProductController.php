<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name'])],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Product::query()->with(['creator', 'editor', 'approver']);
        $query = Access::scope($query, 'products');
        if ($approval = $filters['approval'] ?? null) $query->where('approval_status', $approval);

        if ($search = trim($filters['search'] ?? '')) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('id'),
        };

        $products = $query->paginate(10)->withQueryString();

        $editingProduct = null;
        if ($request->session()->has('errors') && old('form_context') === 'edit') {
            $editingProduct = Access::scope(Product::query(), 'products')->find((int) old('product_id'));
        }

        return $request->ajax()
            ? view('admin.products.partials.results', compact('products'))
            : view('admin.products.index', compact('products', 'editingProduct'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        Product::create($request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('products', 'name')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]));

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless(Access::canEdit('products', $product), 403);
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('products', 'name')->ignore($product->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        DB::transaction(function () use ($product, $data): void {
            $record = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canEdit('products', $record), 403);
            $record->update($data);
        }, 3);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        abort_unless(Access::canDelete('products', $product), 403);
        DB::transaction(function () use ($product): void {
            $record = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('products', $record), 403);
            $record->delete();
        }, 3);

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }
}
