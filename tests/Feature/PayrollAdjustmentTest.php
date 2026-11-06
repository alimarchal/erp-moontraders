<?php

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollAdjustment;
use App\Models\SalaryComponent;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PayrollService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['payroll-list', 'payroll-create', 'payroll-approve', 'payroll-view-company', 'payroll-adjustment-manage', 'payroll-salary-manage'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->ledger = seedExpenseLedger();
    $this->supplier = Supplier::factory()->create();
    $this->month = now()->subMonthNoOverflow()->startOfMonth();

    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['payroll-list', 'payroll-create', 'payroll-adjustment-manage']);
    $this->checker = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['payroll-list', 'payroll-approve', 'payroll-view-company']);

    $this->employee = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true, 'name' => 'Sana Salesman']);
    EmployeeSalary::create(['employee_id' => $this->employee->id, 'salary_type' => 'basic', 'basic_salary' => 30000, 'pay_method' => 'cash']);
    $this->bonus = SalaryComponent::where('code', 'bonus')->firstOrFail();
    $this->commission = SalaryComponent::where('code', 'incentive')->firstOrFail();
    $this->fine = SalaryComponent::where('code', 'fine')->firstOrFail();

    $this->give = fn (array $extra = []) => $this->actingAs($this->maker)->post(route('payroll.adjustments.store'), $extra + [
        'month' => $this->month->format('Y-m'), 'salary_component_id' => $this->bonus->id, 'employee_ids' => [$this->employee->id], 'amount' => 5000, 'note' => 'Eid bonus',
    ]);
});

it('saves a bonus for a chosen employee and month', function () {
    ($this->give)()->assertRedirect()->assertSessionHas('success');

    $adjustment = PayrollAdjustment::first();
    expect($adjustment->employee_id)->toBe($this->employee->id)->and((float) $adjustment->amount)->toBe(5000.0)
        ->and($adjustment->period_month->format('Y-m'))->toBe($this->month->format('Y-m'))->and($adjustment->status)->toBe('pending');
});

it('gives the same commission to many employees at once', function () {
    $second = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);

    ($this->give)(['salary_component_id' => $this->commission->id, 'employee_ids' => [$this->employee->id, $second->id], 'amount' => 1200])->assertSessionHas('success');

    expect(PayrollAdjustment::count())->toBe(2)->and(PayrollAdjustment::pluck('amount')->map(fn ($v) => (float) $v)->unique()->all())->toBe([1200.0]);
});

it('puts the planned bonus on the slip when the month\'s payroll is built', function () {
    ($this->give)();
    $this->actingAs($this->maker);
    $run = app(PayrollService::class)->createRun($this->supplier->id, null, $this->month, $this->maker);
    $slip = $run->slips()->with('lines')->first();

    expect($slip->lines->firstWhere('source', 'adjustment')->label)->toBe('Bonus - Eid bonus')
        ->and((float) $slip->total_earnings)->toBe(35000.0)->and((float) $slip->net_pay)->toBe(35000.0);
});

it('shows a bonus on a draft slip at once, and removes it again', function () {
    $this->actingAs($this->maker);
    $run = app(PayrollService::class)->createRun($this->supplier->id, null, $this->month, $this->maker);
    expect((float) $run->slips()->first()->net_pay)->toBe(30000.0);

    ($this->give)();
    expect((float) $run->slips()->first()->fresh()->net_pay)->toBe(35000.0);

    $this->actingAs($this->maker)->delete(route('payroll.adjustments.destroy', PayrollAdjustment::first()))->assertSessionHas('success');
    expect((float) $run->slips()->first()->fresh()->net_pay)->toBe(30000.0)->and(PayrollAdjustment::count())->toBe(0);
});

it('takes a fine as a deduction', function () {
    ($this->give)(['salary_component_id' => $this->fine->id, 'amount' => 700, 'note' => 'Late']);
    $this->actingAs($this->maker);
    $run = app(PayrollService::class)->createRun($this->supplier->id, null, $this->month, $this->maker);

    expect((float) $run->slips()->first()->net_pay)->toBe(29300.0);
});

it('marks the bonus applied on approval and never takes it twice', function () {
    ($this->give)();
    $service = app(PayrollService::class);
    $this->actingAs($this->maker);
    $run = $service->createRun($this->supplier->id, null, $this->month, $this->maker);
    $service->submit($run, $this->maker);
    $service->approve($run->fresh(), $this->checker);

    $adjustment = PayrollAdjustment::first();
    expect($adjustment->status)->toBe('applied')->and($adjustment->applied_payroll_run_id)->toBe($run->id);

    $this->actingAs($this->maker)->delete(route('payroll.adjustments.destroy', $adjustment))->assertSessionHas('error');
    ($this->give)()->assertSessionHas('error');
    expect(PayrollAdjustment::count())->toBe(1);

    $next = $service->createRun($this->supplier->id, null, $this->month->copy()->addMonthNoOverflow(), $this->maker);
    expect($next->slips()->first()->lines()->where('source', 'adjustment')->count())->toBe(0);
});

it('only offers a bonus to employees of the user\'s own company', function () {
    $stranger = Employee::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'is_active' => true, 'name' => 'Other Co Person']);

    ($this->give)(['employee_ids' => [$stranger->id]])->assertForbidden();
    expect(PayrollAdjustment::count())->toBe(0);

    $names = collect($this->actingAs($this->maker)->getJson(route('payroll.adjustments.employees', ['q' => 'a']))->json('results'))->pluck('text')->join(' ');
    expect($names)->toContain('Sana Salesman')->not->toContain('Other Co Person');
});

it('keeps the bonus screen behind its permission and validates the amount', function () {
    $this->actingAs($this->checker)->get(route('payroll.adjustments.index'))->assertForbidden();
    $this->actingAs($this->maker)->get(route('payroll.adjustments.index'))->assertOk()->assertSee('Bonus');

    ($this->give)(['amount' => 0])->assertSessionHasErrors('amount');
    ($this->give)(['employee_ids' => []])->assertSessionHasErrors('employee_ids');
});

it('adds an employee who became payable after the draft payroll was built', function () {
    $this->actingAs($this->maker);
    $run = app(PayrollService::class)->createRun($this->supplier->id, null, $this->month, $this->maker);
    expect($run->slips)->toHaveCount(1);

    $joiner = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true, 'name' => 'New Joiner']);
    EmployeeSalary::create(['employee_id' => $joiner->id, 'salary_type' => 'fixed', 'basic_salary' => 20000, 'pay_method' => 'cash']);
    ($this->give)(['employee_ids' => [$joiner->id], 'amount' => 1000]);

    $this->actingAs($this->maker)->get(route('payroll.show', $run))->assertOk()->assertSee('New Joiner')->assertSee('Add them to this payroll');
    $this->actingAs($this->maker)->post(route('payroll.add-employees', $run))->assertSessionHas('success');

    $slip = $run->slips()->where('employee_id', $joiner->id)->first();
    expect($run->slips()->count())->toBe(2)->and((float) $slip->net_pay)->toBe(21000.0)
        ->and((float) $run->fresh()->total_net)->toBe(30000.0 + 21000.0);

    $this->actingAs($this->maker)->post(route('payroll.add-employees', $run))->assertSessionHas('success');
    expect($run->slips()->count())->toBe(2);
});

it('refuses to change a run that is no longer a draft when adding employees', function () {
    $service = app(PayrollService::class);
    $this->actingAs($this->maker);
    $run = $service->createRun($this->supplier->id, null, $this->month, $this->maker);
    $service->submit($run, $this->maker);

    $this->actingAs($this->maker)->post(route('payroll.add-employees', $run))->assertSessionHas('error');
});
