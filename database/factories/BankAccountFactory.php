<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_name' => fake()->company().' Main',
            'account_number' => fake()->unique()->numerify('####-####-####'),
            'bank_name' => fake()->randomElement(['HBL', 'MCB', 'UBL', 'ABL', 'NBP', 'Meezan Bank', 'Bank Alfalah']),
            'branch' => fake()->optional()->city(),
            'iban' => fake()->optional()->iban('PK'),
            'swift_code' => fake()->optional()->swiftBicNumber(),
            'chart_of_account_id' => null,
            'is_active' => true,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
