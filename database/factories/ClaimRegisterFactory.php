<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimRegister>
 */
class ClaimRegisterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $transactionDate = fake()->dateTimeBetween('-6 months', 'now');

        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $claimMonth = fake()->randomElement([
            fake()->randomElement($months).' '.fake()->year(),
            fake()->randomElement($months).'-'.fake()->randomElement($months).' '.fake()->year(),
            'Q'.fake()->numberBetween(1, 4).' '.fake()->year(),
        ]);

        $amount = fake()->randomFloat(2, 5000, 500000);

        return [
            'supplier_id' => Supplier::inRandomOrder()->value('id') ?? Supplier::factory(),
            'transaction_date' => $transactionDate,
            'reference_number' => 'ST-'.fake()->unique()->numerify('##-##'),
            'description' => fake()->randomElement([
                'TED '.$claimMonth,
                'Cerelac Margin/FMR Claim',
                'Rate Difference Claim',
                'Free Sampling Claim',
                'Trade Promotion Claim',
            ]),
            'claim_month' => $claimMonth,
            'date_of_dispatch' => fake()->optional(0.7)->dateTimeBetween($transactionDate, 'now'),
            'transaction_type' => 'claim',
            'debit' => $amount,  // Claim = debit
            'credit' => 0,
            'payment_method' => 'bank_transfer',
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function claim(): static
    {
        return $this->state(function () {
            $amount = fake()->randomFloat(2, 5000, 500000);

            return [
                'transaction_type' => 'claim',
                'debit' => $amount,
                'credit' => 0,
            ];
        });
    }

    public function recovery(): static
    {
        return $this->state(function () {
            $amount = fake()->randomFloat(2, 5000, 500000);

            return [
                'transaction_type' => 'recovery',
                'debit' => 0,
                'credit' => $amount,
            ];
        });
    }
}
