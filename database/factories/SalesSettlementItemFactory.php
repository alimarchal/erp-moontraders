<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesSettlementItem>
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
