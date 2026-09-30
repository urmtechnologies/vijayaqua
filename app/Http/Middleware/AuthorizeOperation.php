<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeOperation
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()->getName();
        if ($name === 'dashboard' || str_starts_with($name, 'password.') || $name === 'logout'
            || str_starts_with($name, 'reports.')) return $next($request);

        if (str_starts_with($name, 'approvals.')) {
            abort_unless($request->user()->role === 'admin', 403);
            return $next($request);
        }

        $module = explode('.', $name)[0];
        if ($module === 'customers') $module = 'sales';
        if ($module === 'partners') $module = 'partner-ledger';
        $verb = substr($name, strrpos($name, '.') + 1);
        $action = match ($verb) {
            'create', 'store' => 'create', 'edit', 'update' => 'edit', 'destroy' => 'delete',
            default => 'view',
        };
        if ($name === 'attendance.leave') $action = 'create';
        if ($name === 'sales.show') $action = 'invoice';
        if (in_array($name, ['sales.index', 'customers.show'], true)
            && Access::allowed('sales', 'create')) $action = 'create';
        if (in_array($name, ['sales.edit', 'sales.update'], true)
            && $request->route('sale')?->is_draft && Access::allowed('sales', 'create')) $action = 'create';
        // The owner can view a completed invoice and record its payments without sale edit permission.
        if ($name === 'sales.edit' && $request->route('sale') instanceof \App\Models\Sale
            && ! Access::canEdit('sales', $request->route('sale'))
            && (int) $request->route('sale')->user_id === (int) $request->user()->id
            && (Access::allowed('sales', 'view') || Access::allowed('sales', 'create'))) {
            $action = Access::allowed('sales', 'view') ? 'view' : 'create';
        }
        if ($name === 'customers.lookup') {
            abort_unless(Access::allowed('sales', 'view') || Access::allowed('sales', 'create')
                || Access::allowed('upcoming-orders', 'create'), 403);
        } else {
            abort_unless(Access::allowed($module, $action), 403);
        }
        if ($name === 'stock.overview') abort_unless(Access::all('stock'), 403);

        foreach ($request->route()->parameters() as $record) {
            if ($record instanceof \Illuminate\Database\Eloquent\Model) {
                // Accounts and customers are shared identities; their lists are scoped by child records.
                if (in_array($module, ['sales', 'partner-ledger'], true)
                    && in_array($name, ['customers.show', 'partners.show'], true)) break;
                if (in_array($name, ['sales.edit', 'sales.update'], true) && $record instanceof \App\Models\Sale
                    && $record->is_draft && Access::allowed('sales', 'create')
                    && (int) $record->user_id === (int) $request->user()->id) continue;
                abort_unless(Access::record($module, $action, $record), 403);
            }
        }

        return $next($request);
    }
}
