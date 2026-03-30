<?php

namespace Database\Factories;

use App\Models\ExpenseDetail;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseDetail>
 */
class ExpenseDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 50000);

        return [
            'category' => fake()->randomElement(['stationary', 'tcs', 'tonner_it', 'salaries', 'fuel', 'van_work']),
            'supplier_id' => Supplier::inRandomOrder()->value('id') ?? Supplier::factory(),
            'transaction_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'description' => fake()->sentence(),
            'amount' => $amount,
            'debit' => $amount,
            'credit' => 0,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'posted_at' => now(),
            'posted_by' => User::factory(),
        ]);
    }

    public function category(string $category): static
    {
        return $this->state(fn () => [
            'category' => $category,
        ]);
    }
}
