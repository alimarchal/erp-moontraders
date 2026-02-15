<?php

use App\Livewire\Dashboard;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\SalesSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────

function createDashboardPermissions(): void
{
    $permissions = [
        'sales-settlement-list', 'sales-settlement-create',
        'goods-receipt-note-list', 'goods-receipt-note-create',
        'supplier-payment-list', 'supplier-payment-create',
        'inventory-view',
        'goods-issue-list', 'goods-issue-create',
        'journal-entry-list', 'journal-entry-create',
        'report-view-financial', 'report-view-inventory', 'report-view-sales',
        'accounting-view',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm]);
    }
}

function createSuperAdminUser(): User
{
    createDashboardPermissions();

    $user = User::factory()->create(['is_super_admin' => 'Yes']);

    return $user;
}

// ── Basic Access ─────────────────────────────────────────────────────

it('renders the dashboard page for authenticated users', function () {
    $user = createSuperAdminUser();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSeeLivewire(Dashboard::class);
});

it('redirects unauthenticated users from dashboard', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

// ── Livewire Component ──────────────────────────────────────────────

it('loads KPI cards for super admin', function () {
    $user = createSuperAdminUser();

    SalesSettlement::factory()->create([
        'status' => 'posted',
        'settlement_date' => now(),
        'total_sales_amount' => 5000,
        'cash_sales_amount' => 3000,
        'credit_sales_amount' => 2000,
        'gross_profit' => 1500,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('kpiCards.totalSalesThisMonth', 5000.0)
        ->assertSet('kpiCards.cashCollectedThisMonth', 3000.0)
        ->assertSet('kpiCards.creditSalesThisMonth', 2000.0)
        ->assertSet('kpiCards.grossProfitThisMonth', 1500.0)
        ->assertSuccessful();
});

it('loads pending items count', function () {
    $user = createSuperAdminUser();

    SalesSettlement::factory()->count(3)->create(['status' => 'draft']);
    $supplier = \App\Models\Supplier::factory()->create();
    GoodsReceiptNote::factory()->count(2)->create(['status' => 'draft', 'supplier_id' => $supplier->id]);
    $currency = \App\Models\Currency::firstOrCreate(
        ['currency_code' => 'TST'],
        ['currency_name' => 'Test Currency', 'currency_symbol' => 'T', 'exchange_rate' => 1, 'is_base_currency' => false, 'is_active' => true]
    );
    $accountingPeriod = \App\Models\AccountingPeriod::factory()->create();
    JournalEntry::factory()->count(1)->create([
        'status' => 'draft',
        'entry_date' => now(),
        'currency_id' => $currency->id,
        'accounting_period_id' => $accountingPeriod->id,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('pendingItems.draftSettlements', 3)
        ->assertSet('pendingItems.draftGrns', 2)
        ->assertSet('pendingItems.draftJournalEntries', 1)
        ->assertSuccessful();
});

it('shows settlement status distribution', function () {
    $user = createSuperAdminUser();

    SalesSettlement::factory()->count(2)->create(['status' => 'draft']);
    SalesSettlement::factory()->count(3)->create([
        'status' => 'posted',
        'settlement_date' => now(),
        'total_sales_amount' => 1000,
    ]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->get('settlementStatusDistribution.labels'))->toContain('Draft');
    expect($component->get('settlementStatusDistribution.labels'))->toContain('Posted');
});

// ── Role-based visibility ───────────────────────────────────────────

it('hides sales data from users without sales permissions', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(['goods-receipt-note-list', 'inventory-view']);

    SalesSettlement::factory()->create([
        'status' => 'posted',
        'settlement_date' => now(),
        'total_sales_amount' => 5000,
    ]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->get('kpiCards'))->not->toHaveKey('totalSalesThisMonth');
    expect($component->get('salesByPaymentMethod'))->toBeEmpty();
    expect($component->get('dailySalesTrend'))->toBeEmpty();
});

it('shows inventory data for users with inventory permissions', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(['inventory-view', 'report-view-inventory', 'goods-receipt-note-list']);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->get('kpiCards'))->toHaveKey('totalInventoryValue');
    expect($component->get('kpiCards'))->toHaveKey('productsInStock');
});

it('shows accounting data for users with financial permissions', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(['report-view-financial', 'journal-entry-list']);

    $accountingPeriod = \App\Models\AccountingPeriod::factory()->create();
    JournalEntry::factory()->count(2)->create([
        'status' => 'draft',
        'entry_date' => now(),
        'currency_id' => \App\Models\Currency::factory()->create()->id,
        'accounting_period_id' => $accountingPeriod->id,
    ]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->get('kpiCards'))->toHaveKey('draftJournalEntries');
    expect($component->get('kpiCards.draftJournalEntries'))->toBe(2);
});

// ── View rendering ──────────────────────────────────────────────────

it('renders quick action links based on permissions', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(['goods-receipt-note-create', 'sales-settlement-create']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('New GRN')
        ->assertSee('New Settlement')
        ->assertDontSee('New Journal')
        ->assertSuccessful();
});

it('renders pending actions alert when drafts exist', function () {
    $user = createSuperAdminUser();

    SalesSettlement::factory()->count(2)->create(['status' => 'draft']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Pending Actions')
        ->assertSee('Draft Settlements')
        ->assertSuccessful();
});

it('hides pending actions alert when no drafts exist', function () {
    $user = createSuperAdminUser();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSee('Pending Actions')
        ->assertSuccessful();
});
