<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FmrAmrComparisonController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-fmr-amr-comparison'),
        ];
    }

    public function index(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->endOfMonth()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id');
        $sourceFilter = $request->input('source', 'all');

        $suppliers = Supplier::query()
            ->where('disabled', false)
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name', 'short_name']);

        $hasFilters = $request->hasAny(['supplier_id', 'start_date', 'end_date', 'source']);

        if (! $hasFilters) {
            return view('reports.fmr-amr-comparison.index', [
                'reportData' => collect(),
                'grandTotals' => $this->emptyGrandTotals(),
                'startDate' => $startDate,
                'endDate' => $endDate,
                'suppliers' => $suppliers,
                'supplierId' => $supplierId,
                'sourceFilter' => $sourceFilter,
                'selectedSupplierName' => 'All Suppliers',
                'selectedSourceLabel' => 'All Sources',
            ]);
        }

        $selectedSupplierName = $supplierId
            ? ($suppliers->firstWhere('id', $supplierId)?->supplier_name ?? 'Unknown')
            : 'All Suppliers';

        $sourceLabels = [
            'all' => 'All Sources',
            'grn' => 'Supplier GRN Only',
            'settlement' => 'Sales Settlement Only',
        ];
        $selectedSourceLabel = $sourceLabels[$sourceFilter] ?? 'All Sources';

        $grnData = collect();
        $amrData = collect();

        if (in_array($sourceFilter, ['all', 'grn'])) {
            $grnData = $this->getGrnFmrData($startDate, $endDate, $supplierId);
        }

        if (in_array($sourceFilter, ['all', 'settlement'])) {
            $amrData = $this->getAmrData($startDate, $endDate, $supplierId);
        }

        $reportData = $this->buildDailyReport($startDate, $endDate, $grnData, $amrData);

        $grandTotals = (object) [
            'fmr_liquid_total' => $reportData->sum('fmr_liquid_total'),
            'fmr_powder_total' => $reportData->sum('fmr_powder_total'),
            'amr_liquid_total' => $reportData->sum('amr_liquid_total'),
            'amr_powder_total' => $reportData->sum('amr_powder_total'),
            'difference' => $reportData->sum('difference'),
        ];

        return view('reports.fmr-amr-comparison.index', [
            'reportData' => $reportData,
            'grandTotals' => $grandTotals,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'suppliers' => $suppliers,
            'supplierId' => $supplierId,
            'sourceFilter' => $sourceFilter,
            'selectedSupplierName' => $selectedSupplierName,
            'selectedSourceLabel' => $selectedSourceLabel,
        ]);
    }

    /**
     * Get FMR data directly from GRN items (grouped by date + GRN).
     */
    private function getGrnFmrData(string $startDate, string $endDate, ?string $supplierId): Collection
    {
        $query = DB::table('goods_receipt_note_items as grni')
            ->join('goods_receipt_notes as grn', function ($join) {
                $join->on('grn.id', '=', 'grni.grn_id')
                    ->whereNull('grn.deleted_at')
                    ->where('grn.status', 'posted');
            })
            ->join('products as p', 'p.id', '=', 'grni.product_id')
            ->join('suppliers as s', 's.id', '=', 'grn.supplier_id')
            ->whereBetween('grn.receipt_date', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where('grni.fmr_allowance', '>', 0)
                    ->orWhere('grni.fmr_allowance', '<', 0);
            })
            ->select(
                'grn.receipt_date as entry_date',
                's.supplier_name',
                'grn.supplier_invoice_number as invoice_number',
                DB::raw('MIN(grn.id) as grn_id'),
                DB::raw('SUM(CASE WHEN p.is_powder = false THEN grni.fmr_allowance ELSE 0 END) as fmr_liquid_total'),
                DB::raw('SUM(CASE WHEN p.is_powder = true THEN grni.fmr_allowance ELSE 0 END) as fmr_powder_total'),
            )
            ->groupBy('grn.receipt_date', 's.supplier_name', 'grn.supplier_invoice_number')
            ->orderBy('grn.receipt_date')
            ->orderBy('grn.supplier_invoice_number');

        if ($supplierId) {
            $query->where('s.id', $supplierId);
        }

        return $query->get();
    }

    /**
     * Get AMR data from settlement tables (grouped by date).
     */
    private function getAmrData(string $startDate, string $endDate, ?string $supplierId): Collection
    {
        $liquidQuery = DB::table('sales_settlement_amr_liquids as sal')
            ->join('sales_settlements as ss', function ($join) {
                $join->on('ss.id', '=', 'sal.sales_settlement_id')
                    ->whereNull('ss.deleted_at')
                    ->where('ss.status', 'posted');
            })
            ->join('products as p', 'p.id', '=', 'sal.product_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->whereBetween('ss.settlement_date', [$startDate, $endDate])
            ->select(
                'ss.settlement_date as entry_date',
                's.supplier_name',
                DB::raw('0 as amr_powder_total'),
                DB::raw('SUM(sal.amount) as amr_liquid_total'),
            )
            ->groupBy('ss.settlement_date', 's.supplier_name');

        $powderQuery = DB::table('sales_settlement_amr_powders as sap')
            ->join('sales_settlements as ss', function ($join) {
                $join->on('ss.id', '=', 'sap.sales_settlement_id')
                    ->whereNull('ss.deleted_at')
                    ->where('ss.status', 'posted');
            })
            ->join('products as p', 'p.id', '=', 'sap.product_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->whereBetween('ss.settlement_date', [$startDate, $endDate])
            ->select(
                'ss.settlement_date as entry_date',
                's.supplier_name',
                DB::raw('SUM(sap.amount) as amr_powder_total'),
                DB::raw('0 as amr_liquid_total'),
            )
            ->groupBy('ss.settlement_date', 's.supplier_name');

        if ($supplierId) {
            $liquidQuery->where('s.id', $supplierId);
            $powderQuery->where('s.id', $supplierId);
        }

        $combined = $liquidQuery->unionAll($powderQuery);

        return DB::query()
            ->fromSub($combined, 'amr')
            ->select(
                'entry_date',
                'supplier_name',
                DB::raw('SUM(amr_liquid_total) as amr_liquid_total'),
                DB::raw('SUM(amr_powder_total) as amr_powder_total'),
            )
            ->groupBy('entry_date', 'supplier_name')
            ->orderBy('entry_date')
            ->get();
    }

    /**
     * Build daily report rows: all dates in range, with GRN and AMR data filled where available.
     */
    private function buildDailyReport(string $startDate, string $endDate, Collection $grnData, Collection $amrData): Collection
    {
        $allDates = collect(CarbonPeriod::create($startDate, $endDate))->map(fn ($d) => $d->format('Y-m-d'));

        $grnByDate = $grnData->groupBy(fn ($row) => Carbon::parse($row->entry_date)->format('Y-m-d'));
        $amrByDate = $amrData->groupBy(fn ($row) => Carbon::parse($row->entry_date)->format('Y-m-d'));

        $rows = collect();

        foreach ($allDates as $date) {
            $grnRows = $grnByDate->get($date, collect());
            $amrRows = $amrByDate->get($date, collect());

            if ($grnRows->isEmpty() && $amrRows->isEmpty()) {
                $rows->push((object) [
                    'date' => Carbon::parse($date)->format('d-M-Y'),
                    'supplier_name' => '-',
                    'invoice_number' => '-',
                    'grn_id' => null,
                    'fmr_liquid_total' => null,
                    'fmr_powder_total' => null,
                    'amr_liquid_total' => null,
                    'amr_powder_total' => null,
                    'difference' => null,
                    'is_empty' => true,
                ]);

                continue;
            }

            foreach ($grnRows as $grn) {
                $amrLiquid = 0;
                $amrPowder = 0;

                if ($amrRows->isNotEmpty() && $grnRows->first() === $grn) {
                    $amrLiquid = $amrRows->sum('amr_liquid_total');
                    $amrPowder = $amrRows->sum('amr_powder_total');
                }

                $fmrLiquid = (float) $grn->fmr_liquid_total;
                $fmrPowder = (float) $grn->fmr_powder_total;

                $rows->push((object) [
                    'date' => Carbon::parse($date)->format('d-M-Y'),
                    'supplier_name' => $grn->supplier_name,
                    'invoice_number' => $grn->invoice_number ?? '-',
                    'grn_id' => $grn->grn_id ?? null,
                    'fmr_liquid_total' => $fmrLiquid,
                    'fmr_powder_total' => $fmrPowder,
                    'amr_liquid_total' => ($grnRows->first() === $grn) ? $amrLiquid : 0,
                    'amr_powder_total' => ($grnRows->first() === $grn) ? $amrPowder : 0,
                    'difference' => ($fmrLiquid + $fmrPowder) - (($grnRows->first() === $grn) ? ($amrLiquid + $amrPowder) : 0),
                    'is_empty' => false,
                ]);
            }

            if ($grnRows->isEmpty() && $amrRows->isNotEmpty()) {
                $amrLiquid = $amrRows->sum('amr_liquid_total');
                $amrPowder = $amrRows->sum('amr_powder_total');

                $rows->push((object) [
                    'date' => Carbon::parse($date)->format('d-M-Y'),
                    'supplier_name' => $amrRows->first()->supplier_name,
                    'invoice_number' => '-',
                    'grn_id' => null,
                    'fmr_liquid_total' => 0,
                    'fmr_powder_total' => 0,
                    'amr_liquid_total' => $amrLiquid,
                    'amr_powder_total' => $amrPowder,
                    'difference' => 0 - ($amrLiquid + $amrPowder),
                    'is_empty' => false,
                ]);
            }
        }

        return $rows;
    }

    private function emptyGrandTotals(): object
    {
        return (object) [
            'fmr_liquid_total' => 0,
            'fmr_powder_total' => 0,
            'amr_liquid_total' => 0,
            'amr_powder_total' => 0,
            'difference' => 0,
        ];
    }
}
