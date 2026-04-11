<?php

use App\Models\GoodsIssue;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($this->user);
});

it('sets active_vehicle_lock when a draft GI is created', function () {
    $vehicle = Vehicle::factory()->create();

    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    expect($gi->active_vehicle_lock)->toBe($vehicle->id);
});

it('sets active_vehicle_lock when an issued GI is created', function () {
    $vehicle = Vehicle::factory()->create();

    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'issued',
    ]);

    expect($gi->active_vehicle_lock)->toBe($vehicle->id);
});

it('throws on the unique index when two active GIs share a vehicle', function () {
    $vehicle = Vehicle::factory()->create();

    GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'issued',
    ]);

    // Bypass model events with a raw insert so we can prove the DB index
    // independently catches the duplicate. The model events would themselves
    // also block this — we want to verify the *index* is the safety net.
    expect(fn () => DB::table('goods_issues')->insert([
        'issue_number' => 'GI-LOCK-DUP-1',
        'issue_date' => now()->toDateString(),
        'warehouse_id' => 1,
        'vehicle_id' => $vehicle->id,
        'active_vehicle_lock' => $vehicle->id,
        'employee_id' => 1,
        'status' => 'draft',
        'total_quantity' => 0,
        'total_value' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('releases active_vehicle_lock when a GI is soft-deleted', function () {
    $vehicle = Vehicle::factory()->create();

    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    expect($gi->active_vehicle_lock)->toBe($vehicle->id);

    $gi->delete();

    $reloaded = GoodsIssue::withTrashed()->find($gi->id);
    expect($reloaded->active_vehicle_lock)->toBeNull();
});

it('frees the lock so a replacement GI on the same vehicle can be created', function () {
    $vehicle = Vehicle::factory()->create();

    $first = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    // Soft-delete first to free the lock
    $first->delete();

    // Replacement should now be allowed (no QueryException)
    $second = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'draft',
    ]);

    expect($second->active_vehicle_lock)->toBe($vehicle->id);
});

it('round-trips the lock through settlement post and revert', function () {
    $vehicle = Vehicle::factory()->create();

    $gi = GoodsIssue::factory()->create([
        'vehicle_id' => $vehicle->id,
        'status' => 'issued',
    ]);

    // Simulate settlement post: lock cleared
    GoodsIssue::whereKey($gi->id)->update(['active_vehicle_lock' => null]);
    expect($gi->fresh()->active_vehicle_lock)->toBeNull();

    // Now revert: lock should be re-acquired (mirroring the service code)
    GoodsIssue::whereKey($gi->id)->update(['active_vehicle_lock' => DB::raw('vehicle_id')]);
    expect($gi->fresh()->active_vehicle_lock)->toBe($vehicle->id);
});

it('returns false from canAcceptSupplementaryItems for draft GIs', function () {
    $gi = GoodsIssue::factory()->create([
        'status' => 'draft',
    ]);

    expect($gi->canAcceptSupplementaryItems())->toBeFalse();
});

it('returns true from canAcceptSupplementaryItems for issued GIs without finalized settlement', function () {
    $gi = GoodsIssue::factory()->create([
        'status' => 'issued',
    ]);

    expect($gi->canAcceptSupplementaryItems())->toBeTrue();
});
