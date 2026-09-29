<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Access;

trait RequiresApproval
{
    protected static function bootRequiresApproval(): void
    {
        static::creating(function ($record): void {
            $record->approval_status = Auth::check() && Auth::user()->role !== 'admin' ? 'pending' : 'approved';
        });
        static::updating(function ($record): void {
            if (! Auth::check() || Auth::user()->role === 'admin') return;
            if ($record instanceof \App\Models\Sale && $record->getOriginal('is_draft')
                && (int) $record->user_id === Auth::id() && Access::allowed('sales', 'create')
                && $record->newQuery()->whereKey($record->getKey())->value('approval_status') === 'pending') return;
            $module = self::approvalModule($record);
            abort_unless($module && Access::record($module, 'edit', $record)
                && $record->newQuery()->whereKey($record->getKey())->value('approval_status') === 'pending', 403);
        });
        static::deleting(function ($record): void {
            if (! Auth::check() || Auth::user()->role === 'admin') return;
            $module = self::approvalModule($record);
            abort_unless($module && Access::record($module, 'delete', $record)
                && $record->newQuery()->whereKey($record->getKey())->value('approval_status') === 'pending', 403);
        });
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    private static function approvalModule(object $record): ?string
    {
        return match (class_basename($record)) {
            'User' => 'users', 'Product' => 'products', 'StockEntry' => 'stock-entries',
            'Sale' => 'sales', 'SalePayment' => 'payments', 'Expense' => 'expenses',
            'PartnerTransaction' => 'partner-ledger', 'UpcomingOrder' => 'upcoming-orders',
            'Attendance' => 'attendance', default => null,
        };
    }
}
