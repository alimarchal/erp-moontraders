<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for budget management and variance analysis.
 *
 * Provides functionality for:
 * - Budget vs actual comparison
 * - Variance calculation
 * - Variance analysis (favorable/unfavorable)
 */
class BudgetService
{
    /**
     * Calculate budget variances for a specific budget and period.
     *
     * @param int $budgetId
     * @param int $periodId
     * @return array{success: bool, data: mixed, message: string}
     */
    public function calculateBudgetVariances(int $budgetId, int $periodId): array
    {
        try {
            $period = AccountingPeriod::findOrFail($periodId);
            $budget = DB::table('budgets')->where('id', $budgetId)->first();

            if (!$budget) {
                throw new \Exception('Budget not found.');
            }

            // Get budget lines
            $budgetLines = DB::table('budget_lines as bl')
                ->join('chart_of_accounts as coa', 'coa.id', '=', 'bl.chart_of_account_id')
                ->join('account_types as at', 'at.id', '=', 'coa.account_type_id')
                ->where('bl.budget_id', $budgetId)
                ->select(
                    'bl.id as budget_line_id',
                    'bl.chart_of_account_id',
                    'bl.cost_center_id',
                    'coa.account_code',
                    'coa.account_name',
                    'at.type_name as account_type',
                    'bl.january',
                    'bl.february',
                    'bl.march',
                    'bl.april',
                    'bl.may',
                    'bl.june',
                    'bl.july',
                    'bl.august',
                    'bl.september',
                    'bl.october',
                    'bl.november',
                    'bl.december'
                )
                ->get();

            // Determine which month this period represents
            $periodMonth = (int) date('n', strtotime($period->start_date));
            $periodYear = (int) date('Y', strtotime($period->start_date));

            $variances = [];

            foreach ($budgetLines as $line) {
                // Get budget amount for this month
                $monthName = strtolower(date('F', mktime(0, 0, 0, $periodMonth, 1)));
                $budgetAmount = (float) $line->{$monthName};

                // Get actual amount from journal entries
                $actualQuery = DB::table('journal_entry_details as jed')
                    ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
                    ->where('je.status', 'posted')
                    ->where('je.accounting_period_id', $periodId)
                    ->where('jed.chart_of_account_id', $line->chart_of_account_id);

                if ($line->cost_center_id) {
                    $actualQuery->where('jed.cost_center_id', $line->cost_center_id);
                }

                $actualAmount = (float) $actualQuery->sum(DB::raw('jed.debit - jed.credit'));

                // Calculate variance
                $varianceAmount = $actualAmount - $budgetAmount;
                $variancePercentage = $budgetAmount != 0
                    ? ($varianceAmount / abs($budgetAmount)) * 100
                    : 0;

                // Determine variance type
                $varianceType = $this->determineVarianceType(
                    $line->account_type,
                    $varianceAmount
                );

                // Check if variance record already exists
                $existing = DB::table('budget_variances')
                    ->where('budget_line_id', $line->budget_line_id)
                    ->where('accounting_period_id', $periodId)
                    ->first();

                $varianceData = [
                    'budget_line_id' => $line->budget_line_id,
                    'accounting_period_id' => $periodId,
                    'month' => $periodMonth,
                    'year' => $periodYear,
                    'budget_amount' => $budgetAmount,
                    'actual_amount' => $actualAmount,
                    'variance_amount' => $varianceAmount,
                    'variance_percentage' => $variancePercentage,
                    'variance_type' => $varianceType,
                    'calculated_at' => now(),
                    'calculated_by' => auth()->id(),
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('budget_variances')
                        ->where('id', $existing->id)
                        ->update($varianceData);
                } else {
                    $varianceData['created_at'] = now();
                    DB::table('budget_variances')->insert($varianceData);
                }

                $variances[] = [
                    'account_code' => $line->account_code,
                    'account_name' => $line->account_name,
                    'account_type' => $line->account_type,
                    'budget' => $budgetAmount,
                    'actual' => $actualAmount,
                    'variance' => $varianceAmount,
                    'variance_pct' => round($variancePercentage, 2),
                    'variance_type' => $varianceType,
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'period' => $period->name,
                    'budget' => $budget->budget_name,
                    'variances' => $variances,
                    'summary' => $this->calculateVarianceSummary($variances),
                ],
                'message' => 'Budget variances calculated successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate budget variances', [
                'budget_id' => $budgetId,
                'period_id' => $periodId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to calculate budget variances: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Determine if a variance is favorable, unfavorable, or on target.
     *
     * For income accounts: Actual > Budget is favorable
     * For expense accounts: Actual < Budget is favorable
     */
    protected function determineVarianceType(string $accountType, float $variance): string
    {
        $tolerance = 0.01; // Within $0.01 is considered on target

        if (abs($variance) < $tolerance) {
            return 'on_target';
        }

        if ($accountType === 'Income') {
            // For income, positive variance (actual > budget) is favorable
            return $variance > 0 ? 'favorable' : 'unfavorable';
        } elseif ($accountType === 'Expense') {
            // For expenses, negative variance (actual < budget) is favorable
            return $variance < 0 ? 'favorable' : 'unfavorable';
        }

        // For assets/liabilities, we'll consider them neutral
        return 'on_target';
    }

    /**
     * Calculate variance summary statistics.
     */
    protected function calculateVarianceSummary(array $variances): array
    {
        $totalBudget = array_sum(array_column($variances, 'budget'));
        $totalActual = array_sum(array_column($variances, 'actual'));
        $totalVariance = $totalActual - $totalBudget;

        $favorable = array_filter($variances, fn($v) => $v['variance_type'] === 'favorable');
        $unfavorable = array_filter($variances, fn($v) => $v['variance_type'] === 'unfavorable');

        return [
            'total_budget' => $totalBudget,
            'total_actual' => $totalActual,
            'total_variance' => $totalVariance,
            'total_variance_pct' => $totalBudget != 0 ? ($totalVariance / abs($totalBudget)) * 100 : 0,
            'favorable_count' => count($favorable),
            'unfavorable_count' => count($unfavorable),
            'on_target_count' => count($variances) - count($favorable) - count($unfavorable),
        ];
    }

    /**
     * Get budget performance report for a fiscal year.
     *
     * @param int $budgetId
     * @param int $fiscalYear
     * @return array{success: bool, data: mixed, message: string}
     */
    public function getBudgetPerformanceReport(int $budgetId, int $fiscalYear): array
    {
        try {
            $budget = DB::table('budgets')->where('id', $budgetId)->first();

            if (!$budget) {
                throw new \Exception('Budget not found.');
            }

            // Get all budget lines with YTD actuals
            $report = DB::table('budget_lines as bl')
                ->join('chart_of_accounts as coa', 'coa.id', '=', 'bl.chart_of_account_id')
                ->join('account_types as at', 'at.id', '=', 'coa.account_type_id')
                ->where('bl.budget_id', $budgetId)
                ->select(
                    'coa.account_code',
                    'coa.account_name',
                    'at.type_name as account_type',
                    'bl.total_annual as annual_budget'
                )
                ->get()
                ->map(function ($line) use ($fiscalYear) {
                    // Get YTD actuals
                    $actualYTD = DB::table('journal_entry_details as jed')
                        ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
                        ->join('accounting_periods as ap', 'ap.id', '=', 'je.accounting_period_id')
                        ->where('je.status', 'posted')
                        ->whereYear('je.entry_date', $fiscalYear)
                        ->where('jed.chart_of_account_id', $line->chart_of_account_id)
                        ->sum(DB::raw('jed.debit - jed.credit'));

                    $variance = $actualYTD - $line->annual_budget;
                    $variancePct = $line->annual_budget != 0
                        ? ($variance / abs($line->annual_budget)) * 100
                        : 0;

                    return [
                        'account_code' => $line->account_code,
                        'account_name' => $line->account_name,
                        'account_type' => $line->account_type,
                        'annual_budget' => $line->annual_budget,
                        'ytd_actual' => $actualYTD,
                        'variance' => $variance,
                        'variance_pct' => round($variancePct, 2),
                        'remaining_budget' => $line->annual_budget - $actualYTD,
                    ];
                });

            return [
                'success' => true,
                'data' => [
                    'budget_name' => $budget->budget_name,
                    'fiscal_year' => $fiscalYear,
                    'report' => $report->toArray(),
                ],
                'message' => 'Budget performance report generated successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate budget performance report', [
                'budget_id' => $budgetId,
                'fiscal_year' => $fiscalYear,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to generate report: ' . $e->getMessage(),
            ];
        }
    }
}
