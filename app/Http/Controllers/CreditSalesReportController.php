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

    public function salesmanCreditDetails(Employee $employee)
    {
        $creditSales = CustomerEmployeeAccountTransaction::query()
            ->select('ceat.*', 'cea.customer_id', 'ss.settlement_number')
            ->from('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('sales_settlements as ss', 'ceat.sales_settlement_id', '=', 'ss.id')
            ->where('cea.employee_id', $employee->id)
            ->where('ceat.transaction_type', 'credit_sale')
            ->with(['account.customer', 'salesSettlement'])
            ->orderBy('ceat.transaction_date', 'desc')
            ->paginate(50);

        return view('reports.credit-sales.salesman-details', [
            'employee' => $employee->load('supplier'),
            'creditSales' => $creditSales,
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
            ->select('ceat.*', 'cea.employee_id', 'ss.settlement_number')
            ->from('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->leftJoin('sales_settlements as ss', 'ceat.sales_settlement_id', '=', 'ss.id')
            ->where('cea.customer_id', $customer->id)
            ->where('ceat.transaction_type', 'credit_sale')
            ->with(['account.employee', 'salesSettlement'])
            ->orderBy('ceat.transaction_date', 'desc')
            ->paginate(50);

        $salesmenBreakdown = DB::table('customer_employee_account_transactions as ceat')
            ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->join('employees as e', 'cea.employee_id', '=', 'e.id')
            ->leftJoin('suppliers as s', 'e.supplier_id', '=', 's.id')
            ->where('cea.customer_id', $customer->id)
            ->where('ceat.transaction_type', 'credit_sale')
            ->whereNull('ceat.deleted_at')
            ->select('cea.employee_id', 'e.name as employee_name', 's.id as supplier_id', 's.name as supplier_name')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('SUM(ceat.debit) as total_amount')
            ->groupBy('cea.employee_id', 'e.name', 's.id', 's.name')
            ->orderByDesc('total_amount')
            ->get();

        return view('reports.credit-sales.customer-details', [
            'customer' => $customer,
            'creditSales' => $creditSales,
            'salesmenBreakdown' => $salesmenBreakdown,
        ]);
    }
}
