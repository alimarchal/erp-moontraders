<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basicSalary = fake()->randomFloat(2, 15000, 80000);
        $allowances = fake()->randomFloat(2, 2000, 15000);
        $deductions = fake()->randomFloat(2, 0, 5000);

        return [
            'employee_id' => Employee::inRandomOrder()->value('id') ?? Employee::factory(),
            'basic_salary' => $basicSalary,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => $basicSalary + $allowances - $deductions,
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'effective_to' => null,
            'is_active' => true,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'effective_to' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'effective_to' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }
}
