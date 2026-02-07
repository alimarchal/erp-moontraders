<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoiReportController extends Controller
{
    public function index(Request $request)
    {
        // 1. Initial Setup & Validation
        if (! $request->has('filter.start_date')) {
            $startDate = \Carbon\Carbon::now()->startOfMonth();
            $endDate = \Carbon\Carbon::now();
        } else {
            $startDate = \Carbon\Carbon::parse($request->input('filter.start_date'));
            $endDate = \Carbon\Carbon::parse($request->input('filter.end_date'));
        }
        $employeeIds = $request->input('filter.employee_id');
        $supplierId = $request->input('filter.supplier_id');

        // 2. Fetch All Products
        $products = \App\Models\Product::with('category')
            ->where('is_active', true)
            ->when($supplierId, function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            })
            ->get()
            ->sortBy(['category.name', 'product_name']);

        // 3. Fetch Sales Data (Settlement Items) for ROI
        // ROI Logic:
        // Transactions = Sales Counts (Daily Qty Sold)
        // IP (Issue Price) = Average Unit Cost from Settlements/Issues
        // TP (Trade Price) = Average Selling Price from Settlements
        // Margin = TP - IP
        // Profit = Margin * Sold Qty (or Total Sales - Total COGS from Settlement)

        $salesItems = \App\Models\SalesSettlementItem::query()
            ->selectRaw('
                product_id,
                sales_settlements.settlement_date as date,
                SUM(quantity_sold) as total_sold_qty,
                SUM(sales_settlement_items.total_sales_value) as total_sales,
                SUM(sales_settlement_items.total_cogs) as total_cogs
            ')
            ->join('sales_settlements', 'sales_settlement_items.sales_settlement_id', '=', 'sales_settlements.id')
            ->whereBetween('sales_settlements.settlement_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('sales_settlements.status', 'posted') // Only posted settlements usually count for ROI calculation
            ->when($request->input('filter.status'), function ($query, $status) {
                // If filter status is explicitly passed, use it, otherwise default to posted for ROI accuracy?
                // User said "all the filters similar to this", so maybe they want draft included too if selected.
                // But generally ROI is on finalized sales. Goods Issue report default was "issued".
                // Let's stick to respecting the filter if present, else default to posted.
                if ($status === 'draft' || $status === 'posted') {
                    $query->where('sales_settlements.status', $status);
                }
            }, function ($query) {
                $query->where('sales_settlements.status', 'posted');
            })
            ->when($employeeIds, function ($query) use ($employeeIds) {
                $query->whereIn('sales_settlements.employee_id', (array) $employeeIds);
            })
            ->when($request->input('filter.vehicle_id'), function ($query) use ($request) {
                $query->where('sales_settlements.vehicle_id', $request->input('filter.vehicle_id'));
            })
            ->when($request->input('filter.warehouse_id'), function ($query) use ($request) {
                $query->where('sales_settlements.warehouse_id', $request->input('filter.warehouse_id'));
            })
            ->when($request->input('filter.settlement_number'), function ($query) use ($request) {
                $query->where('sales_settlements.settlement_number', 'like', '%'.$request->input('filter.settlement_number').'%');
            })
            ->when($request->input('filter.product_id'), function ($query) use ($request) {
                $query->where('product_id', $request->input('filter.product_id'));
            })
            ->groupBy('product_id', 'date')
            ->get()
            ->groupBy('product_id');

        // Filter the products list itself if a product filter is active
        // This ensures we only iterate/show the selected product in the report rows
        if ($request->input('filter.product_id')) {
            $products = $products->where('id', $request->input('filter.product_id'));
        }

        // 4. Construct Matrix Data
        $matrixData = [
            'dates' => [],
            'products' => collect(),
            'grand_totals' => [
                'sold_qty' => 0,
                'sale_amount' => 0,
                'cogs' => 0,
                'gross_profit' => 0, // Margin Total
                'expenses' => 0,
                'net_profit' => 0,
            ],
        ];

        // Generate Date Columns
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            $matrixData['dates'][] = $date->format('Y-m-d');
        }

        // Calculate Global Expenses Allocation (ROBUST METHOD)
        // We calculate global totals directly from detailed tables (items & expenses)
        // to avoid issues where sales_settlements.total_sales_amount/expenses_claimed might be zero/outdated.

        // 1. Calculate Global Sales & COGS from ITEMS
        $globalItemsStats = DB::table('sales_settlement_items')
            ->join('sales_settlements', 'sales_settlement_items.sales_settlement_id', '=', 'sales_settlements.id')
            ->whereBetween('sales_settlements.settlement_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->when($request->input('filter.status'), function ($query, $status) {
                if ($status === 'draft' || $status === 'posted') {
                    $query->where('sales_settlements.status', $status);
                }
            }, function ($query) {
                $query->where('sales_settlements.status', 'posted');
            })
            ->when($employeeIds, function ($query) use ($employeeIds) {
                $query->whereIn('sales_settlements.employee_id', (array) $employeeIds);
            })
            ->when($request->input('filter.vehicle_id'), function ($query) use ($request) {
                $query->where('sales_settlements.vehicle_id', $request->input('filter.vehicle_id'));
            })
            ->when($request->input('filter.warehouse_id'), function ($query) use ($request) {
                $query->where('sales_settlements.warehouse_id', $request->input('filter.warehouse_id'));
            })
            ->when($request->input('filter.settlement_number'), function ($query) use ($request) {
                $query->where('sales_settlements.settlement_number', 'like', '%'.$request->input('filter.settlement_number').'%');
            })
            ->selectRaw('
                SUM(sales_settlement_items.total_sales_value) as total_sales,
                SUM(sales_settlement_items.total_cogs) as total_cogs
            ')
            ->first();

        $totalSalesGlobal = $globalItemsStats->total_sales ?? 0;
        $totalCogsGlobal = $globalItemsStats->total_cogs ?? 0;

        // 2. Fetch Detailed All Expenses & Calculate Global Expenses Sum
        // Optimization: We fetch the breakdown first and sum it to get the global total, saving a query.
        $fetchedExpensesCollection = DB::table('sales_settlement_expenses')
            ->join('sales_settlements', 'sales_settlement_expenses.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('chart_of_accounts', 'sales_settlement_expenses.expense_account_id', '=', 'chart_of_accounts.id')
            ->whereBetween('sales_settlements.settlement_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->when($request->input('filter.status'), function ($query, $status) {
                if ($status === 'draft' || $status === 'posted') {
                    $query->where('sales_settlements.status', $status);
                }
            }, function ($query) {
                $query->where('sales_settlements.status', 'posted');
            })
            ->when($employeeIds, function ($query) use ($employeeIds) {
                $query->whereIn('sales_settlements.employee_id', (array) $employeeIds);
            })
            ->when($request->input('filter.vehicle_id'), function ($query) use ($request) {
                $query->where('sales_settlements.vehicle_id', $request->input('filter.vehicle_id'));
            })
            ->when($request->input('filter.warehouse_id'), function ($query) use ($request) {
                $query->where('sales_settlements.warehouse_id', $request->input('filter.warehouse_id'));
            })
            ->when($request->input('filter.settlement_number'), function ($query) use ($request) {
                $query->where('sales_settlements.settlement_number', 'like', '%'.$request->input('filter.settlement_number').'%');
            })
            ->selectRaw('
                chart_of_accounts.id as account_id,
                chart_of_accounts.account_code,
                chart_of_accounts.account_name,
                SUM(sales_settlement_expenses.amount) as total_amount
            ')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.account_code', 'chart_of_accounts.account_name')
            // Remove having > 0 check to ensure we catch even small aggregated amounts
            ->having('total_amount', '>', 0)
            ->orderBy('chart_of_accounts.account_code')
            ->get();

        // Calculate Global Expenses from the breakdown
        $totalExpensesGlobal = $fetchedExpensesCollection->sum('total_amount');

        // Use these robust values
        $totalExpenses = $totalExpensesGlobal;
        // $totalSalesGlobal is already set above

        $expenseRatio = $totalSalesGlobal > 0 ? ($totalExpenses / $totalSalesGlobal) : 0;

        // Initialize Grand Totals Expenses Logic
        // We will calculate exact allocated expenses sum in the loop, so we start at 0.
        $matrixData['grand_totals']['expenses'] = 0;

        foreach ($products as $product) {
            $salesData = $salesItems->get($product->id, collect());

            $dailyData = [];
            $rowTotalSoldQty = 0;
            $rowTotalSalesValue = 0;
            $rowTotalCogs = 0;

            foreach ($salesData as $item) {
                $dailyData[$item->date] = [
                    'qty' => $item->total_sold_qty, // Transaction Count = Sold Qty
                ];
                $rowTotalSoldQty += $item->total_sold_qty;
                $rowTotalSalesValue += $item->total_sales;
                $rowTotalCogs += $item->total_cogs;
            }

            // Calculate Averages (IP, TP)
            // IP = Average Cost = Total COGS / Total Qty Sold
            // TP = Average Price = Total Sales / Total Qty Sold

            if ($rowTotalSoldQty > 0) {
                $avgIp = $rowTotalCogs / $rowTotalSoldQty;
                $avgTp = $rowTotalSalesValue / $rowTotalSoldQty;
                $margin = $avgTp - $avgIp;
            } else {
                // If no sales, get current product cost/price as fallback or 0
                // For accurate report, if 0 sales, we can show 0 or master data.
                // Let's use 0 to avoid confusion if it wasn't sold.
                $avgIp = 0;
                $avgTp = 0;
                $margin = 0;
            }

            $rowGrossProfit = $rowTotalSalesValue - $rowTotalCogs;

            // Allocated Expenses
            // Use the GLOBAL Expense Ratio for allocation
            $rowExpenses = $rowTotalSalesValue * $expenseRatio;
            $rowNetProfit = $rowGrossProfit - $rowExpenses;

            if ($rowTotalSoldQty > 0) {
                $matrixData['products']->push([
                    'product_id' => $product->id,
                    'product_code' => $product->product_code,
                    'product_name' => $product->product_name,
                    'category_name' => $product->brand ?: '-', // Same as Goods Issue logic
                    'ip' => $avgIp,
                    'tp' => $avgTp,
                    'margin' => $margin,
                    'daily_data' => $dailyData,
                    'totals' => [
                        'total_sold_qty' => $rowTotalSoldQty,
                        'total_sale' => $rowTotalSalesValue,
                        'gross_profit' => $rowGrossProfit,
                        'expenses' => $rowExpenses,
                        'net_profit' => $rowNetProfit,
                    ],
                ]);

                $matrixData['grand_totals']['sold_qty'] += $rowTotalSoldQty;
                $matrixData['grand_totals']['sale_amount'] += $rowTotalSalesValue;
                $matrixData['grand_totals']['cogs'] += $rowTotalCogs;
                $matrixData['grand_totals']['gross_profit'] += $rowGrossProfit;
                $matrixData['grand_totals']['expenses'] += $rowExpenses; // Accumulate allocated expenses
                $matrixData['grand_totals']['net_profit'] += $rowNetProfit;
            }
        }

        // Calculate Allocation Factor
        $viewedSales = $matrixData['grand_totals']['sale_amount'];
        $allocationFactor = $totalSalesGlobal > 0 ? ($viewedSales / $totalSalesGlobal) : 0;

        // Define Predefined Expenses (Sequence matters)
        $predefinedExpenses = [
            ['id' => 73, 'label' => 'Toll Tax', 'code' => '5272'],
            ['id' => 71, 'label' => 'AMR Powder', 'code' => '5252'],
            ['id' => 72, 'label' => 'AMR Liquid', 'code' => '5262'],
            ['id' => 75, 'label' => 'Scheme Discount Expense', 'code' => '5292'],
            ['id' => 21, 'label' => 'Advance Tax', 'code' => '1161'],
            ['id' => 74, 'label' => 'Food/Salesman/Loader Charges', 'code' => '5282'],
            ['id' => 77, 'label' => 'Percentage Expense', 'code' => '5223'],
            ['id' => 59, 'label' => 'Miscellaneous Expenses', 'code' => '5221'],
        ];

        // Key fetched expenses by Account ID for easy lookup
        $fetchedExpenses = $fetchedExpensesCollection->keyBy('account_id');

        // Build Optimized Breakdown List
        $finalBreakdown = collect();

        // 1. Add Predefined Expenses (Defaults)
        foreach ($predefinedExpenses as $def) {
            $existing = $fetchedExpenses->get($def['id']);
            $amount = $existing ? $existing->total_amount : 0;

            // Remove from fetched list so we don't duplicate
            if ($existing) {
                $fetchedExpenses->forget($def['id']);
            }

            // Show ACTUAL Total Amount (As per user request) instead of allocated
            // The allocation factor is for the Financial Summary (Allocated Expenses line).
            // The breakdown list should show the actual incurred expenses for transparency.

            $finalBreakdown->push((object) [
                'account_code' => $def['code'],
                'account_name' => $def['label'],
                'total_amount' => $amount,
            ]);
        }

        // 2. Add Remaining (Extra) Expenses
        foreach ($fetchedExpenses as $extra) {
            $finalBreakdown->push((object) [
                'account_code' => $extra->account_code,
                'account_name' => $extra->account_name,
                'total_amount' => $extra->total_amount,
            ]);
        }

        $expenseBreakdown = $finalBreakdown;

        // Fetch Filter Options
        $employees = Employee::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'employee_code as code']);

        $vehicles = Vehicle::where('is_active', true)->orderBy('registration_number', 'asc')->get(['id', 'registration_number']);
        $warehouses = Warehouse::orderBy('warehouse_name', 'asc')->get(['id', 'warehouse_name as name']);

        // Fetch Suppliers for Filter
        $suppliers = \App\Models\Supplier::where('disabled', false)
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);

        // Fetch Product List for Filter
        $productList = \App\Models\Product::where('is_active', true)
            ->orderBy('product_name')
            ->get(['id', 'product_name', 'product_code']);

        // Prepare Filter Summary
        $filterSummary = [];

        // Date Range - Removed as requested (it's duplicated in header)
        // $filterSummary[] = "Period: " . $startDate->format('d-M-Y') . " to " . $endDate->format('d-M-Y');

        if ($request->input('filter.settlement_number')) {
            $filterSummary[] = 'Settlement #: '.$request->input('filter.settlement_number');
        }

        if ($employeeIds) {
            $ids = is_array($employeeIds) ? $employeeIds : [$employeeIds];
            $names = $employees->whereIn('id', $ids)->pluck('name')->implode(', ');
            $filterSummary[] = "Salesman: $names";
        }

        if ($request->input('filter.vehicle_id')) {
            $v = $vehicles->firstWhere('id', $request->input('filter.vehicle_id'));
            if ($v) {
                $filterSummary[] = "Vehicle: {$v->registration_number}";
            }
        }

        if ($request->input('filter.warehouse_id')) {
            $w = $warehouses->firstWhere('id', $request->input('filter.warehouse_id'));
            if ($w) {
                $filterSummary[] = "Warehouse: {$w->name}";
            }
        }

        if ($request->input('filter.status')) {
            $filterSummary[] = 'Status: '.ucfirst($request->input('filter.status'));
        }

        if ($request->input('filter.product_id')) {
            $p = $productList->firstWhere('id', $request->input('filter.product_id'));
            if ($p) {
                $filterSummary[] = "Product: {$p->product_name}";
            }
        }

        if ($request->input('filter.supplier_id')) {
            $s = $suppliers->firstWhere('id', $request->input('filter.supplier_id'));
            if ($s) {
                $filterSummary[] = "Supplier: {$s->supplier_name}";
            }
        }

        // Prepare Category Summary
        $categorySummary = $matrixData['products']->groupBy('category_name')->map(function ($products, $categoryName) use ($matrixData) {
            $catTotalSale = $products->sum('totals.total_sale');
            $catTotalProfit = $products->sum('totals.net_profit');
            $globalTotalSale = $matrixData['grand_totals']['sale_amount'];

            return [
                'name' => $categoryName,
                'total_sale' => $catTotalSale,
                'total_profit' => $catTotalProfit,
                'count' => $products->sum('totals.total_sold_qty'),
                'sales_share' => $globalTotalSale > 0 ? ($catTotalSale / $globalTotalSale) * 100 : 0,
                'profit_margin' => $catTotalSale > 0 ? ($catTotalProfit / $catTotalSale) * 100 : 0,
            ];
        })->sortByDesc('total_sale');

        return view('reports.roi.index', [
            'matrixData' => $matrixData,
            'employees' => $employees,
            'vehicles' => $vehicles,
            'warehouses' => $warehouses,
            'suppliers' => $suppliers,
            'productList' => $productList,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'filters' => $request->input('filter', []),
            'filterSummary' => implode(' | ', $filterSummary),
            'expenseBreakdown' => $expenseBreakdown,
            'categorySummary' => $categorySummary,
        ]);
    }
}
