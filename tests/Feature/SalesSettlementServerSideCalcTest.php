<?php

use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper to create the minimal required setup for a sales settlement test.
 *
 * @return array{user: User, goodsIssue: GoodsIssue, product: Product}
 */
function createSettlementSetup(): array
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

    return compact('user', 'goodsIssue', 'product');
}

/**
 * @return array{product_id: int, quantity_issued: int, quantity_sold: int, quantity_returned: int, unit_cost: int, selling_price: int, batches: array}
 */
function defaultItemPayload(int $productId): array
{
    return [
        'product_id' => $productId,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_cost' => 100,
        'selling_price' => 150,
        'batches' => [],
    ];
}

function createBankAccount(): BankAccount
{
    $currency = Currency::where('is_base_currency', true)->first()
        ?? Currency::factory()->base()->create();

    $accountType = AccountType::firstOrCreate(
        ['type_name' => 'Asset'],
        ['report_group' => 'BalanceSheet'],
    );
    $coa = ChartOfAccount::create([
        'account_code' => 'BANK-'.fake()->unique()->numberBetween(1000, 9999),
        'account_name' => 'Test Bank',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    return BankAccount::create([
        'account_name' => 'Test Account',
        'account_number' => fake()->unique()->bankAccountNumber(),
        'bank_name' => 'Test Bank',
        'chart_of_account_id' => $coa->id,
        'is_active' => true,
    ]);
}

// ──────────────────────────────────────────────────────
// Store: tampered JS values are ignored
// ──────────────────────────────────────────────────────

it('store ignores tampered credit_sales_amount and computes from entries', function () {
    ['user' => $user, 'goodsIssue' => $gi, 'product' => $product] = createSettlementSetup();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $gi->id,
        'items' => [defaultItemPayload($product->id)],
        'credit_sales_amount' => 99999,
        'credit_sales' => json_encode([
            ['customer_id' => $customer->id, 'sale_amount' => 200],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();

    $settlement = SalesSettlement::latest()->first();
    expect((float) $settlement->credit_sales_amount)->toBe(200.0);
});

it('store ignores tampered cheque_sales_amount and computes from cheque entries', function () {
    ['user' => $user, 'goodsIssue' => $gi, 'product' => $product] = createSettlementSetup();

    $response = $this->actingAs($user)->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $gi->id,
        'items' => [defaultItemPayload($product->id)],
        'cheque_sales_amount' => 99999,
        'cheques' => json_encode([
            ['cheque_number' => 'CHQ-001', 'amount' => 500, 'bank_name' => 'HBL', 'cheque_date' => now()->toDateString()],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();

    $settlement = SalesSettlement::latest()->first();
    expect((float) $settlement->cheque_sales_amount)->toBe(500.0)
        ->and((float) $settlement->cheques_collected)->toBe(500.0);
});

it('store ignores tampered bank_transfer_amount and computes from entries', function () {
    ['user' => $user, 'goodsIssue' => $gi, 'product' => $product] = createSettlementSetup();
    $bankAccount = createBankAccount();

    $response = $this->actingAs($user)->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $gi->id,
        'items' => [defaultItemPayload($product->id)],
        'bank_transfer_amount' => 88888,
        'bank_transfers' => json_encode([
            ['bank_account_id' => $bankAccount->id, 'amount' => 300, 'reference_number' => 'TXN-001'],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();

    $settlement = SalesSettlement::latest()->first();
    expect((float) $settlement->bank_transfer_amount)->toBe(300.0);
});

it('store ignores tampered total_bank_slips and computes from slip entries', function () {
    ['user' => $user, 'goodsIssue' => $gi, 'product' => $product] = createSettlementSetup();
    $bankAccount = createBankAccount();

    $response = $this->actingAs($user)->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $gi->id,
        'items' => [defaultItemPayload($product->id)],
        'total_bank_slips' => 77777,
        'bank_slips' => json_encode([
            ['bank_account_id' => $bankAccount->id, 'amount' => 1500, 'deposit_date' => now()->toDateString()],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();

    $settlement = SalesSettlement::latest()->first();
    expect((float) $settlement->bank_slips_amount)->toBe(1500.0);
});

it('store ignores tampered cash_collected and computes from denominations', function () {
    ['user' => $user, 'goodsIssue' => $gi, 'product' => $product] = createSettlementSetup();

    $response = $this->actingAs($user)->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $gi->id,
        'items' => [defaultItemPayload($product->id)],
        'cash_collected' => 99999,
        'denom_5000' => 1,
        'denom_1000' => 2,
        'denom_500' => 0, 'denom_100' => 0, 'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();

    $settlement = SalesSettlement::latest()->first();
    expect((float) $settlement->cash_collected)->toBe(7000.0);
});

it('store computes cash_sales_amount as residual of total sales minus other payment methods', function () {
    ['user' => $user, 'goodsIssue' => $gi, 'product' => $product] = createSettlementSetup();
    $customer = Customer::factory()->create();
    $bankAccount = createBankAccount();

    $response = $this->actingAs($user)->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $gi->id,
        'items' => [defaultItemPayload($product->id)],
        'cash_sales_amount' => 99999,
        'credit_sales' => json_encode([
            ['customer_id' => $customer->id, 'sale_amount' => 200],
        ]),
        'cheques' => json_encode([
            ['cheque_number' => 'CHQ-002', 'amount' => 300, 'bank_name' => 'MCB', 'cheque_date' => now()->toDateString()],
        ]),
        'bank_transfers' => json_encode([
            ['bank_account_id' => $bankAccount->id, 'amount' => 100, 'reference_number' => 'TXN-002'],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();

    $settlement = SalesSettlement::latest()->first();
    // Items: 10 * 150 = 1500 total sales. cash = 1500 - 200(credit) - 300(cheques) - 100(bank_t) = 900
    expect((float) $settlement->cash_sales_amount)->toBe(900.0)
        ->and((float) $settlement->total_sales_amount)->toBe(1500.0)
        ->and((float) $settlement->gross_profit)->toBe(500.0);
});

// ──────────────────────────────────────────────────────
// Update: tampered JS values are ignored
// ──────────────────────────────────────────────────────

it('update ignores tampered credit_sales_amount and computes from entries', function () {
    ['user' => $user, 'goodsIssue' => $gi, 'product' => $product] = createSettlementSetup();
    $customer = Customer::factory()->create();

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $gi->id,
        'employee_id' => $gi->employee_id,
        'vehicle_id' => $gi->vehicle_id,
        'warehouse_id' => $gi->warehouse_id,
        'settlement_number' => 'SETTLE-2025-9990',
    ]);

    $response = $this->actingAs($user)->put(route('sales-settlements.update', $settlement), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $gi->id,
        'items' => [defaultItemPayload($product->id)],
        'credit_sales_amount' => 99999,
        'credit_sales' => json_encode([
            ['customer_id' => $customer->id, 'sale_amount' => 350],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();

    $settlement->refresh();
    expect((float) $settlement->credit_sales_amount)->toBe(350.0);
});

it('update ignores tampered bank_slips_amount and computes from entries', function () {
    ['user' => $user, 'goodsIssue' => $gi, 'product' => $product] = createSettlementSetup();
    $bankAccount = createBankAccount();

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $gi->id,
        'employee_id' => $gi->employee_id,
        'vehicle_id' => $gi->vehicle_id,
        'warehouse_id' => $gi->warehouse_id,
        'settlement_number' => 'SETTLE-2025-9991',
    ]);

    $response = $this->actingAs($user)->put(route('sales-settlements.update', $settlement), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $gi->id,
        'items' => [defaultItemPayload($product->id)],
        'total_bank_slips' => 99999,
        'bank_slips' => json_encode([
            ['bank_account_id' => $bankAccount->id, 'amount' => 2500, 'deposit_date' => now()->toDateString()],
        ]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ]);

    $response->assertRedirect();

    $settlement->refresh();
    expect((float) $settlement->bank_slips_amount)->toBe(2500.0);
});

it('store and update produce identical totals for same input', function () {
    ['user' => $user, 'goodsIssue' => $gi, 'product' => $product] = createSettlementSetup();
    $customer = Customer::factory()->create();
    $bankAccount = createBankAccount();

    $makePayload = fn (int $giId) => [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $giId,
        'items' => [defaultItemPayload($product->id)],
        'credit_sales' => json_encode([
            ['customer_id' => $customer->id, 'sale_amount' => 200],
        ]),
        'cheques' => json_encode([
            ['cheque_number' => 'CHQ-003', 'amount' => 300, 'bank_name' => 'ABL', 'cheque_date' => now()->toDateString()],
        ]),
        'bank_transfers' => json_encode([
            ['bank_account_id' => $bankAccount->id, 'amount' => 100],
        ]),
        'denom_5000' => 1,
        'denom_1000' => 3,
        'denom_500' => 0, 'denom_100' => 0, 'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ];

    $storeResponse = $this->actingAs($user)->post(route('sales-settlements.store'), $makePayload($gi->id));
    $storeResponse->assertRedirect();
    $stored = SalesSettlement::latest()->first();

    $gi2 = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $gi->warehouse_id,
        'vehicle_id' => $gi->vehicle_id,
        'employee_id' => $gi->employee_id,
        'issued_by' => $user->id,
    ]);
    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $gi2->id,
        'line_no' => 1,
        'product_id' => $product->id,
        'uom_id' => Uom::first()->id,
        'quantity_issued' => 10,
        'unit_cost' => 100,
        'selling_price' => 150,
        'total_value' => 1500,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $gi2->id,
        'employee_id' => $gi2->employee_id,
        'vehicle_id' => $gi2->vehicle_id,
        'warehouse_id' => $gi2->warehouse_id,
        'settlement_number' => 'SETTLE-2025-9992',
    ]);

    $updateResponse = $this->actingAs($user)->put(route('sales-settlements.update', $settlement), $makePayload($gi2->id));
    $updateResponse->assertRedirect();
    $settlement->refresh();

    $fields = [
        'total_sales_amount', 'total_cogs', 'gross_profit',
        'credit_sales_amount', 'cheque_sales_amount', 'bank_transfer_amount',
        'bank_slips_amount', 'cash_sales_amount', 'cash_collected',
        'cheques_collected', 'cash_to_deposit',
    ];

    foreach ($fields as $field) {
        expect((float) $settlement->$field)
            ->toBe((float) $stored->$field, "Mismatch on {$field}: store={$stored->$field} update={$settlement->$field}");
    }
});
