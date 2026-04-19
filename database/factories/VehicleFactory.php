<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
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
            'company_id' => null,
            'supplier_id' => null,
            'employee_id' => null,
            'driver_name' => $this->faker->optional()->name(),
            'driver_phone' => $this->faker->optional()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
