<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VehicleExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<\App\Models\Vehicle>  $query
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
            'Vehicle Number',
            'Registration Number',
            'Vehicle Type',
            'Make / Model',
            'Year',
            'Company',
            'Supplier',
            'Driver',
            'Driver Phone',
            'Status',
        ];
    }

    /**
     * @param  \App\Models\Vehicle  $vehicle
     * @return array<int, mixed>
     */
    public function map($vehicle): array
    {
        $driverName = $vehicle->employee?->name ?? $vehicle->driver_name ?? '';
        $driverPhone = $vehicle->employee?->phone ?? $vehicle->driver_phone ?? '';

        return [
            $vehicle->vehicle_number,
            $vehicle->registration_number ?? '',
            $vehicle->vehicle_type ?? '',
            $vehicle->make_model ?? '',
            $vehicle->year ?? '',
            $vehicle->company?->company_name ?? '',
            $vehicle->supplier?->supplier_name ?? '',
            $driverName,
            $driverPhone,
            $vehicle->is_active ? 'Active' : 'Inactive',
        ];
    }
}
