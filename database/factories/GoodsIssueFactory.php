<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoodsIssue>
 */
class GoodsIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_number' => 'GI-TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'issue_date' => now(),
            'status' => 'draft',
            'total_quantity' => 0,
            'total_value' => 0,
            'issued_by' => \App\Models\User::factory(),
        ];
    }
}
