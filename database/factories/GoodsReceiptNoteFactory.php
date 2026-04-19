<?php

namespace Database\Factories;

use App\Models\GoodsReceiptNote;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptNote>
 */
class GoodsReceiptNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grn_number' => 'GRN-TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'receipt_date' => now(),
            'status' => 'draft',
            'total_quantity' => 0,
            'total_amount' => 0,
            'tax_amount' => 0,
            'freight_charges' => 0,
            'other_charges' => 0,
            'grand_total' => 0,
            'received_by' => User::factory(),
            'warehouse_id' => Warehouse::factory(),
        ];
    }
}
