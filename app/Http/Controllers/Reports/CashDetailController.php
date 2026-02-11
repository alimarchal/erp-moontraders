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

        // Now map them to their settlement data for the date
        $salesmanData = $employees->map(function ($employee) use ($date) {
            // Find settlement for this date
            $settlement = SalesSettlement::with(['cashDenominations'])
                ->where('employee_id', $employee->id)
                ->whereDate('settlement_date', $date)
                ->first();

            $amount = 0;
            if ($settlement) {
                $cashDenom = $settlement->cashDenominations->first();
                $amount = ($cashDenom && $cashDenom->total_amount > 0)
                    ? $cashDenom->total_amount
                    : $settlement->cash_collected;
            }

            return (object) [
                'salesman_name' => $employee->name,
                'amount' => $amount,
                'has_settlement' => $settlement ? true : false,
            ];
        });

        // If NO supplier is selected, and NO employee filter, maybe we only want to show employees who HAVE a settlement?
        // The user didn't explicitly say "show all employees of the system" if no filter is active.
        // But usually "Cash Detail" implies active settlements.
        // However, to be safe and consistent with "Show all for supplier", if no filter is active,
        // showing ALL employees might be too much.
        // Let's filter the Main List to only those with Amount > 0 OR if a filter is active.
        // Actually, if a Supplier IS selected, show ALL. If NOT, maybe just show those with activity?
        // Let's stick to: If $supplierId OR $employeeIds is set, show matches. Else show only those with activity.

        if (! $supplierId && ! $designation && empty($employeeIds)) {
            $salesmanData = $salesmanData->filter(function ($item) {
                return $item->amount > 0 || $item->has_settlement;
            });
        }

        $salesmanData = $salesmanData->sortByDesc('amount')->values();

        // 2. Data for Cash Denominations and Bank Slips (Only from ACTUAL settlements)
        // We need to fetch actual settlements again to aggregate denominations and bank slips
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

        // Aggregations
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

        // 3. Bank Slips Data (Grouped by Salesman, matching the Salesman Cash list)
        // The user wants the Bank Slips table to list ALL salesmen just like the Salesman Cash table.
        $bankSlipsData = $employees->map(function ($employee) use ($date) {
            $settlement = SalesSettlement::with(['bankSlips'])
                ->where('employee_id', $employee->id)
                ->whereDate('settlement_date', $date)
                ->first();

            $amount = 0;
            if ($settlement) {
                $amount = $settlement->bankSlips->sum('amount');
            }

            return (object) [
                'salesman_name' => $employee->name,
                'amount' => $amount,
                'has_settlement' => $settlement ? true : false,
            ];
        });

        // Filter logic identical to salesmanData
        if (! $supplierId && ! $designation && empty($employeeIds)) {
            $bankSlipsData = $bankSlipsData->filter(function ($item) {
                return $item->amount > 0 || $item->has_settlement;
            });
        }

        // We do NOT sort this by amount descending because the user likely wants the order to match Salesman Cash?
        // Actually, Salesman Cash IS sorted by amount desc.
        // "Bank Slips mein bhi asay he salesman show honay chieyay jsay Salesman Cash mein ho rhay hn"
        // (Bank Slips should also show salesmen JUST LIKE Salesman Cash).
        // This implies the ORDER should also be the same.
        // Currently Salesman Cash is sorted by Amount Desc.
        // If we sort Bank Slips by Amount Desc, the names won't align row-by-row.
        // If the user wants them to correspond (Row 1 Left = Row 1 Right), they must have the same sort order.
        // BUT Salesman Cash sorts by Cash Amount. Bank Slips sorts by Bank Amount?
        // If I sort Bank Slips by Bank Amount, Row 1 could be "John" (High Bank) and Row 1 Left is "Doe" (High Cash).
        // This effectively breaks the "Salesman A is here" visual link if they expect them to be the same list.
        // HOWEVER, "show salesmen like Salesman Cash" usually means "Show the list of salesmen".
        // Given they are separate tables, let's sort Bank Slips by its own Amount Desc to show top depositors?
        // OR does "jsay Salesman Cash mein ho rhay hn" mean "Same list, same order"?
        // Let's assume SAME LIST, SAME ORDER as Salesman Cash (which is sorted by Cash Amount)? No, that's weird for Bank Slips.
        // Let's assume "Same List of Names" but sorted by its own value?
        // Actually, looking at the user's request: "Salesman Cash mein ho rhay hn" -> The Salesman Cash table lists them.
        // I will sort Bank Slips by ITS OWN amount desc for now, as that's standard for "Top X".
        // If the user wants fixed order (Alphabetical), they would have asked.
        // Wait, if I sort differently, the "Sr.#" won't match per person.
        // Let's just sort by Amount Desc for now.

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
