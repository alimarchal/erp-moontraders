<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementExpense;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class AdvanceTaxSalesRegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-advance-tax-sales-register'),
        ];
    }

    /**
     * Account code for Advance Tax benefits (NTN customers).
     */
    private const ADVANCE_TAX_ACCOUNT_CODE = '1161';

    public function index(Request $request)
    {
        $startDate = $request->input('filter.start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('filter.end_date', now()->endOfMonth()->format('Y-m-d'));
        $supplierId = $request->input('filter.supplier_id');
        $employeeId = $request->input('filter.employee_id');
        $vehicleId = $request->input('filter.vehicle_id');
        $warehouseId = $request->input('filter.warehouse_id');
        $designation = $request->input('filter.designation');

        $suppliers = Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'supplier_id', 'designation']);
        $vehicles = Vehicle::where('is_active', true)->orderBy('registration_number')->get(['id', 'registration_number']);
        $warehouses = Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name as name']);
        $designations = Employee::distinct()->whereNotNull('designation')->orderBy('designation')->pluck('designation');

        $hasFilter = (bool) $supplierId;

        $rows = collect();
        $totalSale = 0;
        $totalAdvanceTax = 0;
        $totalBenefits = 0;
        $totalReceived = 0;

        if ($hasFilter) {
            $isPostgres = DB::connection()->getDriverName() === 'pgsql';

            // CAST settlement_date to text for use as array key (cross-DB safe)
            $dateCast = $isPostgres
                ? 'CAST(settlement_date AS TEXT)'
                : 'DATE_FORMAT(settlement_date, \'%Y-%m-%d\')';

            $settlementQuery = SalesSettlement::query()
                ->where('status', 'posted')
                ->whereNull('deleted_at')
                ->where('supplier_id', $supplierId)
                ->whereBetween('settlement_date', [$startDate, $endDate]);

            if ($employeeId) {
                $settlementQuery->where('employee_id', $employeeId);
            }

            if ($vehicleId) {
                $settlementQuery->where('vehicle_id', $vehicleId);
            }

            if ($warehouseId) {
                $settlementQuery->where('warehouse_id', $warehouseId);
            }

            if ($designation) {
                $settlementQuery->whereHas('employee', fn ($q) => $q->where('designation', $designation));
            }

            // Aggregate sales by date
            $settlementsByDate = (clone $settlementQuery)
                ->selectRaw("{$dateCast} as date_key, SUM(total_sales_amount) as total_sale")
                ->groupBy('settlement_date')
                ->pluck('total_sale', 'date_key');

            // Aggregate account 1161 (NTN benefits) by date
            $benefitsQuery = SalesSettlementExpense::query()
                ->join('sales_settlements', 'sales_settlements.id', '=', 'sales_settlement_expenses.sales_settlement_id')
                ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'sales_settlement_expenses.expense_account_id')
                ->where('sales_settlements.status', 'posted')
                ->whereNull('sales_settlements.deleted_at')
                ->where('sales_settlements.supplier_id', $supplierId)
                ->whereBetween('sales_settlements.settlement_date', [$startDate, $endDate])
                ->where('chart_of_accounts.account_code', self::ADVANCE_TAX_ACCOUNT_CODE);

            if ($employeeId) {
                $benefitsQuery->where('sales_settlements.employee_id', $employeeId);
            }

            if ($vehicleId) {
                $benefitsQuery->where('sales_settlements.vehicle_id', $vehicleId);
            }

            if ($warehouseId) {
                $benefitsQuery->where('sales_settlements.warehouse_id', $warehouseId);
            }

            if ($designation) {
                $benefitsQuery->join('employees', 'employees.id', '=', 'sales_settlements.employee_id')
                    ->where('employees.designation', $designation);
            }

            $benefitDateCast = $isPostgres
                ? 'CAST(sales_settlements.settlement_date AS TEXT)'
                : 'DATE_FORMAT(sales_settlements.settlement_date, \'%Y-%m-%d\')';

            $benefitsByDate = $benefitsQuery
                ->selectRaw("{$benefitDateCast} as date_key, SUM(sales_settlement_expenses.amount) as total_benefits")
                ->groupBy('sales_settlements.settlement_date')
                ->pluck('total_benefits', 'date_key');

            // Build one row per calendar day in the range
            $current = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            while ($current->lte($end)) {
                $dateKey = $current->format('Y-m-d');
                $sale = (float) ($settlementsByDate[$dateKey] ?? 0);
                $benefits = (float) ($benefitsByDate[$dateKey] ?? 0);
                $advanceTax = $sale * 0.025;
                $received = $advanceTax - $benefits;

                $rows->push([
                    'date' => $dateKey,
                    'sale' => $sale,
                    'advance_tax' => $advanceTax,
                    'benefits' => $benefits,
                    'received' => $received,
                ]);

                $current->addDay();
            }

            $totalSale = $rows->sum('sale');
            $totalAdvanceTax = $rows->sum('advance_tax');
            $totalBenefits = $rows->sum('benefits');
            $totalReceived = $rows->sum('received');
        }

        $selectedSupplier = $supplierId
            ? $suppliers->firstWhere('id', $supplierId)
            : null;

        return view('reports.advance-tax-sales-register.index', [
            'rows' => $rows,
            'suppliers' => $suppliers,
            'employees' => $employees,
            'vehicles' => $vehicles,
            'warehouses' => $warehouses,
            'designations' => $designations,
            'selectedSupplierId' => $supplierId,
            'selectedSupplier' => $selectedSupplier,
            'selectedEmployeeId' => $employeeId,
            'selectedVehicleId' => $vehicleId,
            'selectedWarehouseId' => $warehouseId,
            'selectedDesignation' => $designation,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'hasFilter' => $hasFilter,
            'totalSale' => $totalSale,
            'totalAdvanceTax' => $totalAdvanceTax,
            'totalBenefits' => $totalBenefits,
            'totalReceived' => $totalReceived,
        ]);
    }
}
