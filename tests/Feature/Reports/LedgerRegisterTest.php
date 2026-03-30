<?php

use App\Enums\DocumentType;
use App\Models\LedgerRegister;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-audit-ledger-register']);
    Permission::create(['name' => 'report-audit-ledger-register-manage']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-audit-ledger-register');
    $this->user->givePermissionTo('report-audit-ledger-register-manage');

    $this->supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestlé Pakistan',
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);
});

test('ledger register page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.ledger-register.index'))
        ->assertSuccessful();
});

test('ledger register loads with default nestle supplier', function () {
    $this->actingAs($this->user)
        ->get(route('reports.ledger-register.index'))
        ->assertSuccessful()
        ->assertSee('Nestlé Pakistan');
});

test('ledger register requires authentication', function () {
    $this->get(route('reports.ledger-register.index'))
        ->assertRedirect(route('login'));
});

test('ledger register requires permission', function () {
    $userWithoutPermission = User::factory()->create();

    $this->actingAs($userWithoutPermission)
        ->get(route('reports.ledger-register.index'))
        ->assertForbidden();
});

test('ledger register shows entries for selected supplier', function () {
    LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => now()->format('Y-m-d'),
        'online_amount' => 5000000,
    ]);
    LedgerRegister::recalculateBalances($this->supplier->id);

    $this->actingAs($this->user)
        ->get(route('reports.ledger-register.index', [
            'filter' => ['supplier_id' => $this->supplier->id],
        ]))
        ->assertSuccessful()
        ->assertSee('5,000,000.00');
});

test('ledger register filters by date range', function () {
    LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-01-15',
        'online_amount' => 1234567,
    ]);
    LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-15',
        'online_amount' => 2000000,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.ledger-register.index', [
            'filter' => [
                'supplier_id' => $this->supplier->id,
                'date_from' => '2026-02-01',
                'date_to' => '2026-02-28',
            ],
        ]));

    $response->assertSuccessful()
        ->assertSee('2,000,000.00');

    // The Jan entry amount should not appear as a table entry (only as opening balance)
    expect($response->getContent())->not->toContain('1,234,567.00</td>');
});

test('ledger register filters by document type', function () {
    LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => now()->format('Y-m-d'),
        'online_amount' => 5000000,
    ]);
    LedgerRegister::factory()->invoice()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => now()->format('Y-m-d'),
        'invoice_amount' => 1000000,
    ]);

    $this->actingAs($this->user)
        ->get(route('reports.ledger-register.index', [
            'filter' => [
                'supplier_id' => $this->supplier->id,
                'document_type' => [DocumentType::Dz->value],
            ],
        ]))
        ->assertSuccessful()
        ->assertSee('5,000,000.00');
});

test('can store a new ledger register entry', function () {
    $this->actingAs($this->user)
        ->post(route('reports.ledger-register.store'), [
            'supplier_id' => $this->supplier->id,
            'transaction_date' => '2026-02-02',
            'document_type' => DocumentType::Dz->value,
            'online_amount' => 5000000,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('supplier_ledger_registers', [
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-02',
        'document_type' => DocumentType::Dz->value,
        'online_amount' => 5000000,
    ]);
});

test('can update a ledger register entry', function () {
    $entry = LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-02',
        'online_amount' => 5000000,
    ]);

    $this->actingAs($this->user)
        ->put(route('reports.ledger-register.update', $entry), [
            'supplier_id' => $this->supplier->id,
            'transaction_date' => '2026-02-02',
            'document_type' => DocumentType::Dz->value,
            'online_amount' => 7000000,
        ])
        ->assertRedirect();

    $entry->refresh();
    expect((float) $entry->online_amount)->toBe(7000000.00);
});

test('can delete a ledger register entry', function () {
    $entry = LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-02',
        'online_amount' => 5000000,
    ]);

    $this->actingAs($this->user)
        ->delete(route('reports.ledger-register.destroy', $entry))
        ->assertRedirect();

    $this->assertSoftDeleted('supplier_ledger_registers', ['id' => $entry->id]);
});

test('balance recalculates correctly after store', function () {
    // First entry: Online 5M
    $this->actingAs($this->user)
        ->post(route('reports.ledger-register.store'), [
            'supplier_id' => $this->supplier->id,
            'transaction_date' => '2026-02-01',
            'document_type' => DocumentType::Dz->value,
            'online_amount' => 5000000,
        ]);

    // Second entry: Invoice 2M with ZA 10000
    $this->actingAs($this->user)
        ->post(route('reports.ledger-register.store'), [
            'supplier_id' => $this->supplier->id,
            'transaction_date' => '2026-02-02',
            'document_type' => DocumentType::Dr->value,
            'document_number' => '1073527835',
            'invoice_amount' => 2000000,
            'za_point_five_percent_amount' => 10000,
        ]);

    $entries = LedgerRegister::where('supplier_id', $this->supplier->id)
        ->orderBy('transaction_date')
        ->orderBy('id')
        ->get();

    // First entry balance: 0 + 5000000 = 5000000
    expect((float) $entries[0]->balance)->toBe(5000000.00);
    // Second entry balance: 5000000 - 2000000 + 10000 = 3010000
    expect((float) $entries[1]->balance)->toBe(3010000.00);
});

test('balance recalculates correctly after update', function () {
    $entry1 = LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-01',
        'online_amount' => 5000000,
    ]);
    $entry2 = LedgerRegister::factory()->invoice()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-02',
        'invoice_amount' => 2000000,
        'za_point_five_percent_amount' => 10000,
    ]);
    LedgerRegister::recalculateBalances($this->supplier->id);

    // Update first entry to 3M
    $this->actingAs($this->user)
        ->put(route('reports.ledger-register.update', $entry1), [
            'supplier_id' => $this->supplier->id,
            'transaction_date' => '2026-02-01',
            'document_type' => DocumentType::Dz->value,
            'online_amount' => 3000000,
        ]);

    $entry1->refresh();
    $entry2->refresh();

    // First entry balance: 0 + 3000000 = 3000000
    expect((float) $entry1->balance)->toBe(3000000.00);
    // Second entry balance: 3000000 - 2000000 + 10000 = 1010000
    expect((float) $entry2->balance)->toBe(1010000.00);
});

test('balance recalculates correctly after delete', function () {
    $entry1 = LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-01',
        'online_amount' => 5000000,
    ]);
    $entry2 = LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-02',
        'online_amount' => 3000000,
    ]);
    $entry3 = LedgerRegister::factory()->invoice()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-03',
        'invoice_amount' => 1000000,
        'za_point_five_percent_amount' => 5000,
    ]);
    LedgerRegister::recalculateBalances($this->supplier->id);

    // Delete middle entry
    $this->actingAs($this->user)
        ->delete(route('reports.ledger-register.destroy', $entry2));

    $entry1->refresh();
    $entry3->refresh();

    // First entry balance: 0 + 5000000 = 5000000
    expect((float) $entry1->balance)->toBe(5000000.00);
    // Third entry balance: 5000000 - 1000000 + 5000 = 4005000 (skips deleted entry2)
    expect((float) $entry3->balance)->toBe(4005000.00);
});

test('store requires authentication', function () {
    $this->post(route('reports.ledger-register.store'), [
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-02-02',
        'document_type' => DocumentType::Dz->value,
        'online_amount' => 5000000,
    ])->assertRedirect(route('login'));
});

test('store requires manage permission', function () {
    $viewOnlyUser = User::factory()->create();
    $viewOnlyUser->givePermissionTo('report-audit-ledger-register');

    $this->actingAs($viewOnlyUser)
        ->post(route('reports.ledger-register.store'), [
            'supplier_id' => $this->supplier->id,
            'transaction_date' => '2026-02-02',
            'document_type' => DocumentType::Dz->value,
            'online_amount' => 5000000,
        ])
        ->assertForbidden();
});

test('store validates required fields', function () {
    $this->actingAs($this->user)
        ->post(route('reports.ledger-register.store'), [])
        ->assertSessionHasErrors(['supplier_id', 'transaction_date']);
});

test('can update supplier opening balance', function () {
    $this->actingAs($this->user)
        ->put(route('reports.ledger-register.opening-balance.update', $this->supplier), [
            'ledger_opening_balance' => 500000,
            'ledger_opening_balance_date' => '2026-01-01',
        ])
        ->assertRedirect();

    $this->supplier->refresh();
    expect((float) $this->supplier->ledger_opening_balance)->toBe(500000.00);
    expect($this->supplier->ledger_opening_balance_date->format('Y-m-d'))->toBe('2026-01-01');
});

test('opening balance is included in running balance calculation', function () {
    $this->supplier->update([
        'ledger_opening_balance' => 500000,
        'ledger_opening_balance_date' => '2026-01-01',
    ]);

    $this->actingAs($this->user)
        ->post(route('reports.ledger-register.store'), [
            'supplier_id' => $this->supplier->id,
            'transaction_date' => '2026-02-01',
            'document_type' => DocumentType::Dz->value,
            'online_amount' => 3000000,
        ]);

    $entry = LedgerRegister::where('supplier_id', $this->supplier->id)->first();
    // Balance should be opening_balance + online = 500000 + 3000000 = 3500000
    expect((float) $entry->balance)->toBe(3500000.00);
});

test('opening balance shows on ledger register page', function () {
    $this->supplier->update([
        'ledger_opening_balance' => 250000,
        'ledger_opening_balance_date' => '2026-01-01',
    ]);

    LedgerRegister::factory()->online()->create([
        'supplier_id' => $this->supplier->id,
        'transaction_date' => '2026-01-15',
        'online_amount' => 1000000,
    ]);
    LedgerRegister::recalculateBalances($this->supplier->id);

    $this->actingAs($this->user)
        ->get(route('reports.ledger-register.index', [
            'filter' => [
                'supplier_id' => $this->supplier->id,
                'date_from' => '2026-02-01',
                'date_to' => '2026-02-28',
            ],
        ]))
        ->assertSuccessful()
        ->assertSee('1,250,000.00'); // 250000 opening + 1000000 entry before Feb
});

test('opening balance requires manage permission', function () {
    $viewOnlyUser = User::factory()->create();
    $viewOnlyUser->givePermissionTo('report-audit-ledger-register');

    $this->actingAs($viewOnlyUser)
        ->put(route('reports.ledger-register.opening-balance.update', $this->supplier), [
            'ledger_opening_balance' => 500000,
        ])
        ->assertForbidden();
});

test('opening balance validates required fields', function () {
    $this->actingAs($this->user)
        ->put(route('reports.ledger-register.opening-balance.update', $this->supplier), [])
        ->assertSessionHasErrors(['ledger_opening_balance']);
});

test('negative opening balance works correctly', function () {
    $this->actingAs($this->user)
        ->put(route('reports.ledger-register.opening-balance.update', $this->supplier), [
            'ledger_opening_balance' => -200000,
        ])
        ->assertRedirect();

    $this->supplier->refresh();
    expect((float) $this->supplier->ledger_opening_balance)->toBe(-200000.00);
});
