<?php

use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->vehicle = Vehicle::factory()->create();
    $this->employee = Employee::factory()->create();
    $this->uom = Uom::factory()->create(['symbol' => 'Pc']);
    $this->product = Product::factory()->create([
        'uom_id' => $this->uom->id,
    ]);
});

test('sales settlement store validates required fields', function () {
    $response = $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), []);

    $response->assertSessionHasErrors(['settlement_date', 'goods_issue_id', 'items']);
});

test('sales settlement store requires items array', function () {
    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'employee_id' => $this->employee->id,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'goods_issue_id' => $goodsIssue->id,
        ]);

    $response->assertSessionHasErrors(['items']);
});

test('sales settlement validation accepts selling_price at batch level only', function () {
    $stockBatch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'batch_code' => 'BATCH-001',
        'unit_cost' => 100,
        'selling_price' => 120,
    ]);

    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'employee_id' => $this->employee->id,
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $this->product->id,
        'uom_id' => $this->uom->id,
        'quantity_issued' => 100,
        'unit_cost' => 100,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'goods_issue_id' => $goodsIssue->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_issued' => 100,
                    'quantity_sold' => 80,
                    'quantity_returned' => 10,
                    'quantity_shortage' => 5,
                    'unit_cost' => 100,
                    'batches' => [
                        [
                            'stock_batch_id' => $stockBatch->id,
                            'batch_code' => 'BATCH-001',
                            'quantity_issued' => 100,
                            'quantity_sold' => 80,
                            'quantity_returned' => 10,
                            'quantity_shortage' => 5,
                            'unit_cost' => 100,
                            'selling_price' => 120,
                            'is_promotional' => false,
                        ],
                    ],
                ],
            ],
        ]);

    $response->assertSessionDoesntHaveErrors(['items.0.selling_price']);
});

test('sales settlement validation allows expense fields', function () {
    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'employee_id' => $this->employee->id,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'goods_issue_id' => $goodsIssue->id,
            'items' => [],
            'expense_vehicle_rent' => 500.00,
            'expense_fuel' => 200.00,
            'expense_loading_unloading' => 100.00,
            'expense_other' => 50.00,
        ]);

    $response->assertSessionDoesntHaveErrors([
        'expense_vehicle_rent',
        'expense_fuel',
        'expense_loading_unloading',
        'expense_other',
    ]);
});

test('sales settlement validation allows denomination fields', function () {
    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'employee_id' => $this->employee->id,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'goods_issue_id' => $goodsIssue->id,
            'items' => [],
            'denomination_5000' => 2,
            'denomination_1000' => 5,
            'denomination_500' => 10,
            'denomination_100' => 20,
            'denomination_50' => 30,
            'denomination_20' => 10,
            'denomination_10' => 5,
            'denomination_5' => 2,
            'denomination_2' => 1,
            'denomination_1' => 0,
        ]);

    $response->assertSessionDoesntHaveErrors([
        'denomination_5000',
        'denomination_1000',
        'denomination_500',
        'denomination_100',
        'denomination_50',
        'denomination_20',
        'denomination_10',
        'denomination_5',
        'denomination_2',
        'denomination_1',
    ]);
});
