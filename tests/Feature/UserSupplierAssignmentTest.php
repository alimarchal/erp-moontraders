<?php

use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['user-create', 'user-edit'] as $perm) {
        Permission::firstOrCreate(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('stores supplier_id on user create', function () {
    $this->user->givePermissionTo('user-create');

    $supplier = Supplier::factory()->create();

    $response = $this->post(route('users.store'), [
        'name' => 'Supplier Linked User',
        'designation' => 'Sales Officer',
        'supplier_id' => $supplier->id,
        'email' => 'supplier-linked@example.com',
        'password' => 'password123',
        'is_super_admin' => 'No',
        'is_active' => 'Yes',
    ]);

    $response->assertRedirect(route('users.index'));

    $created = User::where('email', 'supplier-linked@example.com')->first();

    expect($created)->not->toBeNull();
    expect($created->supplier_id)->toBe($supplier->id);
});

it('updates supplier_id on user update', function () {
    $this->user->givePermissionTo('user-edit');

    $initialSupplier = Supplier::factory()->create();
    $newSupplier = Supplier::factory()->create();

    $targetUser = User::factory()->create([
        'supplier_id' => $initialSupplier->id,
        'is_super_admin' => 'No',
        'is_active' => 'Yes',
    ]);

    $response = $this->put(route('users.update', $targetUser), [
        'name' => $targetUser->name,
        'designation' => $targetUser->designation,
        'supplier_id' => $newSupplier->id,
        'email' => $targetUser->email,
        'password' => '',
        'is_super_admin' => 'No',
        'is_active' => 'Yes',
        'roles' => [],
        'permissions' => [],
    ]);

    $response->assertRedirect(route('users.index'));

    expect($targetUser->fresh()->supplier_id)->toBe($newSupplier->id);
});
