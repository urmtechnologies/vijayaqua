<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Attendance, Expense, PartnerTransaction, Product, Sale, SalePayment, StockEntry, UpcomingOrder, User, VehicleEntry};
use App\Support\StockBalance;
use App\Support\{CustomerWallet, SaleMoney};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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
        'vehicle-entries' => VehicleEntry::class,
    ];

    public function index(Request $request): View
    {
        $request->validate([
            'module' => ['nullable', 'in:'.implode(',', array_keys(self::MODELS))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $counts = [];
        foreach (self::MODELS as $name => $model) {
            $countQuery = $model::query()->where('approval_status', 'pending');
            if ($name === 'sales') $countQuery->where('is_draft', false);
            if ($name === 'payments') $countQuery->whereHas('sale', fn ($sale) => $sale->where('approval_status', 'approved'));
            $counts[$name] = $countQuery->count();
        }
        $modules = self::MODELS;
        if ($request->filled('module')) $modules = [$request->input('module') => $modules[$request->input('module')]];
        $groups = [];
        foreach ($modules as $module => $model) {
            $query = $model::query()->where('approval_status', 'pending')->with('creator');
            if ($module === 'attendance') $query->with('employee');
            if ($module === 'vehicle-entries') $query->with('referenceUser');
            if ($module === 'payments') $query->whereHas('sale', fn ($sale) => $sale->where('approval_status', 'approved'));
            if ($module === 'sales') $query->where('is_draft', false);
            $groups[$module] = $query->orderBy('created_at')->orderBy('id')
                ->when($request->filled('module'), fn ($q) => $q->paginate(20)->withQueryString(), fn ($q) => $q->limit(10)->get());
        }

        $selected = $request->input('module', '');
        return $request->ajax()
            ? view('admin.approvals.partials.workspace', compact('groups', 'counts', 'selected'))
            : view('admin.approvals.index', compact('groups', 'counts', 'selected'));
    }

    public function approve(Request $request, string $module, int $id): RedirectResponse|JsonResponse
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
                if ($record->is_draft) throw ValidationException::withMessages(['approval' => 'Add products before approving this sale.']);
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
            if ($module === 'vehicle-entries') {
                User::withTrashed()->whereKey($record->user_id)->lockForUpdate()->firstOrFail();
                if (! $record->items()->exists()) {
                    throw ValidationException::withMessages(['approval' => 'Vehicle entry has no products.']);
                }
            }
            if ($module === 'payments') {
                $sale = Sale::whereKey($record->sale_id)->firstOrFail();
                $paid = $sale->netPaidPaise();
                $amount = SaleMoney::paise($record->amount_rupees);
                if ($sale->approval_status !== 'approved' || $sale->is_draft
                    || ($record->entry_type === 'credit' && $amount > SaleMoney::paise($sale->total_rupees) - $paid)
                    || ($record->entry_type === 'debit' && $amount > $paid)) {
                    throw ValidationException::withMessages(['approval' => 'Invoice is pending or the payment entry exceeds the available balance.']);
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
                CustomerWallet::refresh($record->customer_id);
            }
            if ($module === 'payments') CustomerWallet::refresh($record->sale->customer_id);
        }, 3);

        return $request->expectsJson() ? response()->json(['message' => 'Record approved.'])
            : back()->with('success', 'Record approved.');
    }
}
