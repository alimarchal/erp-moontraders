<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency_code' => fake()->unique()->currencyCode(),
            'currency_name' => fake()->words(2, true),
            'currency_symbol' => fake()->randomElement(['$', '€', '£', '¥', '₨', '₩', '₫', '₱', '₹', '₺']),
            'exchange_rate' => fake()->randomFloat(6, 0.5, 300),
            'is_base_currency' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the currency is the base currency.
     */
    public function base(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_base_currency' => true,
            'exchange_rate' => 1.000000,
        ]);
    }
}
