<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        $settlements = $query->orderBy('settlement_date', 'desc')
            ->orderBy('employee_id')
            ->get();

        // Calculate summary
        $summary = [
            'total_sales' => $settlements->sum('total_sales_amount'),
            'cash_sales' => $settlements->sum('cash_sales_amount'),
            'credit_sales' => $settlements->sum('credit_sales_amount'),
            'cheque_sales' => $settlements->sum('cheque_sales_amount'),
            'total_quantity_sold' => $settlements->sum('total_quantity_sold'),
            'total_quantity_returned' => $settlements->sum('total_quantity_returned'),
            'total_quantity_shortage' => $settlements->sum('total_quantity_shortage'),
            'cash_collected' => $settlements->sum('cash_collected'),
            'expenses_claimed' => $settlements->sum('expenses_claimed'),
            'cash_to_deposit' => $settlements->sum('cash_to_deposit'),
        ];

        // Calculate gross profit
        $totalCOGS = $settlements->sum(function ($settlement) {
            return $settlement->items->sum('total_cogs');
        });
        $summary['gross_profit'] = $summary['total_sales'] - $totalCOGS;
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

        // Get salesman-wise performance
        $salesmanPerformance = DB::table('sales_settlements as ss')
            ->join('employees as e', 'ss.employee_id', '=', 'e.id')
            ->join('vehicles as v', 'ss.vehicle_id', '=', 'v.id')
            ->where('ss.status', 'posted')
            ->whereBetween('ss.settlement_date', [$startDate, $endDate])
            ->select(
                'e.id as employee_id',
                'e.name as employee_name',
                'e.employee_code',
                'v.vehicle_number',
                DB::raw('COUNT(ss.id) as settlement_count'),
                DB::raw('SUM(ss.total_sales_amount) as total_sales'),
                DB::raw('SUM(ss.cash_sales_amount) as cash_sales'),
                DB::raw('SUM(ss.credit_sales_amount) as credit_sales'),
                DB::raw('SUM(ss.cheque_sales_amount) as cheque_sales'),
                DB::raw('SUM(ss.total_quantity_sold) as total_quantity_sold'),
                DB::raw('SUM(ss.total_quantity_returned) as total_returned'),
                DB::raw('SUM(ss.total_quantity_shortage) as total_shortage'),
                DB::raw('SUM(ss.cash_collected) as cash_collected'),
                DB::raw('SUM(ss.expenses_claimed) as expenses_claimed')
            )
            ->groupBy('e.id', 'e.name', 'e.employee_code', 'v.vehicle_number')
            ->orderByDesc('total_sales')
            ->get();

        // Calculate gross profit for each salesman
        foreach ($salesmanPerformance as $salesman) {
            $cogs = DB::table('sales_settlement_items as ssi')
                ->join('sales_settlements as ss', 'ssi.sales_settlement_id', '=', 'ss.id')
                ->where('ss.employee_id', $salesman->employee_id)
                ->where('ss.status', 'posted')
                ->whereBetween('ss.settlement_date', [$startDate, $endDate])
                ->sum('ssi.total_cogs');

            $salesman->total_cogs = $cogs;
            $salesman->gross_profit = $salesman->total_sales - $cogs;
            $salesman->gross_profit_margin = $salesman->total_sales > 0
                ? ($salesman->gross_profit / $salesman->total_sales) * 100
                : 0;
        }

        // Calculate totals
        $totals = [
            'settlement_count' => $salesmanPerformance->sum('settlement_count'),
            'total_sales' => $salesmanPerformance->sum('total_sales'),
            'cash_sales' => $salesmanPerformance->sum('cash_sales'),
            'credit_sales' => $salesmanPerformance->sum('credit_sales'),
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
