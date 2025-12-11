<?php

namespace Database\Factories;

use App\Models\SalesSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesSettlementCheque>
 */
class SalesSettlementChequeFactory extends Factory
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
            'cheque_number' => fake()->numerify('CHQ-######'),
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'bank_name' => fake()->randomElement(['HBL', 'MCB', 'UBL', 'ABL', 'NBP', 'Meezan Bank', 'Bank Alfalah', 'Faysal Bank']),
            'cheque_date' => fake()->dateTimeBetween('-7 days', '+30 days'),
            'account_holder_name' => fake()->optional()->name(),
            'status' => fake()->randomElement(['pending', 'cleared', 'bounced', 'cancelled']),
            'cleared_date' => fn (array $attributes) => $attributes['status'] === 'cleared' ? fake()->dateTimeBetween($attributes['cheque_date'], 'now') : null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the cheque is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'cleared_date' => null,
        ]);
    }

    /**
     * Indicate that the cheque is cleared.
     */
    public function cleared(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cleared',
            'cleared_date' => fake()->dateTimeBetween($attributes['cheque_date'] ?? '-7 days', 'now'),
        ]);
    }

    /**
     * Indicate that the cheque is bounced.
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'bounced',
            'cleared_date' => null,
        ]);
    }
}
