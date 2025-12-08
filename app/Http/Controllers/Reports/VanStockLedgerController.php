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

class VanStockLedgerController extends Controller
{
    /**
     * Display van stock ledger - movement history for vehicles
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 100;

        $vehicleId = $request->input('filter.vehicle_id');
        $productId = $request->input('filter.product_id');
        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');
        $movementType = $request->input('filter.movement_type');

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

        $movements = $movementsQuery->orderBy('movement_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
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
        ]);
    }

    /**
     * Display van stock summary - current balances by vehicle
     */
    public function summary(Request $request)
    {
        $vehicleId = $request->input('filter.vehicle_id');

        $stockQuery = VanStockBalance::query()
            ->with(['vehicle', 'product'])
            ->where('quantity_on_hand', '>', 0);

        if ($vehicleId) {
            $stockQuery->where('vehicle_id', $vehicleId);
        }

        $stocks = $stockQuery->orderBy('vehicle_id')
            ->orderBy('product_id')
            ->get()
            ->groupBy('vehicle_id');

        $vehicles = Vehicle::orderBy('vehicle_number')->get();

        $totals = [
            'total_quantity' => VanStockBalance::where('quantity_on_hand', '>', 0)->sum('quantity_on_hand'),
            'total_value' => VanStockBalance::where('quantity_on_hand', '>', 0)
                ->selectRaw('SUM(quantity_on_hand * average_cost) as total')
                ->value('total') ?? 0,
        ];

        return view('reports.van-stock-ledger.summary', [
            'stocks' => $stocks,
            'vehicles' => $vehicles,
            'totals' => $totals,
            'selectedVehicle' => $vehicleId,
        ]);
    }

    /**
     * Display stock movement ledger for a specific vehicle
     */
    public function vehicleLedger(Request $request, Vehicle $vehicle)
    {
        $perPage = $request->input('per_page', 100);
        $perPage = in_array($perPage, [10, 25, 50, 100, 250]) ? $perPage : 100;

        $dateFrom = $request->input('filter.date_from');
        $dateTo = $request->input('filter.date_to');
        $productId = $request->input('filter.product_id');

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

        $movements = $movementsQuery->orderBy('movement_date')
            ->orderBy('id')
            ->paginate($perPage)
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

        return view('reports.van-stock-ledger.vehicle-ledger', [
            'vehicle' => $vehicle,
            'movements' => $movements,
            'currentStock' => $currentStock,
            'products' => $products,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'selectedProduct' => $productId,
        ]);
    }
}
