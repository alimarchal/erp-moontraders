<?php

namespace App\Http\Controllers;

use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Http\Request;

class CreditSalesReportController extends Controller
{
    public function salesmanCreditHistory(Request $request)
    {
        $query = Employee::whereHas('creditSales')
            ->withCount('creditSales')
            ->withSum('creditSales', 'sale_amount')
            ->with('supplier');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
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
        $creditSales = CreditSale::where('employee_id', $employee->id)
            ->with(['customer', 'supplier', 'salesSettlement'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('reports.credit-sales.salesman-details', [
            'employee' => $employee->load('supplier'),
            'creditSales' => $creditSales,
        ]);
    }

    public function customerCreditHistory(Request $request)
    {
        $query = Customer::whereHas('creditSales')
            ->withCount('creditSales')
            ->withSum('creditSales', 'sale_amount');

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
