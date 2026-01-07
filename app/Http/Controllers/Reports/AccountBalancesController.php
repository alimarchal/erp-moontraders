<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountBalancesController extends Controller
{
    /**
     * Display a listing of Account Balances with date filtering.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 200);
        $perPage = \in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 200;

        // Handle date and period selection
        $periodId = $request->input('accounting_period_id');
        $asOfDate = $request->input('as_of_date');

        // If period is selected, get its end date
        if ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $asOfDate = $period->end_date;
            }
        }

        // Default to today if no date specified
        if (! $asOfDate) {
            $asOfDate = now()->format('Y-m-d');
        }

        // Build query with date filtering - Only show posting accounts (non-group accounts)
        $balancesQuery = ChartOfAccount::query()->select([
            'chart_of_accounts.id as account_id',
            'chart_of_accounts.account_code',
            'chart_of_accounts.account_name',
            'chart_of_accounts.account_type_id',
            'account_types.type_name as account_type',
            'account_types.report_group',
            'chart_of_accounts.normal_balance',
            'chart_of_accounts.is_active',
            'chart_of_accounts.is_group',
            DB::raw("COALESCE(SUM(CASE WHEN journal_entries.entry_date <= '{$asOfDate}' THEN journal_entry_details.debit ELSE 0 END), 0) as total_debits"),
            DB::raw("COALESCE(SUM(CASE WHEN journal_entries.entry_date <= '{$asOfDate}' THEN journal_entry_details.credit ELSE 0 END), 0) as total_credits"),
            DB::raw("COALESCE(SUM(CASE WHEN journal_entries.entry_date <= '{$asOfDate}' THEN journal_entry_details.debit - journal_entry_details.credit ELSE 0 END), 0) as balance"),
        ])
            ->leftJoin('account_types', 'chart_of_accounts.account_type_id', '=', 'account_types.id')
            ->leftJoin('journal_entry_details', 'chart_of_accounts.id', '=', 'journal_entry_details.chart_of_account_id')
            ->leftJoin('journal_entries', function ($join) {
                $join->on('journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.status', '=', 'posted');
            })
            ->where('chart_of_accounts.is_group', false)
            ->groupBy([
                'chart_of_accounts.id',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'chart_of_accounts.account_type_id',
                'account_types.type_name',
                'account_types.report_group',
                'chart_of_accounts.normal_balance',
                'chart_of_accounts.is_active',
                'chart_of_accounts.is_group',
            ]);

        // Apply QueryBuilder filters
        $balances = QueryBuilder::for($balancesQuery)
            ->allowedFilters([
                // Text/partial matches
                AllowedFilter::partial('account_code', 'chart_of_accounts.account_code'),
                AllowedFilter::partial('account_name', 'chart_of_accounts.account_name'),
                AllowedFilter::partial('account_type', 'account_types.type_name'),

                // Exact matches
                AllowedFilter::exact('normal_balance', 'chart_of_accounts.normal_balance'),
                AllowedFilter::exact('is_active', 'chart_of_accounts.is_active'),

                // Numeric ranges
                AllowedFilter::callback('balance_min', function ($query, $value) use ($asOfDate) {
                    if (filled($value)) {
                        $query->havingRaw(
                            'COALESCE(SUM(CASE WHEN journal_entries.entry_date <= ? THEN journal_entry_details.debit - journal_entry_details.credit ELSE 0 END), 0) >= ?',
                            [$asOfDate, $value]
                        );
                    }
                }),
                AllowedFilter::callback('balance_max', function ($query, $value) use ($asOfDate) {
                    if (filled($value)) {
                        $query->havingRaw(
                            'COALESCE(SUM(CASE WHEN journal_entries.entry_date <= ? THEN journal_entry_details.debit - journal_entry_details.credit ELSE 0 END), 0) <= ?',
                            [$asOfDate, $value]
                        );
                    }
                }),
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
        $accountTypes = ChartOfAccount::query()->select('account_types.type_name as account_type')
            ->join('account_types', 'chart_of_accounts.account_type_id', '=', 'account_types.id')
            ->distinct()
            ->whereNotNull('account_types.type_name')
            ->orderBy('account_types.type_name')
            ->pluck('account_type');

        $accounts = ChartOfAccount::query()->select('id as account_id', 'account_code', 'account_name')
            ->where('is_group', false)
            ->whereNotNull('account_code')
            ->orderBy('account_code')
            ->get();

        // Get accounting periods for dropdown
        $accountingPeriods = AccountingPeriod::query()->orderBy('start_date', 'desc')->get();

        return view('reports.account-balances.index', [
            'balances' => $balances,
            'accountTypes' => $accountTypes,
            'accounts' => $accounts,
            'asOfDate' => $asOfDate,
            'periodId' => $periodId,
            'accountingPeriods' => $accountingPeriods,
        ]);
    }
}
