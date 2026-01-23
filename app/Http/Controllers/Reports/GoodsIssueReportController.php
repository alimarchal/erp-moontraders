<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GoodsIssueReportController extends Controller
{
    public function index(Request $request)
    {
        // Set default dates in request if missing
        if (!$request->has('filter.start_date')) {
            $request->merge([
                'filter' => array_merge($request->input('filter', []), [
                    'start_date' => now()->startOfMonth()->format('Y-m-d'),
                    'end_date' => now()->format('Y-m-d'),
                ])
            ]);
        }

        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');

        // Spatie Query Builder Implementation
        $query = QueryBuilder::for(GoodsIssue::class)
            ->allowedFilters([
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('issue_number'),
                // Custom date filters
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->where('issue_date', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->where('issue_date', '<=', $value);
                }),
            ])
            ->defaultSort('-issue_date', '-issue_number')
            ->allowedSorts([
                'issue_date',
                'issue_number',
                'total_quantity',
                'total_value',
                'status'
            ])
            ->with(['employee', 'vehicle', 'warehouse']);

        // Execute query to get results
        $goodsIssues = $query->get();

        // Calculate totals for footer
        $totals = (object) [
            'total_quantity' => $goodsIssues->sum('total_quantity'),
            'total_value' => $goodsIssues->sum('total_value'),
        ];

        // Fetch filter options
        $employees = Employee::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'employee_code as code']);

        $vehicles = Vehicle::where('is_active', true)->orderBy('registration_number', 'asc')->get(['id', 'registration_number']);
        $warehouses = Warehouse::orderBy('warehouse_name', 'asc')->get(['id', 'warehouse_name as name']);

        // Prepare filter summary strings for print view (if needed later) or display
        $filterSummary = [];
        if ($request->filled('filter.employee_id')) {
            $ids = is_array($request->input('filter.employee_id')) ? $request->input('filter.employee_id') : [$request->input('filter.employee_id')];
            $names = $employees->whereIn('id', $ids)->pluck('name')->implode(', ');
            $filterSummary[] = "Employee: $names";
        }
        if ($request->filled('filter.vehicle_id')) {
            $vehicle = $vehicles->find($request->input('filter.vehicle_id'));
            if ($vehicle)
                $filterSummary[] = "Vehicle: {$vehicle->registration_number}";
        }
        if ($request->filled('filter.warehouse_id')) {
            $warehouse = $warehouses->find($request->input('filter.warehouse_id'));
            if ($warehouse)
                $filterSummary[] = "Warehouse: {$warehouse->name}";
        }
        if ($request->filled('filter.status')) {
            $filterSummary[] = "Status: " . ucfirst($request->input('filter.status'));
        }

        return view('reports.goods-issue.index', [
            'goodsIssues' => $goodsIssues,
            'totals' => $totals,
            'employees' => $employees,
            'vehicles' => $vehicles,
            'warehouses' => $warehouses,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filters' => $request->input('filter', []),
            'filterSummary' => implode(' | ', $filterSummary),
        ]);
    }
}
