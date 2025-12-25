<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditSalesReportController extends Controller
{
    public function salesmanCreditHistory(Request $request)
    {
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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $salesmenWithCredits = $query->orderBy('credit_sales_sum_sale_amount', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('reports.credit-sales.salesman-history', [
            'salesmen' => $salesmenWithCredits,
        ]);
    }

    public function salesmanCreditDetails(Request $request, Employee $employee)
    {
        $customerId = $request->input('customer_id');

        $creditSales = CustomerEmployeeAccountTransaction::query()
            ->select('customer_employee_account_transactions.*', 'cea.customer_id', 'ss.settlement_number')
            ->join('customer_employee_accounts as cea', 'customer_employee_account_transactions.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('sales_settlements as ss', 'customer_employee_account_transactions.sales_settlement_id', '=', 'ss.id')
            ->where('cea.employee_id', $employee->id)
            ->where('customer_employee_account_transactions.transaction_type', 'credit_sale')
            ->when($customerId, function ($query, $customerId) {
                $query->where('cea.customer_id', $customerId);
            })
            ->with(['account.customer', 'salesSettlement'])
            ->orderBy('customer_employee_account_transactions.transaction_date', 'desc')
            ->paginate(50);

        $customerSummaries = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->join('customers as c', 'cea.customer_id', '=', 'c.id')
            ->where('cea.employee_id', $employee->id)
            ->where('ceat.transaction_type', 'credit_sale')
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

        return view('reports.credit-sales.salesman-details', [
            'employee' => $employee->load('supplier'),
            'creditSales' => $creditSales,
            'customerSummaries' => $customerSummaries,
            'selectedCustomerId' => $customerId,
        ]);
    }

    public function customerCreditHistory(Request $request)
    {
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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        $customersWithCredits = $query->orderBy('credit_sales_sum_sale_amount', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('reports.credit-sales.customer-history', [
            'customers' => $customersWithCredits,
        ]);
    }

    public function customerCreditDetails(Customer $customer)
    {
        $creditSales = CustomerEmployeeAccountTransaction::query()
            ->select('customer_employee_account_transactions.*', 'cea.employee_id', 'ss.settlement_number')
            ->join('customer_employee_accounts as cea', 'customer_employee_account_transactions.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('sales_settlements as ss', 'customer_employee_account_transactions.sales_settlement_id', '=', 'ss.id')
            ->where('cea.customer_id', $customer->id)
            ->where('customer_employee_account_transactions.transaction_type', 'credit_sale')
            ->with(['account.employee', 'salesSettlement'])
            ->orderBy('customer_employee_account_transactions.transaction_date', 'desc')
            ->paginate(50);

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

        return view('reports.credit-sales.customer-details', [
            'customer' => $customer,
            'creditSales' => $creditSales,
            'salesmenBreakdown' => $salesmenBreakdown,
        ]);
    }
}
