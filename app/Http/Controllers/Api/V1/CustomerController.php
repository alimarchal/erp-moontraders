<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    /**
     * Get customer balance.
     */
    public function balance(Customer $customer): JsonResponse
    {
        return response()->json([
            'balance' => $customer->receivable_balance ?? 0,
        ]);
    }

    /**
     * Get customers by employee (salesman) who have credit transactions with the employee.
     */
    public function byEmployee(int $employeeId): JsonResponse
    {
        // Get customers who have credit transactions with this specific employee
        $customerIds = CustomerLedger::where('employee_id', $employeeId)
            ->whereIn('transaction_type', ['credit_sale', 'recovery', 'bank_transfer', 'cheque_payment'])
            ->distinct('customer_id')
            ->pluck('customer_id')
            ->toArray();

        $customers = Customer::whereIn('id', $customerIds)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name', 'business_name', 'receivable_balance'])
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'name' => $customer->customer_name,
                'business_name' => $customer->business_name ?? $customer->customer_name,
                'balance' => $customer->receivable_balance ?? 0,
            ]);

        return response()->json($customers);
    }
}
