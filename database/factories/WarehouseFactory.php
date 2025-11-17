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
            'warehouse_name' => fake()->company() . ' Warehouse',
            'disabled' => fake()->boolean(10),
            'is_group' => fake()->boolean(20),
            'is_rejected_warehouse' => fake()->boolean(10),
            'phone_no' => fake()->phoneNumber(),
            'mobile_no' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'pin' => fake()->postcode(),
        ];
    }
}
