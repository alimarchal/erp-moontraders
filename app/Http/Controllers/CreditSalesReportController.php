<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class CreditSalesReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-sales-credit-sales'),
        ];
    }

    public function salesmanCreditHistory(Request $request)
    {
        // Default date range: current month
        if (! $request->has('filter.start_date')) {
            $request->merge([
                'filter' => array_merge($request->input('filter', []), [
                    'start_date' => now()->startOfMonth()->format('Y-m-d'),
                    'end_date' => now()->format('Y-m-d'),
                ]),
            ]);
        }

        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');
        $employeeIds = $request->input('filter.employee_ids', []);
        if (! is_array($employeeIds)) {
            $employeeIds = array_filter([$employeeIds]);
        }
        $employeeIds = array_filter($employeeIds);

        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 250]) ? $perPage : 50;

        $query = Employee::query()
            ->select('employees.*')
            // Opening Balance: sum of (debit - credit) for all transactions BEFORE start_date,
            // PLUS any 'opening_balance' type transactions within the period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->where('ceat.transaction_date', '<', $startDate)
                            ->orWhere(function ($q2) use ($startDate, $endDate) {
                                $q2->where('ceat.transaction_type', 'opening_balance')
                                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate]);
                            });
                    })
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0)');
            }, 'opening_balance')
            // Credit Sales in period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate])
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0)');
            }, 'credit_sales_amount')
            // Recoveries in period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'recovery')
                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate])
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.credit), 0)');
            }, 'recoveries_amount')
            // Credit Sales Count in period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate])
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COUNT(*)');
            }, 'credit_sales_count')
            // Unique Customers count in period
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereBetween('ceat.transaction_date', [$startDate, $endDate])
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COUNT(DISTINCT cea.customer_id)');
            }, 'customers_count');

        // Filter by supplier
        if ($request->filled('filter.supplier_id')) {
            $query->where('supplier_id', $request->input('filter.supplier_id'));
        }

        // Filter by designation
        if ($request->filled('filter.designation')) {
            $query->where('designation', $request->input('filter.designation'));
        }

        // Filter by selected employees
        if (! empty($employeeIds)) {
            $query->whereIn('employees.id', $employeeIds);
        }

        // Filter by name
        if ($request->filled('filter.name')) {
            $query->where('name', 'like', '%'.$request->input('filter.name').'%');
        }

        // Filter by employee code
        if ($request->filled('filter.employee_code')) {
            $query->where('employee_code', 'like', '%'.$request->input('filter.employee_code').'%');
        }

        // Show all salesmen who have ANY transactions (opening_balance, credit_sale, recovery, etc.)
        // so page totals match grand totals in summary cards
        if (
            ! $request->filled('filter.supplier_id') &&
            ! $request->filled('filter.designation') &&
            empty($employeeIds) &&
            ! $request->filled('filter.name') &&
            ! $request->filled('filter.employee_code')
        ) {
            $query->whereExists(function ($query) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->whereNull('ceat.deleted_at');
            });
        }

        // Sorting
        $sort = $request->input('sort', '-closing_balance');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        switch ($column) {
            case 'credit_sales':
                $query->orderByRaw('credit_sales_amount '.$direction);
                break;
            case 'recoveries':
                $query->orderByRaw('recoveries_amount '.$direction);
                break;
            case 'opening_balance':
                $query->orderByRaw('opening_balance '.$direction);
                break;
            case 'closing_balance':
                $query->orderByRaw('(opening_balance + credit_sales_amount - recoveries_amount) '.$direction);
                break;
            case 'name':
                $query->orderBy('name', $direction);
                break;
            case 'customers_count':
                $query->orderByRaw('customers_count '.$direction);
                break;
            case 'sales_count':
                $query->orderByRaw('credit_sales_count '.$direction);
                break;
            default:
                $query->orderByRaw('(opening_balance + credit_sales_amount - recoveries_amount) desc');
        }

        $salesmenWithCredits = $query->paginate($perPage)->withQueryString();

        // Calculate totals for period
        // Note: Totals calculation currently sums EVERYTHING in the DB. This might need to be filtered by the same criteria if we want "Page Totals" vs "Grand Totals".
        // For now, keeping it as "Grand Total of all transactions" or adapting to filter?
        // The original logic seemed to sum everything. Let's keep it consistent unless requested.

        $totals = DB::table('customer_employee_account_transactions as ceat')
            ->whereNull('ceat.deleted_at')
            ->selectRaw('SUM(CASE WHEN ceat.transaction_date < ? OR (ceat.transaction_type = \'opening_balance\' AND ceat.transaction_date BETWEEN ? AND ?) THEN (ceat.debit - ceat.credit) ELSE 0 END) as total_opening_balance', [$startDate, $startDate, $endDate])
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'credit_sale' AND ceat.transaction_date BETWEEN ? AND ? THEN ceat.debit ELSE 0 END) as total_credit_sales", [$startDate, $endDate])
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'recovery' AND ceat.transaction_date BETWEEN ? AND ? THEN ceat.credit ELSE 0 END) as total_recoveries", [$startDate, $endDate])
            ->first();

        // Get all active employees for multi-select filter
        $employeesQuery = Employee::where('is_active', true)
            ->orderBy('name');

        if ($request->filled('filter.supplier_id')) {
            $employeesQuery->where('supplier_id', $request->input('filter.supplier_id'));
        }

        if ($request->filled('filter.designation')) {
            $employeesQuery->where('designation', $request->input('filter.designation'));
        }

        $employees = $employeesQuery->get(['id', 'name', 'employee_code']);

        // Get all suppliers for filter
        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        // Get unique designations
        $designations = Employee::distinct()->whereNotNull('designation')->orderBy('designation')->pluck('designation');

        // Get selected employee names for display
        $selectedEmployeeNames = ! empty($employeeIds)
            ? $employees->whereIn('id', $employeeIds)->pluck('name')->implode(', ')
            : 'All Salesmen';

        $selectedSupplierName = $request->filled('filter.supplier_id')
            ? $suppliers->firstWhere('id', $request->input('filter.supplier_id'))?->supplier_name
            : null;

        return view('reports.credit-sales.salesman-history', [
            'salesmen' => $salesmenWithCredits,
            'totals' => $totals,
            'employees' => $employees,
            'suppliers' => $suppliers,
            'designations' => $designations, // Pass designations
            'selectedEmployeeIds' => $employeeIds,
            'selectedEmployeeNames' => $selectedEmployeeNames,
            'selectedSupplierId' => $request->input('filter.supplier_id'),
            'selectedDesignation' => $request->input('filter.designation'), // Pass selected designation
            'selectedSupplierName' => $selectedSupplierName,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function salesmanCreditDetails(Request $request, Employee $employee)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 250]) ? $perPage : 50;

        $creditSalesQuery = CustomerEmployeeAccountTransaction::query()
            ->select('customer_employee_account_transactions.*', 'cea.customer_id', 'ss.settlement_number')
            ->join('customer_employee_accounts as cea', 'customer_employee_account_transactions.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('sales_settlements as ss', 'customer_employee_account_transactions.sales_settlement_id', '=', 'ss.id')
            ->where('cea.employee_id', $employee->id)
            ->where('customer_employee_account_transactions.transaction_type', 'credit_sale')
            ->with(['account.customer', 'salesSettlement']);

        // Filters
        if ($request->filled('filter.customer_id')) {
            $creditSalesQuery->where('cea.customer_id', $request->input('filter.customer_id'));
        }

        if ($request->filled('filter.date_from')) {
            $creditSalesQuery->whereDate('customer_employee_account_transactions.transaction_date', '>=', $request->input('filter.date_from'));
        }

        if ($request->filled('filter.date_to')) {
            $creditSalesQuery->whereDate('customer_employee_account_transactions.transaction_date', '<=', $request->input('filter.date_to'));
        }

        $creditSales = $creditSalesQuery
            ->orderBy('customer_employee_account_transactions.transaction_date', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $customerSummaries = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->join('customers as c', 'cea.customer_id', '=', 'c.id')
            ->where('cea.employee_id', $employee->id)
            ->where('ceat.transaction_type', 'credit_sale')
            ->whereNull('ceat.deleted_at')
            ->select(
                'c.id as customer_id',
                'c.customer_name',
                'c.customer_code'
            )
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('SUM(ceat.debit) as total_amount')
            ->groupBy('c.id', 'c.customer_name', 'c.customer_code')
            ->orderByDesc('total_amount')
            ->get();

        // Get customers for filter dropdown
        $customers = Customer::whereHas('employeeAccounts', function ($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })->orderBy('customer_name')->get();

        // Calculate summary including recoveries
        $summaryData = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->where('cea.employee_id', $employee->id)
            ->whereIn('ceat.transaction_type', ['credit_sale', 'recovery'])
            ->whereNull('ceat.deleted_at')
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'credit_sale' THEN ceat.debit ELSE 0 END) as total_credit_sales")
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'recovery' THEN ceat.credit ELSE 0 END) as total_recoveries")
            ->first();

        $summary = [
            'total_credit_sales' => $summaryData->total_credit_sales ?? 0,
            'total_recoveries' => $summaryData->total_recoveries ?? 0,
            'balance' => ($summaryData->total_credit_sales ?? 0) - ($summaryData->total_recoveries ?? 0),
        ];

        return view('reports.credit-sales.salesman-details', [
            'employee' => $employee->load('supplier'),
            'creditSales' => $creditSales,
            'customerSummaries' => $customerSummaries,
            'customers' => $customers,
            'summary' => $summary,
        ]);
    }
}
