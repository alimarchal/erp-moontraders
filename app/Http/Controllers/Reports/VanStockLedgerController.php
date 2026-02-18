<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GoodsIssue;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\StockMovement;
use App\Models\VanStockBalance;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VanStockLedgerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-inventory-van-stock-ledger'),
        ];
    }

    /**
     * Display van stock ledger - movement history for vehicles
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [25, 50, 100, 250]) ? $perPage : 100;

        $vehicleId = $request->input('filter.vehicle_id');
        $productId = $request->input('filter.product_id');
        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');
        $movementType = $request->input('filter.movement_type');
        $sort = $request->input('sort', 'date_asc');

        // Get movements for van stock via GoodsIssue and SalesSettlement references
        $goodsIssueIds = GoodsIssue::query()
            ->whereNotNull('vehicle_id')
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->pluck('id');

        $settlementIds = SalesSettlement::query()
            ->whereNotNull('vehicle_id')
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->pluck('id');

        $movementsQuery = StockMovement::query()
            ->with(['product', 'stockBatch', 'vehicle'])
            ->where(function ($q) use ($goodsIssueIds, $settlementIds) {
                $q->where(function ($q2) use ($goodsIssueIds) {
                    $q2->where('reference_type', 'App\\Models\\GoodsIssue')
                        ->whereIn('reference_id', $goodsIssueIds);
                })->orWhere(function ($q2) use ($settlementIds) {
                    $q2->where('reference_type', 'App\\Models\\SalesSettlement')
                        ->whereIn('reference_id', $settlementIds);
                });
            });

        if ($productId) {
            $movementsQuery->where('product_id', $productId);
        }

        if ($dateFrom) {
            $movementsQuery->whereDate('movement_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $movementsQuery->whereDate('movement_date', '<=', $dateTo);
        }

        if ($movementType) {
            $movementsQuery->where('movement_type', $movementType);
        }

        // Calculate totals before sorting/pagination (using a separate query without order by)
        $totalsQuery = StockMovement::query()
            ->where(function ($q) use ($goodsIssueIds, $settlementIds) {
                $q->where(function ($q2) use ($goodsIssueIds) {
                    $q2->where('reference_type', 'App\\Models\\GoodsIssue')
                        ->whereIn('reference_id', $goodsIssueIds);
                })->orWhere(function ($q2) use ($settlementIds) {
                    $q2->where('reference_type', 'App\\Models\\SalesSettlement')
                        ->whereIn('reference_id', $settlementIds);
                });
            });

        if ($productId) {
            $totalsQuery->where('product_id', $productId);
        }
        if ($dateFrom) {
            $totalsQuery->whereDate('movement_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $totalsQuery->whereDate('movement_date', '<=', $dateTo);
        }
        if ($movementType) {
            $totalsQuery->where('movement_type', $movementType);
        }

        $totalsData = $totalsQuery->selectRaw('
            SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as total_inward,
            SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as total_outward,
            SUM(ABS(quantity * unit_cost)) as total_value
        ')->first();

        // Apply sorting
        switch ($sort) {
            case 'date_desc':
                $movementsQuery->orderBy('movement_date', 'desc')->orderBy('id', 'desc');
                break;
            case 'product':
                $movementsQuery->join('products', 'stock_movements.product_id', '=', 'products.id')
                    ->orderBy('products.product_name')
                    ->select('stock_movements.*');
                break;
            case 'vehicle':
                $movementsQuery->leftJoin('goods_issues', function ($join) {
                    $join->on('stock_movements.reference_id', '=', 'goods_issues.id')
                        ->where('stock_movements.reference_type', '=', 'App\\Models\\GoodsIssue');
                })->leftJoin('sales_settlements', function ($join) {
                    $join->on('stock_movements.reference_id', '=', 'sales_settlements.id')
                        ->where('stock_movements.reference_type', '=', 'App\\Models\\SalesSettlement');
                })->leftJoin('vehicles', function ($join) {
                    $join->on('goods_issues.vehicle_id', '=', 'vehicles.id')
                        ->orOn('sales_settlements.vehicle_id', '=', 'vehicles.id');
                })->orderBy('vehicles.vehicle_number')
                    ->select('stock_movements.*');
                break;
            case '-quantity':
                $movementsQuery->orderBy('quantity', 'desc');
                break;
            case 'quantity':
                $movementsQuery->orderBy('quantity', 'asc');
                break;
            default: // date_asc
                $movementsQuery->orderBy('movement_date')->orderBy('id');
        }

        $totals = [
            'total_inward' => $totalsData->total_inward ?? 0,
            'total_outward' => $totalsData->total_outward ?? 0,
            'total_value' => $totalsData->total_value ?? 0,
        ];

        $movements = $movementsQuery->paginate($perPage)
            ->withQueryString();

        $vehicles = Vehicle::orderBy('vehicle_number')->get();
        $products = Product::orderBy('product_name')->get();
        $movementTypes = StockMovement::distinct('movement_type')->pluck('movement_type');

        return view('reports.van-stock-ledger.index', [
            'movements' => $movements,
            'vehicles' => $vehicles,
            'products' => $products,
            'movementTypes' => $movementTypes,
            'selectedVehicle' => $vehicleId,
            'selectedProduct' => $productId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totals' => $totals,
        ]);
    }

    /**
     * Display van stock summary - current balances by vehicle
     */
    public function summary(Request $request)
    {
        $vehicleId = $request->input('filter.vehicle_id');
        $productId = $request->input('filter.product_id');

        $stockQuery = VanStockBalance::query()
            ->with(['vehicle', 'product'])
            ->where('quantity_on_hand', '>', 0);

        if ($vehicleId) {
            $stockQuery->where('vehicle_id', $vehicleId);
        }

        if ($productId) {
            $stockQuery->where('product_id', $productId);
        }

        $stocks = $stockQuery->orderBy('vehicle_id')
            ->orderBy('product_id')
            ->get()
            ->groupBy('vehicle_id');

        $vehicles = Vehicle::orderBy('vehicle_number')->get();
        $products = Product::orderBy('product_name')->get();

        // Calculate totals based on current filters
        $totalsQuery = VanStockBalance::query()->where('quantity_on_hand', '>', 0);
        if ($vehicleId) {
            $totalsQuery->where('vehicle_id', $vehicleId);
        }
        if ($productId) {
            $totalsQuery->where('product_id', $productId);
        }

        $totals = [
            'total_quantity' => $totalsQuery->sum('quantity_on_hand'),
            'total_value' => $totalsQuery->selectRaw('SUM(quantity_on_hand * average_cost) as total')->value('total') ?? 0,
            'total_vehicles' => $stocks->count(),
        ];

        return view('reports.van-stock-ledger.summary', [
            'stocks' => $stocks,
            'vehicles' => $vehicles,
            'products' => $products,
            'totals' => $totals,
            'selectedVehicle' => $vehicleId,
            'selectedProduct' => $productId,
        ]);
    }

    /**
     * Display stock movement ledger for a specific vehicle
     */
    public function vehicleLedger(Request $request, Vehicle $vehicle)
    {
        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [25, 50, 100, 250]) ? $perPage : 100;

        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');
        $productId = $request->input('filter.product_id');
        $movementType = $request->input('filter.movement_type');
        $sort = $request->input('sort', 'date_asc');

        // Get goods issue and settlement IDs for this vehicle
        $goodsIssueIds = GoodsIssue::where('vehicle_id', $vehicle->id)->pluck('id');
        $settlementIds = SalesSettlement::where('vehicle_id', $vehicle->id)->pluck('id');

        $movementsQuery = StockMovement::query()
            ->with(['product', 'stockBatch'])
            ->where(function ($q) use ($goodsIssueIds, $settlementIds) {
                $q->where(function ($q2) use ($goodsIssueIds) {
                    $q2->where('reference_type', 'App\\Models\\GoodsIssue')
                        ->whereIn('reference_id', $goodsIssueIds);
                })->orWhere(function ($q2) use ($settlementIds) {
                    $q2->where('reference_type', 'App\\Models\\SalesSettlement')
                        ->whereIn('reference_id', $settlementIds);
                });
            });

        if ($dateFrom) {
            $movementsQuery->whereDate('movement_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $movementsQuery->whereDate('movement_date', '<=', $dateTo);
        }

        if ($productId) {
            $movementsQuery->where('product_id', $productId);
        }

        if ($movementType) {
            $movementsQuery->where('movement_type', $movementType);
        }

        // Calculate totals before sorting/pagination (using a separate query without order by)
        $totalsQuery = StockMovement::query()
            ->where(function ($q) use ($goodsIssueIds, $settlementIds) {
                $q->where(function ($q2) use ($goodsIssueIds) {
                    $q2->where('reference_type', 'App\\Models\\GoodsIssue')
                        ->whereIn('reference_id', $goodsIssueIds);
                })->orWhere(function ($q2) use ($settlementIds) {
                    $q2->where('reference_type', 'App\\Models\\SalesSettlement')
                        ->whereIn('reference_id', $settlementIds);
                });
            });

        if ($dateFrom) {
            $totalsQuery->whereDate('movement_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $totalsQuery->whereDate('movement_date', '<=', $dateTo);
        }
        if ($productId) {
            $totalsQuery->where('product_id', $productId);
        }
        if ($movementType) {
            $totalsQuery->where('movement_type', $movementType);
        }

        $totalsData = $totalsQuery->selectRaw('
            SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as total_inward,
            SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as total_outward,
            SUM(ABS(quantity * unit_cost)) as total_value
        ')->first();

        $totals = [
            'total_inward' => $totalsData->total_inward ?? 0,
            'total_outward' => $totalsData->total_outward ?? 0,
            'total_value' => $totalsData->total_value ?? 0,
        ];

        // Apply sorting
        switch ($sort) {
            case 'date_desc':
                $movementsQuery->orderBy('movement_date', 'desc')->orderBy('id', 'desc');
                break;
            case 'product':
                $movementsQuery->join('products', 'stock_movements.product_id', '=', 'products.id')
                    ->orderBy('products.product_name')
                    ->select('stock_movements.*');
                break;
            case '-quantity':
                $movementsQuery->orderBy('quantity', 'desc');
                break;
            case 'quantity':
                $movementsQuery->orderBy('quantity', 'asc');
                break;
            default: // date_asc
                $movementsQuery->orderBy('movement_date')->orderBy('id');
        }

        $movements = $movementsQuery->paginate($perPage)
            ->withQueryString();

        // Calculate running balance
        $runningBalance = 0;
        foreach ($movements as $movement) {
            $runningBalance += $movement->quantity;
            $movement->running_balance = $runningBalance;
        }

        // Current van stock
        $currentStock = VanStockBalance::where('vehicle_id', $vehicle->id)
            ->with('product')
            ->get();

        $products = Product::orderBy('product_name')->get();
        $movementTypes = StockMovement::distinct('movement_type')->pluck('movement_type');

        return view('reports.van-stock-ledger.vehicle-ledger', [
            'vehicle' => $vehicle,
            'movements' => $movements,
            'currentStock' => $currentStock,
            'products' => $products,
            'movementTypes' => $movementTypes,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'selectedProduct' => $productId,
            'selectedMovementType' => $movementType,
            'totals' => $totals,
        ]);
    }
}
