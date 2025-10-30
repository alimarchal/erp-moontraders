<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Warehouse',
            'is_group_warehouse' => fake()->boolean(20),
            'chart_of_account_id' => null,
            'is_rejected_warehouse' => fake()->boolean(10),
            'company' => fake()->company(),
            'phone_no' => fake()->phoneNumber(),
            'mobile_no' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'state_province' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(90),
        ];
    }
}
