<?php

namespace Database\Factories;

use App\Models\ProductRecall;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductRecallFactory extends Factory
{
    protected $model = ProductRecall::class;

    public function definition(): array
    {
        return [
            'recall_number' => 'RCL-'.now()->year.'-'.fake()->unique()->numberBetween(1000, 9999),
            'recall_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'recall_type' => fake()->randomElement(['supplier_initiated', 'quality_issue', 'expiry', 'other']),
            'status' => 'draft',
            'total_quantity_recalled' => 0,
            'total_value' => 0,
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

    public function supplierInitiated(): static
    {
        return $this->state(fn (array $attributes) => [
            'recall_type' => 'supplier_initiated',
            'reason' => 'Supplier initiated product recall due to quality concerns',
        ]);
    }

    public function qualityIssue(): static
    {
        return $this->state(fn (array $attributes) => [
            'recall_type' => 'quality_issue',
            'reason' => 'Quality control detected defects in batch',
        ]);
    }

    public function expiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'recall_type' => 'expiry',
            'reason' => 'Products approaching expiry date',
        ]);
    }
}
