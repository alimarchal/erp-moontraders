<?php

namespace Database\Factories;

use App\Models\CashPayoutDenomination;
use App\Models\ExpenseVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashPayoutDenomination>
 */
class CashPayoutDenominationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payable_type' => ExpenseVoucher::class,
            'payable_id' => ExpenseVoucher::factory(),
            'denom_5000' => 0,
            'denom_1000' => fake()->numberBetween(0, 5),
            'denom_500' => fake()->numberBetween(0, 5),
            'denom_100' => fake()->numberBetween(0, 5),
            'denom_50' => 0,
            'denom_20' => 0,
            'denom_10' => 0,
            'denom_coins' => 0,
        ];
    }
}
