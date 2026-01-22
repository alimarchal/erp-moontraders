<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditSalesReportController extends Controller
{
    public function salesmanCreditHistory(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 250]) ? $perPage : 50;

        $query = Employee::query()
            ->select('employees.*')
            ->selectSub(function ($query) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COUNT(*)');
            }, 'credit_sales_count')
            ->selectSub(function ($query) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0)');
            }, 'credit_sales_sum_sale_amount')
            ->selectSub(function ($query) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'recovery')
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.credit), 0)');
            }, 'recoveries_sum_amount')
            ->selectSub(function ($query) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COUNT(DISTINCT cea.customer_id)');
            }, 'customers_count')
            ->with('supplier')
            ->havingRaw('credit_sales_count > 0');

        // Filters
        if ($request->filled('filter.name')) {
            $query->where('name', 'like', '%'.$request->input('filter.name').'%');
        }

        if ($request->filled('filter.employee_code')) {
            $query->where('employee_code', 'like', '%'.$request->input('filter.employee_code').'%');
        }

        if ($request->filled('filter.supplier_id')) {
            $query->where('supplier_id', $request->input('filter.supplier_id'));
        }

        if ($request->filled('filter.balance_min')) {
            $query->havingRaw('(credit_sales_sum_sale_amount - recoveries_sum_amount) >= ?', [$request->input('filter.balance_min')]);
        }

        if ($request->filled('filter.balance_max')) {
            $query->havingRaw('(credit_sales_sum_sale_amount - recoveries_sum_amount) <= ?', [$request->input('filter.balance_max')]);
        }

        // Sorting
        $sort = $request->input('sort', '-credit_sales');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        switch ($column) {
            case 'credit_sales':
                $query->orderBy('credit_sales_sum_sale_amount', $direction);
                break;
            case 'balance':
                $query->orderByRaw('(credit_sales_sum_sale_amount - recoveries_sum_amount) '.$direction);
                break;
            case 'name':
                $query->orderBy('name', $direction);
                break;
            case 'customers_count':
                $query->orderBy('customers_count', $direction);
                break;
            case 'sales_count':
                $query->orderBy('credit_sales_count', $direction);
                break;
            default:
                $query->orderBy('credit_sales_sum_sale_amount', 'desc');
        }

        $salesmenWithCredits = $query->paginate($perPage)->withQueryString();

        // Calculate totals
        $totals = DB::table('customer_employee_account_transactions as ceat')
            ->whereNull('ceat.deleted_at')
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'credit_sale' THEN ceat.debit ELSE 0 END) as total_credit_sales")
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'recovery' THEN ceat.credit ELSE 0 END) as total_recoveries")
            ->first();

        $suppliers = Supplier::whereHas('employees', function ($q) {
            $q->whereHas('customerAccounts.transactions', function ($q2) {
                $q2->where('transaction_type', 'credit_sale');
            });
        })->orderBy('supplier_name')->get();

        return view('reports.credit-sales.salesman-history', [
            'salesmen' => $salesmenWithCredits,
            'totals' => $totals,
            'suppliers' => $suppliers,
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

    public function customerCreditHistory(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 250]) ? $perPage : 50;

        $query = Customer::query()
            ->select('customers.*')
            ->selectSub(function ($query) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.customer_id', 'customers.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COUNT(*)');
            }, 'credit_sales_count')
            ->selectSub(function ($query) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.customer_id', 'customers.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0)');
            }, 'credit_sales_sum_sale_amount')
            ->selectSub(function ($query) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.customer_id', 'customers.id')
                    ->where('ceat.transaction_type', 'recovery')
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.credit), 0)');
            }, 'recoveries_sum_amount')
            ->havingRaw('credit_sales_count > 0');

        // Filters
        if ($request->filled('filter.customer_name')) {
            $query->where('customer_name', 'like', '%'.$request->input('filter.customer_name').'%');
        }

        if ($request->filled('filter.customer_code')) {
            $query->where('customer_code', 'like', '%'.$request->input('filter.customer_code').'%');
        }

        if ($request->filled('filter.business_name')) {
            $query->where('business_name', 'like', '%'.$request->input('filter.business_name').'%');
        }

        if ($request->filled('filter.phone')) {
            $query->where('phone', 'like', '%'.$request->input('filter.phone').'%');
        }

        if ($request->filled('filter.city')) {
            $query->where('city', $request->input('filter.city'));
        }

        if ($request->filled('filter.channel_type')) {
            $query->where('channel_type', $request->input('filter.channel_type'));
        }

        // Sorting
        $sort = $request->input('sort', '-credit_sales');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        switch ($column) {
            case 'credit_sales':
                $query->orderBy('credit_sales_sum_sale_amount', $direction);
                break;
            case 'balance':
                $query->orderByRaw('(credit_sales_sum_sale_amount - recoveries_sum_amount) '.$direction);
                break;
            case 'customer_name':
                $query->orderBy('customer_name', $direction);
                break;
            case 'sales_count':
                $query->orderBy('credit_sales_count', $direction);
                break;
            default:
                $query->orderBy('credit_sales_sum_sale_amount', 'desc');
        }

        $customersWithCredits = $query->paginate($perPage)->withQueryString();

        // Calculate totals
        $totals = DB::table('customer_employee_account_transactions as ceat')
            ->whereNull('ceat.deleted_at')
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'credit_sale' THEN ceat.debit ELSE 0 END) as total_credit_sales")
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'recovery' THEN ceat.credit ELSE 0 END) as total_recoveries")
            ->first();

        $cities = Customer::whereNotNull('city')->distinct()->pluck('city')->sort();
        $channelTypes = Customer::whereNotNull('channel_type')->distinct()->pluck('channel_type')->sort();

        return view('reports.credit-sales.customer-history', [
            'customers' => $customersWithCredits,
            'totals' => $totals,
            'cities' => $cities,
            'channelTypes' => $channelTypes,
        ]);
    }

    public function customerCreditDetails(Request $request, Customer $customer)
    {
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 250]) ? $perPage : 50;

        $creditSalesQuery = CustomerEmployeeAccountTransaction::query()
            ->select('customer_employee_account_transactions.*', 'cea.employee_id', 'ss.settlement_number')
            ->join('customer_employee_accounts as cea', 'customer_employee_account_transactions.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('sales_settlements as ss', 'customer_employee_account_transactions.sales_settlement_id', '=', 'ss.id')
            ->where('cea.customer_id', $customer->id)
            ->where('customer_employee_account_transactions.transaction_type', 'credit_sale')
            ->with(['account.employee.supplier', 'salesSettlement']);

        // Filters
        if ($request->filled('filter.date_from')) {
            $creditSalesQuery->whereDate('customer_employee_account_transactions.transaction_date', '>=', $request->input('filter.date_from'));
        }

        if ($request->filled('filter.date_to')) {
            $creditSalesQuery->whereDate('customer_employee_account_transactions.transaction_date', '<=', $request->input('filter.date_to'));
        }

        if ($request->filled('filter.employee_id')) {
            $creditSalesQuery->where('cea.employee_id', $request->input('filter.employee_id'));
        }

        $creditSales = $creditSalesQuery
            ->orderBy('customer_employee_account_transactions.transaction_date', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $salesmenBreakdown = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->join('employees as e', 'cea.employee_id', '=', 'e.id')
            ->leftJoin('suppliers as s', 'e.supplier_id', '=', 's.id')
            ->where('cea.customer_id', $customer->id)
            ->whereIn('ceat.transaction_type', ['credit_sale', 'recovery'])
            ->whereNull('ceat.deleted_at')
            ->select(
                'cea.employee_id',
                'e.name as employee_name',
                'e.employee_code',
                's.id as supplier_id',
                's.supplier_name',
                's.short_name'
            )
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'credit_sale' THEN ceat.debit ELSE 0 END) as total_credit_sales")
            ->selectRaw("SUM(CASE WHEN ceat.transaction_type = 'recovery' THEN ceat.credit ELSE 0 END) as total_recoveries")
            ->selectRaw("COUNT(CASE WHEN ceat.transaction_type = 'credit_sale' THEN 1 END) as sales_count")
            ->groupBy('cea.employee_id', 'e.name', 'e.employee_code', 's.id', 's.supplier_name', 's.short_name')
            ->orderByDesc('total_credit_sales')
            ->get();

        // Get employees for filter dropdown
        $employees = Employee::whereHas('customerAccounts', function ($q) use ($customer) {
            $q->where('customer_id', $customer->id);
        })->orderBy('name')->get();

        // Calculate summary
        $totalCreditSales = $salesmenBreakdown->sum('total_credit_sales');
        $totalRecoveries = $salesmenBreakdown->sum('total_recoveries');

        $summary = [
            'total_credit_sales' => $totalCreditSales,
            'total_recoveries' => $totalRecoveries,
            'balance' => $totalCreditSales - $totalRecoveries,
        ];

        return view('reports.credit-sales.customer-details', [
            'customer' => $customer,
            'creditSales' => $creditSales,
            'salesmenBreakdown' => $salesmenBreakdown,
            'employees' => $employees,
            'summary' => $summary,
        ]);
    }
}
