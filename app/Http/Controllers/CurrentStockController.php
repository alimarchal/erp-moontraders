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
            new Middleware('permission:inventory-view|report-inventory-daily-stock-register|report-inventory-salesman-stock-register|report-inventory-inventory-ledger|report-inventory-van-stock-batch|report-inventory-van-stock-ledger'),
        ];
    }

    /**
     * Display current stock summary by product and warehouse
     */
    public function index(Request $request)
    {
        $hasFilter = $request->has('filter');
        $userSupplierId = $this->getUserSupplierScope();

        $this->authorizeCurrentStockFilterAccess($request, $userSupplierId);

        $baseQuery = CurrentStock::query()
            ->with(['product.category', 'product.supplier', 'warehouse'])
            ->where('quantity_on_hand', '>', 0)
            ->when($userSupplierId, function ($query, $supplierId) {
                $query->whereHas('product', fn ($productQuery) => $productQuery->where('supplier_id', $supplierId));
            })
            ->when(! $hasFilter, fn ($q) => $q->whereRaw('0 = 1'));

        $stocks = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::callback('category_id', function ($query, $value) {
                    $query->whereHas('product', fn ($q) => $q->where('category_id', $value));
                }),
                AllowedFilter::callback('supplier_id', function ($query, $value) {
                    $query->whereHas('product', fn ($q) => $q->where('supplier_id', $value));
                }),
                AllowedFilter::callback('has_reserved', function ($query) {
                    $query->where('quantity_reserved', '>', 0);
                }),
                AllowedFilter::callback('min_value', function ($query, $value) {
                    $query->where('total_value', '>=', (float) $value);
                }),
                AllowedFilter::callback('max_value', function ($query, $value) {
                    $query->where('total_value', '<=', (float) $value);
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
                    $value = trim($value);
                    $query->whereHas('product', function ($q) use ($value) {
                        $q->whereRaw('TRIM(product_name) like ?', ["%{$value}%"])
                            ->orWhereRaw('TRIM(product_code) like ?', ["%{$value}%"])
                            ->orWhereRaw('TRIM(barcode) like ?', ["%{$value}%"]);
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
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        // Attach batch data for Avg Cost breakdown display
        if ($stocks->isNotEmpty()) {
            $batchGroups = CurrentStockByBatch::query()
                ->with('stockBatch')
                ->where('quantity_on_hand', '>', 0)
                ->whereIn('product_id', $stocks->pluck('product_id'))
                ->whereIn('warehouse_id', $stocks->pluck('warehouse_id'))
                ->orderBy('priority_order')
                ->orderBy('expiry_date')
                ->get()
                ->groupBy(fn ($b) => $b->product_id.'_'.$b->warehouse_id);

            foreach ($stocks as $stock) {
                $stock->setRelation('batches', $batchGroups->get($stock->product_id.'_'.$stock->warehouse_id, collect()));
            }
        }

        return view('inventory.current-stock.index', [
            'stocks' => $stocks,
            'hasFilter' => $hasFilter,
            'products' => Product::query()
                ->when($userSupplierId, fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
                ->orderBy('product_name')
                ->get(['id', 'product_code', 'product_name', 'supplier_id']),
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()
                ->when($userSupplierId, fn ($query, $supplierId) => $query->where('id', $supplierId))
                ->orderBy('supplier_name')
                ->get(['id', 'supplier_name']),
            'perPage' => $request->get('per_page', 50),
        ]);
    }

    private function resolvePerPage(Request $request): int
    {
        $value = $request->get('per_page', 50);
        $allowed = [50, 100, 200, 500, 1000];

        if ($value === 'all' || (int) $value >= 9999) {
            return 9999;
        }

        return in_array((int) $value, $allowed) ? (int) $value : 50;
    }

    /**
     * Display stock details by batch for a specific product and warehouse
     */
    public function showByBatch(Request $request)
    {
        $productId = $request->get('product_id');
        $warehouseId = $request->get('warehouse_id');

        if (! $productId || ! $warehouseId) {
            return view('inventory.current-stock.by-batch', [
                'batches' => collect(),
                'currentStock' => null,
                'product' => null,
                'warehouse' => null,
            ]);
        }

        $currentStock = CurrentStock::query()
            ->with('product')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->firstOrFail();

        $this->authorizeCurrentStockSupplierAccess($currentStock);

        $batches = CurrentStockByBatch::query()
            ->with(['product', 'warehouse', 'stockBatch'])
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_on_hand', '>', 0)
            ->orderBy('priority_order')
            ->orderBy('expiry_date')
            ->get();

        return view('inventory.current-stock.by-batch', [
            'batches' => $batches,
            'currentStock' => $currentStock,
            'product' => $currentStock->product,
            'warehouse' => Warehouse::find($warehouseId),
        ]);
    }

    private function getUserSupplierScope(): ?int
    {
        $user = auth()->user();

        if ($user->is_super_admin === 'Yes' || $user->hasRole('super-admin')) {
            return null;
        }

        if ($user->hasRole('admin')) {
            return null;
        }

        return $user->supplier_id ? (int) $user->supplier_id : null;
    }

    private function authorizeCurrentStockFilterAccess(Request $request, ?int $userSupplierId): void
    {
        if (! $userSupplierId) {
            return;
        }

        $requestedSupplierId = $request->input('filter.supplier_id');
        if ($requestedSupplierId && (int) $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        $requestedProductId = $request->input('filter.product_id');
        if (! $requestedProductId) {
            return;
        }

        $product = Product::query()
            ->select(['id', 'supplier_id'])
            ->find((int) $requestedProductId);

        if ($product && (int) $product->supplier_id !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this product.');
        }
    }

    private function authorizeCurrentStockSupplierAccess(CurrentStock $currentStock): void
    {
        $userSupplierId = $this->getUserSupplierScope();

        if ($userSupplierId && (int) $currentStock->product->supplier_id !== $userSupplierId) {
            abort(403, 'You do not have permission to access this stock record.');
        }
    }
}
