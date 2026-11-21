<?php

use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * A settlement reverted and posted again leaves its first posting and the revert's reversal in the
 * stock ledger. They cancel out, so the register must show the day's sales once.
 */
beforeEach(function () {
    Permission::create(['name' => 'report-inventory-daily-stock-register']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-inventory-daily-stock-register');
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->vehicle = Vehicle::factory()->create();
    $this->settlement = SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => '2026-08-19']);
});

function stockRow(string $date, string $type, float $debit, float $credit, array $location, ?int $settlementId = null): array
{
    return array_merge([
        'date' => $date, 'transaction_type' => $type, 'product_id' => test()->product->id,
        'warehouse_id' => null, 'vehicle_id' => null, 'sales_settlement_id' => $settlementId,
        'debit_qty' => $debit, 'credit_qty' => $credit, 'unit_cost' => 100,
        'running_balance' => 0, 'created_at' => now(), 'updated_at' => now(),
    ], $location);
}

function registerRow(string $date): object
{
    return test()->get(route('reports.daily-stock-register.index', ['date' => $date, 'supplier_id' => test()->supplier->id]))
        ->assertSuccessful()
        ->viewData('reportData')
        ->sole();
}

it('counts the sales of a settlement reverted and posted again only once', function () {
    $warehouse = ['warehouse_id' => $this->warehouse->id];
    $van = ['vehicle_id' => $this->vehicle->id];

    // Inserted in order, so the ids follow the real sequence: post, revert (booked two days later, as
    // reverts used to), post again.
    foreach ([
        stockRow('2026-08-01', 'purchase', 100, 0, $warehouse),
        stockRow('2026-08-19', 'transfer_out', 0, 49, $warehouse),
        stockRow('2026-08-19', 'transfer_in', 49, 0, $van),
        stockRow('2026-08-19', 'sale', 0, 49, $van, $this->settlement->id),
        stockRow('2026-08-21', 'adjustment', 49, 0, $van, $this->settlement->id),
        stockRow('2026-08-19', 'sale', 0, 49, $van, $this->settlement->id),
    ] as $row) {
        DB::table('inventory_ledger_entries')->insert($row);
    }

    $onTheDay = registerRow('2026-08-19');
    expect((float) $onTheDay->sale)->toBe(49.0)
        ->and((float) $onTheDay->in_hand)->toBe(51.0)
        ->and((float) $onTheDay->in_hand_value)->toBe(5100.0);

    $onTheRevertDay = registerRow('2026-08-21');
    expect((float) $onTheRevertDay->adjustment)->toBe(0.0)
        ->and((float) $onTheRevertDay->in_hand)->toBe(51.0);
});

it('shows no products until a supplier is picked, then only that supplier\'s products', function () {
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);
    Product::factory()->create(['supplier_id' => $otherSupplier->id, 'is_active' => true]);

    $this->get(route('reports.daily-stock-register.index', ['date' => '2026-08-19']))
        ->assertSuccessful()
        ->assertViewHas('needsSupplier', true)
        ->assertViewHas('reportData', fn ($rows) => $rows->isEmpty())
        ->assertSee('Select a supplier in Filters to see its stock register.');

    $this->get(route('reports.daily-stock-register.index', ['date' => '2026-08-19', 'supplier_id' => $this->supplier->id]))
        ->assertSuccessful()
        ->assertViewHas('needsSupplier', false)
        ->assertViewHas('reportData', fn ($rows) => $rows->pluck('id')->all() === [$this->product->id]);
});
