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

        $ledgerEntries = QueryBuilder::for(GeneralLedgerEntry::query())
            ->allowedFilters([
                AllowedFilter::partial('account_code'),
                AllowedFilter::partial('account_name'),
                AllowedFilter::partial('reference'),
                AllowedFilter::partial('journal_description'),
                AllowedFilter::partial('line_description'),
                AllowedFilter::partial('cost_center_code'),
                AllowedFilter::partial('currency_code'),
                AllowedFilter::exact('status'),
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
            ])
            ->orderByDesc('entry_date')
            ->orderBy('journal_entry_id')
            ->orderBy('line_no')
            ->paginate(25)
            ->withQueryString();

        return view('reports.general-ledger.index', [
            'entries' => $ledgerEntries,
            'statusOptions' => $statusOptions,
        ]);
    }
}
