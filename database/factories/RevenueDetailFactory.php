<?php

namespace Database\Factories;

use App\Models\RevenueCategory;
use App\Models\RevenueDetail;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevenueDetail>
 */
class RevenueDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revenue_category_id' => RevenueCategory::factory(),
            'supplier_id' => Supplier::factory(),
            'transaction_date' => fake()->date(),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
