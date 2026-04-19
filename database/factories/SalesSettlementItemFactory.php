<?php

namespace Database\Factories;

use App\Models\SalesSettlementItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesSettlementItem>
 */
class SalesSettlementItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quantity_issued' => 0,
            'quantity_sold' => 0,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_selling_price' => 0,
            'total_sales_value' => 0,
            'unit_cost' => 0,
            'total_cogs' => 0,
        ];
    }
}
