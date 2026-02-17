<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'adjustment_number' => 'SA-'.now()->year.'-'.fake()->unique()->numberBetween(1000, 9999),
            'adjustment_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'warehouse_id' => Warehouse::factory(),
            'adjustment_type' => fake()->randomElement(['damage', 'theft', 'count_variance', 'expiry', 'other']),
            'status' => 'draft',
            'reason' => fake()->sentence(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => 1,
        ]);
    }

    public function damage(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_type' => 'damage',
            'reason' => 'Product damaged during storage',
        ]);
    }

    public function recall(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_type' => 'recall',
            'reason' => 'Supplier initiated product recall',
        ]);
    }
}
