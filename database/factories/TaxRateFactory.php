<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaxRate>
 */
class TaxRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rate' => $this->faker->randomFloat(2, 0, 30),
            'effective_from' => now()->subDays(rand(1, 365)),
            'effective_to' => null,
            'region' => $this->faker->optional()->state(),
            'is_active' => true,
        ];
    }
}
