<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-sales-credit-sales']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-sales-credit-sales');
    $this->actingAs($this->user);
});

it('can load the customer credit sales history page', function () {
    $response = $this->get(route('reports.credit-sales.customer-history'));

    $response->assertSuccessful();
    $response->assertSee('Customer Credit Sales History');
});

it('shows correct column headers', function () {
    $response = $this->get(route('reports.credit-sales.customer-history'));

    $response->assertSuccessful();
    $response->assertSee('Opening Balance');
    $response->assertSee('Credit Sales');
    $response->assertSee('Recoveries');
    $response->assertSee('Closing Balance');
});

it('displays summary cards', function () {
    $response = $this->get(route('reports.credit-sales.customer-history'));

    $response->assertSuccessful();
    $response->assertViewHas('totals');
    $response->assertViewHas('customers');
});

it('displays report purpose heading', function () {
    $response = $this->get(route('reports.credit-sales.customer-history'));

    $response->assertSuccessful();
    $response->assertSee('Customer-wise credit sales, recoveries and outstanding balance report');
});

it('can filter by customer name', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'filter' => ['customer_name' => 'Test'],
    ]));

    $response->assertSuccessful();
});

it('can filter by city', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'filter' => ['city' => 'Lahore'],
    ]));

    $response->assertSuccessful();
});

it('can filter by channel type', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'filter' => ['channel_type' => 'Wholesale'],
    ]));

    $response->assertSuccessful();
});

it('can filter by customer category', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'filter' => ['customer_category' => 'A'],
    ]));

    $response->assertSuccessful();
});

it('can filter by has balance', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'filter' => ['has_balance' => 'yes'],
    ]));

    $response->assertSuccessful();
});

it('can filter by salesman', function () {
    $employee = \App\Models\Employee::factory()->create();

    $response = $this->get(route('reports.credit-sales.customer-history', [
        'filter' => ['employee_id' => $employee->id],
    ]));

    $response->assertSuccessful();
});

it('can sort by closing balance', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'sort' => '-closing_balance',
    ]));

    $response->assertSuccessful();
});

it('can sort by credit sales', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'sort' => '-credit_sales_amount',
    ]));

    $response->assertSuccessful();
});

it('can sort by customer name', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'sort' => 'customer_name',
    ]));

    $response->assertSuccessful();
});

it('accepts all pagination option', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'per_page' => 'all',
    ]));

    $response->assertSuccessful();
});

it('accepts standard pagination values', function () {
    $response = $this->get(route('reports.credit-sales.customer-history', [
        'per_page' => 25,
    ]));

    $response->assertSuccessful();
});

it('passes required view data', function () {
    $response = $this->get(route('reports.credit-sales.customer-history'));

    $response->assertSuccessful();
    $response->assertViewHas('customers');
    $response->assertViewHas('totals');
    $response->assertViewHas('cities');
    $response->assertViewHas('subLocalities');
    $response->assertViewHas('channelTypes');
    $response->assertViewHas('employees');
});
