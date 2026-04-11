<?php

use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\JournalEntry;
use App\Models\SalesSettlement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\SalesSettlementRevertService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($this->user);
});

it('sets active_vehicle_lock to vehicle_id on create for draft goods issues', function () {
    $vehicle = Vehicle::factory()->create();
    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    expect($gi->active_vehicle_lock)->toBe($vehicle->id);
});

it('keeps active_vehicle_lock aligned when draft GI is re-assigned to a new vehicle', function () {
    $vehicleA = Vehicle::factory()->create();
    $vehicleB = Vehicle::factory()->create();

    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicleA->id,
        'status' => 'draft',
    ]);

    $gi->update(['vehicle_id' => $vehicleB->id]);

    expect($gi->fresh()->active_vehicle_lock)->toBe($vehicleB->id);
});

it('releases active_vehicle_lock on soft delete', function () {
    $vehicle = Vehicle::factory()->create();
    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    $gi->delete();

    expect($gi->fresh()->active_vehicle_lock)->toBeNull();
});

it('db unique index prevents two concurrent active GIs on the same vehicle', function () {
    $vehicle = Vehicle::factory()->create();
    GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    expect(fn () => GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]))->toThrow(QueryException::class);
});

it('canAcceptSupplementaryItems returns false for draft GIs', function () {
    $gi = GoodsIssue::factory()->create(['status' => 'draft']);

    expect($gi->canAcceptSupplementaryItems())->toBeFalse();
});

it('canAcceptSupplementaryItems returns true for issued GIs without finalized settlement', function () {
    $gi = GoodsIssue::factory()->create(['status' => 'issued']);

    expect($gi->canAcceptSupplementaryItems())->toBeTrue();
});

it('canAcceptSupplementaryItems returns false once settlement is verified', function () {
    $gi = GoodsIssue::factory()->create(['status' => 'issued']);
    SalesSettlement::factory()->create([
        'goods_issue_id' => $gi->id,
        'vehicle_id' => $gi->vehicle_id,
        'status' => 'verified',
    ]);

    expect($gi->fresh()->canAcceptSupplementaryItems())->toBeFalse();
});

it('appendItemsForm redirects drafts away from the append flow', function () {
    Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    $response = $this->get(route('goods-issues.append-items', $gi));

    $response->assertRedirect(route('goods-issues.show', $gi));
    $response->assertSessionHas('error');
});

it('checkVehicleGoodsIssue returns is_draft true for vehicles holding a draft GI', function () {
    $vehicle = Vehicle::factory()->create();
    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    $response = $this->getJson(route('api.goods-issues.check-vehicle', ['vehicle_id' => $vehicle->id]));

    $response->assertOk();
    $response->assertJson([
        'has_existing' => true,
        'can_append' => false,
        'is_draft' => true,
        'goods_issue_id' => $gi->id,
    ]);
});

it('checkVehicleGoodsIssue returns existing_employee_id/name for issued GIs', function () {
    $vehicle = Vehicle::factory()->create();
    $employee = Employee::factory()->create();
    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'status' => 'issued',
    ]);

    $response = $this->getJson(route('api.goods-issues.check-vehicle', ['vehicle_id' => $vehicle->id]));

    $response->assertOk();
    $response->assertJson([
        'has_existing' => true,
        'can_append' => true,
        'goods_issue_id' => $gi->id,
        'existing_employee_id' => $employee->id,
        'existing_employee_name' => $employee->name,
    ]);
});

it('settlement revert pre-check blocks when another active GI already holds the vehicle lock', function () {
    $vehicle = Vehicle::factory()->create();

    // The original GI is already settled — so its lock is null (released
    // when the settlement was posted).
    $settledGi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'issued',
    ]);
    $settledGi->update(['active_vehicle_lock' => null]);

    // Build a valid posted JournalEntry (the DB trigger requires an open
    // accounting period matching the entry date).
    AccountingPeriod::firstOrCreate(
        ['name' => now()->format('F Y')],
        [
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]
    );
    $currency = Currency::first() ?? Currency::factory()->base()->create();
    $journalEntry = JournalEntry::create([
        'currency_id' => $currency->id,
        'entry_date' => now()->toDateString(),
        'reference' => 'SS-LOCK-TEST',
        'description' => 'Lock test',
        'status' => 'posted',
    ]);

    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $settledGi->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'posted',
        'journal_entry_id' => $journalEntry->id,
    ]);

    // Meanwhile, a new GI has been legitimately created on the same vehicle
    GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    $revertService = app(SalesSettlementRevertService::class);
    $precheck = $revertService->performPreChecks($settlement);

    expect($precheck['ok'])->toBeFalse();
    expect($precheck['message'])->toContain('already locked');
});
