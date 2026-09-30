<?php

namespace App\Support;

use App\Models\{Attendance, Expense, PartnerTransaction, Product, Salary, Sale, SalePayment, StockEntry, StockEntryItem, UpcomingOrder, UpcomingOrderItem, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ReportCatalog
{
    public const LABELS = [
        'sales' => 'Sales', 'payments' => 'Customer payments',
        'stock-entries' => 'Stock entries', 'stock' => 'Available stock',
        'expenses' => 'Expenses', 'partner-ledger' => 'Partner status',
        'upcoming-orders' => 'Upcoming orders', 'attendance' => 'Attendance',
        'salaries' => 'Salary', 'products' => 'Products', 'users' => 'Users',
    ];

    public static function visible(): array
    {
        return array_filter(self::LABELS, fn ($label, $key) => Access::allowed($key)
            && ($key !== 'stock' || Access::all('stock')), ARRAY_FILTER_USE_BOTH);
    }

    public static function authorize(string $module): void
    {
        abort_unless(isset(self::LABELS[$module]), 404);
        abort_unless(Access::allowed($module) && ($module !== 'stock' || Access::all('stock')), 403);
    }

    public static function query(string $module, array $filters): Builder
    {
        self::authorize($module);
        $query = match ($module) {
            'sales' => Access::scope(Sale::query()->where('is_draft', false)->with(['customer', 'creator'])
                ->withSum(['payments as credit_total' => fn ($q) => $q->where('approval_status', 'approved')->where('entry_type', 'credit')], 'amount_rupees')
                ->withSum(['payments as debit_total' => fn ($q) => $q->where('approval_status', 'approved')->where('entry_type', 'debit')], 'amount_rupees'), 'sales'),
            'payments' => Access::scope(SalePayment::query()->whereHas('sale')->with(['sale.customer', 'creator']), 'payments'),
            'stock-entries' => Access::scope(StockEntry::query()->with(['items.product', 'creator']), 'stock-entries'),
            'stock' => Product::query()->where('approval_status', 'approved')
                ->withSum('stockItems as stock_received', 'cartons')->withSum('soldItems as stock_sold', 'cartons'),
            'expenses' => Access::scope(Expense::query()->with(['category', 'creator']), 'expenses'),
            'partner-ledger' => Access::scope(PartnerTransaction::query()->with(['partner', 'creator']), 'partner-ledger'),
            'upcoming-orders' => Access::scope(UpcomingOrder::query()->with(['customer', 'creator'])->withSum('items as carton_total', 'cartons'), 'upcoming-orders'),
            'attendance' => Access::scope(Attendance::query()->with(['employee', 'creator']), 'attendance'),
            'salaries' => Access::scope(Salary::query()->with(['employee', 'creator'])
                ->select('salaries.*')->withSum('payments as paid_total', 'amount_paise')->selectSub(
                    DB::table('salary_advances')->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN -amount_paise ELSE amount_paise END), 0)")
                        ->whereColumn('salary_advances.employee_id', 'salaries.employee_id')
                        ->whereColumn('salary_advances.month', 'salaries.month'),
                    'advance_total'
                ), 'salaries'),
            'products' => Access::scope(Product::query()->with('creator'), 'products'),
            'users' => Access::scope(User::query()->where('role', '!=', 'admin')->with('creator'), 'users'),
        };

        if (! in_array($module, ['salaries', 'stock'], true) && ($filters['approval'] ?? null)) {
            $query->where('approval_status', $filters['approval']);
        }
        $dateColumn = match ($module) {
            'sales' => 'sale_date', 'payments' => 'payment_date', 'stock-entries' => 'entry_date',
            'expenses' => 'expense_date', 'partner-ledger' => 'transaction_date',
            'upcoming-orders' => 'scheduled_date', 'attendance' => 'work_date',
            'salaries' => 'month', 'stock' => null, default => 'created_at',
        };
        if ($dateColumn) {
            if ($filters['month'] ?? null) {
                $query->whereDate($dateColumn, '>=', $filters['month'].'-01')
                    ->whereDate($dateColumn, '<', \Illuminate\Support\Carbon::createFromFormat('!Y-m', $filters['month'])->addMonth()->toDateString());
            }
            if ($filters['from'] ?? null) $query->whereDate($dateColumn, '>=', $filters['from']);
            if ($filters['to'] ?? null) $query->whereDate($dateColumn, '<=', $filters['to']);
        }
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function (Builder $q) use ($module, $search, $filters): void {
                $like = '%'.$search.'%';
                match ($module) {
                    'sales' => $q->where('invoice_no', 'like', $like)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('mobile', 'like', $like)),
                    'payments' => $q->whereHas('sale', fn ($s) => $s->where('invoice_no', 'like', $like)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('mobile', 'like', $like))),
                    'stock-entries' => $q->whereHas('items', fn ($i) => $i
                        ->when(($filters['type'] ?? null) && in_array($filters['type'], ['manufacture', 'purchase'], true),
                            fn ($items) => $items->where('type', $filters['type']))
                        ->whereHas('product', fn ($p) => $p->where('name', 'like', $like))),
                    'stock', 'products' => $q->where('name', 'like', $like),
                    'expenses' => $q->where('title', 'like', $like)->orWhere('notes', 'like', $like),
                    'partner-ledger' => $q->whereHas('partner', fn ($p) => $p->where('name', 'like', $like))->orWhere('note', 'like', $like),
                    'upcoming-orders' => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('mobile', 'like', $like))
                        ->orWhereHas('items', fn ($i) => $i->where('product_name', 'like', $like)),
                    'attendance', 'salaries' => $q->whereHas('employee', fn ($e) => $e->where('name', 'like', $like)->orWhere('mobile', 'like', $like)),
                    'users' => $q->where('name', 'like', $like)->orWhere('mobile', 'like', $like),
                };
            });
        }

        if (($filters['type'] ?? null) && in_array($module, ['stock-entries', 'partner-ledger', 'attendance'], true)) {
            if ($module === 'stock-entries') $query->whereHas('items', fn ($i) => $i->where('type', $filters['type']));
            else $query->where('type', $filters['type']);
        }
        if ($module === 'stock-entries') {
            $stockType = in_array($filters['type'] ?? null, ['manufacture', 'purchase'], true) ? $filters['type'] : null;
            if ($stockType) $query->with(['items' => fn ($items) => $items->where('type', $stockType)->with('product')]);
            $query->withSum(['items as carton_total' => fn ($items) => $items->when($stockType, fn ($q) => $q->where('type', $stockType))], 'cartons');
        }
        if ($module === 'payments' && ($filters['method'] ?? null)) $query->where('method', $filters['method']);
        if ($module === 'expenses' && ($filters['category'] ?? null)) $query->where('expense_category_id', $filters['category']);
        if ($module === 'users' && ($filters['role'] ?? null)) $query->where('role', $filters['role']);
        if (in_array($module, ['products', 'stock'], true) && in_array($filters['status'] ?? null, ['active', 'inactive'], true)) {
            $query->where('status', $filters['status']);
        }
        if ($module === 'stock' && ($filters['availability'] ?? null)) {
            $balance = "(SELECT COALESCE(SUM(i.cartons),0) FROM stock_entry_items i JOIN stock_entries e ON e.id=i.stock_entry_id AND e.deleted_at IS NULL AND e.approval_status='approved' WHERE i.product_id=products.id) - (SELECT COALESCE(SUM(i.cartons),0) FROM sale_items i JOIN sales s ON s.id=i.sale_id AND s.deleted_at IS NULL AND s.approval_status='approved' WHERE i.product_id=products.id)";
            $query->whereRaw($balance.(($filters['availability'] === 'available') ? ' > 0' : ' <= 0'));
        }
        if ($module === 'sales' && in_array($filters['status'] ?? null, ['paid', 'due'], true)) {
            $balance = "sales.total_rupees - (SELECT COALESCE(SUM(CASE WHEN entry_type='debit' THEN -amount_rupees ELSE amount_rupees END),0) FROM sale_payments WHERE sale_payments.sale_id=sales.id AND sale_payments.deleted_at IS NULL AND sale_payments.approval_status='approved')";
            $query->whereRaw($balance.(($filters['status'] === 'paid') ? ' <= 0' : ' > 0'));
        }
        if ($module === 'salaries' && in_array($filters['status'] ?? null, ['due', 'settled'], true)) {
            $balance = "(salaries.earned_paise - (SELECT COALESCE(SUM(amount_paise),0) FROM salary_payments WHERE salary_id=salaries.id) - (SELECT COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN -amount_paise ELSE amount_paise END),0) FROM salary_advances WHERE employee_id=salaries.employee_id AND month=salaries.month))";
            $query->whereRaw($balance.(($filters['status'] === 'due') ? ' > 0' : ' <= 0'));
        }

        $sort = $filters['sort'] ?? null;
        if ($module === 'stock') return $query->orderBy('name')->orderBy('id');
        if ($sort === 'name' && in_array($module, ['users', 'products'], true)) return $query->orderBy('name')->orderBy('id');
        if ($sort === 'oldest' || $sort === 'soonest' || ($module === 'upcoming-orders' && $sort !== 'latest')) {
            return $query->orderBy($dateColumn ?? 'id')->orderBy('id');
        }
        return $query->orderByDesc($dateColumn ?? 'id')->orderByDesc('id');
    }

    public static function headings(string $module): array
    {
        return match ($module) {
            'sales' => ['Date', 'Invoice', 'Party', 'Mobile', 'Total (Rs)', 'Paid (Rs)', 'Due (Rs)', 'Approval', 'Added by'],
            'payments' => ['Date', 'Invoice', 'Party', 'Type', 'Method', 'Amount (Rs)', 'Approval', 'Added by'],
            'stock-entries' => ['Date', 'Entry', 'Products / type', 'Cartons', 'Approval', 'Added by'],
            'stock' => ['Product', 'Status', 'Received CTN', 'Sold CTN', 'Available CTN'],
            'expenses' => ['Date', 'Title', 'Category', 'Amount (Rs)', 'Notes', 'Approval', 'Added by'],
            'partner-ledger' => ['Date', 'Partner', 'Type', 'Amount (Rs)', 'Note', 'Approval', 'Added by'],
            'upcoming-orders' => ['Date', 'Customer', 'Mobile', 'Cartons', 'Note', 'Approval', 'Added by'],
            'attendance' => ['Date', 'Employee', 'Type', 'Hours', 'Note', 'Approval', 'Added by'],
            'salaries' => ['Month', 'Employee', 'Earned (Rs)', 'Credit minus debit (Rs)', 'Paid (Rs)', 'Month due (Rs)', 'Generated by'],
            'products' => ['Product', 'Status', 'Approval', 'Added by'],
            'users' => ['Name', 'Mobile', 'Role', 'Salary (Rs)', 'Approval', 'Added by'],
        };
    }

    public static function summary(string $module, Builder $query, array $filters = []): ?array
    {
        $approved = clone $query;
        if (! in_array($module, ['salaries', 'stock'], true)) $approved->where('approval_status', 'approved');
        [$label, $value] = match ($module) {
            'sales' => ['Approved sales', (float) $approved->sum('total_rupees')],
            'payments' => ['Net received', (float) (clone $approved)->whereHas('sale', fn ($q) => $q->where('approval_status', 'approved'))->where('entry_type', 'credit')->sum('amount_rupees')
                - (float) (clone $approved)->whereHas('sale', fn ($q) => $q->where('approval_status', 'approved'))->where('entry_type', 'debit')->sum('amount_rupees')],
            'expenses' => ['Approved expenses', (float) $approved->sum('amount_rupees')],
            'partner-ledger' => ['Net sent', (float) (clone $approved)->where('type', 'send')->sum('amount_rupees')
                - (float) (clone $approved)->where('type', 'receive')->sum('amount_rupees')],
            'salaries' => ['Salary earned', (float) $approved->sum('earned_paise') / 100],
            'stock-entries' => ['Approved cartons', (float) StockEntryItem::whereIn('stock_entry_id', $approved->reorder()->select('id'))
                ->when(in_array($filters['type'] ?? null, ['manufacture', 'purchase'], true),
                    fn ($items) => $items->where('type', $filters['type']))->sum('cartons')],
            'upcoming-orders' => ['Approved order cartons', (float) UpcomingOrderItem::whereIn('upcoming_order_id', $approved->reorder()->select('id'))->sum('cartons')],
            'attendance' => ['Approved hours', (float) $approved->sum('minutes') / 60],
            default => [null, 0],
        };
        if ($label === null) return null;
        $unit = in_array($module, ['stock-entries', 'upcoming-orders'], true) ? ' CTN'
            : ($module === 'attendance' ? ' hours' : '');
        $prefix = $unit === '' ? 'Rs ' : '';
        return ['label' => $label, 'value' => $prefix.number_format($value, in_array($module, ['salaries', 'attendance', 'sales', 'payments'], true) ? 2 : 0).$unit];
    }

    public static function row(string $module, $record): array
    {
        $by = $record->creator?->name ?? '—';
        $approval = ucfirst($record->approval_status ?? 'approved');
        $rupees = fn ($amount) => number_format((float) $amount, 2, '.', ',');
        $paise = fn ($amount) => number_format(((int) $amount) / 100, 2, '.', ',');
        $paid = \App\Support\SaleMoney::decimal(\App\Support\SaleMoney::paise((string) ($record->credit_total ?? 0))
            - \App\Support\SaleMoney::paise((string) ($record->debit_total ?? 0)));
        return match ($module) {
            'sales' => [$record->sale_date->format('d M Y'), $record->invoice_no, $record->customer?->name, $record->customer?->mobile,
                $rupees($record->total_rupees), $rupees($paid),
                $rupees(\App\Support\SaleMoney::decimal(max(0, \App\Support\SaleMoney::paise($record->total_rupees) - \App\Support\SaleMoney::paise($paid)))), $approval, $by],
            'payments' => [$record->payment_date->format('d M Y'), $record->sale?->invoice_no, $record->sale?->customer?->name,
                ucfirst($record->entry_type), ucfirst($record->method), $rupees($record->amount_rupees), $approval, $by],
            'stock-entries' => [$record->entry_date->format('d M Y'), '#'.$record->id,
                $record->items->map(fn ($i) => ($i->product?->name ?? 'Product').': '.($i->type ?: 'Not set'))->implode(', '),
                (string) ($record->carton_total ?? 0), $approval, $by],
            'stock' => [$record->name, ucfirst($record->status), (string) ($record->stock_received ?? 0),
                (string) ($record->stock_sold ?? 0), (string) ((int) ($record->stock_received ?? 0) - (int) ($record->stock_sold ?? 0))],
            'expenses' => [$record->expense_date->format('d M Y'), $record->title, $record->category?->name,
                $rupees($record->amount_rupees), $record->notes, $approval, $by],
            'partner-ledger' => [$record->transaction_date->format('d M Y'), $record->partner?->name,
                ucfirst($record->type), $rupees($record->amount_rupees), $record->note, $approval, $by],
            'upcoming-orders' => [$record->scheduled_date->format('d M Y'), $record->customer?->name, $record->customer?->mobile,
                (string) ($record->carton_total ?? 0), $record->note, $approval, $by],
            'attendance' => [$record->work_date->format('d M Y'), $record->employee?->name, ucfirst($record->type),
                number_format($record->minutes / 60, 2), $record->note, $approval, $by],
            'salaries' => [$record->month->format('M Y'), $record->employee?->name, $paise($record->earned_paise),
                $paise($record->advance_total ?? 0), $paise($record->paid_total ?? 0),
                $paise((int) $record->earned_paise - (int) ($record->advance_total ?? 0) - (int) ($record->paid_total ?? 0)), $by],
            'products' => [$record->name, ucfirst($record->status), $approval, $by],
            'users' => [$record->name, $record->mobile, ucfirst($record->role), number_format((float) $record->salary, 2, '.', ','), $approval, $by],
        };
    }
}
