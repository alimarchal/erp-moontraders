<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncomeStatementController extends Controller
{
    /**
     * Display the Income Statement report.
     */
    public function index(Request $request)
    {
        // Get date range from request or use current period
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $periodId = $request->input('accounting_period_id');

        // Priority: Manual dates > Period dates > Default to current period

        // If BOTH start and end dates are manually provided, use them (ignore period)
        if ($startDate && $endDate) {
            // Use manual dates, ignore period selection
            $periodId = null; // Clear period selection when using manual dates
        }
        // If period selected and manual dates NOT fully provided, use period dates
        elseif ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $startDate = $period->start_date;
                $endDate = $period->end_date;
            }
        }
        // Default to current open period if no dates or period specified
        else {
            $currentPeriod = AccountingPeriod::where('status', 'open')
                ->orderBy('start_date', 'desc')
                ->first();

            if ($currentPeriod) {
                $startDate = $currentPeriod->start_date;
                $endDate = $currentPeriod->end_date;
                $periodId = $currentPeriod->id;
            } else {
                // Fallback to current year if no period found
                $startDate = now()->startOfYear()->format('Y-m-d');
                $endDate = now()->format('Y-m-d');
            }
        }

        $driver = DB::connection()->getDriverName();

        // Use database function for PostgreSQL, direct query for MySQL
        if ($driver === 'pgsql') {
            $accounts = collect(DB::select('SELECT * FROM fn_income_statement(?::date, ?::date)', [$startDate, $endDate]));
        } else {
            // MySQL compatible query with proper date filtering
            $accounts = DB::table('chart_of_accounts as a')
                ->select([
                    'a.id as account_id',
                    'a.account_code',
                    'a.account_name',
                    'at.type_name as account_type',
                    'at.report_group',
                    'a.normal_balance',
                    DB::raw("
                        CASE
                            WHEN a.normal_balance = 'debit' 
                            THEN COALESCE(SUM(d.debit - d.credit), 0)
                            WHEN a.normal_balance = 'credit' 
                            THEN COALESCE(SUM(d.credit - d.debit), 0)
                            ELSE 0
                        END AS balance
                    "),
                ])
                ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
                ->leftJoin(DB::raw("(
                    SELECT jed.chart_of_account_id, jed.debit, jed.credit
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE je.status = 'posted'
                    AND je.entry_date >= '{$startDate}'
                    AND je.entry_date <= '{$endDate}'
                ) as d"), 'd.chart_of_account_id', '=', 'a.id')
                ->where('at.report_group', '=', 'IncomeStatement')
                ->where('a.is_active', '=', true)
                ->groupBy('a.id', 'a.account_code', 'a.account_name', 'at.type_name', 'at.report_group', 'a.normal_balance')
                ->havingRaw('COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0')
                ->orderBy('a.account_code')
                ->get();
        }

        // Apply filters if provided
        if ($request->has('filter.account_code')) {
            $accounts = $accounts->filter(function ($account) use ($request) {
                return str_contains(strtolower($account->account_code), strtolower($request->input('filter.account_code')));
            });
        }

        if ($request->has('filter.account_name')) {
            $accounts = $accounts->filter(function ($account) use ($request) {
                return str_contains(strtolower($account->account_name), strtolower($request->input('filter.account_name')));
            });
        }

        if ($request->has('filter.account_type')) {
            $accounts = $accounts->filter(function ($account) use ($request) {
                return str_contains(strtolower($account->account_type), strtolower($request->input('filter.account_type')));
            });
        }

        // Group by account type for better presentation
        $groupedAccounts = $accounts->groupBy('account_type');

        // Get all periods for dropdown
        $accountingPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        return view('reports.income-statement.index', [
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'periodId' => $periodId,
            'accountingPeriods' => $accountingPeriods,
        ]);
    }
}
