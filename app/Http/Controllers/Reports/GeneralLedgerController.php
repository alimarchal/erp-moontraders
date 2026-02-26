<?php

namespace App\Http\Controllers\Reports;

use App\Exports\GeneralLedgerExport;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GeneralLedgerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-financial-general-ledger'),
        ];
    }

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

        $periodId = $request->input('accounting_period_id');
        $entryDateFrom = $request->input('filter.entry_date_from');
        $entryDateTo = $request->input('filter.entry_date_to');

        $this->applyAccountingPeriod($request, $periodId, $entryDateFrom, $entryDateTo);

        $ledgerEntries = $this->buildFilteredQuery()
            ->paginate($perPage)
            ->withQueryString();

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

    /**
     * Export filtered general ledger entries to Excel.
     */
    public function exportExcel(Request $request)
    {
        $periodId = $request->input('accounting_period_id');
        $entryDateFrom = $request->input('filter.entry_date_from');
        $entryDateTo = $request->input('filter.entry_date_to');

        $this->applyAccountingPeriod($request, $periodId, $entryDateFrom, $entryDateTo);

        $query = $this->buildFilteredQuery()->getEloquentBuilder();

        return Excel::download(new GeneralLedgerExport($query), 'general-ledger.xlsx');
    }

    /**
     * Apply accounting period date overrides to the request.
     */
    private function applyAccountingPeriod(Request $request, ?string $periodId, ?string &$entryDateFrom, ?string &$entryDateTo): void
    {
        if ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $entryDateFrom = $period->start_date;
                $entryDateTo = $period->end_date;
                $request->merge([
                    'filter' => array_merge($request->input('filter', []), [
                        'entry_date_from' => $entryDateFrom,
                        'entry_date_to' => $entryDateTo,
                    ]),
                ]);
            }
        }
    }

    /**
     * Build the filtered query shared by index and export.
     */
    private function buildFilteredQuery(): QueryBuilder
    {
        return QueryBuilder::for(GeneralLedgerEntry::query())
            ->allowedFilters([
                AllowedFilter::partial('account_code'),
                AllowedFilter::partial('account_name'),
                AllowedFilter::partial('reference'),
                AllowedFilter::partial('journal_description'),
                AllowedFilter::partial('line_description'),
                AllowedFilter::partial('cost_center_code'),
                AllowedFilter::partial('cost_center_name'),
                AllowedFilter::partial('currency_code'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('journal_entry_id'),
                AllowedFilter::exact('line_no'),
                AllowedFilter::exact('account_id'),
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
            ->orderBy('line_no', 'asc');
    }
}
