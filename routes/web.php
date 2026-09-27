<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\StockEntryController;
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
    Route::resource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('stock-entries', StockEntryController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('/customers/lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/payments/lookup', [PaymentController::class, 'lookup'])->name('payments.lookup');
    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store']);
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
