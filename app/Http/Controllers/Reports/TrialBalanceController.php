<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountBalance;
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
        // Get the summary from the view
        $trialBalance = DB::table('vw_trial_balance')->first();

        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;

        // Get detailed account balances using QueryBuilder
        $accounts = QueryBuilder::for(AccountBalance::query())
            ->where(function ($q) {
                $q->where('total_debits', '!=', 0)
                    ->orWhere('total_credits', '!=', 0);
            })
            ->allowedFilters([
                // Text/partial matches
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
        $accountTypes = AccountBalance::select('account_type')
            ->distinct()
            ->whereNotNull('account_type')
            ->orderBy('account_type')
            ->pluck('account_type');

        $accountsList = AccountBalance::select('account_id', 'account_code', 'account_name')
            ->whereNotNull('account_code')
            ->orderBy('account_code')
            ->get();

        return view('reports.trial-balance.index', [
            'trialBalance' => $trialBalance,
            'accounts' => $accounts,
            'accountTypes' => $accountTypes,
            'accountsList' => $accountsList,
        ]);
    }
}
