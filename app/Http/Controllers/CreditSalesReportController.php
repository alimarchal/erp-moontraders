<?php

namespace App\Http\Controllers;

use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\Employee;

class CreditSalesReportController extends Controller
{
    public function salesmanCreditHistory()
    {
        $salesmenWithCredits = Employee::whereHas('creditSales')
            ->withCount('creditSales')
            ->withSum('creditSales', 'sale_amount')
            ->with('supplier')
            ->orderBy('credit_sales_sum_sale_amount', 'desc')
            ->get();

        return view('reports.credit-sales.salesman-history', [
            'salesmen' => $salesmenWithCredits,
        ]);
    }

    public function salesmanCreditDetails(Employee $employee)
    {
        $creditSales = CreditSale::where('employee_id', $employee->id)
            ->with(['customer', 'supplier', 'salesSettlement'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('reports.credit-sales.salesman-details', [
            'employee' => $employee->load('supplier'),
            'creditSales' => $creditSales,
        ]);
    }

    public function customerCreditHistory()
    {
        $customersWithCredits = Customer::whereHas('creditSales')
            ->withCount('creditSales')
            ->withSum('creditSales', 'sale_amount')
            ->orderBy('credit_sales_sum_sale_amount', 'desc')
            ->get();

        return view('reports.credit-sales.customer-history', [
            'customers' => $customersWithCredits,
        ]);
    }

    public function customerCreditDetails(Customer $customer)
    {
        $creditSales = CreditSale::where('customer_id', $customer->id)
            ->with(['employee', 'supplier', 'salesSettlement'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $salesmenBreakdown = CreditSale::where('customer_id', $customer->id)
            ->select('employee_id', 'supplier_id')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('SUM(sale_amount) as total_amount')
            ->with(['employee', 'supplier'])
            ->groupBy('employee_id', 'supplier_id')
            ->orderByDesc('total_amount')
            ->get();

        return view('reports.credit-sales.customer-details', [
            'customer' => $customer,
            'creditSales' => $creditSales,
            'salesmenBreakdown' => $salesmenBreakdown,
        ]);
    }
}
