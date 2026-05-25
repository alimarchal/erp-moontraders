<?php

namespace Database\Factories;

use App\Models\RevenueCategory;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RevenueCategory>
 */
class RevenueCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'supplier_id' => Supplier::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
