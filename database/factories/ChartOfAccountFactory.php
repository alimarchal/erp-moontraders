<?php

namespace Database\Factories;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartOfAccount>
 */
class ChartOfAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_code' => fake()->unique()->numerify('####'),
            'account_name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => fake()->randomElement(['debit', 'credit']),
            'account_type_id' => AccountType::factory(),
            'currency_id' => Currency::factory(),
        ];
    }
}
