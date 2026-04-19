<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesSettlement>
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
            'settlement_number' => 'SETTLE-TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'settlement_date' => now(),
            'goods_issue_id' => GoodsIssue::factory(),
            'status' => 'draft',
            'cash_sales_amount' => 0,
            'credit_sales_amount' => 0,
            'cheque_sales_amount' => 0,
            'total_sales_amount' => 0,
            'employee_id' => Employee::factory(),
            'vehicle_id' => Vehicle::factory(),
            'warehouse_id' => Warehouse::factory(),
        ];
    }
}
