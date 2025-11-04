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
        $dataPath = database_path('seeders/data/vehicles.json');

        if (!file_exists($dataPath)) {
            $this->command?->warn('Vehicle data file not found, skipping vehicle seed.');
            return;
        }

        $payload = json_decode(file_get_contents($dataPath), true);

        if (!is_array($payload) || $payload === []) {
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

            if (!$vehicleNumber || !$registrationNumber) {
                continue;
            }

            $driverName = isset($row['driver_name']) ? trim((string) $row['driver_name']) : null;
            $driverPhone = isset($row['driver_phone']) ? preg_replace('/\\s+/', ' ', trim((string) $row['driver_phone'])) : null;

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

            $attributes = [
                'registration_number' => $registrationNumber,
                'vehicle_type' => $row['vehicle_type'] ?? null,
                'is_active' => true,
            ];

            if ($assignedEmployeeId) {
                $attributes['assigned_employee_id'] = $assignedEmployeeId;
            }

            Vehicle::updateOrCreate(
                ['vehicle_number' => $vehicleNumber],
                $attributes
            );
        }
    }
}
