<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockOverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'availability' => ['nullable', Rule::in(['available', 'empty'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Product::query()
            ->withSum('stockItems as stock_received', 'cartons')
            ->withSum('soldItems as stock_sold', 'cartons');
        if ($search = trim($filters['search'] ?? '')) $query->where('name', 'like', '%'.$search.'%');
        if ($status = $filters['status'] ?? null) $query->where('status', $status);

        // Same active-entry and active-sale rules as StockBalance and the sale form.
        $balanceSql = '(SELECT COALESCE(SUM(i.cartons),0) FROM stock_entry_items i JOIN stock_entries e ON e.id=i.stock_entry_id AND e.deleted_at IS NULL WHERE i.product_id=products.id) - (SELECT COALESCE(SUM(i.cartons),0) FROM sale_items i JOIN sales s ON s.id=i.sale_id AND s.deleted_at IS NULL WHERE i.product_id=products.id)';
        if (($filters['availability'] ?? null) === 'available') $query->whereRaw($balanceSql.' > 0');
        if (($filters['availability'] ?? null) === 'empty') $query->whereRaw($balanceSql.' <= 0');

        $summary = DB::query()->fromSub((clone $query)->toBase(), 'inventory')
            ->selectRaw('COUNT(*) AS products, COALESCE(SUM(stock_received),0) AS received, COALESCE(SUM(stock_sold),0) AS sold')
            ->first();
        $products = $query->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();

        return $request->ajax()
            ? view('admin.stock.partials.overview-results', compact('products', 'summary'))
            : view('admin.stock.overview', compact('products', 'summary'));
    }
}
