<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $allPermissions = [
        'user-list', 'user-create', 'user-edit', 'user-delete', 'user-bulk-update',
        'role-list', 'role-create', 'role-edit', 'role-delete', 'role-sync',
        'permission-list', 'permission-create', 'permission-edit', 'permission-delete',
        'journal-entry-list', 'journal-entry-create', 'journal-entry-edit', 'journal-entry-delete', 'journal-entry-post', 'journal-entry-reverse',
        'goods-issue-list', 'goods-issue-create', 'goods-issue-edit', 'goods-issue-delete', 'goods-issue-post',
        'goods-receipt-note-list', 'goods-receipt-note-create', 'goods-receipt-note-edit', 'goods-receipt-note-delete', 'goods-receipt-note-post', 'goods-receipt-note-reverse',
        'sales-settlement-list', 'sales-settlement-create', 'sales-settlement-edit', 'sales-settlement-delete', 'sales-settlement-post',
        'supplier-payment-list', 'supplier-payment-create', 'supplier-payment-edit', 'supplier-payment-delete', 'supplier-payment-post', 'supplier-payment-reverse',
        'promotional-campaign-list', 'promotional-campaign-create', 'promotional-campaign-edit', 'promotional-campaign-delete',
        'setting-view', 'setting-update',
        'inventory-view',
        'report-view-financial', 'report-view-inventory', 'report-view-sales', 'report-view-audit',
        'chart-of-account-list', 'account-type-list', 'currency-list', 'accounting-period-list',
        'bank-account-list', 'cost-center-list', 'tax-list',
        'company-list', 'supplier-list', 'customer-list', 'employee-list',
        'product-list', 'category-list', 'uom-list',
        'warehouse-list', 'warehouse-type-list', 'vehicle-list',
        'claim-register-list', 'claim-register-create', 'claim-register-edit', 'claim-register-delete', 'claim-register-post',
        'stock-adjustment-list', 'product-recall-list',
    ];

    foreach ($allPermissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

// ── Helper ──────────────────────────────────────────────────────────

function userWithPermissions(array $permissions = []): User
{
    $user = User::factory()->create();
    $user->assignRole('user');
    if ($permissions) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

// ── Journal Entries: Middleware enforced ─────────────────────────────

test('user without journal-entry-list cannot access journal entries index', function () {
    $this->actingAs(userWithPermissions())
        ->get(route('journal-entries.index'))
        ->assertForbidden();
});

test('user with journal-entry-list can access journal entries index', function () {
    $this->actingAs(userWithPermissions(['journal-entry-list']))
        ->get(route('journal-entries.index'))
        ->assertSuccessful();
});

test('user without journal-entry-create cannot access journal entry create', function () {
    $this->actingAs(userWithPermissions(['journal-entry-list']))
        ->get(route('journal-entries.create'))
        ->assertForbidden();
});

test('user with journal-entry-create can access journal entry create', function () {
    $this->actingAs(userWithPermissions(['journal-entry-list', 'journal-entry-create']))
        ->get(route('journal-entries.create'))
        ->assertSuccessful();
});

// ── Promotional Campaigns: Middleware enforced ──────────────────────

test('user without promotional-campaign-list cannot access campaigns index', function () {
    $this->actingAs(userWithPermissions())
        ->get(route('promotional-campaigns.index'))
        ->assertForbidden();
});

test('user with promotional-campaign-list can access campaigns index', function () {
    $this->actingAs(userWithPermissions(['promotional-campaign-list']))
        ->get(route('promotional-campaigns.index'))
        ->assertSuccessful();
});

test('user without promotional-campaign-create cannot access campaign create', function () {
    $this->actingAs(userWithPermissions(['promotional-campaign-list']))
        ->get(route('promotional-campaigns.create'))
        ->assertForbidden();
});

// ── Settings: Middleware enforced ────────────────────────────────────

test('user without setting-view cannot access settings index', function () {
    $this->actingAs(userWithPermissions())
        ->get(route('settings.index'))
        ->assertForbidden();
});

test('user with setting-view can access settings index', function () {
    $this->actingAs(userWithPermissions(['setting-view']))
        ->get(route('settings.index'))
        ->assertSuccessful();
});

// ── Supplier Payments: Middleware enforced ───────────────────────────

test('user without supplier-payment-list cannot access payments index', function () {
    $this->actingAs(userWithPermissions())
        ->get(route('supplier-payments.index'))
        ->assertForbidden();
});

test('user with supplier-payment-list can access payments index', function () {
    $this->actingAs(userWithPermissions(['supplier-payment-list']))
        ->get(route('supplier-payments.index'))
        ->assertSuccessful();
});

// ── Goods Issue: Isolated permission ─────────────────────────────────

test('user with goods-issue-list only cannot access inventory page', function () {
    $user = userWithPermissions(['goods-issue-list']);

    $this->actingAs($user)
        ->get(route('inventory.current-stock.index'))
        ->assertForbidden();
});

test('user with inventory-view can access inventory page', function () {
    $this->actingAs(userWithPermissions(['inventory-view']))
        ->get(route('inventory.current-stock.index'))
        ->assertSuccessful();
});

test('user with report-view-inventory can also access inventory page', function () {
    $this->actingAs(userWithPermissions(['report-view-inventory']))
        ->get(route('inventory.current-stock.index'))
        ->assertSuccessful();
});

// ── Navigation: Isolated permissions ─────────────────────────────────

test('goods-issue-list does not show inventory nav link', function () {
    $response = $this->actingAs(userWithPermissions(['goods-issue-list']))
        ->get(route('goods-issues.index'));

    $response->assertDontSee('href="'.route('inventory.current-stock.index').'"', false);
});

test('inventory-view shows inventory nav link', function () {
    $response = $this->actingAs(userWithPermissions(['inventory-view', 'goods-issue-list']))
        ->get(route('goods-issues.index'));

    $response->assertSee(route('inventory.current-stock.index'), false);
});

// ── Reports: Isolated permissions ────────────────────────────────────

test('user without report permissions cannot access reports index', function () {
    $this->actingAs(userWithPermissions())
        ->get(route('reports.index'))
        ->assertForbidden();
});

test('user with report-view-financial can access reports index', function () {
    $this->actingAs(userWithPermissions(['report-view-financial']))
        ->get(route('reports.index'))
        ->assertSuccessful();
});

test('user without report-view-financial cannot access general ledger', function () {
    $this->actingAs(userWithPermissions(['report-view-sales']))
        ->get(route('reports.general-ledger.index'))
        ->assertForbidden();
});

test('user with report-view-financial can access general ledger', function () {
    $this->actingAs(userWithPermissions(['report-view-financial']))
        ->get(route('reports.general-ledger.index'))
        ->assertSuccessful();
});

// ── User role has no default permissions ─────────────────────────────

test('user role has no default permissions', function () {
    $role = Role::findByName('user', 'web');
    expect($role->permissions)->toHaveCount(0);
});

// ── Unauthenticated access redirects to login ────────────────────────

test('unauthenticated user is redirected to login', function () {
    $this->get(route('journal-entries.index'))
        ->assertRedirect(route('login'));

    $this->get(route('settings.index'))
        ->assertRedirect(route('login'));

    $this->get(route('reports.index'))
        ->assertRedirect(route('login'));
});
