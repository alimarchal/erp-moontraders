<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Employee;
use App\Models\ExpenseDetail;
use App\Models\LedgerRegister;
use App\Models\Product;
use App\Models\ProfitCategoryDetail;
use App\Models\RevenueDetail;
use App\Models\SchemeReceived;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SummaryRoiReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-summary-roi'),
        ];
    }

    public function index(Request $request): View
    {
        // Date range defaults: current month (full month)
        if (! $request->filled('filter.start_date')) {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
        } else {
            $startDate = Carbon::parse($request->input('filter.start_date'));
            $endDate = Carbon::parse($request->input('filter.end_date', Carbon::now()->endOfMonth()->format('Y-m-d')));
        }

        // Default to Nestlé Pakistan (id=3)
        $supplierId = $request->input('filter.supplier_id') ?: 3;
        $employeeIds = $request->input('filter.employee_id');

        $selectedSupplier = Supplier::query()
            ->select(['id', 'supplier_name', 'short_name'])
            ->find($supplierId);
        $supplierName = strtolower((string) ($selectedSupplier?->supplier_name ?? ''));
        $supplierShortName = strtolower((string) ($selectedSupplier?->short_name ?? ''));
        $isEngroSupplier = Str::contains($supplierName, 'engro') || Str::contains($supplierShortName, 'engro');
        $incentiveClaimed = $isEngroSupplier ? 208652.0 : 0.0;
        $expiryClaimed = $isEngroSupplier ? 260000.0 : 0.0;

        // ── Fetch all categories that have active products for this supplier ──
        $supplierCategoryIds = Product::where('is_active', true)
            ->where('supplier_id', $supplierId)
            ->pluck('category_id')
            ->unique();

        $categories = Category::whereIn('id', $supplierCategoryIds)
            ->orderBy('name')
            ->get();

        // ── Fetch sales data grouped by category ──
        $salesByCategory = DB::table('sales_settlement_items')
            ->join('sales_settlements', 'sales_settlement_items.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('products', 'sales_settlement_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('sales_settlements.settlement_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('sales_settlements.supplier_id', $supplierId)
            ->when($request->input('filter.status'), function ($q, $status) {
                if ($status === 'draft' || $status === 'posted') {
                    $q->where('sales_settlements.status', $status);
                }
            }, fn ($q) => $q->where('sales_settlements.status', 'posted'))
            ->when($employeeIds, fn ($q) => $q->whereIn('sales_settlements.employee_id', (array) $employeeIds))
            ->when($request->input('filter.vehicle_id'), fn ($q, $v) => $q->where('sales_settlements.vehicle_id', $v))
            ->when($request->input('filter.warehouse_id'), fn ($q, $w) => $q->where('sales_settlements.warehouse_id', $w))
            ->selectRaw('
                categories.id as category_id,
                categories.name as category_name,
                SUM(sales_settlement_items.total_sales_value) as total_sales,
                SUM(sales_settlement_items.total_cogs) as total_cogs
            ')
            ->groupBy('categories.id', 'categories.name')
            ->get()
            ->keyBy('category_id');

        // ── Global totals (for expense ratio allocation) ──
        $globalSales = $salesByCategory->sum('total_sales');
        $globalCogs = $salesByCategory->sum('total_cogs');

        // ── Fetch settlement expense breakdown (same basis as ROI report) ──
        $fetchedExpensesCollection = DB::table('sales_settlement_expenses')
            ->join('sales_settlements', 'sales_settlement_expenses.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('chart_of_accounts', 'sales_settlement_expenses.expense_account_id', '=', 'chart_of_accounts.id')
            ->whereBetween('sales_settlements.settlement_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('sales_settlements.supplier_id', $supplierId)
            ->when($request->input('filter.status'), function ($q, $status) {
                if ($status === 'draft' || $status === 'posted') {
                    $q->where('sales_settlements.status', $status);
                }
            }, fn ($q) => $q->where('sales_settlements.status', 'posted'))
            ->when($employeeIds, fn ($q) => $q->whereIn('sales_settlements.employee_id', (array) $employeeIds))
            ->when($request->input('filter.vehicle_id'), fn ($q, $v) => $q->where('sales_settlements.vehicle_id', $v))
            ->when($request->input('filter.warehouse_id'), fn ($q, $w) => $q->where('sales_settlements.warehouse_id', $w))
            ->selectRaw('
                chart_of_accounts.id as account_id,
                chart_of_accounts.account_code,
                chart_of_accounts.account_name,
                SUM(sales_settlement_expenses.amount) as total_amount
            ')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.account_code', 'chart_of_accounts.account_name')
            ->havingRaw('SUM(sales_settlement_expenses.amount) > 0')
            ->orderBy('chart_of_accounts.account_code')
            ->get();

        $allSettlementExpensesTotal = (float) $fetchedExpensesCollection->sum('total_amount');
        $expenseRatio = $globalSales > 0 ? ($allSettlementExpensesTotal / $globalSales) : 0;

        // Fetch excess amount (only for Engro)
        $excessAmount = 0.0;
        if ($isEngroSupplier) {
            $excessAmount = (float) DB::table('sales_settlement_excess_amounts')
                ->join('sales_settlements', 'sales_settlement_excess_amounts.sales_settlement_id', '=', 'sales_settlements.id')
                ->whereBetween('sales_settlements.settlement_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->where('sales_settlements.supplier_id', $supplierId)
                ->where('sales_settlements.status', 'posted')
                ->whereNull('sales_settlements.deleted_at')
                ->when($employeeIds, fn ($q) => $q->whereIn('sales_settlements.employee_id', (array) $employeeIds))
                ->when($request->input('filter.vehicle_id'), fn ($q, $v) => $q->where('sales_settlements.vehicle_id', $v))
                ->when($request->input('filter.warehouse_id'), fn ($q, $w) => $q->where('sales_settlements.warehouse_id', $w))
                ->sum('sales_settlement_excess_amounts.amount');
        }

        $predefinedExpenses = [
            ['code' => '5252', 'label' => 'AMR Powder'],
            ['code' => '5262', 'label' => 'AMR Liquid'],
            ['code' => '5292', 'label' => 'Scheme Discount Expense'],
            ['code' => '1161', 'label' => 'Advance Tax'],
            ['code' => '5223', 'label' => 'Discount to Trade'],
            ['code' => '5288', 'label' => 'Promotion Off'],
            ['code' => '5282', 'label' => 'Food/Salesman/Loader Charges'],
            ['code' => '5272', 'label' => 'Toll Tax / Labor'],
            ['code' => '5221', 'label' => 'Miscellaneous Expenses'],
            ['code' => '5210', 'label' => 'Administrative Expenses'],
            ['code' => '5293', 'label' => 'Short Amount'],
        ];

        $fetchedExpenses = $fetchedExpensesCollection->keyBy('account_code');

        if ($isEngroSupplier) {
            // Excess amount is adjusted into short amount for Engro and not shown separately.
            $fetchedExpenses->forget('4250');
        }

        $expenseBreakdown = collect();

        foreach ($predefinedExpenses as $expense) {
            $existing = $fetchedExpenses->get($expense['code']);

            $expenseBreakdown->push((object) [
                'account_code' => $expense['code'],
                'account_name' => $expense['label'],
                'total_amount' => $existing ? (float) $existing->total_amount : 0,
            ]);

            if ($existing) {
                $fetchedExpenses->forget($expense['code']);
            }
        }

        // Add any remaining expenses not in the predefined list
        foreach ($fetchedExpenses as $extra) {
            $expenseBreakdown->push((object) [
                'account_code' => $extra->account_code,
                'account_name' => $extra->account_name,
                'total_amount' => (float) $extra->total_amount,
            ]);
        }

        if ($isEngroSupplier) {
            $expenseBreakdown = $expenseBreakdown->reject(fn ($expense) => $expense->account_code === '4250')->values();

            $shortAmountExpense = $expenseBreakdown->firstWhere('account_code', '5293');
            if ($shortAmountExpense) {
                $originalShortAmount = (float) $shortAmountExpense->total_amount;
                $shortAmountExpense->total_amount = $excessAmount - $originalShortAmount;
                $shortAmountExpense->account_name = 'Excess Amount (4250) - Short Amount (5293) - (Excess: '.number_format($excessAmount, 2).', Short: '.number_format($originalShortAmount, 2).')';
            }
        }

        $otherOperatingExpensesTotal = (float) $expenseBreakdown->sum('total_amount');

        // ── Build category rows ──
        $categoryRows = collect();
        foreach ($categories as $category) {
            $data = $salesByCategory->get($category->id);
            $sale = $data ? (float) $data->total_sales : 0;
            $cogs = $data ? (float) $data->total_cogs : 0;
            $grossProfit = $sale - $cogs;
            $allocatedExpenses = $sale * $expenseRatio;
            $netProfit = $grossProfit - $allocatedExpenses;

            $categoryRows->push([
                'category_id' => $category->id,
                'category_name' => $category->name,
                'sale' => $sale,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'schema_received' => 0,
                'fmr_received' => 0,
                'cash_discount' => 0,
            ]);
        }

        $grandTotals = [
            'sale' => $categoryRows->sum('sale'),
            'cogs' => $categoryRows->sum('cogs'),
            'gross_profit' => $categoryRows->sum('gross_profit'),
            'net_profit' => $categoryRows->sum('net_profit'),
            'schema_received' => (float) SchemeReceived::where('supplier_id', $supplierId)
                ->whereBetween('transaction_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->sum('amount'),
            'fmr_received' => (float) DB::table('goods_receipt_note_items as grni')
                ->join('goods_receipt_notes as grn', 'grn.id', '=', 'grni.grn_id')
                ->where('grn.supplier_id', $supplierId)
                ->where('grn.status', 'posted')
                ->whereNull('grn.deleted_at')
                ->whereBetween('grn.receipt_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->sum('grni.fmr_allowance'),
            'cash_discount' => (float) LedgerRegister::where('supplier_id', $supplierId)
                ->whereBetween('transaction_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->sum('za_point_five_percent_amount'),
        ];

        $postedRevenueRows = RevenueDetail::query()
            ->join('revenue_categories', 'revenue_details.revenue_category_id', '=', 'revenue_categories.id')
            ->where(function ($query) use ($supplierId) {
                $query->where('revenue_details.supplier_id', $supplierId)
                    ->orWhere(function ($query) use ($supplierId) {
                        $query->whereNull('revenue_details.supplier_id')
                            ->where('revenue_categories.supplier_id', $supplierId);
                    });
            })
            ->whereNotNull('revenue_details.posted_at')
            ->whereNull('revenue_details.deleted_at')
            ->whereNull('revenue_categories.deleted_at')
            ->whereBetween('revenue_details.transaction_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw('
                revenue_categories.id as category_id,
                revenue_categories.name as category_name,
                SUM(revenue_details.amount) as total_amount
            ')
            ->groupBy('revenue_categories.id', 'revenue_categories.name')
            ->havingRaw('SUM(revenue_details.amount) > 0')
            ->orderBy('revenue_categories.name')
            ->get()
            ->map(fn ($row) => [
                'category_id' => (int) $row->category_id,
                'category_name' => $row->category_name,
                'amount' => (float) $row->total_amount,
            ]);
        $postedRevenueTotal = (float) $postedRevenueRows->sum('amount');
        $grossInflow = (float) ($grandTotals['sale'] + $grandTotals['schema_received'] + $grandTotals['fmr_received'] + $grandTotals['cash_discount'] + $incentiveClaimed + $expiryClaimed + $postedRevenueTotal);
        $grandRevenue = (float) ($grandTotals['gross_profit'] + $grandTotals['schema_received'] + $grandTotals['fmr_received'] + $grandTotals['cash_discount'] + $incentiveClaimed + $expiryClaimed + $postedRevenueTotal);

        // ── Distribution & Selling Expenses ──
        $allCategoryOptions = ExpenseDetail::categoryOptions();
        $distRaw = ExpenseDetail::query()
            ->whereBetween('transaction_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->select('category', 'description', 'amount')
            ->orderBy('category')
            ->orderBy('transaction_date')
            ->get()
            ->groupBy('category');

        $distributionExpenses = collect();
        foreach ($allCategoryOptions as $key => $label) {
            $records = $distRaw->get($key, collect());
            if ($records->isEmpty()) {
                $distributionExpenses->push([
                    'category' => $label,
                    'description' => '—',
                    'amount' => 0,
                ]);
            } else {
                foreach ($records as $rec) {
                    $distributionExpenses->push([
                        'category' => $label,
                        'description' => $rec->description ?: '—',
                        'amount' => (float) $rec->amount,
                    ]);
                }
            }
        }
        $distributionExpensesTotal = $distributionExpenses->sum('amount');

        $profitCategoryRows = ProfitCategoryDetail::query()
            ->join('profit_categories', 'profit_category_details.profit_category_id', '=', 'profit_categories.id')
            ->where('profit_category_details.supplier_id', $supplierId)
            ->whereNotNull('profit_category_details.posted_at')
            ->whereNull('profit_category_details.deleted_at')
            ->whereNull('profit_categories.deleted_at')
            ->whereBetween('profit_category_details.transaction_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw('
                profit_categories.id as category_id,
                profit_categories.name as category_name,
                SUM(profit_category_details.amount) as total_amount
            ')
            ->groupBy('profit_categories.id', 'profit_categories.name')
            ->havingRaw('SUM(profit_category_details.amount) > 0')
            ->orderBy('profit_categories.name')
            ->get()
            ->map(fn ($row) => [
                'category_id' => (int) $row->category_id,
                'category_name' => $row->category_name,
                'amount' => (float) $row->total_amount,
            ]);
        $profitCategoryTotal = (float) $profitCategoryRows->sum('amount');

        // ── Filter options ──
        $suppliers = Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']);
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_code as code']);
        $vehicles = Vehicle::where('is_active', true)->orderBy('registration_number')->get(['id', 'registration_number']);
        $warehouses = Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name as name']);

        // ── Active filters for badge display ──
        $filters = $request->input('filter', []);
        if (empty($filters['supplier_id'])) {
            $filters['supplier_id'] = 3;
        }

        $filterBadges = [];
        if (! empty($filters['supplier_id'])) {
            $s = $suppliers->firstWhere('id', $filters['supplier_id']);
            if ($s) {
                $filterBadges[] = "Supplier: {$s->supplier_name}";
            }
        }
        if ($employeeIds) {
            $ids = is_array($employeeIds) ? $employeeIds : [$employeeIds];
            $names = $employees->whereIn('id', $ids)->pluck('name')->implode(', ');
            $filterBadges[] = "Salesman: $names";
        }
        if ($request->input('filter.vehicle_id')) {
            $v = $vehicles->firstWhere('id', $request->input('filter.vehicle_id'));
            if ($v) {
                $filterBadges[] = "Vehicle: {$v->registration_number}";
            }
        }
        if ($request->input('filter.warehouse_id')) {
            $w = $warehouses->firstWhere('id', $request->input('filter.warehouse_id'));
            if ($w) {
                $filterBadges[] = "Warehouse: {$w->name}";
            }
        }
        if ($request->input('filter.status')) {
            $filterBadges[] = 'Status: '.ucfirst($request->input('filter.status'));
        }

        return view('reports.summary-roi.index', [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'filters' => $filters,
            'filterBadges' => $filterBadges,
            'suppliers' => $suppliers,
            'employees' => $employees,
            'vehicles' => $vehicles,
            'warehouses' => $warehouses,
            'categoryRows' => $categoryRows,
            'grandTotals' => $grandTotals,
            'expenseBreakdown' => $expenseBreakdown,
            'distributionExpenses' => $distributionExpenses,
            'distributionExpensesTotal' => $distributionExpensesTotal,
            'otherOperatingExpensesTotal' => $otherOperatingExpensesTotal,
            'incentiveClaimed' => $incentiveClaimed,
            'expiryClaimed' => $expiryClaimed,
            'postedRevenueRows' => $postedRevenueRows,
            'postedRevenueTotal' => $postedRevenueTotal,
            'profitCategoryRows' => $profitCategoryRows,
            'profitCategoryTotal' => $profitCategoryTotal,
            'grossInflow' => $grossInflow,
            'grandRevenue' => $grandRevenue,
        ]);
    }
}
