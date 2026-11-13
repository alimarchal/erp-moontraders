<?php

namespace App\Services;

use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Models\PayrollSlipLine;
use App\Models\SalaryComponent;
use Illuminate\Support\Collection;

/**
 * The month's salary sheet: one row per employee with a column for every pay item that month, laid out like the
 * distributors' own sheet: Fixed part (basic and allowances) | Variable part (achievement items, bonuses) |
 * Deductions (absent days, advance, settlement short, death fund...) | Net salary. Only columns with an amount appear.
 */
class PayrollSheetService
{
    public const GROUP_FIXED = 'fixed';

    public const GROUP_VARIABLE = 'variable';

    public const GROUP_DEDUCTION = 'deduction';

    /** @var array<string, string> */
    public const GROUP_LABELS = [self::GROUP_FIXED => 'Fixed part', self::GROUP_VARIABLE => 'Variable part', self::GROUP_DEDUCTION => 'Deductions'];

    /**
     * @return array{
     *     columns: array<string, list<array{key: string, label: string}>>,
     *     groups: Collection<string, Collection<int, array{slip: PayrollSlip, amounts: array<string, float>, fixed: float, variable: float, earnings: float, deductions: float, net: float}>>,
     *     rows: Collection<int, array{slip: PayrollSlip, amounts: array<string, float>, fixed: float, variable: float, earnings: float, deductions: float, net: float}>,
     *     totals: array<string, float>
     * }
     */
    public function build(PayrollRun $run): array
    {
        $run->loadMissing(['slips.employee.supplier', 'slips.lines.component']);

        $columns = [self::GROUP_FIXED => [], self::GROUP_VARIABLE => [], self::GROUP_DEDUCTION => []];
        $order = [];
        $rows = collect();

        foreach ($run->slips->sortBy(fn (PayrollSlip $slip) => $slip->employee?->name) as $slip) {
            $amounts = [];
            $fixed = 0.0;
            $variable = 0.0;

            foreach ($slip->lines as $line) {
                [$group, $key, $label, $sort] = $this->column($line);
                $columns[$group][$key] ??= ['key' => $key, 'label' => $label];
                $order[$key] = $sort;
                $amounts[$key] = round(($amounts[$key] ?? 0) + (float) $line->amount, 2);

                if ($group === self::GROUP_FIXED) {
                    $fixed += (float) $line->amount;
                } elseif ($group === self::GROUP_VARIABLE) {
                    $variable += (float) $line->amount;
                }
            }

            $rows->push([
                'slip' => $slip, 'amounts' => $amounts, 'fixed' => round($fixed, 2), 'variable' => round($variable, 2),
                'earnings' => (float) $slip->total_earnings, 'deductions' => (float) $slip->total_deductions, 'net' => (float) $slip->net_pay,
            ]);
        }

        foreach ($columns as $group => $list) {
            uasort($list, fn ($a, $b) => [$order[$a['key']], $a['label']] <=> [$order[$b['key']], $b['label']]);
            $columns[$group] = array_values($list);
        }

        $totals = ['fixed' => $rows->sum('fixed'), 'variable' => $rows->sum('variable'), 'earnings' => $rows->sum('earnings'), 'deductions' => $rows->sum('deductions'), 'net' => $rows->sum('net')];
        foreach (array_merge(...array_values($columns)) as $column) {
            $totals[$column['key']] = round($rows->sum(fn ($row) => $row['amounts'][$column['key']] ?? 0), 2);
        }

        // A payroll of every employee is shown company by company, as on screen.
        $groups = $run->supplier_id
            ? collect(['' => $rows])
            : $rows->groupBy(fn ($row) => $row['slip']->employee?->supplier?->supplier_name ?? 'Moon Traders (head office)')->sortKeys();

        return ['columns' => $columns, 'groups' => $groups, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Which column a slip line belongs to.
     *
     * @return array{0: string, 1: string, 2: string, 3: int} group, key, label, sort order
     */
    private function column(PayrollSlipLine $line): array
    {
        $component = $line->component;

        if ($line->kind === 'deduction') {
            return match ($line->source) {
                PayrollSlipLine::SOURCE_ABSENT => [self::GROUP_DEDUCTION, 'absent', 'Absent days', 1],
                PayrollSlipLine::SOURCE_ADVANCE => [self::GROUP_DEDUCTION, 'advance', 'Advance', 2],
                PayrollSlipLine::SOURCE_SHORT => [self::GROUP_DEDUCTION, 'short', 'Settlement short', 3],
                default => [self::GROUP_DEDUCTION, $component ? 'c'.$component->id : 'other_deduction', $component?->name ?? 'Other deduction', $component ? 10 + (int) $component->sort_order : 999],
            };
        }

        return match (true) {
            $line->source === PayrollSlipLine::SOURCE_BASIC => [self::GROUP_FIXED, 'basic', 'Basic', 0],
            $line->source === PayrollSlipLine::SOURCE_EXCESS => [self::GROUP_VARIABLE, 'excess', 'Settlement excess', 990],
            $component === null => [self::GROUP_VARIABLE, 'other_earning', 'Other', 999],
            $component->calculation === SalaryComponent::CALC_INPUT => [self::GROUP_VARIABLE, 'c'.$component->id, $component->name, (int) $component->sort_order],
            default => [self::GROUP_FIXED, 'c'.$component->id, $component->name, (int) $component->sort_order],
        };
    }
}
