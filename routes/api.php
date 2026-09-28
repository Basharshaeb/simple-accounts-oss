<?php

use App\Http\Controllers\API\AccountController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BranchController;
use App\Http\Controllers\API\CashBoxController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\CurrencyController;
use App\Http\Controllers\API\ExchangeRateController;
use App\Http\Controllers\API\FiscalYearController;
use App\Http\Controllers\API\JournalEntryController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\ReceiptController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\SuperAdminController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth Routes
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('superadmin/login', [SuperAdminController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // SuperAdmin Routes
        Route::get('superadmin/stats', [SuperAdminController::class, 'stats']);
        Route::put('superadmin/companies/{id}/status', [SuperAdminController::class, 'toggleStatus']);

        // Companies
        Route::apiResource('companies', CompanyController::class)->only(['index', 'store']);

        // Users & Roles
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{id}/status', [UserController::class, 'toggleStatus']);
        Route::get('users/{id}/logins', [UserController::class, 'logins']);

        // Master Data
        Route::get('currencies', [CurrencyController::class, 'index']);
        Route::post('currencies', [CurrencyController::class, 'store']);
        Route::put('currencies/{id}', [CurrencyController::class, 'update']);

        Route::get('branches', [BranchController::class, 'index']);
        Route::post('branches', [BranchController::class, 'store']);

        Route::get('cash-boxes', [CashBoxController::class, 'index']);
        Route::post('cash-boxes', [CashBoxController::class, 'store']);

        Route::get('exchange-rates', [ExchangeRateController::class, 'index']);
        Route::post('exchange-rates', [ExchangeRateController::class, 'store']);

        Route::get('fiscal-years', [FiscalYearController::class, 'index']);
        Route::post('fiscal-years', [FiscalYearController::class, 'store']);
        Route::post('fiscal-periods/{id}/close', [FiscalYearController::class, 'closePeriod']);

        // Chart of Accounts
        Route::apiResource('accounts', AccountController::class);

        // Journal Entries
        Route::get('journal-entries/next-number', [JournalEntryController::class, 'nextNumber']);
        Route::get('journal-entries', [JournalEntryController::class, 'index']);
        Route::post('journal-entries', [JournalEntryController::class, 'store']);
        Route::get('journal-entries/{id}', [JournalEntryController::class, 'show']);
        Route::post('journal-entries/{id}/post', [JournalEntryController::class, 'post']);
        Route::post('journal-entries/{id}/reverse', [JournalEntryController::class, 'reverse']);

        // Receipt Vouchers
        Route::get('receipts/next-number', [ReceiptController::class, 'nextNumber']);
        Route::get('receipts', [ReceiptController::class, 'index']);
        Route::post('receipts', [ReceiptController::class, 'store']);
        Route::get('receipts/{id}', [ReceiptController::class, 'show']);
        Route::post('receipts/{id}/post', [ReceiptController::class, 'post']);

        // Payment Vouchers
        Route::get('payments/next-number', [PaymentController::class, 'nextNumber']);
        Route::get('payments', [PaymentController::class, 'index']);
        Route::post('payments', [PaymentController::class, 'store']);
        Route::get('payments/{id}', [PaymentController::class, 'show']);
        Route::post('payments/{id}/post', [PaymentController::class, 'post']);

        // Financial Reports & Dashboard
        Route::get('reports/trial-balance', [ReportController::class, 'trialBalance']);
        Route::get('reports/account-statement', [ReportController::class, 'accountStatement']);
        Route::get('reports/income-statement', [ReportController::class, 'incomeStatement']);
        Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet']);
        Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    });
});
