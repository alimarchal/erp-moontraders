<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Customer API Routes
Route::get('/customers/{customer}/balance', function ($customerId) {
    $customer = \App\Models\Customer::findOrFail($customerId);

    return response()->json([
        'balance' => $customer->receivable_balance ?? 0,
    ]);
});

Route::get('/customers/by-employee/{employee}', function ($employeeId) {
    // Get customers who have credit transactions with this specific employee
    $customerIds = \App\Models\CustomerLedger::where('employee_id', $employeeId)
        ->whereIn('transaction_type', ['credit_sale', 'recovery', 'bank_transfer', 'cheque_payment'])
        ->distinct('customer_id')
        ->pluck('customer_id')
        ->toArray();

    $customers = \App\Models\Customer::whereIn('id', $customerIds)
        ->orderBy('customer_name')
        ->get(['id', 'customer_name', 'receivable_balance'])
        ->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->customer_name,
                'business_name' => $customer->business_name ?? $customer->customer_name,
                'balance' => $customer->receivable_balance ?? 0,
            ];
        });

    return response()->json($customers);
});

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
