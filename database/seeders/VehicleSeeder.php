<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/vehicles.json');

        if (! file_exists($jsonPath)) {
            $this->command?->warn("Vehicle data file not found at {$jsonPath}, skipping vehicle seed.");

            return;
        }

        $payload = json_decode(file_get_contents($jsonPath), true);

        if (! is_array($payload) || $payload === []) {
            $this->command?->warn('Vehicle data file is empty, skipping vehicle seed.');

            return;
        }

        $employees = Employee::query()
            ->select(['id', 'name', 'phone'])
            ->get()
            ->mapWithKeys(function (Employee $employee) {
                $normalized = strtolower(preg_replace('/\s+/', ' ', trim((string) $employee->name)));

                return [$normalized => $employee];
            });

        foreach ($payload as $row) {
            $vehicleNumber = isset($row['vehicle_number']) ? strtoupper(trim((string) $row['vehicle_number'])) : null;
            $registrationNumber = isset($row['registration_number']) ? strtoupper(trim((string) $row['registration_number'])) : null;

            if (! $vehicleNumber || ! $registrationNumber) {
                continue;
            }

            $driverName = isset($row['driver_name']) ? trim((string) $row['driver_name']) : null;
            $driverName = $driverName === '' ? null : $driverName;
            $driverPhone = isset($row['driver_phone']) ? preg_replace('/\\s+/', ' ', trim((string) $row['driver_phone'])) : null;
            $driverPhone = $driverPhone === '' ? null : $driverPhone;

            $assignedEmployeeId = null;
            if ($driverName !== null && $driverName !== '') {
                $normalizedName = strtolower(preg_replace('/\s+/', ' ', $driverName));
                $employee = $employees->get($normalizedName);
                $assignedEmployeeId = $employee?->id;

                if ($employee && $driverPhone && empty($employee->phone)) {
                    $employee->phone = $driverPhone;
                    $employee->save();
                }
            }

            $vehicleType = isset($row['vehicle_type']) ? trim((string) $row['vehicle_type']) : null;
            $vehicleType = $vehicleType === '' ? null : $vehicleType;

            $attributes = [
                'registration_number' => $registrationNumber,
                'vehicle_type' => $vehicleType,
                'company_id' => $this->normalizeNullableInt($row['company_id'] ?? null),
                'supplier_id' => $this->normalizeNullableInt($row['supplier_id'] ?? null),
                'driver_name' => $driverName,
                'driver_phone' => $driverPhone,
                'is_active' => true,
            ];

            $attributes['employee_id'] = $assignedEmployeeId;

            Vehicle::updateOrCreate(
                ['vehicle_number' => $vehicleNumber],
                $attributes
            );
        }
    }

    /**
     * Normalize nullable integer values.
     */
    protected function normalizeNullableInt($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
