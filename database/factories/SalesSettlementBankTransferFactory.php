<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\SalesSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesSettlementBankTransfer>
 */
class SalesSettlementBankTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_settlement_id' => SalesSettlement::factory(),
            'bank_account_id' => BankAccount::factory(),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'reference_number' => fake()->optional()->numerify('TRF-######'),
            'transfer_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
