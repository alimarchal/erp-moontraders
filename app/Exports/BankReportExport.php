<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BankReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $report  shape produced by BankReportService
     */
    public function __construct(private readonly array $report) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = $this->report['rows'];

        if ($this->report['totals']) {
            $rows[] = $this->report['totals'];
        }

        foreach ($this->report['notes'] as $note) {
            $rows[] = [$note];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->report['headings'];
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->report['title']), 0, 31);
    }
}
