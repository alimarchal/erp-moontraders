<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailySalesReportController extends Controller
{
    /**
     * Display daily sales report with filters
     */
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', now()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $employeeId = $request->input('employee_id');
        $vehicleId = $request->input('vehicle_id');
        $warehouseId = $request->input('warehouse_id');

        // Get settlements with filters
        $query = SalesSettlement::with(['employee', 'vehicle', 'warehouse', 'items.product'])
            ->where('status', 'posted')
            ->whereBetween('settlement_date', [$startDate, $endDate]);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($request->filled('settlement_number')) {
            $query->where('settlement_number', 'like', '%' . $request->input('settlement_number') . '%');
        }

        $settlements = $query->get();

        // Calculate calculated fields first
        $settlements->each(function ($settlement) {
            $settlement->net_sales_amount = $settlement->items->sum('total_sales_value');
            $settlement->total_cogs_amount = $settlement->items->sum('total_cogs');
            $settlement->recoveries_amount = (float) ($settlement->credit_recoveries ?? 0);
            $settlement->gross_profit = $settlement->net_sales_amount - $settlement->total_cogs_amount;
            $settlement->net_profit = $settlement->gross_profit - $settlement->expenses_claimed;
            $settlement->gp_margin = $settlement->net_sales_amount > 0 ? ($settlement->gross_profit / $settlement->net_sales_amount) * 100 : 0;
            $settlement->np_margin = $settlement->net_sales_amount > 0 ? ($settlement->net_profit / $settlement->net_sales_amount) * 100 : 0;
        });

        // Apply sorting
        $sortBy = $request->input('sort_by', 'date_desc');
        $settlements = match ($sortBy) {
            'date_asc' => $settlements->sortBy('settlement_date'),
            'date_desc' => $settlements->sortByDesc('settlement_date'),
            'settlement_no_asc' => $settlements->sortBy('settlement_number'),
            'settlement_no_desc' => $settlements->sortByDesc('settlement_number'),
            'salesman_asc' => $settlements->sortBy(fn($s) => $s->employee->name ?? ''),
            'salesman_desc' => $settlements->sortByDesc(fn($s) => $s->employee->name ?? ''),
            'total_sales_desc' => $settlements->sortByDesc('net_sales_amount'),
            'total_sales_asc' => $settlements->sortBy('net_sales_amount'),
            'net_profit_desc' => $settlements->sortByDesc('net_profit'),
            'net_profit_asc' => $settlements->sortBy('net_profit'),
            'gp_margin_desc' => $settlements->sortByDesc('gp_margin'),
            'gp_margin_asc' => $settlements->sortBy('gp_margin'),
            default => $settlements->sortByDesc('settlement_date'),
        };

        // Summary calculation remains the same...
        $summary = [
            'total_sales' => $settlements->sum('net_sales_amount'),
            'cash_sales' => $settlements->sum('cash_sales_amount'),
            'credit_sales' => $settlements->sum('credit_sales_amount'),
            'cheque_sales' => $settlements->sum('cheque_sales_amount'),
            'recoveries' => $settlements->sum('recoveries_amount'),
            'total_quantity_sold' => $settlements->sum('total_quantity_sold'),
            'total_quantity_returned' => $settlements->sum('total_quantity_returned'),
            'total_quantity_shortage' => $settlements->sum('total_quantity_shortage'),
            'cash_collected' => $settlements->sum('cash_collected'),
            'expenses_claimed' => $settlements->sum('expenses_claimed'),
            'cash_to_deposit' => $settlements->sum('cash_to_deposit'),
        ];

        // Calculate gross profit
        $summary['total_cogs'] = $settlements->sum('total_cogs_amount');
        $summary['gross_profit'] = $summary['total_sales'] - $summary['total_cogs'];
        $summary['gross_profit_margin'] = $summary['total_sales'] > 0
            ? ($summary['gross_profit'] / $summary['total_sales']) * 100
            : 0;

        return view('reports.daily-sales.index', [
            'settlements' => $settlements,
            'summary' => $summary,
            'employees' => Employee::orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::orderBy('vehicle_number')->get(['id', 'vehicle_number']),
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'employeeId' => $employeeId,
            'vehicleId' => $vehicleId,
            'warehouseId' => $warehouseId,
            'sortBy' => $sortBy,
        ]);
    }

    /**
     * Display product-wise sales report
     */
    public function productWise(Request $request)
    {
        $startDate = $request->input('start_date', now()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $employeeId = $request->input('employee_id');

        // Get product-wise sales data
        $query = DB::table('sales_settlement_items as ssi')
            ->join('sales_settlements as ss', 'ssi.sales_settlement_id', '=', 'ss.id')
            ->join('products as p', 'ssi.product_id', '=', 'p.id')
            ->join('employees as e', 'ss.employee_id', '=', 'e.id')
            ->where('ss.status', 'posted')
            ->whereBetween('ss.settlement_date', [$startDate, $endDate]);

        if ($employeeId) {
            $query->where('ss.employee_id', $employeeId);
        }

        $productSales = $query->select(
            'p.id as product_id',
            'p.product_name',
            'p.product_code',
            DB::raw('SUM(ssi.quantity_issued) as total_issued'),
            DB::raw('SUM(ssi.quantity_sold) as total_sold'),
            DB::raw('SUM(ssi.quantity_returned) as total_returned'),
            DB::raw('SUM(ssi.quantity_shortage) as total_shortage'),
            DB::raw('SUM(ssi.total_sales_value) as total_sales_value'),
            DB::raw('SUM(ssi.total_cogs) as total_cogs'),
            DB::raw('SUM(ssi.total_sales_value - ssi.total_cogs) as gross_profit'),
            DB::raw('AVG(ssi.unit_selling_price) as avg_selling_price')
        )
            ->groupBy('p.id', 'p.product_name', 'p.product_code')
            ->orderByDesc('total_sales_value')
            ->get();

        // Calculate totals
        $totals = [
            'total_issued' => $productSales->sum('total_issued'),
            'total_sold' => $productSales->sum('total_sold'),
            'total_returned' => $productSales->sum('total_returned'),
            'total_shortage' => $productSales->sum('total_shortage'),
            'total_sales_value' => $productSales->sum('total_sales_value'),
            'total_cogs' => $productSales->sum('total_cogs'),
            'gross_profit' => $productSales->sum('gross_profit'),
        ];

        return view('reports.daily-sales.product-wise', [
            'productSales' => $productSales,
            'totals' => $totals,
            'employees' => Employee::orderBy('name')->get(['id', 'name']),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'employeeId' => $employeeId,
        ]);
    }

    /**
     * Display salesman-wise sales report
     */
    public function salesmanWise(Request $request)
    {
        $startDate = $request->input('start_date', now()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $settlements = SalesSettlement::with(['employee', 'vehicle', 'items'])
            ->where('status', 'posted')
            ->whereBetween('settlement_date', [$startDate, $endDate])
            ->get();

        $salesmanPerformance = $settlements
            ->groupBy(function ($settlement) {
                return $settlement->employee_id . '-' . $settlement->vehicle_id;
            })
            ->map(function ($group) {
                $first = $group->first();
                $totalSales = $group->sum(function ($settlement) {
                    return $settlement->items->sum('total_sales_value');
                });
                $totalCogs = $group->sum(function ($settlement) {
                    return $settlement->items->sum('total_cogs');
                });

                $grossProfit = $totalSales - $totalCogs;

                return (object) [
                    'employee_id' => $first->employee_id,
                    'employee_name' => $first->employee->name ?? 'N/A',
                    'employee_code' => $first->employee->employee_code ?? '',
                    'vehicle_number' => $first->vehicle->vehicle_number ?? 'N/A',
                    'settlement_count' => $group->count(),
                    'total_sales' => $totalSales,
                    'cash_sales' => $group->sum('cash_sales_amount'),
                    'credit_sales' => $group->sum('credit_sales_amount'),
                    'cheque_sales' => $group->sum('cheque_sales_amount'),
                    'bank_transfer_sales' => $group->sum('bank_transfer_amount'),
                    'recoveries' => $group->sum('credit_recoveries'),
                    'total_quantity_sold' => $group->sum('total_quantity_sold'),
                    'total_returned' => $group->sum('total_quantity_returned'),
                    'total_shortage' => $group->sum('total_quantity_shortage'),
                    'cash_collected' => $group->sum('cash_collected'),
                    'expenses_claimed' => $group->sum('expenses_claimed'),
                    'total_cogs' => $totalCogs,
                    'gross_profit' => $grossProfit,
                    'gross_profit_margin' => $totalSales > 0 ? ($grossProfit / $totalSales) * 100 : 0,
                ];
            })
            ->sortByDesc('total_sales')
            ->values();

        // Calculate totals
        $totals = [
            'settlement_count' => $salesmanPerformance->sum('settlement_count'),
            'total_sales' => $salesmanPerformance->sum('total_sales'),
            'cash_sales' => $salesmanPerformance->sum('cash_sales'),
            'credit_sales' => $salesmanPerformance->sum('credit_sales'),
            'bank_transfer_sales' => $salesmanPerformance->sum('bank_transfer_sales'),
            'recoveries' => $salesmanPerformance->sum('recoveries'),
            'total_quantity_sold' => $salesmanPerformance->sum('total_quantity_sold'),
            'cash_collected' => $salesmanPerformance->sum('cash_collected'),
            'gross_profit' => $salesmanPerformance->sum('gross_profit'),
        ];

        return view('reports.daily-sales.salesman-wise', [
            'salesmanPerformance' => $salesmanPerformance,
            'totals' => $totals,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Display van stock report
     */
    public function vanStock(Request $request)
    {
        $vehicleId = $request->input('vehicle_id');

        $query = DB::table('van_stock_balances as vsb')
            ->join('vehicles as v', 'vsb.vehicle_id', '=', 'v.id')
            ->join('products as p', 'vsb.product_id', '=', 'p.id')
            ->select(
                'v.id as vehicle_id',
                'v.vehicle_number',
                'p.id as product_id',
                'p.product_code',
                'p.product_name',
                'vsb.opening_balance',
                'vsb.quantity_on_hand',
                'vsb.average_cost',
                DB::raw('vsb.quantity_on_hand * vsb.average_cost as total_value')
            )
            ->where('vsb.quantity_on_hand', '>', 0);

        if ($vehicleId) {
            $query->where('vsb.vehicle_id', $vehicleId);
        }

        $vanStock = $query->orderBy('v.vehicle_number')
            ->orderBy('p.product_name')
            ->get();

        // Group by vehicle
        $groupedStock = $vanStock->groupBy('vehicle_id');

        return view('reports.daily-sales.van-stock', [
            'groupedStock' => $groupedStock,
            'vehicles' => Vehicle::orderBy('vehicle_number')->get(['id', 'vehicle_number']),
            'vehicleId' => $vehicleId,
        ]);
    }
}
