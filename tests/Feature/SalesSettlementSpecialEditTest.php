<?php

use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('denies non super admins access to the special edit form', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => 'No']));

    $settlement = SalesSettlement::factory()->create(['status' => 'posted']);

    $this->get(route('sales-settlements.edit-special', $settlement))->assertForbidden();
});

it('corrects the settlement date across dependent tables for super admins', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => 'Yes']));

    AccountingPeriod::factory()->create([
        'start_date' => '2026-07-01',
        'end_date' => '2026-09-30',
        'status' => 'open',
    ]);

    $settlement = SalesSettlement::factory()->create([
        'settlement_date' => '2026-08-25',
        'status' => 'posted',
    ]);

    $expenseAccount = ChartOfAccount::factory()->create();

    DB::table('sales_settlement_expenses')->insert([
        'sales_settlement_id' => $settlement->id,
        'expense_date' => '2026-08-25',
        'expense_account_id' => $expenseAccount->id,
        'amount' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $uom = Uom::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    DB::table('stock_movements')->insert([
        'movement_type' => 'sale',
        'reference_type' => SalesSettlement::class,
        'reference_id' => $settlement->id,
        'movement_date' => '2026-08-25',
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'uom_id' => $uom->id,
        'quantity' => -1,
        'unit_cost' => 10,
        'total_value' => -10,
        'created_by' => $settlement->created_by,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $bankAccount = BankAccount::factory()->create();

    DB::table('sales_settlement_bank_transfers')->insert([
        'sales_settlement_id' => $settlement->id,
        'bank_account_id' => $bankAccount->id,
        'amount' => 500,
        'transfer_date' => '2026-08-25',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sales_settlement_cheques')->insert([
        'sales_settlement_id' => $settlement->id,
        'cheque_number' => 'CHQ-001',
        'amount' => 300,
        'bank_name' => 'Test Bank',
        'cheque_date' => '2026-08-20', // intentionally different from settlement date — must stay untouched
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sales_settlement_bank_slips')->insert([
        'sales_settlement_id' => $settlement->id,
        'employee_id' => $settlement->employee_id ?? Employee::factory()->create()->id,
        'bank_account_id' => $bankAccount->id,
        'amount' => 200,
        'deposit_date' => '2026-08-25',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->post(route('sales-settlements.update-special', $settlement), [
        'settlement_date' => '2026-07-25',
    ])->assertRedirect(route('sales-settlements.show', $settlement));

    expect($settlement->fresh()->settlement_date->toDateString())->toBe('2026-07-25');

    $this->assertDatabaseHas('sales_settlement_expenses', [
        'sales_settlement_id' => $settlement->id,
        'expense_date' => '2026-07-25',
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'reference_type' => SalesSettlement::class,
        'reference_id' => $settlement->id,
        'movement_date' => '2026-07-25',
    ]);

    $this->assertDatabaseHas('sales_settlement_bank_transfers', [
        'sales_settlement_id' => $settlement->id,
        'transfer_date' => '2026-07-25',
    ]);

    $this->assertDatabaseHas('sales_settlement_bank_slips', [
        'sales_settlement_id' => $settlement->id,
        'deposit_date' => '2026-07-25',
    ]);

    // Cheque date intentionally differed from settlement_date — must be left untouched.
    $this->assertDatabaseHas('sales_settlement_cheques', [
        'sales_settlement_id' => $settlement->id,
        'cheque_date' => '2026-08-20',
    ]);
});

it('rejects the correction when no open accounting period covers the new date', function () {
    $this->actingAs(User::factory()->create(['is_super_admin' => 'Yes']));

    $settlement = SalesSettlement::factory()->create([
        'settlement_date' => '2026-08-25',
        'status' => 'posted',
    ]);

    $this->post(route('sales-settlements.update-special', $settlement), [
        'settlement_date' => '2026-07-25',
    ])->assertRedirect();

    expect($settlement->fresh()->settlement_date->toDateString())->toBe('2026-08-25');
});
