<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCreditSale;
use App\Models\CustomerLedger;
use App\Models\Employee;
use App\Models\SalesmanLedger;
use App\Models\SalesSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerService
{
    /**
     * Record customer credit sale in ledger
     */
    public function recordCustomerCreditSale(array $data): array
    {
        try {
            DB::beginTransaction();

            $customer = Customer::findOrFail($data['customer_id']);
            $previousBalance = $this->getCustomerBalance($customer->id);

            $ledgerEntry = CustomerLedger::create([
                'transaction_date' => $data['transaction_date'],
                'customer_id' => $customer->id,
                'transaction_type' => 'credit_sale',
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? 'Credit sale',
                'debit' => $data['amount'], // Debit increases receivable
                'credit' => 0,
                'balance' => $previousBalance + $data['amount'],
                'sales_settlement_id' => $data['sales_settlement_id'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'credit_sale_id' => $data['credit_sale_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $ledgerEntry,
                'message' => 'Credit sale recorded in customer ledger',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording customer credit sale', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to record credit sale: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Record customer payment/recovery in ledger
     */
    public function recordCustomerPayment(array $data): array
    {
        try {
            DB::beginTransaction();

            $customer = Customer::findOrFail($data['customer_id']);
            $previousBalance = $this->getCustomerBalance($customer->id);

            $ledgerEntry = CustomerLedger::create([
                'transaction_date' => $data['transaction_date'],
                'customer_id' => $customer->id,
                'transaction_type' => $data['payment_method'] . '_recovery',
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'] ?? 'Payment received',
                'debit' => 0,
                'credit' => $data['amount'], // Credit decreases receivable
                'balance' => $previousBalance - $data['amount'],
                'sales_settlement_id' => $data['sales_settlement_id'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'cheque_number' => $data['cheque_number'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $ledgerEntry,
                'message' => 'Payment recorded in customer ledger',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording customer payment', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to record payment: ' . $e->getMessage(),
            ];
        }
    }

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
                'message' => 'Failed to record credit sale: ' . $e->getMessage(),
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
                'message' => 'Failed to record collection: ' . $e->getMessage(),
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
                'customer_entries' => [],
                'salesman_entries' => [],
                'credit_sales_created' => [],
            ];

            // First, create CustomerCreditSale records from draft data
            if ($settlement->credit_sales_data && is_array($settlement->credit_sales_data)) {
                foreach ($settlement->credit_sales_data as $creditSaleData) {
                    $customerCreditSale = CustomerCreditSale::create([
                        'sales_settlement_id' => $settlement->id,
                        'employee_id' => $settlement->employee_id,
                        'supplier_id' => $settlement->employee->supplier_id ?? null,
                        'customer_id' => $creditSaleData['customer_id'],
                        'invoice_number' => $creditSaleData['invoice_number'] ?? null,
                        'sale_amount' => $creditSaleData['sale_amount'],
                        'notes' => $creditSaleData['notes'] ?? null,
                    ]);

                    $results['credit_sales_created'][] = $customerCreditSale;

                    // Handle individual recovery payment if any
                    if (floatval($creditSaleData['payment_received'] ?? 0) > 0) {
                        $customer = Customer::find($creditSaleData['customer_id']);
                        if ($customer) {
                            $recoveryResult = $this->recordCustomerPayment([
                                'transaction_date' => $settlement->settlement_date,
                                'customer_id' => $customer->id,
                                'amount' => $creditSaleData['payment_received'],
                                'payment_method' => 'cash',
                                'reference_number' => $settlement->settlement_number,
                                'description' => 'Recovery payment - ' . ($creditSaleData['notes'] ?? 'Settlement ' . $settlement->settlement_number),
                                'sales_settlement_id' => $settlement->id,
                                'employee_id' => $settlement->employee_id,
                                'credit_sale_id' => $customerCreditSale->id,
                                'notes' => 'Recovery: ' . ($creditSaleData['notes'] ?? null),
                            ]);

                            $results['customer_entries'][] = $recoveryResult;
                        }
                    }
                }

                // Reload the settlement to get the newly created creditSales
                $settlement->load('creditSales');
            }

            // Process credit sales (both customer and salesman ledgers)
            foreach ($settlement->creditSales as $creditSale) {
                // Customer ledger entry
                $customerResult = $this->recordCustomerCreditSale([
                    'transaction_date' => $settlement->settlement_date,
                    'customer_id' => $creditSale->customer_id,
                    'amount' => $creditSale->sale_amount,
                    'reference_number' => $settlement->settlement_number,
                    'description' => "Credit sale - {$settlement->employee->name}",
                    'sales_settlement_id' => $settlement->id,
                    'employee_id' => $creditSale->employee_id,
                    'credit_sale_id' => $creditSale->id,
                    'notes' => $creditSale->notes,
                ]);

                $results['customer_entries'][] = $customerResult;

                // Salesman ledger entry
                $salesmanResult = $this->recordSalesmanCreditSale([
                    'transaction_date' => $settlement->settlement_date,
                    'employee_id' => $creditSale->employee_id,
                    'amount' => $creditSale->sale_amount,
                    'reference_number' => $settlement->settlement_number,
                    'description' => "Credit sale to {$creditSale->customer->customer_name}",
                    'sales_settlement_id' => $settlement->id,
                    'customer_id' => $creditSale->customer_id,
                    'supplier_id' => $creditSale->supplier_id,
                ]);

                $results['salesman_entries'][] = $salesmanResult;
            }

            // Process credit recoveries if any
            if ($settlement->credit_recoveries > 0) {
                // Note: Individual recovery breakdown would need to be captured
                // For now, this creates a general recovery entry for the salesman
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
                'message' => 'Failed to process settlement: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get customer's current balance (excluding draft settlements)
     */
    public function getCustomerBalance(int $customerId): float
    {
        $latestEntry = CustomerLedger::where('customer_id', $customerId)
            ->where(function ($query) {
                $query->whereNull('sales_settlement_id')
                    ->orWhereHas('salesSettlement', function ($q) {
                        $q->where('status', 'posted');
                    });
            })
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $latestEntry ? (float) $latestEntry->balance : 0.0;
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
     * Get customer ledger statement
     */
    public function getCustomerStatement(int $customerId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = CustomerLedger::where('customer_id', $customerId)
            ->with(['employee', 'salesSettlement']);

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
            'closing_balance' => $entries->last()->balance ?? 0,
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
}
