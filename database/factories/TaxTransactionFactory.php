<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaxTransaction>
 */
class TaxTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $taxableAmount = $this->faker->randomFloat(2, 100, 10000);
        $taxRate = $this->faker->randomFloat(2, 0, 30);
        $taxAmount = round($taxableAmount * $taxRate / 100, 2);

        return [
            'taxable_type' => 'App\\Models\\Product',
            'taxable_id' => 1,
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'taxable_amount' => $taxableAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'tax_direction' => $this->faker->randomElement(['payable', 'receivable']),
        ];
    }
}
