<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class SupplierLedgerReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-supplier-ledger'),
        ];
    }

    public function index(Request $request)
    {
        $today = now()->toDateString();
        $dateFrom = $request->input('filter.date_from', $today);
        $dateTo = $request->input('filter.date_to', $today);
        $supplierId = $request->input('filter.supplier_id');

        $openingBalances = DB::table('suppliers as s')
            ->join('employees as e', 'e.supplier_id', '=', 's.id')
            ->join('customer_employee_accounts as cea', 'cea.employee_id', '=', 'e.id')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->where('ceat.transaction_date', '<', $dateFrom)
            ->when($supplierId, fn ($q) => $q->where('s.id', $supplierId))
            ->selectRaw('s.id, COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as opening_balance')
            ->groupBy('s.id')
            ->get()
            ->keyBy('id');

        $periodTotals = DB::table('suppliers as s')
            ->join('employees as e', 'e.supplier_id', '=', 's.id')
            ->join('customer_employee_accounts as cea', 'cea.employee_id', '=', 'e.id')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->whereBetween('ceat.transaction_date', [$dateFrom, $dateTo])
            ->when($supplierId, fn ($q) => $q->where('s.id', $supplierId))
            ->selectRaw('
                s.id,
                COALESCE(SUM(ceat.debit), 0) as period_debit,
                COALESCE(SUM(ceat.credit), 0) as period_credit,
                COUNT(DISTINCT e.id) as employee_count
            ')
            ->groupBy('s.id')
            ->get()
            ->keyBy('id');

        $customerCounts = DB::table('suppliers as s')
            ->join('employees as e', 'e.supplier_id', '=', 's.id')
            ->join('customer_employee_accounts as cea', 'cea.employee_id', '=', 'e.id')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->where('ceat.transaction_date', '<=', $dateTo)
            ->when($supplierId, fn ($q) => $q->where('s.id', $supplierId))
            ->selectRaw('s.id, COUNT(DISTINCT cea.customer_id) as customer_count')
            ->groupBy('s.id')
            ->get()
            ->keyBy('id');

        $allSuppliers = Supplier::when($supplierId, fn ($q) => $q->where('id', $supplierId))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);

        $supplierRows = $allSuppliers->map(function ($supplier) use ($openingBalances, $periodTotals, $customerCounts) {
            $opening = isset($openingBalances[$supplier->id])
                ? (float) $openingBalances[$supplier->id]->opening_balance
                : 0.0;

            $period = $periodTotals[$supplier->id] ?? null;
            $debit = $period ? (float) $period->period_debit : 0.0;
            $credit = $period ? (float) $period->period_credit : 0.0;
            $customerCount = isset($customerCounts[$supplier->id]) ? (int) $customerCounts[$supplier->id]->customer_count : 0;
            $employeeCount = $period ? (int) $period->employee_count : 0;

            return (object) [
                'id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'opening_balance' => $opening,
                'period_debit' => $debit,
                'period_credit' => $credit,
                'closing_balance' => $opening + $debit - $credit,
                'customer_count' => $customerCount,
                'employee_count' => $employeeCount,
            ];
        });

        $totals = [
            'opening_balance' => $supplierRows->sum('opening_balance'),
            'period_debit' => $supplierRows->sum('period_debit'),
            'period_credit' => $supplierRows->sum('period_credit'),
            'closing_balance' => $supplierRows->sum('closing_balance'),
            'customer_count' => $supplierRows->sum('customer_count'),
        ];

        $suppliersList = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        return view('reports.supplier-ledger.index', [
            'supplierRows' => $supplierRows,
            'totals' => $totals,
            'suppliersList' => $suppliersList,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'supplierId' => $supplierId ? (int) $supplierId : null,
        ]);
    }

    /**
     * Supplier → Salesman drill-down
     */
    public function salesmen(Request $request, Supplier $supplier)
    {
        $today = now()->toDateString();
        $dateFrom = $request->input('filter.date_from', $today);
        $dateTo = $request->input('filter.date_to', $today);

        $openingBalances = DB::table('employees as e')
            ->join('customer_employee_accounts as cea', 'cea.employee_id', '=', 'e.id')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->where('e.supplier_id', $supplier->id)
            ->where('ceat.transaction_date', '<', $dateFrom)
            ->selectRaw('e.id, COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as opening_balance')
            ->groupBy('e.id')
            ->get()
            ->keyBy('id');

        $periodTotals = DB::table('employees as e')
            ->join('customer_employee_accounts as cea', 'cea.employee_id', '=', 'e.id')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->where('e.supplier_id', $supplier->id)
            ->whereBetween('ceat.transaction_date', [$dateFrom, $dateTo])
            ->selectRaw('
                e.id,
                COALESCE(SUM(ceat.debit), 0) as period_debit,
                COALESCE(SUM(ceat.credit), 0) as period_credit
            ')
            ->groupBy('e.id')
            ->get()
            ->keyBy('id');

        // Customer count = union of opening + period customers per employee
        $customerCounts = DB::table('employees as e')
            ->join('customer_employee_accounts as cea', 'cea.employee_id', '=', 'e.id')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->where('e.supplier_id', $supplier->id)
            ->where('ceat.transaction_date', '<=', $dateTo)
            ->selectRaw('e.id, COUNT(DISTINCT cea.customer_id) as customer_count')
            ->groupBy('e.id')
            ->get()
            ->keyBy('id');

        // Only show employees that have any transaction (opening or period)
        $activeEmployeeIds = $openingBalances->keys()
            ->merge($periodTotals->keys())
            ->unique();

        $activeEmployees = Employee::whereIn('id', $activeEmployeeIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $salesmenRows = $activeEmployees->map(function ($employee) use ($openingBalances, $periodTotals, $customerCounts) {
            $opening = isset($openingBalances[$employee->id])
                ? (float) $openingBalances[$employee->id]->opening_balance
                : 0.0;

            $period = $periodTotals[$employee->id] ?? null;
            $debit = $period ? (float) $period->period_debit : 0.0;
            $credit = $period ? (float) $period->period_credit : 0.0;
            $customerCount = isset($customerCounts[$employee->id])
                ? (int) $customerCounts[$employee->id]->customer_count
                : 0;

            return (object) [
                'id' => $employee->id,
                'name' => $employee->name,
                'opening_balance' => $opening,
                'period_debit' => $debit,
                'period_credit' => $credit,
                'closing_balance' => $opening + $debit - $credit,
                'customer_count' => $customerCount,
            ];
        });

        $uniqueCustomerCount = DB::table('customer_employee_accounts as cea')
            ->join('employees as e', 'e.id', '=', 'cea.employee_id')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->where('e.supplier_id', $supplier->id)
            ->where('ceat.transaction_date', '<=', $dateTo)
            ->distinct()
            ->count('cea.customer_id');

        $totals = [
            'opening_balance' => $salesmenRows->sum('opening_balance'),
            'period_debit' => $salesmenRows->sum('period_debit'),
            'period_credit' => $salesmenRows->sum('period_credit'),
            'closing_balance' => $salesmenRows->sum('closing_balance'),
            'customer_count' => $uniqueCustomerCount,
        ];

        return view('reports.supplier-ledger.salesmen', [
            'supplier' => $supplier,
            'salesmenRows' => $salesmenRows,
            'totals' => $totals,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    /**
     * Salesman → Customers drill-down (scoped to supplier)
     */
    public function customers(Request $request, Supplier $supplier, Employee $employee)
    {
        $today = now()->toDateString();
        $dateFrom = $request->input('filter.date_from', $today);
        $dateTo = $request->input('filter.date_to', $today);

        $openingBalances = DB::table('customer_employee_accounts as cea')
            ->join('customers as c', 'c.id', '=', 'cea.customer_id')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->where('cea.employee_id', $employee->id)
            ->where('ceat.transaction_date', '<', $dateFrom)
            ->selectRaw('cea.customer_id, COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0) as opening_balance')
            ->groupBy('cea.customer_id')
            ->get()
            ->keyBy('customer_id');

        $periodTotals = DB::table('customer_employee_accounts as cea')
            ->join('customer_employee_account_transactions as ceat', 'ceat.customer_employee_account_id', '=', 'cea.id')
            ->whereNull('ceat.deleted_at')
            ->where('cea.employee_id', $employee->id)
            ->whereBetween('ceat.transaction_date', [$dateFrom, $dateTo])
            ->selectRaw('
                cea.customer_id,
                COALESCE(SUM(ceat.debit), 0) as period_debit,
                COALESCE(SUM(ceat.credit), 0) as period_credit
            ')
            ->groupBy('cea.customer_id')
            ->get()
            ->keyBy('customer_id');

        // Only customers that have opening balance or period transactions (same pattern as salesmen filter)
        $activeCustomerIds = $openingBalances->keys()
            ->merge($periodTotals->keys())
            ->unique();

        $customers = DB::table('customers')
            ->whereIn('id', $activeCustomerIds)
            ->orderBy('customer_name')
            ->get(['id', 'customer_name', 'customer_code', 'city']);

        $customerRows = $customers->map(function ($customer) use ($openingBalances, $periodTotals) {
            $opening = isset($openingBalances[$customer->id])
                ? (float) $openingBalances[$customer->id]->opening_balance
                : 0.0;

            $period = $periodTotals[$customer->id] ?? null;
            $debit = $period ? (float) $period->period_debit : 0.0;
            $credit = $period ? (float) $period->period_credit : 0.0;

            return (object) [
                'id' => $customer->id,
                'customer_name' => $customer->customer_name,
                'customer_code' => $customer->customer_code,
                'city' => $customer->city,
                'opening_balance' => $opening,
                'period_debit' => $debit,
                'period_credit' => $credit,
                'closing_balance' => $opening + $debit - $credit,
            ];
        });

        $totals = [
            'opening_balance' => $customerRows->sum('opening_balance'),
            'period_debit' => $customerRows->sum('period_debit'),
            'period_credit' => $customerRows->sum('period_credit'),
            'closing_balance' => $customerRows->sum('closing_balance'),
        ];

        return view('reports.supplier-ledger.customers', [
            'supplier' => $supplier,
            'employee' => $employee,
            'customerRows' => $customerRows,
            'totals' => $totals,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
}
