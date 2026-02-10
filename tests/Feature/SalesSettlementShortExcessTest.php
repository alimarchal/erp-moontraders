<?php

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAdvanceTax;
use App\Models\SalesSettlementBankSlip;
use App\Models\SalesSettlementCashDenomination;
use App\Models\SalesSettlementExpense;
use App\Models\SalesSettlementItem;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;

it('does not double-count advance tax in short/excess calculation', function () {
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
        'quantity_issued' => 100,
        'unit_cost' => 500,
        'selling_price' => 821.6512,
        'total_value' => 82165.12,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'total_sales_amount' => 82165.12,
        'cash_sales_amount' => 52165.12,
        'credit_sales_amount' => 30000.00,
        'cheque_sales_amount' => 0,
        'bank_transfer_amount' => 0,
        'bank_slips_amount' => 30000.00,
        'cash_collected' => 20000.00,
        'expenses_claimed' => 2166.00,
        'credit_recoveries' => 0,
    ]);

    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_issued' => 100,
        'quantity_sold' => 100,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => 821.6512,
        'total_sales_value' => 82165.12,
        'unit_cost' => 500,
        'total_cogs' => 50000,
    ]);

    SalesSettlementCashDenomination::create([
        'sales_settlement_id' => $settlement->id,
        'denom_5000' => 0,
        'denom_1000' => 20,
        'denom_500' => 0,
        'denom_100' => 0,
        'denom_50' => 0,
        'denom_20' => 0,
        'denom_10' => 0,
        'denom_coins' => 0,
        'total_amount' => 20000.00,
    ]);

    $currency = \App\Models\Currency::factory()->create();

    $expenseAccount = ChartOfAccount::factory()->create([
        'account_code' => '1161',
        'account_name' => 'Advance Tax',
        'currency_id' => $currency->id,
    ]);

    $miscExpenseAccount = ChartOfAccount::factory()->create([
        'account_code' => '5262',
        'account_name' => 'AMR Liquid',
        'currency_id' => $currency->id,
    ]);

    SalesSettlementExpense::create([
        'sales_settlement_id' => $settlement->id,
        'expense_date' => now()->toDateString(),
        'expense_account_id' => $expenseAccount->id,
        'amount' => 2000.00,
        'description' => 'Advance Tax',
    ]);

    SalesSettlementExpense::create([
        'sales_settlement_id' => $settlement->id,
        'expense_date' => now()->toDateString(),
        'expense_account_id' => $miscExpenseAccount->id,
        'amount' => 166.00,
        'description' => 'AMR Liquid + Percentage',
    ]);

    $customer = Customer::factory()->create(['customer_code' => 'TC001']);
    SalesSettlementAdvanceTax::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'sale_amount' => 0,
        'tax_rate' => 0.25,
        'tax_amount' => 1000.00,
        'invoice_number' => 'ATI-00001',
    ]);

    $customer2 = Customer::factory()->create(['customer_code' => 'TC002']);
    SalesSettlementAdvanceTax::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer2->id,
        'sale_amount' => 0,
        'tax_rate' => 0.25,
        'tax_amount' => 1000.00,
        'invoice_number' => 'ATI-00002',
    ]);

    SalesSettlementBankSlip::create([
        'sales_settlement_id' => $settlement->id,
        'employee_id' => $employee->id,
        'bank_account_id' => \App\Models\BankAccount::factory()->create()->id,
        'amount' => 30000.00,
        'reference_number' => '130913',
        'deposit_date' => now()->toDateString(),
    ]);

    // Expected calculation:
    // Cash Sales = 52,165.12
    // Expected Cash Gross = 52,165.12 (no cash recoveries)
    // Total Expenses = 2,000 + 166 = 2,166 (advance tax already included in expenses)
    // Expected Cash Net = 52,165.12 - 2,166 = 49,999.12
    // Actual = Physical Cash (20,000) + Bank Slips (30,000) = 50,000
    // Short/Excess = 50,000 - 49,999.12 = +0.88 (slight excess)

    $response = $this->actingAs($user)
        ->get(route('sales-settlements.show', $settlement));

    $response->assertSuccessful();

    // The page should show 0.88 (excess), NOT the double-counted 2,000.88
    $response->assertSee('0.88');
    $response->assertDontSee('2,000.88');
});
