<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:product-list', only: ['index', 'show']),
            new Middleware('can:product-create', only: ['create', 'store']),
            new Middleware('can:product-edit', only: ['edit', 'update']),
            new Middleware('can:product-delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $products = QueryBuilder::for(
            Product::query()->with(['supplier', 'uom', 'salesUom', 'category'])
        )
            ->allowedFilters([
                AllowedFilter::exact('product_name'),
                AllowedFilter::exact('product_code'),
                AllowedFilter::partial('brand'),
                AllowedFilter::partial('barcode'),
                AllowedFilter::partial('pack_size'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('uom_id'),
                AllowedFilter::exact('valuation_method'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_powder'),
            ])
            ->orderBy('product_name')
            ->paginate(40)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'productOptions' => $this->productOptions(),
            'supplierOptions' => $this->supplierOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'uomOptions' => $this->uomOptions(),
            'valuationMethods' => Product::VALUATION_METHODS,
            'statusOptions' => ['1' => 'Active', '0' => 'Inactive'],
        ]);
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

            $updated = $product->update($payload);

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the product.');
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
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        try {
            $name = $product->product_name;
            $product->delete();

            return redirect()
                ->route('products.index')
                ->with('success', "Product '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete product. Please try again.');
        }
    }

    protected function supplierOptions()
    {
        return Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);
    }

    protected function categoryOptions()
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    protected function uomOptions()
    {
        return Uom::orderBy('uom_name')->get(['id', 'uom_name', 'symbol']);
    }

    protected function productOptions()
    {
        return Product::where('is_active', true)
            ->orderBy('product_name')
            ->get(['id', 'product_code', 'product_name']);
    }
}
