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

        // Get the summary from database with date filter
        $trialBalance = DB::select("
            SELECT 
                SUM(jed.debit) AS total_debits,
                SUM(jed.credit) AS total_credits,
                SUM(jed.debit) - SUM(jed.credit) AS difference
            FROM journal_entry_details jed
            JOIN journal_entries je ON je.id = jed.journal_entry_id
            WHERE je.status = 'posted' AND je.entry_date <= ?
        ", [$asOfDate]);

        $trialBalance = (object) [
            'total_debits' => $trialBalance[0]->total_debits ?? 0,
            'total_credits' => $trialBalance[0]->total_credits ?? 0,
            'difference' => $trialBalance[0]->difference ?? 0,
        ];

        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;

        // Get detailed account balances using QueryBuilder with date filter
        $query = ChartOfAccount::select([
            'chart_of_accounts.id as account_id',
            'chart_of_accounts.account_code',
            'chart_of_accounts.account_name',
            'account_types.type_name as account_type',
            'chart_of_accounts.normal_balance',
            DB::raw("COALESCE(SUM(CASE WHEN journal_entries.entry_date <= '{$asOfDate}' THEN journal_entry_details.debit ELSE 0 END), 0) as total_debits"),
            DB::raw("COALESCE(SUM(CASE WHEN journal_entries.entry_date <= '{$asOfDate}' THEN journal_entry_details.credit ELSE 0 END), 0) as total_credits"),
            DB::raw("COALESCE(SUM(CASE WHEN journal_entries.entry_date <= '{$asOfDate}' THEN journal_entry_details.debit - journal_entry_details.credit ELSE 0 END), 0) as balance")
        ])
            ->join('account_types', 'account_types.id', '=', 'chart_of_accounts.account_type_id')
            ->leftJoin('journal_entry_details', 'journal_entry_details.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->leftJoin('journal_entries', function ($join) {
                $join->on('journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted');
            })
            ->where('chart_of_accounts.is_active', true)
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'account_types.type_name',
                'chart_of_accounts.normal_balance'
            )
            ->havingRaw("COALESCE(SUM(CASE WHEN journal_entries.entry_date <= '{$asOfDate}' THEN journal_entry_details.debit ELSE 0 END), 0) != 0 OR COALESCE(SUM(CASE WHEN journal_entries.entry_date <= '{$asOfDate}' THEN journal_entry_details.credit ELSE 0 END), 0) != 0");

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
                'total_debits',
                'total_credits',
                'balance',
            ])
            ->defaultSort('account_code')
            ->paginate($perPage)
            ->withQueryString();

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
