<?php

use App\Models\CurrentStockByBatch;
use App\Models\DailyInventorySnapshot;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Notifications\SnapshotRebuildSkippedProducts;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

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
 * Records a movement and, as posting does, moves current stock with it so the ledger and
 * current_stock_by_batch agree.
 */
function recordStockMovement(array $overrides = []): StockMovement
{
    $context = test();

    $movement = StockMovement::create(array_merge([
        'movement_type' => 'grn',
        'movement_date' => '2026-03-01',
        'product_id' => $context->product->id,
        'stock_batch_id' => $context->batch->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity' => 0,
        'uom_id' => $context->uom->id,
        'unit_cost' => 100,
        'total_value' => 0,
        'created_by' => $context->user->id,
    ], $overrides));

    if ($movement->warehouse_id && in_array($movement->movement_type, ['grn', 'transfer', 'adjustment', 'return', 'damage', 'theft'], true)) {
        $stock = CurrentStockByBatch::firstOrNew(
            ['stock_batch_id' => $movement->stock_batch_id, 'warehouse_id' => $movement->warehouse_id],
            ['product_id' => $movement->product_id, 'unit_cost' => 100, 'total_value' => 0, 'quantity_on_hand' => 0]
        );
        $stock->quantity_on_hand = (float) $stock->quantity_on_hand + (float) $movement->quantity;
        $stock->save();
    }

    return $movement;
}

function recordSnapshot(string $date, array $overrides = []): DailyInventorySnapshot
{
    $context = test();

    return DailyInventorySnapshot::create(array_merge([
        'date' => $date,
        'product_id' => $context->product->id,
        'warehouse_id' => $context->warehouse->id,
        'vehicle_id' => null,
        'quantity_on_hand' => 0,
        'average_cost' => 0,
        'total_value' => 0,
    ], $overrides));
}

it('recomputes a snapshot that was frozen before a backdated goods issue was posted', function () {
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    recordStockMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -40,
        'total_value' => 4000,
    ]);
    recordSnapshot('2026-03-05', [
        'quantity_on_hand' => 100,
        'average_cost' => 100,
        'total_value' => 10000,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-05',
    ])->assertSuccessful();

    $snapshot = DailyInventorySnapshot::where('date', '2026-03-05')->sole();
    expect((float) $snapshot->quantity_on_hand)->toBe(60.0)
        ->and((float) $snapshot->total_value)->toBe(6000.0)
        ->and((float) $snapshot->average_cost)->toBe(100.0);
});

it('values remaining stock at the batch receipt cost when an issue movement is mispriced', function () {
    recordStockMovement(['quantity' => 100, 'unit_cost' => 100, 'total_value' => 10000]);
    recordStockMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -40,
        'unit_cost' => 25,
        'total_value' => 1000,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-05',
    ])->assertSuccessful();

    // Summing the movements' own values would leave 10,000 - 1,000 = 9,000 in the warehouse
    // for 60 units that were received at 100 each.
    $snapshot = DailyInventorySnapshot::where('date', '2026-03-05')->sole();
    expect((float) $snapshot->total_value)->toBe(6000.0)
        ->and((float) $snapshot->average_cost)->toBe(100.0);
});

it('ignores sale and shortage movements so van activity is not deducted twice', function () {
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    recordStockMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -40,
        'total_value' => 4000,
    ]);
    recordStockMovement([
        'movement_type' => 'sale',
        'movement_date' => '2026-03-05',
        'quantity' => -30,
        'total_value' => 3000,
    ]);
    recordStockMovement([
        'movement_type' => 'shortage',
        'movement_date' => '2026-03-05',
        'quantity' => -5,
        'total_value' => 500,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-05',
    ])->assertSuccessful();

    $snapshot = DailyInventorySnapshot::where('date', '2026-03-05')->sole();
    expect((float) $snapshot->quantity_on_hand)->toBe(60.0);
});

it('adds a return from a van back into the warehouse balance', function () {
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    recordStockMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -40,
        'total_value' => 4000,
    ]);
    recordStockMovement([
        'movement_type' => 'return',
        'movement_date' => '2026-03-06',
        'quantity' => 15,
        'total_value' => 1500,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-06',
        'end_date' => '2026-03-06',
    ])->assertSuccessful();

    $snapshot = DailyInventorySnapshot::where('date', '2026-03-06')->sole();
    expect((float) $snapshot->quantity_on_hand)->toBe(75.0)
        ->and((float) $snapshot->total_value)->toBe(7500.0);
});

it('removes a snapshot row for a date the ledger records no movements for', function () {
    // A goods receipt entered early but dated later leaves the nightly job with a balance
    // that the ledger cannot support on the snapshot date.
    recordStockMovement(['movement_date' => '2026-03-10', 'quantity' => 100, 'total_value' => 10000]);
    recordSnapshot('2026-03-05', [
        'quantity_on_hand' => 100,
        'average_cost' => 100,
        'total_value' => 10000,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-05',
    ])->assertSuccessful();

    assertDatabaseCount('daily_inventory_snapshots', 0);
});

it('removes a snapshot row once the ledger shows the warehouse emptied', function () {
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    recordStockMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -100,
        'total_value' => 10000,
    ]);
    recordSnapshot('2026-03-05', [
        'quantity_on_hand' => 100,
        'average_cost' => 100,
        'total_value' => 10000,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-05',
    ])->assertSuccessful();

    assertDatabaseCount('daily_inventory_snapshots', 0);
});

it('leaves stored snapshots untouched on a dry run', function () {
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    recordStockMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -40,
        'total_value' => 4000,
    ]);
    recordSnapshot('2026-03-05', [
        'quantity_on_hand' => 100,
        'average_cost' => 100,
        'total_value' => 10000,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-05',
        '--dry-run' => true,
    ])->assertSuccessful();

    assertDatabaseHas('daily_inventory_snapshots', [
        'date' => '2026-03-05',
        'product_id' => $this->product->id,
        'quantity_on_hand' => 100,
    ]);
});

it('leaves snapshots of other suppliers alone when scoped to one supplier', function () {
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $otherProduct = Product::factory()->create(['supplier_id' => $otherSupplier->id]);

    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    recordSnapshot('2026-03-05', [
        'product_id' => $otherProduct->id,
        'quantity_on_hand' => 999,
        'average_cost' => 1,
        'total_value' => 999,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-05',
        '--supplier_id' => $this->supplier->id,
    ])->assertSuccessful();

    assertDatabaseHas('daily_inventory_snapshots', [
        'date' => '2026-03-05',
        'product_id' => $otherProduct->id,
        'quantity_on_hand' => 999,
    ]);
});

it('does not write vehicle rows unless asked for them', function () {
    $vehicle = Vehicle::factory()->create();

    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    recordStockMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -40,
        'vehicle_id' => $vehicle->id,
        'total_value' => 4000,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-05',
    ])->assertSuccessful();

    expect(DailyInventorySnapshot::whereNotNull('vehicle_id')->count())->toBe(0);
});

it('builds the van balance from the goods issue less what the van sold, returned and lost', function () {
    $vehicle = Vehicle::factory()->create();

    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    recordStockMovement([
        'movement_type' => 'transfer',
        'movement_date' => '2026-03-05',
        'quantity' => -40,
        'vehicle_id' => $vehicle->id,
        'total_value' => 4000,
    ]);
    recordStockMovement([
        'movement_type' => 'sale',
        'movement_date' => '2026-03-05',
        'quantity' => -25,
        'vehicle_id' => $vehicle->id,
        'total_value' => 2500,
    ]);
    recordStockMovement([
        'movement_type' => 'return',
        'movement_date' => '2026-03-05',
        'quantity' => 10,
        'vehicle_id' => $vehicle->id,
        'total_value' => 1000,
    ]);
    recordStockMovement([
        'movement_type' => 'shortage',
        'movement_date' => '2026-03-05',
        'quantity' => -2,
        'vehicle_id' => $vehicle->id,
        'total_value' => 200,
    ]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-05',
        'end_date' => '2026-03-05',
        '--with-vans' => true,
    ])->assertSuccessful();

    $vanSnapshot = DailyInventorySnapshot::whereNotNull('vehicle_id')->sole();
    expect((float) $vanSnapshot->quantity_on_hand)->toBe(3.0)
        ->and((float) $vanSnapshot->total_value)->toBe(300.0)
        ->and($vanSnapshot->warehouse_id)->toBeNull();

    $warehouseSnapshot = DailyInventorySnapshot::whereNotNull('warehouse_id')->sole();
    expect((float) $warehouseSnapshot->quantity_on_hand)->toBe(70.0);
});

it('rebuilds the trailing --days window when no dates are given', function () {
    $this->travelTo('2026-03-20');

    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    recordSnapshot('2026-03-18', [
        'quantity_on_hand' => 999,
        'average_cost' => 1,
        'total_value' => 999,
    ]);

    $this->artisan('inventory:snapshots:rebuild', ['--days' => 5])->assertSuccessful();

    assertDatabaseHas('daily_inventory_snapshots', [
        'date' => '2026-03-18',
        'product_id' => $this->product->id,
        'quantity_on_hand' => 100,
    ]);
    assertDatabaseCount('daily_inventory_snapshots', 6);
});

it('schedules the rolling rebuild nightly, after the snapshot job', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'inventory:snapshots:rebuild'))
        ->values();

    expect($scheduled)->toHaveCount(1)
        ->and($scheduled[0]->expression)->toBe('50 23 * * *')
        ->and($scheduled[0]->command)->toContain('--days=90')
        ->and($scheduled[0]->command)->toContain('--with-vans');
});

it('rejects a start date that is not in Y-m-d format', function () {
    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '05-03-2026',
        'end_date' => '2026-03-05',
    ])->assertFailed();
});

it('rejects a range that ends before it starts', function () {
    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-05',
    ])->assertFailed();
});

it('skips a product whose ledger disagrees with current stock and names it', function () {
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    CurrentStockByBatch::where('stock_batch_id', $this->batch->id)->update(['quantity_on_hand' => 90]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-01',
    ])
        ->expectsOutputToContain($this->product->product_name)
        ->assertFailed();

    assertDatabaseCount('daily_inventory_snapshots', 0);
});

it('rebuilds an out-of-step product anyway when forced', function () {
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    CurrentStockByBatch::where('stock_batch_id', $this->batch->id)->update(['quantity_on_hand' => 90]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-01',
        '--force' => true,
    ])->assertSuccessful();

    expect((float) DailyInventorySnapshot::sole()->quantity_on_hand)->toBe(100.0);
});

it('stops at today when the end date is in the future', function () {
    $this->travelTo('2026-03-03');
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);

    $this->artisan('inventory:snapshots:rebuild', [
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-10',
    ])->assertSuccessful();

    expect(DailyInventorySnapshot::max('date'))->toStartWith('2026-03-03');
});

it('emails the backup recipient when it skips products', function () {
    Notification::fake();
    config(['backup.notifications.mail.to' => 'owner@example.com']);
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    CurrentStockByBatch::where('stock_batch_id', $this->batch->id)->update(['quantity_on_hand' => 90]);

    $this->artisan('inventory:snapshots:rebuild', ['start_date' => '2026-03-01', 'end_date' => '2026-03-01'])
        ->assertFailed();

    Notification::assertSentOnDemand(
        SnapshotRebuildSkippedProducts::class,
        fn (SnapshotRebuildSkippedProducts $notification, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'owner@example.com'
            && str_contains(implode(' ', $notification->toMail($notifiable)->introLines), $this->product->product_name)
    );
});

it('does not email about skipped products on a dry run', function () {
    Notification::fake();
    config(['backup.notifications.mail.to' => 'owner@example.com']);
    recordStockMovement(['quantity' => 100, 'total_value' => 10000]);
    CurrentStockByBatch::where('stock_batch_id', $this->batch->id)->update(['quantity_on_hand' => 90]);

    $this->artisan('inventory:snapshots:rebuild', ['start_date' => '2026-03-01', 'end_date' => '2026-03-01', '--dry-run' => true]);

    Notification::assertNothingSent();
});
