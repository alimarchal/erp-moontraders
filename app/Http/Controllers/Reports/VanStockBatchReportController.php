<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class VanStockBatchReportController extends Controller
{
    public function index(Request $request)
    {
        $vehicleId = $request->input('filter.vehicle_id');
        $productId = $request->input('filter.product_id');

        $query = StockMovement::query()
            ->select(
                'vehicle_id',
                'product_id',
                'stock_batch_id',
                DB::raw("SUM(
                    CASE 
                        WHEN movement_type = 'transfer' AND reference_type = 'App\\\\Models\\\\GoodsIssue' THEN -quantity 
                        WHEN movement_type = 'sale' THEN quantity 
                        WHEN movement_type = 'return' THEN -quantity 
                        WHEN movement_type = 'shortage' THEN quantity 
                        ELSE 0 
                    END
                ) as quantity_on_hand"),
                DB::raw("MAX(unit_cost) as last_unit_cost")
            )
            ->whereNotNull('vehicle_id')
            ->groupBy('vehicle_id', 'product_id', 'stock_batch_id')
            ->having('quantity_on_hand', '>', 0);

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $stocks = $query->with(['vehicle', 'product', 'stockBatch'])
            ->get()
            ->groupBy('vehicle_id');

        $totals = [
            'total_quantity' => 0,
            'total_value' => 0,
        ];

        foreach ($stocks as $vehicleStocks) {
            foreach ($vehicleStocks as $stock) {
                $totals['total_quantity'] += $stock->quantity_on_hand;
                $totals['total_value'] += $stock->quantity_on_hand * $stock->last_unit_cost;
            }
        }

        $vehicles = Vehicle::orderBy('vehicle_number')->get();
        $products = Product::orderBy('product_name')->get();

        return view('reports.van-stock-batch.index', [
            'stocks' => $stocks,
            'vehicles' => $vehicles,
            'products' => $products,
            'totals' => $totals,
            'selectedVehicle' => $vehicleId,
            'selectedProduct' => $productId,
        ]);
    }
}
