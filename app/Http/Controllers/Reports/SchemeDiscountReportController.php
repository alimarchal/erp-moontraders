<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesSettlementExpense;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchemeDiscountReportController extends Controller
{
    /**
     * Account code for Scheme Discount Expense.
     */
    private const SCHEME_DISCOUNT_ACCOUNT_CODE = '5292';

    public function index(Request $request)
    {
        // Default to current month if no dates provided
        $startDate = $request->input('filter.start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('filter.end_date', now()->endOfMonth()->format('Y-m-d'));
        $supplierId = $request->input('filter.supplier_id');
        $sortOrder = $request->input('filter.sort_order', 'name_asc');

        // Get salesman filter (supports multiple selection)
        $salesmanIds = $request->input('filter.salesman_ids', []);
        if (! is_array($salesmanIds)) {
            $salesmanIds = array_filter([$salesmanIds]);
        }
        $salesmanIds = array_filter($salesmanIds);

        // Get all suppliers for filter
        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        // Get all salesmen for the dropdown
        // Filter by supplier if selected
        $salesmenQuery = Employee::query()
            ->whereHas('salesSettlements', function ($query) {
                $query->where('status', 'posted');
            });

        if ($supplierId) {
            $salesmenQuery->where('supplier_id', $supplierId);
        }

        $salesmen = $salesmenQuery->orderBy('name')->get(['id', 'name']);

        // Fetch Scheme Discount Expenses (Fixed Amount)
        $expenses = SalesSettlementExpense::query()
            ->join('sales_settlements', 'sales_settlements.id', '=', 'sales_settlement_expenses.sales_settlement_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'sales_settlement_expenses.expense_account_id')
            ->join('employees', 'employees.id', '=', 'sales_settlements.employee_id')
            ->where('sales_settlements.status', 'posted')
            ->whereNull('sales_settlements.deleted_at')
            ->where('chart_of_accounts.account_code', self::SCHEME_DISCOUNT_ACCOUNT_CODE)
            ->whereBetween('sales_settlements.settlement_date', [$startDate, $endDate])
            ->select([
                'sales_settlements.settlement_date',
                'sales_settlements.employee_id',
                'employees.name as employee_name',
                'sales_settlement_expenses.amount',
            ]);

        if (! empty($salesmanIds)) {
            $expenses->whereIn('sales_settlements.employee_id', $salesmanIds);
        }

        if ($supplierId) {
            $expenses->where('employees.supplier_id', $supplierId);
        }

        $expenses = $expenses->get();

        // Process data for the matrix report
        // Matrix: Rows = Dates, Columns = Salesmen

        // 1. Get all unique dates in the range
        $dates = collect();
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($start->lte($end)) {
            $dates->push($start->format('Y-m-d'));
            $start->addDay();
        }

        // 2. Identify unique salesmen for the report rows
        if ($supplierId) {
            // SCENARIO 1: Supplier Selected
            // User requested to show ALL employees associated with this supplier,
            // regardless of whether they have data in this date range.
            $reportSalesmen = Employee::where('supplier_id', $supplierId)
                ->orderBy('name')
                ->get(['id', 'name', 'supplier_id']);

        } elseif (! empty($salesmanIds)) {
            // SCENARIO 2: Specific Salesmen Selected
            // Show all selected salesmen, even if they have no data.
            $reportSalesmen = Employee::whereIn('id', $salesmanIds)
                ->orderBy('name')
                ->get(['id', 'name', 'supplier_id']);

        } else {
            // SCENARIO 3: No specific filter
            // Default behavior: Show only employees who have activity in this period
            // to avoid listing hundreds of inactive employees.
            $reportSalesmenIds = $expenses->pluck('employee_id')->unique();
            $reportSalesmen = Employee::whereIn('id', $reportSalesmenIds)
                ->orderBy('name')
                ->get(['id', 'name', 'supplier_id']);
        }

        // 3. Build Matrix Data
        // Structure: $matrix[employee_id][date] = amount (Inverted per user request)
        $matrix = [];
        $dateTotals = [];
        $salesmanTotals = [];
        $grandTotal = 0;

        // Initialize totals and matrix
        foreach ($dates as $date) {
            $dateTotals[$date] = 0;
        }
        foreach ($reportSalesmen as $salesman) {
            $salesmanTotals[$salesman->id] = 0;
            // Initialize each date for each salesman to ensure 0s are present if needed,
            // though keeping it sparse or checking isset in view is also fine.
            // Initializing helps with 0 display.
            foreach ($dates as $date) {
                $matrix[$salesman->id][$date] = 0;
            }
        }

        // Populate matrix
        foreach ($expenses as $expense) {
            // Since we used join and select, eloquent casting doesn't apply to `settlement_date`
            // It's returned as a string from DB, so we extract the date portion
            $date = substr($expense->settlement_date, 0, 10);

            $empId = $expense->employee_id;
            $amount = (float) $expense->amount;

            // Update Matrix (Rows: Salesman, Cols: Date)
            // Ensure the salesman is in our report scope (it should be if logic is correct)
            if (isset($matrix[$empId])) {
                $matrix[$empId][$date] += $amount;
            }

            // Update totals
            if (isset($dateTotals[$date])) {
                $dateTotals[$date] += $amount;
            }

            if (isset($salesmanTotals[$empId])) {
                $salesmanTotals[$empId] += $amount;
            }

            $grandTotal += $amount;
        }

        // Sort reportSalesmen based on configuration
        if ($sortOrder === 'high_to_low') {
            $reportSalesmen = $reportSalesmen->sortByDesc(function ($s) use ($salesmanTotals) {
                return $salesmanTotals[$s->id] ?? 0;
            });
        } elseif ($sortOrder === 'low_to_high') {
            $reportSalesmen = $reportSalesmen->sortBy(function ($s) use ($salesmanTotals) {
                return $salesmanTotals[$s->id] ?? 0;
            });
        } else {
            // Default name asc
            $reportSalesmen = $reportSalesmen->sortBy('name');
        }

        // Selected Salesman Names for display
        $selectedSalesmanNames = ! empty($salesmanIds)
            ? $salesmen->whereIn('id', $salesmanIds)->pluck('name')->implode(', ')
            : 'All Salesmen';

        return view('reports.scheme-discount.index', [
            'dates' => $dates,
            'reportSalesmen' => $reportSalesmen,
            'matrix' => $matrix,
            'dateTotals' => $dateTotals,
            'salesmanTotals' => $salesmanTotals,
            'grandTotal' => $grandTotal,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'salesmen' => $salesmen,
            'selectedSalesmanIds' => $salesmanIds,
            'selectedSalesmanNames' => $selectedSalesmanNames,
            'suppliers' => $suppliers,
            'selectedSupplierId' => $supplierId,
            'sortOrder' => $sortOrder,
        ]);
    }
}
