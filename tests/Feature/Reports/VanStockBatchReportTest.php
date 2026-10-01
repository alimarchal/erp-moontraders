<?php

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function vanBatchReportBatch(Product $product, string $code): StockBatch
{
    return StockBatch::create([
        'product_id' => $product->id,
        'batch_code' => $code,
        'supplier_id' => Supplier::first()?->id ?? Supplier::factory()->create()->id,
        'receipt_date' => now(),
        'manufacturing_date' => now()->subMonth(),
        'unit_cost' => 90.21,
        'selling_price' => 94.88,
        'expiry_date' => now()->addYear(),
        'status' => 'active',
    ]);
}

function vanBatchReportMovement(Vehicle $vehicle, Product $product, StockBatch $batch, string $type, float $quantity): void
{
    DB::table('stock_movements')->insert([
        'movement_type' => $type,
        'reference_type' => $type === 'transfer' ? 'App\\Models\\GoodsIssue' : 'App\\Models\\SalesSettlement',
        'reference_id' => 1,
        'movement_date' => now()->toDateString(),
        'product_id' => $product->id,
        'stock_batch_id' => $batch->id,
        'warehouse_id' => Warehouse::first()->id,
        'vehicle_id' => $vehicle->id,
        'quantity' => $quantity,
        'uom_id' => Uom::first()->id,
        'unit_cost' => 90.21,
        'total_value' => round(abs($quantity) * 90.21, 4),
        'created_by' => User::first()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    Permission::create(['name' => 'report-inventory-van-stock-batch']);

    $user = User::factory()->create();
    $user->givePermissionTo('report-inventory-van-stock-batch');
    $this->actingAs($user);

    Uom::factory()->create();
    Warehouse::factory()->create();

    $this->vehicle = Vehicle::factory()->create();
    $this->product = Product::factory()->create();
    $this->older = vanBatchReportBatch($this->product, 'BATCH-OLDER');
    $this->newer = vanBatchReportBatch($this->product, 'BATCH-NEWER');
});

it('shows no stock when a settlement sold a slightly different batch split than was issued', function () {
    // Issued 767.06 + 663.94, settled as 767 + 664: one batch is 0.06 over, the other 0.06 under.
    vanBatchReportMovement($this->vehicle, $this->product, $this->older, 'transfer', -767.06);
    vanBatchReportMovement($this->vehicle, $this->product, $this->newer, 'transfer', -663.94);
    vanBatchReportMovement($this->vehicle, $this->product, $this->older, 'sale', -767.00);
    vanBatchReportMovement($this->vehicle, $this->product, $this->newer, 'sale', -664.00);

    $this->get(route('reports.van-stock-batch.index'))
        ->assertSuccessful()
        ->assertViewHas('stocks', fn ($stocks) => $stocks->isEmpty())
        ->assertViewHas('totals', fn (array $totals) => $totals['total_quantity'] == 0);
});

it('takes what a batch is under off the older batch of the same vehicle and product', function () {
    vanBatchReportMovement($this->vehicle, $this->product, $this->older, 'transfer', -10);
    vanBatchReportMovement($this->vehicle, $this->product, $this->newer, 'transfer', -4);
    vanBatchReportMovement($this->vehicle, $this->product, $this->newer, 'sale', -6);

    $this->get(route('reports.van-stock-batch.index'))
        ->assertSuccessful()
        ->assertViewHas('totals', fn (array $totals) => round($totals['total_quantity'], 3) == 8.0);
});

it('still lists stock that really is on the van', function () {
    vanBatchReportMovement($this->vehicle, $this->product, $this->older, 'transfer', -10);

    $this->get(route('reports.van-stock-batch.index'))
        ->assertSuccessful()
        ->assertViewHas('totals', fn (array $totals) => round($totals['total_quantity'], 3) == 10.0);
});
