<?php

namespace Database\Factories;

use App\Models\BankReceiptScan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReceiptScan>
 */
class BankReceiptScanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scan_date' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 1000, 500000),
            'file_name' => fake()->word().'.jpg',
            'file_path' => 'bank-receipt-scans/'.fake()->uuid().'.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 20480,
            'status' => BankReceiptScan::STATUS_PENDING,
        ];
    }
}
