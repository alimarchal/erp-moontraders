<?php

use App\Models\CurrentStockByBatch;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Services\GrnCostDriftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * A GRN line's unit_cost is what its stock is worth. The receipt movement, the batch and
 * everything later posted out of the batch must carry that same cost, or the Daily Stock
 * Register, the snapshots and the profit reports each value the stock differently.
 */
beforeEach(function () {
    seedGrnPostingAccounts();

    $this->user = User::factory()->create();
    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'uom_id' => $this->uom->id]);

    // A GRN of 10 units at 100 each: 1,000.
    $this->grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'posted',
        'receipt_date' => now()->toDateString(),
    ]);

    $this->item = GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $this->grn->id,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_stock_uom' => 10,
        'quantity_accepted' => 10,
        'unit_cost' => 100,
        'total_cost' => 1000,
    ]);

    $this->batch = StockBatch::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'unit_cost' => 100,
    ]);

    $this->grnMovement = movementOnBatch(['movement_type' => 'grn', 'quantity' => 10, 'unit_cost' => 100, 'total_value' => 1000]);

    DB::table('stock_valuation_layers')->insert([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $this->batch->id,
        'stock_movement_id' => $this->grnMovement->id,
        'grn_item_id' => $this->item->id,
        'receipt_date' => now()->toDateString(),
        'quantity_received' => 10,
        'quantity_remaining' => 6,
        'unit_cost' => 100,
        'total_value' => 1000,
        'value_remaining' => 600,
        'priority_order' => 99,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

function movementOnBatch(array $overrides = []): StockMovement
{
    $context = test();

    return StockMovement::create(array_merge([
        'movement_date' => now()->toDateString(),
        'product_id' => $context->product->id,
        'stock_batch_id' => $context->batch->id,
        'warehouse_id' => $context->warehouse->id,
        'uom_id' => $context->uom->id,
        'created_by' => $context->user->id,
    ], $overrides));
}

/** The state a stale GRN correction leaves: receipt and batch at 75, 4 units sold at 75. */
function leaveStockAtStaleCost(): StockMovement
{
    $context = test();

    $context->grnMovement->update(['unit_cost' => 75, 'total_value' => 750]);
    $context->batch->update(['unit_cost' => 75]);

    return movementOnBatch(['movement_type' => 'sale', 'quantity' => -4, 'unit_cost' => 75, 'total_value' => 300]);
}

it('finds nothing when the line, the receipt, the batch and the issues agree', function () {
    movementOnBatch(['movement_type' => 'sale', 'quantity' => -4, 'unit_cost' => 100, 'total_value' => 400]);

    expect(app(GrnCostDriftService::class)->detect())->toBeEmpty();
});

it('finds a receipt, batch and sale carried at a cost other than the line unit cost', function () {
    leaveStockAtStaleCost();

    $drift = app(GrnCostDriftService::class)->detect();

    expect($drift)->toHaveCount(1)
        ->and($drift[0]->grn_item_id)->toBe($this->item->id)
        ->and($drift[0]->problems)->toEqual(['receipt_movement', 'batch_cost', 'issued_sold_returned']);
});

it('puts the receipt, the batch and the sale back on the line unit cost', function () {
    $sale = leaveStockAtStaleCost();

    $drift = app(GrnCostDriftService::class)->detect()->sole();
    $result = app(GrnCostDriftService::class)->repair($drift);

    expect($result['movements'])->toBe(1)
        ->and((float) $this->grnMovement->fresh()->unit_cost)->toBe(100.0)
        ->and((float) $this->grnMovement->fresh()->total_value)->toBe(1000.0)
        ->and((float) $this->batch->fresh()->unit_cost)->toBe(100.0)
        ->and((float) $sale->fresh()->unit_cost)->toBe(100.0)
        ->and((float) DB::table('stock_valuation_layers')->where('stock_batch_id', $this->batch->id)->value('value_remaining'))->toBe(600.0)
        ->and(app(GrnCostDriftService::class)->detect())->toBeEmpty();
});

it('leaves a dry run untouched', function () {
    leaveStockAtStaleCost();

    $this->artisan('stock:repair-grn-cost-drift', ['--dry-run' => true])->assertSuccessful();

    expect((float) $this->batch->fresh()->unit_cost)->toBe(75.0);
});

it('leaves a batch that a stock adjustment revalued alone', function () {
    leaveStockAtStaleCost();

    movementOnBatch([
        'movement_type' => 'adjustment',
        'reference_type' => StockAdjustment::class,
        'reference_id' => 1,
        'quantity' => 0,
        'unit_cost' => 75,
        'total_value' => 0,
    ]);

    expect(app(GrnCostDriftService::class)->detect())->toBeEmpty();
});

it('restates a settlement line and its header when the sale behind it is re-costed', function () {
    $sale = leaveStockAtStaleCost();

    $settlement = SalesSettlement::factory()->create(['total_cogs' => 300, 'gross_profit' => 500]);
    $settlementItemId = DB::table('sales_settlement_items')->insertGetId([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $this->product->id,
        'quantity_issued' => 4,
        'quantity_sold' => 4,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => 200,
        'total_sales_value' => 800,
        'unit_cost' => 75,
        'total_cogs' => 300,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $sale->update(['reference_type' => SalesSettlement::class, 'reference_id' => $settlement->id]);

    $drift = app(GrnCostDriftService::class)->detect()->sole();
    app(GrnCostDriftService::class)->repair($drift);

    $item = DB::table('sales_settlement_items')->find($settlementItemId);

    expect((float) $item->unit_cost)->toBe(100.0)
        ->and((float) $item->total_cogs)->toBe(400.0)
        ->and((float) $settlement->fresh()->total_cogs)->toBe(400.0)
        ->and((float) $settlement->fresh()->gross_profit)->toBe(400.0);
});

it('moves the Round Off a stale line total went to onto cost of goods sold and Stock In Hand', function () {
    // The line was booked at 10 × 75 = 750, so 250 of its 1,000 went to Round Off. Four units
    // were sold at 75 and six are still on hand, valued at 75.
    $this->item->update(['total_cost' => 750]);
    leaveStockAtStaleCost();

    CurrentStockByBatch::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_batch_id' => $this->batch->id,
        'quantity_on_hand' => 6,
        'unit_cost' => 75,
        'total_value' => 450,
    ]);

    $accounts = DB::table('chart_of_accounts')->whereIn('account_code', ['1151', '5111', '5271', '2142'])->pluck('id', 'account_code');
    $costCenterId = DB::table('cost_centers')->where('code', 'CC006')->value('id');

    $post = fn (string $reference, array $lines) => app(AccountingService::class)->createJournalEntry([
        'entry_date' => now()->toDateString(),
        'reference' => $reference,
        'description' => $reference,
        'lines' => array_map(fn (array $line) => $line + ['cost_center_id' => $costCenterId], $lines),
        'auto_post' => true,
    ])['data'];

    $grnEntry = $post('GRN-TEST', [
        ['account_id' => $accounts['1151'], 'debit' => 750, 'credit' => 0],
        ['account_id' => $accounts['5271'], 'debit' => 250, 'credit' => 0],
        ['account_id' => $accounts['2142'], 'debit' => 0, 'credit' => 1000],
    ]);
    $this->grn->update(['journal_entry_id' => $grnEntry->id]);

    $post('SALE-TEST', [
        ['account_id' => $accounts['5111'], 'debit' => 300, 'credit' => 0],
        ['account_id' => $accounts['1151'], 'debit' => 0, 'credit' => 300],
    ]);

    $drift = app(GrnCostDriftService::class)->detect()->sole();

    expect($drift->problems)->toContain('item_total_cost');

    app(GrnCostDriftService::class)->repair($drift);

    $balance = fn (string $code) => (float) DB::table('journal_entry_details')
        ->where('chart_of_account_id', $accounts[$code])
        ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
        ->value('balance');

    expect((float) $this->item->fresh()->total_cost)->toBe(1000.0)
        ->and($balance('5271'))->toBe(0.0)
        // The four units sold now cost 400 rather than 300, and the six on hand are worth 600.
        ->and($balance('5111'))->toBe(400.0)
        ->and($balance('1151'))->toBe(600.0);
});

it('writes the value a cost revaluation moved its batch by onto the ledger row that has no quantity', function () {
    $this->actingAs($this->user);
    $adjustment = StockAdjustment::factory()->create(['warehouse_id' => $this->warehouse->id]);

    // The receipt (10 units at 100) is the last movement before the batch was revalued to 50.
    movementOnBatch([
        'movement_type' => 'adjustment',
        'reference_type' => StockAdjustment::class,
        'reference_id' => $adjustment->id,
        'quantity' => 0,
        'unit_cost' => 50,
        'total_value' => 0,
    ]);

    $ledgerId = DB::table('inventory_ledger_entries')->insertGetId([
        'date' => now()->toDateString(),
        'transaction_type' => 'adjustment',
        'product_id' => $this->product->id,
        'stock_batch_id' => $this->batch->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_adjustment_id' => $adjustment->id,
        'debit_qty' => 0,
        'credit_qty' => 0,
        'unit_cost' => 50,
        'total_value' => 0,
        'running_balance' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('stock:backfill-revaluation-values', ['--dry-run' => true])->assertSuccessful();
    expect((float) DB::table('inventory_ledger_entries')->where('id', $ledgerId)->value('total_value'))->toBe(0.0);

    $this->artisan('stock:backfill-revaluation-values')->assertSuccessful();

    // 10 units were on hand at 100 when the batch was revalued down to 50.
    expect((float) DB::table('inventory_ledger_entries')->where('id', $ledgerId)->value('total_value'))->toBe(-500.0);
});

it('reports lines out of step in the nightly cost check', function () {
    leaveStockAtStaleCost();

    $this->artisan('inventory:verify-costs')->assertFailed();

    app(GrnCostDriftService::class)->repair(app(GrnCostDriftService::class)->detect()->sole());

    $this->artisan('inventory:verify-costs')->assertSuccessful();
});

it('values a revaluation in the daily stock register so it agrees with the stock value', function () {
    Permission::create(['name' => 'report-inventory-daily-stock-register']);
    $this->user->givePermissionTo('report-inventory-daily-stock-register');
    $this->actingAs($this->user);

    $day = now()->subDays(3)->toDateString();

    // 10 units received at 100, then the batch revalued down to 50: the stock is worth 500.
    DB::table('inventory_ledger_entries')->insert([
        [
            'date' => $day, 'transaction_type' => 'purchase', 'product_id' => $this->product->id,
            'stock_batch_id' => $this->batch->id, 'warehouse_id' => $this->warehouse->id,
            'debit_qty' => 10, 'credit_qty' => 0, 'unit_cost' => 100, 'total_value' => 1000,
            'running_balance' => 10, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'date' => $day, 'transaction_type' => 'adjustment', 'product_id' => $this->product->id,
            'stock_batch_id' => $this->batch->id, 'warehouse_id' => $this->warehouse->id,
            'debit_qty' => 0, 'credit_qty' => 0, 'unit_cost' => 50, 'total_value' => -500,
            'running_balance' => 10, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $this->get(route('reports.daily-stock-register.index', ['date' => now()->toDateString(), 'supplier_id' => $this->supplier->id]))
        ->assertSuccessful()
        ->assertViewHas('reportData', fn ($rows) => (float) $rows->sole()->in_hand_value === 500.0 && (float) $rows->sole()->in_hand === 10.0);
});

it('includes the stock adjustments of the report date in the daily stock register', function () {
    Permission::create(['name' => 'report-inventory-daily-stock-register']);
    $this->user->givePermissionTo('report-inventory-daily-stock-register');
    $this->actingAs($this->user);

    $day = now()->toDateString();
    $row = fn (string $type, float $debit, float $credit, float $cost, float $value) => [
        'date' => $day, 'transaction_type' => $type, 'product_id' => $this->product->id,
        'stock_batch_id' => $this->batch->id, 'warehouse_id' => $this->warehouse->id,
        'debit_qty' => $debit, 'credit_qty' => $credit, 'unit_cost' => $cost, 'total_value' => $value,
        'running_balance' => 0, 'created_at' => now(), 'updated_at' => now(),
    ];

    // Received 10 at 100, then on the same day: 2 units counted short and the cost revalued to 110.
    DB::table('inventory_ledger_entries')->insert([
        $row('purchase', 10, 0, 100, 1000),
        $row('adjustment', 0, 2, 100, -200),
        $row('adjustment', 0, 0, 110, 80),
    ]);

    $this->get(route('reports.daily-stock-register.index', ['date' => $day, 'supplier_id' => $this->supplier->id]))
        ->assertSuccessful()
        ->assertViewHas('reportData', fn ($rows) => (float) $rows->sole()->in_hand === 8.0
            && (float) $rows->sole()->adjustment === -2.0
            && (float) $rows->sole()->in_hand_value === 880.0);
});

it('hides the adjustment column of the daily stock register on request', function () {
    Permission::create(['name' => 'report-inventory-daily-stock-register']);
    $this->user->givePermissionTo('report-inventory-daily-stock-register');
    $this->actingAs($this->user);

    $query = ['date' => now()->toDateString(), 'supplier_id' => $this->supplier->id];

    $this->get(route('reports.daily-stock-register.index', $query))->assertSuccessful()->assertSee('Stock adjustments posted on this date');
    $this->get(route('reports.daily-stock-register.index', $query + ['show_adjustment' => '0']))->assertSuccessful()->assertDontSee('Stock adjustments posted on this date');
});
