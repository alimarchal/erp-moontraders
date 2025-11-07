<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Models\ChartOfAccount;
use App\Models\ProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $categories = QueryBuilder::for(
            ProductCategory::query()->with(['parent', 'defaultInventoryAccount', 'defaultCogsAccount', 'defaultSalesRevenueAccount'])
        )
            ->allowedFilters([
                AllowedFilter::partial('category_name'),
                AllowedFilter::partial('category_code'),
                AllowedFilter::partial('description'),
                AllowedFilter::exact('parent_id'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('category_name')
            ->paginate(40)
            ->withQueryString();

        return view('product-categories.index', [
            'categories' => $categories,
            'parentOptions' => $this->parentOptions(),
            'statusOptions' => ['1' => 'Active', '0' => 'Inactive'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('product-categories.create', [
            'parentOptions' => $this->parentOptions(),
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductCategoryRequest $request)
    {
        DB::beginTransaction();

        try {
            $payload = $request->validated();
            $payload['is_active'] = array_key_exists('is_active', $payload)
                ? (bool) $payload['is_active']
                : true;

            $category = ProductCategory::create($payload);

            DB::commit();

            return redirect()
                ->route('product-categories.index')
                ->with('success', "Category '{$category->category_name}' created successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error creating product category', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to create product category. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'product_categories_category_code_unique')) {
                $message = 'The category code must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error creating product category', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create product category. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductCategory $productCategory)
    {
        $productCategory->load(['parent', 'defaultInventoryAccount', 'defaultCogsAccount', 'defaultSalesRevenueAccount']);

        return view('product-categories.show', [
            'category' => $productCategory,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductCategory $productCategory)
    {
        $productCategory->load(['parent', 'defaultInventoryAccount', 'defaultCogsAccount', 'defaultSalesRevenueAccount']);

        return view('product-categories.edit', [
            'category' => $productCategory,
            'parentOptions' => $this->parentOptions($productCategory->id),
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory)
    {
        DB::beginTransaction();

        try {
            $payload = $request->validated();
            $payload['is_active'] = array_key_exists('is_active', $payload)
                ? (bool) $payload['is_active']
                : $productCategory->is_active;

            $updated = $productCategory->update($payload);

            if (! $updated) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('info', 'No changes were made to the product category.');
            }

            DB::commit();

            return redirect()
                ->route('product-categories.index')
                ->with('success', "Category '{$productCategory->category_name}' updated successfully.");
        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Database error updating product category', [
                'category_id' => $productCategory->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            $message = 'Unable to update product category. Please review the input and try again.';
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'product_categories_category_code_unique')) {
                $message = 'The category code must be unique.';
            }

            return back()
                ->withInput()
                ->with('error', [
                    'message' => $message,
                    'db' => $e->getMessage(),
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Unexpected error updating product category', [
                'category_id' => $productCategory->id,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update product category. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductCategory $productCategory)
    {
        try {
            $name = $productCategory->category_name;
            $productCategory->delete();

            return redirect()
                ->route('product-categories.index')
                ->with('success', "Category '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Error deleting product category', [
                'category_id' => $productCategory->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to delete product category. Please try again.');
        }
    }

    /**
     * Provide reusable dropdown options for parent categories.
     */
    protected function parentOptions(?int $excludeId = null)
    {
        return ProductCategory::query()
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderBy('category_name')
            ->get(['id', 'category_name']);
    }

    /**
     * Provide chart of account options.
     */
    protected function accountOptions()
    {
        return ChartOfAccount::orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);
    }
}
