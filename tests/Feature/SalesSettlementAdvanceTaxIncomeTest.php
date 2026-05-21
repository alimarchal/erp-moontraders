<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAdvanceTax;
use App\Models\SalesSettlementAdvanceTaxIncome;
use App\Models\SalesSettlementCashDenomination;
use App\Models\SalesSettlementExpense;
use App\Models\SalesSettlementItem;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createAdvanceTaxIncomeAccount(string $accountCode = '1161', string $accountName = 'Advance Tax'): ChartOfAccount
{
    $currency = Currency::factory()->create();
    $accountType = AccountType::factory()->create();

    return ChartOfAccount::factory()->create([
        'account_code' => $accountCode,
        'account_name' => $accountName,
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => str_starts_with($accountCode, '4') ? 'credit' : 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);
}

function createAdvanceTaxIncomeStorePayload(GoodsIssue $goodsIssue, Product $product, Customer $customer, ChartOfAccount $advanceTaxAccount): array
{
    return [
        'settlement_date' => '2026-05-21',
        'goods_issue_id' => $goodsIssue->id,
        'items' => [
            [
                'product_id' => $product->id,
                'goods_issue_item_id' => null,
                'quantity_issued' => 10,
                'quantity_sold' => 10,
                'quantity_returned' => 0,
                'quantity_shortage' => 0,
                'unit_cost' => 50,
                'selling_price' => 100,
                'batches' => [],
            ],
        ],
        'advance_taxes' => [
            [
                'customer_id' => $customer->id,
                'sale_amount' => 1000,
                'tax_rate' => 0.25,
                'tax_amount' => 4033,
                'invoice_number' => 'ATI-FRONTEND',
            ],
        ],
        'expenses' => [
            [
                'expense_account_id' => $advanceTaxAccount->id,
                'description' => 'Advance Tax',
                'amount' => 4033,
            ],
        ],
        'credit_sales' => [],
        'recoveries_entries' => [],
        'bank_transfers' => [],
        'bank_slips' => [],
        'cheques' => [],
        'percentage_expenses' => [],
        'denom_5000' => 0,
        'denom_1000' => 0,
        'denom_500' => 0,
        'denom_100' => 0,
        'denom_50' => 0,
        'denom_20' => 0,
        'denom_10' => 0,
        'denom_coins' => 0,
    ];
}

function createAdvanceTaxIncomePrerequisites(bool $usesAdvanceTaxIncome): array
{
    $supplier = Supplier::factory()->create([
        'supplier_name' => $usesAdvanceTaxIncome ? 'Kausar Oil & Ghee' : 'Normal Supplier',
        'is_advance_tax_income' => $usesAdvanceTaxIncome,
    ]);
    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    $employee = Employee::factory()->create(['supplier_id' => $supplier->id]);
    $vehicle = Vehicle::factory()->create(['supplier_id' => $supplier->id]);
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);
    $customer = Customer::factory()->create();
    $uom = Uom::factory()->create();

    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'supplier_id' => $supplier->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'issued_by' => $user->id,
    ]);

    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'line_no' => 1,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'quantity_issued' => 10,
        'unit_cost' => 50,
        'selling_price' => 100,
        'total_value' => 1000,
    ]);

    return compact('supplier', 'user', 'employee', 'vehicle', 'warehouse', 'product', 'customer', 'goodsIssue');
}

it('defaults suppliers to not treat advance tax as income', function () {
    $supplier = Supplier::factory()->create();

    expect($supplier->is_advance_tax_income)->toBeFalse();
});

it('keeps normal supplier advance tax storage unchanged', function () {
    $advanceTaxAccount = createAdvanceTaxIncomeAccount();
    $data = createAdvanceTaxIncomePrerequisites(false);
    $payload = createAdvanceTaxIncomeStorePayload($data['goodsIssue'], $data['product'], $data['customer'], $advanceTaxAccount);

    $this->actingAs($data['user'])
        ->post(route('sales-settlements.store'), $payload)
        ->assertRedirect();

    expect(SalesSettlementAdvanceTax::count())->toBe(1)
        ->and(SalesSettlementAdvanceTaxIncome::count())->toBe(0)
        ->and(SalesSettlementExpense::where('expense_account_id', $advanceTaxAccount->id)->count())->toBe(1);
});

it('stores flagged supplier advance tax in the income table only', function () {
    $advanceTaxAccount = createAdvanceTaxIncomeAccount();
    $data = createAdvanceTaxIncomePrerequisites(true);
    $payload = createAdvanceTaxIncomeStorePayload($data['goodsIssue'], $data['product'], $data['customer'], $advanceTaxAccount);

    $this->actingAs($data['user'])
        ->post(route('sales-settlements.store'), $payload)
        ->assertRedirect();

    expect(SalesSettlementAdvanceTax::count())->toBe(0)
        ->and(SalesSettlementAdvanceTaxIncome::count())->toBe(1)
        ->and(SalesSettlementExpense::where('expense_account_id', $advanceTaxAccount->id)->count())->toBe(0);
});

it('uses advance tax income to reduce flagged supplier short excess to zero', function () {
    $data = createAdvanceTaxIncomePrerequisites(true);

    $settlement = SalesSettlement::factory()->create([
        'supplier_id' => $data['supplier']->id,
        'goods_issue_id' => $data['goodsIssue']->id,
        'employee_id' => $data['employee']->id,
        'vehicle_id' => $data['vehicle']->id,
        'warehouse_id' => $data['warehouse']->id,
        'cash_sales_amount' => 130914,
        'cash_collected' => 110000,
        'expenses_claimed' => 24947,
    ]);

    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $data['product']->id,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => 13091.40,
        'total_sales_value' => 130914,
        'unit_cost' => 50,
        'total_cogs' => 500,
    ]);

    SalesSettlementCashDenomination::create([
        'sales_settlement_id' => $settlement->id,
        'denom_5000' => 22,
        'denom_1000' => 0,
        'denom_500' => 0,
        'denom_100' => 0,
        'denom_50' => 0,
        'denom_20' => 0,
        'denom_10' => 0,
        'denom_coins' => 0,
        'total_amount' => 110000,
    ]);

    SalesSettlementExpense::create([
        'sales_settlement_id' => $settlement->id,
        'expense_date' => now()->toDateString(),
        'expense_account_id' => createAdvanceTaxIncomeAccount('5292', 'Scheme Discount Expense')->id,
        'amount' => 24947,
        'description' => 'Normal expenses',
    ]);

    SalesSettlementAdvanceTaxIncome::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $data['customer']->id,
        'sale_amount' => 0,
        'tax_rate' => 0.25,
        'tax_amount' => 4033,
        'invoice_number' => 'ATI-260521-00001',
    ]);

    $response = $this->actingAs($data['user'])
        ->get(route('sales-settlements.show', $settlement));

    $response->assertSuccessful()
        ->assertSee($data['supplier']->supplier_name)
        ->assertSee('Advance Tax - Income')
        ->assertSee('4,033.00')
        ->assertSee('24,947.00')
        ->assertSee('0.00')
        ->assertDontSee('8,066.00');
});

it('credits settlement excess income when posting flagged supplier advance tax income', function () {
    $currency = Currency::factory()->base()->create(['currency_code' => 'PKR']);
    $accountType = AccountType::factory()->create();

    foreach ([
        ['1121', 'Cash', 'debit'],
        ['1111', 'Debtors', 'debit'],
        ['1123', 'Salesman Clearing', 'debit'],
        ['1151', 'Stock In Hand', 'debit'],
        ['1155', 'Van Stock', 'debit'],
        ['4110', 'Sales', 'credit'],
        ['5111', 'COGS', 'debit'],
        ['5213', 'Inventory Shortage', 'debit'],
        ['4250', 'Settlement Excess Income', 'credit'],
    ] as [$code, $name, $normalBalance]) {
        ChartOfAccount::create([
            'account_code' => $code,
            'account_name' => $name,
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'normal_balance' => $normalBalance,
            'is_group' => false,
            'is_active' => true,
        ]);
    }

    AccountingPeriod::create([
        'name' => now()->format('F Y'),
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    DB::table('cost_centers')->insert([
        ['id' => 4, 'code' => 'CC004', 'name' => 'Sales & Marketing', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'code' => 'CC006', 'name' => 'Warehouse & Inventory', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $data = createAdvanceTaxIncomePrerequisites(true);
    $settlement = SalesSettlement::factory()->create([
        'supplier_id' => $data['supplier']->id,
        'goods_issue_id' => $data['goodsIssue']->id,
        'employee_id' => $data['employee']->id,
        'vehicle_id' => $data['vehicle']->id,
        'warehouse_id' => $data['warehouse']->id,
        'settlement_date' => now(),
        'cash_sales_amount' => 0,
    ]);

    SalesSettlementAdvanceTaxIncome::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $data['customer']->id,
        'tax_amount' => 4033,
        'invoice_number' => 'ATI-260521-00001',
    ]);

    $service = app(DistributionService::class);
    $method = new ReflectionMethod($service, 'createSalesJournalEntry');
    $method->setAccessible(true);

    $journalEntry = $method->invoke($service, $settlement);
    $incomeAccount = ChartOfAccount::where('account_code', '4250')->first();
    $incomeLine = $journalEntry->details->firstWhere('chart_of_account_id', $incomeAccount->id);

    expect($incomeLine)->not->toBeNull()
        ->and((float) $incomeLine->credit)->toEqual(4033.0);
});
