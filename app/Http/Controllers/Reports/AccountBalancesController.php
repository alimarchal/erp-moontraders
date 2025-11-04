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
            ->orderBy('account_code')
            ->paginate(25)
            ->withQueryString();

        return view('reports.account-balances.index', [
            'balances' => $balances,
        ]);
    }
}
