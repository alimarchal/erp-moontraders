<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StockAvailabilityReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-stock-availability'),
        ];
    }

    public function index(Request $request)
    {
        $asOfDate = $request->input('as_of_date', now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id');
        $warehouseId = $request->input('warehouse_id');
        $categoryId = $request->input('category_id');
        $showZeroStock = $request->boolean('show_zero_stock', false);
        $sortBy = $request->input('sort_by', 'supplier_name');
        $stockSource = $request->input('stock_source', 'all'); // all, warehouse, van

        $suppliers = Supplier::orderBy('supplier_name')->get();
        $warehouses = Warehouse::where('disabled', false)->orderBy('warehouse_name')->get();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        $isCurrentStock = $asOfDate >= now()->format('Y-m-d');

        if ($isCurrentStock) {
            $stockData = $this->getCurrentStock($supplierId, $warehouseId, $categoryId, $stockSource);
        } else {
            $stockData = $this->getHistoricalStock($asOfDate, $supplierId, $warehouseId, $categoryId, $stockSource);
        }

        if (! $showZeroStock) {
            $stockData = $stockData->filter(fn ($row) => $row->total_quantity > 0);
        }

        $stockData = $this->applySorting($stockData, $sortBy);

        $grandTotalQuantity = $stockData->sum('total_quantity');
        $grandTotalAmount = $stockData->sum('total_amount');

        return view('reports.stock-availability.index', compact(
            'asOfDate',
            'supplierId',
            'warehouseId',
            'categoryId',
            'showZeroStock',
            'sortBy',
            'stockSource',
            'suppliers',
            'warehouses',
            'categories',
            'stockData',
            'grandTotalQuantity',
            'grandTotalAmount',
            'isCurrentStock',
        ));
    }

    private function getCurrentStock(?int $supplierId, ?int $warehouseId, ?int $categoryId, string $stockSource)
    {
        $warehouseData = collect();
        $vanData = collect();

        if (in_array($stockSource, ['all', 'warehouse'])) {
            $query = DB::table('current_stock_by_batch as csb')
                ->join('products as p', 'csb.product_id', '=', 'p.id')
                ->join('suppliers as s', 'p.supplier_id', '=', 's.id')
                ->whereNull('p.deleted_at')
                ->whereNull('s.deleted_at')
                ->where('csb.status', 'active')
                ->whereNotNull('csb.warehouse_id')
                ->select(
                    's.id as supplier_id',
                    's.supplier_name',
                    DB::raw('COALESCE(SUM(csb.quantity_on_hand), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(csb.quantity_on_hand * csb.unit_cost), 0) as total_amount')
                )
                ->groupBy('s.id', 's.supplier_name');

            if ($supplierId) {
                $query->where('s.id', $supplierId);
            }
            if ($warehouseId) {
                $query->where('csb.warehouse_id', $warehouseId);
            }
            if ($categoryId) {
                $query->where('p.category_id', $categoryId);
            }

            $warehouseData = collect($query->get());
        }

        if (in_array($stockSource, ['all', 'van'])) {
            $query = DB::table('van_stock_batches as vsb')
                ->join('products as p', 'vsb.product_id', '=', 'p.id')
                ->join('suppliers as s', 'p.supplier_id', '=', 's.id')
                ->whereNull('p.deleted_at')
                ->whereNull('s.deleted_at')
                ->select(
                    's.id as supplier_id',
                    's.supplier_name',
                    DB::raw('COALESCE(SUM(vsb.quantity_on_hand), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(vsb.quantity_on_hand * vsb.unit_cost), 0) as total_amount')
                )
                ->groupBy('s.id', 's.supplier_name');

            if ($supplierId) {
                $query->where('s.id', $supplierId);
            }
            if ($categoryId) {
                $query->where('p.category_id', $categoryId);
            }

            $vanData = collect($query->get());
        }

        return $this->mergeSupplierStock($warehouseData, $vanData);
    }

    private function getHistoricalStock(string $date, ?int $supplierId, ?int $warehouseId, ?int $categoryId, string $stockSource)
    {
        $warehouseData = collect();
        $vanData = collect();

        if (in_array($stockSource, ['all', 'warehouse'])) {
            $snapshotQuery = DB::table('daily_inventory_snapshots as dis')
                ->join('products as p', 'dis.product_id', '=', 'p.id')
                ->join('suppliers as s', 'p.supplier_id', '=', 's.id')
                ->where('dis.date', $date)
                ->whereNotNull('dis.warehouse_id')
                ->whereNull('dis.vehicle_id')
                ->whereNull('p.deleted_at')
                ->whereNull('s.deleted_at')
                ->select(
                    's.id as supplier_id',
                    's.supplier_name',
                    DB::raw('COALESCE(SUM(dis.quantity_on_hand), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(dis.total_value), 0) as total_amount')
                )
                ->groupBy('s.id', 's.supplier_name');

            if ($supplierId) {
                $snapshotQuery->where('s.id', $supplierId);
            }
            if ($warehouseId) {
                $snapshotQuery->where('dis.warehouse_id', $warehouseId);
            }
            if ($categoryId) {
                $snapshotQuery->where('p.category_id', $categoryId);
            }

            $snapshotData = collect($snapshotQuery->get());

            $warehouseData = $snapshotData->isNotEmpty()
                ? $snapshotData
                : $this->getHistoricalWarehouseFromLedger($date, $supplierId, $warehouseId, $categoryId);
        }

        if (in_array($stockSource, ['all', 'van'])) {
            $vanQuery = DB::table('daily_inventory_snapshots as dis')
                ->join('products as p', 'dis.product_id', '=', 'p.id')
                ->join('suppliers as s', 'p.supplier_id', '=', 's.id')
                ->where('dis.date', $date)
                ->whereNotNull('dis.vehicle_id')
                ->whereNull('p.deleted_at')
                ->whereNull('s.deleted_at')
                ->select(
                    's.id as supplier_id',
                    's.supplier_name',
                    DB::raw('COALESCE(SUM(dis.quantity_on_hand), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(dis.total_value), 0) as total_amount')
                )
                ->groupBy('s.id', 's.supplier_name');

            if ($supplierId) {
                $vanQuery->where('s.id', $supplierId);
            }
            if ($categoryId) {
                $vanQuery->where('p.category_id', $categoryId);
            }

            $vanData = collect($vanQuery->get());
        }

        return $this->mergeSupplierStock($warehouseData, $vanData);
    }

    private function getHistoricalWarehouseFromLedger(string $date, ?int $supplierId, ?int $warehouseId, ?int $categoryId)
    {
        $latestIdsQuery = DB::table('stock_ledger_entries as sle')
            ->join('products as p', 'sle.product_id', '=', 'p.id')
            ->whereDate('sle.entry_date', '<=', $date)
            ->whereNull('p.deleted_at')
            ->whereNotNull('sle.warehouse_id');

        if ($supplierId) {
            $latestIdsQuery->where('p.supplier_id', $supplierId);
        }
        if ($warehouseId) {
            $latestIdsQuery->where('sle.warehouse_id', $warehouseId);
        }
        if ($categoryId) {
            $latestIdsQuery->where('p.category_id', $categoryId);
        }

        $latestIdsQuery->selectRaw('MAX(sle.id) as latest_id')
            ->groupBy('sle.product_id', 'sle.warehouse_id', 'sle.stock_batch_id');

        return collect(
            DB::table('stock_ledger_entries as sle')
                ->joinSub($latestIdsQuery, 'latest', fn ($join) => $join->on('sle.id', '=', 'latest.latest_id'))
                ->join('products as p', 'sle.product_id', '=', 'p.id')
                ->join('suppliers as s', 'p.supplier_id', '=', 's.id')
                ->whereNull('s.deleted_at')
                ->select(
                    's.id as supplier_id',
                    's.supplier_name',
                    DB::raw('COALESCE(SUM(sle.quantity_balance), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(sle.stock_value), 0) as total_amount')
                )
                ->groupBy('s.id', 's.supplier_name')
                ->get()
        );
    }

    private function mergeSupplierStock($warehouseData, $vanData)
    {
        $merged = collect();

        $allSupplierIds = $warehouseData->pluck('supplier_id')
            ->merge($vanData->pluck('supplier_id'))
            ->unique();

        foreach ($allSupplierIds as $sid) {
            $wh = $warehouseData->firstWhere('supplier_id', $sid);
            $van = $vanData->firstWhere('supplier_id', $sid);

            $merged->push((object) [
                'supplier_id' => $sid,
                'supplier_name' => $wh?->supplier_name ?? $van?->supplier_name,
                'total_quantity' => (float) ($wh?->total_quantity ?? 0) + (float) ($van?->total_quantity ?? 0),
                'total_amount' => (float) ($wh?->total_amount ?? 0) + (float) ($van?->total_amount ?? 0),
            ]);
        }

        return $merged;
    }

    private function applySorting($data, string $sortBy)
    {
        return match ($sortBy) {
            'quantity_desc' => $data->sortByDesc('total_quantity')->values(),
            'quantity_asc' => $data->sortBy('total_quantity')->values(),
            'amount_desc' => $data->sortByDesc('total_amount')->values(),
            'amount_asc' => $data->sortBy('total_amount')->values(),
            default => $data->sortBy('supplier_name')->values(),
        };
    }
}
