<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

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

        // If period selected, use its dates
        if ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $startDate = $period->start_date;
                $endDate = $period->end_date;
            }
        }

        // Default to current open period if no dates specified
        if (!$startDate || !$endDate) {
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

        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;

        // Build income statement query with date filtering
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
            ->leftJoin('journal_entries', function ($join) use ($startDate, $endDate) {
                $join->on('journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted')
                    ->whereDate('journal_entries.entry_date', '>=', $startDate)
                    ->whereDate('journal_entries.entry_date', '<=', $endDate);
            })
            ->where('account_types.report_group', 'IncomeStatement')
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
