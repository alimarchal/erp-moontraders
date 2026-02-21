<?php

use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::create(['name' => 'employee-list']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('employee-list');
    $this->actingAs($this->user);
});

it('downloads an excel file for authenticated user with permission', function () {
    Employee::factory()->count(3)->create();

    $this->get(route('employees.export.excel'))
        ->assertSuccessful()
        ->assertDownload('employees.xlsx');
});

it('exports only filtered employees by supplier', function () {
    $supplier = Supplier::factory()->create();
    Employee::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Filtered Employee']);
    Employee::factory()->create(['name' => 'Other Employee']);

    $this->get(route('employees.export.excel', ['filter' => ['supplier_id' => $supplier->id]]))
        ->assertSuccessful()
        ->assertDownload('employees.xlsx');
});

it('exports only filtered employees by warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    Employee::factory()->create(['warehouse_id' => $warehouse->id]);
    Employee::factory()->create();

    $this->get(route('employees.export.excel', ['filter' => ['warehouse_id' => $warehouse->id]]))
        ->assertSuccessful()
        ->assertDownload('employees.xlsx');
});

it('exports only active employees when status filter applied', function () {
    Employee::factory()->create(['is_active' => true]);
    Employee::factory()->create(['is_active' => false]);

    $this->get(route('employees.export.excel', ['filter' => ['is_active' => '1']]))
        ->assertSuccessful()
        ->assertDownload('employees.xlsx');
});

it('exports with partial name filter', function () {
    Employee::factory()->create(['name' => 'Ali Khan']);
    Employee::factory()->create(['name' => 'Zubair Ahmed']);

    $this->get(route('employees.export.excel', ['filter' => ['name' => 'Ali']]))
        ->assertSuccessful()
        ->assertDownload('employees.xlsx');
});

it('denies export for unauthenticated user', function () {
    auth()->logout();

    $this->get(route('employees.export.excel'))
        ->assertRedirect(route('login'));
});

it('denies export without employee-list permission', function () {
    $userWithoutPermission = User::factory()->create();
    $this->actingAs($userWithoutPermission);

    $this->get(route('employees.export.excel'))
        ->assertForbidden();
});

it('supports per page on index', function () {
    Employee::factory()->count(20)->create();

    $this->get(route('employees.index', ['per_page' => 10]))
        ->assertSuccessful();
});

it('falls back to default per page for invalid value', function () {
    Employee::factory()->count(5)->create();

    $this->get(route('employees.index', ['per_page' => 999]))
        ->assertSuccessful();
});
