<?php

use App\Http\Controllers\Auth\Usercontroller;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route(
        auth()->user()->role === 'admin'
            ? 'admin.dashboard'
            : 'user.dashboard'
    );
});

Route::get('/login', [Usercontroller::class, 'login'])
    ->name('login');

Route::post('/login', [Usercontroller::class, 'authenticate'])
    ->name('login.submit');

Route::middleware('login.check')->group(function () {
    Route::post('/logout', [Usercontroller::class, 'logout'])->name('logout');
});

Route::get('/dashboard', [Usercontroller::class, 'userDashboard'])
    ->middleware('login.check:user')
    ->name('user.dashboard');

Route::get('/admin/dashboard', [Usercontroller::class, 'adminDashboard'])
    ->middleware('login.check:admin')
    ->name('admin.dashboard');
