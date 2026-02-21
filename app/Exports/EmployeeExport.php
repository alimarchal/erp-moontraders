<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeeExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<\App\Models\Employee>  $query
     */
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Employee Code',
            'Name',
            'Company / Principal',
            'Supplier',
            'Designation',
            'Phone',
            'Email',
            'Address',
            'Warehouse',
            'Cost Center',
            'Linked User',
            'Hire Date',
            'Status',
        ];
    }

    /**
     * @param  \App\Models\Employee  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        return [
            $employee->employee_code,
            $employee->name,
            $employee->company_name ?? '',
            $employee->supplier?->supplier_name ?? '',
            $employee->designation ?? '',
            $employee->phone ?? '',
            $employee->email ?? '',
            $employee->address ?? '',
            $employee->warehouse?->warehouse_name ?? '',
            $employee->costCenter?->name ?? '',
            $employee->user?->name ?? '',
            $employee->hire_date?->format('Y-m-d') ?? '',
            $employee->is_active ? 'Active' : 'Inactive',
        ];
    }
}
