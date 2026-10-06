<?php

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete', 'ticket-approve', 'setting-view'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->otherSupplier = Supplier::factory()->create(['disabled' => false]);

    $this->companyUser = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->companyUser->givePermissionTo(['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete']);

    Role::findOrCreate('admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo(['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete', 'ticket-approve']);

    $this->product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'unit_sell_price' => 100,
        'cost_price' => 80,
        'expiry_price' => 50,
        'reorder_level' => 10,
        'is_active' => true,
    ]);
});

function makeBatch(Product $product, float $price, bool $withStock = true): StockBatch
{
    $batch = StockBatch::create([
        'batch_code' => 'B-'.fake()->unique()->numerify('#####'),
        'product_id' => $product->id,
        'receipt_date' => now()->toDateString(),
        'unit_cost' => 80,
        'selling_price' => $price,
        'status' => 'active',
        'is_active' => true,
        'is_promotional' => false,
    ]);

    if ($withStock) {
        $warehouseId = DB::table('warehouses')->value('id') ?? DB::table('warehouses')->insertGetId(
            array_merge(['warehouse_name' => 'Main '.$batch->id], warehouseDefaults())
        );

        DB::table('current_stock_by_batch')->insert([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'stock_batch_id' => $batch->id,
            'quantity_on_hand' => 10,
            'unit_cost' => 80,
            'selling_price' => $price,
            'total_value' => 800,
        ]);
    }

    return $batch;
}

function warehouseDefaults(): array
{
    return collect(DB::select(
        "select column_name from information_schema.columns where table_name = 'warehouses' and is_nullable = 'NO' and column_default is null and column_name not in ('id','warehouse_name')"
    ))->mapWithKeys(fn ($c) => [$c->column_name => 1])->all();
}

it('lets a company user raise a price ticket that stays pending without touching the product', function () {
    $this->actingAs($this->companyUser)
        ->post(route('tickets.store'), [
            'type' => 'price_update',
            'title' => 'Raise price',
            'items' => [[
                'product_id' => $this->product->id,
                'batch_scope' => 'all',
                'unit_sell_price' => '120',
                'cost_price' => '90',
            ]],
        ])->assertRedirect();

    $ticket = Ticket::firstOrFail();

    expect($ticket->status)->toBe(TicketStatus::Pending)
        ->and($ticket->supplier_id)->toBe($this->supplier->id)
        ->and($ticket->ticket_number)->toBe('TKT-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT))
        ->and($ticket->items->first()->old_unit_sell_price)->toBe('100.00')
        ->and($ticket->items->first()->priceChanges())->toHaveCount(2)
        ->and($ticket->histories)->toHaveCount(1)
        ->and($this->product->fresh()->unit_sell_price)->toBe('100.00');
});

it('rejects products from another company and tickets without any real change', function () {
    $foreign = Product::factory()->create(['supplier_id' => $this->otherSupplier->id]);

    $this->actingAs($this->companyUser)
        ->post(route('tickets.store'), [
            'type' => 'price_update', 'title' => 'x',
            'items' => [['product_id' => $foreign->id, 'batch_scope' => 'all', 'unit_sell_price' => '5']],
        ])->assertSessionHasErrors('items.0.product_id');

    $this->actingAs($this->companyUser)
        ->post(route('tickets.store'), [
            'type' => 'price_update', 'title' => 'x',
            'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'unit_sell_price' => '100']],
        ])->assertSessionHasErrors('items.0.unit_sell_price');

    expect(Ticket::count())->toBe(0);
});

it('applies an approved price ticket to the product, every stocked batch and the change log', function () {
    $batchA = makeBatch($this->product, 100);
    $batchB = makeBatch($this->product, 100);

    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);
    $ticket->items()->create([
        'product_id' => $this->product->id, 'apply_to_all_batches' => true,
        'old_unit_sell_price' => 100, 'new_unit_sell_price' => 120,
        'old_cost_price' => 80, 'new_cost_price' => 90,
        'old_expiry_price' => 50, 'new_expiry_price' => 40,
        'old_reorder_level' => 10, 'new_reorder_level' => 25,
    ]);

    $this->actingAs($this->admin)
        ->post(route('tickets.approve', $ticket), ['review_remarks' => 'ok'])
        ->assertRedirect(route('tickets.show', $ticket));

    $product = $this->product->fresh();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Approved)
        ->and($ticket->fresh()->reviewed_by)->toBe($this->admin->id)
        ->and($product->unit_sell_price)->toBe('120.00')
        ->and($product->cost_price)->toBe('90.00')
        ->and($product->expiry_price)->toBe('40.00')
        ->and($product->reorder_level)->toBe('25.00')
        ->and($batchA->fresh()->selling_price)->toBe('120.00')
        ->and($batchB->fresh()->selling_price)->toBe('120.00')
        ->and(DB::table('current_stock_by_batch')->where('product_id', $product->id)->pluck('selling_price')->map(fn ($p) => (float) $p)->unique()->all())->toBe([120.0])
        ->and(ProductPriceChangeLog::where('product_id', $product->id)->pluck('price_type')->sort()->values()->all())
        ->toBe(['cost_price', 'expiry_price', 'selling_price'])
        ->and($ticket->histories()->pluck('action')->all())->toBe(['approved']);
});

it('changes only the selected batches when a ticket targets specific batches', function () {
    $batchA = makeBatch($this->product, 100);
    $batchB = makeBatch($this->product, 100);

    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);
    $ticket->items()->create([
        'product_id' => $this->product->id, 'apply_to_all_batches' => false, 'batch_ids' => [$batchA->id],
        'old_unit_sell_price' => 100, 'new_unit_sell_price' => 130,
    ]);

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertRedirect();

    expect($batchA->fresh()->selling_price)->toBe('130.00')
        ->and($batchB->fresh()->selling_price)->toBe('100.00')
        ->and($this->product->fresh()->unit_sell_price)->toBe('100.00');
});

it('lists only batches with stock for the batch drop-down', function () {
    $stocked = makeBatch($this->product, 100);
    makeBatch($this->product, 100, withStock: false);

    $this->actingAs($this->companyUser)
        ->getJson(route('tickets.product-batches', $this->product))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $stocked->id);

    $foreign = Product::factory()->create(['supplier_id' => $this->otherSupplier->id]);
    $this->actingAs($this->companyUser)->getJson(route('tickets.product-batches', $foreign))->assertForbidden();
});

it('creates the product only when an admin approves a New SKU ticket', function () {
    $uom = Uom::factory()->create();
    $category = Category::create(['name' => 'Biscuits']);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'new_sku', 'title' => 'New biscuit',
        'sku' => [
            'product_code' => 'new-001', 'product_name' => 'New Biscuit', 'supplier_id' => $this->supplier->id,
            'uom_id' => $uom->id, 'category_id' => $category->id, 'valuation_method' => 'FIFO',
            'unit_sell_price' => '55', 'cost_price' => '40',
            'reorder_level' => null, 'expiry_price' => null, 'barcode' => null, 'weight' => null,
        ],
    ])->assertRedirect();

    $ticket = Ticket::firstOrFail();
    expect(Product::where('product_code', 'NEW-001')->exists())->toBeFalse();

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertRedirect();

    $product = Product::where('product_code', 'NEW-001')->firstOrFail();
    expect($product->product_name)->toBe('New Biscuit')
        ->and($product->supplier_id)->toBe($this->supplier->id)
        ->and($product->unit_sell_price)->toBe('55.00')
        ->and($product->is_active)->toBeTrue()
        ->and($ticket->items()->first()->product_id)->toBe($product->id);
});

it('re-activates an inactive SKU on approval and only offers inactive products', function () {
    $inactive = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => false]);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Back on shelf',
        'items' => [['product_id' => $this->product->id, 'new_is_active' => '1']],
    ])->assertSessionHasErrors('items.0.product_id');

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Back on shelf',
        'items' => [['product_id' => $inactive->id, 'new_is_active' => '1']],
    ])->assertRedirect();

    $this->actingAs($this->admin)->post(route('tickets.approve', Ticket::firstOrFail()))->assertRedirect();

    expect($inactive->fresh()->is_active)->toBeTrue();
});

it('rejects a ticket with remarks and changes nothing', function () {
    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);
    $ticket->items()->create(['product_id' => $this->product->id, 'new_unit_sell_price' => 500, 'old_unit_sell_price' => 100]);

    $this->actingAs($this->admin)->post(route('tickets.reject', $ticket), [])->assertSessionHasErrors('review_remarks');

    $this->actingAs($this->admin)->post(route('tickets.reject', $ticket), ['review_remarks' => 'Too high'])->assertRedirect();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Rejected)
        ->and($ticket->fresh()->review_remarks)->toBe('Too high')
        ->and($this->product->fresh()->unit_sell_price)->toBe('100.00');

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertSessionHasErrors('ticket');
    expect($this->product->fresh()->unit_sell_price)->toBe('100.00');
});

it('keeps approval for users with the approve permission and company tickets private', function () {
    $own = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id, 'title' => 'Our ticket']);
    Ticket::factory()->create(['supplier_id' => $this->otherSupplier->id, 'title' => 'Their ticket']);

    $this->actingAs($this->companyUser)->post(route('tickets.approve', $own))->assertForbidden();

    $this->actingAs($this->companyUser)->get(route('tickets.index'))
        ->assertOk()->assertSee('Our ticket')->assertDontSee('Their ticket');

    $this->actingAs($this->admin)->get(route('tickets.index'))
        ->assertOk()->assertSee('Our ticket')->assertSee('Their ticket');

    $foreign = Ticket::where('title', 'Their ticket')->first();
    $this->actingAs($this->companyUser)->get(route('tickets.show', $foreign))->assertNotFound();
    $this->actingAs($this->companyUser)->get(route('tickets.show', $own))->assertOk()->assertSee('Ticket history');
});

it('allows editing and deleting only while a ticket is pending', function () {
    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'price_update', 'title' => 'Draft',
        'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'unit_sell_price' => '110']],
    ]);
    $ticket = Ticket::firstOrFail();

    $this->actingAs($this->companyUser)->get(route('tickets.edit', $ticket))->assertOk();
    $this->actingAs($this->companyUser)->put(route('tickets.update', $ticket), [
        'type' => 'price_update', 'title' => 'Edited',
        'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'unit_sell_price' => '115']],
    ])->assertRedirect();

    expect($ticket->fresh()->title)->toBe('Edited')
        ->and($ticket->items()->first()->new_unit_sell_price)->toBe('115.00')
        ->and($ticket->histories()->pluck('action')->all())->toBe(['created', 'updated']);

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket));

    $this->actingAs($this->companyUser)->get(route('tickets.edit', $ticket))->assertForbidden();
    $this->actingAs($this->companyUser)->delete(route('tickets.destroy', $ticket))->assertForbidden();
});

it('shows the ticket form for every ticket type', function () {
    foreach (TicketType::cases() as $type) {
        $this->actingAs($this->companyUser)->get(route('tickets.create', ['type' => $type->value]))
            ->assertOk()->assertSee($type->label());
    }
});

it('gives tickets their own menu item after Settings, only to users with ticket-list', function () {
    Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);

    // Tickets do not depend on Settings access ...
    $page = $this->actingAs($this->companyUser)->get(route('tickets.index'))
        ->assertOk()->assertSee('href="'.route('tickets.index').'"', false);
    expect($page->getContent())->not->toMatch('/>\s*Settings\s*<\/a>/');

    // ... and sit right after Settings for users who have both.
    $this->companyUser->givePermissionTo('setting-view');
    $page = $this->actingAs($this->companyUser)->get(route('settings.index'))->assertOk();
    expect($page->getContent())->toMatch('/>\s*Settings\s*<\/a>.*?>\s*Tickets/s');

    expect(route('tickets.index', [], false))->toBe('/tickets');

    $noAccess = User::factory()->create();
    $noAccess->givePermissionTo('setting-view');
    expect($this->actingAs($noAccess)->get(route('settings.index'))->getContent())->not->toMatch('/>\s*Tickets/');
    $this->actingAs($noAccess)->get(route('tickets.index'))->assertForbidden();
});

it('filters the ticket list by status tab and shows per-status counts', function () {
    Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id, 'title' => 'Waiting one']);
    Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id, 'title' => 'Done one', 'status' => TicketStatus::Approved]);

    $response = $this->actingAs($this->companyUser)->get(route('tickets.index', ['status' => 'approved']));

    $response->assertOk()->assertSee('Done one')->assertDontSee('Waiting one');
    expect($response->viewData('statusCounts')->all())->toEqual(['pending' => 1, 'approved' => 1]);
});

it('renders the issue-style create form with its submit action', function () {
    $this->actingAs($this->companyUser)->get(route('tickets.create'))
        ->assertOk()->assertSee('data-product-picker', false)->assertSee('Submit for approval');
});

it('identifies tickets by uuid in every URL so ids cannot be guessed', function () {
    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);

    expect($ticket->uuid)->toBeString()->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and(route('tickets.show', $ticket))->toEndWith('/tickets/'.$ticket->uuid)
        ->and(route('tickets.approve', $ticket))->toContain($ticket->uuid);

    $this->actingAs($this->companyUser)->get(route('tickets.show', $ticket))->assertOk();
    $this->actingAs($this->companyUser)->get('/tickets/'.$ticket->id)->assertNotFound();
    $this->actingAs($this->companyUser)->get('/tickets/'.$ticket->id.'/edit')->assertNotFound();
    $this->actingAs($this->admin)->post('/tickets/'.$ticket->id.'/approve')->assertNotFound();
    $this->actingAs($this->admin)->get('/tickets/'.str_replace('-', '', $ticket->uuid))->assertNotFound();

    $this->actingAs($this->admin)->get(route('tickets.index'))->assertOk()
        ->assertSee(route('tickets.show', $ticket), false)->assertDontSee('/tickets/'.$ticket->id.'"', false);
});

it('hides other companies tickets completely, even when their uuid is known', function () {
    $foreign = Ticket::factory()->create(['supplier_id' => $this->otherSupplier->id, 'title' => 'Their secret']);
    $foreign->items()->create(['product_id' => $this->product->id, 'old_unit_sell_price' => 100, 'new_unit_sell_price' => 1]);

    $scopedApprover = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $scopedApprover->givePermissionTo(['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete', 'ticket-approve']);

    foreach ([$this->companyUser, $scopedApprover] as $user) {
        $this->actingAs($user);
        $this->get(route('tickets.show', $foreign))->assertNotFound();
        $this->get(route('tickets.edit', $foreign))->assertNotFound();
        $this->delete(route('tickets.destroy', $foreign))->assertNotFound();

        // Without ticket-approve the permission check answers first; with it, the company scope does.
        $expected = $user->can('ticket-approve') ? 404 : 403;
        $this->post(route('tickets.approve', $foreign))->assertStatus($expected);
        $this->post(route('tickets.reject', $foreign), ['review_remarks' => 'x'])->assertStatus($expected);
    }

    expect($foreign->fresh()->status)->toBe(TicketStatus::Pending)
        ->and($this->product->fresh()->unit_sell_price)->toBe('100.00');
});
