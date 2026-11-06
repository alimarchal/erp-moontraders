<?php

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Models\PayrollSlipLine;
use App\Models\SalaryComponent;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['payroll-list', 'payroll-create', 'payroll-approve', 'payroll-pay', 'payroll-view-company', 'payroll-view-all', 'payroll-salary-manage', 'report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->ledger = seedExpenseLedger();
    $this->supplier = Supplier::factory()->create();
    $this->month = now()->subMonthNoOverflow()->startOfMonth();
    $this->days = $this->month->daysInMonth;

    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['payroll-list', 'payroll-create', 'payroll-salary-manage']);
    $this->checker = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['payroll-list', 'payroll-approve', 'payroll-pay', 'payroll-view-company', 'report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies']);

    // Basic is 1,000 a day whatever the length of the month, so an absent day costs exactly 1,000.
    $this->employee = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true, 'name' => 'Imran Salesman']);
    EmployeeSalary::create(['employee_id' => $this->employee->id, 'salary_type' => 'basic', 'basic_salary' => $this->days * 1000, 'pay_method' => 'cash']);
    $this->houseRent = SalaryComponent::where('code', 'house_rent')->firstOrFail();
    $this->employee->salaryComponents()->create(['salary_component_id' => $this->houseRent->id, 'value' => 6000]);

    $this->build = function () {
        $this->actingAs($this->maker);

        return app(PayrollService::class)->createRun($this->supplier->id, null, $this->month, $this->maker);
    };
    $this->balanceOf = fn (string $code) => (float) DB::table('journal_entry_details')->where('chart_of_account_id', $this->ledger[$code]->id)->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as balance')->value('balance');
    $this->advance = fn (float $amount, ?float $instalment = null) => EmployeeAdvance::create([
        'employee_id' => $this->employee->id, 'supplier_id' => $this->supplier->id, 'advance_date' => $this->month->copy()->subDays(3),
        'amount' => $amount, 'installment_amount' => $instalment, 'recovered_amount' => 0, 'status' => 'open',
    ]);
});

it('saves an employee\'s salary type, basic salary and allowances', function () {
    $other = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);
    $conveyance = SalaryComponent::where('code', 'conveyance')->firstOrFail();

    $this->actingAs($this->maker)->put(route('payroll.salaries.update', $other), [
        'salary_type' => 'fixed', 'basic_salary' => 45000, 'pay_method' => 'bank', 'components' => [$conveyance->id => 2500, $this->houseRent->id => 0],
    ])->assertRedirect(route('payroll.salaries.index'));

    $other->refresh();
    expect($other->salary->salary_type)->toBe('fixed')->and((float) $other->salary->basic_salary)->toBe(45000.0)->and($other->salary->pay_method)->toBe('bank')
        ->and($other->salaryComponents)->toHaveCount(1)->and((float) $other->salaryComponents->first()->value)->toBe(2500.0);
});

it('keeps salary set-up to the people with the manage permission and their own company', function () {
    $stranger = Employee::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'is_active' => true]);

    $this->actingAs($this->checker)->get(route('payroll.salaries.index'))->assertForbidden();
    $this->actingAs($this->maker)->get(route('payroll.salaries.edit', $stranger))->assertNotFound();
    $this->actingAs($this->maker)->get(route('payroll.salaries.edit', $this->employee))->assertOk()->assertSee('Imran Salesman');
});

it('builds every slip in one go: basic, allowance, absent days and the advance instalment', function () {
    ($this->advance)(10000, 4000);
    $run = ($this->build)();
    $slip = $run->slips()->with('lines')->first();

    $this->actingAs($this->maker);
    app(PayrollService::class)->setAbsentDays($slip->setRelation('run', $run), 3);
    $slip->refresh()->load('lines');

    $earnings = $this->days * 1000 + 6000;
    expect($slip->lines->pluck('amount', 'label')->map(fn ($v) => (float) $v)->all())->toMatchArray([
        'Basic salary' => (float) ($this->days * 1000), 'House Rent' => 6000.0, 'Absent 3 day(s)' => 3000.0, 'Advance of '.$this->month->copy()->subDays(3)->format('d M Y') => 4000.0,
    ])
        ->and((float) $slip->total_earnings)->toBe((float) $earnings)
        ->and((float) $slip->total_deductions)->toBe(7000.0)
        ->and((float) $slip->net_pay)->toBe((float) ($earnings - 7000))
        ->and((float) $run->fresh()->total_net)->toBe((float) ($earnings - 7000));
});

it('never cuts a fixed salary for absent days', function () {
    $this->employee->salary->update(['salary_type' => 'fixed']);
    $run = ($this->build)();
    $slip = $run->slips()->first();

    app(PayrollService::class)->setAbsentDays($slip->setRelation('run', $run), 5);

    expect($slip->fresh()->lines->where('source', 'absent'))->toHaveCount(0)->and((float) $slip->fresh()->total_deductions)->toBe(0.0);
});

it('takes the whole balance when an advance has no instalment, but never more than the pay', function () {
    ($this->advance)(9000000);
    $run = ($this->build)();
    $slip = $run->slips()->first();

    expect((float) $slip->net_pay)->toBe(0.0)->and((float) $slip->total_deductions)->toBe((float) ($this->days * 1000 + 6000));
});

it('adds and removes a one-off line, and refuses to remove an automatic one', function () {
    $run = ($this->build)();
    $slip = $run->slips()->first();
    $overtime = SalaryComponent::where('code', 'overtime')->firstOrFail();
    $before = (float) $slip->net_pay;

    $this->actingAs($this->maker)->post(route('payroll.lines.store', [$run, $slip]), ['salary_component_id' => $overtime->id, 'amount' => 1500, 'label' => 'Sunday work'])->assertSessionHas('success');
    expect((float) $slip->fresh()->net_pay)->toBe($before + 1500);

    $manual = $slip->lines()->where('source', 'manual')->first();
    $this->actingAs($this->maker)->delete(route('payroll.lines.destroy', [$run, $manual]))->assertSessionHas('success');
    expect((float) $slip->fresh()->net_pay)->toBe($before);

    $basic = $slip->lines()->where('source', 'basic')->first();
    $this->actingAs($this->maker)->delete(route('payroll.lines.destroy', [$run, $basic]))->assertSessionHas('error');
});

it('starts one run per company and month, and only for employees with a salary', function () {
    Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);
    $run = ($this->build)();

    expect($run->slips)->toHaveCount(1);
    expect(fn () => app(PayrollService::class)->createRun($this->supplier->id, null, $this->month, $this->maker))->toThrow(RuntimeException::class, 'already been started');
    expect(fn () => app(PayrollService::class)->createRun(Supplier::factory()->create()->id, null, $this->month, $this->maker))->toThrow(RuntimeException::class, 'No active employee');
});

it('blocks a negative net pay from approval', function () {
    $run = ($this->build)();
    $slip = $run->slips()->first();
    $fine = SalaryComponent::where('code', 'fine')->firstOrFail();
    app(PayrollService::class)->addManualLine($slip->setRelation('run', $run), $fine, (float) $slip->net_pay + 500);

    $this->actingAs($this->maker)->post(route('payroll.submit', $run))->assertSessionHas('error');
    expect($run->fresh()->status)->toBe('draft');
});

it('posts a balanced ledger entry on approval and records the advance recovery', function () {
    $advance = ($this->advance)(10000, 4000);
    $run = ($this->build)();
    $this->actingAs($this->maker)->post(route('payroll.submit', $run))->assertSessionHas('success');

    $this->actingAs($this->maker->givePermissionTo(['payroll-approve', 'payroll-view-company']))->post(route('payroll.approve', $run))->assertSessionHas('error');
    $this->actingAs($this->checker)->post(route('payroll.approve', $run))->assertSessionHas('success');

    $earnings = (float) ($this->days * 1000 + 6000);
    expect($run->fresh()->status)->toBe('approved')
        ->and(($this->balanceOf)('5281'))->toBe($earnings)
        ->and(($this->balanceOf)('1131'))->toBe(-4000.0)
        ->and(($this->balanceOf)('2112'))->toBe(-($earnings - 4000))
        ->and($advance->fresh()->balance())->toBe(6000.0)
        ->and($advance->recoveries)->toHaveCount(1);
});

it('recovers an advance across months until it is cleared', function () {
    $advance = ($this->advance)(5000, 3000);
    $service = app(PayrollService::class);

    foreach ([$this->month, $this->month->copy()->addMonthNoOverflow()] as $i => $month) {
        $this->actingAs($this->maker);
        $run = $service->createRun($this->supplier->id, null, $month, $this->maker);
        $service->submit($run, $this->maker);
        $service->approve($run->fresh(), $this->checker);
    }

    expect($advance->fresh()->status)->toBe('recovered')->and((float) $advance->fresh()->recovered_amount)->toBe(5000.0)
        ->and($advance->recoveries()->pluck('amount')->map(fn ($v) => (float) $v)->all())->toBe([3000.0, 2000.0]);
});

it('rolls the approval back completely when the ledger cannot be posted', function () {
    $advance = ($this->advance)(10000, 4000);
    $run = ($this->build)();
    $this->actingAs($this->maker)->post(route('payroll.submit', $run));
    $this->ledger['2112']->delete();

    $this->actingAs($this->checker)->post(route('payroll.approve', $run))->assertSessionHas('error');

    expect($run->fresh()->status)->toBe('pending')->and($advance->fresh()->recovered_amount)->toEqual(0)->and($advance->recoveries)->toHaveCount(0);
});

it('pays the salaries in cash into the day\'s cash report, and by bank without touching cash', function () {
    $run = ($this->build)();
    $service = app(PayrollService::class);
    $service->submit($run, $this->maker);
    $service->approve($run->fresh(), $this->checker);
    $today = now()->toDateString();

    $this->actingAs($this->checker)->post(route('payroll.pay', $run), ['method' => 'cash', 'paid_on' => $today])->assertSessionHas('success');

    $net = (float) $run->fresh()->total_net;
    $tables = $this->actingAs($this->checker)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => $this->supplier->id, 'date' => $today]))->viewData('tables');
    $summary = collect($tables['summary']['rows'])->mapWithKeys(fn ($row) => [$row[0] => $row[1]]);

    expect($run->fresh()->status)->toBe('paid')
        ->and(($this->balanceOf)('2112'))->toBe(0.0)
        ->and(($this->balanceOf)('1121'))->toBe(-$net)
        ->and($summary['Less: salaries paid (cash)'])->toBe(number_format($net, 2))
        ->and(collect($tables['expenses']['rows'])->pluck(1)->all())->toContain('Salaries - '.$this->month->format('M Y'));

    $this->actingAs($this->checker)->post(route('payroll.pay', $run), ['method' => 'cash', 'paid_on' => $today])->assertSessionHas('error');
});

it('shows each user only the payroll they made, their company\'s, or everything', function () {
    $run = ($this->build)();
    $stranger = User::factory()->create(['supplier_id' => Supplier::factory()->create()->id])->givePermissionTo(['payroll-list', 'payroll-view-company']);

    $this->actingAs($this->maker)->get(route('payroll.show', $run))->assertOk()->assertSee('Imran Salesman');
    $this->actingAs($this->checker)->get(route('payroll.show', $run))->assertOk();
    $this->actingAs($stranger)->get(route('payroll.show', $run))->assertNotFound();
    $this->actingAs($stranger)->get(route('payroll.index'))->assertOk()->assertDontSee($run->run_number);
    $this->actingAs($stranger->givePermissionTo('payroll-view-all'))->get(route('payroll.show', $run))->assertOk();
});

it('only lets the maker change the run and prints slips with earnings and deductions side by side', function () {
    $run = ($this->build)();
    $slip = $run->slips()->first();

    $this->actingAs($this->checker)->post(route('payroll.recalculate', $run))->assertForbidden();
    $this->actingAs($this->maker)->get(route('payroll.slips.show', [$run, $slip]))->assertOk()->assertSee('Salary slip')->assertSee('Earnings')->assertSee('Deductions')->assertSee('Net pay');
    $this->actingAs($this->maker)->get(route('payroll.print', $run))->assertOk()->assertSee('Imran Salesman');
    $this->actingAs($this->maker)->get(route('payroll.help'))->assertOk()->assertSee('How payroll works');
});

it('lets an allowance be added and switched off in the component list', function () {
    $this->actingAs($this->maker)->post(route('payroll.components.store'), ['name' => 'Night Shift', 'type' => 'earning', 'calculation' => 'fixed'])->assertSessionHas('success');
    $component = SalaryComponent::where('name', 'Night Shift')->firstOrFail();

    $this->actingAs($this->maker)->put(route('payroll.components.update', $component), ['name' => 'Night Shift', 'type' => 'earning', 'calculation' => 'fixed'])->assertSessionHas('success');
    expect($component->fresh()->is_active)->toBeFalse();
});

it('has a recorded line source for every automatic slip line', function () {
    $run = ($this->build)();

    expect($run->slips->first()->lines->pluck('source')->unique()->sort()->values()->all())->toBe([PayrollSlipLine::SOURCE_ALLOWANCE, PayrollSlipLine::SOURCE_BASIC])
        ->and(PayrollSlip::count())->toBe(1)
        ->and(Carbon::parse($run->period_month)->isSameMonth($this->month))->toBeTrue()
        ->and(PayrollRun::count())->toBe(1);
});
