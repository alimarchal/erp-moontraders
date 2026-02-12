<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CurrentStockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-view-inventory'),
        ];
    }

    /**
     * Display current stock summary by product and warehouse
     */
    public function index(Request $request)
    {
        $stocks = QueryBuilder::for(
            CurrentStock::query()
                ->with(['product.category', 'product.supplier', 'warehouse'])
                ->where('quantity_on_hand', '>', 0)
        )
            ->allowedFilters([
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::callback('category_id', function ($query, $value) {
                    $query->whereHas('product', fn ($q) => $q->where('category_id', $value));
                }),
                AllowedFilter::callback('supplier_id', function ($query, $value) {
                    $query->whereHas('product', fn ($q) => $q->where('supplier_id', $value));
                }),
                AllowedFilter::callback('has_promotional', function ($query) {
                    $query->where('promotional_batches', '>', 0);
                }),
                AllowedFilter::callback('has_priority', function ($query) {
                    $query->where('priority_batches', '>', 0);
                }),
                AllowedFilter::callback('stock_level', function ($query, $value) {
                    match ($value) {
                        'low' => $query->where('quantity_on_hand', '<=', 10),
                        'medium' => $query->whereBetween('quantity_on_hand', [11, 100]),
                        'high' => $query->where('quantity_on_hand', '>', 100),
                        'zero_available' => $query->where('quantity_available', '<=', 0),
                        default => null,
                    };
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->whereHas('product', function ($q) use ($value) {
                        $q->where('product_name', 'like', "%{$value}%")
                            ->orWhere('product_code', 'like', "%{$value}%")
                            ->orWhere('barcode', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts([
                'quantity_on_hand',
                'quantity_available',
                'average_cost',
                'total_value',
                'total_batches',
                AllowedSort::callback('product_name', function ($query, bool $descending) {
                    $direction = $descending ? 'desc' : 'asc';
                    $query->join('products', 'current_stock.product_id', '=', 'products.id')
                        ->orderBy('products.product_name', $direction)
                        ->select('current_stock.*');
                }),
            ])
            ->defaultSort('product_id')
            ->paginate(50)
            ->withQueryString();

        return view('inventory.current-stock.index', [
            'stocks' => $stocks,
            'products' => Product::orderBy('product_name')->get(['id', 'product_code', 'product_name']),
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
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
