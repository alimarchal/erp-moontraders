<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for closing accounting periods and generating closing entries.
 *
 * Implements GAAP/IFRS period closing procedures:
 * - Closes income/expense accounts to retained earnings
 * - Prevents posting to closed periods
 * - Creates closing journal entries
 */
class PeriodClosingService
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Close an accounting period by transferring income/expense balances to retained earnings.
     *
     * @param int $periodId
     * @param int $retainedEarningsAccountId The chart of account ID for retained earnings
     * @return array{success: bool, data: mixed, message: string}
     */
    public function closeAccountingPeriod(int $periodId, int $retainedEarningsAccountId): array
    {
        try {
            return DB::transaction(function () use ($periodId, $retainedEarningsAccountId) {
                $period = AccountingPeriod::findOrFail($periodId);

                // Validate period can be closed
                if ($period->status === 'closed') {
                    throw new \Exception('Period is already closed.');
                }

                if ($period->status === 'archived') {
                    throw new \Exception('Archived periods cannot be closed.');
                }

                // Get income statement accounts (Income and Expense)
                $incomeStatementTypes = AccountType::where('report_group', 'IncomeStatement')
                    ->pluck('id');

                // Calculate balances for all income/expense accounts
                $accountBalances = DB::table('journal_entry_details as jed')
                    ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
                    ->join('chart_of_accounts as coa', 'coa.id', '=', 'jed.chart_of_account_id')
                    ->where('je.status', 'posted')
                    ->where('je.accounting_period_id', $periodId)
                    ->whereIn('coa.account_type_id', $incomeStatementTypes)
                    ->select(
                        'jed.chart_of_account_id',
                        'coa.account_name',
                        'coa.normal_balance',
                        DB::raw('SUM(jed.debit) as total_debit'),
                        DB::raw('SUM(jed.credit) as total_credit'),
                        DB::raw('SUM(jed.debit - jed.credit) as net_balance')
                    )
                    ->groupBy('jed.chart_of_account_id', 'coa.account_name', 'coa.normal_balance')
                    ->having(DB::raw('SUM(jed.debit - jed.credit)'), '<>', 0)
                    ->get();

                if ($accountBalances->isEmpty()) {
                    throw new \Exception('No income or expense transactions found for this period.');
                }

                // Build closing entry lines
                $closingLines = [];
                $totalExpenses = 0;
                $totalIncome = 0;

                foreach ($accountBalances as $balance) {
                    $netBalance = (float) $balance->net_balance;

                    if ($netBalance == 0) {
                        continue;
                    }

                    // Close the account by reversing its balance
                    // If account has debit balance, credit it to zero
                    // If account has credit balance, debit it to zero
                    if ($netBalance > 0) {
                        // Account has debit balance (typically expense)
                        $closingLines[] = [
                            'account_id' => $balance->chart_of_account_id,
                            'debit' => 0,
                            'credit' => abs($netBalance),
                            'description' => 'Period closing: ' . $balance->account_name,
                        ];
                        $totalExpenses += abs($netBalance);
                    } else {
                        // Account has credit balance (typically income)
                        $closingLines[] = [
                            'account_id' => $balance->chart_of_account_id,
                            'debit' => abs($netBalance),
                            'credit' => 0,
                            'description' => 'Period closing: ' . $balance->account_name,
                        ];
                        $totalIncome += abs($netBalance);
                    }
                }

                // Add balancing entry to retained earnings
                $netIncome = $totalIncome - $totalExpenses; // Income - Expenses

                if ($netIncome > 0) {
                    // Profit: Credit retained earnings
                    $closingLines[] = [
                        'account_id' => $retainedEarningsAccountId,
                        'debit' => 0,
                        'credit' => $netIncome,
                        'description' => 'Transfer of net income for period ' . $period->name,
                    ];
                } elseif ($netIncome < 0) {
                    // Loss: Debit retained earnings
                    $closingLines[] = [
                        'account_id' => $retainedEarningsAccountId,
                        'debit' => abs($netIncome),
                        'credit' => 0,
                        'description' => 'Transfer of net loss for period ' . $period->name,
                    ];
                }

                // Create the closing journal entry
                $closingEntryData = [
                    'entry_date' => $period->end_date,
                    'reference' => 'CLOSE-' . $period->id,
                    'description' => 'Closing entry for ' . $period->name,
                    'accounting_period_id' => $periodId,
                    'lines' => $closingLines,
                    'auto_post' => true,
                ];

                $result = $this->accountingService->createJournalEntry($closingEntryData);

                if (!$result['success']) {
                    throw new \Exception('Failed to create closing entry: ' . $result['message']);
                }

                $closingEntry = $result['data'];

                // Mark the entry as a closing entry
                $closingEntry->update([
                    'is_closing_entry' => true,
                    'closes_period_id' => $periodId,
                ]);

                // Update the period status
                $period->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                    'closed_by' => auth()->id(),
                    'closing_journal_entry_id' => $closingEntry->id,
                    'closing_total_debits' => $totalDebits,
                    'closing_total_credits' => $totalCredits,
                    'closing_net_income' => $netIncome,
                ]);

                Log::info('Accounting period closed successfully', [
                    'period_id' => $periodId,
                    'net_income' => $netIncome,
                    'closing_entry_id' => $closingEntry->id,
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'period' => $period->fresh(),
                        'closing_entry' => $closingEntry->load(['details.account']),
                        'net_income' => $netIncome,
                    ],
                    'message' => "Period '{$period->name}' closed successfully. Net income: " . number_format($netIncome, 2),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to close accounting period', [
                'period_id' => $periodId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to close period: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reopen a closed accounting period (for corrections).
     *
     * @param int $periodId
     * @return array{success: bool, data: mixed, message: string}
     */
    public function reopenAccountingPeriod(int $periodId): array
    {
        try {
            return DB::transaction(function () use ($periodId) {
                $period = AccountingPeriod::findOrFail($periodId);

                if ($period->status !== 'closed') {
                    throw new \Exception('Only closed periods can be reopened.');
                }

                // Reverse the closing entry if it exists
                if ($period->closing_journal_entry_id) {
                    $result = $this->accountingService->reverseJournalEntry(
                        $period->closing_journal_entry_id,
                        'Reversal due to period reopening'
                    );

                    if (!$result['success']) {
                        throw new \Exception('Failed to reverse closing entry: ' . $result['message']);
                    }
                }

                // Update period status
                $period->update([
                    'status' => 'open',
                    'closed_at' => null,
                    'closed_by' => null,
                    'closing_journal_entry_id' => null,
                    'closing_total_debits' => null,
                    'closing_total_credits' => null,
                    'closing_net_income' => null,
                ]);

                Log::info('Accounting period reopened', [
                    'period_id' => $periodId,
                ]);

                return [
                    'success' => true,
                    'data' => $period->fresh(),
                    'message' => "Period '{$period->name}' has been reopened for corrections.",
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to reopen accounting period', [
                'period_id' => $periodId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to reopen period: ' . $e->getMessage(),
            ];
        }
    }
}
