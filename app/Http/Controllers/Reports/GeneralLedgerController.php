<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GeneralLedgerController extends Controller
{
    /**
     * Display a listing of the General Ledger entries.
     */
    public function index(Request $request)
    {
        $statusOptions = [
            'draft' => 'Draft',
            'posted' => 'Posted',
            'void' => 'Void',
        ];

        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 100;

        // Handle accounting period selection
        $periodId = $request->input('accounting_period_id');
        $entryDateFrom = $request->input('filter.entry_date_from');
        $entryDateTo = $request->input('filter.entry_date_to');

        // If period is selected, use its date range
        if ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $entryDateFrom = $period->start_date;
                $entryDateTo = $period->end_date;
                // Override the filter parameters
                $request->merge([
                    'filter' => array_merge($request->input('filter', []), [
                        'entry_date_from' => $entryDateFrom,
                        'entry_date_to' => $entryDateTo,
                    ]),
                ]);
            }
        }

        $ledgerEntries = QueryBuilder::for(GeneralLedgerEntry::query())
            ->allowedFilters([
                // Text/partial matches
                AllowedFilter::partial('account_code'),
                AllowedFilter::partial('account_name'),
                AllowedFilter::partial('reference'),
                AllowedFilter::partial('journal_description'),
                AllowedFilter::partial('line_description'),
                AllowedFilter::partial('cost_center_code'),
                AllowedFilter::partial('cost_center_name'),
                AllowedFilter::partial('currency_code'),

                // Exact matches
                AllowedFilter::exact('status'),
                AllowedFilter::exact('journal_entry_id'),
                AllowedFilter::exact('line_no'),
                AllowedFilter::exact('account_id'),

                // Date range
                AllowedFilter::callback('entry_date_from', function ($query, $value) {
                    if (filled($value)) {
                        $query->whereDate('entry_date', '>=', $value);
                    }
                }),
                AllowedFilter::callback('entry_date_to', function ($query, $value) {
                    if (filled($value)) {
                        $query->whereDate('entry_date', '<=', $value);
                    }
                }),

                // Numeric ranges
                AllowedFilter::callback('debit_min', function ($query, $value) {
                    if (filled($value)) {
                        $query->where('debit', '>=', $value);
                    }
                }),
                AllowedFilter::callback('debit_max', function ($query, $value) {
                    if (filled($value)) {
                        $query->where('debit', '<=', $value);
                    }
                }),
                AllowedFilter::callback('credit_min', function ($query, $value) {
                    if (filled($value)) {
                        $query->where('credit', '>=', $value);
                    }
                }),
                AllowedFilter::callback('credit_max', function ($query, $value) {
                    if (filled($value)) {
                        $query->where('credit', '<=', $value);
                    }
                }),
                AllowedFilter::callback('fx_rate_min', function ($query, $value) {
                    if (filled($value)) {
                        $query->where('fx_rate_to_base', '>=', $value);
                    }
                }),
                AllowedFilter::callback('fx_rate_max', function ($query, $value) {
                    if (filled($value)) {
                        $query->where('fx_rate_to_base', '<=', $value);
                    }
                }),
            ])
            ->allowedSorts([
                'entry_date',
                'journal_entry_id',
                'line_no',
                'account_code',
                'account_name',
                'reference',
                'debit',
                'credit',
                'status',
            ])
            ->orderBy('entry_date', 'asc')
            ->orderBy('journal_entry_id', 'asc')
            ->orderBy('line_no', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        // Get distinct values for dropdowns - show all accounts from ChartOfAccount
        $accounts = ChartOfAccount::select('id as account_id', 'account_code', 'account_name')
            ->whereNotNull('account_code')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $costCenters = GeneralLedgerEntry::select(
            'cost_center_code as code',
            'cost_center_name as name'
        )
            ->distinct()
            ->whereNotNull('cost_center_code')
            ->orderBy('cost_center_code')
            ->get()
            ->unique('code');

        // Get accounting periods for dropdown
        $accountingPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        return view('reports.general-ledger.index', [
            'entries' => $ledgerEntries,
            'statusOptions' => $statusOptions,
            'accounts' => $accounts,
            'costCenters' => $costCenters,
            'accountingPeriods' => $accountingPeriods,
            'periodId' => $periodId,
            'entryDateFrom' => $entryDateFrom,
            'entryDateTo' => $entryDateTo,
        ]);
    }
}
