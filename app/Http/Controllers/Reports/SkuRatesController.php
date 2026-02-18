<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SkuRatesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-sku-rates'),
        ];
    }

    public const PER_PAGE_OPTIONS = [100, 500, 1000, 10000, 1000000];

    public const DEFAULT_PER_PAGE = 1000;

    /**
     * Display the SKU Rates report.
     */
    public function index(Request $request)
    {
        $perPage = $this->getPerPage($request);

        $products = QueryBuilder::for(
            Product::query()->with(['supplier', 'uom', 'salesUom'])
        )
            ->allowedFilters([
                AllowedFilter::partial('product_name'),
                AllowedFilter::partial('product_code'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::partial('barcode'),
                AllowedFilter::partial('pack_size'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('uom_id'),
                AllowedFilter::exact('valuation_method'),
                AllowedFilter::exact('is_active'),
            ])
            ->join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
            ->orderBy('suppliers.supplier_name')
            ->orderBy('products.product_code')
            ->select('products.*')
            ->paginate($perPage)
            ->withQueryString();

        return view('reports.sku-rates.index', [
            'products' => $products,
            'categories' => \App\Models\Category::where('is_active', true)->orderBy('name')->get(),
            'supplierOptions' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'uomOptions' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name']),
            'valuationMethods' => Product::VALUATION_METHODS,
            'statusOptions' => ['' => 'All', '1' => 'Active', '0' => 'Inactive'],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'currentPerPage' => $perPage,
        ]);
    }

    /**
     * Get the per page value from request or default.
     */
    private function getPerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);

        if (! in_array($perPage, self::PER_PAGE_OPTIONS)) {
            return self::DEFAULT_PER_PAGE;
        }

        return $perPage;
    }
}
