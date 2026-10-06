<?php

use App\Enums\TicketStatus;
use App\Models\CurrentStockByBatch;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/*
 * An approved price ticket must hit exactly the same tables, rows and log entries as
 * editing the product on the Products screen. These tests build two identical products,
 * change one through products.update and the other through a ticket, and compare.
 */

beforeEach(function () {
    $this->withoutVite();

    $this->admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->uom = Uom::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->supplier = Supplier::factory()->create(['disabled' => false]);
});

/**
 * Product with: a batch holding valuation-layer stock + GRN line + current stock, a batch with
 * only a current-stock row, a depleted batch, and a promotional batch.
 *
 * @return array<string, mixed>
 */
function buildParityProduct(object $t): array
{
    $product = Product::factory()->create([
        'supplier_id' => $t->supplier->id, 'uom_id' => $t->uom->id, 'valuation_method' => 'FIFO',
        'unit_sell_price' => 100, 'cost_price' => 80, 'expiry_price' => 50, 'reorder_level' => 10,
    ]);
    $grn = GoodsReceiptNote::factory()->create(['warehouse_id' => $t->warehouse->id, 'supplier_id' => $t->supplier->id]);

    $make = function (array $batch = [], ?int $remaining = null, ?int $line = null, ?int $onHand = null) use ($t, $product, $grn) {
        $batch = StockBatch::factory()->create($batch + ['product_id' => $product->id, 'selling_price' => 100, 'is_promotional' => false, 'status' => 'active']);
        $grnItem = $line ? GoodsReceiptNoteItem::factory()->create([
            'grn_id' => $grn->id, 'product_id' => $product->id, 'selling_price' => 100, 'is_promotional' => false, 'line_no' => $line,
        ]) : null;

        if ($remaining !== null) {
            DB::table('stock_valuation_layers')->insert([
                'product_id' => $product->id, 'warehouse_id' => $t->warehouse->id, 'stock_batch_id' => $batch->id,
                'stock_movement_id' => DB::table('stock_movements')->insertGetId([
                    'movement_type' => 'grn', 'movement_date' => now()->toDateString(), 'product_id' => $product->id,
                    'stock_batch_id' => $batch->id, 'warehouse_id' => $t->warehouse->id, 'quantity' => 50, 'uom_id' => $t->uom->id,
                    'unit_cost' => 80, 'total_value' => 4000, 'created_by' => $t->admin->id, 'created_at' => now(), 'updated_at' => now(),
                ]),
                'grn_item_id' => $grnItem?->id, 'receipt_date' => now()->toDateString(), 'quantity_received' => 50,
                'quantity_remaining' => $remaining, 'unit_cost' => 80, 'is_depleted' => $remaining === 0,
                'is_promotional' => (bool) ($batch->is_promotional), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if ($onHand !== null) {
            CurrentStockByBatch::create([
                'product_id' => $product->id, 'warehouse_id' => $t->warehouse->id, 'stock_batch_id' => $batch->id,
                'quantity_on_hand' => $onHand, 'unit_cost' => 80, 'selling_price' => 100,
                'is_promotional' => (bool) $batch->is_promotional, 'status' => 'active', 'last_updated' => now(),
            ]);
        }

        return $batch;
    };

    return [
        'product' => $product,
        'layerBatch' => $make([], 30, 1, 30),
        'currentOnlyBatch' => $make([], null, null, 12),
        'depletedBatch' => $make([], 0, 2),
        'promoBatch' => $make(['is_promotional' => true, 'promotional_selling_price' => 80], null, null, 5),
    ];
}

/**
 * Everything a price change can touch, in a shape comparable between two products.
 *
 * @param  array<string, mixed>  $scenario
 * @return array<string, mixed>
 */
function parityState(array $scenario): array
{
    $product = $scenario['product']->fresh();
    $order = collect(['layerBatch', 'currentOnlyBatch', 'depletedBatch', 'promoBatch']);
    $ids = $order->mapWithKeys(fn ($key) => [$scenario[$key]->id => $key]);

    return [
        'product' => $product->only(['unit_sell_price', 'cost_price', 'expiry_price', 'reorder_level', 'is_active']),
        'batches' => $order->mapWithKeys(fn ($key) => [$key => (float) $scenario[$key]->fresh()->selling_price])->all(),
        'currentStock' => DB::table('current_stock_by_batch')->where('product_id', $product->id)->get()
            ->mapWithKeys(fn ($r) => [$ids[$r->stock_batch_id] => (float) $r->selling_price])->sortKeys()->all(),
        'grnItems' => DB::table('goods_receipt_note_items')->where('product_id', $product->id)->orderBy('line_no')->pluck('selling_price', 'line_no')
            ->map(fn ($p) => (float) $p)->all(),
        'logs' => DB::table('product_price_change_logs')->where('product_id', $product->id)->orderBy('price_type')->get()
            ->map(fn ($l) => [
                $l->price_type, (float) $l->old_price, (float) $l->new_price, (int) $l->impacted_batch_count,
                collect(json_decode($l->impacted_batch_ids ?? '[]'))->map(fn ($id) => $ids[$id])->sort()->values()->all(),
            ])->all(),
    ];
}

it('hits exactly the same data as editing the product when a price ticket is approved for all batches', function () {
    $viaProduct = buildParityProduct($this);
    $viaTicket = buildParityProduct($this);

    $this->actingAs($this->admin)->put(route('products.update', $viaProduct['product']), [
        'product_code' => $viaProduct['product']->product_code, 'product_name' => $viaProduct['product']->product_name,
        'uom_id' => $this->uom->id, 'valuation_method' => 'FIFO', 'supplier_id' => $this->supplier->id,
        'unit_sell_price' => 125, 'cost_price' => 90, 'expiry_price' => 40, 'reorder_level' => 25, 'is_active' => 1,
    ])->assertRedirect(route('products.index'));

    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->admin->id]);
    $ticket->items()->create([
        'product_id' => $viaTicket['product']->id, 'apply_to_all_batches' => true,
        'new_unit_sell_price' => 125, 'new_cost_price' => 90, 'new_expiry_price' => 40, 'new_reorder_level' => 25,
    ]);
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertRedirect();

    $expected = parityState($viaProduct);

    // Sanity: the scenario really exercises every table, so equality below is meaningful.
    expect($expected['batches'])->toBe(['layerBatch' => 125.0, 'currentOnlyBatch' => 125.0, 'depletedBatch' => 100.0, 'promoBatch' => 100.0])
        ->and($expected['grnItems'])->toBe([1 => 125.0, 2 => 100.0])
        ->and($expected['logs'])->toHaveCount(3);

    expect(parityState($viaTicket))->toEqual($expected);
});

it('lists in the batch drop-down exactly the batches an all-batches approval would change', function () {
    $scenario = buildParityProduct($this);
    $this->admin->givePermissionTo(Permission::findOrCreate('ticket-create'));

    $listed = collect($this->actingAs($this->admin)->getJson(route('tickets.product-batches', $scenario['product']))->assertOk()->json())
        ->pluck('id')->sort()->values()->all();

    expect($listed)->toBe(collect([$scenario['layerBatch']->id, $scenario['currentOnlyBatch']->id])->sort()->values()->all());

    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->admin->id]);
    $ticket->items()->create(['product_id' => $scenario['product']->id, 'apply_to_all_batches' => true, 'new_unit_sell_price' => 140]);
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket));

    $changed = StockBatch::where('product_id', $scenario['product']->id)->where('selling_price', 140)->pluck('id')->sort()->values()->all();
    expect($changed)->toBe($listed);
});

it('limits a selected-batch approval to those batches and their own GRN lines', function () {
    $scenario = buildParityProduct($this);

    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->admin->id]);
    $ticket->items()->create([
        'product_id' => $scenario['product']->id, 'apply_to_all_batches' => false,
        'batch_ids' => [$scenario['currentOnlyBatch']->id], 'new_unit_sell_price' => 130,
    ]);
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertRedirect();

    $state = parityState($scenario);

    expect($state['batches'])->toBe(['layerBatch' => 100.0, 'currentOnlyBatch' => 130.0, 'depletedBatch' => 100.0, 'promoBatch' => 100.0])
        ->and($state['currentStock'])->toBe(['currentOnlyBatch' => 130.0, 'layerBatch' => 100.0, 'promoBatch' => 100.0])
        ->and($state['grnItems'])->toBe([1 => 100.0, 2 => 100.0])
        ->and($state['product']['unit_sell_price'])->toBe('100.00')
        ->and($state['logs'])->toHaveCount(1)
        ->and($state['logs'][0][4])->toBe(['currentOnlyBatch']);
});

it('selected batches need a new selling price and the batches must belong to the product', function () {
    $scenario = buildParityProduct($this);
    $other = buildParityProduct($this);

    $base = ['type' => 'price_update', 'title' => 'x'];

    $this->actingAs($this->admin)->post(route('tickets.store'), $base + ['items' => [[
        'product_id' => $scenario['product']->id, 'batch_scope' => 'selected', 'batch_ids' => [$scenario['layerBatch']->id], 'cost_price' => '95',
    ]]])->assertSessionHasErrors('items.0.unit_sell_price');

    $this->actingAs($this->admin)->post(route('tickets.store'), $base + ['items' => [[
        'product_id' => $scenario['product']->id, 'batch_scope' => 'selected', 'batch_ids' => [$other['layerBatch']->id], 'unit_sell_price' => '95',
    ]]])->assertSessionHasErrors('items.0.batch_ids');

    expect(Ticket::count())->toBe(0);
});

it('warns the approver when the product changed after the ticket was raised', function () {
    $scenario = buildParityProduct($this);
    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->admin->id]);
    $ticket->items()->create(['product_id' => $scenario['product']->id, 'old_unit_sell_price' => 100, 'new_unit_sell_price' => 120]);

    $this->actingAs($this->admin)->get(route('tickets.show', $ticket))->assertDontSee('changed after this ticket was raised');

    $scenario['product']->update(['unit_sell_price' => 110]);

    $this->actingAs($this->admin)->get(route('tickets.show', $ticket))
        ->assertSee('changed after this ticket was raised')->assertSee('110.00');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Pending);
});

it('shows ticket approvals in the Product Price Change Log report just like product edits', function () {
    Permission::findOrCreate('report-audit-product-price-change-log');
    $this->admin->update(['name' => 'Approving Admin']);

    $viaProduct = buildParityProduct($this);
    $viaTicket = buildParityProduct($this);
    $viaProduct['product']->update(['product_name' => 'Edited On Products Screen']);
    $viaTicket['product']->update(['product_name' => 'Changed By Ticket']);

    $this->actingAs($this->admin)->put(route('products.update', $viaProduct['product']), [
        'product_code' => $viaProduct['product']->product_code, 'product_name' => 'Edited On Products Screen',
        'uom_id' => $this->uom->id, 'valuation_method' => 'FIFO', 'supplier_id' => $this->supplier->id,
        'unit_sell_price' => 125, 'cost_price' => 90, 'expiry_price' => 40, 'reorder_level' => 25, 'is_active' => 1,
    ]);

    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->admin->id]);
    $ticket->items()->create([
        'product_id' => $viaTicket['product']->id, 'apply_to_all_batches' => true,
        'new_unit_sell_price' => 125, 'new_cost_price' => 90, 'new_expiry_price' => 40, 'new_reorder_level' => 25,
    ]);
    $this->post(route('tickets.approve', $ticket));

    $report = $this->get(route('reports.product-price-change-log.index'))->assertSuccessful();

    $report->assertSee('Edited On Products Screen')->assertSee('Changed By Ticket')->assertSee('Approving Admin');

    $rows = fn ($product) => $report->viewData('logs')->filter(fn ($log) => $log->product_id === $product->id)
        ->map(fn ($log) => [$log->price_type, (float) $log->old_price, (float) $log->new_price, $log->impacted_batch_count])
        ->sort()->values()->all();

    expect($rows($viaTicket['product']))->toHaveCount(3)->toEqual($rows($viaProduct['product']));
});
