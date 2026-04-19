<?php

namespace Database\Factories;

use App\Models\ProductTaxMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTaxMapping>
 */
class ProductTaxMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_type' => $this->faker->randomElement(['sales', 'purchase', 'both']),
            'is_active' => true,
        ];
    }
}
