<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChartOfAccount>
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
            'account_type_id' => \App\Models\AccountType::factory(),
            'currency_id' => \App\Models\Currency::factory(),
        ];
    }
}
