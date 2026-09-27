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
        if (Auth::check()) {
            return redirect()->route(
                Auth::user()->role === 'admin'
                    ? 'admin.dashboard'
                    : 'user.dashboard'
            );
        }

        return view('auth.login');
    }

    public function authenticate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'regex:/^[0-9]{10}$/'],
            'password' => ['required', 'string'],
        ]);

        $rateKey = 'login:'.sha1($data['mobile'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return response()->json([
                'message' => 'Too many login attempts. Try again in '
                    .RateLimiter::availableIn($rateKey).' seconds.',
            ], 429);
        }

        if (! Auth::attempt([
            'mobile' => $data['mobile'],
            'password' => $data['password'],
        ])) {
            RateLimiter::hit($rateKey, 60);

            return response()->json([
                'message' => 'Mobile number or password is incorrect.',
            ], 422);
        }

        RateLimiter::clear($rateKey);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login successful.',
            'redirect' => route(
                Auth::user()->role === 'admin'
                    ? 'admin.dashboard'
                    : 'user.dashboard'
            ),
        ]);
    }

    public function adminDashboard(): View
    {
        return view('dashboard', [
            'heading' => 'Admin Dashboard',
        ]);
    }

    public function userDashboard(): View
    {
        return view('dashboard', [
            'heading' => 'User Dashboard',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
