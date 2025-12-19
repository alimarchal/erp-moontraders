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
     */
    public function processSalesSettlement(SalesSettlement $settlement): array
    {
        try {
            DB::beginTransaction();

            $results = [
                'customer_employee_transactions' => [],
                'customer_entries' => [],
                'salesman_entries' => [],
            ];

            // Reload the settlement to get updated data
            $settlement->load(['employee', 'bankTransfers', 'cheques']);

            // Process credit sales - NEW SYSTEM: customer_employee_account_transactions
            foreach ($settlement->creditSales as $creditSale) {
                // Record credit sale transaction
                if ($creditSale->sale_amount > 0) {
                    $result = $this->recordCustomerEmployeeTransaction([
                        'customer_id' => $creditSale->customer_id,
                        'employee_id' => $creditSale->employee_id,
                        'transaction_date' => $settlement->settlement_date,
                        'transaction_type' => 'credit_sale',
                        'reference_number' => $settlement->settlement_number,
                        'sales_settlement_id' => $settlement->id,
                        'credit_sale_id' => $creditSale->id,
                        'invoice_number' => $creditSale->invoice_number,
                        'description' => "Credit sale - {$settlement->employee->name}",
                        'debit' => $creditSale->sale_amount, // Customer owes
                        'credit' => 0,
                        'payment_method' => null,
                        'notes' => $creditSale->notes,
                    ]);
                    $results['customer_employee_transactions'][] = $result;
                }

                // Record recovery transaction
                if ($creditSale->recovery_amount > 0) {
                    $result = $this->recordCustomerEmployeeTransaction([
                        'customer_id' => $creditSale->customer_id,
                        'employee_id' => $creditSale->employee_id,
                        'transaction_date' => $settlement->settlement_date,
                        'transaction_type' => 'recovery_cash',
                        'reference_number' => $settlement->settlement_number,
                        'sales_settlement_id' => $settlement->id,
                        'credit_sale_id' => $creditSale->id,
                        'description' => "Cash recovery - {$settlement->employee->name}",
                        'debit' => 0,
                        'credit' => $creditSale->recovery_amount, // Customer pays
                        'payment_method' => 'cash',
                        'notes' => $creditSale->notes,
                    ]);
                    $results['customer_employee_transactions'][] = $result;
                }

                // Still record in salesman ledger
                $salesmanResult = $this->recordSalesmanCreditSale([
                    'transaction_date' => $settlement->settlement_date,
                    'employee_id' => $creditSale->employee_id,
                    'amount' => $creditSale->sale_amount,
                    'reference_number' => $settlement->settlement_number,
                    'description' => "Credit sale to {$creditSale->customer->customer_name}",
                    'sales_settlement_id' => $settlement->id,
                    'customer_id' => $creditSale->customer_id,
                    'supplier_id' => $creditSale->supplier_id ?? null,
                ]);

                $results['salesman_entries'][] = $salesmanResult;
            }

            // Process bank transfers
            foreach ($settlement->bankTransfers as $transfer) {
                if ($transfer->customer_id && $transfer->amount > 0) {
                    $result = $this->recordCustomerEmployeeTransaction([
                        'customer_id' => $transfer->customer_id,
                        'employee_id' => $settlement->employee_id,
                        'transaction_date' => $transfer->transfer_date ?? $settlement->settlement_date,
                        'transaction_type' => 'bank_transfer',
                        'reference_number' => $transfer->reference_number ?? $settlement->settlement_number,
                        'sales_settlement_id' => $settlement->id,
                        'description' => "Bank transfer payment - {$transfer->notes}",
                        'debit' => 0,
                        'credit' => $transfer->amount,
                        'payment_method' => 'bank_transfer',
                        'bank_account_id' => $transfer->bank_account_id,
                        'notes' => $transfer->notes,
                    ]);
                    $results['customer_employee_transactions'][] = $result;
                }
            }

            // Process cheques
            foreach ($settlement->cheques as $cheque) {
                if ($cheque->customer_id && $cheque->amount > 0) {
                    $result = $this->recordCustomerEmployeeTransaction([
                        'customer_id' => $cheque->customer_id,
                        'employee_id' => $settlement->employee_id,
                        'transaction_date' => $cheque->cheque_date ?? $settlement->settlement_date,
                        'transaction_type' => 'recovery_cheque',
                        'reference_number' => $cheque->cheque_number,
                        'sales_settlement_id' => $settlement->id,
                        'description' => "Cheque payment - {$cheque->bank_name}",
                        'debit' => 0,
                        'credit' => $cheque->amount,
                        'payment_method' => 'cheque',
                        'cheque_number' => $cheque->cheque_number,
                        'cheque_date' => $cheque->cheque_date,
                        'notes' => $cheque->notes,
                    ]);
                    $results['customer_employee_transactions'][] = $result;
                }
            }

            // Process credit recoveries if any (general recovery not tied to specific customer)
            if ($settlement->credit_recoveries > 0) {
                $salesmanResult = $this->recordSalesmanCollection([
                    'transaction_date' => $settlement->settlement_date,
                    'employee_id' => $settlement->employee_id,
                    'cash_amount' => $settlement->credit_recoveries,
                    'cheque_amount' => 0,
                    'reference_number' => $settlement->settlement_number,
                    'description' => 'Credit recoveries collected',
                    'sales_settlement_id' => $settlement->id,
                ]);

                $results['salesman_entries'][] = $salesmanResult;
            }

            DB::commit();

            return [
                'success' => true,
                'data' => $results,
                'message' => 'Sales settlement processed in ledgers',
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
                'credit_sale_id' => $data['credit_sale_id'] ?? null,
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
