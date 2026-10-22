<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankCheque;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankCheque>
 */
class BankChequeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'cheque_no' => fake()->unique()->numerify('########'),
            'direction' => 'issued',
            'cheque_date' => fake()->dateTimeBetween('2024-07-01', '2025-06-30')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 10000, 5000000),
            'payee_name' => fake()->company(),
            'status' => 'pending',
        ];
    }
}
