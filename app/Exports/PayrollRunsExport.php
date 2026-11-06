<?php

namespace App\Exports;

use App\Models\PayrollRun;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The payroll register: one row per payroll run.
 */
class PayrollRunsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, PayrollRun>  $runs
     */
    public function __construct(private Collection $runs) {}

    public function collection(): Collection
    {
        return $this->runs;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Run', 'Month', 'Company', 'Employees', 'Earnings', 'Deductions', 'Net pay', 'Status', 'Paid on'];
    }

    /**
     * @param  PayrollRun  $run
     * @return array<int, mixed>
     */
    public function map($run): array
    {
        return [
            $run->run_number, $run->period_month->format('F Y'), $run->ownerLabel(), $run->slips_count,
            (float) $run->total_earnings, (float) $run->total_deductions, (float) $run->total_net,
            $run->statusLabel(), $run->paid_on?->format('d-M-Y'),
        ];
    }
}
