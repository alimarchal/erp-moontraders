<?php

use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('report-audit-cheque-register');
    Permission::findOrCreate('report-audit-cheque-register-manage');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['report-audit-cheque-register', 'report-audit-cheque-register-manage']);

    $supplier = Supplier::factory()->create();
    $this->user->update(['supplier_id' => $supplier->id]);
    $employee = Employee::factory()->create(['supplier_id' => $supplier->id, 'designation' => 'Salesman']);
    $settlement = SalesSettlement::factory()->create(['employee_id' => $employee->id, 'supplier_id' => $supplier->id]);
    $this->cheque = SalesSettlementCheque::create([
        'sales_settlement_id' => $settlement->id, 'cheque_number' => 'CH-1', 'amount' => 10000, 'bank_name' => 'HBL', 'status' => 'pending',
    ]);
});

it('clears a cheque with the cleared amount defaulting to the cheque amount and records history', function () {
    $this->actingAs($this->user)
        ->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28'])
        ->assertRedirect();

    $this->cheque->refresh();
    expect($this->cheque->status)->toBe('cleared')
        ->and((float) $this->cheque->cleared_amount)->toBe(10000.0)
        ->and($this->cheque->histories)->toHaveCount(1)
        ->and($this->cheque->histories->first()->from_status)->toBe('pending')
        ->and($this->cheque->histories->first()->changed_by)->toBe($this->user->id);
});

it('keeps a different cleared amount and requires a date when clearing', function () {
    $this->actingAs($this->user)
        ->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared'])
        ->assertSessionHasErrors('cleared_date');

    $this->actingAs($this->user)
        ->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'cleared_amount' => 9500, 'notes' => 'bank charges'])
        ->assertRedirect();

    expect((float) $this->cheque->fresh()->cleared_amount)->toBe(9500.0)
        ->and($this->cheque->histories()->first()->notes)->toBe('bank charges');
});

it('clears the cleared date and amount when a cheque goes back, but keeps the history', function () {
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28']);
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'bounced', 'notes' => 'returned by bank']);

    $this->cheque->refresh();
    expect($this->cheque->cleared_date)->toBeNull()
        ->and($this->cheque->cleared_amount)->toBeNull()
        ->and($this->cheque->histories)->toHaveCount(2);
});

it('returns the status history as json for users who can view the register', function () {
    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28']);

    $this->actingAs($this->user)->getJson(route('reports.cheque-register.history', $this->cheque))
        ->assertOk()
        ->assertJsonPath('cheque_number', 'CH-1')
        ->assertJsonPath('entries.0.to', 'Cleared');

    $this->actingAs(User::factory()->create())->getJson(route('reports.cheque-register.history', $this->cheque))->assertForbidden();
});

it('feeds the distributor report with the cleared amount on the cleared date', function () {
    Permission::findOrCreate('report-audit-cash-collection-distributor');
    Permission::findOrCreate('report-audit-cash-collection-distributor-all-companies');
    $this->user->givePermissionTo(['report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies']);

    $this->actingAs($this->user)->post(route('reports.cheque-register.update-status', $this->cheque), ['status' => 'cleared', 'cleared_date' => '2026-10-28', 'cleared_amount' => 9500]);

    $tables = $this->actingAs($this->user)
        ->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all', 'date' => '2026-10-28']))
        ->viewData('tables');

    expect($tables['cash']['rows'][0][3])->toBe('9,500.00');
});
