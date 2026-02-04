<?php

namespace Tests\Feature;

use App\Models\AccountType;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\User;
use App\Models\VanStockBatch;
use App\Models\VanStockBalance;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VanStockBatchConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create dependencies
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create GL Accounts
        $this->createGlAccounts();

        // Create Accounting Period
        AccountingPeriod::create([
            'name' => 'Test Period',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'status' => 'open',
        ]);
    }

    protected function createGlAccounts()
    {
        $currency = Currency::first() ?? Currency::factory()->create();
        $accountType = AccountType::first() ?? AccountType::factory()->create([
            'type_name' => 'Assets',
            'report_group' => 'BalanceSheet'
        ]);

        $codes = [
            '1121',
            '1122',
            '1123',
            '1171',
            '1111',
            '1141',
            '1161',
            '1151',
            '1155',
            '4110',
            '5111',
            '5272',
            '5252',
            '5262',
            '5292',
            '5282',
            '5223',
            '5213'
        ];

        foreach ($codes as $code) {
            ChartOfAccount::create([
                'account_code' => $code,
                'account_name' => "Account $code",
                'account_type_id' => $accountType->id,
                'currency_id' => $currency->id,
                'is_active' => true,
            ]);
        }
    }

    /** @test */
    public function test_it_consumes_van_stock_batches_fifo_on_settlement()
    {
        // 1. Setup Models
        $warehouse = Warehouse::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $product = Product::factory()->create();
        $employee = Employee::factory()->create();

        // 2. Create Van Stock Balance (Required by logic)
        VanStockBalance::create([
            'vehicle_id' => $vehicle->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 20, // Total
            'last_updated' => now(),
        ]);

        // 3. Create Van Stock Batches (FIFO Setup)
        // Batch A: Older
        $batchA = VanStockBatch::create([
            'vehicle_id' => $vehicle->id,
            'product_id' => $product->id,
            'goods_issue_number' => 'GI-001',
            'quantity_on_hand' => 10,
            'unit_cost' => 100,
            'selling_price' => 150,
            'created_at' => now()->subHour(),
        ]);

        // Batch B: Newer
        $batchB = VanStockBatch::create([
            'vehicle_id' => $vehicle->id,
            'product_id' => $product->id,
            'goods_issue_number' => 'GI-002',
            'quantity_on_hand' => 10,
            'unit_cost' => 120,
            'selling_price' => 180,
            'created_at' => now(),
        ]);

        // Create Goods Issue (Required for Settlement)
        $goodsIssue = \App\Models\GoodsIssue::factory()->create([
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        // 4. Create Settlement with Sales
        $settlement = SalesSettlement::create([
            'settlement_number' => 'SS-TEST-001',
            'settlement_date' => now(),
            'salesman_id' => $employee->id, // map employee to salesman
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'goods_issue_id' => $goodsIssue->id, // Linked
            'status' => 'draft',
        ]);

        SalesSettlementItem::create([
            'sales_settlement_id' => $settlement->id,
            'product_id' => $product->id,
            'quantity_sold' => 15, // Should consume all A (10) and half B (5)
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_price' => 200,
            'total_amount' => 3000,
        ]);

        // 5. Run Service
        $service = app(DistributionService::class);
        $result = $service->postSalesSettlement($settlement);

        if (isset($result['status']) && $result['status'] === 'error') {
            dump($result);
        }

        // 6. Verify Batches
        $this->assertDatabaseHas('van_stock_batches', [
            'id' => $batchA->id,
            'quantity_on_hand' => 0,
        ]);

        $this->assertDatabaseHas('van_stock_batches', [
            'id' => $batchB->id,
            'quantity_on_hand' => 5,
        ]);
    }
}
