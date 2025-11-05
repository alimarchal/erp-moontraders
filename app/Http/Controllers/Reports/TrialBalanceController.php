<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TrialBalanceController extends Controller
{
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

        $driver = DB::connection()->getDriverName();

        // Get data based on database driver
        if ($driver === 'pgsql') {
            // PostgreSQL: Use functions
            $accountsData = DB::select('SELECT * FROM fn_trial_balance(?)', [$asOfDate]);
            $accountsCollection = collect($accountsData)->map(function ($row) {
                return (object) [
                    'account_id' => $row->account_id,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'account_type' => $row->account_type,
                    'normal_balance' => $row->normal_balance,
                    'total_debits' => $row->total_debits,
                    'total_credits' => $row->total_credits,
                    'balance' => $row->balance,
                ];
            });
        } else {
            // MySQL: Use direct queries with proper date filtering in subquery
            $accountsData = DB::select("
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    a.normal_balance,
                    COALESCE(SUM(filtered.debit), 0) AS total_debits,
                    COALESCE(SUM(filtered.credit), 0) AS total_credits,
                    COALESCE(SUM(filtered.debit - filtered.credit), 0) AS balance
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN (
                    SELECT 
                        jed.chart_of_account_id,
                        jed.debit,
                        jed.credit
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE je.status = 'posted' AND je.entry_date <= ?
                ) filtered ON filtered.chart_of_account_id = a.id
                WHERE a.is_active = 1
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, a.normal_balance
                HAVING COALESCE(SUM(filtered.debit), 0) <> 0 OR COALESCE(SUM(filtered.credit), 0) <> 0
                ORDER BY a.account_code
            ", [$asOfDate]);

            $accountsCollection = collect($accountsData)->map(function ($row) {
                return (object) [
                    'account_id' => $row->account_id,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'account_type' => $row->account_type,
                    'normal_balance' => $row->normal_balance,
                    'total_debits' => $row->total_debits,
                    'total_credits' => $row->total_credits,
                    'balance' => $row->balance,
                ];
            });
        }

        // Calculate trial balance summary from account balances (not raw journal entries)
        $totalDebitBalance = $accountsCollection->filter(fn($a) => $a->balance > 0)->sum('balance');
        $totalCreditBalance = abs($accountsCollection->filter(fn($a) => $a->balance < 0)->sum('balance'));

        $trialBalance = (object) [
            'total_debits' => $totalDebitBalance,
            'total_credits' => $totalCreditBalance,
            'difference' => $totalDebitBalance - $totalCreditBalance,
        ];

        // Apply filters
        if ($request->filled('filter.account_code')) {
            $accountsCollection = $accountsCollection->filter(function ($item) use ($request) {
                return stripos($item->account_code, $request->input('filter.account_code')) !== false;
            });
        }
        if ($request->filled('filter.account_name')) {
            $accountsCollection = $accountsCollection->filter(function ($item) use ($request) {
                return stripos($item->account_name, $request->input('filter.account_name')) !== false;
            });
        }
        if ($request->filled('filter.account_type')) {
            $accountsCollection = $accountsCollection->filter(function ($item) use ($request) {
                return stripos($item->account_type, $request->input('filter.account_type')) !== false;
            });
        }

        // Apply sorting
        $sortField = $request->input('sort', 'account_code');
        $sortDirection = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sortField, '-');

        $accountsCollection = $sortDirection === 'asc'
            ? $accountsCollection->sortBy($sortField)->values()
            : $accountsCollection->sortByDesc($sortField)->values();

        // Return all accounts (no pagination for trial balance)
        $accounts = $accountsCollection;

        // Get distinct values for dropdowns
        $accountTypes = ChartOfAccount::join('account_types', 'account_types.id', '=', 'chart_of_accounts.account_type_id')
            ->select('account_types.type_name')
            ->distinct()
            ->orderBy('account_types.type_name')
            ->pluck('account_types.type_name');

        $accountsList = ChartOfAccount::select('id as account_id', 'account_code', 'account_name')
            ->whereNotNull('account_code')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        // Get all periods for dropdown
        $accountingPeriods = AccountingPeriod::orderBy('end_date', 'desc')->get();

        return view('reports.trial-balance.index', [
            'trialBalance' => $trialBalance,
            'accounts' => $accounts,
            'accountTypes' => $accountTypes,
            'accountsList' => $accountsList,
            'asOfDate' => $asOfDate,
            'periodId' => $periodId,
            'accountingPeriods' => $accountingPeriods,
        ]);
    }
}
