<?php

use App\Models\Customer;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['customer-list', 'customer-create', 'customer-edit', 'customer-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without customer-list permission', function () {
    $this->get(route('customers.index'))->assertForbidden();
});

it('allows index with customer-list permission', function () {
    $this->user->givePermissionTo('customer-list');
    $this->get(route('customers.index'))->assertSuccessful();
});

it('denies create without customer-create permission', function () {
    $this->get(route('customers.create'))->assertForbidden();
});

it('allows create with customer-create permission', function () {
    $this->user->givePermissionTo('customer-create');
    $this->get(route('customers.create'))->assertSuccessful();
});

it('denies store without customer-create permission', function () {
    $this->post(route('customers.store'), [])->assertForbidden();
});

it('denies show without customer-list permission', function () {
    $customer = Customer::factory()->create();
    $this->get(route('customers.show', $customer))->assertForbidden();
});

it('denies edit without customer-edit permission', function () {
    $customer = Customer::factory()->create();
    $this->get(route('customers.edit', $customer))->assertForbidden();
});

it('denies update without customer-edit permission', function () {
    $customer = Customer::factory()->create();
    $this->put(route('customers.update', $customer), [])->assertForbidden();
});

it('denies destroy without customer-delete permission', function () {
    $customer = Customer::factory()->create();
    $this->delete(route('customers.destroy', $customer))->assertForbidden();
});

it('denies destroy to a non super admin even with customer-delete permission', function () {
    $this->user->givePermissionTo('customer-delete');
    $customer = Customer::factory()->create(['is_active' => true]);

    $this->delete(route('customers.destroy', $customer))->assertForbidden();

    expect($customer->fresh()->is_active)->toBeTrue();
});

it('deactivates instead of deleting when a super admin destroys a customer', function () {
    $this->user->forceFill(['is_super_admin' => 'Yes'])->save();
    $this->user->givePermissionTo('customer-delete');
    $customer = Customer::factory()->create(['is_active' => true]);

    $this->delete(route('customers.destroy', $customer))->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_active' => false, 'deleted_at' => null]);
});
