<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliation>
 */
class BankReconciliationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'statement_date' => now()->endOfMonth()->toDateString(),
            'statement_balance' => 0,
            'opening_balance' => 0,
            'book_balance' => 0,
            'difference' => 0,
            'status' => BankReconciliation::STATUS_IN_PROGRESS,
        ];
    }
}
