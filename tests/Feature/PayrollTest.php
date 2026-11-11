<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Models\PayrollSlipLine;
use App\Models\SalaryComponent;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\SalesSettlementRevertService;
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

it('pays every employee of Moon Traders in one run whichever supplier they work for, shown supplier by supplier', function () {
    $other = Supplier::factory()->create(['supplier_name' => 'Dalda Distribution']);
    $colleague = Employee::factory()->create(['supplier_id' => $other->id, 'is_active' => true, 'name' => 'Zafar Driver']);
    EmployeeSalary::create(['employee_id' => $colleague->id, 'salary_type' => 'fixed', 'basic_salary' => 25000, 'pay_method' => 'cash']);
    $headOffice = Employee::factory()->create(['supplier_id' => null, 'is_active' => true, 'name' => 'Head Office Accountant']);
    EmployeeSalary::create(['employee_id' => $headOffice->id, 'salary_type' => 'fixed', 'basic_salary' => 40000, 'pay_method' => 'bank']);
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($admin);

    $run = app(PayrollService::class)->createRun(null, Company::factory()->create(['company_name' => 'Moon Traders', 'abbr' => 'MT'])->id, $this->month, $admin);

    expect($run->slips)->toHaveCount(3)
        ->and($run->supplier_id)->toBeNull()
        ->and($run->ownerLabel())->toContain('(all employees)')
        ->and((float) $run->total_net)->toBe((float) ($this->days * 1000 + 6000 + 25000 + 40000));

    $this->get(route('payroll.show', $run))->assertOk()->assertSeeInOrder([$this->supplier->supplier_name, 'Imran Salesman'])->assertSee('Dalda Distribution')->assertSee('Moon Traders (head office)')->assertSee('Zafar Driver');
});

it('never lets one employee be paid twice for a month, between a run for all employees and a run for one supplier', function () {
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($admin);
    $service = app(PayrollService::class);
    $company = Company::factory()->create(['company_name' => 'Moon Traders', 'abbr' => 'MT']);

    $service->createRun($this->supplier->id, null, $this->month, $admin);
    expect(fn () => $service->createRun(null, $company->id, $this->month, $admin))->toThrow(RuntimeException::class, 'already been started');

    PayrollRun::query()->delete();
    $service->createRun(null, $company->id, $this->month, $admin);
    expect(fn () => $service->createRun($this->supplier->id, null, $this->month, $admin))->toThrow(RuntimeException::class, 'or for all employees');
    expect(fn () => $service->createRun(null, $company->id, $this->month, $admin))->toThrow(RuntimeException::class, 'already been started');
});

it('shows the all-employees payroll only to admins and holders of the all permission, not to a supplier\'s own users', function () {
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($admin);
    $run = app(PayrollService::class)->createRun(null, Company::factory()->create(['company_name' => 'Moon Traders', 'abbr' => 'MT'])->id, $this->month, $admin);

    $this->actingAs($this->checker)->get(route('payroll.show', $run))->assertNotFound();
    $this->actingAs($admin)->get(route('payroll.show', $run))->assertOk();
});

it('downloads the payroll as PDF and Excel, the slips as PDF, and the payroll list as PDF and Excel', function () {
    $run = ($this->build)();
    $this->actingAs($this->maker);

    foreach ([route('payroll.export.pdf', $run), route('payroll.slips.pdf', $run), route('payroll.slip.pdf', [$run, $run->slips->first()]), route('payroll.list.pdf')] as $url) {
        $response = $this->get($url)->assertOk();
        expect($response->headers->get('content-type'))->toContain('application/pdf');
    }

    foreach ([route('payroll.export.excel', $run), route('payroll.list.excel')] as $url) {
        expect($this->get($url)->assertOk()->headers->get('content-disposition'))->toContain('.xlsx');
    }
});

it('keeps the payroll downloads to people who may see that payroll', function () {
    $run = ($this->build)();
    $stranger = User::factory()->create(['supplier_id' => Supplier::factory()->create()->id])->givePermissionTo('payroll-list');
    $nobody = User::factory()->create();

    $this->actingAs($stranger)->get(route('payroll.export.pdf', $run))->assertNotFound();
    $this->actingAs($stranger)->get(route('payroll.slips.pdf', $run))->assertNotFound();
    $this->actingAs($stranger)->get(route('payroll.export.excel', $run))->assertNotFound();
    $this->actingAs($nobody)->get(route('payroll.list.pdf'))->assertForbidden();
    $this->actingAs($nobody)->get(route('payroll.list.excel'))->assertForbidden();
});

it('sets the salary of many employees at once and keeps what is left empty', function () {
    $second = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);
    $noSalary = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true]);
    $conveyance = SalaryComponent::where('code', 'conveyance')->firstOrFail();
    EmployeeSalary::create(['employee_id' => $second->id, 'salary_type' => 'basic', 'basic_salary' => 30000, 'pay_method' => 'cash']);

    $this->actingAs($this->maker)->post(route('payroll.salaries.bulk'), [
        'employee_ids' => [$this->employee->id, $second->id, $noSalary->id], 'pay_method' => 'bank', 'components' => [$conveyance->id => 1500, $this->houseRent->id => ''],
    ])->assertRedirect(route('payroll.salaries.index'))->assertSessionHas('error');

    expect($second->fresh()->salary->pay_method)->toBe('bank')->and((float) $second->fresh()->salary->basic_salary)->toBe(30000.0)
        ->and((float) $second->salaryComponents()->where('salary_component_id', $conveyance->id)->value('value'))->toBe(1500.0)
        ->and((float) $this->employee->salaryComponents()->where('salary_component_id', $this->houseRent->id)->value('value'))->toBe(6000.0)
        ->and($noSalary->fresh()->salary)->toBeNull();

    $this->actingAs($this->maker)->post(route('payroll.salaries.bulk'), ['employee_ids' => [$noSalary->id], 'salary_type' => 'fixed', 'basic_salary' => 20000])->assertSessionDoesntHaveErrors();
    expect($noSalary->fresh()->salary->salary_type)->toBe('fixed')->and((float) $noSalary->fresh()->salary->basic_salary)->toBe(20000.0);
});

it('does not let a bulk salary change reach another company\'s employees', function () {
    $foreign = Employee::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'is_active' => true]);

    $this->actingAs($this->maker)->post(route('payroll.salaries.bulk'), ['employee_ids' => [$foreign->id], 'basic_salary' => 1])->assertRedirect();
    $this->actingAs($this->maker)->post(route('payroll.salaries.bulk'), [])->assertSessionHasErrors('employee_ids');

    expect($foreign->fresh()->salary)->toBeNull();
});

it('filters the payroll list by payroll number, month range and status', function () {
    $run = ($this->build)();
    $this->actingAs($this->maker);

    $this->get(route('payroll.index', ['filter' => ['search' => $run->run_number]]))->assertOk()->assertSee($run->run_number);
    $this->get(route('payroll.index', ['filter' => ['month_from' => $this->month->copy()->addMonth()->format('Y-m')]]))->assertOk()->assertDontSee($run->run_number);
    $this->get(route('payroll.index', ['filter' => ['month_from' => $this->month->format('Y-m'), 'month_to' => $this->month->format('Y-m'), 'status' => 'draft']]))->assertOk()->assertSee($run->run_number);
    $this->get(route('payroll.index', ['filter' => ['status' => 'paid']]))->assertOk()->assertDontSee($run->run_number);
});

it('pays a salesman his settlement excess with the salary and takes his advance out of it (worked example)', function () {
    // Imran: basic = days x 1,000 and house rent 6,000. In the month his settlements came 1,000 and 500 over
    // (Rs 1,500 he handed in above what was due), and he took a 5,000 advance to be recovered in full.
    $excessAccount = seedSettlementExcessAccount();
    foreach ([1000, 500] as $i => $excess) {
        $settlement = SalesSettlement::factory()->create([
            'employee_id' => $this->employee->id, 'supplier_id' => $this->supplier->id, 'status' => 'posted', 'settlement_date' => $this->month->copy()->addDays(4 + $i),
        ]);
        $settlement->excessAmounts()->create(['amount' => $excess, 'description' => 'Cash Excess']);
    }
    $draft = SalesSettlement::factory()->create(['employee_id' => $this->employee->id, 'supplier_id' => $this->supplier->id, 'status' => 'draft', 'settlement_date' => $this->month->copy()->addDays(9)]);
    $draft->excessAmounts()->create(['amount' => 999, 'description' => 'not posted']);
    ($this->advance)(5000);

    $run = ($this->build)();
    $slip = $run->slips()->with('lines')->first();
    $earnings = $this->days * 1000 + 6000;

    expect($slip->lines->firstWhere('source', PayrollSlipLine::SOURCE_EXCESS)->label)->toBe('Settlement excess (2 settlements)')
        ->and((float) $slip->lines->firstWhere('source', PayrollSlipLine::SOURCE_EXCESS)->amount)->toBe(1500.0)
        ->and((float) $slip->total_earnings)->toBe((float) $earnings + 1500)
        ->and((float) $slip->total_deductions)->toBe(5000.0)
        ->and((float) $slip->net_pay)->toBe((float) $earnings + 1500 - 5000);

    $this->actingAs($this->maker)->post(route('payroll.submit', $run));
    $this->actingAs($this->checker)->post(route('payroll.approve', $run))->assertSessionHas('success');

    // Dr Salary (basic + rent), Dr 4250 1,500 (the excess leaves company income) / Cr 1131 5,000, Cr 2112 net pay.
    expect((float) DB::table('journal_entry_details')->where('chart_of_account_id', $excessAccount->id)->sum('debit'))->toBe(1500.0)
        ->and(($this->balanceOf)('5281'))->toBe((float) $earnings)
        ->and(($this->balanceOf)('1131'))->toBe(-5000.0)
        ->and(($this->balanceOf)('2112'))->toBe(-((float) $earnings + 1500 - 5000));

    $paidSettlement = SalesSettlement::where('status', 'posted')->first()->forceFill(['journal_entry_id' => DB::table('journal_entries')->value('id')]);
    expect(app(SalesSettlementRevertService::class)->performPreChecks($paidSettlement)['message'])->toContain('already in the approved payroll');
});
