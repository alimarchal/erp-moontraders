<?php

use App\Models\Customer;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['is_super_admin' => 'Yes']);
});

/** @return array<string, mixed> */
function customerForm(array $override = []): array
{
    return $override + [
        'customer_code' => 'K00000700001', 'customer_name' => 'Code Test Store', 'channel_type' => 'General Store',
        'customer_category' => 'B', 'country' => 'Pakistan', 'is_active' => 1,
    ];
}

it('refuses a duplicate customer code when creating a customer, deleted customers included', function () {
    Customer::factory()->create(['customer_code' => 'K00000700001']);
    Customer::factory()->create(['customer_code' => 'K00000700002'])->delete();

    $this->actingAs($this->admin)->post(route('customers.store'), customerForm())->assertSessionHasErrors('customer_code');
    $this->post(route('customers.store'), customerForm(['customer_code' => 'k00000700002']))->assertSessionHasErrors('customer_code');
    $this->post(route('customers.store'), customerForm(['customer_code' => 'K00000700003']))->assertSessionHasNoErrors();

    expect(Customer::where('customer_code', 'K00000700003')->exists())->toBeTrue();
});

it('refuses a duplicate customer code when editing, but lets a customer keep or change to a free code', function () {
    $other = Customer::factory()->create(['customer_code' => 'K00000700001']);
    $customer = Customer::factory()->create(['customer_code' => 'K00000700009']);

    $this->actingAs($this->admin)->put(route('customers.update', $customer), customerForm(['customer_code' => $other->customer_code]))->assertSessionHasErrors('customer_code');
    $this->put(route('customers.update', $customer), customerForm(['customer_code' => 'K00000700009']))->assertSessionHasNoErrors();
    $this->put(route('customers.update', $customer), customerForm(['customer_code' => 'n00000700010']))->assertSessionHasNoErrors();

    expect($customer->fresh()->customer_code)->toBe('N00000700010');
});
