<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankStatementEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatementEntry>
 */
class BankStatementEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('2024-07-01', '2025-06-30')->format('Y-m-d');
        $reference = 'CHQ '.fake()->unique()->numerify('########');
        $credit = fake()->randomFloat(2, 1000, 900000);
        $accountId = BankAccount::factory();

        return [
            'bank_account_id' => $accountId,
            'posting_date' => $date,
            'reference' => $reference,
            'narration' => $reference.' | '.strtoupper(fake()->name()),
            'party_name_in_statement' => strtoupper(fake()->name()),
            'debit' => 0,
            'credit' => $credit,
            'status' => BankStatementEntry::STATUS_UNCLASSIFIED,
            'classification_source' => 'unresolved',
            'needs_review' => false,
            'dedupe_hash' => fn () => hash('sha256', fake()->unique()->uuid()),
        ];
    }

    public function debit(?float $amount = null): static
    {
        return $this->state(fn () => ['debit' => $amount ?? fake()->randomFloat(2, 1000, 900000), 'credit' => 0]);
    }

    public function credit(?float $amount = null): static
    {
        return $this->state(fn () => ['credit' => $amount ?? fake()->randomFloat(2, 1000, 900000), 'debit' => 0]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
