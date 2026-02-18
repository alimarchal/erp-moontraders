<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'report-sales-credit-sales']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-sales-credit-sales');
    $this->actingAs($this->user);
});

it('can load the salesman credit sales history page', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history'));

    $response->assertSuccessful();
    $response->assertSee('Salesman Credit Sales History');
});

it('defaults to current month date range', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history'));

    $response->assertSuccessful();
    $response->assertViewHas('startDate', now()->startOfMonth()->format('Y-m-d'));
    $response->assertViewHas('endDate', now()->format('Y-m-d'));
});

it('accepts custom date range filters', function () {
    $startDate = '2025-01-01';
    $endDate = '2025-01-31';

    $response = $this->get(route('reports.credit-sales.salesman-history', [
        'filter' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ],
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('startDate', $startDate);
    $response->assertViewHas('endDate', $endDate);
});

it('can sort by closing balance', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history', [
        'sort' => '-closing_balance',
    ]));

    $response->assertSuccessful();
});

it('can sort by credit sales amount', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history', [
        'sort' => '-credit_sales',
    ]));

    $response->assertSuccessful();
});

it('can sort by recoveries amount', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history', [
        'sort' => '-recoveries',
    ]));

    $response->assertSuccessful();
});

it('can sort by opening balance', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history', [
        'sort' => '-opening_balance',
    ]));

    $response->assertSuccessful();
});

it('can sort by name ascending', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history', [
        'sort' => 'name',
    ]));

    $response->assertSuccessful();
});

it('displays header with date range and filter info', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history'));

    $response->assertSuccessful();
    $response->assertSee('For the period');
    $response->assertSee(now()->startOfMonth()->format('d-M-Y'));
    $response->assertSee(now()->format('d-M-Y'));
});

it('shows correct column headers', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history'));

    $response->assertSuccessful();
    $response->assertSee('Opening Bal.');
    $response->assertSee('Credit Sales');
    $response->assertSee('Recoveries');
    $response->assertSee('Closing Bal.');
    $response->assertSee('Customers');
    $response->assertSee('Sales');
});

it('does not show supplier column', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history'));

    $response->assertSuccessful();
    // Ensure there's no Supplier column header in the table
    $response->assertDontSee('<th style="width: 120px;">Supplier</th>');
});

it('displays summary cards with correct labels', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history'));

    $response->assertSuccessful();
    $response->assertSee('Opening Balance (Total)');
    $response->assertSee('Credit Sales (Period)');
    $response->assertSee('Recoveries (Period)');
    $response->assertSee('Closing Balance (Total)');
});

it('accepts pagination parameter', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history', [
        'per_page' => 25,
    ]));

    $response->assertSuccessful();
});

it('accepts empty employee filter', function () {
    $response = $this->get(route('reports.credit-sales.salesman-history', [
        'filter' => [
            'employee_ids' => [],
        ],
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('selectedEmployeeNames', 'All Salesmen');
});

it('shows all active employees in the filter dropdown', function () {
    $activeEmployee = \App\Models\Employee::factory()->create([
        'name' => 'Active Salesman',
        'is_active' => true,
    ]);
    $inactiveEmployee = \App\Models\Employee::factory()->create([
        'name' => 'Inactive Salesman',
        'is_active' => false,
    ]);

    $response = $this->get(route('reports.credit-sales.salesman-history'));

    $response->assertSuccessful();
    // Check if the employees variable in the view contains the active employee
    // and does NOT contain the inactive employee
    $response->assertViewHas('employees', function ($employees) use ($activeEmployee, $inactiveEmployee) {
        return $employees->contains('id', $activeEmployee->id)
            && ! $employees->contains('id', $inactiveEmployee->id);
    });
});
