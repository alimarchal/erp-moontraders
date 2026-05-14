<?php

use App\Models\ChartOfAccount;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementExcessAmount;
use App\Models\SalesSettlementExpense;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('report-sales-summary-roi');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('report-sales-summary-roi');
});

test('summary roi report sets fixed incentive and expiry claimed values for engro supplier', function () {
    $engroSupplier = Supplier::factory()->create([
        'supplier_name' => 'Engro Foods',
        'short_name' => 'Engro',
        'disabled' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $engroSupplier->id,
            ],
        ]));

    $response->assertSuccessful()
        ->assertViewHas('incentiveClaimed', 208652.0)
        ->assertViewHas('expiryClaimed', 260000.0)
        ->assertSee('Incentive Claimed')
        ->assertSee('Expiry Claimed')
        ->assertSee('208,652.00')
        ->assertSee('260,000.00');
});

test('summary roi report sets incentive and expiry claimed as zero for non engro supplier', function () {
    $otherSupplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan',
        'short_name' => 'Nestle',
        'disabled' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $otherSupplier->id,
            ],
        ]));

    $response->assertSuccessful()
        ->assertViewHas('incentiveClaimed', 0.0)
        ->assertViewHas('expiryClaimed', 0.0)
        ->assertSee('Incentive Claimed')
        ->assertSee('Expiry Claimed')
        ->assertSee('0.00');
});

test('summary roi report adjusts short amount by excess amount and hides 4250 for engro', function () {
    $engroSupplier = Supplier::factory()->create([
        'supplier_name' => 'Engro Foods',
        'short_name' => 'Engro',
        'disabled' => false,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'supplier_id' => $engroSupplier->id,
        'status' => 'posted',
        'settlement_date' => now()->toDateString(),
    ]);

    $shortAmountAccount = ChartOfAccount::factory()->create([
        'account_code' => '5293',
        'account_name' => 'Short Amount',
    ]);

    SalesSettlementExpense::create([
        'sales_settlement_id' => $settlement->id,
        'expense_account_id' => $shortAmountAccount->id,
        'amount' => 1000.00,
        'expense_date' => now()->toDateString(),
        'description' => 'Short amount',
    ]);

    SalesSettlementExcessAmount::create([
        'sales_settlement_id' => $settlement->id,
        'amount' => 200.00,
        'description' => 'Cash excess',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('reports.summary-roi.index', [
            'filter' => [
                'supplier_id' => $engroSupplier->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'status' => 'posted',
            ],
        ]));

    $response->assertSuccessful();

    $breakdown = $response['expenseBreakdown'];
    $shortAmount = $breakdown->firstWhere('account_code', '5293');

    expect($shortAmount)->not->toBeNull('Short Amount should be in breakdown');
    expect((float) $shortAmount->total_amount)->toBe(-800.00);
    expect($shortAmount->account_name)->toContain('Excess Amount (4250)');
    expect($shortAmount->account_name)->toContain('Short Amount (5293)');
    expect($shortAmount->account_name)->toContain('200.00');
    expect($shortAmount->account_name)->toContain('1,000.00');
    expect($breakdown->contains(fn ($row) => $row->account_code === '4250'))->toBeFalse();
});
