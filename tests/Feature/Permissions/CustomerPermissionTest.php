<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Route;
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

it('has no delete route for customers', function () {
    expect(Route::has('customers.destroy'))->toBeFalse();
});

it('denies activating or deactivating without customer-edit permission', function () {
    $customer = Customer::factory()->create(['is_active' => true]);
    $this->post(route('customers.toggle-active', $customer))->assertForbidden();

    expect($customer->fresh()->is_active)->toBeTrue();
});

it('deactivates and activates a customer without deleting anything', function () {
    $this->user->givePermissionTo('customer-edit');
    $customer = Customer::factory()->create(['is_active' => true]);

    $this->post(route('customers.toggle-active', $customer))->assertRedirect();
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_active' => false, 'deleted_at' => null]);

    $this->post(route('customers.toggle-active', $customer))->assertRedirect();
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'is_active' => true, 'deleted_at' => null]);
});
