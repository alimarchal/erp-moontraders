<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;

class CustomerEmployeeAccountController extends Controller
{
    public function __construct(protected LedgerService $ledgerService) {}

    /**
     * Get customer balance with specific employee
     * GET /api/v1/customer-employee-accounts/{customer}/balance/{employee}
     */
    public function balance(int $customerId, int $employeeId): JsonResponse
    {
        $balance = $this->ledgerService->getCustomerEmployeeBalance($customerId, $employeeId);

        return response()->json([
            'balance' => $balance,
        ]);
    }

    /**
     * Get all customers with their balances for specific employee
     * GET /api/v1/customer-employee-accounts/by-employee/{employee}
     */
    public function byEmployee(int $employeeId): JsonResponse
    {
        $balances = $this->ledgerService->getCustomersWithBalancesByEmployee($employeeId);

        // Get ALL active customers (salesman can create credit with any customer)
        $customers = Customer::where('is_active', true)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name', 'customer_code', 'business_name'])
            ->map(function ($customer) use ($balances) {
                // Use employee-specific balance from ledger (0 if no existing transactions)
                $balanceData = $balances[$customer->id] ?? ['outstanding_balance' => 0];

                return [
                    'id' => $customer->id,
                    'name' => $customer->customer_name.' ('.$customer->customer_code.')',
                    'business_name' => $customer->business_name ?? $customer->customer_name,
                    'balance' => $balanceData['outstanding_balance'],
                ];
            });

        return response()->json($customers);
    }

    /**
     * Get ledger entries for specific customer-employee account
     * GET /api/v1/customer-employee-accounts/{customer}/ledger/{employee}
     */
    public function ledger(int $customerId, int $employeeId): JsonResponse
    {
        $entries = CustomerEmployeeAccountTransaction::query()
            ->select('ceat.*')
            ->from('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->where('cea.customer_id', $customerId)
            ->where('cea.employee_id', $employeeId)
            ->whereNull('ceat.deleted_at')
            ->with(['salesSettlement'])
            ->orderBy('ceat.transaction_date', 'desc')
            ->orderBy('ceat.id', 'desc')
            ->get();

        return response()->json($entries);
    }
}
