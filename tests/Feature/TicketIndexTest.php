<?php

use App\Enums\TicketStatus;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
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
