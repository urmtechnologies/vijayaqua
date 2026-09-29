<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class Access
{
    public static function allowed(string $module, string $action = 'view', ?User $user = null): bool
    {
        $user ??= Auth::user();
        if (! $user) return false;
        if ($user->role === 'admin') return true;

        $permission = $user->permissions->firstWhere('module', $module);

        return (bool) ($permission?->{'can_'.$action} ?? false);
    }

    public static function all(string $module, ?User $user = null): bool
    {
        $user ??= Auth::user();
        return $user?->role === 'admin' || $user?->permissions->firstWhere('module', $module)?->scope === 'all';
    }

    public static function scope(Builder $query, string $module): Builder
    {
        $ownerColumn = in_array($module, ['attendance', 'salaries'], true) ? 'employee_id' : 'user_id';
        return self::all($module) ? $query : $query->where($ownerColumn, Auth::id());
    }

    public static function record(string $module, string $action, Model $record): bool
    {
        if (! self::allowed($module, $action)) return false;
        $owner = in_array($module, ['attendance', 'salaries'], true) && $action === 'view'
            ? $record->employee_id : $record->user_id;
        if (! self::all($module) && (int) $owner !== Auth::id()) return false;
        if ($module === 'attendance' && in_array($action, ['edit', 'delete'], true)
            && Auth::user()->role !== 'admin' && ((int) $record->employee_id !== Auth::id() || (int) $record->user_id !== Auth::id())) return false;
        if (in_array($action, ['edit', 'delete'], true) && Auth::user()->role !== 'admin'
            && $record->approval_status !== 'pending') return false;

        return true;
    }

    public static function canEdit(string $module, Model $record): bool
    {
        if ($module === 'sales' && $record instanceof \App\Models\Sale && $record->is_draft
            && self::allowed('sales', 'create') && (int) $record->user_id === Auth::id()) return true;
        return self::record($module, 'edit', $record);
    }

    public static function canDelete(string $module, Model $record): bool
    {
        return self::record($module, 'delete', $record);
    }
}
