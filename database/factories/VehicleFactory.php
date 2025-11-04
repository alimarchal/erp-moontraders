<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_number' => strtoupper($this->faker->unique()->bothify('VH-####')),
            'registration_number' => strtoupper($this->faker->unique()->bothify('REG-####')),
            'vehicle_type' => $this->faker->randomElement(['Truck', 'Van', 'Pickup']),
            'make_model' => $this->faker->company(),
            'year' => (string) $this->faker->numberBetween(2005, (int) date('Y')),
            'assigned_employee_id' => null,
            'is_active' => true,
        ];
    }
}
