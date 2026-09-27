<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class Usercontroller extends Controller
{
    public function login(): View|RedirectResponse
    {
        return Auth::check() && Auth::user()->role === 'admin'
            ? redirect()->route('dashboard')
            : view('auth.login');
    }

    public function authenticate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'regex:/^[0-9]{10}$/'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login:'.sha1($data['mobile'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ], 429);
        }

        // Staff accounts are created now; their login is intentionally disabled.
        if (! Auth::attempt([
            'mobile' => $data['mobile'],
            'password' => $data['password'],
            'role' => 'admin',
        ])) {
            RateLimiter::hit($key, 60);

            return response()->json(['message' => 'Mobile number or password is incorrect.'], 422);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('dashboard')]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
