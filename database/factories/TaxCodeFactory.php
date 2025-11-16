<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaxCode>
 */
class TaxCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tax_code' => strtoupper($this->faker->unique()->lexify('TAX-???')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'tax_type' => $this->faker->randomElement(['sales_tax', 'gst', 'vat', 'withholding_tax', 'excise', 'customs_duty']),
            'calculation_method' => 'percentage',
            'tax_payable_account_id' => null,
            'tax_receivable_account_id' => null,
            'is_active' => true,
            'is_compound' => false,
            'included_in_price' => false,
        ];
    }
}
