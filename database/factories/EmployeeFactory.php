<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => strtoupper(fake()->unique()->bothify('EMP-####')),
            'name' => fake()->name(),
            'company_name' => fake()->optional()->company(),
            'designation' => fake()->optional()->jobTitle(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->boolean(70) ? fake()->unique()->safeEmail() : null,
            'address' => fake()->optional()->address(),
            'hire_date' => fake()->optional()->date(),
            'is_active' => fake()->boolean(90),
        ];
    }
}
