<?php

use App\Models\CurrentStockByBatch;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\BatchTransferService;
use Illuminate\Support\Facades\DB;

/**
 * Daily snapshots and the inventory ledger are rebuilt from the per-product ledgers, so a batch
 * moved to another product must take its ledger rows with it — otherwise the stock keeps being
 * reported under the old product while current stock shows it under the new one.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->vehicle = Vehicle::factory()->create();
    $supplier = Supplier::factory()->create(['disabled' => false]);

    $this->oldProduct = Product::factory()->create(['supplier_id' => $supplier->id, 'uom_id' => $this->uom->id]);
    $this->newProduct = Product::factory()->create(['supplier_id' => $supplier->id, 'uom_id' => $this->uom->id]);

    $this->batch = StockBatch::factory()->create([
        'product_id' => $this->oldProduct->id,
        'supplier_id' => $supplier->id,
        'unit_cost' => 100,
    ]);

    // 100 received, 40 issued to a van and sold: 60 left in the warehouse.
    $receipt = batchMovement('grn', 100, '2026-05-01');
    batchLedgerRows($receipt, 100, 0, 'purchase');

    $issue = batchMovement('transfer', -40, '2026-05-02', $this->vehicle->id);
    batchLedgerRows($issue, 0, 40, 'transfer_out');
    batchMovement('sale', -40, '2026-05-02', $this->vehicle->id);

    StockValuationLayer::create([
        'product_id' => $this->oldProduct->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $this->batch->id,
        'stock_movement_id' => $receipt->id,
        'receipt_date' => '2026-05-01',
        'quantity_received' => 100,
        'quantity_remaining' => 60,
        'unit_cost' => 100,
        'total_value' => 10000,
        'value_remaining' => 6000,
    ]);

    CurrentStockByBatch::create([
        'product_id' => $this->oldProduct->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $this->batch->id,
        'quantity_on_hand' => 60,
        'unit_cost' => 100,
        'total_value' => 6000,
        'status' => 'active',
    ]);
});

function batchMovement(string $type, float $quantity, string $date, ?int $vehicleId = null): StockMovement
{
    $context = test();

    return StockMovement::create([
        'movement_type' => $type,
        'movement_date' => $date,
        'product_id' => $context->oldProduct->id,
        'stock_batch_id' => $context->batch->id,
        'warehouse_id' => $context->warehouse->id,
        'vehicle_id' => $vehicleId,
        'quantity' => $quantity,
        'uom_id' => $context->uom->id,
        'unit_cost' => 100,
        'total_value' => abs($quantity) * 100,
        'created_by' => $context->user->id,
    ]);
}

function batchLedgerRows(StockMovement $movement, float $in, float $out, string $inventoryType): void
{
    $context = test();

    DB::table('stock_ledger_entries')->insert([
        'product_id' => $context->oldProduct->id,
        'warehouse_id' => $context->warehouse->id,
        'stock_batch_id' => $context->batch->id,
        'entry_date' => $movement->movement_date,
        'stock_movement_id' => $movement->id,
        'quantity_in' => $in,
        'quantity_out' => $out,
        'quantity_balance' => 0,
        'valuation_rate' => 100,
        'stock_value' => 0,
        'created_at' => now(),
    ]);

    DB::table('inventory_ledger_entries')->insert([
        'date' => $movement->movement_date,
        'transaction_type' => $inventoryType,
        'product_id' => $context->oldProduct->id,
        'stock_batch_id' => $context->batch->id,
        'warehouse_id' => $context->warehouse->id,
        'debit_qty' => $in,
        'credit_qty' => $out,
        'unit_cost' => 100,
        'total_value' => ($in + $out) * 100,
        'running_balance' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Warehouse stock per the movement ledger — what the snapshot rebuild reads.
 */
function warehouseLedgerQuantity(int $productId): float
{
    return (float) StockMovement::where('product_id', $productId)
        ->whereNotNull('warehouse_id')
        ->whereIn('movement_type', ['grn', 'transfer', 'adjustment', 'return', 'damage', 'theft'])
        ->sum('quantity');
}

function currentStockQuantity(int $productId): float
{
    return (float) CurrentStockByBatch::where('product_id', $productId)->sum('quantity_on_hand');
}

it('moves the whole ledger of a fully transferred batch to the new product', function () {
    $result = app(BatchTransferService::class)->transfer($this->batch->id, $this->newProduct->id, 60, 'Received under the wrong product');

    expect($result['success'])->toBeTrue()
        ->and(warehouseLedgerQuantity($this->newProduct->id))->toBe(currentStockQuantity($this->newProduct->id))
        ->and(warehouseLedgerQuantity($this->newProduct->id))->toBe(60.0)
        ->and(warehouseLedgerQuantity($this->oldProduct->id))->toBe(0.0)
        ->and(DB::table('stock_ledger_entries')->where('product_id', $this->oldProduct->id)->exists())->toBeFalse()
        ->and(DB::table('inventory_ledger_entries')->where('product_id', $this->oldProduct->id)->exists())->toBeFalse()
        ->and((float) DB::table('stock_ledger_entries')->where('product_id', $this->newProduct->id)->orderByDesc('id')->value('quantity_balance'))->toBe(60.0)
        ->and((float) DB::table('inventory_ledger_entries')->where('product_id', $this->newProduct->id)->orderByDesc('id')->value('running_balance'))->toBe(60.0);
});

it('refuses a full transfer while part of the batch is still on a van', function () {
    batchMovement('transfer', -10, '2026-05-03', $this->vehicle->id);

    $result = app(BatchTransferService::class)->transfer($this->batch->id, $this->newProduct->id, 60, 'Received under the wrong product');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('van')
        ->and($this->batch->fresh()->product_id)->toBe($this->oldProduct->id)
        ->and(StockMovement::where('product_id', $this->newProduct->id)->exists())->toBeFalse();
});

it('records a partial transfer as stock leaving the old product and arriving on the new one', function () {
    $result = app(BatchTransferService::class)->transfer($this->batch->id, $this->newProduct->id, 20, 'Twenty units were the CP pack');

    expect($result['success'])->toBeTrue()
        ->and(warehouseLedgerQuantity($this->oldProduct->id))->toBe(40.0)
        ->and(warehouseLedgerQuantity($this->oldProduct->id))->toBe(currentStockQuantity($this->oldProduct->id))
        ->and(warehouseLedgerQuantity($this->newProduct->id))->toBe(20.0)
        ->and(warehouseLedgerQuantity($this->newProduct->id))->toBe(currentStockQuantity($this->newProduct->id))
        ->and((float) DB::table('stock_ledger_entries')->where('product_id', $this->newProduct->id)->sum('quantity_in'))->toBe(20.0)
        ->and((float) DB::table('inventory_ledger_entries')->where('product_id', $this->newProduct->id)->sum('debit_qty'))->toBe(20.0)
        ->and((float) DB::table('inventory_ledger_entries')->where('product_id', $this->oldProduct->id)->where('transaction_type', 'adjustment')->sum('credit_qty'))->toBe(20.0);
});
