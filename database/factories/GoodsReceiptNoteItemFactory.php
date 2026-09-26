<?php

namespace Database\Factories;

use App\Models\GoodsReceiptNoteItem;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptNoteItem>
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
            'purchase_uom_id' => Uom::factory(),
            'stock_uom_id' => Uom::factory(),
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

    /**
     * A line saved through the GRN screens always carries total_cost = quantity × unit cost,
     * and its invoice value; posting refuses a line where they disagree. Fill both in when a
     * test only states the quantity and unit cost.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (GoodsReceiptNoteItem $item): void {
            $lineValue = round((float) ($item->quantity_accepted ?? $item->quantity_received) * (float) $item->unit_cost, 4);

            if ((float) $item->total_cost === 0.0 && $lineValue > 0) {
                $item->total_cost = $lineValue;
            }

            if ((float) $item->extended_value === 0.0 && (float) $item->total_cost > 0) {
                $item->extended_value = $item->total_cost;
            }
        });
    }
}
