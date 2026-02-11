<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAdvanceTax;
use App\Models\SalesSettlementCreditSale;
use App\Models\SalesSettlementPercentageExpense;
use App\Models\SalesSettlementRecovery;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;

function createSettlementPrerequisites(): array
{
    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $uom = Uom::factory()->create();
    $product = Product::factory()->create();
    $customer = Customer::factory()->create();

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

    return compact('user', 'employee', 'vehicle', 'warehouse', 'product', 'customer', 'goodsIssue');
}

function buildStorePayload(int $goodsIssueId, int $productId, int $customerId, string $date = '2026-02-11'): array
{
    return [
        'settlement_date' => $date,
        'goods_issue_id' => $goodsIssueId,
        'items' => [
            [
                'product_id' => $productId,
                'goods_issue_item_id' => null,
                'quantity_issued' => 10,
                'quantity_sold' => 8,
                'quantity_returned' => 2,
                'quantity_shortage' => 0,
                'unit_cost' => 100,
                'selling_price' => 150,
                'batches' => [],
            ],
        ],
        'credit_sales' => json_encode([
            [
                'customer_id' => $customerId,
                'invoice_number' => 'CSI-00001',
                'sale_amount' => 500,
                'payment_received' => 0,
                'previous_balance' => 0,
                'new_balance' => 500,
                'notes' => '',
            ],
        ]),
        'recoveries_entries' => json_encode([
            [
                'customer_id' => $customerId,
                'recovery_number' => 'REC-00001',
                'payment_method' => 'cash',
                'bank_account_id' => null,
                'amount' => 200,
                'previous_balance' => 1000,
                'new_balance' => 800,
                'notes' => '',
            ],
        ]),
        'advance_taxes' => [
            [
                'customer_id' => $customerId,
                'sale_amount' => 1000,
                'tax_rate' => 0.25,
                'tax_amount' => 2.50,
                'invoice_number' => 'ATI-00001',
            ],
        ],
        'percentage_expenses' => json_encode([
            [
                'customer_id' => $customerId,
                'invoice_number' => 'PEI-00001',
                'amount' => 50,
                'notes' => '',
            ],
        ]),
        'credit_sales_amount' => 500,
        'credit_recoveries_total' => 200,
        'denom_5000' => 0,
        'denom_1000' => 0,
        'denom_500' => 0,
        'denom_100' => 0,
        'denom_50' => 0,
        'denom_20' => 0,
        'denom_10' => 0,
        'denom_coins' => 0,
        'expenses' => [],
    ];
}

test('store() generates unique CSI invoice numbers with date code', function () {
    $prereqs = createSettlementPrerequisites();
    $payload = buildStorePayload(
        $prereqs['goodsIssue']->id,
        $prereqs['product']->id,
        $prereqs['customer']->id,
    );

    $this->actingAs($prereqs['user'])
        ->post(route('sales-settlements.store'), $payload)
        ->assertRedirect();

    $creditSale = SalesSettlementCreditSale::first();
    expect($creditSale)->not->toBeNull();
    expect($creditSale->invoice_number)->toMatch('/^CSI-\d{6}-\d{5}$/');
    expect($creditSale->invoice_number)->toBe('CSI-260211-00001');
});

test('store() generates unique REC recovery numbers with date code', function () {
    $prereqs = createSettlementPrerequisites();
    $payload = buildStorePayload(
        $prereqs['goodsIssue']->id,
        $prereqs['product']->id,
        $prereqs['customer']->id,
    );

    $this->actingAs($prereqs['user'])
        ->post(route('sales-settlements.store'), $payload)
        ->assertRedirect();

    $recovery = SalesSettlementRecovery::first();
    expect($recovery)->not->toBeNull();
    expect($recovery->recovery_number)->toMatch('/^REC-\d{6}-\d{5}$/');
    expect($recovery->recovery_number)->toBe('REC-260211-00001');
});

test('store() generates unique ATI invoice numbers with date code', function () {
    $prereqs = createSettlementPrerequisites();
    $payload = buildStorePayload(
        $prereqs['goodsIssue']->id,
        $prereqs['product']->id,
        $prereqs['customer']->id,
    );

    $this->actingAs($prereqs['user'])
        ->post(route('sales-settlements.store'), $payload)
        ->assertRedirect();

    $advanceTax = SalesSettlementAdvanceTax::first();
    expect($advanceTax)->not->toBeNull();
    expect($advanceTax->invoice_number)->toMatch('/^ATI-\d{6}-\d{5}$/');
    expect($advanceTax->invoice_number)->toBe('ATI-260211-00001');
});

test('store() generates unique PEI invoice numbers with date code', function () {
    $prereqs = createSettlementPrerequisites();
    $payload = buildStorePayload(
        $prereqs['goodsIssue']->id,
        $prereqs['product']->id,
        $prereqs['customer']->id,
    );

    $this->actingAs($prereqs['user'])
        ->post(route('sales-settlements.store'), $payload)
        ->assertRedirect();

    $percentageExpense = SalesSettlementPercentageExpense::first();
    expect($percentageExpense)->not->toBeNull();
    expect($percentageExpense->invoice_number)->toMatch('/^PEI-\d{6}-\d{5}$/');
    expect($percentageExpense->invoice_number)->toBe('PEI-260211-00001');
});

test('numbers increment correctly across multiple settlements', function () {
    $prereqs = createSettlementPrerequisites();

    $payload1 = buildStorePayload(
        $prereqs['goodsIssue']->id,
        $prereqs['product']->id,
        $prereqs['customer']->id,
    );

    $this->actingAs($prereqs['user'])
        ->post(route('sales-settlements.store'), $payload1)
        ->assertRedirect();

    $goodsIssue2 = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $prereqs['warehouse']->id,
        'vehicle_id' => $prereqs['vehicle']->id,
        'employee_id' => $prereqs['employee']->id,
        'issued_by' => $prereqs['user']->id,
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue2->id,
        'line_no' => 1,
        'product_id' => $prereqs['product']->id,
        'uom_id' => Uom::first()->id,
        'quantity_issued' => 5,
        'unit_cost' => 100,
        'selling_price' => 150,
        'total_value' => 750,
    ]);

    $payload2 = buildStorePayload(
        $goodsIssue2->id,
        $prereqs['product']->id,
        $prereqs['customer']->id,
    );

    $this->actingAs($prereqs['user'])
        ->post(route('sales-settlements.store'), $payload2)
        ->assertRedirect();

    $creditSales = SalesSettlementCreditSale::orderBy('id')->get();
    expect($creditSales)->toHaveCount(2);
    expect($creditSales[0]->invoice_number)->toBe('CSI-260211-00001');
    expect($creditSales[1]->invoice_number)->toBe('CSI-260211-00002');

    $recoveries = SalesSettlementRecovery::orderBy('id')->get();
    expect($recoveries)->toHaveCount(2);
    expect($recoveries[0]->recovery_number)->toBe('REC-260211-00001');
    expect($recoveries[1]->recovery_number)->toBe('REC-260211-00002');

    $advanceTaxes = SalesSettlementAdvanceTax::orderBy('id')->get();
    expect($advanceTaxes)->toHaveCount(2);
    expect($advanceTaxes[0]->invoice_number)->toBe('ATI-260211-00001');
    expect($advanceTaxes[1]->invoice_number)->toBe('ATI-260211-00002');

    $percentageExpenses = SalesSettlementPercentageExpense::orderBy('id')->get();
    expect($percentageExpenses)->toHaveCount(2);
    expect($percentageExpenses[0]->invoice_number)->toBe('PEI-260211-00001');
    expect($percentageExpenses[1]->invoice_number)->toBe('PEI-260211-00002');
});

test('numbers use different date codes for different settlement dates', function () {
    $prereqs = createSettlementPrerequisites();

    $payload = buildStorePayload(
        $prereqs['goodsIssue']->id,
        $prereqs['product']->id,
        $prereqs['customer']->id,
        '2026-03-15',
    );

    $this->actingAs($prereqs['user'])
        ->post(route('sales-settlements.store'), $payload)
        ->assertRedirect();

    $creditSale = SalesSettlementCreditSale::first();
    expect($creditSale->invoice_number)->toBe('CSI-260315-00001');

    $recovery = SalesSettlementRecovery::first();
    expect($recovery->recovery_number)->toBe('REC-260315-00001');
});

test('server overrides frontend-supplied invoice numbers', function () {
    $prereqs = createSettlementPrerequisites();
    $payload = buildStorePayload(
        $prereqs['goodsIssue']->id,
        $prereqs['product']->id,
        $prereqs['customer']->id,
    );

    $payload['credit_sales'] = json_encode([
        [
            'customer_id' => $prereqs['customer']->id,
            'invoice_number' => 'CSI-00001',
            'sale_amount' => 500,
            'payment_received' => 0,
            'previous_balance' => 0,
            'new_balance' => 500,
            'notes' => '',
        ],
    ]);

    $this->actingAs($prereqs['user'])
        ->post(route('sales-settlements.store'), $payload)
        ->assertRedirect();

    $creditSale = SalesSettlementCreditSale::first();
    expect($creditSale->invoice_number)->toMatch('/^CSI-260211-\d{5}$/');
    expect($creditSale->invoice_number)->not->toBe('CSI-00001');
});

test('database unique constraint prevents duplicate invoice numbers', function () {
    $prereqs = createSettlementPrerequisites();

    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $prereqs['goodsIssue']->id,
        'employee_id' => $prereqs['employee']->id,
        'vehicle_id' => $prereqs['vehicle']->id,
        'warehouse_id' => $prereqs['warehouse']->id,
    ]);

    SalesSettlementCreditSale::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $prereqs['customer']->id,
        'employee_id' => $prereqs['employee']->id,
        'invoice_number' => 'CSI-260211-00001',
        'sale_amount' => 100,
    ]);

    expect(fn () => SalesSettlementCreditSale::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $prereqs['customer']->id,
        'employee_id' => $prereqs['employee']->id,
        'invoice_number' => 'CSI-260211-00001',
        'sale_amount' => 200,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
