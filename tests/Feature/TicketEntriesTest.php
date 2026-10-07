<?php

use App\Enums\TicketStatus;
use App\Models\ClaimRegister;
use App\Models\Customer;
use App\Models\LedgerRegister;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketSubmitted;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Supplier ledger entry, claim register entry and new customer tickets: approving must create the very
 * record the real screen creates (and never post it).
 */

beforeEach(function () {
    $this->withoutVite();

    $this->supplier = Supplier::factory()->create(['disabled' => false]);
    $this->otherSupplier = Supplier::factory()->create(['disabled' => false]);

    $this->admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->companyUser = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->companyUser->assignRole('company-user');
});

/** @return array<string, mixed> */
function ledgerData(object $t, array $override = []): array
{
    return $override + [
        'supplier_id' => $t->supplier->id, 'transaction_date' => now()->toDateString(), 'document_type' => 'DR',
        'document_number' => 'INV-4500123', 'sap_code' => 'SAP-9', 'invoice_amount' => '12500.50', 'expenses_amount' => '300',
        'remarks' => 'October invoice',
    ];
}

/** @return array<string, mixed> */
function claimData(object $t, array $override = []): array
{
    return $override + [
        'supplier_id' => $t->supplier->id, 'transaction_date' => now()->toDateString(), 'transaction_type' => 'claim',
        'amount' => '8000', 'reference_number' => 'CLM-77', 'claim_month' => 'October 2026', 'description' => 'Price difference',
    ];
}

/** @return array<string, mixed> */
function customerData(array $override = []): array
{
    return $override + [
        'customer_code' => 'cust-900', 'customer_name' => 'Al-Madina General Store', 'channel_type' => 'General Store',
        'customer_category' => 'B', 'country' => 'Pakistan', 'phone' => '0300-1234567', 'email' => 'Madina@Example.com', 'city' => 'Lahore', 'credit_limit' => '50000',
    ];
}

it('creates a pending ticket for each entry type and touches no live data until approval', function () {
    $this->actingAs($this->companyUser);

    $this->post(route('tickets.store'), ['type' => 'ledger_entry', 'title' => 'Ledger line', 'data' => ledgerData($this)])->assertRedirect();
    $this->post(route('tickets.store'), ['type' => 'claim_entry', 'title' => 'Claim line', 'data' => claimData($this)])->assertRedirect();
    $this->post(route('tickets.store'), ['type' => 'new_customer', 'title' => 'New customer', 'data' => customerData()])->assertRedirect();

    expect(Ticket::count())->toBe(3)
        ->and(Ticket::where('status', TicketStatus::Pending)->count())->toBe(3)
        ->and(Ticket::where('supplier_id', $this->supplier->id)->count())->toBe(3)
        ->and(LedgerRegister::count() + ClaimRegister::count() + Customer::count())->toBe(0);

    $customerTicket = Ticket::firstWhere('title', 'New customer');
    expect($customerTicket->items->first()->payload['customer_code'])->toBe('CUST-900')
        ->and($customerTicket->items->first()->payload['email'])->toBe('madina@example.com');

    foreach (Ticket::all() as $ticket) {
        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Approve');
    }
});

it('validates with the rules of the real screens and keeps company users on their own supplier', function () {
    $this->actingAs($this->companyUser);

    $this->post(route('tickets.store'), ['type' => 'ledger_entry', 'title' => 'x', 'data' => ledgerData($this, ['supplier_id' => $this->otherSupplier->id])])->assertSessionHasErrors('data.supplier_id');
    $this->post(route('tickets.store'), ['type' => 'ledger_entry', 'title' => 'x', 'data' => ledgerData($this, ['document_type' => 'NOPE'])])->assertSessionHasErrors('data.document_type');
    $this->post(route('tickets.store'), ['type' => 'claim_entry', 'title' => 'x', 'data' => claimData($this, ['transaction_type' => 'gift'])])->assertSessionHasErrors('data.transaction_type');
    $this->post(route('tickets.store'), ['type' => 'claim_entry', 'title' => 'x', 'data' => claimData($this, ['amount' => ''])])->assertSessionHasErrors('data.amount');
    $this->post(route('tickets.store'), ['type' => 'new_customer', 'title' => 'x', 'data' => customerData(['channel_type' => 'Moon base'])])->assertSessionHasErrors('data.channel_type');
    $this->post(route('tickets.store'), ['type' => 'new_customer', 'title' => 'x', 'data' => customerData(['customer_name' => ''])])->assertSessionHasErrors('data.customer_name');

    Customer::factory()->create(['customer_code' => 'CUST-900']);
    $this->post(route('tickets.store'), ['type' => 'new_customer', 'title' => 'x', 'data' => customerData()])->assertSessionHasErrors('data.customer_code');

    expect(Ticket::count())->toBe(0);
});

it('creates the same ledger register entry as the Ledger Register screen and does not post it', function () {
    $this->actingAs($this->admin);
    $this->post(route('reports.ledger-register.store'), ledgerData($this, ['document_number' => 'VIA-SCREEN']))->assertRedirect();
    $screen = LedgerRegister::firstWhere('document_number', 'VIA-SCREEN');

    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'ledger_entry', 'title' => 'Ledger line', 'data' => ledgerData($this, ['document_number' => 'VIA-TICKET'])]);
    $ticket = Ticket::firstOrFail();
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertRedirect(route('tickets.show', $ticket));

    $viaTicket = LedgerRegister::firstWhere('document_number', 'VIA-TICKET');
    $columns = ['supplier_id', 'document_type', 'sap_code', 'online_amount', 'opening_balance', 'invoice_amount', 'expenses_amount', 'za_point_five_percent_amount', 'claim_adjust_amount', 'remarks'];

    expect($viaTicket)->not->toBeNull()
        ->and($viaTicket->only($columns))->toEqual($screen->only($columns))
        ->and($viaTicket->transaction_date->toDateString())->toBe($screen->transaction_date->toDateString())
        ->and($viaTicket->isPosted())->toBeFalse()
        ->and($viaTicket->journal_entry_id)->toBeNull()
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Approved)
        ->and($ticket->histories()->reorder('id', 'desc')->first()->remarks)->toContain('not posted');

    // Running balances were recalculated for the supplier, as the screen does.
    expect((float) $viaTicket->fresh()->balance)->toBe((float) LedgerRegister::where('supplier_id', $this->supplier->id)->orderByDesc('id')->value('balance'));
});

it('creates the same claim register entry as the Claim Register screen, with the default accounts, and does not post it', function () {
    $this->actingAs($this->admin);
    $this->post(route('reports.claim-register.store'), claimData($this, ['reference_number' => 'VIA-SCREEN']))->assertRedirect();
    $screen = ClaimRegister::firstWhere('reference_number', 'VIA-SCREEN');

    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'claim_entry', 'title' => 'Claim line', 'data' => claimData($this, ['reference_number' => 'VIA-TICKET'])]);
    $ticket = Ticket::firstOrFail();
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertRedirect();

    $viaTicket = ClaimRegister::firstWhere('reference_number', 'VIA-TICKET');
    $columns = ['supplier_id', 'transaction_type', 'debit', 'credit', 'claim_month', 'description', 'debit_account_id', 'credit_account_id', 'bank_account_id', 'payment_method'];

    expect($viaTicket)->not->toBeNull()
        ->and($viaTicket->only($columns))->toEqual($screen->only($columns))
        ->and((float) $viaTicket->debit)->toBe(8000.0)
        ->and($viaTicket->isPosted())->toBeFalse()
        ->and($viaTicket->journal_entry_id)->toBeNull();

    // A recovery goes to the credit side.
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'claim_entry', 'title' => 'Recovery', 'data' => claimData($this, ['transaction_type' => 'recovery', 'reference_number' => 'REC-1'])]);
    $this->actingAs($this->admin)->post(route('tickets.approve', Ticket::firstWhere('title', 'Recovery')));
    expect((float) ClaimRegister::firstWhere('reference_number', 'REC-1')->credit)->toBe(8000.0);
});

it('creates the customer on approval exactly like the Customers screen, and refuses a duplicate code', function () {
    $this->actingAs($this->admin);
    $this->post(route('customers.store'), customerData(['customer_code' => 'VIA-SCREEN', 'email' => 'screen@example.com', 'is_active' => 1]))->assertRedirect(route('customers.index'));
    $screen = Customer::firstWhere('customer_code', 'VIA-SCREEN');

    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'new_customer', 'title' => 'New customer', 'data' => customerData(['customer_code' => 'via-ticket', 'email' => 'ticket@example.com'])]);
    $ticket = Ticket::firstOrFail();
    $this->actingAs($this->admin)->post(route('tickets.approve', $ticket))->assertRedirect();

    $viaTicket = Customer::firstWhere('customer_code', 'VIA-TICKET');
    $columns = ['customer_name', 'channel_type', 'customer_category', 'phone', 'city', 'credit_limit', 'is_active'];

    expect($viaTicket)->not->toBeNull()
        ->and($viaTicket->only($columns))->toEqual($screen->only($columns))
        ->and($viaTicket->is_active)->toBeTrue();

    // Same code raised again after the first was approved: the ticket cannot be approved and stays pending.
    Customer::factory()->create(['customer_code' => 'TAKEN-1']);
    $second = Ticket::factory()->create(['type' => 'new_customer', 'supplier_id' => $this->supplier->id, 'created_by' => $this->companyUser->id]);
    $second->items()->create(['payload' => customerData(['customer_code' => 'TAKEN-1', 'email' => null])]);
    $before = Customer::count();
    $this->actingAs($this->admin)->post(route('tickets.approve', $second))->assertSessionHasErrors('ticket');

    expect($second->fresh()->status)->toBe(TicketStatus::Pending)->and(Customer::count())->toBe($before);
});

it('needs the matching create permission to approve an entry ticket', function () {
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'new_customer', 'title' => 'New customer', 'data' => customerData()]);
    $ticket = Ticket::firstOrFail();

    $approver = User::factory()->create();
    $approver->assignRole(Role::findOrCreate('admin', 'web'));
    $approver->givePermissionTo(Permission::findOrCreate('ticket-approve', 'web'));

    $this->actingAs($approver)->post(route('tickets.approve', $ticket))->assertSessionHasErrors('ticket');
    expect(Customer::count())->toBe(0)->and($ticket->fresh()->status)->toBe(TicketStatus::Pending);

    $approver->givePermissionTo(Permission::findOrCreate('customer-create', 'web'));
    $this->actingAs($approver->fresh())->post(route('tickets.approve', $ticket))->assertRedirect();
    expect(Customer::count())->toBe(1);
});

it('renders the forms and the e-mail summary for every entry type', function () {
    foreach (['ledger_entry' => 'Supplier Ledger Entry', 'claim_entry' => 'Claim Register Entry', 'new_customer' => 'New Customer'] as $type => $label) {
        $this->actingAs($this->companyUser)->get(route('tickets.create', ['type' => $type]))->assertOk()->assertSee($label)->assertSee('name="data[', false);
    }

    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'claim_entry', 'title' => 'Claim line', 'data' => claimData($this)]);
    $ticket = Ticket::firstOrFail();
    $mail = (new TicketSubmitted($ticket))->toMail($this->admin)->render()->toHtml();

    expect($mail)->toContain('Claim Register Entry')->toContain('CLM-77')->toContain('8,000.00')->toContain('not posted');
});

it('tells an approver who lacks the matching permission instead of showing a bare 403', function () {
    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'new_customer', 'title' => 'New customer', 'data' => customerData()]);
    $ticket = Ticket::firstOrFail();

    $approver = User::factory()->create();
    $approver->assignRole(Role::findOrCreate('admin', 'web'));
    $approver->givePermissionTo(Permission::findOrCreate('ticket-approve', 'web'));
    $approver->givePermissionTo(Permission::findOrCreate('ticket-list', 'web'));

    $this->actingAs($approver)->get(route('tickets.show', $ticket))->assertOk()
        ->assertSee('also needs the')->assertSee('customer-create')->assertDontSee('name="review_remarks"', false);

    $this->actingAs($approver)->from(route('tickets.show', $ticket))->post(route('tickets.approve', $ticket))
        ->assertRedirect(route('tickets.show', $ticket))->assertSessionHasErrors('ticket');
});

it('creates a customer from a ticket with every optional field left blank', function () {
    $blank = array_fill_keys(['business_name', 'phone', 'email', 'ntn', 'owner_cnic', 'address', 'sub_locality', 'city', 'state', 'country', 'credit_limit', 'payment_terms', 'notes'], '');

    $this->actingAs($this->companyUser)->post(route('tickets.store'), ['type' => 'new_customer', 'title' => 'Minimal customer', 'data' => [
        'customer_code' => 'min-1', 'customer_name' => 'Minimal Store', 'channel_type' => 'General Store', 'customer_category' => 'C',
    ] + $blank])->assertSessionHasNoErrors()->assertRedirect();

    $this->actingAs($this->admin)->post(route('tickets.approve', Ticket::firstOrFail()))->assertSessionHasNoErrors();

    $customer = Customer::firstWhere('customer_code', 'MIN-1');
    expect($customer)->not->toBeNull()->and($customer->country)->toBe('Pakistan')->and($customer->payment_terms)->not->toBeNull();
});
