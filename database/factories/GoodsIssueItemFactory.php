<?php

namespace Database\Factories;

use App\Models\GoodsIssueItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsIssueItem>
 */
class GoodsIssueItemFactory extends Factory
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
            'unit_cost' => 0,
            'selling_price' => 0,
        ];
    }
}
