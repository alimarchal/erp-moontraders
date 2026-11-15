<?php

use App\Models\CashPayoutDenomination;
use App\Models\ChequeClearing;
use App\Models\ExpenseVoucher;
use App\Models\SalesSettlementCheque;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('backfills payment_method from payment_type', function () {
    $credit = ExpenseVoucher::factory()->create(['payment_type' => 'credit']);
    $cash = ExpenseVoucher::factory()->create(['payment_type' => 'cash']);

    expect($credit->fresh()->payment_method)->toBe(ExpenseVoucher::METHOD_CREDIT)
        ->and($cash->fresh()->payment_method)->toBe(ExpenseVoucher::METHOD_CASH);
});

it('computes the payout total on save', function () {
    $voucher = ExpenseVoucher::factory()->create();

    $payout = $voucher->cashPayout()->create([
        'denom_5000' => 2,
        'denom_100' => 3,
        'denom_coins' => 5.50,
    ]);

    expect((float) $payout->total_amount)->toBe(10305.50)
        ->and($voucher->fresh()->cashPayout)->toBeInstanceOf(CashPayoutDenomination::class);
});

it('tells how a voucher was paid and whether it was reversed', function () {
    $cheque = ExpenseVoucher::factory()->make(['payment_method' => ExpenseVoucher::METHOD_CHEQUE]);
    $transfer = ExpenseVoucher::factory()->make(['payment_method' => ExpenseVoucher::METHOD_BANK_TRANSFER]);
    $cash = ExpenseVoucher::factory()->make(['payment_method' => ExpenseVoucher::METHOD_CASH, 'status' => ExpenseVoucher::STATUS_REVERSED]);

    expect($cheque->isPaidByBank())->toBeTrue()
        ->and($transfer->isPaidByBank())->toBeTrue()
        ->and($cash->isPaidByBank())->toBeFalse()
        ->and($cash->isReversed())->toBeTrue()
        ->and(ExpenseVoucher::paymentMethodLabels())->toHaveKeys(['cash', 'bank_transfer', 'cheque', 'credit']);
});

it('links cheques to a clearing batch', function () {
    $clearing = ChequeClearing::factory()->create(['denom_1000' => 4, 'denom_coins' => 0.50]);
    $cheque = SalesSettlementCheque::factory()->create(['cheque_clearing_id' => $clearing->id]);

    expect((float) $clearing->total_amount)->toBe(4000.50)
        ->and($clearing->cheques)->toHaveCount(1)
        ->and($cheque->clearing->is($clearing))->toBeTrue()
        ->and(Schema::hasColumn('sales_settlement_cheques', 'bounce_journal_entry_id'))->toBeTrue();
});

it('creates the new permissions', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    expect(Permission::whereIn('name', ['expense-voucher-reverse', 'report-audit-cheque-register-all-companies'])->count())->toBe(2);
});
