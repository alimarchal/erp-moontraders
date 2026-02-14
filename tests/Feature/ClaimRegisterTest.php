<?php

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\ClaimRegister;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['claim-register-list', 'claim-register-create', 'claim-register-edit', 'claim-register-delete', 'claim-register-post'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['claim-register-list', 'claim-register-create', 'claim-register-edit', 'claim-register-delete', 'claim-register-post']);
    $this->actingAs($this->user);
});

// ── Index ──────────────────────────────────────────────────────────

test('index page can be rendered', function () {
    $this->get(route('claim-registers.index'))
        ->assertSuccessful()
        ->assertViewIs('claim-registers.index')
        ->assertViewHas('claims');
});

test('index displays claim registers', function () {
    $supplier = Supplier::factory()->create();
    ClaimRegister::factory()->create([
        'supplier_id' => $supplier->id,
        'reference_number' => 'ST-99-01',
    ]);

    $this->get(route('claim-registers.index'))
        ->assertSuccessful()
        ->assertSee('ST-99-01');
});

test('index can filter by supplier', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    ClaimRegister::factory()->create(['supplier_id' => $supplier1->id]);
    ClaimRegister::factory()->create(['supplier_id' => $supplier2->id]);

    $response = $this->get(route('claim-registers.index', ['filter' => ['supplier_id' => $supplier1->id]]));

    $response->assertSuccessful();
    expect($response->viewData('claims'))->toHaveCount(1);
});

// ── Create ─────────────────────────────────────────────────────────

test('create page can be rendered', function () {
    $this->get(route('claim-registers.create'))
        ->assertSuccessful()
        ->assertViewIs('claim-registers.create')
        ->assertViewHas('suppliers');
});

// ── Store ──────────────────────────────────────────────────────────

test('claim can be created as draft', function () {
    $supplier = Supplier::factory()->create();

    $data = [
        'supplier_id' => $supplier->id,
        'transaction_date' => now()->format('Y-m-d'),
        'reference_number' => 'ST-TEST-01',
        'description' => 'TED June-August',
        'claim_month' => 'June-Aug',
        'transaction_type' => 'claim',
        'amount' => 50000.00,
    ];

    $response = $this->post(route('claim-registers.store'), $data);

    $response->assertRedirect(route('claim-registers.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('claim_registers', [
        'supplier_id' => $supplier->id,
        'reference_number' => 'ST-TEST-01',
        'debit' => 50000.00,
        'credit' => 0,
        'posted_at' => null,
        'journal_entry_id' => null,
    ]);
});

test('store requires supplier_id', function () {
    $this->post(route('claim-registers.store'), [
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'claim',
        'amount' => 1000,
    ])->assertSessionHasErrors('supplier_id');
});

test('store requires transaction_date', function () {
    $supplier = Supplier::factory()->create();

    $this->post(route('claim-registers.store'), [
        'supplier_id' => $supplier->id,
        'transaction_type' => 'claim',
        'amount' => 1000,
    ])->assertSessionHasErrors('transaction_date');
});

test('store validates transaction_type is required', function () {
    $supplier = Supplier::factory()->create();

    $this->post(route('claim-registers.store'), [
        'supplier_id' => $supplier->id,
        'transaction_date' => now()->format('Y-m-d'),
        'amount' => 5000,
    ])->assertSessionHasErrors('transaction_type');
});

// ── Show ───────────────────────────────────────────────────────────

test('show page can be rendered', function () {
    $claim = ClaimRegister::factory()->create();

    $this->get(route('claim-registers.show', $claim))
        ->assertSuccessful()
        ->assertViewIs('claim-registers.show')
        ->assertViewHas('claimRegister');
});

test('show page displays draft badge for unposted claims', function () {
    $claim = ClaimRegister::factory()->create();

    $this->get(route('claim-registers.show', $claim))
        ->assertSuccessful()
        ->assertSee('Draft');
});

test('show page displays posted badge for posted claims', function () {
    $claim = ClaimRegister::factory()->create([
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $this->get(route('claim-registers.show', $claim))
        ->assertSuccessful()
        ->assertSee('Posted');
});

// ── Edit ───────────────────────────────────────────────────────────

test('edit page can be rendered', function () {
    $claim = ClaimRegister::factory()->create();

    $this->get(route('claim-registers.edit', $claim))
        ->assertSuccessful()
        ->assertViewIs('claim-registers.edit')
        ->assertViewHas('claimRegister')
        ->assertViewHas('suppliers');
});

test('posted claims cannot be edited', function () {
    $claim = ClaimRegister::factory()->create([
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $this->get(route('claim-registers.edit', $claim))
        ->assertRedirect()
        ->assertSessionHas('error');
});

// ── Update ─────────────────────────────────────────────────────────

test('claim can be updated', function () {
    $supplier = Supplier::factory()->create();
    $claim = ClaimRegister::factory()->create([
        'supplier_id' => $supplier->id,
        'debit' => 50000,
        'credit' => 0,
    ]);

    $data = [
        'supplier_id' => $supplier->id,
        'transaction_date' => now()->format('Y-m-d'),
        'reference_number' => 'ST-UPD-01',
        'transaction_type' => 'claim',
        'amount' => 75000.00,
    ];

    $response = $this->put(route('claim-registers.update', $claim), $data);

    $response->assertRedirect(route('claim-registers.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('claim_registers', [
        'id' => $claim->id,
        'reference_number' => 'ST-UPD-01',
        'debit' => 75000.00,
    ]);
});

test('posted claims cannot be updated', function () {
    $supplier = Supplier::factory()->create();
    $claim = ClaimRegister::factory()->create([
        'supplier_id' => $supplier->id,
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $this->put(route('claim-registers.update', $claim), [
        'supplier_id' => $supplier->id,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_type' => 'claim',
        'amount' => 99999,
    ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

// ── Destroy ────────────────────────────────────────────────────────

test('claim can be deleted', function () {
    $claim = ClaimRegister::factory()->create();

    $this->delete(route('claim-registers.destroy', $claim))
        ->assertRedirect(route('claim-registers.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('claim_registers', ['id' => $claim->id]);
});

test('posted claims cannot be deleted', function () {
    $claim = ClaimRegister::factory()->create([
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $this->delete(route('claim-registers.destroy', $claim))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('claim_registers', ['id' => $claim->id, 'deleted_at' => null]);
});

// ── Post ───────────────────────────────────────────────────────────

test('claim can be posted with valid password', function () {
    $debitAccount = ChartOfAccount::factory()->create(['is_group' => false, 'is_active' => true]);
    $creditAccount = ChartOfAccount::factory()->create(['is_group' => false, 'is_active' => true]);

    $claim = ClaimRegister::factory()->create([
        'debit_account_id' => $debitAccount->id,
        'credit_account_id' => $creditAccount->id,
    ]);

    // Reuse an existing currency (already created by ChartOfAccount factories)
    $currency = Currency::first() ?? Currency::factory()->create();
    $accountingPeriod = AccountingPeriod::factory()->create([
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
    ]);
    $mockJournalEntry = JournalEntry::factory()->create([
        'currency_id' => $currency->id,
        'accounting_period_id' => $accountingPeriod->id,
        'entry_date' => now(),
        'status' => 'posted',
    ]);

    // Mock AccountingService so we don't need full GL infrastructure in tests
    $this->mock(\App\Services\AccountingService::class, function ($mock) use ($mockJournalEntry) {
        $mock->shouldReceive('createJournalEntry')
            ->once()
            ->andReturn(['success' => true, 'data' => $mockJournalEntry]);
    });

    $this->post(route('claim-registers.post', $claim), [
        'password' => 'password',
    ])
        ->assertRedirect(route('claim-registers.show', $claim));

    $claim->refresh();
    expect($claim->posted_at)->not->toBeNull();
    expect($claim->posted_by)->toBe($this->user->id);
    expect($claim->journal_entry_id)->toBe($mockJournalEntry->id);
});

test('post fails with invalid password', function () {
    $claim = ClaimRegister::factory()->create();

    $this->post(route('claim-registers.post', $claim), [
        'password' => 'wrong-password',
    ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $claim->refresh();
    expect($claim->posted_at)->toBeNull();
});

test('already posted claim cannot be posted again', function () {
    $claim = ClaimRegister::factory()->create([
        'posted_at' => now(),
        'posted_by' => $this->user->id,
    ]);

    $this->post(route('claim-registers.post', $claim), [
        'password' => 'password',
    ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('post requires password', function () {
    $claim = ClaimRegister::factory()->create();

    $this->post(route('claim-registers.post', $claim), [])
        ->assertSessionHasErrors('password');
});

// ── Permissions ────────────────────────────────────────────────────

test('unauthenticated users cannot access claim registers', function () {
    auth()->logout();

    $this->get(route('claim-registers.index'))->assertRedirect(route('login'));
});

test('users without permission cannot access index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('claim-registers.index'))->assertForbidden();
});

test('users without permission cannot store', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('claim-registers.store'), [])->assertForbidden();
});

test('users without permission cannot update', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $claim = ClaimRegister::factory()->create();

    $this->put(route('claim-registers.update', $claim), [])->assertForbidden();
});

test('users without permission cannot delete', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $claim = ClaimRegister::factory()->create();

    $this->delete(route('claim-registers.destroy', $claim))->assertForbidden();
});

test('users without permission cannot post', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $claim = ClaimRegister::factory()->create();

    $this->post(route('claim-registers.post', $claim), ['password' => 'password'])->assertForbidden();
});
