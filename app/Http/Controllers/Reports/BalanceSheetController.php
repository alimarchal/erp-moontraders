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

        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;

        // Build balance sheet query with date filtering
        $query = ChartOfAccount::select([
            'chart_of_accounts.id as account_id',
            'chart_of_accounts.account_code',
            'chart_of_accounts.account_name',
            'account_types.type_name as account_type',
            'chart_of_accounts.normal_balance',
            DB::raw("
                CASE
                    WHEN chart_of_accounts.normal_balance = 'debit' 
                    THEN COALESCE(SUM(journal_entry_details.debit - journal_entry_details.credit), 0)
                    WHEN chart_of_accounts.normal_balance = 'credit' 
                    THEN COALESCE(SUM(journal_entry_details.credit - journal_entry_details.debit), 0)
                    ELSE 0
                END AS balance
            ")
        ])
            ->join('account_types', 'account_types.id', '=', 'chart_of_accounts.account_type_id')
            ->leftJoin('journal_entry_details', 'journal_entry_details.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->leftJoin('journal_entries', function ($join) use ($asOfDate) {
                $join->on('journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted')
                    ->whereDate('journal_entries.entry_date', '<=', $asOfDate);
            })
            ->where('account_types.report_group', 'BalanceSheet')
            ->where('chart_of_accounts.is_active', true)
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'account_types.type_name',
                'chart_of_accounts.normal_balance'
            )
            ->havingRaw('COALESCE(SUM(journal_entry_details.debit), 0) != 0 OR COALESCE(SUM(journal_entry_details.credit), 0) != 0');

        $accounts = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::partial('account_code'),
                AllowedFilter::partial('account_name'),
                AllowedFilter::partial('account_type'),
            ])
            ->allowedSorts([
                'account_code',
                'account_name',
                'account_type',
                'balance',
            ])
            ->defaultSort('account_code')
            ->paginate($perPage)
            ->withQueryString();

        // Group by account type for better presentation
        $groupedAccounts = $accounts->groupBy('account_type');

        // Calculate net income from revenue and expense accounts up to the as_of_date
        $netIncomeQuery = ChartOfAccount::select([
            'account_types.type_name as account_type',
            DB::raw("
                CASE
                    WHEN chart_of_accounts.normal_balance = 'debit' 
                    THEN COALESCE(SUM(journal_entry_details.debit - journal_entry_details.credit), 0)
                    WHEN chart_of_accounts.normal_balance = 'credit' 
                    THEN COALESCE(SUM(journal_entry_details.credit - journal_entry_details.debit), 0)
                    ELSE 0
                END AS balance
            ")
        ])
            ->join('account_types', 'account_types.id', '=', 'chart_of_accounts.account_type_id')
            ->leftJoin('journal_entry_details', 'journal_entry_details.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->leftJoin('journal_entries', function ($join) use ($asOfDate) {
                $join->on('journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted')
                    ->whereDate('journal_entries.entry_date', '<=', $asOfDate);
            })
            ->where('account_types.report_group', 'IncomeStatement')
            ->where('chart_of_accounts.is_active', true)
            ->groupBy('chart_of_accounts.id', 'account_types.type_name', 'chart_of_accounts.normal_balance')
            ->get();

        $totalRevenue = $netIncomeQuery->filter(function ($item) {
            return str_contains(strtolower($item->account_type), 'income') ||
                str_contains(strtolower($item->account_type), 'revenue');
        })->sum('balance');

        $totalExpenses = $netIncomeQuery->filter(function ($item) {
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
