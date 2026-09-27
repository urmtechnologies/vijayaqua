<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Product::query();

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
            $editingProduct = Product::find((int) old('product_id'));
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
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        $product->update($request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('products', 'name')->ignore($product->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]));

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }
}
