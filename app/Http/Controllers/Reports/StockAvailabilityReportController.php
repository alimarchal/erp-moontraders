<?php

namespace App\Http\Controllers\Reports;

use App\Helpers\ReportPdf;
use App\Helpers\SvgChart;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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
        $data = $this->reportData($request);

        return view('reports.stock-availability.index', $data + [
            'tiles' => $this->tiles($data),
            'charts' => $this->charts($data),
        ]);
    }

    /**
     * A4 PDF with the figure tiles, charts and the supplier table; portrait or landscape (?orientation=).
     */
    public function exportPdf(Request $request): Response
    {
        $data = $this->reportData($request);
        abort_if($data['stockData']->isEmpty(), 422, 'No stock data found for the selected filters.');

        $orientation = ReportPdf::orientation($request->query('orientation'));
        $source = match ($data['stockSource']) {
            'warehouse' => 'Warehouse only',
            'van' => 'Van only',
            default => 'Warehouse + Van',
        };

        $pdf = ReportPdf::make('reports.stock-availability.pdf', $data + [
            'title' => 'Stock Availability Report',
            'subtitle' => [
                'As of '.Carbon::parse($data['asOfDate'])->format('d M Y').($data['isCurrentStock'] ? ' (current)' : ' (historical)'),
                'Source: '.$source.($data['supplierId'] ? ' | Supplier: '.$data['suppliers']->firstWhere('id', (int) $data['supplierId'])?->supplier_name : ''),
            ],
            'meta' => [
                'Suppliers' => $data['stockData']->count(),
                'Printed by' => $request->user()->name,
                'On' => now()->format('d M Y, h:i A'),
            ],
            'tiles' => $this->tiles($data),
            'charts' => $this->charts($data),
        ], $orientation, 'Moon Traders ERP  |  Stock Availability Report  |  As of '.Carbon::parse($data['asOfDate'])->format('d M Y'));

        return $pdf->download('stock-availability-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{label: string, value: string, note: string, color: string}>
     */
    private function tiles(array $data): array
    {
        if ($data['stockData']->isEmpty()) {
            return [];
        }

        $withStock = $data['stockData']->filter(fn ($row) => $row->total_quantity > 0)->count();

        return [
            ['label' => 'Stock value', 'value' => 'Rs '.SvgChart::money($data['grandTotalAmount']), 'note' => number_format($data['grandTotalAmount']), 'color' => '#2563eb'],
            ['label' => 'Stock quantity', 'value' => SvgChart::money($data['grandTotalQuantity']), 'note' => number_format($data['grandTotalQuantity'], 2).' units', 'color' => '#16a34a'],
            ['label' => 'Salesman credit', 'value' => 'Rs '.SvgChart::money($data['grandTotalSalesmanCreditBalance']), 'note' => 'owed by customers', 'color' => '#f97316'],
            ['label' => 'Stock + credit', 'value' => 'Rs '.SvgChart::money($data['grandTotalAmount'] + $data['grandTotalSalesmanCreditBalance']), 'note' => 'tied up in the market', 'color' => '#7c3aed'],
            ['label' => 'Suppliers', 'value' => (string) $data['stockData']->count(), 'note' => $withStock.' with stock', 'color' => '#0891b2'],
        ];
    }

    /**
     * Three charts: share of stock value, biggest stock by supplier, stock against credit.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function charts(array $data): array
    {
        $rows = $data['stockData'];

        if ($rows->isEmpty() || $data['grandTotalAmount'] <= 0 && $data['grandTotalSalesmanCreditBalance'] <= 0) {
            return [];
        }

        $byAmount = $rows->sortByDesc('total_amount')->values();
        $head = $byAmount->take(5);
        $others = (float) $byAmount->skip(5)->sum('total_amount');
        $shareLabels = $head->pluck('supplier_name')->all();
        $shareValues = $head->pluck('total_amount')->map(fn ($v) => max(0.0, (float) $v))->all();
        if ($others > 0) {
            $shareLabels[] = 'Others';
            $shareValues[] = $others;
        }
        $short = fn ($name) => explode(' ', (string) $name)[0];

        return [
            [
                'title' => 'Share of stock value',
                'subtitle' => 'Which supplier holds the stock',
                'accent' => '#2563eb',
                'svg' => fn (int $w) => SvgChart::donut($shareLabels, $shareValues, SvgChart::PALETTE, $w, 200, SvgChart::money($data['grandTotalAmount']), 'stock value'),
            ],
            [
                'title' => 'Stock value by supplier',
                'subtitle' => 'Top 8',
                'accent' => '#16a34a',
                'svg' => fn (int $w) => SvgChart::bars(
                    $byAmount->take(8)->pluck('supplier_name')->all(),
                    $byAmount->take(8)->pluck('total_amount')->all(),
                    SvgChart::PALETTE, $w, 200, null, 18
                ),
            ],
            [
                'title' => 'Stock and salesman credit',
                'subtitle' => 'Money tied up per supplier',
                'accent' => '#f97316',
                'wide' => true,
                'svg' => fn (int $w) => SvgChart::columns(
                    $byAmount->take($w < 500 ? 4 : 6)->pluck('supplier_name')->map($short)->all(),
                    [
                        ['name' => 'Stock', 'color' => '#2563eb', 'values' => $byAmount->take($w < 500 ? 4 : 6)->pluck('total_amount')->all()],
                        ['name' => 'Salesman credit', 'color' => '#f97316', 'values' => $byAmount->take($w < 500 ? 4 : 6)->pluck('credit_balance_salesman')->all()],
                    ],
                    $w, 200
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(Request $request): array
    {
        $asOfDate = $request->input('as_of_date', now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id');
        $warehouseId = $request->input('warehouse_id');
        $categoryId = $request->input('category_id');
        $showZeroStock = $request->boolean('show_zero_stock', true);
        $sortBy = $request->input('sort_by', 'supplier_name');
        $stockSource = $request->input('stock_source', 'all'); // all, warehouse, van

        $suppliers = Supplier::orderBy('supplier_name')->get();
        $warehouses = Warehouse::where('disabled', false)->orderBy('warehouse_name')->get();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        // All active suppliers (filtered if specific one selected)
        $allSuppliers = Supplier::whereNull('deleted_at')
            ->when($supplierId, fn ($q) => $q->where('id', $supplierId))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);

        $isCurrentStock = $asOfDate >= now()->format('Y-m-d');

        if ($isCurrentStock) {
            $stockData = $this->getCurrentStock($allSuppliers, $warehouseId, $categoryId, $stockSource);
        } else {
            $stockData = $this->getHistoricalStock($asOfDate, $allSuppliers, $warehouseId, $categoryId, $stockSource);
        }

        $salesmanCreditBalances = $this->getSalesmanCreditBalances($allSuppliers, $asOfDate);

        $stockData = $stockData->map(function ($row) use ($salesmanCreditBalances) {
            $row->credit_balance_salesman = (float) ($salesmanCreditBalances->get($row->supplier_id)?->credit_balance_salesman ?? 0);

            return $row;
        });

        if (! $showZeroStock) {
            $stockData = $stockData->filter(fn ($row) => $row->total_quantity > 0);
        }

        $stockData = $this->applySorting($stockData, $sortBy);

        $grandTotalQuantity = $stockData->sum('total_quantity');
        $grandTotalAmount = $stockData->sum('total_amount');
        $grandTotalSalesmanCreditBalance = $stockData->sum('credit_balance_salesman');

        return compact(
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
            'grandTotalSalesmanCreditBalance',
            'isCurrentStock',
        );
    }

    private function getCurrentStock($allSuppliers, ?int $warehouseId, ?int $categoryId, string $stockSource)
    {
        $supplierIds = $allSuppliers->pluck('id')->toArray();

        $whTotals = collect();
        $vanTotals = collect();

        if (in_array($stockSource, ['all', 'warehouse'])) {
            $query = DB::table('current_stock_by_batch as csb')
                ->join('products as p', 'csb.product_id', '=', 'p.id')
                ->whereNull('p.deleted_at')
                ->where('csb.status', 'active')
                ->whereNotNull('csb.warehouse_id')
                ->whereIn('p.supplier_id', $supplierIds)
                ->select(
                    'p.supplier_id',
                    DB::raw('COALESCE(SUM(csb.quantity_on_hand), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(csb.total_value), 0) as total_amount')
                )
                ->groupBy('p.supplier_id');

            if ($warehouseId) {
                $query->where('csb.warehouse_id', $warehouseId);
            }
            if ($categoryId) {
                $query->where('p.category_id', $categoryId);
            }

            $whTotals = collect($query->get())->keyBy('supplier_id');
        }

        if (in_array($stockSource, ['all', 'van'])) {
            $query = DB::table('van_stock_batches as vsb')
                ->join('products as p', 'vsb.product_id', '=', 'p.id')
                ->whereNull('p.deleted_at')
                ->whereIn('p.supplier_id', $supplierIds)
                ->select(
                    'p.supplier_id',
                    DB::raw('COALESCE(SUM(vsb.quantity_on_hand), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(vsb.quantity_on_hand * vsb.unit_cost), 0) as total_amount')
                )
                ->groupBy('p.supplier_id');

            if ($categoryId) {
                $query->where('p.category_id', $categoryId);
            }

            $vanTotals = collect($query->get())->keyBy('supplier_id');
        }

        return $allSuppliers->map(function ($supplier) use ($whTotals, $vanTotals) {
            $wh = $whTotals->get($supplier->id);
            $van = $vanTotals->get($supplier->id);

            return (object) [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'total_quantity' => (float) ($wh?->total_quantity ?? 0) + (float) ($van?->total_quantity ?? 0),
                'total_amount' => (float) ($wh?->total_amount ?? 0) + (float) ($van?->total_amount ?? 0),
            ];
        });
    }

    private function getHistoricalStock(string $date, $allSuppliers, ?int $warehouseId, ?int $categoryId, string $stockSource)
    {
        $supplierIds = $allSuppliers->pluck('id')->toArray();

        $whTotals = collect();
        $vanTotals = collect();

        if (in_array($stockSource, ['all', 'warehouse'])) {
            $snapshotQuery = DB::table('daily_inventory_snapshots as dis')
                ->join('products as p', 'dis.product_id', '=', 'p.id')
                ->where('dis.date', $date)
                ->whereNotNull('dis.warehouse_id')
                ->whereNull('dis.vehicle_id')
                ->whereNull('p.deleted_at')
                ->whereIn('p.supplier_id', $supplierIds)
                ->select(
                    'p.supplier_id',
                    DB::raw('COALESCE(SUM(dis.quantity_on_hand), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(dis.total_value), 0) as total_amount')
                )
                ->groupBy('p.supplier_id');

            if ($warehouseId) {
                $snapshotQuery->where('dis.warehouse_id', $warehouseId);
            }
            if ($categoryId) {
                $snapshotQuery->where('p.category_id', $categoryId);
            }

            $snapshotData = collect($snapshotQuery->get())->keyBy('supplier_id');

            $whTotals = $snapshotData->isNotEmpty()
                ? $snapshotData
                : $this->getHistoricalWarehouseFromLedger($date, $supplierIds, $warehouseId, $categoryId)->keyBy('supplier_id');
        }

        if (in_array($stockSource, ['all', 'van'])) {
            $vanQuery = DB::table('daily_inventory_snapshots as dis')
                ->join('products as p', 'dis.product_id', '=', 'p.id')
                ->where('dis.date', $date)
                ->whereNotNull('dis.vehicle_id')
                ->whereNull('p.deleted_at')
                ->whereIn('p.supplier_id', $supplierIds)
                ->select(
                    'p.supplier_id',
                    DB::raw('COALESCE(SUM(dis.quantity_on_hand), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(dis.total_value), 0) as total_amount')
                )
                ->groupBy('p.supplier_id');

            if ($categoryId) {
                $vanQuery->where('p.category_id', $categoryId);
            }

            $vanTotals = collect($vanQuery->get())->keyBy('supplier_id');
        }

        return $allSuppliers->map(function ($supplier) use ($whTotals, $vanTotals) {
            $wh = $whTotals->get($supplier->id);
            $van = $vanTotals->get($supplier->id);

            return (object) [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'total_quantity' => (float) ($wh?->total_quantity ?? 0) + (float) ($van?->total_quantity ?? 0),
                'total_amount' => (float) ($wh?->total_amount ?? 0) + (float) ($van?->total_amount ?? 0),
            ];
        });
    }

    private function getHistoricalWarehouseFromLedger(string $date, array $supplierIds, ?int $warehouseId, ?int $categoryId)
    {
        $latestIdsQuery = DB::table('stock_ledger_entries as sle')
            ->join('products as p', 'sle.product_id', '=', 'p.id')
            ->whereDate('sle.entry_date', '<=', $date)
            ->whereNull('p.deleted_at')
            ->whereNotNull('sle.warehouse_id')
            ->whereIn('p.supplier_id', $supplierIds);

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
                ->select(
                    'p.supplier_id',
                    DB::raw('COALESCE(SUM(sle.quantity_balance), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(sle.stock_value), 0) as total_amount')
                )
                ->groupBy('p.supplier_id')
                ->get()
        );
    }

    private function getSalesmanCreditBalances($allSuppliers, string $asOfDate)
    {
        $supplierIds = $allSuppliers->pluck('id')->toArray();

        if (empty($supplierIds)) {
            return collect();
        }

        return DB::table('employees as e')
            ->join('customer_employee_accounts as cea', 'cea.employee_id', '=', 'e.id')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->whereIn('e.supplier_id', $supplierIds)
            ->where('ceat.transaction_date', '<=', $asOfDate)
            ->selectRaw('e.supplier_id, COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as credit_balance_salesman')
            ->groupBy('e.supplier_id')
            ->get()
            ->keyBy('supplier_id');
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
