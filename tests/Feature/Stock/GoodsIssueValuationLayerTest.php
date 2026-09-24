<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
        'status' => 'open',
    ]);

    $currency = Currency::factory()->create(['is_base_currency' => true]);
    $assetType = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);

    foreach (['1151' => 'Stock In Hand', '1155' => 'Van Stock'] as $code => $name) {
        ChartOfAccount::create([
            'account_code' => $code,
            'account_name' => $name,
            'account_type_id' => $assetType->id,
            'currency_id' => $currency->id,
            'is_active' => true,
            'normal_balance' => 'debit',
        ]);
    }

    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->vehicle = Vehicle::factory()->create();
    $this->employee = Employee::factory()->create();
    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'unit_cost' => 150,
        'selling_price' => 200,
        'status' => 'active',
    ]);

    // 80 received on the GRN, plus 23 found on a later stock count: one batch, two layers.
    // This is the shape that used to leave current_stock over-stated, because a goods
    // issue emptied the first layer and dropped whatever it could not cover.
    issueLayer(80, '2026-05-01');
    issueLayer(23, '2026-05-10');

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $this->batch->id,
        'quantity_on_hand' => 103,
        'unit_cost' => 150,
        'total_value' => 15450,
        'selling_price' => 200,
        'status' => 'active',
    ]);

    CurrentStock::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => 103,
        'quantity_available' => 103,
        'average_cost' => 150,
        'total_value' => 15450,
        'total_batches' => 1,
    ]);
});

function issueLayer(float $quantity, string $receiptDate): StockValuationLayer
{
    $context = test();

    $movement = StockMovement::create([
        'movement_type' => 'grn',
        'movement_date' => $receiptDate,
        'product_id' => $context->product->id,
        'stock_batch_id' => $context->batch->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity' => $quantity,
        'uom_id' => $context->uom->id,
        'unit_cost' => 150,
        'total_value' => $quantity * 150,
        'created_by' => $context->user->id,
    ]);

    return StockValuationLayer::create([
        'product_id' => $context->product->id,
        'warehouse_id' => $context->warehouse->id,
        'stock_batch_id' => $context->batch->id,
        'stock_movement_id' => $movement->id,
        'receipt_date' => $receiptDate,
        'quantity_received' => $quantity,
        'quantity_remaining' => $quantity,
        'unit_cost' => 150,
        'total_value' => $quantity * 150,
        'value_remaining' => $quantity * 150,
    ]);
}

function postIssueOf(float $quantity): array
{
    $context = test();

    $goodsIssue = GoodsIssue::create([
        'warehouse_id' => $context->warehouse->id,
        'vehicle_id' => $context->vehicle->id,
        'employee_id' => $context->employee->id,
        'issued_by' => $context->user->id,
        'issue_date' => now(),
        'issue_number' => 'GI-LAYER-1',
        'status' => 'draft',
    ]);

    GoodsIssueItem::create([
        'goods_issue_id' => $goodsIssue->id,
        'product_id' => $context->product->id,
        'uom_id' => $context->uom->id,
        'quantity_issued' => $quantity,
        'unit_cost' => 150,
        'selling_price' => 200,
        'total_cogs' => $quantity * 150,
        'total_value' => $quantity * 200,
    ]);

    return app(DistributionService::class)->postGoodsIssue($goodsIssue->refresh());
}

it('takes an issue larger than the first layer out of the next one too', function () {
    $result = postIssueOf(90);

    expect($result['success'])->toBeTrue();

    // 103 on hand less 90 issued leaves 13, in the newer layer.
    expect((float) StockValuationLayer::where('stock_batch_id', $this->batch->id)->sum('quantity_remaining'))->toBe(13.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $this->batch->id)->value('quantity_on_hand'))->toBe(13.0);
});

it('leaves current_stock showing the same quantity as the batch rows behind it', function () {
    postIssueOf(90);

    // The current-stock page reads current_stock and its batch modal reads
    // current_stock_by_batch; the two showing different numbers is the defect.
    expect((float) CurrentStock::where('product_id', $this->product->id)->value('quantity_on_hand'))->toBe(13.0);
});
