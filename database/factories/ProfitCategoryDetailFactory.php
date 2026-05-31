<?php

namespace Database\Factories;

use App\Models\ProfitCategory;
use App\Models\ProfitCategoryDetail;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitCategoryDetail>
 */
class ProfitCategoryDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profit_category_id' => ProfitCategory::factory(),
            'supplier_id' => Supplier::factory(),
            'transaction_date' => fake()->date(),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
