<?php

namespace App\Services;

use App\Models\EmployeeSalaryTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalaryService
{
    public function __construct(private AccountingService $accountingService) {}

    /**
     * Create a salary transaction (no GL posting — user must explicitly post).
     *
     * @param  array<string, mixed>  $data  Validated request data
     * @return array{success: bool, data: ?EmployeeSalaryTransaction, message: string}
     */
    public function createTransaction(array $data): array
    {
        try {
            DB::beginTransaction();

            $transaction = EmployeeSalaryTransaction::create($data);

            DB::commit();

            return [
                'success' => true,
                'data' => $transaction->fresh(),
                'message' => 'Transaction created successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create salary transaction', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create transaction: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Update a salary transaction (only if not yet posted).
     *
     * @param  array<string, mixed>  $data  Validated request data
     * @return array{success: bool, data: ?EmployeeSalaryTransaction, message: string}
     */
    public function updateTransaction(EmployeeSalaryTransaction $transaction, array $data): array
    {
        try {
            if ($transaction->status === 'Paid') {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Cannot edit a posted (Paid) transaction.',
                ];
            }

            DB::beginTransaction();

            $transaction->update($data);

            DB::commit();

            return [
                'success' => true,
                'data' => $transaction->fresh(),
                'message' => 'Transaction updated successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update salary transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to update transaction: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Post a salary transaction — creates GL journal entry and marks as Paid.
     * User must explicitly trigger this action.
     *
     * @return array{success: bool, data: ?EmployeeSalaryTransaction, message: string}
     */
    public function postTransaction(EmployeeSalaryTransaction $transaction): array
    {
        try {
            if ($transaction->status === 'Paid') {
                return ['success' => false, 'data' => null, 'message' => 'Transaction is already posted.'];
            }

            if ($transaction->status === 'Cancelled') {
                return ['success' => false, 'data' => null, 'message' => 'Cannot post a cancelled transaction.'];
            }

            if (! $transaction->debit_account_id || ! $transaction->credit_account_id) {
                return ['success' => false, 'data' => null, 'message' => 'Debit and Credit accounts must be set before posting.'];
            }

            DB::beginTransaction();

            $journalEntry = $this->createJournalEntry($transaction);

            $transaction->update([
                'status' => 'Paid',
                'journal_entry_id' => $journalEntry?->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $transaction->fresh(),
                'message' => 'Transaction posted successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post salary transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to post transaction: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get the current balance for an employee (total debits minus total credits).
     */
    public function getEmployeeBalance(int $employeeId): float
    {
        $result = EmployeeSalaryTransaction::where('employee_id', $employeeId)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance');

        return (float) $result;
    }

    /**
     * Create a GL journal entry for the salary transaction.
     *
     * @return \App\Models\JournalEntry|null
     */
    protected function createJournalEntry(EmployeeSalaryTransaction $transaction)
    {
        $transaction->loadMissing(['employee']);

        $amount = max((float) $transaction->debit, (float) $transaction->credit);

        if ($amount <= 0) {
            return null;
        }

        $employeeName = $transaction->employee?->name ?? 'Unknown';
        $costCenterId = $transaction->employee?->cost_center_id ?? 1;
        $lineDescription = "{$transaction->transaction_type} - {$employeeName}"
            .($transaction->salary_month ? " ({$transaction->salary_month})" : '');

        $description = "Salary Transaction - {$employeeName} - {$transaction->transaction_type}";
        if ($transaction->reference_number) {
            $description .= " ({$transaction->reference_number})";
        }

        $result = $this->accountingService->createJournalEntry([
            'entry_date' => Carbon::parse($transaction->transaction_date)->toDateString(),
            'reference' => $transaction->reference_number,
            'description' => $description,
            'reference_type' => 'App\Models\EmployeeSalaryTransaction',
            'reference_id' => $transaction->id,
            'lines' => [
                [
                    'account_id' => $transaction->debit_account_id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $lineDescription,
                    'cost_center_id' => $costCenterId,
                ],
                [
                    'account_id' => $transaction->credit_account_id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $lineDescription,
                    'cost_center_id' => $costCenterId,
                ],
            ],
            'auto_post' => true,
        ]);

        if ($result['success']) {
            return $result['data'];
        }

        Log::error("Failed to create JE for salary transaction #{$transaction->id}: ".$result['message']);

        return null;
    }
}
