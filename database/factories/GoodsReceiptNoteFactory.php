<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoodsReceiptNote>
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
            'received_by' => \App\Models\User::factory(),
        ];
    }
}
