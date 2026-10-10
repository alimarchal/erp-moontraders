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
use App\Models\Vehicle;
use App\Notifications\TicketSubmitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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
        ->and($this->product->fresh()->unit_sell_price)->toBe('130.00');
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

it('re-activates an inactive SKU on approval and rejects a status equal to the current one', function () {
    $inactive = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => false]);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Back on shelf',
        'items' => [['product_id' => $this->product->id, 'new_is_active' => '1']],
    ])->assertSessionHasErrors('items.0.new_is_active');

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Back on shelf',
        'items' => [['product_id' => $inactive->id, 'new_is_active' => '0']],
    ])->assertSessionHasErrors('items.0.new_is_active');

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Back on shelf',
        'items' => [['product_id' => $inactive->id, 'new_is_active' => '1']],
    ])->assertRedirect();

    $this->actingAs($this->admin)->post(route('tickets.approve', Ticket::firstOrFail()))->assertRedirect();

    expect($inactive->fresh()->is_active)->toBeTrue();
});

it('deactivates an active SKU of the company on approval and blocks foreign SKUs', function () {
    $foreign = Product::factory()->create(['supplier_id' => $this->otherSupplier->id, 'is_active' => true]);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Foreign',
        'items' => [['product_id' => $foreign->id, 'new_is_active' => '0']],
    ])->assertSessionHasErrors('items.0.product_id');

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Retire it',
        'items' => [['product_id' => $this->product->id, 'new_is_active' => '0']],
    ])->assertRedirect();

    $ticket = Ticket::firstWhere('title', 'Retire it');
    expect($ticket->items()->first()->old_is_active)->toBeTrue()
        ->and($this->product->fresh()->is_active)->toBeTrue();

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertRedirect();

    expect($this->product->fresh()->is_active)->toBeFalse();
});

it('refuses to deactivate a SKU while stock remains in a warehouse', function () {
    makeBatch($this->product, 100);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Retire with stock',
        'items' => [['product_id' => $this->product->id, 'new_is_active' => '0']],
    ])->assertSessionHasErrors('items.0.product_id');

    expect(session('errors')->first('items.0.product_id'))->toContain('cannot be made Inactive while stock remains in the system (warehouses 10, vans 0)');

    expect(Ticket::count())->toBe(0)->and($this->product->fresh()->is_active)->toBeTrue();
});

it('refuses to deactivate a SKU whose stock is only on a van', function () {
    $vehicle = Vehicle::factory()->create();
    DB::table('van_stock_batches')->insert([
        'vehicle_id' => $vehicle->id, 'product_id' => $this->product->id, 'goods_issue_number' => 'GI-TEST-1',
        'quantity_on_hand' => 4, 'unit_cost' => 80, 'selling_price' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Retire van stock',
        'items' => [['product_id' => $this->product->id, 'new_is_active' => '0']],
    ])->assertSessionHasErrors('items.0.product_id');

    expect(session('errors')->first('items.0.product_id'))->toContain('warehouses 0, vans 4');

    expect(Ticket::count())->toBe(0);
});

it('deactivates once the stock is zero, even when emptied batches and van rows still exist', function () {
    $batch = makeBatch($this->product, 100);
    DB::table('current_stock_by_batch')->where('stock_batch_id', $batch->id)->update(['quantity_on_hand' => 0, 'total_value' => 0]);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Retire emptied',
        'items' => [['product_id' => $this->product->id, 'new_is_active' => '0']],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->actingAs($this->admin)->post(route('tickets.approve', Ticket::firstOrFail()))->assertRedirect();

    expect($this->product->fresh()->is_active)->toBeFalse();
});

it('re-checks the stock on approval, so stock received after the ticket was raised blocks the deactivation', function () {
    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Retire it',
        'items' => [['product_id' => $this->product->id, 'new_is_active' => '0']],
    ])->assertSessionHasNoErrors();
    $ticket = Ticket::firstOrFail();

    makeBatch($this->product, 100);

    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertSessionHasErrors('ticket');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Pending)
        ->and($this->product->fresh()->is_active)->toBeTrue();

    DB::table('current_stock_by_batch')->where('product_id', $this->product->id)->update(['quantity_on_hand' => 0, 'total_value' => 0]);
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertRedirect();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Approved)->and($this->product->fresh()->is_active)->toBeFalse();
});

it('does not apply the stock rule to activating an inactive SKU, and shows stock to the form', function () {
    $inactive = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => false]);
    makeBatch($inactive, 100);
    makeBatch($this->product, 100);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Wake with stock',
        'items' => [['product_id' => $inactive->id, 'new_is_active' => '1']],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $products = collect($this->actingAs($this->companyUser)->get(route('tickets.create', ['type' => 'reactivate_sku']))->assertOk()->viewData('products'));
    expect($products->firstWhere('id', $this->product->id)['stock'])->toEqual(10);
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

it('filters the ticket list like Goods Issues: status tab, search, type, date, and shows KPI counts', function () {
    $mine = ['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id];
    Ticket::factory()->create($mine + ['title' => 'Waiting one']);
    Ticket::factory()->create($mine + ['title' => 'Done one', 'status' => TicketStatus::Approved]);
    Ticket::factory()->create($mine + ['title' => 'Brand new biscuit', 'type' => TicketType::NewSku]);
    Ticket::factory()->create($mine + ['title' => 'Ancient request', 'created_at' => now()->subMonths(3)]);

    $this->actingAs($this->companyUser);

    $approved = $this->get(route('tickets.index', ['filter' => ['status' => 'approved']]))->assertOk();
    $approved->assertSee('Done one')->assertDontSee('Waiting one');
    expect($approved->viewData('stats'))->toBe(['total' => 4, 'pending' => 3, 'approved' => 1, 'rejected' => 0]);

    $this->get(route('tickets.index', ['filter' => ['search' => 'biscuit']]))->assertSee('Brand new biscuit')->assertDontSee('Waiting one');
    $this->get(route('tickets.index', ['filter' => ['type' => 'new_sku']]))->assertSee('Brand new biscuit')->assertDontSee('Done one');
    $this->get(route('tickets.index', ['filter' => ['date_from' => now()->subMonth()->toDateString()]]))
        ->assertSee('Waiting one')->assertDontSee('Ancient request');
    $this->get(route('tickets.index', ['filter' => ['date_to' => now()->subMonth()->toDateString()]]))
        ->assertSee('Ancient request')->assertDontSee('Waiting one');
    $this->get(route('tickets.index', ['sort' => 'title']))->assertOk()->assertSeeInOrder(['Ancient request', 'Brand new biscuit']);
    $this->get(route('tickets.index', ['per_page' => 15]))->assertOk()->assertSee('Showing');
});

it('only lets an admin filter by other companies and requesters', function () {
    Ticket::factory()->create(['supplier_id' => $this->otherSupplier->id, 'title' => 'Other company ticket']);
    Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id, 'title' => 'Own company ticket']);

    // A company user cannot widen the scope with filter[supplier_id].
    $this->actingAs($this->companyUser)
        ->get(route('tickets.index', ['filter' => ['supplier_id' => $this->otherSupplier->id]]))
        ->assertOk()->assertDontSee('Other company ticket');

    $this->actingAs($this->admin)
        ->get(route('tickets.index', ['filter' => ['supplier_id' => $this->otherSupplier->id]]))
        ->assertSee('Other company ticket')->assertDontSee('Own company ticket');
    $this->actingAs($this->admin)
        ->get(route('tickets.index', ['filter' => ['created_by' => $this->companyUser->id]]))
        ->assertSee('Own company ticket')->assertDontSee('Other company ticket');
});

it('ships a ready company-user role from the migration that can raise tickets but never approve them', function () {
    $role = Role::findByName('company-user', 'web');

    expect($role->hasPermissionTo('ticket-list'))->toBeTrue()
        ->and($role->hasPermissionTo('ticket-create'))->toBeTrue()
        ->and($role->hasPermissionTo('ticket-edit'))->toBeTrue()
        ->and($role->hasPermissionTo('ticket-delete'))->toBeTrue()
        ->and($role->permissions->pluck('name')->contains('ticket-approve'))->toBeFalse();

    $user = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $user->assignRole('company-user');

    $this->actingAs($user)->get(route('tickets.index'))->assertOk();
    $this->actingAs($user)->post(route('tickets.store'), [
        'type' => 'price_update', 'title' => 'By role only',
        'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'unit_sell_price' => '101']],
    ])->assertRedirect();

    $ticket = Ticket::firstWhere('title', 'By role only');
    $this->actingAs($user)->post(route('tickets.approve', $ticket))->assertForbidden();
});

it('renders the create form with its select2 product picker and submit action', function () {
    $this->actingAs($this->companyUser)->get(route('tickets.create'))
        ->assertOk()->assertSee('tk-product-select', false)->assertSee('Submit for approval');
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

it('offers inactive SKUs of the company in the re-activate form, even though the products list hides them', function () {
    $inactive = Product::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => false, 'product_name' => 'Sleeping SKU']);
    $foreignInactive = Product::factory()->create(['supplier_id' => $this->otherSupplier->id, 'is_active' => false, 'product_name' => 'Foreign sleeping SKU']);

    $user = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $user->assignRole('company-user');

    $response = $this->actingAs($user)->get(route('tickets.create', ['type' => 'reactivate_sku']))->assertOk();
    $offered = collect($response->viewData('products'))->pluck('id')->all();

    expect($offered)->toContain($inactive->id, $this->product->id)->not->toContain($foreignInactive->id);

    $this->actingAs($user)->post(route('tickets.store'), [
        'type' => 'reactivate_sku', 'title' => 'Wake it up',
        'items' => [['product_id' => $inactive->id, 'new_is_active' => '1']],
    ])->assertRedirect();

    $this->actingAs($this->admin)->post(route('tickets.approve', Ticket::firstWhere('title', 'Wake it up')))->assertRedirect();
    expect($inactive->fresh()->is_active)->toBeTrue();
});

it('supports 500 and All rows per page on the ticket list', function () {
    Ticket::factory()->count(30)->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);

    $this->actingAs($this->companyUser);

    expect($this->get(route('tickets.index', ['per_page' => 15]))->viewData('tickets')->count())->toBe(15)
        ->and($this->get(route('tickets.index', ['per_page' => 'all']))->viewData('tickets')->count())->toBe(30)
        ->and($this->get(route('tickets.index', ['per_page' => 500]))->viewData('tickets')->count())->toBe(30)
        ->and($this->get(route('tickets.index', ['per_page' => 'bogus']))->viewData('perPage'))->toBe('25');
    $this->get(route('tickets.index', ['per_page' => 'all']))->assertSee('value="all" selected', false);
});

it('links an approved price ticket to the price change log only for users who may open that report', function () {
    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);
    $ticket->items()->create(['product_id' => $this->product->id, 'old_unit_sell_price' => 100, 'new_unit_sell_price' => 120]);
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket));

    $link = route('reports.product-price-change-log.index', ['product_id' => $this->product->id]);

    $this->actingAs($this->companyUser)->get(route('tickets.show', $ticket))->assertOk()->assertDontSee($link, false);

    Permission::findOrCreate('report-audit-product-price-change-log');
    $this->companyUser->givePermissionTo('report-audit-product-price-change-log');
    $this->actingAs($this->companyUser->fresh())->get(route('tickets.show', $ticket))->assertSee('View in price change log')->assertSee($link, false);
});

it('emails every approver who may see the ticket with the details needed to decide, and nobody else', function () {
    Notification::fake();

    $scopedApprover = User::factory()->create(['supplier_id' => $this->otherSupplier->id]);
    $scopedApprover->givePermissionTo(['ticket-list', 'ticket-approve']);
    $inactiveAdmin = User::factory()->create(['is_active' => 'No']);
    $inactiveAdmin->assignRole('admin');
    $inactiveAdmin->givePermissionTo('ticket-approve');

    $batch = makeBatch($this->product, 100);

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'price_update', 'title' => 'Revised trade prices',
        'description' => 'Head office circular',
        'items' => [['product_id' => $this->product->id, 'batch_scope' => 'selected', 'batch_ids' => [$batch->id], 'unit_sell_price' => '120', 'reorder_level' => '30']],
    ])->assertRedirect();

    $ticket = Ticket::firstOrFail();

    Notification::assertSentTo($this->admin, TicketSubmitted::class);
    Notification::assertNotSentTo([$this->companyUser, $scopedApprover, $inactiveAdmin], TicketSubmitted::class);

    $mail = (new TicketSubmitted($ticket))->toMail($this->admin);
    $text = $mail->render()->toHtml();

    expect($mail->subject)->toContain($ticket->ticket_number)->toContain('Revised trade prices')
        ->and($text)->toContain('Head office circular')
        ->toContain($this->product->product_name)
        ->toContain('Selling Price 100.00')
        ->toContain('120.00')
        ->toContain('Reorder Level')
        ->toContain($batch->batch_code)
        ->toContain(route('tickets.show', $ticket));
});

it('still saves the ticket when mailing the approvers fails', function () {
    Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp down'));

    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'price_update', 'title' => 'Mail broken',
        'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'unit_sell_price' => '120']],
    ])->assertRedirect();

    expect(Ticket::firstWhere('title', 'Mail broken'))->not->toBeNull();
});

it('confirms success in a modal with an OK button instead of a banner', function () {
    $this->actingAs($this->companyUser)->post(route('tickets.store'), [
        'type' => 'price_update', 'title' => 'Modal check',
        'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'unit_sell_price' => '120']],
    ])->assertRedirect();

    $ticket = Ticket::firstWhere('title', 'Modal check');

    $this->actingAs($this->companyUser)->withSession(['success' => 'Ticket submitted.'])->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('data-flash-modal', false)->assertSee('Ticket submitted.')->assertSee('>OK<', false);

    $this->actingAs($this->companyUser)->get(route('tickets.show', $ticket))->assertDontSee('data-flash-modal', false);
});

it('creates only one ticket when the same form is submitted twice (double click)', function () {
    $payload = [
        'type' => 'price_update', 'title' => 'Double click', '_ticket_token' => 'token-abc',
        'items' => [['product_id' => $this->product->id, 'batch_scope' => 'all', 'unit_sell_price' => '120']],
    ];

    $this->actingAs($this->companyUser)->post(route('tickets.store'), $payload)->assertRedirect();
    $this->actingAs($this->companyUser)->post(route('tickets.store'), $payload)
        ->assertRedirect(route('tickets.index'))->assertSessionHas('warning');

    expect(Ticket::where('title', 'Double click')->count())->toBe(1);

    // A fresh form (new token) is a new ticket again.
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['_ticket_token' => 'token-def'] + $payload)->assertRedirect();
    expect(Ticket::where('title', 'Double click')->count())->toBe(2);
});

it('disables the buttons while a ticket form is being sent and plays the status sound', function () {
    $page = $this->actingAs($this->companyUser)->get(route('tickets.create'))->assertOk();
    $page->assertSee(':disabled="submitting"', false)->assertSee('name="_ticket_token"', false);

    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);
    $this->actingAs($this->companyUser)->withSession(['success' => 'Done.'])->get(route('tickets.show', $ticket))
        ->assertSee('data-status-sound="success"', false)->assertSee('AudioContext', false);
});

it('serves the short Urdu help page (RTL, printable) to ticket users only and links it from the ticket pages', function () {
    $this->actingAs($this->companyUser)->get(route('tickets.help'))
        ->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ur"', false)->assertSee('window.print()', false)
        ->assertSee('ٹکٹ کیسے بنائیں', false)->assertSee('icons-images/ticket-help/3-form.jpg', false);

    expect(route('tickets.help', [], false))->toBe('/tickets/help');

    foreach (['1-menu', '2-type', '3-form', '4-success', '5-adjustment', '6-review', '7-approve', '8-password'] as $image) {
        expect(file_exists(public_path("icons-images/ticket-help/$image.jpg")))->toBeTrue();
    }

    $this->actingAs($this->companyUser)->get(route('tickets.index'))
        ->assertSee('href="'.route('tickets.help').'" target="_blank"', false);
    $this->actingAs($this->companyUser)->get(route('tickets.create'))->assertSee(route('tickets.help'), false);

    $noAccess = User::factory()->create();
    $this->actingAs($noAccess)->get(route('tickets.help'))->assertForbidden();
});
