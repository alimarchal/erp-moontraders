<?php

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollAdjustment;
use App\Models\SalaryComponent;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PayrollExcelService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['payroll-list', 'payroll-salary-manage', 'payroll-adjustment-manage', 'payroll-import-excel'] as $name) {
        Permission::findOrCreate($name);
    }

    seedExpenseLedger();
    $this->supplier = Supplier::factory()->create();
    $this->admin = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['payroll-list', 'payroll-salary-manage', 'payroll-adjustment-manage', 'payroll-import-excel']);
    $this->ali = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true, 'name' => 'Ali Driver', 'employee_code' => 'EMP-101']);
    $this->sara = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'is_active' => true, 'name' => 'Sara Clerk', 'employee_code' => 'EMP-102']);
    EmployeeSalary::create(['employee_id' => $this->sara->id, 'salary_type' => 'basic', 'basic_salary' => 25000, 'pay_method' => 'cash']);
    $this->houseRent = SalaryComponent::where('code', 'house_rent')->firstOrFail();
    $this->medical = SalaryComponent::where('code', 'medical')->firstOrFail();
    $this->conveyance = SalaryComponent::where('code', 'conveyance')->firstOrFail();
    $this->sara->salaryComponents()->create(['salary_component_id' => $this->conveyance->id, 'value' => 1000]);

    /** Fill a downloaded template: $cells is [sheet title => [cell => value]]. */
    $this->upload = function (string $kind, array $cells): UploadedFile {
        $response = $this->actingAs($this->admin)->get(route('payroll.import.template', $kind));
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);

        foreach ($cells as $sheet => $values) {
            foreach ($values as $cell => $value) {
                $book->getSheetByName($sheet)->setCellValue($cell, $value);
            }
        }

        (new Xlsx($book))->save($path);

        return new UploadedFile($path, $kind.'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    };
});

it('downloads a salary template filled with employees and drop-down lists', function () {
    $response = $this->actingAs($this->admin)->get(route('payroll.import.template', 'salaries'))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $book = IOFactory::load($path);

    $salaries = $book->getSheetByName(PayrollExcelService::SHEET_SALARIES);
    expect($salaries->getCell('A1')->getValue())->toBe('Employee Code')
        ->and(collect($salaries->toArray())->pluck(0)->all())->toContain('EMP-101', 'EMP-102')
        ->and($salaries->getDataValidation('D2')->getFormula1())->toBe('"Basic,Fixed"')
        ->and($book->getSheetByName(PayrollExcelService::SHEET_COMPONENTS)->getCell('C2')->getValue())->toBe('Conveyance')
        ->and(collect($book->getSheetByName(PayrollExcelService::SHEET_LISTS)->toArray())->pluck(0)->all())->toContain('House Rent');
});

it('imports basic salaries and earnings and deductions by employee code', function () {
    $file = ($this->upload)('salaries', [
        PayrollExcelService::SHEET_SALARIES => ['D2' => 'Fixed', 'E2' => 'Bank', 'F2' => 40000],
        PayrollExcelService::SHEET_COMPONENTS => [
            'A3' => 'EMP-101', 'C3' => 'House Rent', 'E3' => '% of basic', 'F3' => 40,
            'A4' => 'EMP-101', 'C4' => 'Medical Allowance', 'E4' => '% of another allowance', 'F4' => 10, 'G4' => 'House Rent',
        ],
    ]);
    // Row 2 of Salaries is the first employee by name: Ali Driver.
    $this->actingAs($this->admin)->post(route('payroll.import.store', 'salaries'), ['file' => $file, 'replace' => 1])
        ->assertRedirect(route('payroll.index', ['tab' => 'salaries']))->assertSessionHas('success');

    $ali = $this->ali->fresh(['salary', 'salaryComponents']);
    expect($ali->salary->salary_type)->toBe('fixed')->and($ali->salary->pay_method)->toBe('bank')->and((float) $ali->salary->basic_salary)->toBe(40000.0)
        ->and($ali->salaryComponents->firstWhere('salary_component_id', $this->houseRent->id)->calculation)->toBe('percent_of_basic')
        ->and($ali->salaryComponents->firstWhere('salary_component_id', $this->medical->id)->base_component_id)->toBe($this->houseRent->id)
        ->and((float) $this->sara->fresh()->salary->basic_salary)->toBe(25000.0)
        ->and((float) $this->sara->salaryComponents()->where('salary_component_id', $this->conveyance->id)->value('value'))->toBe(1000.0);
});

it('saves nothing when any row of the salary import has a mistake', function () {
    $file = ($this->upload)('salaries', [
        PayrollExcelService::SHEET_SALARIES => ['F2' => 40000, 'A3' => 'EMP-999', 'F3' => 1000],
        PayrollExcelService::SHEET_COMPONENTS => ['A3' => 'EMP-101', 'C3' => 'No Such Thing', 'F3' => 5],
    ]);

    $this->actingAs($this->admin)->post(route('payroll.import.store', 'salaries'), ['file' => $file])
        ->assertSessionHas('error')->assertSessionHas('import_errors', fn (array $errors) => count($errors) === 2
            && str_contains($errors[0], 'EMP-999') && str_contains($errors[1], 'No Such Thing'));

    expect($this->ali->fresh()->salary)->toBeNull()->and((float) $this->sara->fresh()->salary->basic_salary)->toBe(25000.0);
});

it('imports incentives one time and every month with their narration', function () {
    $month = now()->format('Y-m');
    $commission = SalaryComponent::where('code', 'incentive')->firstOrFail();
    $fine = SalaryComponent::where('code', 'fine')->firstOrFail();
    $file = ($this->upload)('adjustments', [PayrollExcelService::SHEET_ADJUSTMENTS => [
        'D2' => $commission->name, 'F2' => 3000, 'G2' => 'Every month', 'I2' => 'Route target',
        'D3' => $fine->name, 'F3' => 500, 'G3' => 'One time', 'I3' => 'Late arrival',
    ]]);

    $this->actingAs($this->admin)->post(route('payroll.import.store', 'adjustments'), ['file' => $file])->assertSessionHas('success');

    $recurring = PayrollAdjustment::where('employee_id', $this->ali->id)->first();
    $once = PayrollAdjustment::where('employee_id', $this->sara->id)->first();
    expect(PayrollAdjustment::count())->toBe(2)
        ->and($recurring->is_recurring)->toBeTrue()->and($recurring->note)->toBe('Route target')->and($recurring->period_month->format('Y-m'))->toBe($month)
        ->and($once->is_recurring)->toBeFalse()->and((float) $once->amount)->toBe(500.0)->and($once->salary_component_id)->toBe($fine->id);
});

it('refuses an incentive import with an unknown category or month and saves none of it', function () {
    $commission = SalaryComponent::where('code', 'incentive')->firstOrFail();
    $file = ($this->upload)('adjustments', [PayrollExcelService::SHEET_ADJUSTMENTS => [
        'D2' => $commission->name, 'F2' => 3000,
        'C3' => 'someday', 'D3' => $commission->name, 'F3' => 100,
    ]]);

    $this->actingAs($this->admin)->post(route('payroll.import.store', 'adjustments'), ['file' => $file])
        ->assertSessionHas('import_errors', fn (array $errors) => count($errors) === 1 && str_contains($errors[0], 'Row 3') && str_contains($errors[0], 'Month'));
    expect(PayrollAdjustment::count())->toBe(0);
});

it('keeps the Excel import to holders of payroll-import-excel and to their own employees', function () {
    $manager = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['payroll-list', 'payroll-salary-manage', 'payroll-adjustment-manage']);
    $this->actingAs($manager)->get(route('payroll.import.template', 'salaries'))->assertForbidden();
    $this->actingAs($manager)->post(route('payroll.import.store', 'adjustments'))->assertForbidden();
    $this->actingAs($manager)->get(route('payroll.index', ['tab' => 'salaries']))->assertOk()->assertDontSee('Import Excel');
    $this->actingAs($this->admin)->get(route('payroll.index', ['tab' => 'salaries']))->assertOk()->assertSee('Import Excel');

    Employee::factory()->create(['supplier_id' => Supplier::factory()->create()->id, 'is_active' => true, 'employee_code' => 'OTHER-1']);
    $file = ($this->upload)('salaries', [PayrollExcelService::SHEET_SALARIES => ['A2' => 'OTHER-1', 'F2' => 9000]]);
    $this->actingAs($this->admin)->post(route('payroll.import.store', 'salaries'), ['file' => $file])
        ->assertSessionHas('import_errors', fn (array $errors) => str_contains($errors[0], 'OTHER-1'));
});

it('sets a different basic salary for each ticked employee in one save', function () {
    $this->actingAs($this->admin)->post(route('payroll.salaries.bulk'), [
        'employee_ids' => [$this->ali->id, $this->sara->id], 'salary_type' => 'basic', 'basics' => [$this->ali->id => 31000, $this->sara->id => 27500],
    ])->assertSessionHas('success')->assertSessionDoesntHaveErrors();

    expect((float) $this->ali->fresh()->salary->basic_salary)->toBe(31000.0)->and((float) $this->sara->fresh()->salary->basic_salary)->toBe(27500.0);
});
