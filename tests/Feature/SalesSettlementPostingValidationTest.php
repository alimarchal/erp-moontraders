<?php

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCashDenomination;
use App\Models\SalesSettlementCreditSale;
use App\Models\SalesSettlementItem;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────

function makePostingSetup(): array
{
    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $uom = Uom::factory()->create();
    $product = Product::factory()->create();

    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'issued_by' => $user->id,
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'line_no' => 1,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'quantity_issued' => 10,
        'unit_cost' => 100,
        'selling_price' => 150,
        'total_value' => 1500,
    ]);

    return compact('user', 'employee', 'vehicle', 'warehouse', 'product', 'goodsIssue');
}

/**
 * Create a minimal draft settlement with one item and a cash denomination.
 * Returns the settlement ready for posting.
 */
function makeDraftSettlement(array $setup, array $overrides = []): SalesSettlement
{
    $settlement = SalesSettlement::factory()->create(array_merge([
        'status' => 'draft',
        'goods_issue_id' => $setup['goodsIssue']->id,
        'employee_id' => $setup['employee']->id,
        'vehicle_id' => $setup['vehicle']->id,
        'warehouse_id' => $setup['warehouse']->id,
        'total_sales_amount' => 1500,
        'credit_sales_amount' => 0,
        'cheque_sales_amount' => 0,
        'bank_transfer_amount' => 0,
        'cash_sales_amount' => 1500,
        'cash_collected' => 1500,
        'expenses_claimed' => 0,
    ], $overrides));

    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $setup['product']->id,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => 150,
        'total_sales_value' => 1500,
        'unit_cost' => 100,
        'total_cogs' => 1000,
    ]);

    SalesSettlementCashDenomination::create([
        'sales_settlement_id' => $settlement->id,
        'denom_5000' => 0,
        'denom_1000' => 1,
        'denom_500' => 1,
        'denom_100' => 0,
        'denom_50' => 0,
        'denom_20' => 0,
        'denom_10' => 0,
        'denom_coins' => 0,
        'total_amount' => 1500,
    ]);

    return $settlement;
}

// ──────────────────────────────────────────────────────
// Layer 1: Store — negative cash_sales_amount blocked
// ──────────────────────────────────────────────────────

it('store blocks when credit sales exceed total sales (negative cash_sales_amount)', function () {
    $setup = makePostingSetup();
    $customer = Customer::factory()->create();

    // Total sales = 10 * 150 = 1500, credit = 1600 → cash_sales = -100
    $response = $this->actingAs($setup['user'])->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $setup['goodsIssue']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'credit_sales' => json_encode([
            ['customer_id' => $customer->id, 'sale_amount' => 1600],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(SalesSettlement::count())->toBe(0);
});

it('store blocks when cheques exceed total sales (negative cash_sales_amount)', function () {
    $setup = makePostingSetup();

    // Total sales = 1500, cheques = 2000 → cash_sales = -500
    $response = $this->actingAs($setup['user'])->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $setup['goodsIssue']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'cheques' => json_encode([
            ['cheque_number' => 'CHQ-001', 'amount' => 2000, 'bank_name' => 'HBL', 'cheque_date' => now()->toDateString()],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(SalesSettlement::count())->toBe(0);
});

it('store allows when payment breakdown is valid', function () {
    $setup = makePostingSetup();
    $customer = Customer::factory()->create();

    // Total = 1500, credit = 500, cash = 1000 → no negative
    $response = $this->actingAs($setup['user'])->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $setup['goodsIssue']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'credit_sales' => json_encode([
            ['customer_id' => $customer->id, 'sale_amount' => 500],
        ]),
        'denom_5000' => 0, 'denom_1000' => 1, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();
    $response->assertSessionMissing('error');
    expect(SalesSettlement::count())->toBe(1);
});

// ──────────────────────────────────────────────────────
// Layer 1: Update — negative cash_sales_amount blocked
// ──────────────────────────────────────────────────────

it('update blocks when credit sales exceed total sales (negative cash_sales_amount)', function () {
    $setup = makePostingSetup();
    $customer = Customer::factory()->create();

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $setup['goodsIssue']->id,
        'employee_id' => $setup['employee']->id,
        'vehicle_id' => $setup['vehicle']->id,
        'warehouse_id' => $setup['warehouse']->id,
        'settlement_number' => 'SETTLE-TEST-8001',
    ]);

    // Total = 1500, credit = 2000 → cash_sales = -500
    $response = $this->actingAs($setup['user'])->put(route('sales-settlements.update', $settlement), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $setup['goodsIssue']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'credit_sales' => json_encode([
            ['customer_id' => $customer->id, 'sale_amount' => 2000],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    // Settlement amounts unchanged
    $settlement->refresh();
    expect((float) $settlement->cash_sales_amount)->not->toBeLessThan(0.0);
});

// ──────────────────────────────────────────────────────
// Layer 2: Post — negative cash_sales_amount blocked
// ──────────────────────────────────────────────────────

it('post blocks when stored cash_sales_amount is negative (defence-in-depth)', function () {
    $setup = makePostingSetup();
    $customer = Customer::factory()->create();

    // Bypass Layer 1 by directly setting a negative cash_sales_amount in the DB
    $settlement = makeDraftSettlement($setup, ['cash_sales_amount' => -100]);

    SalesSettlementCreditSale::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'employee_id' => $setup['employee']->id,
        'sale_amount' => 1600,
        'invoice_number' => 'INV-001',
        'sale_date' => now()->toDateString(),
    ]);

    // Overwrite item so total_sales_value < credit
    SalesSettlementItem::where('sales_settlement_id', $settlement->id)->update([
        'total_sales_value' => 1500,
    ]);

    $response = $this->actingAs($setup['user'])
        ->post(route('sales-settlements.post', $settlement));

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $settlement->refresh();
    expect($settlement->status)->toBe('draft');
});

// ──────────────────────────────────────────────────────
// Layer 2: Post — negative short/excess blocked
// ──────────────────────────────────────────────────────

it('post blocks when physical cash submitted is less than expected (cash shortage)', function () {
    $setup = makePostingSetup();

    // cash_sales = 1500, expenses = 0, expected clearing = 1500
    // Physical cash = 500 → short/excess = 500 - 1500 = -1000 (shortage)
    $settlement = makeDraftSettlement($setup, [
        'cash_sales_amount' => 1500,
        'cash_collected' => 500,
        'expenses_claimed' => 0,
    ]);

    // Update denomination to reflect only 500
    SalesSettlementCashDenomination::where('sales_settlement_id', $settlement->id)
        ->update(['denom_1000' => 0, 'denom_500' => 1, 'total_amount' => 500]);

    $response = $this->actingAs($setup['user'])
        ->post(route('sales-settlements.post', $settlement));

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $settlement->refresh();
    expect($settlement->status)->toBe('draft');
});

it('post allows when short/excess is exactly zero', function () {
    // Zero short/excess: cash collected exactly matches expected
    // We just verify the validation passes (posting itself may fail for other reasons like GL accounts,
    // but the validation layer must not block it).
    $setup = makePostingSetup();

    $settlement = makeDraftSettlement($setup, [
        'cash_sales_amount' => 1500,
        'cash_collected' => 1500,
        'expenses_claimed' => 0,
    ]);

    // Denomination matches exactly
    SalesSettlementCashDenomination::where('sales_settlement_id', $settlement->id)
        ->update(['denom_1000' => 1, 'denom_500' => 1, 'total_amount' => 1500]);

    $response = $this->actingAs($setup['user'])
        ->post(route('sales-settlements.post', $settlement));

    // The validation layer passes. The response may redirect with error due to missing GL accounts in test env,
    // but the session error must NOT contain our validation message keywords.
    $response->assertRedirect();
    $errorMessage = session('error') ?? '';
    expect($errorMessage)->not->toContain('cash shortage');
    expect($errorMessage)->not->toContain('cannot be negative');
});

it('post allows when short/excess is positive (excess cash)', function () {
    $setup = makePostingSetup();

    // Cash sales = 1500, collected = 2000 → excess of 500
    $settlement = makeDraftSettlement($setup, [
        'cash_sales_amount' => 1500,
        'cash_collected' => 2000,
        'expenses_claimed' => 0,
    ]);

    SalesSettlementCashDenomination::where('sales_settlement_id', $settlement->id)
        ->update(['denom_1000' => 2, 'total_amount' => 2000]);

    $response = $this->actingAs($setup['user'])
        ->post(route('sales-settlements.post', $settlement));

    $response->assertRedirect();
    $errorMessage = session('error') ?? '';
    expect($errorMessage)->not->toContain('cash shortage');
    expect($errorMessage)->not->toContain('cannot be negative');
});

// ──────────────────────────────────────────────────────
// Layer 2: Post — quantity integrity blocked
// ──────────────────────────────────────────────────────

it('post blocks when item quantities exceed quantity issued', function () {
    $setup = makePostingSetup();

    $settlement = makeDraftSettlement($setup);

    // Tamper: sold(8) + returned(2) + shortage(2) = 12 > issued(10)
    SalesSettlementItem::where('sales_settlement_id', $settlement->id)->update([
        'quantity_sold' => 8,
        'quantity_returned' => 2,
        'quantity_shortage' => 2,
        'quantity_issued' => 10,
    ]);

    $response = $this->actingAs($setup['user'])
        ->post(route('sales-settlements.post', $settlement));

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $settlement->refresh();
    expect($settlement->status)->toBe('draft');
});

it('post allows when item quantities exactly equal quantity issued', function () {
    $setup = makePostingSetup();

    // sold(8) + returned(1) + shortage(1) = 10 = issued(10) — exactly equal, must pass
    $settlement = makeDraftSettlement($setup);

    SalesSettlementItem::where('sales_settlement_id', $settlement->id)->update([
        'quantity_sold' => 8,
        'quantity_returned' => 1,
        'quantity_shortage' => 1,
        'quantity_issued' => 10,
    ]);

    $response = $this->actingAs($setup['user'])
        ->post(route('sales-settlements.post', $settlement));

    $response->assertRedirect();
    $errorMessage = session('error') ?? '';
    expect($errorMessage)->not->toContain('exceeds quantity issued');
});

// ──────────────────────────────────────────────────────
// Edge cases
// ──────────────────────────────────────────────────────

it('store allows when cash_sales_amount is exactly zero', function () {
    $setup = makePostingSetup();
    $customer = Customer::factory()->create();

    // Total = 1500, credit = 1500 → cash_sales = 0 — exactly zero must pass
    $response = $this->actingAs($setup['user'])->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $setup['goodsIssue']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'credit_sales' => json_encode([
            ['customer_id' => $customer->id, 'sale_amount' => 1500],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();
    $response->assertSessionMissing('error');

    $settlement = SalesSettlement::latest()->first();
    expect((float) $settlement->cash_sales_amount)->toBe(0.0);
});

it('post blocks an already-posted settlement from being posted again', function () {
    $setup = makePostingSetup();

    $settlement = makeDraftSettlement($setup, ['status' => 'posted']);

    $response = $this->actingAs($setup['user'])
        ->post(route('sales-settlements.post', $settlement));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

// ──────────────────────────────────────────────────────
// COGS (511x) accounts blocked as expenses
// ──────────────────────────────────────────────────────

it('store blocks when a COGS account (511x) is used as a settlement expense', function () {
    $setup = makePostingSetup();

    $cogsAccount = ChartOfAccount::factory()->create([
        'account_code' => '5111',
        'account_name' => 'Cost of Goods Sold',
        'is_group' => false,
        'is_active' => true,
    ]);

    $response = $this->actingAs($setup['user'])->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $setup['goodsIssue']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'expenses' => [
            ['expense_account_id' => $cogsAccount->id, 'description' => 'Test COGS', 'amount' => 50],
        ],
        'denom_5000' => 0, 'denom_1000' => 1, 'denom_500' => 1, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
    expect(SalesSettlement::count())->toBe(0);
});

it('post blocks when a COGS account (511x) already exists in expenses (defence-in-depth)', function () {
    $setup = makePostingSetup();

    $cogsAccount = ChartOfAccount::factory()->create([
        'account_code' => '5111',
        'account_name' => 'Cost of Goods Sold',
        'is_group' => false,
        'is_active' => true,
    ]);

    // Bypass Layer 1 by directly inserting a COGS expense into the DB
    $settlement = makeDraftSettlement($setup);
    \App\Models\SalesSettlementExpense::create([
        'sales_settlement_id' => $settlement->id,
        'expense_account_id' => $cogsAccount->id,
        'amount' => 31.00,
        'description' => 'Manually injected COGS',
        'expense_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($setup['user'])
        ->post(route('sales-settlements.post', $settlement));

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $settlement->refresh();
    expect($settlement->status)->toBe('draft');
});

it('store allows when a non-COGS 5xxx account is used as a settlement expense', function () {
    $setup = makePostingSetup();

    $expenseAccount = ChartOfAccount::factory()->create([
        'account_code' => '5272',
        'account_name' => 'Toll Tax',
        'is_group' => false,
        'is_active' => true,
    ]);

    $response = $this->actingAs($setup['user'])->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $setup['goodsIssue']->id,
        'items' => [[
            'product_id' => $setup['product']->id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'expenses' => [
            ['expense_account_id' => $expenseAccount->id, 'description' => 'Toll', 'amount' => 50],
        ],
        'denom_5000' => 0, 'denom_1000' => 1, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();
    $response->assertSessionMissing('error');
    expect(SalesSettlement::count())->toBe(1);
});
