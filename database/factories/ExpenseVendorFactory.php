<?php

namespace Database\Factories;

use App\Models\ExpenseVendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseVendor>
 */
class ExpenseVendorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'category' => 'shop',
            'phone' => fake()->numerify('03#########'),
            'city' => fake()->city(),
            'is_active' => true,
        ];
    }
}
