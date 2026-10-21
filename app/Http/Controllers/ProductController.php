<?php

namespace App\Http\Controllers;

use App\Exports\ProductExport;
use App\Exports\ProductImportTemplateExport;
use App\Http\Requests\ImportProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Imports\ProductImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use App\Models\Supplier;
use App\Models\Uom;
use App\Services\ProductPricingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:product-list', only: ['index', 'show', 'exportExcel']),
            new Middleware('can:product-create', only: ['create', 'store', 'importForm', 'import', 'importTemplate']),
            new Middleware('can:product-edit', only: ['edit', 'update']),
            new Middleware('can:product-delete', only: ['destroy']),
        ];
    }

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100, 250];

    private const DEFAULT_PER_PAGE = 25;

    public function __construct(private ProductPricingService $pricing) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $products = $this->buildFilteredQuery()
            ->paginate($perPage)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'supplierOptions' => $this->supplierOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'uomOptions' => $this->uomOptions(),
            'valuationMethods' => Product::VALUATION_METHODS,
            'statusOptions' => ['1' => 'Active', '0' => 'Inactive'],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'perPage' => $perPage,
            'stats' => $this->listStats($request),
        ]);
    }

    /**
     * Headline numbers for the list; counts follow the filters except the status tab.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    private function listStats(Request $request): array
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($v) => $v !== null && $v !== '');
        unset($filters['is_active']);

        $byStatus = QueryBuilder::for($this->baseQuery(), new Request(['filter' => $filters]))
            ->allowedFilters($this->allowedFilters())
            ->getEloquentBuilder()
            ->reorder()
            ->selectRaw('is_active, COUNT(*) as aggregate')
            ->groupBy('is_active')
            ->pluck('aggregate', 'is_active');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[1] ?? 0),
            'inactive' => (int) ($byStatus[0] ?? 0),
        ];
    }

    /**
     * Excel import screen.
     */
    public function importForm()
    {
        return view('products.import', [
            'valuationMethods' => Product::VALUATION_METHODS,
        ]);
    }

    /**
     * Download the blank import template.
     */
    public function importTemplate()
    {
        return Excel::download(new ProductImportTemplateExport, 'products_import_template.xlsx');
    }

    /**
     * Import products from Excel. Existing product codes are skipped, never overwritten.
     */
    public function import(ImportProductRequest $request)
    {
        $dryRun = $request->boolean('dry_run');
        $import = new ProductImport($dryRun, $this->getUserSupplierScope());

        DB::beginTransaction();

        try {
            Excel::import($import, $request->file('import_file'));
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error importing products', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return back()->withErrors(['import_file' => 'The file could not be read. Please use the template and try again.']);
        }

        return redirect()
            ->route('products.import.form')
            ->with('import_result', [
                'dry_run' => $dryRun,
                'counts' => $import->counts(),
                'report' => $import->report(),
                'file' => $request->file('import_file')->getClientOriginalName(),
            ]);
    }

    /**
     * Export filtered products to Excel.
     */
    public function exportExcel(Request $request)
    {
        $query = $this->buildFilteredQuery()->getEloquentBuilder();

        return Excel::download(new ProductExport($query), 'products.xlsx');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('products.create', [
            'supplierOptions' => $this->supplierOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'uomOptions' => $this->uomOptions(),
            'valuationMethods' => Product::VALUATION_METHODS,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        DB::beginTransaction();

        try {
            $payload = $request->validated();
            $payload['valuation_method'] = $payload['valuation_method'] ?? Product::VALUATION_METHODS[0];
            $payload['is_active'] = array_key_exists('is_active', $payload)
                ? (bool) $payload['is_active']
                : true;
            $payload['is_powder'] = array_key_exists('is_powder', $payload)
                ? (bool) $payload['is_powder']
                : false;

            $product = Product::create($payload);

            DB::commit();

            return redirect()
                ->route('products.index')
                ->with('success', "Product '{$product->product_name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating product', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create product. Please review the input and try again.';
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'products_product_code_unique')) {
                    $message = 'The product code must be unique.';
                } elseif (str_contains($e->getMessage(), 'products_barcode_unique')) {
                    $message = 'The barcode must be unique.';
                }
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating product', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create product. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $product->load(['supplier', 'uom', 'salesUom', 'category']);

        return view('products.show', [
            'product' => $product,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $product->load(['supplier', 'uom', 'salesUom', 'category']);

        return view('products.edit', [
            'product' => $product,
            'supplierOptions' => $this->supplierOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'uomOptions' => $this->uomOptions(),
            'valuationMethods' => Product::VALUATION_METHODS,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        DB::beginTransaction();

        try {
            $payload = $request->validated();
            $payload['valuation_method'] = $payload['valuation_method'] ?? $product->valuation_method;
            $payload['is_active'] = array_key_exists('is_active', $payload)
                ? (bool) $payload['is_active']
                : $product->is_active;
            $payload['is_powder'] = array_key_exists('is_powder', $payload)
                ? (bool) $payload['is_powder']
                : $product->is_powder;

            $oldSellingPrice = $product->unit_sell_price;
            $oldExpiryPrice = $product->expiry_price;
            $oldCostPrice = $product->cost_price;

            $updated = $product->update($payload);

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the product.');
            }

            $impactedBatchIds = collect();

            // When unit_sell_price changes, cascade to batches with available stock
            if ($product->unit_sell_price !== null && (float) $product->unit_sell_price !== (float) $oldSellingPrice) {
                $newPrice = $product->unit_sell_price;

                $impactedBatchIds = $this->pricing->cascadeSellingPrice($product, $newPrice);

                ProductPriceChangeLog::create([
                    'product_id' => $product->id,
                    'changed_by' => auth()->id(),
                    'price_type' => 'selling_price',
                    'old_price' => $oldSellingPrice,
                    'new_price' => $newPrice,
                    'impacted_batch_ids' => $impactedBatchIds->all(),
                    'impacted_batch_count' => $impactedBatchIds->count(),
                ]);
            }

            if ((float) $product->expiry_price !== (float) $oldExpiryPrice) {
                ProductPriceChangeLog::create([
                    'product_id' => $product->id,
                    'changed_by' => auth()->id(),
                    'price_type' => 'expiry_price',
                    'old_price' => $oldExpiryPrice,
                    'new_price' => $product->expiry_price,
                    'impacted_batch_ids' => null,
                    'impacted_batch_count' => 0,
                ]);
            }

            if ((float) $product->cost_price !== (float) $oldCostPrice) {
                ProductPriceChangeLog::create([
                    'product_id' => $product->id,
                    'changed_by' => auth()->id(),
                    'price_type' => 'cost_price',
                    'old_price' => $oldCostPrice,
                    'new_price' => $product->cost_price,
                    'impacted_batch_ids' => null,
                    'impacted_batch_count' => 0,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('products.index')
                ->with('success', "Product '{$product->product_name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating product', [
                'product_id' => $product->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update product. Please review the input and try again.';
            if ($e->getCode() === '23000') {
                if (str_contains($e->getMessage(), 'products_product_code_unique')) {
                    $message = 'The product code must be unique.';
                } elseif (str_contains($e->getMessage(), 'products_barcode_unique')) {
                    $message = 'The barcode must be unique.';
                }
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating product', [
                'product_id' => $product->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update product. Please try again.');
        }
    }

    /**
     * "Delete" only deactivates the product; records are never removed.
     */
    public function destroy(Product $product)
    {
        try {
            $product->update(['is_active' => false]);

            return redirect()
                ->route('products.index')
                ->with('success', "Product '{$product->product_name}' deactivated. Its history is kept.");
        } catch (\Throwable $e) {
            Log::error('Error deactivating product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to deactivate product. Please try again.');
        }
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

    /**
     * Products visible to the current user (supplier-scoped users only see their own).
     *
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    private function baseQuery()
    {
        $userSupplierId = $this->getUserSupplierScope();

        return Product::query()
            ->with(['supplier', 'uom', 'salesUom', 'category'])
            ->when($userSupplierId, fn ($q, $supplierId) => $q->where('supplier_id', $supplierId));
    }

    /**
     * @return array<int, AllowedFilter>
     */
    private function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('search', fn ($query, $value) => $query->where(function ($q) use ($value) {
                $term = '%'.trim((string) $value).'%';
                $q->where('product_name', 'like', $term)
                    ->orWhere('product_code', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('barcode', 'like', $term);
            })),
            AllowedFilter::exact('product_name'),
            AllowedFilter::exact('product_code'),
            AllowedFilter::partial('brand'),
            AllowedFilter::partial('barcode'),
            AllowedFilter::partial('pack_size'),
            AllowedFilter::exact('supplier_id'),
            AllowedFilter::exact('category_id'),
            AllowedFilter::exact('uom_id'),
            AllowedFilter::exact('sales_uom_id'),
            AllowedFilter::exact('valuation_method'),
            AllowedFilter::exact('is_active'),
            AllowedFilter::exact('is_powder'),
            AllowedFilter::exact('expiry_price'),
        ];
    }

    /**
     * Build the filtered query shared by index and export.
     */
    private function buildFilteredQuery(): QueryBuilder
    {
        return QueryBuilder::for($this->baseQuery())
            ->allowedFilters($this->allowedFilters())
            ->allowedSorts([
                AllowedSort::field('product_name'),
                AllowedSort::field('product_code'),
                AllowedSort::field('unit_sell_price'),
                AllowedSort::field('cost_price'),
                AllowedSort::field('reorder_level'),
                AllowedSort::field('brand'),
            ])
            ->defaultSort('product_name');
    }

    protected function supplierOptions()
    {
        $userSupplierId = $this->getUserSupplierScope();

        return Supplier::query()
            ->when($userSupplierId, fn ($q, $supplierId) => $q->where('id', $supplierId))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);
    }

    protected function categoryOptions()
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    protected function uomOptions()
    {
        return Uom::orderBy('uom_name')->get(['id', 'uom_name', 'symbol']);
    }
}
