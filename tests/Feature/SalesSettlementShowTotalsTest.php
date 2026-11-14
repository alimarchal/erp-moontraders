<?php

use App\Models\GoodsIssue;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\StockBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows value totals for sold, returned, and shortage quantities on settlement show page', function () {
    $user = User::factory()->create(['is_super_admin' => 'Yes']);

    $goodsIssue = GoodsIssue::factory()->create();
    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $goodsIssue->employee_id,
        'vehicle_id' => $goodsIssue->vehicle_id,
        'warehouse_id' => $goodsIssue->warehouse_id,
    ]);

    $product = Product::factory()->create();
    $item = SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_issued' => 5,
        'quantity_sold' => 2,
        'quantity_returned' => 1,
        'quantity_shortage' => 1,
        'unit_selling_price' => 100,
        'total_sales_value' => 200,
        'unit_cost' => 60,
        'total_cogs' => 120,
    ]);

    $stockBatch = StockBatch::factory()->create([
        'product_id' => $product->id,
        'unit_cost' => 60,
        'selling_price' => 100,
    ]);

    SalesSettlementItemBatch::create([
        'sales_settlement_item_id' => $item->id,
        'stock_batch_id' => $stockBatch->id,
        'batch_code' => $stockBatch->batch_code,
        'quantity_issued' => 5,
        'quantity_sold' => 2,
        'quantity_returned' => 1,
        'quantity_shortage' => 1,
        'unit_cost' => 60,
        'selling_price' => 100,
        'is_promotional' => false,
    ]);

    $this->actingAs($user)
        ->get(route('sales-settlements.show', $settlement))
        ->assertSuccessful()
        ->assertSee('Totals:')
        ->assertSee('200.00')
        ->assertSee('100.00');
});

it('shows return and shortage values in the sales summary on the show page and print sheet', function () {
    $user = User::factory()->create(['is_super_admin' => 'Yes']);

    $goodsIssue = GoodsIssue::factory()->create();
    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $goodsIssue->employee_id,
        'vehicle_id' => $goodsIssue->vehicle_id,
        'warehouse_id' => $goodsIssue->warehouse_id,
    ]);

    $product = Product::factory()->create();
    $item = SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_issued' => 200,
        'quantity_sold' => 180,
        'quantity_returned' => 15,
        'quantity_shortage' => 5,
        'unit_selling_price' => 85.05,
        'total_sales_value' => 15309,
        'unit_cost' => 70,
        'total_cogs' => 12600,
    ]);

    $stockBatch = StockBatch::factory()->create([
        'product_id' => $product->id,
        'unit_cost' => 70,
        'selling_price' => 85.05,
    ]);

    SalesSettlementItemBatch::create([
        'sales_settlement_item_id' => $item->id,
        'stock_batch_id' => $stockBatch->id,
        'batch_code' => $stockBatch->batch_code,
        'quantity_issued' => 200,
        'quantity_sold' => 180,
        'quantity_returned' => 15,
        'quantity_shortage' => 5,
        'unit_cost' => 70,
        'selling_price' => 85.05,
        'is_promotional' => false,
    ]);

    $this->actingAs($user)
        ->get(route('sales-settlements.show', $settlement))
        ->assertSuccessful()
        ->assertSeeInOrder(['Return Value', '1,275.75', 'Shortage Value', '425.25', 'Recovery (Cash)']);

    $this->actingAs($user)
        ->get(route('sales-settlements.show', [$settlement, 'layout' => 'print2']))
        ->assertSuccessful()
        ->assertSeeInOrder(['Return value', '1,275.75', 'Shortage value', '425.25', 'Recovery (cash)']);
});
