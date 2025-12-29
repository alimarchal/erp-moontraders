<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FmrAmrComparisonController extends Controller
{
    /**
     * GL Account codes for the report.
     */
    private const FMR_LIQUID_ACCOUNT_CODE = '4210';

    private const FMR_POWDER_ACCOUNT_CODE = '4220';

    private const AMR_POWDER_ACCOUNT_CODE = '5252';

    private const AMR_LIQUID_ACCOUNT_CODE = '5262';

    /**
     * Display the FMR vs AMR Comparison report.
     */
    public function index(Request $request)
    {
        // Default to current year (Jan 1 to Dec 31) if no dates provided
        $startDate = $request->input('filter.start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('filter.end_date', now()->endOfYear()->format('Y-m-d'));

        // Get account IDs for the GL codes
        $accounts = DB::table('chart_of_accounts')
            ->whereIn('account_code', [
                self::FMR_LIQUID_ACCOUNT_CODE,
                self::FMR_POWDER_ACCOUNT_CODE,
                self::AMR_POWDER_ACCOUNT_CODE,
                self::AMR_LIQUID_ACCOUNT_CODE,
            ])
            ->pluck('id', 'account_code');

        $fmrLiquidAccountId = $accounts[self::FMR_LIQUID_ACCOUNT_CODE] ?? null;
        $fmrPowderAccountId = $accounts[self::FMR_POWDER_ACCOUNT_CODE] ?? null;
        $amrPowderAccountId = $accounts[self::AMR_POWDER_ACCOUNT_CODE] ?? null;
        $amrLiquidAccountId = $accounts[self::AMR_LIQUID_ACCOUNT_CODE] ?? null;

        // Generate all months for the selected date range
        $allMonths = $this->generateAllMonths($startDate, $endDate);

        // Build the query to get monthly totals
        $actualData = collect();

        if ($fmrLiquidAccountId && $fmrPowderAccountId && $amrPowderAccountId && $amrLiquidAccountId) {
            $actualData = DB::table('journal_entry_details as jed')
                ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
                ->whereIn('jed.chart_of_account_id', [
                    $fmrLiquidAccountId,
                    $fmrPowderAccountId,
                    $amrPowderAccountId,
                    $amrLiquidAccountId,
                ])
                ->where('je.status', 'posted')
                ->whereNull('je.deleted_at')
                ->whereBetween('je.entry_date', [$startDate, $endDate])
                ->select(
                    DB::raw('YEAR(je.entry_date) as year'),
                    DB::raw('MONTH(je.entry_date) as month'),
                    DB::raw("SUM(CASE WHEN jed.chart_of_account_id = {$fmrLiquidAccountId} THEN (jed.credit - jed.debit) ELSE 0 END) as fmr_liquid_total"),
                    DB::raw("SUM(CASE WHEN jed.chart_of_account_id = {$fmrPowderAccountId} THEN (jed.credit - jed.debit) ELSE 0 END) as fmr_powder_total"),
                    DB::raw("SUM(CASE WHEN jed.chart_of_account_id = {$amrPowderAccountId} THEN (jed.debit - jed.credit) ELSE 0 END) as amr_powder_total"),
                    DB::raw("SUM(CASE WHEN jed.chart_of_account_id = {$amrLiquidAccountId} THEN (jed.debit - jed.credit) ELSE 0 END) as amr_liquid_total")
                )
                ->groupBy(DB::raw('YEAR(je.entry_date)'), DB::raw('MONTH(je.entry_date)'))
                ->get()
                ->keyBy(fn ($row) => $row->year.'-'.$row->month);
        }

        // Merge actual data with all months (show 0.00 for missing months)
        $reportData = $allMonths->map(function ($monthData) use ($actualData) {
            $key = $monthData['year'].'-'.$monthData['month'];
            $actual = $actualData->get($key);

            $fmrLiquid = $actual->fmr_liquid_total ?? 0;
            $fmrPowder = $actual->fmr_powder_total ?? 0;
            $amrLiquid = $actual->amr_liquid_total ?? 0;
            $amrPowder = $actual->amr_powder_total ?? 0;

            return (object) [
                'year' => $monthData['year'],
                'month' => $monthData['month'],
                'month_year' => $monthData['month_year'],
                'fmr_liquid_total' => $fmrLiquid,
                'fmr_powder_total' => $fmrPowder,
                'fmr_total' => $fmrLiquid + $fmrPowder,
                'amr_liquid_total' => $amrLiquid,
                'amr_powder_total' => $amrPowder,
                'amr_total' => $amrLiquid + $amrPowder,
                'liquid_diff' => $amrLiquid - $fmrLiquid,
                'powder_diff' => $amrPowder - $fmrPowder,
                'difference' => ($amrLiquid + $amrPowder) - ($fmrLiquid + $fmrPowder),
            ];
        });

        // Calculate grand totals
        $grandTotals = (object) [
            'fmr_liquid_total' => $reportData->sum('fmr_liquid_total'),
            'fmr_powder_total' => $reportData->sum('fmr_powder_total'),
            'fmr_total' => $reportData->sum('fmr_total'),
            'amr_liquid_total' => $reportData->sum('amr_liquid_total'),
            'amr_powder_total' => $reportData->sum('amr_powder_total'),
            'amr_total' => $reportData->sum('amr_total'),
            'liquid_diff' => $reportData->sum('liquid_diff'),
            'powder_diff' => $reportData->sum('powder_diff'),
            'difference' => $reportData->sum('difference'),
        ];

        return view('reports.fmr-amr-comparison.index', [
            'reportData' => $reportData,
            'grandTotals' => $grandTotals,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Generate all months between start and end date.
     */
    private function generateAllMonths(string $startDate, string $endDate): \Illuminate\Support\Collection
    {
        $start = \Carbon\Carbon::parse($startDate)->startOfMonth();
        $end = \Carbon\Carbon::parse($endDate)->endOfMonth();

        $months = collect();

        while ($start->lte($end)) {
            $months->push([
                'year' => $start->year,
                'month' => $start->month,
                'month_year' => $start->format('F - Y'),
            ]);
            $start->addMonth();
        }

        return $months;
    }
}
