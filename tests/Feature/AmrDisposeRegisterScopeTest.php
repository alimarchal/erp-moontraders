<?php

use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAmrLiquid;
use App\Models\SalesSettlementAmrPowder;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-amr-dispose-register', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'report-audit-amr-dispose-register-manage', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['report-audit-amr-dispose-register', 'report-audit-amr-dispose-register-manage']);
    $this->actingAs($this->user);
});

function createAmrLiquidForSupplier(Supplier $supplier, string $productName, string $batchCode): SalesSettlementAmrLiquid
{
    $employee = Employee::factory()->create([
        'supplier_id' => $supplier->id,
        'is_active' => true,
    ]);
    $settlement = SalesSettlement::factory()->create([
        'supplier_id' => $supplier->id,
        'employee_id' => $employee->id,
        'settlement_date' => now()->toDateString(),
    ]);
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'product_name' => $productName,
    ]);

    return SalesSettlementAmrLiquid::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'batch_code' => $batchCode,
        'quantity' => 5,
        'amount' => 250,
        'is_disposed' => false,
    ]);
}

function createAmrPowderForSupplier(Supplier $supplier, string $productName, string $batchCode): SalesSettlementAmrPowder
{
    $employee = Employee::factory()->create([
        'supplier_id' => $supplier->id,
        'is_active' => true,
    ]);
    $settlement = SalesSettlement::factory()->create([
        'supplier_id' => $supplier->id,
        'employee_id' => $employee->id,
        'settlement_date' => now()->toDateString(),
    ]);
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'product_name' => $productName,
    ]);

    return SalesSettlementAmrPowder::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'batch_code' => $batchCode,
        'quantity' => 3,
        'amount' => 150,
        'is_disposed' => false,
    ]);
}

test('amr dispose register is scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create(['supplier_name' => 'Kausar Oil']);
    $otherSupplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    createAmrLiquidForSupplier($ownSupplier, 'Own AMR Liquid', 'OWN-LIQ-001');
    createAmrPowderForSupplier($otherSupplier, 'Other AMR Powder', 'OTHER-POW-001');

    $response = $this->get(route('reports.amr-dispose-register.index'));

    $response->assertSuccessful();
    $response->assertSee('Own AMR Liquid');
    $response->assertSee('OWN-LIQ-001');
    $response->assertDontSee('Other AMR Powder');
    $response->assertDontSee('OTHER-POW-001');
    expect($response->viewData('suppliers'))->toHaveCount(1);
    expect($response->viewData('suppliers')->first()->id)->toBe($ownSupplier->id);
    expect($response->viewData('employees'))->toHaveCount(1);
});

test('amr dispose register blocks filtering by another supplier for scoped users', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();

    $this->get(route('reports.amr-dispose-register.index', [
        'filter' => ['supplier_id' => $otherSupplier->id],
    ]))->assertForbidden();
});

test('amr dispose update is scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();
    $otherRecord = createAmrLiquidForSupplier($otherSupplier, 'Other AMR Liquid', 'OTHER-LIQ-001');

    $this->post(route('reports.amr-dispose-register.update-disposed', ['liquid', $otherRecord->id]), [
        'is_disposed' => true,
        'disposed_at' => '2026-04-15',
    ])->assertForbidden();

    expect($otherRecord->fresh()->is_disposed)->toBeFalse();
});

test('amr dispose bulk update is scoped to the authenticated users supplier', function () {
    $ownSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $this->user->forceFill(['supplier_id' => $ownSupplier->id])->save();
    $ownRecord = createAmrLiquidForSupplier($ownSupplier, 'Own AMR Liquid', 'OWN-LIQ-001');
    $otherRecord = createAmrPowderForSupplier($otherSupplier, 'Other AMR Powder', 'OTHER-POW-001');

    $this->post(route('reports.amr-dispose-register.bulk-update-disposed'), [
        'is_disposed' => true,
        'disposed_at' => '2026-04-15',
        'items' => [
            ['type' => 'liquid', 'id' => $ownRecord->id],
            ['type' => 'powder', 'id' => $otherRecord->id],
        ],
    ])->assertForbidden();

    expect($ownRecord->fresh()->is_disposed)->toBeFalse();
    expect($otherRecord->fresh()->is_disposed)->toBeFalse();
});

test('amr dispose update stores the provided disposed date', function () {
    $supplier = Supplier::factory()->create();
    $this->user->forceFill(['supplier_id' => $supplier->id])->save();
    $record = createAmrLiquidForSupplier($supplier, 'Own AMR Liquid', 'OWN-LIQ-001');

    $this->post(route('reports.amr-dispose-register.update-disposed', ['liquid', $record->id]), [
        'is_disposed' => true,
        'disposed_at' => '2026-04-15',
    ])->assertRedirect();

    $record->refresh();

    expect($record->is_disposed)->toBeTrue();
    expect($record->disposed_at?->format('Y-m-d'))->toBe('2026-04-15');
});

test('amr dispose bulk update stores the provided disposed date', function () {
    $supplier = Supplier::factory()->create();
    $this->user->forceFill(['supplier_id' => $supplier->id])->save();
    $liquidRecord = createAmrLiquidForSupplier($supplier, 'Own AMR Liquid', 'OWN-LIQ-001');
    $powderRecord = createAmrPowderForSupplier($supplier, 'Own AMR Powder', 'OWN-POW-001');

    $this->post(route('reports.amr-dispose-register.bulk-update-disposed'), [
        'is_disposed' => true,
        'disposed_at' => '2026-04-20',
        'items' => [
            ['type' => 'liquid', 'id' => $liquidRecord->id],
            ['type' => 'powder', 'id' => $powderRecord->id],
        ],
    ])->assertRedirect();

    $liquidRecord->refresh();
    $powderRecord->refresh();

    expect($liquidRecord->is_disposed)->toBeTrue();
    expect($liquidRecord->disposed_at?->format('Y-m-d'))->toBe('2026-04-20');
    expect($powderRecord->is_disposed)->toBeTrue();
    expect($powderRecord->disposed_at?->format('Y-m-d'))->toBe('2026-04-20');
});
