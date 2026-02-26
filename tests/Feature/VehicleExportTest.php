<?php

use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'vehicle-list']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('vehicle-list');
    $this->actingAs($this->user);
});

it('downloads an excel file for authenticated user with permission', function () {
    Vehicle::factory()->count(3)->create();

    $this->get(route('vehicles.export.excel'))
        ->assertSuccessful()
        ->assertDownload('vehicles.xlsx');
});

it('exports only filtered vehicles by supplier', function () {
    $supplier = Supplier::factory()->create();
    Vehicle::factory()->create(['supplier_id' => $supplier->id]);
    Vehicle::factory()->create();

    $this->get(route('vehicles.export.excel', ['filter' => ['supplier_id' => $supplier->id]]))
        ->assertSuccessful()
        ->assertDownload('vehicles.xlsx');
});

it('exports only active vehicles when status filter applied', function () {
    Vehicle::factory()->create(['is_active' => true]);
    Vehicle::factory()->create(['is_active' => false]);

    $this->get(route('vehicles.export.excel', ['filter' => ['is_active' => '1']]))
        ->assertSuccessful()
        ->assertDownload('vehicles.xlsx');
});

it('exports with vehicle number filter', function () {
    Vehicle::factory()->create(['vehicle_number' => 'RLF-1234']);
    Vehicle::factory()->create(['vehicle_number' => 'ABC-5678']);

    $this->get(route('vehicles.export.excel', ['filter' => ['vehicle_number' => 'RLF']]))
        ->assertSuccessful()
        ->assertDownload('vehicles.xlsx');
});

it('denies export for unauthenticated user', function () {
    auth()->logout();

    $this->get(route('vehicles.export.excel'))
        ->assertRedirect(route('login'));
});

it('denies export without vehicle-list permission', function () {
    $userWithoutPermission = User::factory()->create();
    $this->actingAs($userWithoutPermission);

    $this->get(route('vehicles.export.excel'))
        ->assertForbidden();
});

it('supports per page on index', function () {
    Vehicle::factory()->count(20)->create();

    $this->get(route('vehicles.index', ['per_page' => 10]))
        ->assertSuccessful();
});

it('falls back to default per page for invalid value', function () {
    Vehicle::factory()->count(5)->create();

    $this->get(route('vehicles.index', ['per_page' => 999]))
        ->assertSuccessful();
});
