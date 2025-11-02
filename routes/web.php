<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseTypeController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Journal Entry Routes
    Route::resource('journal-entries', JournalEntryController::class);

    // Additional journal entry actions
    Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])
        ->name('journal-entries.post');
    Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])
        ->name('journal-entries.reverse');

    // Quick transaction helpers
    Route::post('transactions/cash-receipt', [JournalEntryController::class, 'recordCashReceipt'])
        ->name('transactions.cash-receipt');
    Route::post('transactions/cash-payment', [JournalEntryController::class, 'recordCashPayment'])
        ->name('transactions.cash-payment');
    Route::post('transactions/opening-balance', [JournalEntryController::class, 'recordOpeningBalance'])
        ->name('transactions.opening-balance');

    // Settings Routes
    Route::prefix('settings')->group(function () {
        // Settings Index
        Route::get('/', [SettingsController::class, 'index'])->name('settings.index');

        // Account Types CRUD
        Route::resource('account-types', AccountTypeController::class);

        // Accounting Periods CRUD
        Route::resource('accounting-periods', AccountingPeriodController::class);

        // Currencies CRUD
        Route::resource('currencies', CurrencyController::class);

        // Cost Centers CRUD
        Route::resource('cost-centers', CostCenterController::class);

        // Chart of Accounts Tree View
        Route::get('chart-of-accounts/manage-tree-structure', [ChartOfAccountController::class, 'tree'])->name('chart-of-accounts.tree');

        // Chart of Accounts CRUD
        Route::resource('chart-of-accounts', ChartOfAccountController::class);

        // Company CRUD
        Route::resource('companies', CompanyController::class);

        // Warehouses CRUD
        Route::resource('warehouses', WarehouseController::class);

        // Warehouse Types CRUD
        Route::resource('warehouse-types', WarehouseTypeController::class);
    });
});
