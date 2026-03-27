<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class SkuFmrAmrController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-sku-fmr-amr'),
        ];
    }

    public function index(Request $request)
    {
        $startDate = $request->input('filter.start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('filter.end_date', now()->endOfYear()->format('Y-m-d'));

        $defaultSupplierId = Supplier::where('supplier_name', 'Nestlé Pakistan')->value('id');
        $supplierId = (int) $request->input('filter.supplier_id', $defaultSupplierId);

        $productIds = $request->input('filter.product_ids', []);
        if (! is_array($productIds)) {
            $productIds = array_filter([$productIds]);
        }
        $productIds = array_filter(array_map('intval', $productIds));

        $typeFilter = $request->input('filter.type', 'all');

        $suppliers = Supplier::where('disabled', false)
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name', 'short_name']);

        // All active products for the selected supplier — used in the dropdown
        $allProducts = DB::table('products')
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('product_code')
            ->get(['id', 'product_code', 'product_name', 'is_powder', 'brand']);

        // Apply type and product filters for the report rows
        $reportProducts = $allProducts;

        if ($typeFilter === 'liquid') {
            $reportProducts = $reportProducts->where('is_powder', false);
        } elseif ($typeFilter === 'powder') {
            $reportProducts = $reportProducts->where('is_powder', true);
        }

        if (! empty($productIds)) {
            $reportProducts = $reportProducts->whereIn('id', $productIds);
        }

        $reportProducts = $reportProducts->values();

        if ($reportProducts->isEmpty()) {
            return view('reports.sku-fmr-amr.index', [
                'reportData' => collect(),
                'grandTotals' => $this->emptyTotals(),
                'startDate' => $startDate,
                'endDate' => $endDate,
                'suppliers' => $suppliers,
                'selectedSupplierId' => $supplierId,
                'selectedProductIds' => $productIds,
                'typeFilter' => $typeFilter,
                'allProducts' => $allProducts,
                'selectedSupplier' => $suppliers->firstWhere('id', $supplierId),
            ]);
        }

        $productIdList = $reportProducts->pluck('id')->toArray();

        // FMR from GRN items (fmr_allowance per product line)
        $fmrData = DB::table('goods_receipt_note_items as grni')
            ->join('goods_receipt_notes as grn', 'grn.id', '=', 'grni.grn_id')
            ->whereIn('grni.product_id', $productIdList)
            ->where('grn.status', 'posted')
            ->whereNull('grn.deleted_at')
            ->whereBetween('grn.receipt_date', [$startDate, $endDate])
            ->selectRaw('grni.product_id, SUM(grni.fmr_allowance) as fmr_amount')
            ->groupBy('grni.product_id')
            ->pluck('fmr_amount', 'product_id');

        // AMR Liquid from sales settlements
        $amrLiquidData = DB::table('sales_settlement_amr_liquids as sal')
            ->join('sales_settlements as ss', 'ss.id', '=', 'sal.sales_settlement_id')
            ->whereIn('sal.product_id', $productIdList)
            ->where('ss.status', 'posted')
            ->whereNull('ss.deleted_at')
            ->whereBetween('ss.settlement_date', [$startDate, $endDate])
            ->selectRaw('sal.product_id, SUM(sal.amount) as amr_liquid_amount')
            ->groupBy('sal.product_id')
            ->pluck('amr_liquid_amount', 'product_id');

        // AMR Powder from sales settlements
        $amrPowderData = DB::table('sales_settlement_amr_powders as sap')
            ->join('sales_settlements as ss', 'ss.id', '=', 'sap.sales_settlement_id')
            ->whereIn('sap.product_id', $productIdList)
            ->where('ss.status', 'posted')
            ->whereNull('ss.deleted_at')
            ->whereBetween('ss.settlement_date', [$startDate, $endDate])
            ->selectRaw('sap.product_id, SUM(sap.amount) as amr_powder_amount')
            ->groupBy('sap.product_id')
            ->pluck('amr_powder_amount', 'product_id');

        // Build report rows — all products shown with 0.00 defaults if no data
        $reportData = $reportProducts->map(function ($product) use ($fmrData, $amrLiquidData, $amrPowderData) {
            $fmr = (float) ($fmrData[$product->id] ?? 0);
            $amrLiquid = (float) ($amrLiquidData[$product->id] ?? 0);
            $amrPowder = (float) ($amrPowderData[$product->id] ?? 0);
            $totalAmr = $amrLiquid + $amrPowder;

            return (object) [
                'product_id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'is_powder' => $product->is_powder,
                'type_label' => $product->is_powder ? 'Powder' : 'Liquid',
                'brand' => $product->brand,
                'fmr_amount' => $fmr,
                'amr_liquid_amount' => $amrLiquid,
                'amr_powder_amount' => $amrPowder,
                'total_amr' => $totalAmr,
                'difference' => $fmr - $totalAmr,
            ];
        });

        $grandTotals = (object) [
            'fmr_amount' => $reportData->sum('fmr_amount'),
            'amr_liquid_amount' => $reportData->sum('amr_liquid_amount'),
            'amr_powder_amount' => $reportData->sum('amr_powder_amount'),
            'total_amr' => $reportData->sum('total_amr'),
            'difference' => $reportData->sum('difference'),
        ];

        return view('reports.sku-fmr-amr.index', [
            'reportData' => $reportData,
            'grandTotals' => $grandTotals,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'suppliers' => $suppliers,
            'selectedSupplierId' => $supplierId,
            'selectedProductIds' => $productIds,
            'typeFilter' => $typeFilter,
            'allProducts' => $allProducts,
            'selectedSupplier' => $suppliers->firstWhere('id', $supplierId),
        ]);
    }

    private function emptyTotals(): object
    {
        return (object) [
            'fmr_amount' => 0.0,
            'amr_liquid_amount' => 0.0,
            'amr_powder_amount' => 0.0,
            'total_amr' => 0.0,
            'difference' => 0.0,
        ];
    }
}
