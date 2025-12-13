<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API V1 Routes
Route::prefix('v1')->group(function () {
    // Customer API Routes
    Route::prefix('customers')->group(function () {
        Route::get('/{customer}/balance', [CustomerController::class, 'balance']);
        Route::get('/by-employee/{employee}', [CustomerController::class, 'byEmployee']);
    });
});

// Legacy Customer API Routes (for backward compatibility)
Route::get('/customers/{customer}/balance', [CustomerController::class, 'balance']);
Route::get('/customers/by-employee/{employee}', [CustomerController::class, 'byEmployee']);

// Accounting Transactions API Routes
Route::prefix('transactions')->group(function () {
    // General journal entry
    Route::post('/journal-entry', [TransactionController::class, 'createJournalEntry']);

    // Post/Reverse entries
    Route::post('/{id}/post', [TransactionController::class, 'postJournalEntry']);
    Route::post('/{id}/reverse', [TransactionController::class, 'reverseJournalEntry']);

    // Quick transaction helpers
    Route::post('/opening-balance', [TransactionController::class, 'recordOpeningBalance']);
    Route::post('/cash-receipt', [TransactionController::class, 'recordCashReceipt']);
    Route::post('/cash-payment', [TransactionController::class, 'recordCashPayment']);
    Route::post('/credit-sale', [TransactionController::class, 'recordCreditSale']);
    Route::post('/payment-received', [TransactionController::class, 'recordPaymentReceived']);
});
