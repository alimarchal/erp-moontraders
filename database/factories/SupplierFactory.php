<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_name' => fake()->company(),
            'short_name' => fake()->companySuffix(),
            'country' => fake()->country(),
            'supplier_group' => fake()->randomElement(['Wholesale', 'Retail', 'Manufacturer']),
            'supplier_type' => fake()->randomElement(['Local', 'International']),
            'is_transporter' => fake()->boolean(20),
            'is_internal_supplier' => fake()->boolean(10),
            'disabled' => fake()->boolean(10),
            'supplier_details' => fake()->optional()->sentence(),
            'website' => fake()->optional()->url(),
            'tax_id' => fake()->optional()->numerify('TAX-#######'),
            'sales_tax' => fake()->randomFloat(2, 5, 20),
            'pan_number' => fake()->optional()->bothify('PAN-#####??'),
        ];
    }
}
