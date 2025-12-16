<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(protected LedgerService $ledgerService)
    {
    }

    /**
     * Get customer balance (overall balance - used as fallback).
     */
    public function balance(Customer $customer): JsonResponse
    {
        return response()->json([
            'balance' => $customer->receivable_balance ?? 0,
        ]);
    }

    /**
     * Get customer balance for a specific employee/salesman.
     * This returns the employee-specific outstanding balance from customer_ledgers.
     *
     * Formula: SUM(debit) - SUM(credit) = Outstanding Balance
     */
    public function balanceByEmployee(int $customerId, int $employeeId): JsonResponse
    {
        $balance = $this->ledgerService->getCustomerBalanceByEmployee($customerId, $employeeId);
    /**
     * Get customers by employee (salesman) with their employee-specific balances.
     * This returns the correct balance for each customer with the specified employee.
     */
    public function byEmployee(int $employeeId): JsonResponse
    {
        // Get employee-specific balances from customer_ledgers
        $employeeBalances = $this->ledgerService->getCustomersWithBalancesByEmployee($employeeId);

        // Get ALL active customers (salesman can create credit with any customer)
        $customers = Customer::where('is_active', true)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name', 'business_name'])
            ->map(function ($customer) use ($employeeBalances) {
                // Use employee-specific balance from ledger (0 if no existing transactions)
                $balanceData = $employeeBalances[$customer->id] ?? ['outstanding_balance' => 0];

                return [
                    'id' => $customer->id,
                    'name' => $customer->customer_name,
                    'business_name' => $customer->business_name ?? $customer->customer_name,
                    'balance' => $balanceData['outstanding_balance'],
                ];
            });
}
