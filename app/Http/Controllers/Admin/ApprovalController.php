<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Attendance, Expense, PartnerTransaction, Product, Sale, SalePayment, StockEntry, UpcomingOrder, User};
use App\Support\StockBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    private const MODELS = [
        'users' => User::class, 'products' => Product::class, 'stock-entries' => StockEntry::class,
        'sales' => Sale::class, 'payments' => SalePayment::class, 'expenses' => Expense::class,
        'partner-ledger' => PartnerTransaction::class, 'upcoming-orders' => UpcomingOrder::class,
        'attendance' => Attendance::class,
    ];

    public function index(Request $request): View
    {
        $request->validate([
            'module' => ['nullable', 'in:'.implode(',', array_keys(self::MODELS))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $modules = self::MODELS;
        if ($request->filled('module')) $modules = [$request->input('module') => $modules[$request->input('module')]];
        $groups = [];
        foreach ($modules as $module => $model) {
            $query = $model::query()->where('approval_status', 'pending')->with('creator');
            if ($module === 'attendance') $query->with('employee');
            if ($module === 'payments') $query->whereHas('sale', fn ($sale) => $sale->where('approval_status', 'approved'));
            $groups[$module] = $query->orderBy('created_at')->orderBy('id')
                ->when($request->filled('module'), fn ($q) => $q->paginate(20)->withQueryString(), fn ($q) => $q->limit(10)->get());
        }

        return view('admin.approvals.index', compact('groups'));
    }

    public function approve(string $module, int $id): RedirectResponse
    {
        abort_unless(isset(self::MODELS[$module]), 404);
        DB::transaction(function () use ($module, $id): void {
            if ($module === 'attendance') {
                $employeeId = Attendance::whereKey($id)->value('employee_id');
                User::withTrashed()->whereKey($employeeId)->lockForUpdate()->firstOrFail();
            }
            if ($module === 'payments') {
                $saleId = SalePayment::whereKey($id)->value('sale_id');
                Sale::whereKey($saleId)->lockForUpdate()->firstOrFail();
            }
            $record = self::MODELS[$module]::query()->lockForUpdate()->findOrFail($id);
            if ($record->approval_status !== 'pending') {
                throw ValidationException::withMessages(['approval' => 'This record has already been reviewed.']);
            }

            if ($module === 'sales') {
                $items = $record->items()->orderBy('product_id')->get();
                $products = Product::withTrashed()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($items as $item) {
                    $product = $products->get($item->product_id);
                    if (! $product || $product->trashed() || $product->status !== 'active' || $product->approval_status !== 'approved') {
                        throw ValidationException::withMessages(['approval' => 'An invoice product is no longer active.']);
                    }
                    if (StockBalance::available($item->product_id) < $item->cartons) {
                        throw ValidationException::withMessages(['approval' => 'Not enough approved stock for this sale.']);
                    }
                }
            }
            if ($module === 'payments') {
                $sale = Sale::whereKey($record->sale_id)->firstOrFail();
                if ($sale->approval_status !== 'approved'
                    || (int) $sale->total_rupees - (int) $sale->payments()->where('approval_status', 'approved')->sum('amount_rupees') < (int) $record->amount_rupees) {
                    throw ValidationException::withMessages(['approval' => 'Invoice is pending or the payment exceeds its due amount.']);
                }
            }
            if ($module === 'attendance' && \App\Models\Salary::query()
                ->where('employee_id', $record->employee_id)
                ->whereDate('month', $record->work_date->format('Y-m-01'))->exists()) {
                throw ValidationException::withMessages(['approval' => 'This month’s salary is already generated.']);
            }

            $record->forceFill(['approval_status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()])->saveQuietly();
            if ($module === 'sales') {
                $record->payments()->where('approval_status', 'pending')->where('user_id', $record->user_id)
                    ->update(['approval_status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            }
        }, 3);

        return back()->with('success', 'Record approved.');
    }
}
