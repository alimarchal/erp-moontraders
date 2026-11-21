<?php

use App\Enums\TicketStatus;
use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete', 'ticket-approve'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->companyUser = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->companyUser->givePermissionTo(['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete']);

    Role::findOrCreate('admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo(['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete', 'ticket-approve']);

    $this->product = Product::factory()->create([
        'supplier_id' => $this->supplier->id, 'unit_sell_price' => 100, 'cost_price' => 80, 'expiry_price' => 50, 'reorder_level' => 10, 'is_active' => true,
    ]);
});

function guardBatch(Product $product, float $price): StockBatch
{
    $batch = StockBatch::create([
        'batch_code' => 'G-'.fake()->unique()->numerify('#####'), 'product_id' => $product->id, 'receipt_date' => now()->toDateString(),
        'unit_cost' => 80, 'selling_price' => $price, 'status' => 'active', 'is_active' => true, 'is_promotional' => false,
    ]);
    $warehouseId = DB::table('warehouses')->value('id') ?? DB::table('warehouses')->insertGetId([
        'warehouse_name' => 'Main', 'disabled' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('current_stock_by_batch')->insert([
        'product_id' => $product->id, 'warehouse_id' => $warehouseId, 'stock_batch_id' => $batch->id,
        'quantity_on_hand' => 10, 'unit_cost' => 80, 'selling_price' => $price, 'total_value' => 800,
    ]);

    return $batch;
}

it('refuses to approve a price ticket raised against a price that has changed since', function () {
    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'price_update', 'title' => 'A', 'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'cost_price' => '60']],
    ])->assertSessionHasNoErrors();
    $ticket = Ticket::firstOrFail();

    // Another change moves the cost to 70 before this ticket is approved.
    $this->product->update(['cost_price' => 70]);

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))
        ->assertSessionHasErrors(['ticket' => "{$this->product->product_name}: cost price was 80.00 when this ticket was raised but is now 70.00. Edit the ticket to confirm the new figure before approving."]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Pending)
        ->and((float) $this->product->fresh()->cost_price)->toBe(70.0);

    // Editing captures the current figure, so it can then be approved.
    $this->actingAs($this->companyUser)->put(route('tickets.update', $ticket), [
        'type' => 'price_update', 'title' => 'A', 'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'cost_price' => '60']],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertSessionHasNoErrors();

    expect((float) $this->product->fresh()->cost_price)->toBe(60.0);
});

it('rejects a selected-batch selling price that every chosen batch already has', function () {
    $batch = guardBatch($this->product, 120);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'price_update', 'title' => 'noop', 'items' => [['product_id' => $this->product->id, 'batch_scope' => 'selected', 'batch_ids' => [$batch->id], 'unit_sell_price' => '120']],
    ])->assertSessionHasErrors('items.0.unit_sell_price');

    expect(Ticket::count())->toBe(0);
});

it('logs each selected batch\'s own old selling price', function () {
    $batch = guardBatch($this->product, 110);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'price_update', 'title' => 'batch', 'items' => [['product_id' => $this->product->id, 'batch_scope' => 'selected', 'batch_ids' => [$batch->id], 'unit_sell_price' => '125']],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('tickets.approve', Ticket::firstOrFail()))->assertSessionHasNoErrors();

    $log = ProductPriceChangeLog::where('price_type', 'selling_price')->sole();

    expect((float) $log->old_price)->toBe(110.0)
        ->and((float) $log->new_price)->toBe(125.0)
        ->and($log->impacted_batch_ids)->toBe([$batch->id])
        ->and((float) $batch->fresh()->selling_price)->toBe(125.0);
});

it('records the ticket as the source of the price changes it approves', function () {
    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'price_update', 'title' => 'src', 'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'cost_price' => '85']],
    ])->assertSessionHasNoErrors();
    $ticket = Ticket::firstOrFail();
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertSessionHasNoErrors();

    $log = ProductPriceChangeLog::sole();

    expect($log->source)->toBe('ticket')->and($log->source_reference)->toBe($ticket->ticket_number);
});
