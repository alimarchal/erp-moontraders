<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
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

        $perPage = $request->input('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 25;

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
            ->orderByDesc('entry_date')
            ->orderBy('journal_entry_id')
            ->orderBy('line_no')
            ->paginate($perPage)
            ->withQueryString();

        return view('reports.general-ledger.index', [
            'entries' => $ledgerEntries,
            'statusOptions' => $statusOptions,
        ]);
    }
}
