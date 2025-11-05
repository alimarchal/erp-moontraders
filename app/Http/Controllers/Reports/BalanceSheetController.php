<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountBalance;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BalanceSheetController extends Controller
{
    /**
     * Display the Balance Sheet report.
     */
    public function index(Request $request)
    {
        // Get "as of" date from request
        $asOfDate = $request->input('as_of_date');
        $periodId = $request->input('accounting_period_id');

        // If period selected, use its end date
        if ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $asOfDate = $period->end_date;
            }
        }

        // Default to today if no date specified
        if (!$asOfDate) {
            $asOfDate = now()->format('Y-m-d');
        }

        $driver = DB::connection()->getDriverName();

        // Use database function for PostgreSQL, direct query for MySQL
        if ($driver === 'pgsql') {
            $accounts = collect(DB::select("SELECT * FROM fn_balance_sheet(?::date)", [$asOfDate]));
        } else {
            // MySQL compatible query
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
                    ")
                ])
                ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
                ->leftJoin('journal_entry_details as d', 'd.chart_of_account_id', '=', 'a.id')
                ->leftJoin('journal_entries as je', function ($join) use ($asOfDate) {
                    $join->on('je.id', '=', 'd.journal_entry_id')
                        ->where('je.status', '=', 'posted')
                        ->whereDate('je.entry_date', '<=', $asOfDate);
                })
                ->where('at.report_group', '=', 'BalanceSheet')
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

        // Calculate net income from revenue and expense accounts up to the as_of_date
        if ($driver === 'pgsql') {
            $netIncomeData = collect(DB::select("SELECT * FROM fn_income_statement(NULL, ?::date)", [$asOfDate]));
        } else {
            $netIncomeData = DB::table('chart_of_accounts as a')
                ->select([
                    'at.type_name as account_type',
                    DB::raw("
                        CASE
                            WHEN a.normal_balance = 'debit' 
                            THEN COALESCE(SUM(d.debit - d.credit), 0)
                            WHEN a.normal_balance = 'credit' 
                            THEN COALESCE(SUM(d.credit - d.debit), 0)
                            ELSE 0
                        END AS balance
                    ")
                ])
                ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
                ->leftJoin('journal_entry_details as d', 'd.chart_of_account_id', '=', 'a.id')
                ->leftJoin('journal_entries as je', function ($join) use ($asOfDate) {
                    $join->on('je.id', '=', 'd.journal_entry_id')
                        ->where('je.status', '=', 'posted')
                        ->whereDate('je.entry_date', '<=', $asOfDate);
                })
                ->where('at.report_group', '=', 'IncomeStatement')
                ->where('a.is_active', '=', true)
                ->groupBy('a.id', 'at.type_name', 'a.normal_balance')
                ->get();
        }

        $totalRevenue = $netIncomeData->filter(function ($item) {
            return str_contains(strtolower($item->account_type), 'income') ||
                str_contains(strtolower($item->account_type), 'revenue');
        })->sum('balance');

        $totalExpenses = $netIncomeData->filter(function ($item) {
            return str_contains(strtolower($item->account_type), 'expense') ||
                str_contains(strtolower($item->account_type), 'cost');
        })->sum('balance');

        $netIncome = $totalRevenue - $totalExpenses;

        // Get all periods for dropdown
        $accountingPeriods = AccountingPeriod::orderBy('end_date', 'desc')->get();

        return view('reports.balance-sheet.index', [
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
            'netIncome' => $netIncome,
            'asOfDate' => $asOfDate,
            'periodId' => $periodId,
            'accountingPeriods' => $accountingPeriods,
        ]);
    }
}
