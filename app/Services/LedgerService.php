<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesmanLedger;
use App\Models\SalesSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerService
{
    /**
     * Record salesman credit sale in ledger
     */
    public function recordSalesmanCreditSale(array $data): array
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($data['employee_id']);
            $previousBalance = $this->getSalesmanBalance($employee->id);

            $ledgerEntry = SalesmanLedger::create([
                'transaction_date' => $data['transaction_date'],
                'employee_id' => $employee->id,
                'transaction_type' => 'credit_sale',
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? 'Credit sale issued',
                'debit' => $data['amount'], // Debit increases salesman's outstanding
                'credit' => 0,
                'balance' => $previousBalance + $data['amount'],
                'sales_settlement_id' => $data['sales_settlement_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? $employee->supplier_id,
                'cash_amount' => 0,
                'cheque_amount' => 0,
                'credit_amount' => $data['amount'],
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $ledgerEntry,
                'message' => 'Credit sale recorded in salesman ledger',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording salesman credit sale', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to record credit sale: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Record salesman cash/cheque collection
     */
    public function recordSalesmanCollection(array $data): array
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($data['employee_id']);
            $previousBalance = $this->getSalesmanBalance($employee->id);

            $totalCollection = ($data['cash_amount'] ?? 0) + ($data['cheque_amount'] ?? 0);

            $ledgerEntry = SalesmanLedger::create([
                'transaction_date' => $data['transaction_date'],
                'employee_id' => $employee->id,
                'transaction_type' => 'recovery',
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? 'Cash/Cheque collection',
                'debit' => 0,
                'credit' => $totalCollection, // Credit reduces salesman's outstanding
                'balance' => $previousBalance - $totalCollection,
                'sales_settlement_id' => $data['sales_settlement_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? $employee->supplier_id,
                'cash_amount' => $data['cash_amount'] ?? 0,
                'cheque_amount' => $data['cheque_amount'] ?? 0,
                'credit_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $ledgerEntry,
                'message' => 'Collection recorded in salesman ledger',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording salesman collection', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to record collection: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Process complete sales settlement and create all ledger entries
     * Creates entries in:
     * 1. customer_employee_account_transactions (customer sub-ledger)
     * 2. salesman_ledgers (salesman sub-ledger)
     *
     * NOTE: General Ledger (GL) entries are now handled by DistributionService
     * in a single consolidated journal entry.
     */
    public function processSalesSettlement(SalesSettlement $settlement): array
    {
        try {
            DB::beginTransaction();

            $results = [
                'customer_employee_transactions' => [],
                'salesman_entries' => [],
            ];

            // Reload the settlement to get all related data
            $settlement->load([
                'employee',
                'cheques',
                'bankTransfers.bankAccount',
                'expenses.expenseAccount',
                'advanceTaxes',
                'recoveries.customer',
            ]);

            // ========================================
            // 1. RECOVERIES - Customer Sub-Ledger
            // ========================================
            // ========================================
            // 2. CHEQUE PAYMENTS - Customer Sub-Ledger
            // ========================================
            foreach ($settlement->cheques as $cheque) {
                if ($cheque->amount > 0 && $cheque->customer_id) {
                    $result = $this->recordCustomerEmployeeTransaction([
                        'customer_id' => $cheque->customer_id,
                        'employee_id' => $settlement->employee_id,
                        'transaction_date' => $settlement->settlement_date,
                        'transaction_type' => 'recovery',
                        'reference_number' => $settlement->settlement_number,
                        'sales_settlement_id' => $settlement->id,
                        'description' => "Cheque Payment - {$cheque->bank_name} #{$cheque->cheque_number}",
                        'debit' => 0,
                        'credit' => $cheque->amount,
                        'payment_method' => 'cheque',
                    ]);
                    $results['customer_employee_transactions'][] = $result;
                }
            }

            // ========================================
            // 3. BANK TRANSFERS - Customer Sub-Ledger
            // ========================================
            foreach ($settlement->bankTransfers as $transfer) {
                if ($transfer->amount > 0 && $transfer->customer_id) {
                    $result = $this->recordCustomerEmployeeTransaction([
                        'customer_id' => $transfer->customer_id,
                        'employee_id' => $settlement->employee_id,
                        'transaction_date' => $settlement->settlement_date,
                        'transaction_type' => 'recovery',
                        'reference_number' => $settlement->settlement_number,
                        'sales_settlement_id' => $settlement->id,
                        'description' => "Bank Transfer - Ref: {$transfer->reference_number}",
                        'debit' => 0,
                        'credit' => $transfer->amount,
                        'payment_method' => 'bank_transfer',
                    ]);
                    $results['customer_employee_transactions'][] = $result;
                }
            }

            // ========================================
            // 4. RECOVERIES (Payments against old balances) - Customer Sub-Ledger
            // ========================================
            foreach ($settlement->recoveries as $recovery) {
                if ($recovery->amount > 0 && $recovery->customer_id) {
                    $methodLabel = $recovery->payment_method === 'cash' ? 'Cash' : 'Bank Transfer';
                    $result = $this->recordCustomerEmployeeTransaction([
                        'customer_id' => $recovery->customer_id,
                        'employee_id' => $recovery->employee_id ?? $settlement->employee_id,
                        'transaction_date' => $settlement->settlement_date,
                        'transaction_type' => 'recovery',
                        'reference_number' => $recovery->recovery_number ?? $settlement->settlement_number,
                        'sales_settlement_id' => $settlement->id,
                        'description' => "Recovery via {$methodLabel} - Ref: ".($recovery->recovery_number ?? 'N/A'),
                        'debit' => 0,
                        'credit' => $recovery->amount,
                        'payment_method' => $recovery->payment_method,
                        'bank_account_id' => $recovery->bank_account_id,
                        'notes' => $recovery->notes,
                    ]);
                    $results['customer_employee_transactions'][] = $result;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => $results,
                'message' => 'Sales settlement processed - customer and salesman sub-ledgers updated',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing sales settlement ledgers', [
                'error' => $e->getMessage(),
                'settlement_id' => $settlement->id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to process settlement: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get customer's current balance (sum across all employees)
     */
    public function getCustomerBalance(int $customerId): float
    {
        $result = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->where('cea.customer_id', $customerId)
            ->whereNull('ceat.deleted_at')
            ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as outstanding_balance')
            ->first();

        return $result ? (float) $result->outstanding_balance : 0.0;
    }

    /**
     * Resolve a Chart of Account ID by account_code with simple caching.
     */
    protected function getAccountIdByCode(string $accountCode): int
    {
        static $cache = [];

        if (isset($cache[$accountCode])) {
            return $cache[$accountCode];
        }

        $id = \App\Models\ChartOfAccount::where('account_code', $accountCode)->value('id');

        if (! $id) {
            throw new \Exception("Chart of Account with code {$accountCode} not found");
        }

        $cache[$accountCode] = (int) $id;

        return (int) $id;
    }

    /**
     * Get customer's outstanding balance for a specific employee/salesman
     * This is the correct balance to show when a salesman is settling with a customer
     *
     * Formula: SUM(debit) - SUM(credit) = Outstanding Balance
     * - Debit = Credit sales (customer owes us)
     * - Credit = Payments/Recovery (customer paid)
     */
    public function getCustomerBalanceByEmployee(int $customerId, int $employeeId): float
    {
        return \App\Models\CustomerEmployeeAccount::getBalance($customerId, $employeeId);
    }

    /**
     * Get salesman's current balance
     */
    public function getSalesmanBalance(int $employeeId): float
    {
        $latestEntry = SalesmanLedger::where('employee_id', $employeeId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $latestEntry ? (float) $latestEntry->balance : 0.0;
    }

    /**
     * Get customer ledger statement from customer_employee_account_transactions
     */
    public function getCustomerStatement(int $customerId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('employees as e', 'cea.employee_id', '=', 'e.id')
            ->leftJoin('sales_settlements as ss', 'ceat.sales_settlement_id', '=', 'ss.id')
            ->where('cea.customer_id', $customerId)
            ->whereNull('ceat.deleted_at')
            ->select(
                'ceat.*',
                'e.name as employee_name',
                'ss.settlement_number'
            );

        if ($fromDate) {
            $query->where('ceat.transaction_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('ceat.transaction_date', '<=', $toDate);
        }

        $entries = $query->orderBy('ceat.transaction_date')
            ->orderBy('ceat.id')
            ->get();

        $summary = [
            'opening_balance' => 0,
            'total_debits' => $entries->sum('debit'),
            'total_credits' => $entries->sum('credit'),
            'closing_balance' => $entries->sum('debit') - $entries->sum('credit'),
        ];

        return [
            'entries' => $entries,
            'summary' => $summary,
        ];
    }

    /**
     * Get salesman ledger statement
     */
    public function getSalesmanStatement(int $employeeId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = SalesmanLedger::where('employee_id', $employeeId)
            ->with(['customer', 'salesSettlement', 'supplier']);

        if ($fromDate) {
            $query->where('transaction_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('transaction_date', '<=', $toDate);
        }

        $entries = $query->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $summary = [
            'opening_balance' => 0,
            'total_debits' => $entries->sum('debit'),
            'total_credits' => $entries->sum('credit'),
            'total_cash' => $entries->sum('cash_amount'),
            'total_cheques' => $entries->sum('cheque_amount'),
            'total_credit_sales' => $entries->sum('credit_amount'),
            'closing_balance' => $entries->last()->balance ?? 0,
        ];

        return [
            'entries' => $entries,
            'summary' => $summary,
        ];
    }

    /**
     * =========================================================================
     * CUSTOMER-EMPLOYEE ACCOUNT METHODS (NEW SYSTEM)
     * =========================================================================
     */

    /**
     * Record transaction in customer-employee account system
     */
    public function recordCustomerEmployeeTransaction(array $data): array
    {
        try {
            DB::beginTransaction();

            // Find or create account
            $account = \App\Models\CustomerEmployeeAccount::firstOrCreate(
                [
                    'customer_id' => $data['customer_id'],
                    'employee_id' => $data['employee_id'],
                ],
                [
                    'account_number' => \App\Models\CustomerEmployeeAccount::generateAccountNumber(),
                    'opened_date' => $data['transaction_date'],
                    'status' => 'active',
                    'created_by' => auth()->id(),
                ]
            );

            // Create transaction
            $transaction = \App\Models\CustomerEmployeeAccountTransaction::create([
                'customer_employee_account_id' => $account->id,
                'transaction_date' => $data['transaction_date'],
                'transaction_type' => $data['transaction_type'],
                'reference_number' => $data['reference_number'] ?? null,
                'sales_settlement_id' => $data['sales_settlement_id'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'description' => $data['description'],
                'debit' => $data['debit'] ?? 0,
                'credit' => $data['credit'] ?? 0,
                'payment_method' => $data['payment_method'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'account' => $account,
                'transaction' => $transaction,
                'message' => 'Transaction recorded successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording customer-employee transaction', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to record transaction: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get customer balance with specific employee
     */
    public function getCustomerEmployeeBalance(int $customerId, int $employeeId): float
    {
        return \App\Models\CustomerEmployeeAccount::getBalance($customerId, $employeeId);
    }

    /**
     * Get all customers with balances for specific employee
     */
    public function getCustomersWithBalancesByEmployee(int $employeeId): array
    {
        $results = \Illuminate\Support\Facades\DB::table('customer_employee_account_transactions as ceat')
            ->select('cea.customer_id')
            ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as outstanding_balance')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->where('cea.employee_id', $employeeId)
            ->whereNull('ceat.deleted_at')
            ->groupBy('cea.customer_id')
            ->get();

        $balances = [];
        foreach ($results as $result) {
            $balances[$result->customer_id] = [
                'outstanding_balance' => (float) $result->outstanding_balance,
            ];
        }

        return $balances;
    }
}
