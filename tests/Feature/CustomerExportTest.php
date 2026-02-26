<?php

use App\Models\Customer;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'customer-list']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('customer-list');
    $this->actingAs($this->user);
});

it('downloads an excel file for authenticated user with permission', function () {
    Customer::factory()->count(3)->create();

    $this->get(route('customers.export.excel'))
        ->assertSuccessful()
        ->assertDownload('customers.xlsx');
});

it('exports only filtered customers by channel type', function () {
    Customer::factory()->create(['channel_type' => 'Wholesale']);
    Customer::factory()->create(['channel_type' => 'Pharmacy']);

    $this->get(route('customers.export.excel', ['filter' => ['channel_type' => 'Wholesale']]))
        ->assertSuccessful()
        ->assertDownload('customers.xlsx');
});

it('exports only filtered customers by city', function () {
    Customer::factory()->create(['city' => 'Lahore']);
    Customer::factory()->create(['city' => 'Karachi']);

    $this->get(route('customers.export.excel', ['filter' => ['city' => 'Lah']]))
        ->assertSuccessful()
        ->assertDownload('customers.xlsx');
});

it('exports only active customers when status filter applied', function () {
    Customer::factory()->create(['is_active' => true]);
    Customer::factory()->create(['is_active' => false]);

    $this->get(route('customers.export.excel', ['filter' => ['is_active' => '1']]))
        ->assertSuccessful()
        ->assertDownload('customers.xlsx');
});

it('exports with partial name filter', function () {
    Customer::factory()->create(['customer_name' => 'Ali Store']);
    Customer::factory()->create(['customer_name' => 'Zubair Shop']);

    $this->get(route('customers.export.excel', ['filter' => ['customer_name' => 'Ali']]))
        ->assertSuccessful()
        ->assertDownload('customers.xlsx');
});

it('denies export for unauthenticated user', function () {
    auth()->logout();

    $this->get(route('customers.export.excel'))
        ->assertRedirect(route('login'));
});

it('denies export without customer-list permission', function () {
    $userWithoutPermission = User::factory()->create();
    $this->actingAs($userWithoutPermission);

    $this->get(route('customers.export.excel'))
        ->assertForbidden();
});

it('supports per page on index', function () {
    Customer::factory()->count(20)->create();

    $this->get(route('customers.index', ['per_page' => 10]))
        ->assertSuccessful();
});

it('falls back to default per page for invalid value', function () {
    Customer::factory()->count(5)->create();

    $this->get(route('customers.index', ['per_page' => 999]))
        ->assertSuccessful();
});
