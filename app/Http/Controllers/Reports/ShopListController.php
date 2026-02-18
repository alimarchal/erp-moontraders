<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ShopListController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-shop-list'),
        ];
    }

    public const PER_PAGE_OPTIONS = [100, 500, 1000, 10000, 1000000];

    public const DEFAULT_PER_PAGE = 1000;

    /**
     * Display the Shop List report.
     */
    public function index(Request $request)
    {
        $perPage = $this->getPerPage($request);

        $customers = QueryBuilder::for(Customer::query())
            ->allowedFilters([
                AllowedFilter::partial('customer_name'),
                AllowedFilter::partial('customer_code'),
                AllowedFilter::partial('business_name'),
                AllowedFilter::partial('phone'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('address'),
                AllowedFilter::partial('city'),
                AllowedFilter::partial('sub_locality'),
                AllowedFilter::partial('ntn'),
                AllowedFilter::exact('channel_type'),
                AllowedFilter::exact('customer_category'),
                AllowedFilter::exact('sales_rep_id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('it_status'),
            ])
            ->orderBy('customer_code')
            ->paginate($perPage)
            ->withQueryString();

        return view('reports.shop-list.index', [
            'customers' => $customers,
            'channelTypes' => Customer::CHANNEL_TYPES,
            'customerCategories' => Customer::CUSTOMER_CATEGORIES,
            'statusOptions' => ['' => 'All', '1' => 'Active', '0' => 'Inactive'],
            'itStatusOptions' => ['' => 'All', '1' => 'Filer', '0' => 'Non-Filer'],
            'salesRepOptions' => User::orderBy('name')->get(['id', 'name']),
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
