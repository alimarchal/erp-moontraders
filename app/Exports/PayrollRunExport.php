<?php

namespace App\Exports;

use App\Models\PayrollRun;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One payroll as a sheet: a row per employee (supplier by supplier for a company-wide run) with a total row.
 */
class PayrollRunExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private PayrollRun $run) {}

    public function title(): string
    {
        return substr($this->run->run_number, 0, 31);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Supplier', 'Code', 'Employee', 'Designation', 'Salary type', 'Basic salary', 'Absent days', 'Earnings', 'Deductions', 'Net pay'];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $slips = $this->run->slips->sortBy(fn ($slip) => [$slip->employee?->supplier?->supplier_name ?? '', $slip->employee?->name ?? '']);

        $rows = $slips->map(fn ($slip) => [
            $slip->employee?->supplier?->supplier_name ?? 'Moon Traders (head office)',
            $slip->employee?->employee_code,
            $slip->employee?->name,
            $slip->employee?->designation,
            $slip->salary_type === 'fixed' ? 'Fixed' : 'Basic',
            (float) $slip->basic_salary,
            (float) $slip->absent_days,
            (float) $slip->total_earnings,
            (float) $slip->total_deductions,
            (float) $slip->net_pay,
        ])->values()->all();

        $rows[] = ['', '', 'Total', '', '', '', '', (float) $this->run->total_earnings, (float) $this->run->total_deductions, (float) $this->run->total_net];

        return $rows;
    }
}
