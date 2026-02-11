<?php

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CustomerSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-view-audit']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-view-audit');
    $this->seed(CustomerSeeder::class);
});

test('creditors ledger index page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.creditors-ledger.index'))
        ->assertSuccessful();
});

test('creditors ledger customer ledger page loads for authenticated user', function () {
    $customer = Customer::first();

    $this->actingAs($this->user)
        ->get(route('reports.creditors-ledger.customer-ledger', $customer))
        ->assertSuccessful();
});

test('creditors ledger customer credit sales page loads for authenticated user', function () {
    $customer = Customer::first();

    $this->actingAs($this->user)
        ->get(route('reports.creditors-ledger.customer-credit-sales', $customer))
        ->assertSuccessful();
});

test('creditors ledger requires authentication', function () {
    $this->get(route('reports.creditors-ledger.index'))
        ->assertRedirect(route('login'));
});
