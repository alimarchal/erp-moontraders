<?php

use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
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
 * A mistyped issue date leaves the warehouse overstated and the van negative between the
 * real date and the typed one, because the stock is sold before the ledger says it left.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->vehicle = Vehicle::factory()->create();
    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'unit_cost' => 100,
    ]);

    // Issued on 2026-08-25 but typed as 2026-09-25, and settled on the real date.
    $this->goodsIssue = GoodsIssue::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'supplier_id' => $this->supplier->id,
        'issue_date' => '2026-09-25',
        'issued_by' => $this->user->id,
        'status' => 'issued',
    ]);

    $this->item = GoodsIssueItem::create([
        'goods_issue_id' => $this->goodsIssue->id,
        'line_no' => 1,
        'product_id' => $this->product->id,
        'quantity_issued' => 40,
        'unit_cost' => 100,
        'selling_price' => 150,
        'uom_id' => $this->uom->id,
        'total_value' => 6000,
    ]);

    $this->movement = StockMovement::create([
        'movement_type' => 'transfer',
        'reference_type' => GoodsIssue::class,
        'reference_id' => $this->goodsIssue->id,
        'goods_issue_item_id' => $this->item->id,
        'movement_date' => '2026-09-25',
        'product_id' => $this->product->id,
        'stock_batch_id' => $this->batch->id,
        'warehouse_id' => $this->warehouse->id,
        'vehicle_id' => $this->vehicle->id,
        'quantity' => -40,
        'uom_id' => $this->uom->id,
        'unit_cost' => 100,
        'total_value' => 4000,
        'created_by' => $this->user->id,
    ]);

    DB::table('inventory_ledger_entries')->insert([
        'date' => '2026-09-25',
        'transaction_type' => 'transfer_out',
        'product_id' => $this->product->id,
        'stock_batch_id' => $this->batch->id,
        'warehouse_id' => $this->warehouse->id,
        'goods_issue_id' => $this->goodsIssue->id,
        'debit_qty' => 0,
        'credit_qty' => 40,
        'unit_cost' => 100,
        'total_value' => 4000,
        'running_balance' => 60,
    ]);

    SalesSettlement::factory()->create([
        'goods_issue_id' => $this->goodsIssue->id,
        'vehicle_id' => $this->vehicle->id,
        'settlement_date' => '2026-08-25',
        'status' => 'posted',
    ]);
});

it('moves the goods issue and everything posted from it onto the settlement date', function () {
    $this->artisan('goods-issue:correct-date', ['goods_issue' => $this->goodsIssue->id])
        ->assertSuccessful();

    assertDatabaseHas('goods_issues', [
        'id' => $this->goodsIssue->id,
        'issue_date' => '2026-08-25',
    ]);
    assertDatabaseHas('stock_movements', [
        'id' => $this->movement->id,
        'movement_date' => '2026-08-25',
    ]);
    assertDatabaseHas('inventory_ledger_entries', [
        'goods_issue_id' => $this->goodsIssue->id,
        'date' => '2026-08-25',
    ]);
});

it('accepts an explicit date instead of the settlement date', function () {
    $this->artisan('goods-issue:correct-date', [
        'goods_issue' => $this->goodsIssue->issue_number,
        'date' => '2026-08-24',
    ])->assertSuccessful();

    assertDatabaseHas('goods_issues', [
        'id' => $this->goodsIssue->id,
        'issue_date' => '2026-08-24',
    ]);
});

it('refuses a date after the settlement, which would sell the stock before it left the warehouse', function () {
    $this->artisan('goods-issue:correct-date', [
        'goods_issue' => $this->goodsIssue->id,
        'date' => '2026-08-26',
    ])->assertFailed();

    assertDatabaseHas('goods_issues', [
        'id' => $this->goodsIssue->id,
        'issue_date' => '2026-09-25',
    ]);
});

it('rejects a date that is not in Y-m-d format', function () {
    $this->artisan('goods-issue:correct-date', [
        'goods_issue' => $this->goodsIssue->id,
        'date' => '25-08-2026',
    ])->assertFailed();
});

it('reports an unknown goods issue instead of changing anything', function () {
    $this->artisan('goods-issue:correct-date', ['goods_issue' => 'GI-DOES-NOT-EXIST'])
        ->assertFailed();
});

it('changes nothing on a dry run', function () {
    $this->artisan('goods-issue:correct-date', [
        'goods_issue' => $this->goodsIssue->id,
        '--dry-run' => true,
    ])->assertSuccessful();

    assertDatabaseHas('goods_issues', [
        'id' => $this->goodsIssue->id,
        'issue_date' => '2026-09-25',
    ]);
    assertDatabaseHas('stock_movements', [
        'id' => $this->movement->id,
        'movement_date' => '2026-09-25',
    ]);
});
