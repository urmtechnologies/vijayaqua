<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PartnerLedgerController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\StockEntryController;
use App\Http\Controllers\Admin\StockOverviewController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UpcomingOrderController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\SalaryController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\Usercontroller as LoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/login', [LoginController::class, 'login'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.submit');

Route::middleware(['auth', 'operation'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{module}/{format}', [ReportController::class, 'export'])->name('reports.export');
    Route::resource('users', UserController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::get('/users/new', [UserController::class, 'create'])->name('users.create');
    Route::resource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/stock', StockOverviewController::class)->name('stock.overview');
    Route::resource('stock-entries', StockEntryController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('/customers/lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('/payments/lookup', [PaymentController::class, 'lookup'])->name('payments.lookup');
    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::get('/partners/{partner}', [PartnerLedgerController::class, 'account'])->name('partners.show');
    Route::resource('partner-ledger', PartnerLedgerController::class)
        ->parameters(['partner-ledger' => 'transaction'])
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::resource('upcoming-orders', UpcomingOrderController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::resource('attendance', AttendanceController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::get('/salaries/preview', [SalaryController::class, 'preview'])->name('salaries.preview');
    Route::post('/salaries/advances', [SalaryController::class, 'advance'])->name('salaries.advance');
    Route::post('/salaries/{salary}/payments', [SalaryController::class, 'payment'])->name('salaries.payment');
    Route::resource('salaries', SalaryController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{module}/{id}', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
