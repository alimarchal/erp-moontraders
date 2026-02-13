<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeSalaryTransaction>
 */
class EmployeeSalaryTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        return [
            'employee_id' => Employee::inRandomOrder()->value('id') ?? Employee::factory(),
            'transaction_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'reference_number' => 'SAL-'.fake()->unique()->numerify('####-###'),
            'transaction_type' => 'Salary',
            'description' => fake()->randomElement($months).' '.date('Y').' Salary',
            'salary_month' => fake()->randomElement($months).' '.date('Y'),
            'debit' => fake()->randomFloat(2, 15000, 60000),
            'credit' => 0,
            'status' => 'Pending',
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function salary(): static
    {
        return $this->state(fn () => [
            'transaction_type' => 'Salary',
            'debit' => fake()->randomFloat(2, 20000, 60000),
            'credit' => 0,
        ]);
    }

    public function advance(): static
    {
        return $this->state(fn () => [
            'transaction_type' => 'Advance',
            'reference_number' => 'ADV-'.fake()->unique()->numerify('####-###'),
            'description' => 'Advance Payment',
            'debit' => fake()->randomFloat(2, 5000, 30000),
            'credit' => 0,
        ]);
    }

    public function advanceRecovery(): static
    {
        return $this->state(fn () => [
            'transaction_type' => 'AdvanceRecovery',
            'reference_number' => 'ADVR-'.fake()->unique()->numerify('####-###'),
            'description' => 'Advance Recovery',
            'debit' => 0,
            'credit' => fake()->randomFloat(2, 2000, 15000),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'Paid',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'Pending',
        ]);
    }
}
