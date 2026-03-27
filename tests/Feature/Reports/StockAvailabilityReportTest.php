<?php

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::create(['name' => 'report-audit-stock-availability']);
});

it('redirects unauthenticated users', function () {
    $this->get(route('reports.stock-availability.index'))
        ->assertRedirect(route('login'));
});

it('denies access without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index'))
        ->assertForbidden();
});

it('loads stock availability report with permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-stock-availability');

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index'))
        ->assertOk()
        ->assertViewIs('reports.stock-availability.index');
});

it('loads report with supplier filter', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-stock-availability');
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index', ['supplier_id' => $supplier->id]))
        ->assertOk()
        ->assertViewHas('supplierId', (string) $supplier->id);
});

it('loads report with historical date', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-stock-availability');
    $pastDate = now()->subDays(30)->format('Y-m-d');

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index', ['as_of_date' => $pastDate]))
        ->assertOk()
        ->assertViewHas('isCurrentStock', false);
});

it('passes suppliers, warehouses, and categories to view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('report-audit-stock-availability');

    $this->actingAs($user)
        ->get(route('reports.stock-availability.index'))
        ->assertOk()
        ->assertViewHas('suppliers')
        ->assertViewHas('warehouses')
        ->assertViewHas('categories');
});
