<?php

use App\Models\CurrentStockByBatch;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockValuationService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'unit_cost' => 100,
    ]);
    $this->service = app(StockValuationService::class);
});

/**
 * One valuation layer on the shared batch. Several are realistic: a GRN leaves one,
 * and a correction posted against the same batch leaves another.
 */
function valuationLayer(float $received, float $remaining, string $receiptDate = '2026-05-01'): StockValuationLayer
{
    $context = test();

    $movement = StockMovement::create([
        'movement_type' => 'grn',
        'movement_date' => $receiptDate,
        'product_id' => $context->product->id,
        'stock_batch_id' => $context->batch->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity' => $received,
        'uom_id' => $context->uom->id,
        'unit_cost' => 100,
        'total_value' => $received * 100,
        'created_by' => $context->user->id,
    ]);

    return StockValuationLayer::create([
        'product_id' => $context->product->id,
        'warehouse_id' => $context->warehouse->id,
        'stock_batch_id' => $context->batch->id,
        'stock_movement_id' => $movement->id,
        'receipt_date' => $receiptDate,
        'quantity_received' => $received,
        'quantity_remaining' => $remaining,
        'unit_cost' => 100,
        'total_value' => $received * 100,
        'value_remaining' => $remaining * 100,
    ]);
}

function layerTotal(): float
{
    return (float) StockValuationLayer::where('stock_batch_id', test()->batch->id)->sum('quantity_remaining');
}

it('spills a withdrawal across every layer the batch holds', function () {
    $first = valuationLayer(80, 80, '2026-05-01');
    $second = valuationLayer(23, 23, '2026-05-10');

    $this->service->consumeBatch($this->batch->id, $this->warehouse->id, 90);

    // The oldest layer empties and the rest comes out of the newer one, instead of
    // being clamped at zero and silently dropped.
    expect((float) $first->fresh()->quantity_remaining)->toBe(0.0)
        ->and((float) $second->fresh()->quantity_remaining)->toBe(13.0)
        ->and(layerTotal())->toBe(13.0);
});

it('refuses a withdrawal larger than the batch holds and names the repair command', function () {
    $layer = valuationLayer(80, 80);

    expect(fn () => $this->service->consumeBatch($this->batch->id, $this->warehouse->id, 95))
        ->toThrow(RuntimeException::class, 'inventory:verify-consistency --fix');

    expect((float) $layer->fresh()->quantity_remaining)->toBe(0.0);
});

it('zeroes the remaining value and marks a layer depleted when it empties', function () {
    $layer = valuationLayer(80, 80);

    $this->service->consumeBatch($this->batch->id, $this->warehouse->id, 80);

    expect((float) $layer->fresh()->value_remaining)->toBe(0.0)
        ->and($layer->fresh()->is_depleted)->toBeTrue();
});

it('leaves total_value at the original receipt value when stock is taken out', function () {
    $layer = valuationLayer(80, 80);

    $this->service->consumeBatch($this->batch->id, $this->warehouse->id, 30);

    // total_value is the receipt record the stock value report reconciles against;
    // only value_remaining follows the quantity.
    expect((float) $layer->fresh()->total_value)->toBe(8000.0)
        ->and((float) $layer->fresh()->value_remaining)->toBe(5000.0);
});

it('refills the most recently received layer first', function () {
    $first = valuationLayer(80, 0, '2026-05-01');
    $second = valuationLayer(20, 0, '2026-05-10');

    $this->service->restoreBatch($this->batch->id, $this->warehouse->id, 25, 100.0);

    expect((float) $second->fresh()->quantity_remaining)->toBe(20.0)
        ->and((float) $first->fresh()->quantity_remaining)->toBe(5.0);
});

it('grows the newest layer rather than losing stock a full batch cannot absorb', function () {
    $layer = valuationLayer(80, 80);

    $this->service->restoreBatch($this->batch->id, $this->warehouse->id, 12, 100.0);

    // Every layer was already full, so the quantity has to go somewhere; dropping it
    // is what left current_stock disagreeing with current_stock_by_batch.
    expect(layerTotal())->toBe(92.0)
        ->and((float) $layer->fresh()->quantity_received)->toBe(92.0);
});

it('does not write a layer for a batch with none when no movement is given', function () {
    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $this->batch->id,
        'quantity_on_hand' => 10,
        'unit_cost' => 100,
        'total_value' => 1000,
        'status' => 'active',
    ]);

    $this->service->restoreBatch($this->batch->id, $this->warehouse->id, 10, 100.0);

    // stock_valuation_layers.stock_movement_id is NOT NULL, so there is nothing to
    // write; the consistency check reports the batch instead.
    expect(StockValuationLayer::where('stock_batch_id', $this->batch->id)->count())->toBe(0);
});

it('gives a batch with no layer one tied to the movement that restored it', function () {
    $movement = StockMovement::create([
        'movement_type' => 'return',
        'movement_date' => '2026-05-20',
        'product_id' => $this->product->id,
        'stock_batch_id' => $this->batch->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
        'uom_id' => $this->uom->id,
        'unit_cost' => 100,
        'total_value' => 1000,
        'created_by' => $this->user->id,
    ]);

    $this->service->restoreBatch($this->batch->id, $this->warehouse->id, 10, 100.0, $movement->id);

    // Returned stock is never dropped, so the layer total keeps matching
    // current_stock_by_batch even for a batch the layers had lost.
    expect(layerTotal())->toBe(10.0);
    $this->assertDatabaseHas('stock_valuation_layers', [
        'stock_batch_id' => $this->batch->id,
        'stock_movement_id' => $movement->id,
        'quantity_remaining' => 10,
    ]);
});

it('ignores a withdrawal of nothing', function () {
    $layer = valuationLayer(80, 80);

    $this->service->consumeBatch($this->batch->id, $this->warehouse->id, 0);

    expect((float) $layer->fresh()->quantity_remaining)->toBe(80.0);
});
