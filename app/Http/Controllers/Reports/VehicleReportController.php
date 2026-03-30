<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VehicleReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-vehicle'),
        ];
    }

    public const PER_PAGE_OPTIONS = [100, 500, 1000, 10000, 1000000];

    public const DEFAULT_PER_PAGE = 100;

    /**
     * Display the Vehicle report.
     */
    public function index(Request $request)
    {
        $perPage = $this->getPerPage($request);

        $vehicles = QueryBuilder::for(
            Vehicle::query()->with(['company', 'supplier', 'employee'])
        )
            ->allowedFilters([
                AllowedFilter::partial('vehicle_number'),
                AllowedFilter::partial('registration_number'),
                AllowedFilter::partial('vehicle_type'),
                AllowedFilter::partial('make_model'),
                AllowedFilter::partial('driver_name'),
                AllowedFilter::exact('company_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('is_active'),
            ])
            ->join('suppliers', 'vehicles.supplier_id', '=', 'suppliers.id')
            ->orderBy('suppliers.supplier_name')
            ->orderBy('vehicles.registration_number')
            ->select('vehicles.*')
            ->paginate($perPage)
            ->withQueryString();

        return view('reports.vehicle.index', [
            'vehicles' => $vehicles,
            'companyOptions' => Company::orderBy('company_name')->get(['id', 'company_name']),
            'supplierOptions' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'employeeOptions' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'typeOptions' => Vehicle::distinct()->pluck('vehicle_type')->filter()->values(),
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
