<?php

use App\Models\Employee;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['employee-list', 'employee-create', 'employee-edit', 'employee-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('denies index without employee-list permission', function () {
    $this->get(route('employees.index'))->assertForbidden();
});

it('allows index with employee-list permission', function () {
    $this->user->givePermissionTo('employee-list');
    $this->get(route('employees.index'))->assertSuccessful();
});

it('denies create without employee-create permission', function () {
    $this->get(route('employees.create'))->assertForbidden();
});

it('allows create with employee-create permission', function () {
    $this->user->givePermissionTo('employee-create');
    $this->get(route('employees.create'))->assertSuccessful();
});

it('denies store without employee-create permission', function () {
    $this->post(route('employees.store'), [])->assertForbidden();
});

it('denies show without employee-list permission', function () {
    $employee = Employee::factory()->create();
    $this->get(route('employees.show', $employee))->assertForbidden();
});

it('denies edit without employee-edit permission', function () {
    $employee = Employee::factory()->create();
    $this->get(route('employees.edit', $employee))->assertForbidden();
});

it('denies update without employee-edit permission', function () {
    $employee = Employee::factory()->create();
    $this->put(route('employees.update', $employee), [])->assertForbidden();
});

it('denies activate and deactivate without employee-edit permission', function () {
    $employee = Employee::factory()->create();
    $this->post(route('employees.bulk-update'), ['ids' => [$employee->id], 'action' => 'deactivate'])->assertForbidden();
});
