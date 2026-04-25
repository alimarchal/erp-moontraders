<?php

use App\Models\GoodsIssue;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'goods-issue-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-issue-view-all', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-issue-create', 'guard_name' => 'web']);

    $this->regularRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $this->regularRole->syncPermissions(['goods-issue-list']);

    $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->adminRole->syncPermissions(['goods-issue-list', 'goods-issue-view-all']);
});

it('user with only list permission sees only their own goods issues on index', function () {
    $user = User::factory()->create();
    $user->assignRole($this->regularRole);

    $otherUser = User::factory()->create();

    $ownIssue = GoodsIssue::factory()->create(['issued_by' => $user->id]);
    $otherIssue = GoodsIssue::factory()->create(['issued_by' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('goods-issues.index'))
        ->assertSuccessful()
        ->assertSee($ownIssue->issue_number)
        ->assertDontSee($otherIssue->issue_number);
});

it('user with view-all permission sees all goods issues on index', function () {
    $admin = User::factory()->create();
    $admin->assignRole($this->adminRole);

    $otherUser1 = User::factory()->create();
    $otherUser2 = User::factory()->create();

    $issue1 = GoodsIssue::factory()->create(['issued_by' => $otherUser1->id]);
    $issue2 = GoodsIssue::factory()->create(['issued_by' => $otherUser2->id]);

    $this->actingAs($admin)
        ->get(route('goods-issues.index'))
        ->assertSuccessful()
        ->assertSee($issue1->issue_number)
        ->assertSee($issue2->issue_number);
});

it('super admin flag user sees all goods issues on index', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);

    $otherUser1 = User::factory()->create();
    $otherUser2 = User::factory()->create();

    $issue1 = GoodsIssue::factory()->create(['issued_by' => $otherUser1->id]);
    $issue2 = GoodsIssue::factory()->create(['issued_by' => $otherUser2->id]);

    $this->actingAs($superAdmin)
        ->get(route('goods-issues.index'))
        ->assertSuccessful()
        ->assertSee($issue1->issue_number)
        ->assertSee($issue2->issue_number);
});

it('scopes goods issues by assigned supplier for non-admin users', function () {
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplierA->id]);
    $user->assignRole($this->regularRole);

    $allowedIssue = GoodsIssue::factory()->create([
        'issued_by' => $user->id,
        'supplier_id' => $supplierA->id,
        'issue_date' => now()->toDateString(),
    ]);

    $blockedIssue = GoodsIssue::factory()->create([
        'issued_by' => $user->id,
        'supplier_id' => $supplierB->id,
        'issue_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('goods-issues.index'))
        ->assertSuccessful()
        ->assertSee($allowedIssue->issue_number)
        ->assertDontSee($blockedIssue->issue_number);
});

it('forbids non-admin user from directly filtering goods issues for another supplier', function () {
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplierA->id]);
    $user->assignRole($this->regularRole);

    $this->actingAs($user)
        ->get(route('goods-issues.index', ['filter[supplier_id]' => $supplierB->id]))
        ->assertForbidden();
});

it('shows only assigned supplier in goods issue create form for non-admin user', function () {
    $supplierA = Supplier::factory()->create(['supplier_name' => 'Supplier A', 'disabled' => false]);
    $supplierB = Supplier::factory()->create(['supplier_name' => 'Supplier B', 'disabled' => false]);

    $user = User::factory()->create(['supplier_id' => $supplierA->id]);
    $user->assignRole($this->regularRole);
    $user->givePermissionTo('goods-issue-create');

    $this->actingAs($user)
        ->get(route('goods-issues.create'))
        ->assertSuccessful()
        ->assertSee($supplierA->supplier_name)
        ->assertDontSee($supplierB->supplier_name);
});

it('forbids non-admin user from viewing goods issue of another supplier', function () {
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplierA->id]);
    $user->assignRole($this->regularRole);

    $issue = GoodsIssue::factory()->create(['supplier_id' => $supplierB->id]);

    $this->actingAs($user)
        ->get(route('goods-issues.show', $issue))
        ->assertForbidden();
});

it('forbids non-admin user from loading products for another supplier via api', function () {
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplierA->id]);
    $user->assignRole($this->regularRole);

    $this->actingAs($user)
        ->get(route('api.products.by-suppliers', ['supplier_ids' => [$supplierB->id]]))
        ->assertForbidden();
});

it('returns only assigned supplier products via api for non-admin user', function () {
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplierA->id]);
    $user->assignRole($this->regularRole);

    $allowedProduct = Product::factory()->create(['supplier_id' => $supplierA->id, 'is_active' => true]);
    $blockedProduct = Product::factory()->create(['supplier_id' => $supplierB->id, 'is_active' => true]);

    $response = $this->actingAs($user)
        ->get(route('api.products.by-suppliers'));

    $response->assertSuccessful();
    $response->assertSee($allowedProduct->product_name);
    $response->assertDontSee($blockedProduct->product_name);
});
