<?php

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\ClaimRegister;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-claim-register', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'claim-register-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'claim-register-post', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['report-audit-claim-register', 'claim-register-create', 'claim-register-post']);
    $this->actingAs($this->user);
});

test('claim register report is scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create([
        'supplier_name' => 'Kausar Oil',
        'short_name' => 'Kausar',
        'disabled' => false,
    ]);
    $otherSupplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    ClaimRegister::factory()->create([
        'supplier_id' => $ownSupplier->id,
        'transaction_date' => now()->toDateString(),
        'reference_number' => 'OWN-CLAIM-001',
    ]);
    ClaimRegister::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'transaction_date' => now()->toDateString(),
        'reference_number' => 'OTHER-CLAIM-001',
    ]);

    $response = $this->get(route('reports.claim-register.index'));

    $response->assertSuccessful();
    $response->assertSee('OWN-CLAIM-001');
    $response->assertDontSee('OTHER-CLAIM-001');
    expect($response->viewData('supplierId'))->toBe($ownSupplier->id);
    expect($response->viewData('suppliers'))->toHaveCount(1);
    expect($response->viewData('suppliers')->first()->id)->toBe($ownSupplier->id);
});

test('claim register report blocks filtering by another supplier for scoped users', function () {
    $ownSupplier = Supplier::factory()->create(['disabled' => false]);
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->get(route('reports.claim-register.index', ['supplier_id' => $otherSupplier->id]))
        ->assertForbidden();
});

test('claim register report blocks creating entries for another supplier', function () {
    $ownSupplier = Supplier::factory()->create(['disabled' => false]);
    $otherSupplier = Supplier::factory()->create(['disabled' => false]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->post(route('reports.claim-register.store'), [
        'supplier_id' => $otherSupplier->id,
        'transaction_date' => now()->toDateString(),
        'reference_number' => 'FORBIDDEN-CLAIM',
        'transaction_type' => 'claim',
        'amount' => 1000,
    ])->assertForbidden();

    $this->assertDatabaseMissing('claim_registers', [
        'supplier_id' => $otherSupplier->id,
        'reference_number' => 'FORBIDDEN-CLAIM',
    ]);
});

test('claim register report allows creating entries for the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create(['disabled' => false]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->post(route('reports.claim-register.store'), [
        'supplier_id' => $ownSupplier->id,
        'transaction_date' => now()->toDateString(),
        'reference_number' => 'OWN-NEW-CLAIM',
        'transaction_type' => 'claim',
        'amount' => 1000,
    ])->assertRedirect();

    $this->assertDatabaseHas('claim_registers', [
        'supplier_id' => $ownSupplier->id,
        'reference_number' => 'OWN-NEW-CLAIM',
        'debit' => 1000,
        'credit' => 0,
    ]);
});

test('claim register report shows post action for unposted claim', function () {
    $supplier = Supplier::factory()->create(['disabled' => false]);
    $this->user->forceFill(['supplier_id' => $supplier->id])->save();

    $claim = ClaimRegister::factory()->create([
        'supplier_id' => $supplier->id,
        'transaction_date' => now()->toDateString(),
        'posted_at' => null,
    ]);

    $response = $this->get(route('reports.claim-register.index', [
        'supplier_id' => $supplier->id,
        'date_from' => now()->startOfMonth()->toDateString(),
        'date_to' => now()->endOfMonth()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertSee(route('reports.claim-register.post', $claim), false);
});

test('claim can be posted from claim register report', function () {
    $supplier = Supplier::factory()->create(['disabled' => false]);
    $this->user->forceFill(['supplier_id' => $supplier->id])->save();

    $debitAccount = ChartOfAccount::factory()->create([
        'account_code' => '1112',
        'is_group' => false,
        'is_active' => true,
    ]);

    $creditAccount = ChartOfAccount::factory()->create([
        'account_code' => '4230',
        'is_group' => false,
        'is_active' => true,
    ]);

    $claim = ClaimRegister::factory()->claim()->create([
        'supplier_id' => $supplier->id,
        'transaction_date' => now()->toDateString(),
        'debit_account_id' => $debitAccount->id,
        'credit_account_id' => $creditAccount->id,
        'posted_at' => null,
    ]);

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

    $this->mock(AccountingService::class, function ($mock) use ($mockJournalEntry) {
        $mock->shouldReceive('createJournalEntry')
            ->once()
            ->andReturn(['success' => true, 'data' => $mockJournalEntry]);
    });

    $this->post(route('reports.claim-register.post', $claim))
        ->assertRedirect();

    $claim->refresh();

    expect($claim->posted_at)->not->toBeNull();
    expect($claim->posted_by)->toBe($this->user->id);
    expect($claim->journal_entry_id)->toBe($mockJournalEntry->id);
});
