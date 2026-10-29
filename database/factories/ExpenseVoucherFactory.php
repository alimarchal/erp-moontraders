<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\ExpenseVoucher;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseVoucher>
 */
class ExpenseVoucherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_number' => 'EV-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'voucher_date' => now()->format('Y-m-d'),
            'supplier_id' => Supplier::factory(),
            'party_name' => fake()->name(),
            'expense_account_id' => ChartOfAccount::factory(),
            'amount' => fake()->numberBetween(100, 5000),
            'narration' => fake()->sentence(),
            'status' => ExpenseVoucher::STATUS_DRAFT,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ExpenseVoucher::STATUS_PENDING, 'submitted_at' => now()]);
    }
}
