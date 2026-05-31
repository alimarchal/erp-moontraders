<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-cheque-register', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'report-audit-cheque-register-manage', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['report-audit-cheque-register', 'report-audit-cheque-register-manage']);
    $this->actingAs($this->user);
});

test('cheque register is scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create(['supplier_name' => 'Kausar Oil']);
    $otherSupplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);
    $ownEmployee = Employee::factory()->create([
        'supplier_id' => $ownSupplier->id,
        'name' => 'Own Salesman',
        'is_active' => true,
    ]);
    $otherEmployee = Employee::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Other Salesman',
        'is_active' => true,
    ]);
    $ownCustomer = Customer::factory()->create(['customer_name' => 'Own Customer']);
    $otherCustomer = Customer::factory()->create(['customer_name' => 'Other Customer']);
    $ownSettlement = SalesSettlement::factory()->create([
        'supplier_id' => $ownSupplier->id,
        'employee_id' => $ownEmployee->id,
    ]);
    $otherSettlement = SalesSettlement::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'employee_id' => $otherEmployee->id,
    ]);

    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    SalesSettlementCheque::factory()->create([
        'sales_settlement_id' => $ownSettlement->id,
        'customer_id' => $ownCustomer->id,
        'cheque_number' => 'OWN-CHQ-001',
        'cheque_date' => now()->toDateString(),
        'status' => 'pending',
    ]);
    SalesSettlementCheque::factory()->create([
        'sales_settlement_id' => $otherSettlement->id,
        'customer_id' => $otherCustomer->id,
        'cheque_number' => 'OTHER-CHQ-001',
        'cheque_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $response = $this->get(route('reports.cheque-register.index'));

    $response->assertSuccessful();
    $response->assertSee('OWN-CHQ-001');
    $response->assertDontSee('OTHER-CHQ-001');
    expect($response->viewData('suppliers'))->toHaveCount(1);
    expect($response->viewData('suppliers')->first()->id)->toBe($ownSupplier->id);
    expect($response->viewData('employees'))->toHaveCount(1);
    expect($response->viewData('employees')->first()->id)->toBe($ownEmployee->id);
    expect($response->viewData('customers'))->toHaveCount(1);
    expect($response->viewData('customers')->first()->id)->toBe($ownCustomer->id);
});

test('cheque register blocks filtering by another supplier for scoped users', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->get(route('reports.cheque-register.index', [
        'filter' => ['supplier_id' => $otherSupplier->id],
    ]))->assertForbidden();
});

test('cheque status update is scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $otherSettlement = SalesSettlement::factory()->create(['supplier_id' => $otherSupplier->id]);
    $cheque = SalesSettlementCheque::factory()->create([
        'sales_settlement_id' => $otherSettlement->id,
        'cheque_date' => now()->toDateString(),
        'status' => 'pending',
    ]);
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->post(route('reports.cheque-register.update-status', $cheque), [
        'status' => 'cleared',
        'cleared_date' => now()->toDateString(),
    ])->assertForbidden();

    expect($cheque->fresh()->status)->toBe('pending');
});
