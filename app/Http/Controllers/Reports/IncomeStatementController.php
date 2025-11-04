<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\IncomeStatementAccount;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class IncomeStatementController extends Controller
{
    /**
     * Display the Income Statement report.
     */
    public function index(Request $request)
    {
        $accounts = QueryBuilder::for(IncomeStatementAccount::query())
            ->allowedFilters([
                // Text/partial matches
                AllowedFilter::partial('account_code'),
                AllowedFilter::partial('account_name'),
                AllowedFilter::partial('account_type'),
            ])
            ->orderBy('account_code')
            ->paginate(50)
            ->withQueryString();

        // Group by account type for better presentation
        $groupedAccounts = $accounts->groupBy('account_type');

        return view('reports.income-statement.index', [
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
        ]);
    }
}
