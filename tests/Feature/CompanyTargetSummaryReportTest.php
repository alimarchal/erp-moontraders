<?php

use App\Models\CompanyTarget;
use App\Models\CompanyTargetLine;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-company-target-summary', 'guard_name' => 'web']);

    $this->nestle = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan', 'disabled' => false]);
    $this->other = Supplier::factory()->create(['supplier_name' => 'Other Foods', 'disabled' => false]);

    $this->admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->admin->givePermissionTo('report-audit-company-target-summary');
    $this->companyUser = User::factory()->create(['is_super_admin' => 'No', 'supplier_id' => $this->nestle->id]);
    $this->companyUser->givePermissionTo('report-audit-company-target-summary');

    $this->tayyab = Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Salesman', 'name' => 'Tayyab Ali']);
    $this->atif = Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Salesman', 'name' => 'Atif Naqvi']);

    $sheet = CompanyTarget::factory()->create([
        'supplier_id' => $this->nestle->id,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-31',
    ]);
    CompanyTargetLine::create(['company_target_id' => $sheet->id, 'employee_id' => $this->tayyab->id, 'target_label' => 'Corporate', 'target_amount' => 6014521, 'sort_order' => 1]);
    CompanyTargetLine::create(['company_target_id' => $sheet->id, 'employee_id' => $this->atif->id, 'target_label' => 'Corporate', 'target_amount' => 0, 'sort_order' => 2]);

    $settle = fn (Employee $employee, string $date, float $sales, string $status = 'posted') => SalesSettlement::factory()->create([
        'supplier_id' => $employee->supplier_id,
        'employee_id' => $employee->id,
        'settlement_date' => $date,
        'total_sales_amount' => $sales,
        'status' => $status,
    ]);
    $settle($this->tayyab, '2026-10-05', 1000000);
    $settle($this->tayyab, '2026-10-12', 179281);
    $settle($this->tayyab, '2026-10-13', 5000000, 'draft');
    $settle($this->tayyab, '2026-09-30', 7000000);
    $settle($this->tayyab, '2026-10-20', 9000000);
    $settle($this->atif, '2026-10-06', 250000);

    $this->travelTo('2026-10-14 10:00:00');
    $this->query = ['supplier_id' => $this->nestle->id, 'from' => '2026-10-01', 'to' => '2026-10-14'];
});

it('requires the report permission', function () {
    $this->actingAs(User::factory()->create())->get(route('reports.company-target-summary.index'))->assertForbidden();
});

it('adds up posted sales inside the dates only, then works out remaining per day and percentage', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.company-target-summary.index', array_merge($this->query, ['to' => '2026-10-31'])));

    $summary = $response->viewData('summary');
    $tayyab = $summary['rows']->firstWhere('employee', 'Tayyab Ali');

    expect($summary['days_left'])->toBe(16)
        ->and($tayyab['total'])->toBe(6014521.0)
        ->and($tayyab['achieved'])->toBe(10179281.0)
        ->and($tayyab['remaining_per_day'])->toBe(0.0)
        ->and($tayyab['percentage'])->toBeGreaterThan(100);

    $response = $this->actingAs($this->admin)->get(route('reports.company-target-summary.index', $this->query));
    $tayyab = $response->viewData('summary')['rows']->firstWhere('employee', 'Tayyab Ali');

    expect($tayyab['achieved'])->toBe(1179281.0)
        ->and(round($tayyab['remaining_per_day']))->toBe(round((6014521 - 1179281) / 1))
        ->and(round($tayyab['percentage']))->toBe(20.0);
});

it('spreads what is left over the working days (Monday to Saturday) to the end date', function () {
    CompanyTargetLine::query()->where('employee_id', $this->tayyab->id)->update(['target_amount' => 20000000]);
    $response = $this->actingAs($this->admin)->get(route('reports.company-target-summary.index', array_merge($this->query, ['to' => '2026-10-31'])));

    $tayyab = $response->viewData('summary')['rows']->firstWhere('employee', 'Tayyab Ali');
    expect(round($tayyab['remaining_per_day']))->toBe(round((20000000 - 10179281) / 16));
});

it('shows zero percent for a zero target without dividing by zero', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.company-target-summary.index', $this->query));

    $atif = $response->viewData('summary')['rows']->firstWhere('employee', 'Atif Naqvi');
    expect($atif['total'])->toBe(0.0)->and($atif['achieved'])->toBe(250000.0)->and($atif['percentage'])->toBe(0.0);
    $response->assertOk()->assertSee('Nestle Pakistan', false)->assertSee('MOON TRADERS ERP');
});

it('lets a company user see only their own company', function () {
    $this->actingAs($this->companyUser)
        ->get(route('reports.company-target-summary.index', ['from' => '2026-10-01', 'to' => '2026-10-14']))
        ->assertOk()->assertSee('Tayyab Ali');

    $this->actingAs($this->companyUser)
        ->get(route('reports.company-target-summary.index', ['supplier_id' => $this->other->id]))
        ->assertForbidden();
});

it('downloads the Excel and PDF versions', function () {
    $this->actingAs($this->admin)->get(route('reports.company-target-summary.export.excel', $this->query))
        ->assertOk()->assertDownload();

    $pdf = $this->actingAs($this->admin)->get(route('reports.company-target-summary.export.pdf', $this->query));
    $pdf->assertOk()->assertDownload();
    expect($pdf->baseResponse->getContent() ?: $pdf->streamedContent())->toStartWith('%PDF');
});

it('downloads the PDF in landscape and shows charts on screen', function () {
    $pdf = $this->actingAs($this->admin)->get(route('reports.company-target-summary.export.pdf', $this->query + ['orientation' => 'landscape']));
    $pdf->assertOk()->assertDownload();
    expect($pdf->baseResponse->getContent() ?: $pdf->streamedContent())->toStartWith('%PDF');

    $this->actingAs($this->admin)->get(route('reports.company-target-summary.index', $this->query))
        ->assertOk()
        ->assertSee('<svg', false);
});

it('keeps the order of the target sheet and lists unplanned salesmen last', function () {
    $extra = Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Salesman', 'name' => 'Aaron Unplanned']);
    SalesSettlement::factory()->create([
        'supplier_id' => $this->nestle->id,
        'employee_id' => $extra->id,
        'settlement_date' => '2026-10-07',
        'total_sales_amount' => 100,
        'status' => 'posted',
    ]);

    $rows = $this->actingAs($this->admin)->get(route('reports.company-target-summary.index', $this->query))->viewData('summary')['rows'];

    expect($rows->pluck('employee')->all())->toBe(['Tayyab Ali', 'Atif Naqvi', 'Aaron Unplanned']);
});
