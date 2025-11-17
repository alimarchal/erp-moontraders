<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Services\AccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for calculating and posting depreciation for fixed assets.
 *
 * Supports multiple depreciation methods:
 * - Straight-line (most common)
 * - Declining balance
 * - Double declining balance
 * - Units of production
 */
class DepreciationService
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Calculate depreciation for a single fixed asset for a period.
     *
     * @param object $asset The fixed asset model
     * @param int $periodId The accounting period ID
     * @param float|null $unitsProduced For units of production method
     * @return float The calculated depreciation amount
     */
    public function calculateDepreciation(object $asset, int $periodId, ?float $unitsProduced = null): float
    {
        if ($asset->status !== 'active') {
            return 0;
        }

        $period = AccountingPeriod::findOrFail($periodId);

        switch ($asset->depreciation_method) {
            case 'straight_line':
                return $this->calculateStraightLine($asset, $period);

            case 'declining_balance':
                return $this->calculateDecliningBalance($asset, $period, 1);

            case 'double_declining_balance':
                return $this->calculateDecliningBalance($asset, $period, 2);

            case 'units_of_production':
                return $this->calculateUnitsOfProduction($asset, $period, $unitsProduced);

            default:
                throw new \Exception("Unsupported depreciation method: {$asset->depreciation_method}");
        }
    }

    /**
     * Calculate straight-line depreciation.
     */
    protected function calculateStraightLine(object $asset, object $period): float
    {
        if ($asset->useful_life_months <= 0) {
            return 0;
        }

        $monthlyDepreciation = $asset->depreciable_amount / $asset->useful_life_months;

        // Calculate number of months in this period
        $startDate = max($asset->depreciation_start_date, $period->start_date);
        $endDate = min(now()->toDateString(), $period->end_date);

        $months = max(1, $this->monthsBetween($startDate, $endDate));

        $depreciation = $monthlyDepreciation * $months;

        // Don't exceed remaining depreciable amount
        $remainingDepreciable = max(0, $asset->depreciable_amount - $asset->total_depreciation);

        return min($depreciation, $remainingDepreciable);
    }

    /**
     * Calculate declining balance depreciation.
     */
    protected function calculateDecliningBalance(object $asset, object $period, int $factor = 1): float
    {
        if ($asset->useful_life_years <= 0) {
            return 0;
        }

        $rate = ($factor / $asset->useful_life_years);
        $bookValue = $asset->book_value;

        // Calculate for the period
        $startDate = max($asset->depreciation_start_date, $period->start_date);
        $endDate = min(now()->toDateString(), $period->end_date);

        $months = max(1, $this->monthsBetween($startDate, $endDate));

        $depreciation = $bookValue * $rate * ($months / 12);

        // Don't depreciate below salvage value
        $maxDepreciation = max(0, $bookValue - $asset->salvage_value);

        return min($depreciation, $maxDepreciation);
    }

    /**
     * Calculate units of production depreciation.
     */
    protected function calculateUnitsOfProduction(object $asset, object $period, ?float $unitsProduced): float
    {
        if ($asset->useful_life_units <= 0 || !$unitsProduced) {
            return 0;
        }

        $perUnitDepreciation = $asset->depreciable_amount / $asset->useful_life_units;
        $depreciation = $perUnitDepreciation * $unitsProduced;

        // Don't exceed remaining depreciable amount
        $remainingDepreciable = max(0, $asset->depreciable_amount - $asset->total_depreciation);

        return min($depreciation, $remainingDepreciable);
    }

    /**
     * Calculate depreciation for all active assets for a period.
     *
     * @param int $periodId
     * @param bool $autoPost Whether to automatically post the journal entries
     * @return array{success: bool, data: mixed, message: string}
     */
    public function calculatePeriodDepreciation(int $periodId, bool $autoPost = false): array
    {
        try {
            $period = AccountingPeriod::findOrFail($periodId);

            // Get all active fixed assets
            $assets = DB::table('fixed_assets')
                ->where('status', 'active')
                ->where('depreciation_start_date', '<=', $period->end_date)
                ->get();

            if ($assets->isEmpty()) {
                return [
                    'success' => true,
                    'data' => [],
                    'message' => 'No active assets found for depreciation.',
                ];
            }

            $depreciationEntries = [];
            $totalDepreciation = 0;

            foreach ($assets as $asset) {
                // Check if depreciation already calculated for this period
                $existing = DB::table('depreciation_entries')
                    ->where('fixed_asset_id', $asset->id)
                    ->where('accounting_period_id', $periodId)
                    ->first();

                if ($existing) {
                    continue; // Skip already calculated
                }

                $depreciationAmount = $this->calculateDepreciation($asset, $periodId);

                if ($depreciationAmount > 0) {
                    // Create depreciation entry record
                    $depreciationId = DB::table('depreciation_entries')->insertGetId([
                        'fixed_asset_id' => $asset->id,
                        'accounting_period_id' => $periodId,
                        'depreciation_date' => $period->end_date,
                        'depreciation_amount' => $depreciationAmount,
                        'accumulated_depreciation' => $asset->total_depreciation + $depreciationAmount,
                        'book_value_after' => $asset->book_value - $depreciationAmount,
                        'status' => 'calculated',
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Update asset totals immediately after creating depreciation entry
                    DB::table('fixed_assets')
                        ->where('id', $asset->id)
                        ->increment('total_depreciation', $depreciationAmount);
                    DB::table('fixed_assets')
                        ->where('id', $asset->id)
                        ->decrement('book_value', $depreciationAmount);

                    $depreciationEntries[] = [
                        'id' => $depreciationId,
                        'asset_code' => $asset->asset_code,
                        'asset_name' => $asset->asset_name,
                        'amount' => $depreciationAmount,
                    ];

                    $totalDepreciation += $depreciationAmount;

                    // Optionally create and post journal entry
                    if ($autoPost) {
                        $this->postDepreciationEntry($asset, $depreciationId, $depreciationAmount, $period);
                    }
                }
            }

            return [
                'success' => true,
                'data' => [
                    'entries' => $depreciationEntries,
                    'total_depreciation' => $totalDepreciation,
                    'assets_count' => count($depreciationEntries),
                ],
                'message' => sprintf(
                    'Calculated depreciation for %d assets. Total: %s',
                    count($depreciationEntries),
                    number_format($totalDepreciation, 2)
                ),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to calculate period depreciation', [
                'period_id' => $periodId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to calculate depreciation: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Post a depreciation entry as a journal entry.
     */
    protected function postDepreciationEntry(object $asset, int $depreciationEntryId, float $amount, object $period): void
    {
        $journalData = [
            'entry_date' => $period->end_date,
            'reference' => 'DEP-' . $asset->asset_code . '-' . $period->id,
            'description' => "Depreciation for {$asset->asset_name}",
            'accounting_period_id' => $period->id,
            'lines' => [
                [
                    'account_id' => $asset->depreciation_expense_account_id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Depreciation expense',
                ],
                [
                    'account_id' => $asset->accumulated_depreciation_account_id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Accumulated depreciation',
                ],
            ],
            'auto_post' => true,
        ];

        $result = $this->accountingService->createJournalEntry($journalData);

        if ($result['success']) {
            // Update depreciation entry with journal link
            DB::table('depreciation_entries')
                ->where('id', $depreciationEntryId)
                ->update([
                    'journal_entry_id' => $result['data']->id,
                    'status' => 'posted',
                    'posted_at' => now(),
                ]);
        }
    }

    /**
     * Calculate months between two dates.
     */
    protected function monthsBetween(string $startDate, string $endDate): int
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $interval = $start->diff($end);

        $months = ($interval->y * 12) + $interval->m;
        if ($interval->d > 0) {
            $months += 1;
        }
        return $months;
    }
}
