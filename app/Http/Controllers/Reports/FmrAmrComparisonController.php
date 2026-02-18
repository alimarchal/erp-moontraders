<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class FmrAmrComparisonController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-fmr-amr-comparison'),
        ];
    }

    /**
     * GL Account codes for the report.
     */
    private const FMR_LIQUID_ACCOUNT_CODE = '4210';

    private const FMR_POWDER_ACCOUNT_CODE = '4220';

    private const AMR_POWDER_ACCOUNT_CODE = '5252';

    private const AMR_LIQUID_ACCOUNT_CODE = '5262';

    /**
     * Display the FMR vs AMR Comparison report.
     */
    public function index(Request $request)
    {
        // Default to current year (Jan 1 to Dec 31) if no dates provided
        $startDate = $request->input('filter.start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('filter.end_date', now()->endOfYear()->format('Y-m-d'));

        // Get supplier filter (supports multiple selection)
        $supplierIds = $request->input('filter.supplier_ids', []);
        if (! is_array($supplierIds)) {
            $supplierIds = array_filter([$supplierIds]);
        }
        $supplierIds = array_filter($supplierIds);

        // Get source filter: 'all', 'grn', or 'settlement'
        $sourceFilter = $request->input('filter.source', 'all');

        // Get all active suppliers for the dropdown
        $suppliers = Supplier::query()
            ->where('disabled', false)
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name', 'short_name']);

        // Get account IDs for the GL codes
        $accounts = DB::table('chart_of_accounts')
            ->whereIn('account_code', [
                self::FMR_LIQUID_ACCOUNT_CODE,
                self::FMR_POWDER_ACCOUNT_CODE,
                self::AMR_POWDER_ACCOUNT_CODE,
                self::AMR_LIQUID_ACCOUNT_CODE,
            ])
            ->pluck('id', 'account_code');

        $fmrLiquidAccountId = $accounts[self::FMR_LIQUID_ACCOUNT_CODE] ?? null;
        $fmrPowderAccountId = $accounts[self::FMR_POWDER_ACCOUNT_CODE] ?? null;

        $amrPowderAccountId = $accounts[self::AMR_POWDER_ACCOUNT_CODE] ?? null;
        $amrLiquidAccountId = $accounts[self::AMR_LIQUID_ACCOUNT_CODE] ?? null;

        // Generate all months for the selected date range
        $allMonths = $this->generateAllMonths($startDate, $endDate);

        // Build report data combining GRN and Sales Settlement sources with supplier breakdown
        $actualData = collect();

        if ($fmrLiquidAccountId && $fmrPowderAccountId && $amrPowderAccountId && $amrLiquidAccountId) {
            // Query 1: GRN-based FMR entries (supplier from GRN)
            $grnQuery = DB::table('journal_entry_details as jed')
                ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
                ->join('goods_receipt_notes as grn', function ($join) {
                    $join->on('grn.journal_entry_id', '=', 'je.id')
                        ->whereNull('grn.deleted_at');
                })
                ->join('suppliers as s', 's.id', '=', 'grn.supplier_id')
                ->whereIn('jed.chart_of_account_id', [$fmrLiquidAccountId, $fmrPowderAccountId])
                ->where('je.status', 'posted')
                ->whereNull('je.deleted_at')
                ->whereBetween('je.entry_date', [$startDate, $endDate])
                ->selectRaw("
                    'grn' as source_type,
                    s.id as supplier_id,
                    s.supplier_name,
                    s.short_name,
                    EXTRACT(YEAR FROM je.entry_date) as year,
                    EXTRACT(MONTH FROM je.entry_date) as month,
                    SUM(CASE WHEN jed.chart_of_account_id = {$fmrLiquidAccountId} THEN (jed.credit - jed.debit) ELSE 0 END) as fmr_liquid_total,
                    SUM(CASE WHEN jed.chart_of_account_id = {$fmrPowderAccountId} THEN (jed.credit - jed.debit) ELSE 0 END) as fmr_powder_total,
                    0 as amr_powder_total,
                    0 as amr_liquid_total
                ")
                ->groupBy('s.id', 's.supplier_name', 's.short_name', DB::raw('EXTRACT(YEAR FROM je.entry_date)'), DB::raw('EXTRACT(MONTH FROM je.entry_date)'));

            // Query 2: Sales Settlement AMR Liquid (supplier from product)
            $settlementLiquidQuery = DB::table('sales_settlements as ss')
                ->join('sales_settlement_amr_liquids as sal', 'sal.sales_settlement_id', '=', 'ss.id')
                ->join('products as p', 'p.id', '=', 'sal.product_id')
                ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
                ->where('ss.status', 'posted')
                ->whereNull('ss.deleted_at')
                ->whereBetween('ss.settlement_date', [$startDate, $endDate])
                ->selectRaw("
                    'settlement' as source_type,
                    s.id as supplier_id,
                    s.supplier_name,
                    s.short_name,
                    EXTRACT(YEAR FROM ss.settlement_date) as year,
                    EXTRACT(MONTH FROM ss.settlement_date) as month,
                    0 as fmr_liquid_total,
                    0 as fmr_powder_total,
                    0 as amr_powder_total,
                    SUM(sal.amount) as amr_liquid_total
                ")
                ->groupBy('s.id', 's.supplier_name', 's.short_name', DB::raw('EXTRACT(YEAR FROM ss.settlement_date)'), DB::raw('EXTRACT(MONTH FROM ss.settlement_date)'));

            // Query 3: Sales Settlement AMR Powder (supplier from product)
            $settlementPowderQuery = DB::table('sales_settlements as ss')
                ->join('sales_settlement_amr_powders as sap', 'sap.sales_settlement_id', '=', 'ss.id')
                ->join('products as p', 'p.id', '=', 'sap.product_id')
                ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
                ->where('ss.status', 'posted')
                ->whereNull('ss.deleted_at')
                ->whereBetween('ss.settlement_date', [$startDate, $endDate])
                ->selectRaw("
                    'settlement' as source_type,
                    s.id as supplier_id,
                    s.supplier_name,
                    s.short_name,
                    EXTRACT(YEAR FROM ss.settlement_date) as year,
                    EXTRACT(MONTH FROM ss.settlement_date) as month,
                    0 as fmr_liquid_total,
                    0 as fmr_powder_total,
                    SUM(sap.amount) as amr_powder_total,
                    0 as amr_liquid_total
                ")
                ->groupBy('s.id', 's.supplier_name', 's.short_name', DB::raw('EXTRACT(YEAR FROM ss.settlement_date)'), DB::raw('EXTRACT(MONTH FROM ss.settlement_date)'));

            // Apply supplier filter to each query individually if properly set
            if (! empty($supplierIds)) {
                $grnQuery->whereIn('s.id', $supplierIds);
                $settlementLiquidQuery->whereIn('s.id', $supplierIds);
                $settlementPowderQuery->whereIn('s.id', $supplierIds);
            }

            // Combine based on source filter
            if ($sourceFilter === 'grn') {
                $combinedData = $grnQuery;
            } elseif ($sourceFilter === 'settlement') {
                $combinedData = $settlementLiquidQuery->unionAll($settlementPowderQuery);
            } else {
                $combinedData = $grnQuery->unionAll($settlementLiquidQuery)->unionAll($settlementPowderQuery);
            }

            // Aggregate combined data by supplier, year, month
            $actualData = DB::query()
                ->fromSub($combinedData, 'combined')
                ->select(
                    'supplier_id',
                    'supplier_name',
                    'short_name',
                    'year',
                    'month',
                    DB::raw('SUM(fmr_liquid_total) as fmr_liquid_total'),
                    DB::raw('SUM(fmr_powder_total) as fmr_powder_total'),
                    DB::raw('SUM(amr_powder_total) as amr_powder_total'),
                    DB::raw('SUM(amr_liquid_total) as amr_liquid_total')
                )
                ->groupBy('supplier_id', 'supplier_name', 'short_name', 'year', 'month')
                ->orderBy('supplier_name')
                ->orderBy('year')
                ->orderBy('month')
                ->get();
        }

        // Build report data with supplier info and calculated fields
        $reportData = $actualData->map(function ($row) {
            $fmrLiquid = $row->fmr_liquid_total ?? 0;
            $fmrPowder = $row->fmr_powder_total ?? 0;
            $amrLiquid = $row->amr_liquid_total ?? 0;
            $amrPowder = $row->amr_powder_total ?? 0;

            $monthDate = \Carbon\Carbon::create($row->year, $row->month, 1);

            return (object) [
                'supplier_id' => $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'short_name' => $row->short_name,
                'year' => $row->year,
                'month' => $row->month,
                'month_year' => $monthDate->format('F - Y'),
                'fmr_liquid_total' => $fmrLiquid,
                'fmr_powder_total' => $fmrPowder,
                'fmr_total' => $fmrLiquid + $fmrPowder,
                'amr_liquid_total' => $amrLiquid,
                'amr_powder_total' => $amrPowder,
                'amr_total' => $amrLiquid + $amrPowder,
                'liquid_diff' => $fmrLiquid - $amrLiquid,
                'powder_diff' => $fmrPowder - $amrPowder,
                'difference' => ($fmrLiquid + $fmrPowder) - ($amrLiquid + $amrPowder),
            ];
        });

        // Calculate grand totals
        $grandTotals = (object) [
            'fmr_liquid_total' => $reportData->sum('fmr_liquid_total'),
            'fmr_powder_total' => $reportData->sum('fmr_powder_total'),
            'fmr_total' => $reportData->sum('fmr_total'),
            'amr_liquid_total' => $reportData->sum('amr_liquid_total'),
            'amr_powder_total' => $reportData->sum('amr_powder_total'),
            'amr_total' => $reportData->sum('amr_total'),
            'liquid_diff' => $reportData->sum('liquid_diff'),
            'powder_diff' => $reportData->sum('powder_diff'),
            'difference' => $reportData->sum('difference'),
        ];

        // Get selected supplier names for display
        $selectedSupplierNames = ! empty($supplierIds)
            ? $suppliers->whereIn('id', $supplierIds)->pluck('supplier_name')->implode(', ')
            : 'All Suppliers';

        // Get source label for display
        $sourceLabels = [
            'all' => 'All Sources',
            'grn' => 'Supplier GRN Only',
            'settlement' => 'Sales Settlement Only',
        ];
        $selectedSourceLabel = $sourceLabels[$sourceFilter] ?? 'All Sources';

        return view('reports.fmr-amr-comparison.index', [
            'reportData' => $reportData,
            'grandTotals' => $grandTotals,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'suppliers' => $suppliers,
            'selectedSupplierIds' => $supplierIds,
            'selectedSupplierNames' => $selectedSupplierNames,
            'sourceFilter' => $sourceFilter,
            'selectedSourceLabel' => $selectedSourceLabel,
        ]);
    }

    /**
     * Generate all months between start and end date.
     */
    private function generateAllMonths(string $startDate, string $endDate): \Illuminate\Support\Collection
    {
        $start = \Carbon\Carbon::parse($startDate)->startOfMonth();
        $end = \Carbon\Carbon::parse($endDate)->endOfMonth();

        $months = collect();

        while ($start->lte($end)) {
            $months->push([
                'year' => $start->year,
                'month' => $start->month,
                'month_year' => $start->format('F - Y'),
            ]);
            $start->addMonth();
        }

        return $months;
    }
}
