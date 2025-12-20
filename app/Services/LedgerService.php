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
     * 2. journal_entries + journal_entry_details (General Ledger)
     */
    public function processSalesSettlement(SalesSettlement $settlement): array
    {
        try {
            DB::beginTransaction();

            $results = [
                'customer_employee_transactions' => [],
                'salesman_entries' => [],
                'journal_entries' => [],
            ];

            $accountingService = app(AccountingService::class);

            // Resolve frequently used accounts by code to avoid hardcoded IDs
            $accountIds = [
                'accounts_receivable' => $this->getAccountIdByCode('1110'),
                'cash_in_hand' => $this->getAccountIdByCode('1120'),
                'bank_accounts' => $this->getAccountIdByCode('1170'),
                'sales_revenue' => $this->getAccountIdByCode('4110'),
                'advance_tax' => $this->getAccountIdByCode('1161'),
            ];

            // Reload the settlement to get all related data
            $settlement->load([
                'employee',
                'creditSales.customer',
                'cheques',
                'bankTransfers.bankAccount',
                'expenses.expenseAccount',
                'advanceTaxes',
            ]);

            // ========================================
            // 1. CREDIT SALES - Customer Sub-Ledger + GL
            // ========================================
            foreach ($settlement->creditSales as $creditSale) {
                // Ensure customer-employee account exists before recording transaction
                $account = \App\Models\CustomerEmployeeAccount::firstOrCreate(
                    [
                        'customer_id' => $creditSale->customer_id,
                        'employee_id' => $creditSale->employee_id,
                    ],
                    [
                        'account_number' => \App\Models\CustomerEmployeeAccount::generateAccountNumber(),
                        'opened_date' => $settlement->settlement_date,
                        'status' => 'active',
                        'created_by' => auth()->id(),
                    ]
                );

                // 1a. CUSTOMER SUB-LEDGER: Record debit (sale) and credit (payment)
                if ($creditSale->sale_amount > 0) {
                    $result = $this->recordCustomerEmployeeTransaction([
                        'customer_id' => $creditSale->customer_id,
                        'employee_id' => $creditSale->employee_id,
                        'transaction_date' => $settlement->settlement_date,
                        'transaction_type' => 'credit_sale',
                        'reference_number' => $settlement->settlement_number,
                        'sales_settlement_id' => $settlement->id,
                        'invoice_number' => $creditSale->invoice_number,
                        'description' => "Accounts Receivable - {$creditSale->customer->customer_name}",
                        'debit' => $creditSale->sale_amount,
                        'credit' => 0,
                        'notes' => $creditSale->notes,
                    ]);
                    $results['customer_employee_transactions'][] = $result;
                }

                if ($creditSale->payment_received > 0) {
                    $result = $this->recordCustomerEmployeeTransaction([
                        'customer_id' => $creditSale->customer_id,
                        'employee_id' => $creditSale->employee_id,
                        'transaction_date' => $settlement->settlement_date,
                        'transaction_type' => 'credit_sale',
                        'reference_number' => $settlement->settlement_number,
                        'sales_settlement_id' => $settlement->id,
                        'description' => "Cash Received - {$creditSale->customer->customer_name}",
                        'debit' => 0,
                        'credit' => $creditSale->payment_received,
                        'payment_method' => 'cash',
                        'notes' => $creditSale->notes,
                    ]);
                    $results['customer_employee_transactions'][] = $result;
                }

                // 1b. GENERAL LEDGER: Create journal entry for credit sale
                if ($creditSale->sale_amount > 0 || $creditSale->payment_received > 0) {
                    $journalLines = [];

                    // DR: Accounts Receivable (1110) - customer owes
                    if ($creditSale->sale_amount > 0) {
                        $journalLines[] = [
                            'account_id' => $accountIds['accounts_receivable'],
                            'debit' => $creditSale->sale_amount,
                            'credit' => 0,
                            'description' => "AR - {$creditSale->customer->customer_name} - Invoice {$creditSale->invoice_number}",
                            'cost_center_id' => null,
                        ];

                        // CR: Sales Revenue (4110)
                        $journalLines[] = [
                            'account_id' => $accountIds['sales_revenue'],
                            'debit' => 0,
                            'credit' => $creditSale->sale_amount,
                            'description' => "Sales - {$creditSale->customer->customer_name}",
                            'cost_center_id' => null,
                        ];
                    }

                    // DR: Cash (1131) - payment received
                    if ($creditSale->payment_received > 0) {
                        $journalLines[] = [
                            'account_id' => $accountIds['cash_in_hand'],
                            'debit' => $creditSale->payment_received,
                            'credit' => 0,
                            'description' => "Cash received from {$creditSale->customer->customer_name}",
                            'cost_center_id' => null,
                        ];

                        // CR: Accounts Receivable (1110) - reduce what customer owes
                        $journalLines[] = [
                            'account_id' => $accountIds['accounts_receivable'],
                            'debit' => 0,
                            'credit' => $creditSale->payment_received,
                            'description' => "Payment from {$creditSale->customer->customer_name}",
                            'cost_center_id' => null,
                        ];
                    }

                    $journalResult = $accountingService->createJournalEntry([
                        'entry_date' => $settlement->settlement_date,
                        'description' => "Credit Sale - {$creditSale->customer->customer_name} - {$settlement->settlement_number}",
                        'reference' => $settlement->settlement_number,
                        'lines' => $journalLines,
                        'auto_post' => true,
                    ]);

                    $results['journal_entries'][] = $journalResult;
                }

                // Record in salesman ledger (separate system for salesman tracking)
                if ($creditSale->sale_amount > 0) {
                    $salesmanResult = $this->recordSalesmanCreditSale([
                        'transaction_date' => $settlement->settlement_date,
                        'employee_id' => $creditSale->employee_id,
                        'amount' => $creditSale->sale_amount,
                        'reference_number' => $settlement->settlement_number,
                        'description' => "Credit sale to {$creditSale->customer->customer_name}",
                        'sales_settlement_id' => $settlement->id,
                        'customer_id' => $creditSale->customer_id,
                    ]);

                    $results['salesman_entries'][] = $salesmanResult;
                }
            }

            // ========================================
            // 2. CHEQUE PAYMENTS - GL Only
            // ========================================
            foreach ($settlement->cheques as $cheque) {
                if ($cheque->amount > 0) {
                    $bankAccountName = $cheque->bank_name ?? 'Bank';
                    $journalResult = $accountingService->createJournalEntry([
                        'entry_date' => $settlement->settlement_date,
                        'description' => "Cheque #{$cheque->cheque_number} - {$bankAccountName} - {$settlement->settlement_number}",
                        'reference' => $settlement->settlement_number.'-CHQ-'.$cheque->cheque_number,
                        'lines' => [
                            [
                                'account_id' => $accountIds['bank_accounts'],
                                'debit' => $cheque->amount,
                                'credit' => 0,
                                'description' => "Cheque received - {$bankAccountName} #{$cheque->cheque_number}",
                                'cost_center_id' => null,
                            ],
                            [
                                'account_id' => $accountIds['accounts_receivable'],
                                'debit' => 0,
                                'credit' => $cheque->amount,
                                'description' => 'Cheque payment applied to AR',
                                'cost_center_id' => null,
                            ],
                        ],
                        'auto_post' => true,
                    ]);

                    $results['journal_entries'][] = $journalResult;
                }
            }

            // ========================================
            // 3. BANK TRANSFERS - GL Only
            // ========================================
            foreach ($settlement->bankTransfers as $transfer) {
                if ($transfer->amount > 0) {
                    $bankAccountName = $transfer->bankAccount->bank_name ?? 'Bank';
                    $journalResult = $accountingService->createJournalEntry([
                        'entry_date' => $settlement->settlement_date,
                        'description' => "Bank Transfer - {$bankAccountName} - {$settlement->settlement_number}",
                        'reference' => $settlement->settlement_number.'-TRF-'.$transfer->id,
                        'lines' => [
                            [
                                'account_id' => $accountIds['bank_accounts'],
                                'debit' => $transfer->amount,
                                'credit' => 0,
                                'description' => "Bank transfer to {$bankAccountName}",
                                'cost_center_id' => null,
                            ],
                            [
                                'account_id' => $accountIds['accounts_receivable'],
                                'debit' => 0,
                                'credit' => $transfer->amount,
                                'description' => 'Bank transfer applied to AR',
                                'cost_center_id' => null,
                            ],
                        ],
                        'auto_post' => true,
                    ]);

                    $results['journal_entries'][] = $journalResult;
                }
            }

            // ========================================
            // 4. EXPENSES - GL Only
            // ========================================
            foreach ($settlement->expenses as $expense) {
                if ($expense->amount > 0) {
                    $expenseAccountName = $expense->expenseAccount->account_name ?? 'Expense';
                    $journalResult = $accountingService->createJournalEntry([
                        'entry_date' => $expense->expense_date,
                        'description' => "{$expenseAccountName} - {$expense->description} - {$settlement->settlement_number}",
                        'reference' => $settlement->settlement_number.'-EXP-'.$expense->id,
                        'lines' => [
                            [
                                'account_id' => $expense->expense_account_id,
                                'debit' => $expense->amount,
                                'credit' => 0,
                                'description' => $expense->description,
                                'cost_center_id' => null,
                            ],
                            [
                                'account_id' => $accountIds['cash_in_hand'],
                                'debit' => 0,
                                'credit' => $expense->amount,
                                'description' => "Cash paid for {$expenseAccountName}",
                                'cost_center_id' => null,
                            ],
                        ],
                        'auto_post' => true,
                    ]);

                    $results['journal_entries'][] = $journalResult;
                }
            }

            // ========================================
            // 5. ADVANCE TAX - GL Only
            // ========================================
            foreach ($settlement->advanceTaxes as $advanceTax) {
                if ($advanceTax->amount > 0) {
                    $journalResult = $accountingService->createJournalEntry([
                        'entry_date' => $settlement->settlement_date,
                        'description' => "Advance Tax - {$settlement->settlement_number}",
                        'reference' => $settlement->settlement_number.'-TAX',
                        'lines' => [
                            [
                                'account_id' => $accountIds['advance_tax'],
                                'debit' => $advanceTax->amount,
                                'credit' => 0,
                                'description' => 'Advance tax collected',
                                'cost_center_id' => null,
                            ],
                            [
                                'account_id' => $accountIds['cash_in_hand'],
                                'debit' => 0,
                                'credit' => $advanceTax->amount,
                                'description' => 'Cash for advance tax',
                                'cost_center_id' => null,
                            ],
                        ],
                        'auto_post' => true,
                    ]);

                    $results['journal_entries'][] = $journalResult;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'data' => $results,
                'message' => 'Sales settlement processed - customer ledger and GL journal entries created',
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
