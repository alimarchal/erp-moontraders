<?php

namespace App\Services;

use App\Models\CompanyTarget;
use App\Models\CompanyTargetLine;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyTargetService
{
    /** Designation pre-selected on the create form and used by the monthly generator. */
    public const DEFAULT_DESIGNATION = 'Salesman';

    /**
     * Active employees of a supplier with the given designation, in the order rows are listed.
     *
     * @return Collection<int, Employee>
     */
    public function eligibleEmployees(int $supplierId, string $designation = self::DEFAULT_DESIGNATION): Collection
    {
        return Employee::query()
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->where('designation', $designation)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'designation']);
    }

    /**
     * Target vs achieved rows for one supplier between two dates.
     *
     * Total is the sum of the target sheets overlapping the dates; Achieved is the sales of
     * posted settlements in the dates; Remaining/Day spreads what is left over the working days
     * still to go (Monday to Saturday, today to the end date, at least one).
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, float>, days_left: int}
     */
    public function summary(int $supplierId, CarbonInterface $from, CarbonInterface $to, ?CarbonInterface $today = null): array
    {
        $today ??= now();

        $lines = CompanyTargetLine::query()
            ->whereHas('companyTarget', fn ($query) => $query->where('supplier_id', $supplierId)->overlapping($from->toDateString(), $to->toDateString()))
            ->with('employee:id,name,designation')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $achieved = DB::table('sales_settlements')
            ->where('supplier_id', $supplierId)
            ->where('status', 'posted')
            ->whereDate('settlement_date', '>=', $from->toDateString())
            ->whereDate('settlement_date', '<=', $to->toDateString())
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(total_sales_amount) as sales')
            ->pluck('sales', 'employee_id');

        $employees = Employee::query()
            ->whereIn('id', $lines->pluck('employee_id')->merge($achieved->keys())->unique())
            ->get(['id', 'name'])
            ->keyBy('id');

        $anchor = $today->copy()->startOfDay();
        $anchor = $anchor->lt($from) ? $from->copy()->startOfDay() : $anchor;
        $daysLeft = max(1, $this->workingDaysBetween($anchor, $to));

        // Rows keep the order of the target sheet; salesmen with sales but no target follow, by name.
        $sheetOrder = $lines->pluck('employee_id')->unique()->values()->flip();

        $rows = $employees->map(function (Employee $employee) use ($lines, $achieved, $daysLeft, $sheetOrder) {
            $employeeLines = $lines->where('employee_id', $employee->id);
            $total = (float) $employeeLines->sum('target_amount');
            $sales = (float) ($achieved[$employee->id] ?? 0);
            $remaining = (float) max($total - $sales, 0);

            return [
                'position' => $sheetOrder[$employee->id] ?? PHP_INT_MAX,
                'employee' => $employee->name,
                'label' => $employeeLines->pluck('target_label')->filter()->last() ?? CompanyTarget::DEFAULT_LABEL,
                'total' => $total,
                'achieved' => $sales,
                'remaining_per_day' => $remaining / $daysLeft,
                'remaining_per_day_share' => $remaining / $daysLeft / $daysLeft,
                'percentage' => $total > 0 ? $sales / $total * 100 : 0.0,
            ];
        })->sortBy([['position', 'asc'], ['employee', 'asc']])->values();

        $total = (float) $rows->sum('total');
        $sales = (float) $rows->sum('achieved');

        return [
            'rows' => $rows,
            'totals' => [
                'total' => $total,
                'achieved' => $sales,
                'remaining_per_day' => (float) $rows->sum('remaining_per_day'),
                'remaining_per_day_share' => (float) $rows->sum('remaining_per_day_share'),
                'percentage' => $total > 0 ? $sales / $total * 100 : 0.0,
            ],
            'days_left' => $daysLeft,
        ];
    }

    /**
     * Working days from one date to another, both included: Monday to Saturday, Sundays are off.
     */
    private function workingDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $days = 0;
        $day = $from->copy()->startOfDay();

        while ($day->lte($to)) {
            if (! $day->isSunday()) {
                $days++;
            }

            $day = $day->addDay();
        }

        return $days;
    }
}
