<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserAccess
{
    public function handle(
        Request $request,
        Closure $next,
        ?string $role = null
    ): Response {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Please sign in to continue.',
                ], 401);
            }

            return redirect()->route('login');
        }

        if ($role !== null && Auth::user()->role !== $role) {
            abort(403, 'You do not have access to this page.');
        }

        return $next($request);
    }
}
