<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CashDetailController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-view-audit'),
        ];
    }

    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id');
        $designation = $request->input('designation');
        $employeeIds = $request->input('employee_ids', []); // Array of selected employee IDs

        $suppliers = Supplier::orderBy('supplier_name')->get();
        // Fetch unique designations
        $designations = \App\Models\Employee::distinct()->whereNotNull('designation')->orderBy('designation')->pluck('designation');

        // Fetch all employees (Salesmen) to populate the filter
        $allEmployees = \App\Models\Employee::query();

        if ($supplierId) {
            $allEmployees->where('supplier_id', $supplierId);
        }

        if ($designation) {
            $allEmployees->where('designation', $designation);
        }

        $allEmployees = $allEmployees->orderBy('name')->get();

        // 1. Prepare Salesman Data (Show ALL salesmen if supplier selected, or just those with settlements otherwise?)
        // The user said: "agar mein supplier select kro tou us supplier k saray salesman show honey chieyay"
        // So we start with Employees, then left join settlements.

        $employeesQuery = \App\Models\Employee::query();

        if ($supplierId) {
            $employeesQuery->where('supplier_id', $supplierId);
        }

        if ($designation) {
            $employeesQuery->where('designation', $designation);
        }

        if (! empty($employeeIds)) {
            $employeesQuery->whereIn('id', $employeeIds);
        }

        // We get the relevant employees
        $employees = $employeesQuery->orderBy('name')->get();

        // Fetch ALL matching settlements once (with relationships) to reuse across all sections.
        // This eliminates the N+1 problem and ensures all settlements per employee are included.
        $settlementsQuery = SalesSettlement::with(['employee', 'cashDenominations', 'bankSlips'])
            ->whereDate('settlement_date', $date);

        if ($supplierId) {
            $settlementsQuery->whereHas('employee', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            });
        }

        if ($designation) {
            $settlementsQuery->whereHas('employee', function ($q) use ($designation) {
                $q->where('designation', $designation);
            });
        }

        if (! empty($employeeIds)) {
            $settlementsQuery->whereIn('employee_id', $employeeIds);
        }

        $settlements = $settlementsQuery->get();

        // Group settlements by employee_id for efficient lookup
        $settlementsByEmployee = $settlements->groupBy('employee_id');

        // 1. Salesman Cash — sum cash across ALL settlements per employee
        $salesmanData = $employees->map(function ($employee) use ($settlementsByEmployee) {
            $employeeSettlements = $settlementsByEmployee->get($employee->id, collect());

            $amount = $employeeSettlements->sum(function ($settlement) {
                $cashDenom = $settlement->cashDenominations->first();

                return ($cashDenom && $cashDenom->total_amount > 0)
                    ? (float) $cashDenom->total_amount
                    : (float) $settlement->cash_collected;
            });

            return (object) [
                'salesman_name' => $employee->name,
                'amount' => $amount,
                'has_settlement' => $employeeSettlements->isNotEmpty(),
            ];
        });

        if (! $supplierId && ! $designation && empty($employeeIds)) {
            $salesmanData = $salesmanData->filter(function ($item) {
                return $item->amount > 0 || $item->has_settlement;
            });
        }

        $salesmanData = $salesmanData->sortByDesc('amount')->values();

        // 2. Cash Denominations — aggregate across ALL settlements
        $denominations = [
            '5000' => 0,
            '1000' => 0,
            '500' => 0,
            '100' => 0,
            '50' => 0,
            '20' => 0,
            '10' => 0,
            'coins' => 0,
        ];

        foreach ($settlements as $settlement) {
            $denom = $settlement->cashDenominations->first();
            if ($denom) {
                $denominations['5000'] += $denom->denom_5000;
                $denominations['1000'] += $denom->denom_1000;
                $denominations['500'] += $denom->denom_500;
                $denominations['100'] += $denom->denom_100;
                $denominations['50'] += $denom->denom_50;
                $denominations['20'] += $denom->denom_20;
                $denominations['10'] += $denom->denom_10;
                $denominations['coins'] += $denom->denom_coins;
            }
        }

        // 3. Bank Slips — sum across ALL settlements per employee
        $bankSlipsData = $employees->map(function ($employee) use ($settlementsByEmployee) {
            $employeeSettlements = $settlementsByEmployee->get($employee->id, collect());

            $amount = $employeeSettlements->sum(fn ($settlement) => (float) $settlement->bankSlips->sum('amount'));

            return (object) [
                'salesman_name' => $employee->name,
                'amount' => $amount,
                'has_settlement' => $employeeSettlements->isNotEmpty(),
            ];
        });

        if (! $supplierId && ! $designation && empty($employeeIds)) {
            $bankSlipsData = $bankSlipsData->filter(function ($item) {
                return $item->amount > 0 || $item->has_settlement;
            });
        }

        $bankSlipsData = $bankSlipsData->sortByDesc('amount')->values();

        return view('reports.cash-detail', compact(
            'date',
            'supplierId',
            'designation',
            'employeeIds',
            'suppliers',
            'designations',
            'allEmployees',
            'salesmanData',
            'denominations',
            'bankSlipsData'
        ));
    }
}
