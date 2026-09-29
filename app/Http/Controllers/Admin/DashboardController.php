<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Attendance, Expense, PartnerTransaction, Product, Salary, Sale, SalePayment, StockEntry, StockEntryItem, UpcomingOrder, User};
use App\Support\{Access, ReportCatalog};
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $from = today()->startOfMonth()->toDateString();
        $next = today()->startOfMonth()->addMonth()->toDateString();
        $previous = today()->startOfMonth()->subMonth()->toDateString();
        $cards = [];
        $add = function (string $module, string $label, string $value, string $hint, string $icon, string $route) use (&$cards): void {
            if (Access::allowed($module) && ($module !== 'stock' || Access::all('stock'))) {
                $cards[] = compact('label', 'value', 'hint', 'icon', 'route');
            }
        };

        if (Access::allowed('sales')) {
            $sales = Access::scope(Sale::query(), 'sales')->where('approval_status', 'approved')
                ->where('sale_date', '>=', $from)->where('sale_date', '<', $next);
            $add('sales', 'Sales', 'Rs '.number_format((float) (clone $sales)->sum('total_rupees')),
                number_format((clone $sales)->count()).' approved invoices this month', 'mdi-cart-outline', 'sales.index');
        }
        if (Access::allowed('payments')) {
            $payments = Access::scope(SalePayment::query(), 'payments')->where('approval_status', 'approved')
                ->whereHas('sale', fn ($q) => $q->where('approval_status', 'approved'))
                ->where('payment_date', '>=', $from)->where('payment_date', '<', $next);
            $add('payments', 'Payments received', 'Rs '.number_format((float) $payments->sum('amount_rupees')),
                'Approved payments this month', 'mdi-cash-check', 'payments.index');
        }
        if (Access::allowed('stock') && Access::all('stock')) {
            $productIds = Product::query()->where('approval_status', 'approved')->select('id');
            $received = (int) StockEntryItem::whereIn('product_id', clone $productIds)
                ->whereHas('entry', fn ($q) => $q->where('approval_status', 'approved'))->sum('cartons');
            $sold = (int) \App\Models\SaleItem::whereIn('product_id', clone $productIds)
                ->whereHas('sale', fn ($q) => $q->where('approval_status', 'approved'))->sum('cartons');
            $add('stock', 'Available stock', number_format($received - $sold).' CTN',
                'Across all approved stock and sales', 'mdi-package-variant-closed', 'stock.overview');
        }
        if (Access::allowed('stock-entries')) {
            $entryIds = Access::scope(StockEntry::query(), 'stock-entries')
                ->where('approval_status', 'approved')->where('entry_date', '>=', $from)
                ->where('entry_date', '<', $next)->select('id');
            $add('stock-entries', 'Stock received', number_format((int) StockEntryItem::whereIn('stock_entry_id', $entryIds)->sum('cartons')).' CTN',
                'Approved entries this month', 'mdi-truck-delivery-outline', 'stock-entries.index');
        }
        if (Access::allowed('upcoming-orders')) {
            $orders = Access::scope(UpcomingOrder::query(), 'upcoming-orders')
                ->where('approval_status', 'approved')->whereDate('scheduled_date', '>=', today());
            $add('upcoming-orders', 'Upcoming orders', number_format($orders->count()),
                'Scheduled from today', 'mdi-calendar-clock-outline', 'upcoming-orders.index');
        }
        if (Access::allowed('expenses')) {
            $expenses = Access::scope(Expense::query(), 'expenses')->where('approval_status', 'approved')
                ->where('expense_date', '>=', $from)->where('expense_date', '<', $next);
            $add('expenses', 'Expenses', 'Rs '.number_format((float) $expenses->sum('amount_rupees')),
                'Approved spending this month', 'mdi-cash-minus', 'expenses.index');
        }
        if (Access::allowed('partner-ledger')) {
            $partners = Access::scope(PartnerTransaction::query(), 'partner-ledger')->where('approval_status', 'approved');
            $sent = (int) (clone $partners)->where('type', 'send')->sum('amount_rupees');
            $received = (int) (clone $partners)->where('type', 'receive')->sum('amount_rupees');
            $add('partner-ledger', 'Partner net sent', 'Rs '.number_format($sent - $received),
                'Approved send minus receive, all time', 'mdi-swap-horizontal', 'partner-ledger.index');
        }
        if (Access::allowed('attendance')) {
            $attendance = Access::scope(Attendance::query(), 'attendance')->where('work_date', '>=', $from)->where('work_date', '<', $next);
            $add('attendance', 'Attendance', number_format($attendance->count()),
                'Entries this month, including pending', 'mdi-calendar-check-outline', 'attendance.index');
        }
        if (Access::allowed('salaries')) {
            $salaries = Access::scope(Salary::query(), 'salaries')->whereDate('month', $previous);
            $add('salaries', 'Last month salary', 'Rs '.number_format((float) $salaries->sum('earned_paise') / 100, 2),
                'Generated salary for '.today()->startOfMonth()->subMonth()->format('M Y'), 'mdi-wallet-outline', 'salaries.index');
        }
        if (Access::allowed('products')) {
            $products = Access::scope(Product::query(), 'products')->where('approval_status', 'approved');
            $add('products', 'Products', number_format($products->count()), 'Approved products', 'mdi-package-variant', 'products.index');
        }
        if (Access::allowed('users')) {
            $users = Access::scope(User::query()->where('role', '!=', 'admin'), 'users')->where('approval_status', 'approved');
            $add('users', 'Users', number_format($users->count()), 'Active approved users', 'mdi-account-group-outline', 'users.index');
        }

        $shortcuts = [];
        foreach ([
            ['sales', 'Add Sale', 'sales.create'], ['payments', 'Add Payment', 'payments.create'],
            ['stock-entries', 'Add Stock', 'stock-entries.create'], ['upcoming-orders', 'Add Order', 'upcoming-orders.create'],
            ['expenses', 'Add Expense', 'expenses.create'], ['partner-ledger', 'Add Partner Entry', 'partner-ledger.create'],
            ['attendance', 'Add Attendance', 'attendance.create'],
        ] as [$module, $label, $route]) {
            if (Access::allowed($module, 'create')) $shortcuts[] = compact('label', 'route');
        }
        if (auth()->user()->role === 'admin') $shortcuts[] = ['label' => 'Generate Salary', 'route' => 'salaries.create'];

        $pending = null;
        if (auth()->user()->role === 'admin') {
            $pending = 0;
            foreach ([User::class, Product::class, StockEntry::class, Sale::class, SalePayment::class,
                Expense::class, PartnerTransaction::class, UpcomingOrder::class, Attendance::class] as $model) {
                $pending += $model::query()->where('approval_status', 'pending')->count();
            }
        }
        return view('admin.dashboard', [
            'cards' => $cards, 'shortcuts' => $shortcuts, 'pending' => $pending,
            'canSeeReports' => ! empty(ReportCatalog::visible()),
        ]);
    }
}
