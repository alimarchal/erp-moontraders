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
    private const FMR_ACCOUNT_CODE = '4210';

    private const AMR_POWDER_ACCOUNT_CODE = '5252';

    private const AMR_LIQUID_ACCOUNT_CODE = '5262';

    /**
     * Display the FMR vs AMR Comparison report.
     */
    public function index(Request $request)
    {
        // Default to current month if no dates provided
        $startDate = $request->input('filter.start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('filter.end_date', now()->endOfMonth()->format('Y-m-d'));

        // Get account IDs for the GL codes
        $accounts = DB::table('chart_of_accounts')
            ->whereIn('account_code', [
                self::FMR_ACCOUNT_CODE,
                self::AMR_POWDER_ACCOUNT_CODE,
                self::AMR_LIQUID_ACCOUNT_CODE,
            ])
            ->pluck('id', 'account_code');

        $fmrAccountId = $accounts[self::FMR_ACCOUNT_CODE] ?? null;
        $amrPowderAccountId = $accounts[self::AMR_POWDER_ACCOUNT_CODE] ?? null;
        $amrLiquidAccountId = $accounts[self::AMR_LIQUID_ACCOUNT_CODE] ?? null;

        // Build the query to get monthly totals
        $reportData = collect();

        if ($fmrAccountId && $amrPowderAccountId && $amrLiquidAccountId) {
            $reportData = DB::table('journal_entry_details as jed')
                ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
                ->whereIn('jed.chart_of_account_id', [$fmrAccountId, $amrPowderAccountId, $amrLiquidAccountId])
                ->where('je.status', 'posted')
                ->whereNull('je.deleted_at')
                ->whereBetween('je.entry_date', [$startDate, $endDate])
                ->select(
                    DB::raw('YEAR(je.entry_date) as year'),
                    DB::raw('MONTH(je.entry_date) as month'),
                    DB::raw("SUM(CASE WHEN jed.chart_of_account_id = {$fmrAccountId} THEN (jed.credit - jed.debit) ELSE 0 END) as fmr_total"),
                    DB::raw("SUM(CASE WHEN jed.chart_of_account_id = {$amrPowderAccountId} THEN (jed.debit - jed.credit) ELSE 0 END) as amr_powder_total"),
                    DB::raw("SUM(CASE WHEN jed.chart_of_account_id = {$amrLiquidAccountId} THEN (jed.debit - jed.credit) ELSE 0 END) as amr_liquid_total")
                )
                ->groupBy(DB::raw('YEAR(je.entry_date)'), DB::raw('MONTH(je.entry_date)'))
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($row) {
                    $row->amr_total = $row->amr_powder_total + $row->amr_liquid_total;
                    $row->difference = $row->amr_total - $row->fmr_total;
                    $row->month_year = \Carbon\Carbon::createFromDate($row->year, $row->month, 1)->format('F - Y');

                    return $row;
                });
        }

        // Calculate grand totals
        $grandTotals = (object) [
            'fmr_total' => $reportData->sum('fmr_total'),
            'amr_powder_total' => $reportData->sum('amr_powder_total'),
            'amr_liquid_total' => $reportData->sum('amr_liquid_total'),
            'amr_total' => $reportData->sum('amr_total'),
            'difference' => $reportData->sum('difference'),
        ];

        return view('reports.fmr-amr-comparison.index', [
            'reportData' => $reportData,
            'grandTotals' => $grandTotals,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
