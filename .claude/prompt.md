Make these two api using v1 or v2 like standard apis
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


that we are using in create, and edit of sales settlememnt and also change from there moreover on modal of Creditors / Credit Sales Breakdown when we add the customer it call another api it should the api /customers/by-employee/{employee} because the sales settlement employee_id as we might have many salesman which belong to same customer everyone has different credit with differnt salesman so this is the modal where the salesman sell thing on credit on this it should load that particalar employee credit and also this customer_ledgers this should track the related credit or debit for any time for any report make sure deep think this table should be very comprehensive 

and remove supplier_id from customer_credit_sales this does not we need

and use max-w-4xl modal for all modals specially for Credit Sales Detail do not run any test as it will wash all my data from database 
also create, form make sure it should work and same should be made for edit 
/Users/alirazamarchal/Herd/moontrader/resources/views/sales-settlements/edit.blade.php /Users/alirazamarchal/Herd/moontrader/resources/views/sales-settlements/create.blade.php