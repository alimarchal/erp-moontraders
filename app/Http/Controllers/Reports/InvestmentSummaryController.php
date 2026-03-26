<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ClaimRegister;
use App\Models\Employee;
use App\Models\LegerRegister;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class InvestmentSummaryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-investment-summary'),
        ];
    }

    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id', Supplier::where('supplier_name', 'Nestlé Pakistan')->value('id'));
        $designation = $request->input('designation', 'Salesman');
        $employeeIds = $request->input('employee_ids', []);

        $suppliers = Supplier::orderBy('supplier_name')->get();
        $designations = Employee::distinct()->whereNotNull('designation')->orderBy('designation')->pluck('designation');

        $allEmployeesQuery = Employee::query();
        if ($supplierId) {
            $allEmployeesQuery->where('supplier_id', $supplierId);
        }
        if ($designation) {
            $allEmployeesQuery->where('designation', $designation);
        }
        $allEmployees = $allEmployeesQuery->orderBy('name')->get();

        // Part 1: Salesman Credit Data
        $salesmanCreditData = $this->getSalesmanCreditData($date, $supplierId, $designation, $employeeIds);

        // Grand totals
        $creditGrandTotals = (object) [
            'opening_credit' => $salesmanCreditData->sum('opening_credit'),
            'credit_amount' => $salesmanCreditData->sum('credit_amount'),
            'recovery_amount' => $salesmanCreditData->sum('recovery_amount'),
            'total_credit' => $salesmanCreditData->sum('total_credit'),
        ];

        // Part 2: Investment Summary
        $powderExpiry = $this->getPowderExpiry($supplierId);
        $liquidExpiry = $this->getLiquidExpiry($supplierId);
        $claimAmount = $this->getClaimAmount($supplierId);
        $stockAmount = $this->getStockAmount($supplierId);
        $creditAmount = (float) $creditGrandTotals->total_credit;
        $ledgerAmount = $this->getLedgerAmount($supplierId);

        $currentTotal = $powderExpiry + $liquidExpiry + $claimAmount + $stockAmount + $creditAmount + $ledgerAmount;

        // Previous date comparison
        $previousDate = Carbon::parse($date)->subDay()->format('Y-m-d');
        $previousCreditData = $this->getSalesmanCreditData($previousDate, $supplierId, $designation, $employeeIds);
        $previousCreditAmount = (float) $previousCreditData->sum('total_credit');

        $previousTotal = $powderExpiry + $liquidExpiry + $claimAmount + $stockAmount + $previousCreditAmount + $ledgerAmount;

        $difference = $currentTotal - $previousTotal;
        $differencePercent = $previousTotal != 0 ? ($difference / abs($previousTotal)) * 100 : 0;

        $selectedSupplier = $supplierId ? $suppliers->find($supplierId) : null;

        return view('reports.investment-summary', compact(
            'date',
            'supplierId',
            'designation',
            'employeeIds',
            'suppliers',
            'designations',
            'allEmployees',
            'salesmanCreditData',
            'creditGrandTotals',
            'powderExpiry',
            'liquidExpiry',
            'claimAmount',
            'stockAmount',
            'creditAmount',
            'ledgerAmount',
            'currentTotal',
            'previousDate',
            'previousTotal',
            'difference',
            'differencePercent',
            'selectedSupplier',
        ));
    }

    private function getSalesmanCreditData(string $date, ?int $supplierId, ?string $designation, array $employeeIds)
    {
        $query = Employee::query()
            ->select('employees.*')
            ->selectSub(function ($query) use ($date) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_date', '<', $date)
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0) - COALESCE(SUM(ceat.credit), 0)');
            }, 'opening_credit')
            ->selectSub(function ($query) use ($date) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'credit_sale')
                    ->where('ceat.transaction_date', $date)
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.debit), 0)');
            }, 'credit_amount')
            ->selectSub(function ($query) use ($date) {
                $query->from('customer_employee_account_transactions as ceat')
                    ->join('customer_employee_accounts as cea', 'ceat.customer_employee_account_id', '=', 'cea.id')
                    ->whereColumn('cea.employee_id', 'employees.id')
                    ->where('ceat.transaction_type', 'recovery')
                    ->where('ceat.transaction_date', $date)
                    ->whereNull('ceat.deleted_at')
                    ->selectRaw('COALESCE(SUM(ceat.credit), 0)');
            }, 'recovery_amount');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($designation) {
            $query->where('designation', $designation);
        }

        if (! empty($employeeIds)) {
            $query->whereIn('employees.id', $employeeIds);
        }

        $query->orderBy('name');

        return $query->get()->map(function ($employee) {
            $employee->opening_credit = (float) $employee->opening_credit;
            $employee->credit_amount = (float) $employee->credit_amount;
            $employee->recovery_amount = (float) $employee->recovery_amount;
            $employee->total_credit = $employee->opening_credit + $employee->credit_amount - $employee->recovery_amount;

            return $employee;
        });
    }

    private function getPowderExpiry(?int $supplierId): float
    {
        $query = DB::table('sales_settlement_amr_powders as p')
            ->join('sales_settlements as ss', 'p.sales_settlement_id', '=', 'ss.id')
            ->where('p.is_disposed', false)
            ->whereNull('ss.deleted_at');

        if ($supplierId) {
            $query->where('ss.supplier_id', $supplierId);
        }

        return (float) $query->sum('p.amount');
    }

    private function getLiquidExpiry(?int $supplierId): float
    {
        $query = DB::table('sales_settlement_amr_liquids as l')
            ->join('sales_settlements as ss', 'l.sales_settlement_id', '=', 'ss.id')
            ->where('l.is_disposed', false)
            ->whereNull('ss.deleted_at');

        if ($supplierId) {
            $query->where('ss.supplier_id', $supplierId);
        }

        return (float) $query->sum('l.amount');
    }

    private function getClaimAmount(?int $supplierId): float
    {
        $query = ClaimRegister::whereNotNull('posted_at');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return (float) $query->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->value('balance');
    }

    private function getStockAmount(?int $supplierId): float
    {
        $query = DB::table('current_stock_by_batch as csb')
            ->join('products as p', 'csb.product_id', '=', 'p.id')
            ->where('csb.status', 'active')
            ->whereNull('p.deleted_at');

        if ($supplierId) {
            $query->where('p.supplier_id', $supplierId);
        }

        return (float) $query->selectRaw('COALESCE(SUM(csb.quantity_on_hand * csb.unit_cost), 0) as total')
            ->value('total');
    }

    private function getLedgerAmount(?int $supplierId): float
    {
        $query = LegerRegister::query();

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return (float) $query->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->value('balance');
    }
}
