<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesSettlement>
 */
class SalesSettlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'settlement_number' => 'SETTLE-TEST-' . fake()->unique()->numberBetween(1000, 9999),
            'settlement_date' => now(),
            'goods_issue_id' => \App\Models\GoodsIssue::factory(),
            'status' => 'draft',
            'cash_sales_amount' => 0,
            'credit_sales_amount' => 0,
            'cheque_sales_amount' => 0,
            'total_sales_amount' => 0,
            'employee_id' => \App\Models\Employee::factory(),
            'vehicle_id' => \App\Models\Vehicle::factory(),
            'warehouse_id' => \App\Models\Warehouse::factory(),
        ];
    }
}
