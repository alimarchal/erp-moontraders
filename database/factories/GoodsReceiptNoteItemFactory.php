<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoodsReceiptNoteItem>
 */
class GoodsReceiptNoteItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_uom_id' => \App\Models\Uom::factory(),
            'stock_uom_id' => \App\Models\Uom::factory(),
            'qty_in_purchase_uom' => 1,
            'uom_conversion_factor' => 1,
            'qty_in_stock_uom' => 1,
            'quantity_ordered' => 0,
            'quantity_received' => 0,
            'quantity_accepted' => 0,
            'unit_cost' => 0,
            'total_cost' => 0,
            'is_promotional' => false,
            'priority_order' => 99,
        ];
    }
}
