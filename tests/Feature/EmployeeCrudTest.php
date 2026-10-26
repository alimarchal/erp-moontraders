<?php

use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['list', 'create', 'edit', 'delete'] as $action) {
        Permission::findOrCreate("employee-{$action}");
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['employee-list', 'employee-create', 'employee-edit', 'employee-delete']);
    $this->actingAs($this->user);
});

it('renders the list, search and status tab', function () {
    Employee::factory()->create(['name' => 'Findable Person', 'is_active' => true]);
    Employee::factory()->create(['name' => 'Other Person', 'is_active' => false]);

    $this->get(route('employees.index', ['filter' => ['search' => 'findable']]))
        ->assertOk()
        ->assertSee('Findable Person')
        ->assertDontSee('Other Person');

    $this->get(route('employees.index', ['filter' => ['is_active' => '0']]))
        ->assertOk()
        ->assertSee('Other Person')
        ->assertDontSee('Findable Person');
});

it('renders the add, edit and view screens', function () {
    $employee = Employee::factory()->create();

    $this->get(route('employees.create'))->assertOk()->assertSee('Add New Employee');
    $this->get(route('employees.edit', $employee))->assertOk()->assertSee($employee->employee_code);
    $this->get(route('employees.show', $employee))->assertOk()->assertSee($employee->name);
});

it('creates and updates an employee', function () {
    $supplier = Supplier::factory()->create();

    $this->post(route('employees.store'), [
        'employee_code' => 'emp-900',
        'name' => 'New Hire',
        'supplier_id' => $supplier->id,
        'is_active' => '1',
    ])->assertRedirect(route('employees.index'));

    $employee = Employee::where('employee_code', 'EMP-900')->firstOrFail();
    expect($employee->supplier_id)->toBe($supplier->id);

    $this->put(route('employees.update', $employee), [
        'employee_code' => 'EMP-900',
        'name' => 'Renamed',
        'is_active' => '0',
    ])->assertRedirect(route('employees.index'));

    expect($employee->fresh())->name->toBe('Renamed')->is_active->toBeFalse();
});

it('deletes an employee', function () {
    $employee = Employee::factory()->create();

    $this->delete(route('employees.destroy', $employee))->assertRedirect(route('employees.index'));

    expect(Employee::find($employee->id))->toBeNull();
});

it('bulk activates, deactivates and deletes', function () {
    $employees = Employee::factory()->count(2)->create(['is_active' => true]);
    $ids = $employees->pluck('id')->all();

    $this->post(route('employees.bulk-update'), ['ids' => $ids, 'action' => 'deactivate'])->assertRedirect();
    expect(Employee::whereIn('id', $ids)->where('is_active', true)->count())->toBe(0);

    $this->post(route('employees.bulk-update'), ['ids' => $ids, 'action' => 'activate'])->assertRedirect();
    expect(Employee::whereIn('id', $ids)->where('is_active', true)->count())->toBe(2);

    $this->post(route('employees.bulk-update'), ['ids' => $ids, 'action' => 'delete'])->assertRedirect();
    expect(Employee::whereIn('id', $ids)->count())->toBe(0);
});

it('blocks bulk delete without the delete permission', function () {
    $this->user->revokePermissionTo('employee-delete');
    $employee = Employee::factory()->create();

    $this->post(route('employees.bulk-update'), ['ids' => [$employee->id], 'action' => 'delete'])->assertForbidden();
});
