<?php

use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create all permissions needed for Sales Settlement
    Permission::firstOrCreate(['name' => 'sales-settlement-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-delete', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-post', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'sales-settlement-view-all', 'guard_name' => 'web']);

    $this->userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $this->userRole->syncPermissions([
        'sales-settlement-list',
        'sales-settlement-create',
        'sales-settlement-edit',
        'sales-settlement-delete',
        'sales-settlement-post',
    ]);

    $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->adminRole->syncPermissions([
        'sales-settlement-list',
        'sales-settlement-create',
        'sales-settlement-edit',
        'sales-settlement-delete',
        'sales-settlement-post',
        'sales-settlement-view-all',
    ]);
});

it('non-admin user sees only their assigned supplier settlements in listing', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One']);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two']);

    $user1 = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user1->assignRole($this->userRole);

    $this->actingAs($user1);
    $settlement1 = SalesSettlement::factory()->create(['supplier_id' => $supplier1->id, 'created_by' => $user1->id]);

    $otherUser = User::factory()->create(['supplier_id' => $supplier2->id]);
    $this->actingAs($otherUser);
    $settlement2 = SalesSettlement::factory()->create(['supplier_id' => $supplier2->id, 'created_by' => $otherUser->id]);

    $this->actingAs($user1)
        ->get(route('sales-settlements.index'))
        ->assertSuccessful()
        ->assertSee($settlement1->settlement_number)
        ->assertDontSee($settlement2->settlement_number);
});

it('admin sees all supplier settlements in listing', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One']);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two']);

    $admin = User::factory()->create();
    $admin->assignRole($this->adminRole);

    $settlement1 = SalesSettlement::factory()->create(['supplier_id' => $supplier1->id]);
    $settlement2 = SalesSettlement::factory()->create(['supplier_id' => $supplier2->id]);

    $this->actingAs($admin)
        ->get(route('sales-settlements.index'))
        ->assertSuccessful()
        ->assertSee($settlement1->settlement_number)
        ->assertSee($settlement2->settlement_number);
});

it('super-admin sees all supplier settlements in listing', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);

    $settlement1 = SalesSettlement::factory()->create(['supplier_id' => $supplier1->id]);
    $settlement2 = SalesSettlement::factory()->create(['supplier_id' => $supplier2->id]);

    $this->actingAs($superAdmin)
        ->get(route('sales-settlements.index'))
        ->assertSuccessful()
        ->assertSee($settlement1->settlement_number)
        ->assertSee($settlement2->settlement_number);
});

it('non-admin user sees only their assigned supplier in create form', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One', 'disabled' => false]);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two', 'disabled' => false]);

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole($this->userRole);

    $this->actingAs($user)
        ->get(route('sales-settlements.create'))
        ->assertSuccessful()
        ->assertSee($supplier1->supplier_name)
        ->assertDontSee($supplier2->supplier_name);
});

it('admin sees all suppliers in create form', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One', 'disabled' => false]);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two', 'disabled' => false]);

    $admin = User::factory()->create();
    $admin->assignRole($this->adminRole);

    $this->actingAs($admin)
        ->get(route('sales-settlements.create'))
        ->assertSuccessful()
        ->assertSee($supplier1->supplier_name)
        ->assertSee($supplier2->supplier_name);
});

it('non-admin user cannot view settlement from different supplier', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole($this->userRole);

    $settlement = SalesSettlement::factory()->create(['supplier_id' => $supplier2->id]);

    $this->actingAs($user)
        ->get(route('sales-settlements.show', $settlement))
        ->assertForbidden();
});

it('non-admin user can view settlement from their assigned supplier', function () {
    $supplier = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier->id]);
    $user->assignRole($this->userRole);

    $this->actingAs($user);
    $settlement = SalesSettlement::factory()->create(['supplier_id' => $supplier->id, 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('sales-settlements.show', $settlement))
        ->assertSuccessful();
});

it('admin can view any supplier settlement', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $admin = User::factory()->create();
    $admin->assignRole($this->adminRole);

    $settlement = SalesSettlement::factory()->create(['supplier_id' => $supplier1->id]);

    $this->actingAs($admin)
        ->get(route('sales-settlements.show', $settlement))
        ->assertSuccessful();
});

it('non-admin user cannot edit settlement from different supplier', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole($this->userRole);

    $settlement = SalesSettlement::factory()->create(['supplier_id' => $supplier2->id, 'status' => 'draft']);

    $this->actingAs($user)
        ->get(route('sales-settlements.edit', $settlement))
        ->assertForbidden();
});

it('non-admin user cannot delete settlement from different supplier', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole($this->userRole);

    $settlement = SalesSettlement::factory()->create(['supplier_id' => $supplier2->id, 'status' => 'draft']);

    $this->actingAs($user)
        ->delete(route('sales-settlements.destroy', $settlement))
        ->assertForbidden();
});

it('non-admin user cannot post settlement from different supplier', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole($this->userRole);

    $settlement = SalesSettlement::factory()->create(['supplier_id' => $supplier2->id, 'status' => 'draft']);

    $this->actingAs($user)
        ->post(route('sales-settlements.post', $settlement))
        ->assertForbidden();
});

it('non-admin user cannot fetch goods issues from different supplier via api', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole($this->userRole);

    $this->actingAs($user)
        ->get(route('api.sales-settlements.goods-issues', ['supplier_id' => $supplier2->id]))
        ->assertForbidden();
});

it('non-admin user can fetch goods issues from their assigned supplier via api', function () {
    $supplier = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier->id]);
    $user->assignRole($this->userRole);

    $goodsIssue = GoodsIssue::factory()->create(['supplier_id' => $supplier->id, 'status' => 'issued']);

    $response = $this->actingAs($user)
        ->get(route('api.sales-settlements.goods-issues', ['supplier_id' => $supplier->id]));

    $response->assertSuccessful();
    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($goodsIssue->id);
});

it('admin can fetch goods issues from any supplier via api', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $admin = User::factory()->create();
    $admin->assignRole($this->adminRole);

    $goodsIssue = GoodsIssue::factory()->create(['supplier_id' => $supplier2->id, 'status' => 'issued']);

    $response = $this->actingAs($admin)
        ->get(route('api.sales-settlements.goods-issues', ['supplier_id' => $supplier2->id]));

    $response->assertSuccessful();
    $data = $response->json();
    expect($data)->toHaveCount(1);
});

it('user with no supplier assigned sees all settlements', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => null]);
    $user->assignRole($this->userRole);

    $this->actingAs($user);
    $settlement1 = SalesSettlement::factory()->create(['supplier_id' => $supplier1->id, 'created_by' => $user->id]);
    $settlement2 = SalesSettlement::factory()->create(['supplier_id' => $supplier2->id, 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('sales-settlements.index'))
        ->assertSuccessful()
        ->assertSee($settlement1->settlement_number)
        ->assertSee($settlement2->settlement_number);
});
