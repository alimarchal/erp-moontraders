<?php

use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

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

        // Chart of Accounts CRUD
        Route::resource('chart-of-accounts', ChartOfAccountController::class);
    });
});
