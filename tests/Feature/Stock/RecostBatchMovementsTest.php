<?php

use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseHas;

/**
 * A GRN corrected after stock has already left the batch restates the batch but not the
 * documents posted from it. These tests cover the repair that brings those documents back
 * onto the batch's receipt cost.
 */
beforeEach(function () {
    seedGrnPostingAccounts();
    $this->user = User::factory()->create();
    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->vehicle = Vehicle::factory()->create();
    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id]);

    // Received at 1,000; a later GRN correction is what leaves issues behind at 400.
    $this->batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'unit_cost' => 1000,
    ]);

    $this->grnMovement = makeMovement([
        'movement_type' => 'grn',
        'quantity' => 100,
        'unit_cost' => 1000,
        'total_value' => 100000,
    ]);
});

function makeMovement(array $overrides = []): StockMovement
{
    $context = test();

    return StockMovement::create(array_merge([
        'movement_date' => '2026-03-01',
        'product_id' => $context->product->id,
        'stock_batch_id' => $context->batch->id,
        'warehouse_id' => $context->warehouse->id,
        'uom_id' => $context->uom->id,
        'created_by' => $context->user->id,
    ], $overrides));
}

function makeIssuedLine(float $quantity, float $staleCost): GoodsIssueItem
{
    $context = test();

    $goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $context->warehouse->id,
        'vehicle_id' => $context->vehicle->id,
        'issued_by' => $context->user->id,
    ]);

    $item = GoodsIssueItem::create([
        'goods_issue_id' => $goodsIssue->id,
        'line_no' => 1,
        'product_id' => $context->product->id,
        'quantity_issued' => $quantity,
        'unit_cost' => $staleCost,
        'selling_price' => 1500,
        'uom_id' => $context->uom->id,
        'total_value' => $quantity * 1500,
    ]);

    DB::table('van_stock_batches')->insert([
        'vehicle_id' => $context->vehicle->id,
        'product_id' => $context->product->id,
        'goods_issue_item_id' => $item->id,
        'goods_issue_number' => $goodsIssue->issue_number,
        'quantity_on_hand' => $quantity,
        'unit_cost' => $staleCost,
        'selling_price' => 1500,
    ]);

    return $item;
}

it('re-costs a movement left behind at the cost the batch carried before it was corrected', function () {
    $movement = makeMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'vehicle_id' => $this->vehicle->id,
        'quantity' => -10,
        'unit_cost' => 400,
        'total_value' => 4000,
    ]);

    $this->artisan('stock:recost-batch-movements')->assertSuccessful();

    $movement->refresh();
    expect((float) $movement->unit_cost)->toBe(1000.0)
        ->and((float) $movement->total_value)->toBe(10000.0);
});

it('leaves the receiving movement of the batch alone', function () {
    makeMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -10,
        'unit_cost' => 400,
        'total_value' => 4000,
    ]);

    $this->artisan('stock:recost-batch-movements')->assertSuccessful();

    // total_value on a receipt is the invoice amount and must survive the repair.
    $this->grnMovement->refresh();
    expect((float) $this->grnMovement->unit_cost)->toBe(1000.0)
        ->and((float) $this->grnMovement->total_value)->toBe(100000.0);
});

it('ignores a movement that is within a paisa of the receipt cost', function () {
    $movement = makeMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -10,
        'unit_cost' => 999.995,
        'total_value' => 9999.95,
    ]);

    $this->artisan('stock:recost-batch-movements')
        ->expectsOutputToContain('nothing to do')
        ->assertSuccessful();

    // Read the column directly: the model casts unit_cost to 2 decimals, which would hide
    // whether the stored 6-decimal value survived.
    $storedCost = DB::table('stock_movements')->where('id', $movement->id)->value('unit_cost');
    expect((float) $storedCost)->toBe(999.995);
});

it('reports the COGS difference from sold quantity only', function () {
    makeMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -10,
        'unit_cost' => 400,
        'total_value' => 4000,
    ]);
    makeMovement([
        'movement_type' => 'sale',
        'movement_date' => '2026-03-05',
        'vehicle_id' => $this->vehicle->id,
        'quantity' => -6,
        'unit_cost' => 400,
        'total_value' => 2400,
    ]);

    // Only the 6 sold units reach the P&L: 6 x (1,000 - 400) = 3,600 understated.
    $this->artisan('stock:recost-batch-movements')
        ->expectsOutputToContain('3,600.00')
        ->assertSuccessful();
});

it('brings the goods issue line and its van stock onto the re-costed weighted average', function () {
    $item = makeIssuedLine(quantity: 10, staleCost: 400);

    makeMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'vehicle_id' => $this->vehicle->id,
        'goods_issue_item_id' => $item->id,
        'quantity' => -10,
        'unit_cost' => 400,
        'total_value' => 4000,
    ]);

    $this->artisan('stock:recost-batch-movements')->assertSuccessful();

    expect((float) $item->fresh()->unit_cost)->toBe(1000.0);
    assertDatabaseHas('van_stock_batches', [
        'goods_issue_item_id' => $item->id,
        'unit_cost' => 1000.00,
    ]);
});

it('re-costs the inventory ledger rows of the batch but not its purchase row', function () {
    $movement = makeMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -10,
        'unit_cost' => 400,
        'total_value' => 4000,
    ]);

    DB::table('inventory_ledger_entries')->insert([
        [
            'date' => '2026-03-05', 'transaction_type' => 'transfer_out',
            'product_id' => $this->product->id, 'stock_batch_id' => $this->batch->id,
            'warehouse_id' => $this->warehouse->id, 'debit_qty' => 0, 'credit_qty' => 10,
            'unit_cost' => 400, 'total_value' => 4000, 'running_balance' => 90,
        ],
    ]);

    $this->artisan('stock:recost-batch-movements')->assertSuccessful();

    assertDatabaseHas('inventory_ledger_entries', [
        'stock_batch_id' => $this->batch->id,
        'transaction_type' => 'transfer_out',
        'unit_cost' => 1000.00,
        'total_value' => 10000.00,
    ]);
    expect((float) $movement->fresh()->unit_cost)->toBe(1000.0);
});

it('changes nothing on a dry run', function () {
    $movement = makeMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -10,
        'unit_cost' => 400,
        'total_value' => 4000,
    ]);

    $this->artisan('stock:recost-batch-movements', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();

    expect((float) $movement->fresh()->unit_cost)->toBe(400.0);
});
