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
            ->orderBy('account_code')
            ->paginate(50)
            ->withQueryString();

        return view('reports.trial-balance.index', [
            'trialBalance' => $trialBalance,
            'accounts' => $accounts,
        ]);
    }
}
