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
        if ($name === 'sales.show') $action = 'invoice';
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
                abort_unless(Access::record($module, $action, $record), 403);
            }
        }

        return $next($request);
    }
}
