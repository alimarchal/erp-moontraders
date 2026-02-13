<?php

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['employee-salary-list', 'employee-salary-create', 'employee-salary-edit', 'employee-salary-delete'] as $perm) {
        Permission::create(['name' => $perm]);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['employee-salary-list', 'employee-salary-create', 'employee-salary-edit', 'employee-salary-delete']);
    $this->actingAs($this->user);
});

// ── Index ──────────────────────────────────────────────────────────

test('index page can be rendered', function () {
    $this->get(route('employee-salaries.index'))
        ->assertSuccessful()
        ->assertViewIs('employee-salaries.index')
        ->assertViewHas('items');
});

test('index displays employee salaries', function () {
    $employee = Employee::factory()->create();
    EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'basic_salary' => 50000,
    ]);

    $this->get(route('employee-salaries.index'))
        ->assertSuccessful()
        ->assertSee($employee->name);
});

test('index can filter by employee', function () {
    $employee1 = Employee::factory()->create();
    $employee2 = Employee::factory()->create();

    EmployeeSalary::factory()->create(['employee_id' => $employee1->id]);
    EmployeeSalary::factory()->create(['employee_id' => $employee2->id]);

    $response = $this->get(route('employee-salaries.index', ['filter' => ['employee_id' => $employee1->id]]));

    $response->assertSuccessful();
    expect($response->viewData('items'))->toHaveCount(1);
});

test('index can filter by active status', function () {
    $employee = Employee::factory()->create();
    EmployeeSalary::factory()->active()->create(['employee_id' => $employee->id]);
    EmployeeSalary::factory()->inactive()->create(['employee_id' => $employee->id]);

    $response = $this->get(route('employee-salaries.index', ['filter' => ['is_active' => 1]]));

    $response->assertSuccessful();
    expect($response->viewData('items'))->toHaveCount(1);
});

// ── Create ─────────────────────────────────────────────────────────

test('create page can be rendered', function () {
    $this->get(route('employee-salaries.create'))
        ->assertSuccessful()
        ->assertViewIs('employee-salaries.create')
        ->assertViewHas('employees')
        ->assertViewHas('suppliers');
});

// ── Store ──────────────────────────────────────────────────────────

test('employee salary can be created', function () {
    $employee = Employee::factory()->create();

    $data = [
        'employee_id' => $employee->id,
        'basic_salary' => 50000,
        'allowances' => 10000,
        'deductions' => 5000,
        'net_salary' => 55000,
        'effective_from' => now()->format('Y-m-d'),
        'is_active' => true,
        'notes' => 'Test salary record',
    ];

    $response = $this->post(route('employee-salaries.store'), $data);

    $response->assertRedirect(route('employee-salaries.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('employee_salaries', [
        'employee_id' => $employee->id,
        'basic_salary' => 50000,
        'net_salary' => 55000,
    ]);
});

test('store auto-populates supplier from employee', function () {
    $employee = Employee::factory()->create();

    $data = [
        'employee_id' => $employee->id,
        'basic_salary' => 30000,
        'allowances' => 5000,
        'deductions' => 2000,
        'net_salary' => 33000,
        'effective_from' => now()->format('Y-m-d'),
        'is_active' => true,
    ];

    $this->post(route('employee-salaries.store'), $data);

    $this->assertDatabaseHas('employee_salaries', [
        'employee_id' => $employee->id,
        'supplier_id' => $employee->supplier_id,
    ]);
});

test('store recalculates net salary', function () {
    $employee = Employee::factory()->create();

    $data = [
        'employee_id' => $employee->id,
        'basic_salary' => 40000,
        'allowances' => 10000,
        'deductions' => 5000,
        'net_salary' => 0,
        'effective_from' => now()->format('Y-m-d'),
        'is_active' => true,
    ];

    $this->post(route('employee-salaries.store'), $data);

    $this->assertDatabaseHas('employee_salaries', [
        'employee_id' => $employee->id,
        'net_salary' => 45000,
    ]);
});

test('store requires employee_id', function () {
    $this->post(route('employee-salaries.store'), [
        'basic_salary' => 50000,
        'allowances' => 0,
        'deductions' => 0,
        'net_salary' => 50000,
        'effective_from' => now()->format('Y-m-d'),
        'is_active' => true,
    ])->assertSessionHasErrors('employee_id');
});

test('store requires basic_salary', function () {
    $employee = Employee::factory()->create();

    $this->post(route('employee-salaries.store'), [
        'employee_id' => $employee->id,
        'allowances' => 0,
        'deductions' => 0,
        'net_salary' => 0,
        'effective_from' => now()->format('Y-m-d'),
        'is_active' => true,
    ])->assertSessionHasErrors('basic_salary');
});

test('store requires effective_from', function () {
    $employee = Employee::factory()->create();

    $this->post(route('employee-salaries.store'), [
        'employee_id' => $employee->id,
        'basic_salary' => 50000,
        'allowances' => 0,
        'deductions' => 0,
        'net_salary' => 50000,
        'is_active' => true,
    ])->assertSessionHasErrors('effective_from');
});

test('store validates effective_to must be after effective_from', function () {
    $employee = Employee::factory()->create();

    $this->post(route('employee-salaries.store'), [
        'employee_id' => $employee->id,
        'basic_salary' => 50000,
        'allowances' => 0,
        'deductions' => 0,
        'net_salary' => 50000,
        'effective_from' => '2026-06-01',
        'effective_to' => '2026-05-01',
        'is_active' => true,
    ])->assertSessionHasErrors('effective_to');
});

// ── Show ───────────────────────────────────────────────────────────

test('show page can be rendered', function () {
    $salary = EmployeeSalary::factory()->create();

    $this->get(route('employee-salaries.show', $salary))
        ->assertSuccessful()
        ->assertViewIs('employee-salaries.show')
        ->assertViewHas('employeeSalary');
});

// ── Edit ───────────────────────────────────────────────────────────

test('edit page can be rendered', function () {
    $salary = EmployeeSalary::factory()->create();

    $this->get(route('employee-salaries.edit', $salary))
        ->assertSuccessful()
        ->assertViewIs('employee-salaries.edit')
        ->assertViewHas('employeeSalary')
        ->assertViewHas('employees')
        ->assertViewHas('suppliers');
});

// ── Update ─────────────────────────────────────────────────────────

test('employee salary can be updated', function () {
    $employee = Employee::factory()->create();
    $salary = EmployeeSalary::factory()->create([
        'employee_id' => $employee->id,
        'basic_salary' => 50000,
    ]);

    $data = [
        'employee_id' => $employee->id,
        'basic_salary' => 60000,
        'allowances' => 10000,
        'deductions' => 5000,
        'net_salary' => 0,
        'effective_from' => now()->format('Y-m-d'),
        'is_active' => true,
    ];

    $response = $this->put(route('employee-salaries.update', $salary), $data);

    $response->assertRedirect(route('employee-salaries.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('employee_salaries', [
        'id' => $salary->id,
        'basic_salary' => 60000,
        'net_salary' => 65000,
    ]);
});

// ── Destroy ────────────────────────────────────────────────────────

test('employee salary can be deleted', function () {
    $salary = EmployeeSalary::factory()->create();

    $this->delete(route('employee-salaries.destroy', $salary))
        ->assertRedirect(route('employee-salaries.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('employee_salaries', ['id' => $salary->id]);
});

// ── Permissions ────────────────────────────────────────────────────

test('unauthenticated users cannot access employee salaries', function () {
    auth()->logout();

    $this->get(route('employee-salaries.index'))->assertRedirect(route('login'));
});

test('users without permission cannot access index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('employee-salaries.index'))->assertForbidden();
});

test('users without permission cannot store', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('employee-salaries.store'), [])->assertForbidden();
});

test('users without permission cannot update', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $salary = EmployeeSalary::factory()->create();

    $this->put(route('employee-salaries.update', $salary), [])->assertForbidden();
});

test('users without permission cannot delete', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $salary = EmployeeSalary::factory()->create();

    $this->delete(route('employee-salaries.destroy', $salary))->assertForbidden();
});
