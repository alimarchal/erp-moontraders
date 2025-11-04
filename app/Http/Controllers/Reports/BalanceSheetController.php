<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\BalanceSheetAccount;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BalanceSheetController extends Controller
{
    /**
     * Display the Balance Sheet report.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 50;

        $accounts = QueryBuilder::for(BalanceSheetAccount::query())
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
                'balance',
            ])
            ->defaultSort('account_code')
            ->paginate($perPage)
            ->withQueryString();

        // Group by account type for better presentation
        $groupedAccounts = $accounts->groupBy('account_type');

        return view('reports.balance-sheet.index', [
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
        ]);
    }
}
