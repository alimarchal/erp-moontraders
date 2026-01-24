<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GoodsIssueReportController extends Controller
{
    public function index(Request $request)
    {
        // 1. Initial Setup & Validation
        if (!$request->has('filter.start_date')) {
            $request->merge([
                'filter' => array_merge($request->input('filter', []), [
                    'start_date' => now()->startOfMonth()->format('Y-m-d'),
                    'end_date' => now()->format('Y-m-d'),
                ])
            ]);
        }

        $startDate = \Carbon\Carbon::parse($request->input('filter.start_date'));
        $endDate = \Carbon\Carbon::parse($request->input('filter.end_date'));
        $employeeIds = $request->input('filter.employee_id');

        // 2. Fetch All Products
        $products = \App\Models\Product::with('category')
            ->where('is_active', true)
            ->get()
            ->sortBy(['category.name', 'product_name']);

        // 3. Fetch Goods Issue Items (Grouped by Product & Date)
        $goodsIssueItems = \App\Models\GoodsIssueItem::query()
            ->selectRaw('
                product_id, 
                goods_issues.issue_date as date, 
                SUM(quantity_issued) as total_qty, 
                SUM(goods_issue_items.total_value) as total_value,
                COUNT(goods_issues.id) as issue_count
            ')
            ->join('goods_issues', 'goods_issue_items.goods_issue_id', '=', 'goods_issues.id')
            ->whereBetween('goods_issues.issue_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->when($request->input('filter.status'), function ($query, $status) {
                $query->where('goods_issues.status', $status);
            }, function ($query) {
                $query->where('goods_issues.status', 'issued'); // Default to issued if not specified (or should we show all?)
                // Let's default to 'issued' to keep the report relevant to sales, unless user explicitly asks for something else or clears it?
                // Actually, if 'All Statuses' is selected (empty), we should probably show all or just issued. 
                // Given the columns (Profit/Sale), 'Issued' makes most sense as default.
                // But if user clears filter, they might expect all. The view options are "All", "Issued", "Draft".
                // If "All" (value ""), and we default to Issued, it's misleading. 
                // But let's stick to respecting the input if present.
            })
            ->when($request->input('filter.issue_number'), function ($query, $number) {
                $query->where('goods_issues.issue_number', 'like', "%{$number}%");
            })
            ->when($employeeIds, function ($query) use ($employeeIds) {
                $query->whereIn('goods_issues.employee_id', (array) $employeeIds);
            })
            // Filter by vehicle/warehouse if needed, but requirements emphasize Salesman wise
            ->when($request->input('filter.vehicle_id'), function ($query) use ($request) {
                $query->where('goods_issues.vehicle_id', $request->input('filter.vehicle_id'));
            })
            ->when($request->input('filter.warehouse_id'), function ($query) use ($request) {
                $query->where('goods_issues.warehouse_id', $request->input('filter.warehouse_id'));
            })
            ->groupBy('product_id', 'date')
            ->get()
            ->groupBy('product_id');

        // 4. Fetch Sales Settlement Items (Grouped by Product) for Totals
        // Note: We need to link settlements to the filtered scope (Date Range & Salesman)
        // Adjusting logic: Find settlements within the date range for the same salesman
        $settlementItems = \App\Models\SalesSettlementItem::query()
            ->selectRaw('
                product_id,
                SUM(quantity_sold) as total_sold,
                SUM(sales_settlement_items.total_sales_value) as total_sales,
                SUM(sales_settlement_items.total_cogs) as total_cogs
            ')
            ->join('sales_settlements', 'sales_settlement_items.sales_settlement_id', '=', 'sales_settlements.id')
            ->whereBetween('sales_settlements.settlement_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->when($employeeIds, function ($query) use ($employeeIds) {
                $query->whereIn('sales_settlements.employee_id', (array) $employeeIds);
            })
            ->when($request->input('filter.vehicle_id'), function ($query) use ($request) {
                $query->where('sales_settlements.vehicle_id', $request->input('filter.vehicle_id'));
            })
            ->when($request->input('filter.warehouse_id'), function ($query) use ($request) {
                $query->where('sales_settlements.warehouse_id', $request->input('filter.warehouse_id'));
            })
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // 5. Construct Matrix Data
        $matrixData = [
            'dates' => [],
            'products' => collect(),
            'grand_totals' => [
                'issued_qty' => 0,
                'issued_value' => 0,
                'sold_qty' => 0,
                'sale_amount' => 0,
                'cogs' => 0,
                'gross_profit' => 0,
                'profit' => 0,
            ]
        ];

        // Generate Date Columns
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            $matrixData['dates'][] = $date->format('Y-m-d');
        }

        // Calculate Global Totals for Allocation (Expenses / Sales)
        // We filter settlements by the expected settlement date range
        $globalFinancials = DB::table('sales_settlements')
            ->join('goods_issues', 'sales_settlements.goods_issue_id', '=', 'goods_issues.id')
            ->whereBetween('goods_issues.issue_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('goods_issues.status', 'issued') // Match report logic
            ->when($request->input('filter.status'), function ($query, $status) {
                $query->where('goods_issues.status', $status);
            })
            ->when($request->input('filter.issue_number'), function ($query, $number) {
                $query->where('goods_issues.issue_number', 'like', "%{$number}%");
            })
            ->when($employeeIds, function ($query) use ($employeeIds) {
                $query->whereIn('goods_issues.employee_id', (array) $employeeIds);
            })
            ->selectRaw('SUM(expenses_claimed) as total_expenses, SUM(total_sales_amount) as total_sales')
            ->first();

        $totalExpenses = $globalFinancials->total_expenses ?? 0;
        $totalSalesGlobal = $globalFinancials->total_sales ?? 0;
        $expenseRatio = $totalSalesGlobal > 0 ? ($totalExpenses / $totalSalesGlobal) : 0;

        $matrixData['grand_totals']['expenses'] = $totalExpenses;
        // Matrix Grand Totals for Profit/Net Profit will be summed from rows to ensure consistency

        // Build Product Rows
        foreach ($products as $product) {
            $giData = $goodsIssueItems->get($product->id, collect());
            $settlementData = $settlementItems->get($product->id);

            $dailyData = [];
            $rowTotalIssuedQty = 0;
            $rowTotalIssuedValue = 0;

            // Map GI data to dates
            foreach ($giData as $item) {
                $dailyData[$item->date] = [
                    'qty' => $item->total_qty,
                    'value' => $item->total_value,
                    'count' => $item->issue_count
                ];
                $rowTotalIssuedQty += $item->issue_count; // Summing counts as per user request
                $rowTotalIssuedValue += $item->total_value;
            }

            // Settlement Totals
            $rowTotalSold = $settlementData ? $settlementData->total_sold : 0;
            $rowTotalSale = $settlementData ? $settlementData->total_sales : 0;
            $rowTotalCogs = $settlementData ? $settlementData->total_cogs : 0;
            $rowGrossProfit = $settlementData ? ($settlementData->total_sales - $settlementData->total_cogs) : 0;

            // Allocate Expenses to calculate Net Profit
            $rowExpenses = $rowTotalSale * $expenseRatio;
            $rowNetProfit = $rowGrossProfit - $rowExpenses;

            // Calculate Avg Unit Cost (Weighted Average based on Issued)
            $actualQtyIssued = $giData->sum('total_qty');
            $avgUnitCost = $actualQtyIssued > 0 ? $rowTotalIssuedValue / $actualQtyIssued : 0;

            // Allow row if it has any activity (issue OR settlement) or if we want to show all products
            if ($rowTotalIssuedQty > 0 || $rowTotalSold > 0) {
                $matrixData['products']->push([
                    'product_id' => $product->id,
                    'product_code' => $product->product_code,
                    'product_name' => $product->product_name,
                    'category_name' => $product->category->name ?? 'Uncategorized',
                    'daily_data' => $dailyData,
                    'totals' => [
                        'total_issued_qty' => $rowTotalIssuedQty,
                        'total_issued_value' => $rowTotalIssuedValue,
                        'total_sold_qty' => $rowTotalSold,
                        'total_sale' => $rowTotalSale,
                        'total_cogs' => $rowTotalCogs,
                        'total_profit' => $rowNetProfit, // Saving NET PROFIT as the main profit for the column
                        'total_gross_profit' => $rowGrossProfit, // Keeping GP if needed
                        'avg_unit_cost' => $avgUnitCost,
                    ]
                ]);

                // Add to grand totals
                $matrixData['grand_totals']['issued_qty'] += $rowTotalIssuedQty;
                $matrixData['grand_totals']['issued_value'] += $rowTotalIssuedValue;
                $matrixData['grand_totals']['sold_qty'] += $rowTotalSold;
                $matrixData['grand_totals']['sale_amount'] += $rowTotalSale;
                $matrixData['grand_totals']['cogs'] += $rowTotalCogs;
                $matrixData['grand_totals']['gross_profit'] += $rowGrossProfit;
                $matrixData['grand_totals']['profit'] += $rowNetProfit; // Summing NET PROFIT
            }
        }

        // Final Net Profit is simply the sum of row net profits (which equals GP - Exp)
        // We can just assign the calculated profit to 'net_profit' as well for consistency in footer logic
        $matrixData['grand_totals']['net_profit'] = $matrixData['grand_totals']['profit'];

        // Fetch filter options (Same as before)
        $employees = Employee::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'employee_code as code']);

        $vehicles = Vehicle::where('is_active', true)->orderBy('registration_number', 'asc')->get(['id', 'registration_number']);
        $warehouses = Warehouse::orderBy('warehouse_name', 'asc')->get(['id', 'warehouse_name as name']);

        // Prepare Filter Summary
        $filterSummary = [];
        if ($employeeIds) {
            $ids = is_array($employeeIds) ? $employeeIds : [$employeeIds];
            $names = $employees->whereIn('id', $ids)->pluck('name')->implode(', ');
            $filterSummary[] = "Salesman: $names";
        }

        return view('reports.goods-issue.index', [
            'matrixData' => $matrixData,
            'employees' => $employees,
            'vehicles' => $vehicles,
            'warehouses' => $warehouses,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'filters' => $request->input('filter', []),
            'filterSummary' => implode(' | ', $filterSummary),
        ]);
    }
}
