<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\Usercontroller as LoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/login', [LoginController::class, 'login'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.submit');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('users', UserController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::get('/users/new', [UserController::class, 'create'])->name('users.create');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
