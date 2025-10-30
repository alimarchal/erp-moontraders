<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Currency>
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
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
        ];

        $currency = fake()->randomElement($currencies);

        return [
            'currency_code' => $currency['code'],
            'currency_name' => $currency['name'],
            'currency_symbol' => $currency['symbol'],
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
        return $this->state(fn(array $attributes) => [
            'is_base_currency' => true,
            'exchange_rate' => 1.000000,
        ]);
    }
}
