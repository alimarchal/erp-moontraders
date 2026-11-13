<?php

namespace App\Exports;

use App\Models\PayrollRun;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The month's salary sheet as Excel: the same table as the PDF (Fixed part | Variable part | Deductions | Net).
 */
class PayrollSheetExport implements FromView, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<string, mixed>  $sheet  from PayrollSheetService::build()
     */
    public function __construct(private PayrollRun $run, private array $sheet) {}

    public function view(): View
    {
        return view('payroll.pdf._sheet-table', ['sheet' => $this->sheet, 'raw' => true]);
    }

    public function title(): string
    {
        return 'Salary '.$this->run->period_month->format('M Y');
    }
}
