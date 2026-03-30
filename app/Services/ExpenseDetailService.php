<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\ExpenseDetail;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseDetailService
{
    public function __construct(private AccountingService $accountingService) {}

    /**
     * Create an expense detail entry (draft - no GL posting).
     *
     * @param  array<string, mixed>  $data  Validated request data
     * @return array{success: bool, data: ?ExpenseDetail, message: string}
     */
    public function createExpense(array $data): array
    {
        try {
            $expense = ExpenseDetail::create($data);

            return [
                'success' => true,
                'data' => $expense,
                'message' => 'Expense created successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create expense detail', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create expense: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Update an expense detail entry (no GL re-posting).
     *
     * @param  array<string, mixed>  $data  Validated request data
     * @return array{success: bool, data: ?ExpenseDetail, message: string}
     */
    public function updateExpense(ExpenseDetail $expense, array $data): array
    {
        try {
            $expense->update($data);

            return [
                'success' => true,
                'data' => $expense->fresh(),
                'message' => 'Expense updated successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update expense detail', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to update expense: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Post an expense to the GL - creates a journal entry and marks as posted.
     *
     * Double-entry:
     *   DR Expense Account (category-specific)  = amount
     *   CR Cash Account (1121)                   = amount
     *
     * @return array{success: bool, data: ?ExpenseDetail, message: string}
     */
    public function postExpense(ExpenseDetail $expense): array
    {
        try {
            DB::beginTransaction();

            if ($expense->isPosted()) {
                throw new \Exception('Expense is already posted.');
            }

            if (! $expense->debit_account_id || ! $expense->credit_account_id) {
                throw new \Exception('Debit and Credit accounts must be set before posting.');
            }

            $journalEntry = $this->createExpenseJournalEntry($expense);

            if (! $journalEntry) {
                throw new \Exception('Failed to create journal entry - check GL account configuration.');
            }

            $expense->update([
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'journal_entry_id' => $journalEntry->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => $expense->fresh(),
                'message' => 'Expense posted successfully with journal entry.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post expense detail', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to post expense: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Resolve the debit (expense) account based on category.
     */
    public function resolveDebitAccount(string $category): ?int
    {
        $accountMap = ExpenseDetail::categoryAccountMap();
        $accountName = $accountMap[$category] ?? null;

        if (! $accountName) {
            return null;
        }

        return ChartOfAccount::where('account_name', $accountName)->value('id');
    }

    /**
     * Resolve the credit (cash) account - always Cash account 1121.
     */
    public function resolveCreditAccount(): ?int
    {
        return ChartOfAccount::where('account_code', '1121')->value('id');
    }

    /**
     * Create a journal entry for an expense detail record.
     *
     * DR Expense Account (category-specific), CR Cash (1121)
     *
     * @return JournalEntry|null
     */
    protected function createExpenseJournalEntry(ExpenseDetail $expense)
    {
        $amount = (float) $expense->amount;

        if ($amount <= 0) {
            Log::info("Skipping JE for expense #{$expense->id}: amount is zero");

            return null;
        }

        $categoryLabel = ExpenseDetail::categoryOptions()[$expense->category] ?? $expense->category;

        $lines = [
            [
                'account_id' => $expense->debit_account_id,
                'debit' => $amount,
                'credit' => 0,
                'description' => "{$categoryLabel} expense".($expense->description ? " - {$expense->description}" : ''),
                'cost_center_id' => 1,
            ],
            [
                'account_id' => $expense->credit_account_id,
                'debit' => 0,
                'credit' => $amount,
                'description' => "{$categoryLabel} expense payment".($expense->description ? " - {$expense->description}" : ''),
                'cost_center_id' => 1,
            ],
        ];

        $description = "Expense Detail - {$categoryLabel}";
        if ($expense->description) {
            $description .= " - {$expense->description}";
        }

        $journalEntryData = [
            'entry_date' => Carbon::parse($expense->transaction_date)->toDateString(),
            'reference' => "EXP-{$expense->id}",
            'description' => $description,
            'reference_type' => 'App\Models\ExpenseDetail',
            'reference_id' => $expense->id,
            'lines' => $lines,
            'auto_post' => true,
        ];

        $result = $this->accountingService->createJournalEntry($journalEntryData);

        if ($result['success']) {
            Log::info("Journal entry created for expense #{$expense->id}: JE #{$result['data']->id}");

            return $result['data'];
        }

        Log::error("Failed to create JE for expense #{$expense->id}: ".$result['message']);

        return null;
    }
}
