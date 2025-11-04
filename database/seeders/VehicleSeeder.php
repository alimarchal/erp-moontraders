<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VehicleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $excelPath = env('VEHICLE_DATA_PATH', '/Users/alirazamarchal/Library/CloudStorage/GoogleDrive-kh.marchal@gmail.com/My Drive/Moon Traders/Implementation Documents Received/vehicles.xlsx');
        $jsonPath = database_path('seeders/data/vehicles.json');

        $payload = [];

        if (file_exists($excelPath)) {
            try {
                $payload = $this->loadFromExcel($excelPath);
            } catch (\Throwable $e) {
                $this->command?->warn("Failed to parse vehicle Excel data: {$e->getMessage()}");
            }
        }

        if ($payload === [] && file_exists($jsonPath)) {
            $payload = json_decode(file_get_contents($jsonPath), true) ?? [];
        }

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
                'company_id' => isset($row['company_id']) && $row['company_id'] !== '' ? (int) $row['company_id'] : null,
                'supplier_id' => isset($row['supplier_id']) && $row['supplier_id'] !== '' ? (int) $row['supplier_id'] : null,
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
     * Load vehicle data directly from an Excel file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadFromExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2 || !isset($rows[2])) {
            return [];
        }

        $headerRow = $rows[2];
        $normalizedHeaders = [];
        foreach ($headerRow as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalizedHeaders[$column] = trim((string) $value);
        }

        $records = [];
        $seenVehicleNumbers = [];

        foreach ($rows as $index => $row) {
            if ($index <= 2) {
                continue;
            }

            $hasData = false;
            foreach ($row as $cell) {
                if ($cell !== null && $cell !== '') {
                    $hasData = true;
                    break;
                }
            }

            if (!$hasData) {
                continue;
            }

            $mapped = [];
            foreach ($normalizedHeaders as $column => $header) {
                $mapped[$header] = $row[$column] ?? null;
            }

            $rawNumber = $mapped['Van #'] ?? null;
            $vehicleNumber = $rawNumber === null ? null : strtoupper(trim((string) $rawNumber));

            if (!$vehicleNumber || isset($seenVehicleNumbers[$vehicleNumber])) {
                continue;
            }

            $seenVehicleNumbers[$vehicleNumber] = true;

            $vehicleType = $mapped['Vehicle Type'] ?? null;
            $vehicleType = $vehicleType === null ? null : trim((string) $vehicleType);
            $vehicleType = $vehicleType === '' ? null : $vehicleType;

            $record = [
                'vehicle_number' => $vehicleNumber,
                'registration_number' => $vehicleNumber,
                'vehicle_type' => $vehicleType,
                'driver_name' => $mapped['Driver Name'] ?? null,
                'driver_phone' => $mapped['Cell#'] ?? null,
                'company_id' => isset($mapped['company_id']) && is_numeric($mapped['company_id'])
                    ? (int) $mapped['company_id']
                    : null,
                'supplier_id' => isset($mapped['supplier_id']) && is_numeric($mapped['supplier_id'])
                    ? (int) $mapped['supplier_id']
                    : null,
            ];

            if (isset($record['driver_name'])) {
                $record['driver_name'] = trim((string) $record['driver_name']);
            }

            if (isset($record['driver_phone'])) {
                $record['driver_phone'] = preg_replace('/\\s+/', ' ', trim((string) $record['driver_phone']));
            }

            $records[] = $record;
        }

        return $records;
    }
}
