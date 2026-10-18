<?php

use App\Models\CompanyTarget;
use App\Models\CompanyTargetLine;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['list', 'create', 'edit', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "company-target-{$action}", 'guard_name' => 'web']);
    }

    $this->nestle = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan', 'disabled' => false]);
    $this->other = Supplier::factory()->create(['supplier_name' => 'Other Foods', 'disabled' => false]);

    $this->admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->admin->givePermissionTo(['company-target-list', 'company-target-create', 'company-target-edit', 'company-target-delete']);

    $this->companyUser = User::factory()->create(['is_super_admin' => 'No', 'supplier_id' => $this->nestle->id]);
    $this->companyUser->givePermissionTo(['company-target-list', 'company-target-create', 'company-target-edit', 'company-target-delete']);

    $this->tayyab = Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Salesman', 'is_active' => true, 'name' => 'Tayyab Ali']);
    $this->atif = Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Salesman', 'is_active' => true, 'name' => 'Atif Naqvi']);

    $this->payload = fn (array $overrides = []) => array_merge([
        'supplier_id' => $this->nestle->id,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-31',
        'lines' => [
            ['employee_id' => $this->tayyab->id, 'target_label' => 'Corporate', 'target_amount' => '6014521'],
            ['employee_id' => $this->atif->id, 'target_label' => '', 'target_amount' => '0'],
        ],
    ], $overrides);
});

it('requires the company-target permissions', function () {
    $this->actingAs(User::factory()->create())->get(route('company-targets.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('company-targets.create'))->assertForbidden();
});

it('pre-fills the create form with the month dates and the active salesmen of the company', function () {
    Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Salesman', 'is_active' => false, 'name' => 'Left Company']);
    Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Driver', 'is_active' => true, 'name' => 'A Driver']);

    $response = $this->actingAs($this->admin)->get(route('company-targets.create', ['supplier_id' => $this->nestle->id]));

    $response->assertOk()
        ->assertSee(now()->startOfMonth()->toDateString())
        ->assertSee(now()->endOfMonth()->toDateString())
        ->assertSee('Tayyab Ali')
        ->assertSee('Atif Naqvi')
        ->assertDontSee('Left Company')
        ->assertDontSee('A Driver');
});

it('saves a target sheet, defaults a blank target text to Corporate and links by uuid', function () {
    $response = $this->actingAs($this->admin)->post(route('company-targets.store'), ($this->payload)());

    $target = CompanyTarget::query()->firstOrFail();
    $response->assertRedirect(route('company-targets.show', $target))->assertSessionHas('success');
    expect($response->headers->get('Location'))->toContain($target->uuid);
    expect($target->lines)->toHaveCount(2)
        ->and($target->lines[0]->target_amount)->toBe('6014521.00')
        ->and($target->lines[1]->target_label)->toBe('Corporate');
});

it('rejects a total below zero even though the column can hold negatives', function () {
    $payload = ($this->payload)(['lines' => [['employee_id' => $this->tayyab->id, 'target_label' => 'Corporate', 'target_amount' => '-1']]]);

    $this->actingAs($this->admin)->post(route('company-targets.store'), $payload)->assertSessionHasErrors('lines.0.target_amount');
    expect(CompanyTarget::count())->toBe(0);

    $target = CompanyTarget::factory()->create(['supplier_id' => $this->nestle->id]);
    $line = CompanyTargetLine::create(['company_target_id' => $target->id, 'employee_id' => $this->tayyab->id, 'target_amount' => -250.5]);
    expect($line->fresh()->target_amount)->toBe('-250.50');
});

it('rejects salesmen of another company and a duplicate period', function () {
    $stranger = Employee::factory()->create(['supplier_id' => $this->other->id, 'designation' => 'Salesman', 'is_active' => true]);

    $this->actingAs($this->admin)
        ->post(route('company-targets.store'), ($this->payload)(['lines' => [['employee_id' => $stranger->id, 'target_amount' => 0]]]))
        ->assertSessionHasErrors('lines.0.employee_id');

    $this->actingAs($this->admin)->post(route('company-targets.store'), ($this->payload)())->assertSessionDoesntHaveErrors();
    $this->actingAs($this->admin)->post(route('company-targets.store'), ($this->payload)())->assertSessionHasErrors('period_start');
    expect(CompanyTarget::count())->toBe(1);
});

it('locks a company user to their own company', function () {
    $this->actingAs($this->companyUser)->post(route('company-targets.store'), ($this->payload)(['supplier_id' => $this->other->id]))
        ->assertSessionDoesntHaveErrors();

    expect(CompanyTarget::query()->firstOrFail()->supplier_id)->toBe($this->nestle->id);
});

it('shows a company user only their own sheets and hides the rest as not found', function () {
    $mine = CompanyTarget::factory()->create(['supplier_id' => $this->nestle->id]);
    $theirs = CompanyTarget::factory()->create(['supplier_id' => $this->other->id]);

    $this->actingAs($this->companyUser)->get(route('company-targets.index'))
        ->assertOk()->assertSee('Nestle Pakistan')->assertDontSee('Other Foods');
    $this->actingAs($this->companyUser)->get(route('company-targets.show', $mine))->assertOk();
    $this->actingAs($this->companyUser)->get(route('company-targets.show', $theirs))->assertNotFound();
    $this->actingAs($this->companyUser)->delete(route('company-targets.destroy', $theirs))->assertNotFound();
    expect(CompanyTarget::count())->toBe(2);
});

it('updates the rows of a sheet and deletes it', function () {
    $this->actingAs($this->admin)->post(route('company-targets.store'), ($this->payload)());
    $target = CompanyTarget::query()->firstOrFail();

    $this->actingAs($this->admin)->put(route('company-targets.update', $target), ($this->payload)([
        'lines' => [['employee_id' => $this->atif->id, 'target_label' => 'Retail', 'target_amount' => '12700154']],
    ]))->assertRedirect(route('company-targets.show', $target));

    expect($target->lines()->get())->toHaveCount(1)
        ->and($target->lines()->first()->target_label)->toBe('Retail');

    $this->actingAs($this->admin)->delete(route('company-targets.destroy', $target))->assertRedirect(route('company-targets.index'));
    expect(CompanyTarget::count())->toBe(0)->and(CompanyTargetLine::count())->toBe(0);
});

it('lists the active employees of a designation for the create form', function () {
    Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Driver', 'is_active' => true, 'name' => 'A Driver']);

    $this->actingAs($this->admin)
        ->getJson(route('company-targets.employees', ['supplier_id' => $this->nestle->id, 'designation' => 'Salesman']))
        ->assertOk()
        ->assertJsonPath('employees.0.name', 'Atif Naqvi')
        ->assertJsonCount(2, 'employees')
        ->assertJsonCount(3, 'all_employees')
        ->assertJsonFragment(['designations' => ['Driver', 'Salesman']]);
});

it('creates the monthly sheet with zero targets for every active salesman, once', function () {
    Employee::factory()->create(['supplier_id' => $this->nestle->id, 'designation' => 'Salesman', 'is_active' => false]);
    Employee::factory()->create(['supplier_id' => $this->other->id, 'designation' => 'Driver', 'is_active' => true]);

    $this->artisan('company-targets:generate', ['--month' => '2026-11'])->assertSuccessful();
    $this->artisan('company-targets:generate', ['--month' => '2026-11'])->assertSuccessful();

    $sheet = CompanyTarget::query()->where('supplier_id', $this->nestle->id)->firstOrFail();
    expect($sheet->period_start->toDateString())->toBe('2026-11-01')
        ->and($sheet->period_end->toDateString())->toBe('2026-11-30')
        ->and($sheet->lines)->toHaveCount(2)
        ->and($sheet->lines->every(fn ($line) => (float) $line->target_amount === 0.0 && $line->target_label === 'Corporate'))->toBeTrue()
        ->and(CompanyTarget::query()->where('supplier_id', $this->nestle->id)->count())->toBe(1);

    $this->artisan('company-targets:generate', ['--month' => '2026-13'])->assertFailed();
});

it('keeps the salesman names and typed values when the form comes back with an error', function () {
    $payload = ($this->payload)(['lines' => [['employee_id' => $this->tayyab->id, 'target_label' => 'Retail', 'target_amount' => '-5']]]);

    $this->actingAs($this->admin)->from(route('company-targets.create'))
        ->post(route('company-targets.store'), $payload)
        ->assertRedirect(route('company-targets.create'));

    $this->actingAs($this->admin)->get(route('company-targets.create'))
        ->assertOk()
        ->assertViewHas('rows', fn ($rows) => $rows->count() === 1
            && $rows[0]['name'] === 'Tayyab Ali'
            && $rows[0]['target_label'] === 'Retail'
            && $rows[0]['target_amount'] === '-5');
});
