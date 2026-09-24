<?php

use App\Models\CurrentStock;
use App\Models\CurrentStockByBatch;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\InventoryConsistencyMismatch;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

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
});

/**
 * A product whose four stock records all agree, as they do after a clean GRN:
 * the movement ledger, current_stock_by_batch, the valuation layer and current_stock.
 */
function agreeingStock(float $quantity, ?float $layerQuantity = null, ?float $currentQuantity = null): void
{
    $context = test();

    $movement = StockMovement::create([
        'movement_type' => 'grn',
        'movement_date' => '2026-05-01',
        'product_id' => $context->product->id,
        'stock_batch_id' => $context->batch->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity' => $quantity,
        'uom_id' => $context->uom->id,
        'unit_cost' => 100,
        'total_value' => $quantity * 100,
        'created_by' => $context->user->id,
    ]);

    CurrentStockByBatch::create([
        'product_id' => $context->product->id,
        'warehouse_id' => $context->warehouse->id,
        'stock_batch_id' => $context->batch->id,
        'quantity_on_hand' => $quantity,
        'unit_cost' => 100,
        'total_value' => $quantity * 100,
        'status' => 'active',
    ]);

    StockValuationLayer::create([
        'product_id' => $context->product->id,
        'warehouse_id' => $context->warehouse->id,
        'stock_batch_id' => $context->batch->id,
        'stock_movement_id' => $movement->id,
        'receipt_date' => '2026-05-01',
        'quantity_received' => $quantity,
        'quantity_remaining' => $layerQuantity ?? $quantity,
        'unit_cost' => 100,
        'total_value' => $quantity * 100,
        'value_remaining' => ($layerQuantity ?? $quantity) * 100,
    ]);

    CurrentStock::create([
        'product_id' => $context->product->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity_on_hand' => $currentQuantity ?? $quantity,
        'quantity_available' => $currentQuantity ?? $quantity,
        'average_cost' => 100,
        'total_value' => ($currentQuantity ?? $quantity) * 100,
        'total_batches' => 1,
    ]);
}

function grnItemForBatch(): GoodsReceiptNoteItem
{
    $context = test();

    $grn = GoodsReceiptNote::factory()->create([
        'warehouse_id' => $context->warehouse->id,
        'supplier_id' => $context->supplier->id,
    ]);

    return GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id,
        'product_id' => $context->product->id,
        'stock_uom_id' => $context->uom->id,
        'purchase_uom_id' => $context->uom->id,
    ]);
}

it('succeeds when all four stock records agree', function () {
    agreeingStock(60);

    $this->artisan('inventory:verify-consistency')
        ->expectsOutputToContain('agree for all')
        ->assertSuccessful();
});

it('fails and names the product when the valuation layers hold more than current stock', function () {
    // A count surplus that was never issued: exactly what left layers above by-batch.
    agreeingStock(60, layerQuantity: 83, currentQuantity: 83);

    $this->artisan('inventory:verify-consistency')
        ->expectsOutputToContain($this->product->product_name)
        ->assertFailed();
});

it('brings the valuation layers and current_stock back to current stock with --fix', function () {
    agreeingStock(60, layerQuantity: 83, currentQuantity: 83);

    $this->artisan('inventory:verify-consistency', ['--fix' => true])->assertSuccessful();

    expect((float) StockValuationLayer::where('stock_batch_id', $this->batch->id)->sum('quantity_remaining'))->toBe(60.0)
        ->and((float) CurrentStock::where('product_id', $this->product->id)->value('quantity_on_hand'))->toBe(60.0);
});

it('leaves the movement ledger and current_stock_by_batch untouched by --fix', function () {
    agreeingStock(60, layerQuantity: 83, currentQuantity: 83);

    $this->artisan('inventory:verify-consistency', ['--fix' => true])->assertSuccessful();

    // A ledger disagreement is a posting problem; the repair must not paper over it.
    expect((float) StockMovement::where('stock_batch_id', $this->batch->id)->sum('quantity'))->toBe(60.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $this->batch->id)->value('quantity_on_hand'))->toBe(60.0);
});

it('still fails after --fix when the ledger itself disagrees', function () {
    // Layers and current_stock can be derived; a ledger that does not end where
    // current stock stands needs a person to look at it.
    agreeingStock(60);
    StockMovement::where('stock_batch_id', $this->batch->id)->update(['quantity' => 45]);

    $this->artisan('inventory:verify-consistency', ['--fix' => true])->assertFailed();
});

it('emails the backup recipient when records disagree', function () {
    Notification::fake();
    config(['backup.notifications.mail.to' => 'owner@example.test']);
    agreeingStock(60, layerQuantity: 83, currentQuantity: 83);

    $this->artisan('inventory:verify-consistency')->assertFailed();

    Notification::assertSentOnDemand(
        InventoryConsistencyMismatch::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'owner@example.test'
    );
});

it('sends no email when everything agrees', function () {
    Notification::fake();
    config(['backup.notifications.mail.to' => 'owner@example.test']);
    agreeingStock(60);

    $this->artisan('inventory:verify-consistency')->assertSuccessful();

    Notification::assertNothingSent();
});

it('only checks the products of the supplier passed in', function () {
    agreeingStock(60, layerQuantity: 83, currentQuantity: 83);
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);

    $this->artisan('inventory:verify-consistency', ['--supplier_id' => $otherSupplier->id])
        ->assertSuccessful();
});

it('folds a batch split across two valuation layers back into its grn layer with --fix', function () {
    agreeingStock(60);
    $grnLayer = StockValuationLayer::where('stock_batch_id', $this->batch->id)->sole();
    $grnLayer->update(['grn_item_id' => grnItemForBatch()->id, 'quantity_remaining' => 51, 'quantity_received' => 51]);

    // The extra layer a count surplus used to create: no grn_item_id, so the
    // Goods Issue batch picker could never offer its 9 units.
    $orphan = StockValuationLayer::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $this->batch->id,
        'stock_movement_id' => $grnLayer->stock_movement_id,
        'receipt_date' => '2026-05-10',
        'quantity_received' => 9,
        'quantity_remaining' => 9,
        'unit_cost' => 100,
        'total_value' => 900,
        'value_remaining' => 900,
    ]);

    $this->artisan('inventory:verify-consistency', ['--fix' => true])->assertSuccessful();

    $layers = StockValuationLayer::where('stock_batch_id', $this->batch->id)->get();
    expect($layers)->toHaveCount(1)
        ->and((float) $layers->first()->quantity_remaining)->toBe(60.0)
        ->and($layers->first()->grn_item_id)->not->toBeNull();
    $this->assertModelMissing($orphan);
});

it('leaves a split batch alone when neither layer is linked to a grn item', function () {
    agreeingStock(60);
    $grnLayer = StockValuationLayer::where('stock_batch_id', $this->batch->id)->sole();
    $grnLayer->update(['quantity_remaining' => 51, 'quantity_received' => 51]);
    StockValuationLayer::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $this->batch->id,
        'stock_movement_id' => $grnLayer->stock_movement_id,
        'receipt_date' => '2026-05-10',
        'quantity_received' => 9,
        'quantity_remaining' => 9,
        'unit_cost' => 100,
        'total_value' => 900,
        'value_remaining' => 900,
    ]);

    // Merging into a layer the picker cannot offer either would hide the problem
    // rather than fix it, so the batch is reported instead.
    $this->artisan('inventory:verify-consistency', ['--fix' => true])->assertSuccessful();

    expect(StockValuationLayer::where('stock_batch_id', $this->batch->id)->count())->toBe(2);
});

test('the nightly schedule runs the check after the snapshot rebuild', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'inventory:verify-consistency'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('55 23 * * *');
});
