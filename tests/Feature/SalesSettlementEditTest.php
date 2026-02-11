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
use App\Models\SalesSettlementCheque;
use App\Models\SalesSettlementCreditSale;
use App\Models\SalesSettlementExpense;
use App\Models\SalesSettlementPercentageExpense;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;

test('sales settlement edit page can be rendered', function () {
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
        'quantity_issued' => 1,
        'unit_cost' => 10,
        'selling_price' => 15,
        'total_value' => 15,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->actingAs($user)
        ->get(route('sales-settlements.edit', $settlement, absolute: false))
        ->assertSuccessful()
        ->assertSee('Edit Sales Settlement');
});

test('sales settlement edit page shows credit sales with previous_balance and new_balance', function () {
    $user = User::factory()->create(['is_super_admin' => 'Yes']);

    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $uom = Uom::factory()->create();
    $product = Product::factory()->create();
    $customer = Customer::factory()->create(['customer_name' => 'Test Credit Customer', 'customer_code' => 'TC001']);

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

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
    ]);

    SalesSettlementCreditSale::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'employee_id' => $employee->id,
        'invoice_number' => 'CSI-00001',
        'sale_amount' => 5000.00,
        'payment_received' => 0,
        'previous_balance' => 2000.00,
        'new_balance' => 7000.00,
        'notes' => 'Test note',
    ]);

    $response = $this->actingAs($user)
        ->get(route('sales-settlements.edit', $settlement, absolute: false));

    $response->assertSuccessful();

    $html = $response->getContent();

    $creditSalesJson = null;
    if (preg_match('/id="credit_sales"[^>]*value=\'(\[.*?\])\'/', $html, $matches)) {
        $creditSalesJson = json_decode($matches[1], true);
    }

    expect($creditSalesJson)->not->toBeNull()
        ->and($creditSalesJson)->toHaveCount(1)
        ->and((float) $creditSalesJson[0]['previous_balance'])->toBe(2000.0)
        ->and((float) $creditSalesJson[0]['new_balance'])->toBe(7000.0)
        ->and((float) $creditSalesJson[0]['sale_amount'])->toBe(5000.0)
        ->and($creditSalesJson[0]['customer_name'])->toBe('Test Credit Customer');
});

test('can update sales settlement with percentage expenses', function () {
    $user = User::factory()->create(['is_super_admin' => 'Yes']);

    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $customer = Customer::factory()->create([
        'customer_code' => 'C001',
        'customer_name' => 'Test Customer',
    ]);

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

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'settlement_number' => 'SETTLE-2025-0001',
    ]);

    $accountType = AccountType::factory()->create();
    $currency = Currency::firstOrCreate(
        ['currency_code' => 'TST'],
        ['currency_name' => 'Test Currency', 'currency_symbol' => 'T', 'exchange_rate' => 1, 'is_base_currency' => false, 'is_active' => true]
    );

    // Create correct expense accounts
    $percentageAccount = ChartOfAccount::factory()->create([
        'id' => 76,
        'account_code' => '5223',
        'account_name' => 'Percentage Expense',
        'is_group' => false,
        'is_active' => true,
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
    ]);

    $itemData = [
        'product_id' => $product->id,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'unit_cost' => 100,
        'selling_price' => 150,
        'batches' => [], // Add this to avoid undefined array key warning if any
    ];

    $response = $this->actingAs($user)
        ->put(route('sales-settlements.update', $settlement), [
            'settlement_date' => now()->toDateString(),
            'goods_issue_id' => $goodsIssue->id,
            'items' => [$itemData],
            'expenses' => [
                [
                    'expense_account_id' => 76,
                    'amount' => 500,
                    'description' => 'Percentage Expense Total',
                ],
            ],
            'percentage_expenses' => [
                [
                    'customer_id' => $customer->id,
                    'invoice_number' => 'INV-001',
                    'amount' => 200,
                    'notes' => 'Note 1',
                ],
                [
                    'customer_id' => $customer->id,
                    'invoice_number' => 'INV-002',
                    'amount' => 300,
                    'notes' => 'Note 2',
                ],
            ],
            // Required fields for update
            'denom_5000' => 0,
            'denom_1000' => 0,
            'denom_500' => 0,
            'denom_100' => 0,
            'denom_50' => 0,
            'denom_20' => 0,
            'denom_10' => 0,
            'denom_coins' => 0,
        ]);

    $response->assertRedirect(route('sales-settlements.show', $settlement));
    $response->assertSessionHas('success');

    // Verify SalesSettlementExpense (Summary)
    $this->assertDatabaseHas('sales_settlement_expenses', [
        'sales_settlement_id' => $settlement->id,
        'expense_account_id' => 76,
        'amount' => 500,
    ]);

    // Verify SalesSettlementPercentageExpense (Details)
    $this->assertDatabaseHas('sales_settlement_percentage_expenses', [
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'PEI-260211-00001',
        'amount' => 200,
    ]);

    $this->assertDatabaseHas('sales_settlement_percentage_expenses', [
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'PEI-260211-00002',
        'amount' => 300,
    ]);
});

test('edit page shows cheques with customer_name and bank_account_name', function () {
    $user = User::factory()->create(['is_super_admin' => 'Yes']);

    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $uom = Uom::factory()->create();
    $product = Product::factory()->create();
    $customer = Customer::factory()->create([
        'customer_code' => 'CHQ-C001',
        'customer_name' => 'Cheque Customer',
    ]);
    $bankAccount = BankAccount::factory()->create([
        'account_name' => 'HBL Current',
        'bank_name' => 'HBL',
    ]);

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

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
    ]);

    SalesSettlementCheque::factory()->create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'bank_account_id' => $bankAccount->id,
        'cheque_number' => 'CHQ-000001',
        'amount' => 3000.00,
        'bank_name' => 'HBL',
        'cheque_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)
        ->get(route('sales-settlements.edit', $settlement, absolute: false));

    $response->assertSuccessful();

    $html = $response->getContent();

    $chequesJson = null;
    if (preg_match('/id="cheques"[^>]*value=\'(\[.*?\])\'/', $html, $matches)) {
        $chequesJson = json_decode($matches[1], true);
    }

    expect($chequesJson)->not->toBeNull()
        ->and($chequesJson)->toHaveCount(1)
        ->and($chequesJson[0]['customer_name'])->toBe('Cheque Customer')
        ->and($chequesJson[0]['bank_account_name'])->toBe('HBL Current')
        ->and($chequesJson[0]['cheque_number'])->toBe('CHQ-000001')
        ->and((float) $chequesJson[0]['amount'])->toBe(3000.0);
});

test('update calculates cash_sales_amount as gross cash sales not denomination total', function () {
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

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'settlement_number' => 'SETTLE-2025-9999',
    ]);

    $response = $this->actingAs($user)
        ->put(route('sales-settlements.update', $settlement), [
            'settlement_date' => now()->toDateString(),
            'goods_issue_id' => $goodsIssue->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_issued' => 10,
                    'quantity_sold' => 10,
                    'quantity_returned' => 0,
                    'unit_cost' => 100,
                    'selling_price' => 150,
                    'batches' => [],
                ],
            ],
            'credit_sales_amount' => 200,
            'cheques' => [
                [
                    'cheque_number' => 'CHQ-TEST',
                    'amount' => 300,
                    'bank_name' => 'HBL',
                    'cheque_date' => now()->toDateString(),
                ],
            ],
            'bank_transfers' => [
                [
                    'bank_account_id' => BankAccount::factory()->create()->id,
                    'amount' => 100,
                    'transfer_date' => now()->toDateString(),
                ],
            ],
            'denom_5000' => 0,
            'denom_1000' => 1,
            'denom_500' => 0,
            'denom_100' => 0,
            'denom_50' => 0,
            'denom_20' => 0,
            'denom_10' => 0,
            'denom_coins' => 0,
        ]);

    $response->assertRedirect(route('sales-settlements.show', $settlement));

    $settlement->refresh();

    // Total sales value from items = 10 * 150 = 1500
    // cash_sales_amount = totalSalesValue - credit(200) - cheques(300) - bankTransfers(100) = 900
    expect((float) $settlement->cash_sales_amount)->toBe(900.0)
        ->and((float) $settlement->cheque_sales_amount)->toBe(300.0)
        ->and((float) $settlement->bank_transfer_amount)->toBe(100.0)
        ->and((float) $settlement->credit_sales_amount)->toBe(200.0)
        ->and((float) $settlement->cash_collected)->toBe(1000.0);
});
