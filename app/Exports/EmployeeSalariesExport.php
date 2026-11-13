<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\SalaryComponent;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The salary register: one row per employee, basic salary, one column per allowance and standing deduction, and totals.
 */
class EmployeeSalariesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, array{employee: Employee, basic: float, amounts: array<int, float>, earnings: float, deductions: float, net: float}>  $rows
     * @param  Collection<int, SalaryComponent>  $components
     */
    public function __construct(private Collection $rows, private Collection $components) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Code', 'Employee', 'Designation', 'Company', 'Salary type', 'Pay by', 'Basic',
            ...$this->components->map(fn (SalaryComponent $component) => $component->name.($component->isEarning() ? '' : ' (deduction)'))->all(),
            'Total earnings', 'Total deductions', 'Net salary',
        ];
    }

    /**
     * @param  array{employee: Employee, basic: float, amounts: array<int, float>, earnings: float, deductions: float, net: float}  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $employee = $row['employee'];

        return [
            $employee->employee_code, $employee->name, $employee->designation, $employee->supplier?->supplier_name,
            $employee->salary ? ($employee->salary->salary_type === 'fixed' ? 'Fixed' : 'Basic') : 'Not set',
            $employee->salary ? ucfirst($employee->salary->pay_method) : '',
            $row['basic'],
            ...$this->components->map(fn (SalaryComponent $component) => $row['amounts'][$component->id] ?? 0)->all(),
            $row['earnings'], $row['deductions'], $row['net'],
        ];
    }
}
