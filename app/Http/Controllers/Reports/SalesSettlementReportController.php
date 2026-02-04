<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SalesSettlementReportController extends Controller
{
    public function index(Request $request)
    {
        // Set default dates in request if missing, so QueryBuilder picks them up via default logic or accessible via input
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
        $query = QueryBuilder::for(SalesSettlement::class)
            ->allowedFilters([
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('settlement_number'),
                // Custom date filters
                AllowedFilter::callback('start_date', function ($query, $value) {
                    $query->where('settlement_date', '>=', $value);
                }),
                AllowedFilter::callback('end_date', function ($query, $value) {
                    $query->where('settlement_date', '<=', $value);
                }),
            ])
            ->defaultSort('-settlement_date', '-settlement_number')
            ->allowedSorts([
                'settlement_date',
                'settlement_number',
                'total_value_issued',
                'total_sales_amount',
                'cash_collected',
                'cheques_collected',
                'bank_transfer_amount',
                'credit_sales_amount',
                'expenses_claimed',
                'total_quantity_shortage',
                'cash_to_deposit',
                'status'
            ])
            ->with(['employee', 'vehicle', 'warehouse', 'goodsIssue']);

        // Execute query to get results
        $settlements = $query->get();

        // Calculate totals for footer
        // Doing this on the collection is acceptable for reasonable report sizes
        $totals = (object) [
            'total_value_issued' => $settlements->sum('total_value_issued'),
            'total_sales_amount' => $settlements->sum('total_sales_amount'),
            'cash_collected' => $settlements->sum('cash_collected'),
            'cheques_collected' => $settlements->sum('cheques_collected'),
            'bank_transfer_amount' => $settlements->sum('bank_transfer_amount'),
            'credit_sales_amount' => $settlements->sum('credit_sales_amount'),
            'expenses_claimed' => $settlements->sum('expenses_claimed'),
            'total_quantity_shortage' => $settlements->sum('total_quantity_shortage'),
            'cash_to_deposit' => $settlements->sum('cash_to_deposit'),
            'credit_recoveries' => $settlements->sum('credit_recoveries'),
            'gross_profit' => $settlements->sum('gross_profit'),
            'net_profit' => $settlements->sum('gross_profit') - $settlements->sum('expenses_claimed'),
        ];

        // Fetch filter options
        $employees = Employee::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'employee_code as code']);

        $vehicles = Vehicle::where('is_active', true)->orderBy('registration_number', 'asc')->get(['id', 'registration_number']);
        $warehouses = Warehouse::orderBy('warehouse_name', 'asc')->get(['id', 'warehouse_name as name']);

        // Prepare filter summary strings for print view
        $filterSummary = [];
        if ($request->filled('filter.employee_id')) {
            $ids = is_array($request->input('filter.employee_id')) ? $request->input('filter.employee_id') : [$request->input('filter.employee_id')];
            $names = $employees->whereIn('id', $ids)->pluck('name')->implode(', ');
            $filterSummary[] = "Salesman: $names";
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

        return view('reports.sales-settlement.index', [
            'settlements' => $settlements,
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
    public function print(SalesSettlement $salesSettlement)
    {
        $salesSettlement->load([
            'goodsIssue',
            'employee',
            'vehicle',
            'warehouse',
            'verifiedBy',
            'items.product',
            'items.batches.stockBatch',
            'advanceTaxes.customer',
            'expenses.expenseAccount',
            'cheques.bankAccount',
            'cheques.customer',
            'creditSales.customer',
            'recoveries.customer',
            'recoveries.bankAccount',
            'bankTransfers.customer',
            'bankTransfers.bankAccount',
            'cashDenominations',
            'amrPowders.product',
            'amrLiquids.product'
        ]);

        return view('reports.sales-settlement.print', [
            'settlement' => $salesSettlement,
        ]);
    }
}
