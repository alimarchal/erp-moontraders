<?php

use App\Models\AccountingPeriod;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\StockBatch;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────

function makeDraftEditSetup(): array
{
    AccountingPeriod::firstOrCreate(
        ['name' => now()->format('F Y')],
        [
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]
    );

    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);
    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'settlement_date' => now()->toDateString(),
        'notes' => 'Original notes',
    ]);

    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => 150,
        'total_sales_value' => 1500,
        'unit_cost' => 100,
        'total_cogs' => 1000,
    ]);

    return compact('superAdmin', 'employee', 'vehicle', 'warehouse', 'product', 'goodsIssue', 'settlement');
}

function draftEditPayload(array $setup, array $overrides = []): array
{
    return array_merge([
        'settlement_date' => $setup['settlement']->settlement_date->toDateString(),
        'goods_issue_id' => $setup['goodsIssue']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'notes' => 'Corrected notes',
        'denom_5000' => 0, 'denom_1000' => 1, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ], $overrides);
}

it('blocks changing to a Goods Issue that already has another settlement', function () {
    $setup = makeDraftEditSetup();

    $otherGoodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'employee_id' => $setup['employee']->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'warehouse_id' => $setup['warehouse']->id,
    ]);

    SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $otherGoodsIssue->id,
        'settlement_number' => 'SETTLE-TEST-9001',
    ]);

    $this->actingAs($setup['superAdmin'])
        ->put(route('sales-settlements.update', $setup['settlement']), draftEditPayload($setup, [
            'goods_issue_id' => $otherGoodsIssue->id,
        ]))
        ->assertRedirect()
        ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'already exists for this Goods Issue'));

    expect($setup['settlement']->fresh()->notes)->toBe('Original notes');
});

// ──────────────────────────────────────────────────────
// Regression: edit grid must use the settlement's saved batch price,
// not the live stock price (which may have changed since it was saved)
// ──────────────────────────────────────────────────────

it('loads the saved historical batch selling price into the edit grid, not the current stock batch price', function () {
    $setup = makeDraftEditSetup();
    $settlement = $setup['settlement'];

    $goodsIssueItem = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $setup['goodsIssue']->id,
        'line_no' => 1,
        'product_id' => $setup['product']->id,
        'uom_id' => Uom::factory()->create()->id,
        'quantity_issued' => 1,
        'unit_cost' => 2696.66,
        'selling_price' => 2881.97,
        'total_value' => 2881.97,
    ]);

    // Price on the stock batch has moved since the settlement was saved.
    $stockBatch = StockBatch::factory()->create([
        'product_id' => $setup['product']->id,
        'unit_cost' => 2696.66,
        'selling_price' => 3026.07,
    ]);

    $item = SalesSettlementItem::where('sales_settlement_id', $settlement->id)->firstOrFail();
    $item->update(['goods_issue_item_id' => $goodsIssueItem->id]);

    SalesSettlementItemBatch::create([
        'sales_settlement_item_id' => $item->id,
        'stock_batch_id' => $stockBatch->id,
        'batch_code' => $stockBatch->batch_code,
        'quantity_issued' => 1,
        'quantity_sold' => 1,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_cost' => 2696.66,
        'selling_price' => 2881.97,
        'is_promotional' => false,
    ]);

    $response = $this->actingAs($setup['superAdmin'])
        ->get(route('sales-settlements.edit', $settlement, absolute: false));

    $response->assertSuccessful();

    $savedBatches = null;
    if (preg_match('/const savedBatchQuantities = (\{.*?\});/', $response->getContent(), $matches)) {
        $savedBatches = json_decode($matches[1], true);
    }

    $key = $goodsIssueItem->id.'_'.$stockBatch->id;

    expect($savedBatches)->not->toBeNull()
        ->and($savedBatches)->toHaveKey($key)
        ->and((float) $savedBatches[$key]['selling_price'])->toBe(2881.97)
        ->and((float) $savedBatches[$key]['unit_cost'])->toBe(2696.66);
});

it('saves the settlement\'s saved batch price even when the client submits the drifted live price', function () {
    $setup = makeDraftEditSetup();
    $settlement = $setup['settlement'];

    $goodsIssueItem = GoodsIssueItem::factory()->create([
        'goods_issue_id' => $setup['goodsIssue']->id,
        'line_no' => 1,
        'product_id' => $setup['product']->id,
        'uom_id' => Uom::factory()->create()->id,
        'quantity_issued' => 1,
        'unit_cost' => 2696.66,
        'selling_price' => 2881.97,
        'total_value' => 2881.97,
    ]);

    $stockBatch = StockBatch::factory()->create([
        'product_id' => $setup['product']->id,
        'unit_cost' => 2696.66,
        'selling_price' => 3026.07,
    ]);

    $item = SalesSettlementItem::where('sales_settlement_id', $settlement->id)->firstOrFail();
    $item->update(['goods_issue_item_id' => $goodsIssueItem->id, 'quantity_issued' => 1, 'quantity_sold' => 1, 'total_sales_value' => 2881.97, 'total_cogs' => 2696.66]);

    SalesSettlementItemBatch::create([
        'sales_settlement_item_id' => $item->id,
        'stock_batch_id' => $stockBatch->id,
        'batch_code' => $stockBatch->batch_code,
        'quantity_issued' => 1,
        'quantity_sold' => 1,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_cost' => 2696.66,
        'selling_price' => 2881.97,
        'is_promotional' => false,
    ]);

    $this->actingAs($setup['superAdmin'])
        ->put(route('sales-settlements.update', $settlement), draftEditPayload($setup, [
            'items' => [[
                'product_id' => $setup['product']->id,
                'goods_issue_item_id' => $goodsIssueItem->id,
                'quantity_issued' => 1,
                'quantity_sold' => 1,
                'quantity_returned' => 0,
                'quantity_shortage' => 0,
                'unit_cost' => 2696.66,
                'selling_price' => 3026.07,
                'batches' => [[
                    'stock_batch_id' => $stockBatch->id,
                    'batch_code' => $stockBatch->batch_code,
                    'quantity_issued' => 1,
                    'quantity_sold' => 1,
                    'quantity_returned' => 0,
                    'quantity_shortage' => 0,
                    'unit_cost' => 2696.66,
                    'selling_price' => 3026.07, // drifted live price sent by the client
                    'is_promotional' => false,
                ]],
            ]],
            'denom_5000' => 0, 'denom_1000' => 2, 'denom_500' => 1, 'denom_100' => 3,
            'denom_50' => 1, 'denom_20' => 1, 'denom_10' => 1, 'denom_coins' => 1.97,
        ]))
        ->assertRedirect(route('sales-settlements.show', $settlement))
        ->assertSessionHas('success');

    $rebuiltBatch = SalesSettlementItemBatch::whereHas('salesSettlementItem', fn ($q) => $q->where('sales_settlement_id', $settlement->id))
        ->where('stock_batch_id', $stockBatch->id)
        ->firstOrFail();

    expect((float) $rebuiltBatch->selling_price)->toBe(2881.97)
        ->and((float) $rebuiltBatch->unit_cost)->toBe(2696.66)
        ->and((float) $settlement->fresh()->total_sales_amount)->toBe(2881.97);
});

it('offers no Special Edit on a posted settlement, only Reverse Settlement', function () {
    $setup = makeDraftEditSetup();
    $setup['settlement']->update(['status' => 'posted', 'posted_at' => now()]);

    expect(Route::has('sales-settlements.edit-special'))->toBeFalse()
        ->and(Route::has('sales-settlements.update-special'))->toBeFalse();

    $this->actingAs($setup['superAdmin'])
        ->get(route('sales-settlements.show', $setup['settlement']))
        ->assertSuccessful()
        ->assertDontSee('Special Edit')
        ->assertSee('Reverse Settlement');
});
