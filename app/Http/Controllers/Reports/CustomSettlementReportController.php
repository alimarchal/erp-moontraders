<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CustomSettlementReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-view-audit'),
        ];
    }

    // Account Codes
    private const ACC_PERCENTAGE_EXPENSE = '5223';

    private const ACC_SCHEME_DISCOUNT = '5292'; // Assuming 5292 based on user request "Scheme Discount Expense (5292)"

    private const ACC_AMR_LIQUID = '5262';

    private const ACC_AMR_POWDER = '5252';

    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id');
        $designation = $request->input('designation');
        $employeeIds = $request->input('employee_ids', []);

        // Fetch Dropdown Data
        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);
        $designations = Employee::distinct()->whereNotNull('designation')->orderBy('designation')->pluck('designation');

        // Fetch employees
        $employeesQuery = Employee::query();

        if ($supplierId) {
            $employeesQuery->where('supplier_id', $supplierId);
        }

        if ($designation) {
            $employeesQuery->where('designation', $designation);
        }

        if (! empty($employeeIds)) {
            $employeesQuery->whereIn('id', $employeeIds);
        }

        $employees = $employeesQuery->orderBy('name')->get();

        // Map settlements
        $settlements = $employees->map(function ($employee) use ($date) {

            // Eager load bankSlips for calculation and fetch ALL settlements for the day
            $employeeSettlements = SalesSettlement::with(['expenses.chartOfAccount', 'bankSlips'])
                ->where('employee_id', $employee->id)
                ->whereDate('settlement_date', $date)
                ->where('status', 'posted')
                ->get();

            $hasData = $employeeSettlements->isNotEmpty();

            // Outstanding credit balance from customer-employee account ledger (debit - credit)
            $totalCredit = (float) CustomerEmployeeAccountTransaction::whereHas('account', function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')->value('balance');

            // Aggregators
            $sale = 0;
            $percentageExpense = 0;
            $schemeDiscount = 0;
            $amrLiquid = 0;
            $amrPowder = 0;
            $todayCashAmount = 0;

            if ($hasData) {
                foreach ($employeeSettlements as $settlement) {
                    $sale += $settlement->total_sales_amount;

                    // Cash + Bank Slips
                    $cashCollected = $settlement->cash_collected;
                    $bankSlipsTotal = $settlement->bankSlips->sum('amount');
                    $todayCashAmount += ($cashCollected + $bankSlipsTotal);

                    // Expenses
                    $expenses = $settlement->expenses; // Eager loaded

                    $getExpense = function ($expenses, $code) {
                        return $expenses->filter(function ($expense) use ($code) {
                            return $expense->chartOfAccount && $expense->chartOfAccount->account_code === $code;
                        })->sum('amount');
                    };

                    $percentageExpense += $getExpense($expenses, self::ACC_PERCENTAGE_EXPENSE);
                    $schemeDiscount += $getExpense($expenses, self::ACC_SCHEME_DISCOUNT);
                    $amrLiquid += $getExpense($expenses, self::ACC_AMR_LIQUID);
                    $amrPowder += $getExpense($expenses, self::ACC_AMR_POWDER);
                }
            }

            $totalDiscount = $percentageExpense + $schemeDiscount;

            return (object) [
                'salesman_name' => $employee->name,
                'designation' => $employee->designation,
                'sale' => $sale,
                'percentage_expense' => $percentageExpense,
                'scheme_discount' => $schemeDiscount,
                'total_discount' => $totalDiscount,
                'amr_liquid' => $amrLiquid,
                'amr_powder' => $amrPowder,
                'today_cash_amount' => $todayCashAmount,
                'total_credit' => $totalCredit,
                'has_data' => $hasData,
            ];
        });

        if (! $supplierId && ! $designation && empty($employeeIds)) {
            $settlements = $settlements->filter(function ($item) {
                return $item->has_data;
            })->values();
        }

        // Calculate Totals
        $totals = [
            'sale' => $settlements->sum('sale'),
            'percentage_expense' => $settlements->sum('percentage_expense'),
            'scheme_discount' => $settlements->sum('scheme_discount'),
            'total_discount' => $settlements->sum('total_discount'),
            'amr_liquid' => $settlements->sum('amr_liquid'),
            'amr_powder' => $settlements->sum('amr_powder'),
            'today_cash_amount' => $settlements->sum('today_cash_amount'),
            'total_credit' => $settlements->sum('total_credit'),
        ];

        // Re-query for dropdown
        $allEmployeesDropdownQuery = Employee::query();
        if ($supplierId) {
            $allEmployeesDropdownQuery->where('supplier_id', $supplierId);
        }
        if ($designation) {
            $allEmployeesDropdownQuery->where('designation', $designation);
        }
        $allEmployees = $allEmployeesDropdownQuery->orderBy('name')->get(['id', 'name']);

        return view('reports.custom-settlement.index', compact(
            'date',
            'supplierId',
            'designation',
            'employeeIds',
            'suppliers',
            'designations',
            'allEmployees',
            'settlements',
            'totals'
        ));
    }
}
