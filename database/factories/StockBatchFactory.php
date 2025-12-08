<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockBatch>
 */
class StockBatchFactory extends Factory
{
    protected $model = StockBatch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_code' => 'BATCH-'.fake()->unique()->numerify('######'),
            'product_id' => Product::factory(),
            'receipt_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'unit_cost' => fake()->randomFloat(2, 10, 500),
            'selling_price' => fake()->randomFloat(2, 15, 750),
            'is_promotional' => false,
            'priority_order' => 99,
            'status' => 'active',
            'is_active' => true,
        ];
    }

    public function promotional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_promotional' => true,
            'priority_order' => 1,
            'promotional_selling_price' => $attributes['selling_price'] * 0.8,
            'promotional_discount_percent' => 20,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }
}
