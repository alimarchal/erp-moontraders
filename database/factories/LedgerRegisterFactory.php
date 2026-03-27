<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\LedgerRegister;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerRegister>
 */
class LedgerRegisterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(DocumentType::cases());

        return [
            'supplier_id' => Supplier::inRandomOrder()->value('id') ?? Supplier::factory(),
            'transaction_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'document_type' => $type,
            'document_number' => $type === DocumentType::Dr ? (string) fake()->numerify('107352####') : null,
            'sap_code' => null,
            'online_amount' => $type === DocumentType::Dz ? fake()->randomFloat(2, 1000000, 10000000) : 0,
            'invoice_amount' => $type === DocumentType::Dr ? fake()->randomFloat(2, 10000, 5000000) : 0,
            'expenses_amount' => 0,
            'za_point_five_percent_amount' => $type === DocumentType::Dr ? fake()->randomFloat(2, 100, 60000) : 0,
            'claim_adjust_amount' => $type === DocumentType::Dg ? fake()->randomFloat(2, 50000, 300000) : 0,
            'balance' => 0,
            'remarks' => fake()->optional(0.2)->sentence(),
        ];
    }

    public function online(): static
    {
        return $this->state(fn () => [
            'document_type' => DocumentType::Dz,
            'document_number' => null,
            'online_amount' => fake()->randomFloat(2, 1000000, 10000000),
            'invoice_amount' => 0,
            'expenses_amount' => 0,
            'za_point_five_percent_amount' => 0,
            'claim_adjust_amount' => 0,
        ]);
    }

    public function invoice(): static
    {
        $invoiceAmount = fake()->randomFloat(2, 10000, 12000000);

        return $this->state(fn () => [
            'document_type' => DocumentType::Dr,
            'document_number' => (string) fake()->numerify('107352####'),
            'online_amount' => 0,
            'invoice_amount' => $invoiceAmount,
            'expenses_amount' => 0,
            'za_point_five_percent_amount' => round($invoiceAmount * 0.005, 2),
            'claim_adjust_amount' => 0,
        ]);
    }

    public function claim(): static
    {
        return $this->state(fn () => [
            'document_type' => DocumentType::Dg,
            'document_number' => 'ST-26-'.fake()->numberBetween(1, 50),
            'online_amount' => 0,
            'invoice_amount' => 0,
            'expenses_amount' => 0,
            'za_point_five_percent_amount' => 0,
            'claim_adjust_amount' => fake()->randomFloat(2, 50000, 300000),
        ]);
    }
}
