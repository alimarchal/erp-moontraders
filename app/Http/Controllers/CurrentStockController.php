<?php

namespace App\Http\Controllers;

use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CurrentStockController extends Controller
{
    /**
     * Display current stock summary by product and warehouse
     */
    public function index(Request $request)
    {
        $stocks = QueryBuilder::for(
            CurrentStock::query()
                ->with(['product', 'warehouse'])
                ->where('quantity_on_hand', '>', 0)
        )
            ->allowedFilters([
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::callback('has_promotional', function ($query) {
                    $query->where('promotional_batches', '>', 0);
                }),
                AllowedFilter::callback('has_priority', function ($query) {
                    $query->where('priority_batches', '>', 0);
                }),
            ])
            ->defaultSort('product_id')
            ->paginate(50)
            ->withQueryString();

        return view('inventory.current-stock.index', [
            'stocks' => $stocks,
            'products' => Product::orderBy('product_name')->get(['id', 'product_code', 'product_name']),
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
        ]);
    }

    /**
     * Display stock details by batch for a specific product and warehouse
     */
    public function showByBatch(Request $request)
    {
        $productId = $request->get('product_id');
        $warehouseId = $request->get('warehouse_id');

        $batches = CurrentStockByBatch::query()
            ->with(['product', 'warehouse', 'stockBatch'])
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_on_hand', '>', 0)
            ->orderBy('priority_order')
            ->orderBy('expiry_date')
            ->get();

        $currentStock = CurrentStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return view('inventory.current-stock.by-batch', [
            'batches' => $batches,
            'currentStock' => $currentStock,
            'product' => Product::find($productId),
            'warehouse' => Warehouse::find($warehouseId),
        ]);
    }
}
