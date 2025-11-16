<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_code' => strtoupper($this->faker->unique()->lexify('PROD-???-####')),
            'product_name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'weight' => $this->faker->optional()->randomFloat(3, 0.1, 100),
            'pack_size' => $this->faker->optional()->randomElement(['1kg', '500g', '1L', '2L', '500ml']),
            'barcode' => $this->faker->boolean(70) ? $this->faker->unique()->ean13() : null,
            'brand' => $this->faker->optional()->company(),
            'valuation_method' => $this->faker->randomElement(['FIFO', 'LIFO', 'Average', 'Standard']),
            'reorder_level' => $this->faker->numberBetween(10, 100),
            'unit_sell_price' => $this->faker->randomFloat(2, 10, 1000),
            'cost_price' => $this->faker->randomFloat(2, 5, 800),
            'is_active' => true,
        ];
    }
}
