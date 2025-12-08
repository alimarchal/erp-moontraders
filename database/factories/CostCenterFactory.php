<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CostCenter>
 */
class CostCenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['cost_center', 'project']);

        return [
            'parent_id' => null,
            'code' => $type === 'cost_center' ? 'CC'.fake()->unique()->numberBetween(1000, 9999) : 'PROJ'.fake()->unique()->numberBetween(100, 999),
            'name' => $type === 'cost_center'
                ? fake()->randomElement(['Marketing', 'Sales', 'IT', 'HR', 'Finance', 'Operations']).' Department'
                : fake()->catchPhrase().' Project',
            'description' => fake()->optional()->paragraph(),
            'type' => $type,
            'start_date' => $type === 'project' ? fake()->dateTimeBetween('-1 year', 'now') : null,
            'end_date' => $type === 'project' ? fake()->dateTimeBetween('now', '+1 year') : null,
            'is_active' => fake()->boolean(90),
        ];
    }

    /**
     * Indicate that the cost center is a project.
     */
    public function project(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'project',
            'code' => 'PROJ'.fake()->unique()->numberBetween(100, 999),
            'start_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'end_date' => fake()->dateTimeBetween('now', '+1 year'),
        ]);
    }

    /**
     * Indicate that the cost center is a department.
     */
    public function department(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cost_center',
            'code' => 'CC'.fake()->unique()->numberBetween(1000, 9999),
            'start_date' => null,
            'end_date' => null,
        ]);
    }
}
