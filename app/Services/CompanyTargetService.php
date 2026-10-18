<?php

namespace App\Services;

use App\Models\CompanyTarget;
use App\Models\CompanyTargetLine;
use App\Models\Employee;
use App\Models\Supplier;
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
     * Creates the target sheet of a supplier for a period with one zero-target row per active
     * salesman. Safe to run again: an existing sheet only receives rows for salesmen it lacks.
     *
     * @return array{target: CompanyTarget, created: bool, lines_added: int}
     */
    public function generateForPeriod(Supplier $supplier, CarbonInterface $start, CarbonInterface $end, ?int $userId = null): array
    {
        return DB::transaction(function () use ($supplier, $start, $end, $userId) {
            $target = CompanyTarget::query()->firstOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                ],
                ['created_by' => $userId, 'updated_by' => $userId],
            );

            $existing = $target->lines()->pluck('employee_id');
            $sortOrder = (int) $target->lines()->max('sort_order');

            $missing = $this->eligibleEmployees($supplier->id)->reject(fn (Employee $employee) => $existing->contains($employee->id));

            foreach ($missing as $employee) {
                $target->lines()->create([
                    'employee_id' => $employee->id,
                    'target_label' => CompanyTarget::DEFAULT_LABEL,
                    'target_amount' => 0,
                    'sort_order' => ++$sortOrder,
                ]);
            }

            return ['target' => $target, 'created' => $target->wasRecentlyCreated, 'lines_added' => $missing->count()];
        });
    }

    /**
     * Target vs achieved rows for one supplier between two dates.
     *
     * Total is the sum of the target sheets overlapping the dates; Achieved is the sales of
     * posted settlements in the dates; Remaining/Day spreads what is left over the days still
     * to go (today to the end date, at least one).
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
        $daysLeft = max(1, (int) $anchor->diffInDays($to->copy()->startOfDay(), false) + 1);

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
                'percentage' => $total > 0 ? $sales / $total * 100 : 0.0,
            ],
            'days_left' => $daysLeft,
        ];
    }
}
