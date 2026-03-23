<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceSummaryRequest;
use App\Http\Requests\UpdateInvoiceSummaryRequest;
use App\Models\InvoiceSummary;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceSummaryReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-invoice-summary', only: ['index']),
            new Middleware('can:report-audit-invoice-summary-manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = \in_array($perPage, [10, 25, 50, 100, 250, 'all']) ? $perPage : 50;

        // Default supplier: Nestlé
        $defaultSupplier = Supplier::where('short_name', 'Nestle')->first();
        $supplierId = $request->input('filter.supplier_id', $defaultSupplier?->id);

        // Date range defaults: current month
        $dateFrom = $request->input('filter.date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('filter.date_to', now()->endOfMonth()->toDateString());

        $query = InvoiceSummary::query()
            ->with('supplier')
            ->when($supplierId, fn ($q) => $q->forSupplier($supplierId))
            ->dateRange($dateFrom, $dateTo);

        if ($request->filled('filter.invoice_number')) {
            $query->where('invoice_number', 'like', '%'.$request->input('filter.invoice_number').'%');
        }

        // Column totals (for all filtered entries, before ordering)
        $totals = (clone $query)->selectRaw('
            COALESCE(SUM(cartons), 0) as total_cartons,
            COALESCE(SUM(invoice_value), 0) as total_invoice_value,
            COALESCE(SUM(za_on_invoices), 0) as total_za,
            COALESCE(SUM(discount_value), 0) as total_discount,
            COALESCE(SUM(fmr_allowance), 0) as total_fmr,
            COALESCE(SUM(discount_before_sales_tax), 0) as total_disc_before_st,
            COALESCE(SUM(excise_duty), 0) as total_excise,
            COALESCE(SUM(sales_tax_value), 0) as total_sales_tax,
            COALESCE(SUM(advance_tax), 0) as total_advance_tax,
            COALESCE(SUM(total_value_with_tax), 0) as total_with_tax,
            COUNT(*) as total_entries
        ')->first();

        $sortDirection = $request->input('sort', 'asc');
        $sortDirection = \in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'asc';

        $query->orderBy('invoice_date', $sortDirection)->orderBy('id', $sortDirection);

        if ($perPage === 'all') {
            $allEntries = $query->get();
            $entries = new \Illuminate\Pagination\LengthAwarePaginator(
                $allEntries,
                $allEntries->count(),
                $allEntries->count() ?: 1,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $entries = $query->paginate((int) $perPage)->withQueryString();
        }

        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get();
        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;

        return view('reports.invoice-summary.index', [
            'entries' => $entries,
            'totals' => $totals,
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function store(StoreInvoiceSummaryRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                InvoiceSummary::create($request->validated());
            });

            return redirect()->back()->with('success', 'Invoice summary entry added successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('InvoiceSummary store error: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Failed to add invoice summary. Please try again.');
        }
    }

    public function update(UpdateInvoiceSummaryRequest $request, InvoiceSummary $invoiceSummary)
    {
        try {
            DB::transaction(function () use ($request, $invoiceSummary) {
                $invoiceSummary->update($request->validated());
            });

            return redirect()->back()->with('success', 'Invoice summary entry updated successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('InvoiceSummary update error: '.$e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Failed to update invoice summary. Please try again.');
        }
    }

    public function destroy(InvoiceSummary $invoiceSummary)
    {
        try {
            $invoiceSummary->delete();

            return redirect()->back()->with('success', 'Invoice summary entry deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('InvoiceSummary destroy error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete invoice summary. Please try again.');
        }
    }
}
