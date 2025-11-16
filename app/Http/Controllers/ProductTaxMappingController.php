<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductTaxMappingRequest;
use App\Http\Requests\UpdateProductTaxMappingRequest;
use App\Models\ProductTaxMapping;
use App\Models\Product;
use App\Models\TaxCode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductTaxMappingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $mappings = QueryBuilder::for(ProductTaxMapping::query())
            ->with(['product', 'taxCode'])
            ->allowedFilters([
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('tax_code_id'),
                AllowedFilter::exact('transaction_type'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $products = Product::where('is_active', true)->orderBy('product_code')->get();
        $taxCodes = TaxCode::where('is_active', true)->orderBy('tax_code')->get();

        return view('settings.product-tax-mappings.index', [
            'mappings' => $mappings,
            'products' => $products,
            'taxCodes' => $taxCodes,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::where('is_active', true)->orderBy('product_code')->get();
        $taxCodes = TaxCode::where('is_active', true)->orderBy('tax_code')->get();

        return view('settings.product-tax-mappings.create', [
            'products' => $products,
            'taxCodes' => $taxCodes,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductTaxMappingRequest $request)
    {
        DB::beginTransaction();

        try {
            $mapping = ProductTaxMapping::create($request->validated());

            DB::commit();

            return redirect()
                ->route('product-tax-mappings.index')
                ->with('success', 'Product tax mapping created successfully.');
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating product tax mapping', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create product tax mapping. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uk_product_tax')) {
                $message = 'This product-tax-transaction combination already exists.';
            }

            return back()
                ->withInput()
                ->with('error', $message);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating product tax mapping', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create product tax mapping. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductTaxMapping $productTaxMapping)
    {
        $productTaxMapping->load(['product', 'taxCode']);

        return view('settings.product-tax-mappings.show', [
            'mapping' => $productTaxMapping,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductTaxMapping $productTaxMapping)
    {
        $products = Product::where('is_active', true)->orderBy('product_code')->get();
        $taxCodes = TaxCode::where('is_active', true)->orderBy('tax_code')->get();

        return view('settings.product-tax-mappings.edit', [
            'mapping' => $productTaxMapping,
            'products' => $products,
            'taxCodes' => $taxCodes,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductTaxMappingRequest $request, ProductTaxMapping $productTaxMapping)
    {
        DB::beginTransaction();

        try {
            $updated = $productTaxMapping->update($request->validated());

            if (!$updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the product tax mapping.');
            }

            DB::commit();

            return redirect()
                ->route('product-tax-mappings.index')
                ->with('success', 'Product tax mapping updated successfully.');
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating product tax mapping', [
                'mapping_id' => $productTaxMapping->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update product tax mapping. Please review your input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uk_product_tax')) {
                $message = 'This product-tax-transaction combination already exists.';
            }

            return back()
                ->withInput()
                ->with('error', $message);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating product tax mapping', [
                'mapping_id' => $productTaxMapping->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update product tax mapping. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductTaxMapping $productTaxMapping)
    {
        try {
            $productTaxMapping->delete();

            return redirect()
                ->route('product-tax-mappings.index')
                ->with('success', 'Product tax mapping deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Error deleting product tax mapping', [
                'mapping_id' => $productTaxMapping->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete product tax mapping. Please try again.');
        }
    }
}
