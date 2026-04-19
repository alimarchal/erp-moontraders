<?php

namespace Database\Factories;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsIssue>
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
        $currency = Currency::first() ?? Currency::factory()->base()->create();
        $assetType = AccountType::firstOrCreate(
            ['type_name' => 'Asset', 'report_group' => 'BalanceSheet'],
            ['description' => 'Auto-created for GoodsIssue factory']
        );

        $stockInHand = ChartOfAccount::firstOrCreate(
            ['account_code' => '1151'],
            [
                'account_name' => 'Stock In Hand',
                'account_type_id' => $assetType->id,
                'currency_id' => $currency->id,
                'normal_balance' => 'debit',
                'is_group' => false,
                'is_active' => true,
            ]
        );

        $vanStock = ChartOfAccount::firstOrCreate(
            ['account_code' => '1155'],
            [
                'account_name' => 'Van Stock',
                'account_type_id' => $assetType->id,
                'currency_id' => $currency->id,
                'normal_balance' => 'debit',
                'is_group' => false,
                'is_active' => true,
            ]
        );

        return [
            'issue_number' => 'GI-TEST-'.fake()->unique()->numberBetween(1000, 9999),
            'issue_date' => now(),
            'status' => 'draft',
            'total_quantity' => 0,
            'total_value' => 0,
            'employee_id' => Employee::factory(),
            'vehicle_id' => Vehicle::factory(),
            'warehouse_id' => Warehouse::factory(),
            'issued_by' => User::factory(),
            'stock_in_hand_account_id' => $stockInHand->id,
            'van_stock_account_id' => $vanStock->id,
        ];
    }
}
