<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class TrialBalanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-view-financial'),
        ];
    }

    /**
     * Display the Trial Balance report.
     */
    public function index(Request $request)
    {
        // Get "as of" date from request
        $asOfDate = $request->input('as_of_date');
        $periodId = $request->input('accounting_period_id');

        // Priority: Manual date > Period date > Default to today

        // If manual date provided, use it (ignore period)
        if ($asOfDate) {
            // Use manual date, ignore period selection
            $periodId = null; // Clear period selection when using manual date
        }
        // If period selected and manual date NOT provided, use period end date
        elseif ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $asOfDate = $period->end_date;
            }
        }
        // Default to today if neither date nor period specified
        else {
            $asOfDate = now()->format('Y-m-d');
        }

        $totals = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '<=', $asOfDate)
            ->selectRaw('COALESCE(SUM(jed.debit), 0) as total_debits')
            ->selectRaw('COALESCE(SUM(jed.credit), 0) as total_credits')
            ->first();

        $trialBalance = (object) [
            'total_debits' => (float) ($totals->total_debits ?? 0),
            'total_credits' => (float) ($totals->total_credits ?? 0),
            'difference' => (float) ($totals->total_debits ?? 0) - (float) ($totals->total_credits ?? 0),
        ];

        // Get all periods for dropdown
        $accountingPeriods = AccountingPeriod::orderBy('end_date', 'desc')->get();

        return view('reports.trial-balance.index', [
            'trialBalance' => $trialBalance,
            'asOfDate' => $asOfDate,
            'periodId' => $periodId,
            'accountingPeriods' => $accountingPeriods,
        ]);
    }
}
