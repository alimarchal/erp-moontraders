<?php

use App\Enums\TicketStatus;
use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\CurrentStockByBatch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockValuationLayer;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TicketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * A stock adjustment ticket must, once approved, leave the database exactly as creating the
 * adjustment on the Stock Adjustments screen and posting it would: same tables, balanced journal.
 */

beforeEach(function () {
    $this->withoutVite();

    foreach (['stock-adjustment-list', 'stock-adjustment-create', 'stock-adjustment-edit', 'stock-adjustment-delete', 'stock-adjustment-post'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->otherSupplier = Supplier::factory()->create(['disabled' => false]);
    $this->warehouse = Warehouse::factory()->create(['disabled' => false]);
    $this->uom = Uom::factory()->create();

    $this->admin = User::factory()->create(['is_super_admin' => 'Yes']);

    $this->companyUser = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->companyUser->assignRole('company-user');

    $currency = Currency::factory()->base()->create();
    $expense = AccountType::create(['type_name' => 'Expense', 'report_group' => 'IncomeStatement']);
    $asset = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);
    ChartOfAccount::create(['account_code' => '1151', 'account_name' => 'Stock In Hand', 'account_type_id' => $asset->id, 'currency_id' => $currency->id, 'is_active' => true, 'normal_balance' => 'debit']);
    foreach (['5280' => 'Stock Loss on Recalls', '5281' => 'Stock Loss - Damage', '5282' => 'Stock Loss - Theft', '5283' => 'Stock Loss - Expiry', '5284' => 'Stock Loss - Other'] as $code => $name) {
        ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $expense->id, 'currency_id' => $currency->id, 'is_active' => true, 'normal_balance' => 'debit']);
    }
    CostCenter::create(['code' => 'CC006', 'name' => 'Warehouse & Inventory', 'is_active' => true]);
    AccountingPeriod::create(['name' => 'Test Period', 'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'status' => 'open']);
});

/**
 * A product with one batch of $quantity pieces in the warehouse, with the valuation layer a GRN leaves behind.
 *
 * @return array{product: Product, batch: StockBatch}
 */
function adjustableStock(object $t, float $quantity = 100, ?int $supplierId = null): array
{
    $product = Product::factory()->create(['supplier_id' => $supplierId ?? $t->supplier->id, 'uom_id' => $t->uom->id, 'is_active' => true]);
    $batch = StockBatch::factory()->create(['product_id' => $product->id, 'status' => 'active', 'unit_cost' => 50]);

    $receipt = StockMovement::create([
        'movement_type' => 'grn', 'reference_type' => 'App\\Models\\GoodsReceiptNote', 'reference_id' => $batch->id,
        'movement_date' => now()->toDateString(), 'product_id' => $product->id, 'stock_batch_id' => $batch->id,
        'warehouse_id' => $t->warehouse->id, 'quantity' => $quantity, 'uom_id' => $t->uom->id, 'unit_cost' => 50,
        'total_value' => $quantity * 50, 'created_by' => $t->admin->id,
    ]);
    StockValuationLayer::create([
        'product_id' => $product->id, 'warehouse_id' => $t->warehouse->id, 'stock_batch_id' => $batch->id, 'stock_movement_id' => $receipt->id,
        'receipt_date' => now()->toDateString(), 'quantity_received' => $quantity, 'quantity_remaining' => $quantity,
        'unit_cost' => 50, 'total_value' => $quantity * 50, 'value_remaining' => $quantity * 50, 'priority_order' => 99,
    ]);
    CurrentStockByBatch::create([
        'product_id' => $product->id, 'warehouse_id' => $t->warehouse->id, 'stock_batch_id' => $batch->id,
        'quantity_on_hand' => $quantity, 'unit_cost' => 50, 'total_value' => $quantity * 50,
    ]);

    return ['product' => $product, 'batch' => $batch];
}

/**
 * @param  array{product: Product, batch: StockBatch}  $stock
 * @return array<string, mixed>
 */
function adjustmentPayload(object $t, array $stock, float $counted = 90): array
{
    return [
        'adjustment_date' => now()->toDateString(),
        'supplier_id' => $stock['product']->supplier_id,
        'warehouse_id' => $t->warehouse->id,
        'adjustment_type' => 'damage',
        'reason' => 'Water damage in rack 4',
        'items' => [[
            'product_id' => $stock['product']->id, 'stock_batch_id' => $stock['batch']->id,
            'system_quantity' => 100, 'actual_quantity' => $counted, 'unit_cost' => 50, 'uom_id' => $t->uom->id,
        ]],
    ];
}

/** @return array<string, string> */
function tableFingerprints(): array
{
    $skip = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'];

    return collect(Schema::getTableListing())
        ->map(fn ($name) => str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name)
        ->reject(fn ($name) => in_array($name, $skip, true))
        ->mapWithKeys(fn ($name) => [$name => md5(DB::table($name)->get()->map(fn ($row) => json_encode($row))->sort()->implode('|'))])
        ->all();
}

it('creates only a pending ticket when a company user requests a stock adjustment — stock is untouched until approval', function () {
    $stock = adjustableStock($this);

    $this->actingAs($this->companyUser)
        ->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'Damaged cartons'] + adjustmentPayload($this, $stock))
        ->assertRedirect();

    $ticket = Ticket::firstOrFail();
    $line = $ticket->items->first()->payload['items'][0];

    expect($ticket->status)->toBe(TicketStatus::Pending)
        ->and($ticket->supplier_id)->toBe($this->supplier->id)
        ->and($line['system_quantity'])->toEqual(100)
        ->and($line['adjustment_quantity'])->toEqual(-10)
        ->and($line['adjustment_value'])->toEqual(-500)
        ->and(StockAdjustment::count())->toBe(0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('quantity_on_hand'))->toBe(100.0);

    $this->actingAs($this->companyUser)->get(route('tickets.show', $ticket))->assertOk()->assertSee('Water damage in rack 4')->assertSee('Total value');
});

it('validates like the stock adjustments screen and keeps the company scope', function () {
    $stock = adjustableStock($this);
    $foreign = adjustableStock($this, 50, $this->otherSupplier->id);
    $base = ['type' => 'stock_adjustment', 'title' => 'x'];
    $this->actingAs($this->companyUser);

    $this->post(route('tickets.store'), $base + adjustmentPayload($this, $stock, 100))->assertSessionHasErrors('items.0.actual_quantity'); // nothing to adjust
    $this->post(route('tickets.store'), $base + ['adjustment_date' => now()->addDay()->toDateString()] + adjustmentPayload($this, $stock))->assertSessionHasErrors('adjustment_date');
    $this->post(route('tickets.store'), $base + ['supplier_id' => $this->otherSupplier->id] + adjustmentPayload($this, $foreign))->assertSessionHasErrors('supplier_id');
    $this->post(route('tickets.store'), $base + ['adjustment_type' => 'lost-in-space'] + adjustmentPayload($this, $stock))->assertSessionHasErrors('adjustment_type');

    $otherProductBatch = adjustableStock($this);
    $mixed = adjustmentPayload($this, $stock);
    $mixed['items'][0]['stock_batch_id'] = $otherProductBatch['batch']->id;
    $this->post(route('tickets.store'), $base + $mixed)->assertSessionHasErrors('items.0.stock_batch_id');

    $this->get(route('tickets.adjustment-batches', [$foreign['product'], $this->warehouse]))->assertForbidden();
    expect(Ticket::count())->toBe(0);
});

it('lists the batches that hold stock in the chosen warehouse for the adjustment lines', function () {
    $stock = adjustableStock($this);
    $emptyWarehouse = Warehouse::factory()->create(['disabled' => false]);

    $this->actingAs($this->companyUser);

    $this->getJson(route('tickets.adjustment-batches', [$stock['product'], $this->warehouse]))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $stock['batch']->id)->assertJsonPath('0.quantity', '100.00');
    $this->getJson(route('tickets.adjustment-batches', [$stock['product'], $emptyWarehouse]))->assertOk()->assertJsonCount(0);
});

it('approving creates the stock adjustment and posts it, hitting exactly the same tables as the Stock Adjustments screen', function () {
    $viaScreen = adjustableStock($this);
    $viaTicket = adjustableStock($this);
    $this->actingAs($this->admin);

    // 1. The classic path: create on /stock-adjustments, then post it.
    $beforeScreen = tableFingerprints();
    $this->post(route('stock-adjustments.store'), adjustmentPayload($this, $viaScreen))->assertRedirect();
    $adjustment = StockAdjustment::firstOrFail();
    $this->post(route('stock-adjustments.post', $adjustment), ['password' => 'password'])->assertRedirect();
    $touchedByScreen = collect(tableFingerprints())->filter(fn ($hash, $table) => ($beforeScreen[$table] ?? null) !== $hash)->keys()->sort()->values()->all();

    // 2. The ticket path.
    $this->actingAs($this->companyUser)
        ->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'Damaged cartons'] + adjustmentPayload($this, $viaTicket))->assertSessionHasNoErrors()->assertRedirect();
    $ticket = Ticket::firstOrFail();

    $beforeTicket = tableFingerprints();
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket), ['password' => 'password'])->assertRedirect(route('tickets.show', $ticket));
    $touchedByTicket = collect(tableFingerprints())->filter(fn ($hash, $table) => ($beforeTicket[$table] ?? null) !== $hash)->keys()
        ->reject(fn ($table) => in_array($table, ['tickets', 'ticket_items', 'ticket_histories'], true))->sort()->values()->all();

    expect($touchedByTicket)->toBe($touchedByScreen)
        ->and($touchedByScreen)->toContain('stock_adjustments', 'stock_adjustment_items', 'stock_movements', 'stock_valuation_layers', 'current_stock_by_batch', 'journal_entries', 'journal_entry_details');

    $ticket->refresh();
    $created = StockAdjustment::where('id', '!=', $adjustment->id)->firstOrFail();

    expect($ticket->status)->toBe(TicketStatus::Approved)
        ->and($created->status)->toBe('posted')
        ->and($created->posted_by)->toBe($this->admin->id)
        ->and($created->journal_entry_id)->not->toBeNull()
        ->and($ticket->items->first()->payload['stock_adjustment_number'])->toBe($created->adjustment_number)
        ->and($ticket->histories()->reorder('id', 'desc')->first()->remarks)->toContain($created->adjustment_number);

    // Same end state for both products: 100 → 90, valued at cost, journal balanced.
    foreach ([$viaScreen, $viaTicket] as $stock) {
        expect((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('quantity_on_hand'))->toBe(90.0)
            ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('total_value'))->toBe(4500.0)
            ->and((float) StockValuationLayer::where('stock_batch_id', $stock['batch']->id)->value('quantity_remaining'))->toBe(90.0);
    }
    $entry = DB::table('journal_entries')->where('id', $created->journal_entry_id)->first();
    $totals = DB::table('journal_entry_details')->where('journal_entry_id', $entry->id)->selectRaw('sum(debit) as d, sum(credit) as c')->first();
    expect((float) $totals->d)->toBe((float) $totals->c)->and((float) $totals->d)->toBe(500.0);
});

it('needs the post permission and the admin password to approve, and changes nothing otherwise', function () {
    $stock = adjustableStock($this);
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'x'] + adjustmentPayload($this, $stock));
    $ticket = Ticket::firstOrFail();

    $approver = User::factory()->create();
    $approver->assignRole(Role::findOrCreate('admin', 'web'));
    $approver->givePermissionTo(['ticket-list', 'ticket-approve']);
    $this->actingAs($approver)->post(route('tickets.approve', $ticket), ['password' => 'password'])->assertSessionHasErrors('ticket');

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertSessionHasErrors('password');
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket), ['password' => 'wrong'])->assertSessionHasErrors('password');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Pending)
        ->and(StockAdjustment::count())->toBe(0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('quantity_on_hand'))->toBe(100.0);
});

it('refuses to approve a count taken against stock that has since moved, and posts once the ticket is recounted', function () {
    $stock = adjustableStock($this);
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'x'] + adjustmentPayload($this, $stock, 90));
    $ticket = Ticket::firstOrFail();

    // Someone sold 20 meanwhile: the count of 90 was taken against 100, so it no longer says what the batch holds.
    CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->update(['quantity_on_hand' => 80, 'total_value' => 4000]);
    StockValuationLayer::where('stock_batch_id', $stock['batch']->id)->update(['quantity_remaining' => 80, 'value_remaining' => 4000]);

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket), ['password' => 'password'])
        ->assertSessionHasErrors(['ticket' => "Batch {$stock['batch']->batch_code} was counted against 100 but the system now holds 80. Edit the ticket to recount it before approving."]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Pending)
        ->and(StockAdjustment::count())->toBe(0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('quantity_on_hand'))->toBe(80.0);

    // Editing re-reads the system quantity, so the recount of 75 is now a loss of 5 and posts.
    $this->actingAs($this->companyUser)->put(route('tickets.update', $ticket), ['type' => 'stock_adjustment', 'title' => 'x'] + adjustmentPayload($this, $stock, 75))->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket), ['password' => 'password'])->assertSessionHasNoErrors();

    $posted = StockAdjustment::with('items')->firstOrFail()->items->first();
    $line = $ticket->fresh()->items->first()->payload['items'][0];

    expect((float) $posted->system_quantity)->toBe(80.0)
        ->and((float) $posted->adjustment_quantity)->toBe(-5.0)
        ->and((float) $line['system_quantity'])->toBe(80.0)
        ->and((float) $line['adjustment_quantity'])->toBe(-5.0)
        ->and((float) $line['adjustment_value'])->toBe((float) $posted->adjustment_value)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('quantity_on_hand'))->toBe(75.0);
});

it('rejects a stock adjustment ticket for a batch that is already on another waiting ticket', function () {
    $stock = adjustableStock($this);
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'first'] + adjustmentPayload($this, $stock, 90))->assertSessionHasNoErrors();
    $first = Ticket::firstOrFail();

    $this->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'second'] + adjustmentPayload($this, $stock, 119))
        ->assertSessionHasErrors(['items.0.stock_batch_id' => "Batch {$stock['batch']->batch_code} is already on ticket #{$first->id}, which is waiting for approval. Edit that ticket instead."]);

    // The waiting ticket itself can still be edited.
    $this->put(route('tickets.update', $first), ['type' => 'stock_adjustment', 'title' => 'first'] + adjustmentPayload($this, $stock, 95))->assertSessionHasNoErrors();

    expect(Ticket::count())->toBe(1);

    // Once it is decided, the batch can be counted again.
    $this->actingAs($this->admin)->post(route('tickets.approve', $first), ['password' => 'password'])->assertSessionHasNoErrors();
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'third'] + adjustmentPayload($this, $stock, 90))->assertSessionHasNoErrors();
});

it('leaves a ticket pending with no draft behind when its batch has run out', function () {
    $gone = adjustableStock($this);
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'gone'] + adjustmentPayload($this, $gone, 0));
    $goneTicket = Ticket::firstWhere('title', 'gone');
    CurrentStockByBatch::where('stock_batch_id', $gone['batch']->id)->update(['quantity_on_hand' => 0, 'total_value' => 0]);

    $this->actingAs($this->admin)->post(route('tickets.approve', $goneTicket), ['password' => 'password'])->assertSessionHasErrors('ticket');

    expect($goneTicket->fresh()->status)->toBe(TicketStatus::Pending)
        ->and(StockAdjustment::count())->toBe(0);
});

it('shows the password step and links the posted adjustment on the ticket page', function () {
    $stock = adjustableStock($this);
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'x'] + adjustmentPayload($this, $stock));
    $ticket = Ticket::firstOrFail();

    $this->actingAs($this->admin)->get(route('tickets.show', $ticket))->assertOk()
        ->assertSee('Approve &amp; post', false)->assertSee('name="password"', false);

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket), ['password' => 'password']);

    $number = StockAdjustment::firstOrFail()->adjustment_number;
    $this->actingAs($this->admin)->get(route('tickets.show', $ticket))->assertSee('Open '.$number)->assertDontSee('name="password"', false);
    $this->actingAs($this->companyUser)->get(route('tickets.show', $ticket))->assertSee($number.' posted');
});

it('renders the stock adjustment form for a company user', function () {
    $this->actingAs($this->companyUser)->get(route('tickets.create', ['type' => 'stock_adjustment']))
        ->assertOk()->assertSee('Adjustment details')->assertSee('name="warehouse_id"', false)->assertSee('Stock Adjustment');
});

/**
 * A unit cost sent with a ticket line, as an old form or a hand-made request still could.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function withUnitCost(array $payload, float $unitCost): array
{
    $payload['items'][0]['unit_cost'] = $unitCost;

    return $payload;
}

it('rejects a line that only changes the unit cost: tickets never change a batch\'s cost', function () {
    $stock = adjustableStock($this);
    $this->actingAs($this->companyUser);

    $this->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'same'] + adjustmentPayload($this, $stock, 100))->assertSessionHasErrors('items.0.actual_quantity');
    $this->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'cost only'] + withUnitCost(adjustmentPayload($this, $stock, 100), 60))->assertSessionHasErrors('items.0.actual_quantity');

    expect(Ticket::count())->toBe(0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('unit_cost'))->toBe(50.0);
});

it('values an approved ticket at the batch\'s own cost and never revalues it, whatever unit cost is sent', function () {
    $stock = adjustableStock($this);

    $this->actingAs($this->companyUser)
        ->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'Count fix'] + withUnitCost(adjustmentPayload($this, $stock, 90), 60))
        ->assertSessionHasNoErrors()->assertRedirect();
    $ticket = Ticket::firstOrFail();

    $line = $ticket->items->first()->payload['items'][0];
    expect((float) $line['unit_cost'])->toBe(50.0)
        ->and((float) $line['adjustment_value'])->toBe(-500.0)
        ->and($line)->not->toHaveKey('revaluation_value');

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket), ['password' => 'password'])->assertRedirect(route('tickets.show', $ticket));

    $created = StockAdjustment::firstOrFail();
    $totals = DB::table('journal_entry_details')->where('journal_entry_id', $created->journal_entry_id)->selectRaw('sum(debit) as d, sum(credit) as c')->first();
    expect($created->status)->toBe('posted')
        ->and((float) $totals->d)->toBe((float) $totals->c)->and((float) $totals->d)->toBe(500.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('quantity_on_hand'))->toBe(90.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('unit_cost'))->toBe(50.0)
        ->and((float) $stock['batch']->fresh()->unit_cost)->toBe(50.0)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Approved);
});

it('shows the selling-cost-only hint on the stock adjustment form, with no unit cost field', function () {
    $this->actingAs($this->companyUser)->get(route('tickets.create', ['type' => 'stock_adjustment']))
        ->assertOk()->assertSee('To change only the selling cost', false)
        ->assertSee('The value uses the batch\'s own unit cost', false)
        ->assertDontSee('name="confirm_cost_change"', false);
});

it('needs no cost confirmation or password to raise a ticket, since a ticket cannot change the unit cost', function () {
    $stock = adjustableStock($this);
    $this->actingAs($this->companyUser);

    $this->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'cost'] + withUnitCost(adjustmentPayload($this, $stock, 90), 60))
        ->assertSessionHasNoErrors()->assertSessionDoesntHaveErrors(['confirm_cost_change', 'password'])->assertRedirect();

    expect(Ticket::count())->toBe(1)
        ->and((float) Ticket::firstOrFail()->items->first()->payload['items'][0]['unit_cost'])->toBe(50.0);
});

it('updates only the batch selling price when just the selling cost changes, with no password needed', function () {
    $stock = adjustableStock($this);
    $stock['batch']->update(['selling_price' => 80]);
    CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->update(['selling_price' => 80]);

    $payload = ['type' => 'stock_adjustment', 'title' => 'selling'] + adjustmentPayload($this, $stock, 100);
    $payload['items'][0]['selling_price'] = 95;

    $this->actingAs($this->companyUser)->post(route('tickets.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $ticket = Ticket::firstWhere('title', 'selling');

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket), ['password' => 'password'])->assertRedirect();

    expect((float) $stock['batch']->fresh()->selling_price)->toBe(95.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('selling_price'))->toBe(95.0)
        ->and(StockAdjustment::count())->toBe(0)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Approved);
});

it('applies a new selling cost to every batch of the product when All batches is chosen', function () {
    $stock = adjustableStock($this);
    $other = StockBatch::factory()->create(['product_id' => $stock['product']->id, 'status' => 'active', 'selling_price' => 70]);
    $stock['batch']->update(['selling_price' => 80]);
    CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->update(['selling_price' => 80]);
    CurrentStockByBatch::create(['product_id' => $stock['product']->id, 'warehouse_id' => $this->warehouse->id, 'stock_batch_id' => $other->id, 'quantity_on_hand' => 10, 'unit_cost' => 50, 'selling_price' => 70, 'total_value' => 500, 'status' => 'active']);

    $payload = ['type' => 'stock_adjustment', 'title' => 'sell all'] + adjustmentPayload($this, $stock, 100);
    $payload['items'][0]['selling_price'] = 99;
    $payload['items'][0]['selling_scope'] = 'all';
    $this->actingAs($this->companyUser)->post(route('tickets.store'), $payload)->assertSessionHasNoErrors();

    $ticket = Ticket::firstWhere('title', 'sell all');
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket), ['password' => 'password'])->assertRedirect();

    expect((float) $stock['batch']->fresh()->selling_price)->toBe(99.0)
        ->and((float) $other->fresh()->selling_price)->toBe(99.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $other->id)->value('selling_price'))->toBe(99.0)
        ->and((float) $stock['product']->fresh()->unit_sell_price)->toBe(99.0);
});

it('lists stock adjustments with tabs, filters and totals, and serves the help page', function () {
    $this->actingAs($this->admin)->get(route('stock-adjustments.index', ['filter' => ['status' => 'posted']]))->assertOk()->assertSee('How it works');
    $this->actingAs($this->admin)->get(route('stock-adjustments.help'))->assertOk()->assertSee('How stock adjustments work');
});

it('shows the selling cost and unit cost on the posted stock adjustment page', function () {
    $stock = adjustableStock($this);
    $stock['batch']->update(['selling_price' => 80]);
    CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->update(['selling_price' => 80]);
    $payload = ['type' => 'stock_adjustment', 'title' => 'view'] + adjustmentPayload($this, $stock, 90);
    $payload['items'][0]['selling_price'] = 95;
    $this->actingAs($this->companyUser)->post(route('tickets.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('tickets.approve', Ticket::firstWhere('title', 'view')), ['password' => 'password'])->assertRedirect();

    $this->actingAs($this->admin)->get(route('stock-adjustments.show', StockAdjustment::first()))
        ->assertOk()->assertSee('Selling cost')->assertSee('Unit cost')->assertSee('80.00')->assertSee('95.00');
});

it('never sets back a selling cost that changed after a quantity-only ticket was raised', function () {
    $stock = adjustableStock($this);
    $stock['batch']->update(['selling_price' => 80]);
    CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->update(['selling_price' => 80]);

    // The form pre-fills the batch's current selling cost on every line.
    $payload = ['type' => 'stock_adjustment', 'title' => 'count'] + adjustmentPayload($this, $stock, 90);
    $payload['items'][0]['selling_price'] = 80;
    $this->actingAs($this->companyUser)->post(route('tickets.store'), $payload)->assertSessionHasNoErrors();

    // The batch's price is raised to 95 before the count is approved.
    $stock['batch']->update(['selling_price' => 95]);
    CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->update(['selling_price' => 95]);

    $this->actingAs($this->admin)->post(route('tickets.approve', Ticket::firstOrFail()), ['password' => 'password'])->assertSessionHasNoErrors();

    expect((float) $stock['batch']->fresh()->selling_price)->toBe(95.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('selling_price'))->toBe(95.0)
        ->and((float) CurrentStockByBatch::where('stock_batch_id', $stock['batch']->id)->value('quantity_on_hand'))->toBe(90.0);
});

it('refuses to edit or delete a ticket that was approved in the meantime', function () {
    $stock = adjustableStock($this);
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'stock_adjustment', 'title' => 'x'] + adjustmentPayload($this, $stock, 90));
    $stale = Ticket::firstOrFail();
    app(TicketService::class)->approve($stale, $this->admin);

    $service = app(TicketService::class);
    expect(fn () => $service->update($stale, $this->companyUser, ['title' => 'y'] + adjustmentPayload($this, $stock, 80)))->toThrow(ValidationException::class)
        ->and(fn () => $service->delete($stale))->toThrow(ValidationException::class)
        ->and($stale->fresh()->title)->toBe('x')
        ->and($stale->fresh()->items->first()->payload['items'][0]['actual_quantity'])->toEqual(90);
});
