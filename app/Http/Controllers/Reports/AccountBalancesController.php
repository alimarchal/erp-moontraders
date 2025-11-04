<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountBalance;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountBalancesController extends Controller
{
    /**
     * Display a listing of Account Balances.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 25;

        $balances = QueryBuilder::for(AccountBalance::query())
            ->allowedFilters([
                // Text/partial matches
                AllowedFilter::partial('account_code'),
                AllowedFilter::partial('account_name'),
                AllowedFilter::partial('account_type'),
                AllowedFilter::partial('cost_center_name'),

                // Exact matches
                AllowedFilter::exact('report_group'),
                AllowedFilter::exact('normal_balance'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_group'),

                // Numeric ranges
                AllowedFilter::callback('balance_min', function ($query, $value) {
                    if (filled($value)) {
                        $query->where('balance', '>=', $value);
                    }
                }),
                AllowedFilter::callback('balance_max', function ($query, $value) {
                    if (filled($value)) {
                        $query->where('balance', '<=', $value);
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
        $accountTypes = AccountBalance::select('account_type')
            ->distinct()
            ->whereNotNull('account_type')
            ->orderBy('account_type')
            ->pluck('account_type');

        $accounts = AccountBalance::select('account_id', 'account_code', 'account_name')
            ->whereNotNull('account_code')
            ->orderBy('account_code')
            ->get();

        return view('reports.account-balances.index', [
            'balances' => $balances,
            'accountTypes' => $accountTypes,
            'accounts' => $accounts,
        ]);
    }
}
