<?php

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete', 'ticket-approve'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->otherSupplier = Supplier::factory()->create(['disabled' => false]);

    $this->companyUser = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->companyUser->givePermissionTo(['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete']);

    Role::findOrCreate('admin');
    $this->admin = User::factory()->create(['name' => 'Approving Admin']);
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo(['ticket-list', 'ticket-create', 'ticket-edit', 'ticket-delete', 'ticket-approve']);
});

it('filters the ticket list by who approved or rejected it', function () {
    $otherAdmin = User::factory()->create(['name' => 'Second Admin']);
    Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'title' => 'Decided by first', 'status' => TicketStatus::Approved, 'reviewed_by' => $this->admin->id, 'reviewed_at' => now()]);
    Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'title' => 'Decided by second', 'status' => TicketStatus::Rejected, 'reviewed_by' => $otherAdmin->id, 'reviewed_at' => now()]);

    $response = $this->actingAs($this->admin)
        ->get(route('tickets.index', ['filter' => ['status' => 'all', 'reviewed_by' => $this->admin->id]]))
        ->assertOk()->assertSee('Decided by first')->assertDontSee('Decided by second')
        ->assertSee('Decided by:', false);

    expect($response->viewData('reviewers')->pluck('name')->all())->toBe(['Approving Admin', 'Second Admin']);
});

it('keeps the reviewer filter and its dropdown inside the company scope', function () {
    $foreignReviewer = User::factory()->create(['name' => 'Foreign Reviewer']);
    Ticket::factory()->create(['supplier_id' => $this->otherSupplier->id, 'title' => 'Other company decided', 'status' => TicketStatus::Approved, 'reviewed_by' => $foreignReviewer->id]);
    Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id, 'title' => 'Own decided', 'status' => TicketStatus::Approved, 'reviewed_by' => $this->admin->id]);

    $response = $this->actingAs($this->companyUser)
        ->get(route('tickets.index', ['filter' => ['status' => 'all', 'reviewed_by' => $foreignReviewer->id]]))
        ->assertOk()->assertDontSee('Other company decided');

    expect($response->viewData('reviewers')->pluck('name')->all())->toBe(['Approving Admin']);
});

it('searches ticket number, title and description regardless of letter case', function () {
    $mine = ['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id];
    Ticket::factory()->create($mine + ['ticket_number' => 'TKT-000101', 'title' => 'Biscuit price', 'description' => 'Plain']);
    Ticket::factory()->create($mine + ['ticket_number' => 'TKT-000102', 'title' => 'Juice change', 'description' => 'Raise the MANGO rate']);
    Ticket::factory()->create($mine + ['ticket_number' => 'TKT-000777', 'title' => 'Numbered one', 'description' => 'Plain']);

    $this->actingAs($this->companyUser);

    $this->get(route('tickets.index', ['filter' => ['search' => 'BISCUIT']]))->assertSee('Biscuit price')->assertDontSee('Juice change');
    $this->get(route('tickets.index', ['filter' => ['search' => 'mango']]))->assertSee('Juice change')->assertDontSee('Biscuit price');
    $this->get(route('tickets.index', ['filter' => ['search' => 'tkt-000777']]))
        ->assertSee('Numbered one')->assertDontSee('Biscuit price');
});

it('lays the list out like Goods Issues with the shared header, row numbers and a totals row', function () {
    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);
    $product = Product::factory()->create(['supplier_id' => $this->supplier->id]);
    $ticket->items()->create(['product_id' => $product->id, 'old_unit_sell_price' => 100, 'new_unit_sell_price' => 110]);
    $ticket->items()->create(['product_id' => $product->id, 'old_unit_sell_price' => 100, 'new_unit_sell_price' => 120]);

    $response = $this->actingAs($this->companyUser)->get(route('tickets.index'))->assertOk()
        ->assertSee('id="toggle"', false)
        ->assertSee('href="'.route('tickets.create').'" data-create', false)
        ->assertSee('Total &middot; 1 ticket', false)
        ->assertSee('class="tk-no"', false);

    expect($response->viewData('itemsTotal'))->toBe(2);
});

it('opens the Urdu help in a window on the list, like Goods Issues', function () {
    $this->actingAs($this->admin)->get(route('tickets.index'))
        ->assertOk()
        ->assertSee('Help: Tickets')
        ->assertSee("\$dispatch('rg-help'", false)
        ->assertSee('data-topic="adjustment"', false);
});

/**
 * A stock adjustment ticket whose payload keeps ids the way the form posts them (strings) or as numbers.
 */
function adjustmentTicket(object $t, string $title, Product $product, StockBatch $batch, Warehouse $warehouse, string $type, bool $idsAsStrings = true): Ticket
{
    $ticket = Ticket::factory()->create(['supplier_id' => $t->supplier->id, 'created_by' => $t->companyUser->id, 'type' => TicketType::StockAdjustment, 'title' => $title]);
    $id = fn (int $value) => $idsAsStrings ? (string) $value : $value;
    $ticket->items()->create(['payload' => [
        'warehouse_id' => $id($warehouse->id), 'adjustment_type' => $type, 'supplier_id' => $t->supplier->id,
        'items' => [['product_id' => $id($product->id), 'stock_batch_id' => $id($batch->id), 'system_quantity' => 73, 'actual_quantity' => '119', 'adjustment_quantity' => 46, 'adjustment_value' => 20522.9, 'unit_cost' => 446.15]],
    ]]);

    return $ticket;
}

it('filters tickets by product, batch, warehouse and adjustment type inside stock adjustment lines', function () {
    $warehouse = Warehouse::factory()->create(['disabled' => false]);
    $otherWarehouse = Warehouse::factory()->create(['disabled' => false]);
    $grapes = Product::factory()->create(['supplier_id' => $this->supplier->id, 'product_code' => 'FV-GRAPE', 'product_name' => 'Red Grapes Gold']);
    $orange = Product::factory()->create(['supplier_id' => $this->supplier->id, 'product_code' => 'FV-ORANGE', 'product_name' => 'Orange Gold']);
    $grapesBatch = StockBatch::factory()->create(['product_id' => $grapes->id, 'batch_code' => 'BATCH-2026-3409']);
    $orangeBatch = StockBatch::factory()->create(['product_id' => $orange->id, 'batch_code' => 'BATCH-2026-3505']);

    adjustmentTicket($this, 'Grapes count', $grapes, $grapesBatch, $warehouse, 'count_variance');
    adjustmentTicket($this, 'Orange damage', $orange, $orangeBatch, $otherWarehouse, 'damage', idsAsStrings: false);
    $this->actingAs($this->admin);
    $list = fn (array $filter) => $this->get(route('tickets.index', ['filter' => $filter + ['status' => 'all']]))->assertOk();

    $list(['product_id' => $grapes->id])->assertSee('Grapes count')->assertDontSee('Orange damage');
    $list(['product_id' => $orange->id])->assertSee('Orange damage')->assertDontSee('Grapes count');
    $list(['batch' => '3409'])->assertSee('Grapes count')->assertDontSee('Orange damage');
    $list(['warehouse_id' => $otherWarehouse->id])->assertSee('Orange damage')->assertDontSee('Grapes count');
    $list(['adjustment_type' => 'count_variance'])->assertSee('Grapes count')->assertDontSee('Orange damage');
    $list(['search' => 'fv-orange'])->assertSee('Orange damage')->assertDontSee('Grapes count');
});

it('filters new SKU tickets by category and product code', function () {
    $category = Category::create(['name' => 'Juices', 'slug' => 'juices', 'is_active' => true]);
    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id, 'type' => TicketType::NewSku, 'title' => 'New juice SKU']);
    $ticket->items()->create(['payload' => ['product_code' => 'NEW-JUICE-1', 'product_name' => 'Mango Nectar', 'category_id' => (string) $category->id]]);
    Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id, 'title' => 'Unrelated ticket']);

    $this->actingAs($this->admin)->get(route('tickets.index', ['filter' => ['status' => 'all', 'category_id' => $category->id]]))
        ->assertSee('New juice SKU')->assertDontSee('Unrelated ticket');
    $this->get(route('tickets.index', ['filter' => ['status' => 'all', 'search' => 'mango nectar']]))
        ->assertSee('New juice SKU')->assertDontSee('Unrelated ticket');
});

it('offers a company user only their own products in the product filter', function () {
    $own = Product::factory()->create(['supplier_id' => $this->supplier->id, 'product_code' => 'OWN-SKU']);
    $foreign = Product::factory()->create(['supplier_id' => $this->otherSupplier->id, 'product_code' => 'FOREIGN-SKU']);

    $response = $this->actingAs($this->companyUser)->get(route('tickets.index'))->assertOk();

    expect($response->viewData('products')->pluck('id')->all())->toContain($own->id)->not->toContain($foreign->id);
});

it('shows each stock adjustment line with its SKU, batch, counted figures, difference and value', function () {
    $warehouse = Warehouse::factory()->create(['disabled' => false, 'warehouse_name' => 'Main Store']);
    $grapes = Product::factory()->create(['supplier_id' => $this->supplier->id, 'product_name' => 'Red Grapes Gold']);
    $batch = StockBatch::factory()->create(['product_id' => $grapes->id, 'batch_code' => 'BATCH-2026-3409']);
    adjustmentTicket($this, 'Grapes count', $grapes, $batch, $warehouse, 'count_variance');

    $response = $this->actingAs($this->admin)->get(route('tickets.index', ['filter' => ['status' => 'all']]))->assertOk();

    $response->assertSee('Red Grapes Gold')->assertSee('BATCH-2026-3409')->assertSee('Main Store')
        ->assertSee('73 → 119', false)->assertSee('+46')->assertSee('20,522.90');
    expect($response->viewData('itemsTotal'))->toBe(1);
});

it('opens a quick look with every line of a ticket, like Goods Issues', function () {
    $warehouse = Warehouse::factory()->create(['disabled' => false]);
    $grapes = Product::factory()->create(['supplier_id' => $this->supplier->id, 'product_name' => 'Red Grapes Gold']);
    $ticket = Ticket::factory()->create(['supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id, 'type' => TicketType::StockAdjustment, 'title' => 'Big count']);
    $ticket->items()->create(['payload' => [
        'warehouse_id' => (string) $warehouse->id, 'adjustment_type' => 'count_variance',
        'items' => collect(range(1, 5))->map(fn (int $n) => [
            'product_id' => (string) $grapes->id, 'stock_batch_id' => (string) StockBatch::factory()->create(['product_id' => $grapes->id, 'batch_code' => "QL-BATCH-{$n}"])->id,
            'system_quantity' => 10, 'actual_quantity' => '11', 'adjustment_quantity' => 1, 'adjustment_value' => 100,
        ])->all(),
    ]]);

    $html = $this->actingAs($this->admin)->get(route('tickets.index', ['filter' => ['status' => 'all']]))->assertOk()
        ->assertSee("\$dispatch('tk-peek'", false)
        ->assertSee('id="tk-peek-'.$ticket->id.'"', false)
        ->assertSee('class="tk-type-pill"', false)
        ->getContent();

    // The row shows three lines; the quick look template holds all five.
    $template = str($html)->after('id="tk-peek-'.$ticket->id.'"')->before('</template>');
    expect(substr_count($template, 'QL-BATCH-'))->toBe(5)
        ->and(substr_count(str($html)->before('id="tk-peek-'.$ticket->id.'"'), 'QL-BATCH-'))->toBe(3);
});
