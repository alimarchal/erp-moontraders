<?php

use App\Models\GoodsReceiptNote;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create all permissions needed for GRN
    Permission::firstOrCreate(['name' => 'goods-receipt-note-list', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-view-all', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-delete', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-post', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-reverse', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-import', 'guard_name' => 'web']);

    // Create roles
    $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $userRole->syncPermissions([
        'goods-receipt-note-list',
        'goods-receipt-note-create',
        'goods-receipt-note-edit',
        'goods-receipt-note-delete',
        'goods-receipt-note-post',
        'goods-receipt-note-reverse',
        'goods-receipt-note-import',
    ]);

    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->syncPermissions([
        'goods-receipt-note-list',
        'goods-receipt-note-view-all',
        'goods-receipt-note-create',
        'goods-receipt-note-edit',
        'goods-receipt-note-delete',
        'goods-receipt-note-post',
        'goods-receipt-note-reverse',
        'goods-receipt-note-import',
    ]);

    $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $superAdminRole->syncPermissions([
        'goods-receipt-note-list',
        'goods-receipt-note-view-all',
        'goods-receipt-note-create',
        'goods-receipt-note-edit',
        'goods-receipt-note-delete',
        'goods-receipt-note-post',
        'goods-receipt-note-reverse',
        'goods-receipt-note-import',
    ]);
});

it('non-admin user sees only their assigned supplier grns in listing', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One']);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two']);

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole('user');

    $grn1 = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier1->id, 'received_by' => $user->id]);
    $grn2 = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier2->id]);

    $this->actingAs($user)
        ->get(route('goods-receipt-notes.index'))
        ->assertSuccessful()
        ->assertSee($grn1->grn_number)
        ->assertDontSee($grn2->grn_number);
});

it('admin user sees all grns regardless of supplier', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One']);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two']);

    $admin = User::factory()->create(['supplier_id' => $supplier1->id]);
    $admin->assignRole('admin');

    $grn1 = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier1->id]);
    $grn2 = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier2->id]);

    $this->actingAs($admin)
        ->get(route('goods-receipt-notes.index'))
        ->assertSuccessful()
        ->assertSee($grn1->grn_number)
        ->assertSee($grn2->grn_number);
});

it('super-admin user sees all grns', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One']);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two']);

    $superAdmin = User::factory()->create(['is_super_admin' => 'Yes']);

    $grn1 = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier1->id]);
    $grn2 = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier2->id]);

    $this->actingAs($superAdmin)
        ->get(route('goods-receipt-notes.index'))
        ->assertSuccessful()
        ->assertSee($grn1->grn_number)
        ->assertSee($grn2->grn_number);
});

it('user with no supplier assigned sees all grns', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One']);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two']);

    $user = User::factory()->create(['supplier_id' => null]);
    $user->assignRole('user');

    $grn1 = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier1->id, 'received_by' => $user->id]);
    $grn2 = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier2->id, 'received_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('goods-receipt-notes.index'))
        ->assertSuccessful()
        ->assertSee($grn1->grn_number)
        ->assertSee($grn2->grn_number);
});

it('non-admin user can only see their supplier on create form', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One', 'disabled' => false]);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two', 'disabled' => false]);

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee($supplier1->supplier_name)
        ->assertDontSee($supplier2->supplier_name);
});

it('admin sees all suppliers on create form', function () {
    $supplier1 = Supplier::factory()->create(['supplier_name' => 'Supplier One', 'disabled' => false]);
    $supplier2 = Supplier::factory()->create(['supplier_name' => 'Supplier Two', 'disabled' => false]);

    $admin = User::factory()->create(['supplier_id' => $supplier1->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee($supplier1->supplier_name)
        ->assertSee($supplier2->supplier_name);
});

it('non-admin user cannot access grn from different supplier', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user1 = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user1->assignRole('user');

    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier2->id, 'status' => 'draft']);

    $this->actingAs($user1)
        ->get(route('goods-receipt-notes.show', $grn))
        ->assertForbidden();
});

it('non-admin user cannot edit grn from different supplier', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user1 = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user1->assignRole('user');

    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier2->id, 'status' => 'draft']);

    $this->actingAs($user1)
        ->get(route('goods-receipt-notes.edit', $grn))
        ->assertForbidden();
});

it('non-admin user cannot delete grn from different supplier', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user1 = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user1->assignRole('user');

    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier2->id, 'status' => 'draft']);

    $this->actingAs($user1)
        ->delete(route('goods-receipt-notes.destroy', $grn))
        ->assertForbidden();
});

it('non-admin user cannot import grn for different supplier', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole('user');

    $file = UploadedFile::fake()->create('import.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($user)
        ->post(route('goods-receipt-notes.import'), [
            'supplier_id' => $supplier2->id,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->toDateString(),
            'import_file' => $file,
        ])
        ->assertSessionHas('error', 'You do not have permission to import GRNs for this supplier.');
});

it('admin can access products from any supplier via ajax', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get("/api/suppliers/{$supplier2->id}/products")
        ->assertSuccessful();
});

it('non-admin user cannot access products from different supplier via ajax', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->get("/api/suppliers/{$supplier2->id}/products")
        ->assertForbidden();
});

it('non-admin user can access products from their assigned supplier via ajax', function () {
    $supplier1 = Supplier::factory()->create();

    $user = User::factory()->create(['supplier_id' => $supplier1->id]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->get("/api/suppliers/{$supplier1->id}/products")
        ->assertSuccessful();
});
