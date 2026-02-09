<?php

use App\Models\AccountingPeriod;
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
use App\Models\SalesSettlementBankSlip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);

    // Create accounting period for current year
    AccountingPeriod::create([
        'name' => 'FY 2026',
        'start_date' => now()->startOfYear()->format('Y-m-d'),
        'end_date' => now()->endOfYear()->format('Y-m-d'),
        'status' => AccountingPeriod::STATUS_OPEN,
    ]);
});

it('validation fails if bank slips are invalid', function () {
    $settlement = SalesSettlement::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($this->user)
        ->postJson(route('sales-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'goods_issue_id' => $settlement->goods_issue_id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_issued' => 10,
                    'quantity_sold' => 10,
                    'unit_cost' => 100,
                    'batches' => [],
                ]
            ],
            'bank_slips' => json_encode([['amount' => 5000]]), // Missing bank_account_id
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['bank_slips.0.bank_account_id']);
});

it('can store sales settlement with bank slips', function () {
    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();

    // Create necessary COA for Bank Account
    $currency = Currency::factory()->base()->create();
    $accountType = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);
    $bankInfoCoa = ChartOfAccount::create([
        'account_code' => '1001',
        'account_name' => 'Bank Al-Habib',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    // Manually create Bank Account
    $bankAccount = BankAccount::create([
        'account_name' => 'Main Account',
        'account_number' => '1234567890',
        'bank_name' => 'Bank Al-Habib',
        'chart_of_account_id' => $bankInfoCoa->id,
        'is_active' => true,
    ]);

    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
    ]);

    $product = Product::factory()->create();

    // Create Items array
    $items = [
        [
            'product_id' => $product->id,
            'quantity_issued' => 10,
            'quantity_sold' => 5,
            'quantity_returned' => 5,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]
    ];

    $bankSlipsData = [
        [
            'bank_account_id' => $bankAccount->id,
            'amount' => 5000,
            'reference_number' => 'REF-123',
            'deposit_date' => now()->toDateString(),
            'notes' => 'Test Deposit',
        ]
    ];

    $response = $this->actingAs($this->user)
        ->postJson(route('sales-settlements.store'), [
            'settlement_date' => now()->toDateString(),
            'goods_issue_id' => $goodsIssue->id,
            'items' => $items,
            'bank_slips' => json_encode($bankSlipsData),
            'total_bank_slips' => 5000,
            'sales' => [], // Required
            // Provide default values for other required numeric fields
            'cash_sales_amount' => 0,
            'cheque_sales_amount' => 0,
            'bank_transfer_amount' => 0,
            'credit_sales_amount' => 0,
            'cash_collected' => 0,
            'cheques_collected' => 0,
            'expenses_claimed' => 0,
            'denom_5000' => 0,
            'denom_1000' => 0,
            'denom_500' => 0,
            'denom_100' => 0,
            'denom_50' => 0,
            'denom_20' => 0,
            'denom_10' => 0,
            'denom_coins' => 0,
        ]);

    $response->assertStatus(302); // Redirects on success

    $settlement = SalesSettlement::latest()->first();

    $this->assertDatabaseHas('sales_settlements', [
        'id' => $settlement->id,
        'bank_slips_amount' => 5000,
    ]);

    $this->assertDatabaseHas('sales_settlement_bank_slips', [
        'sales_settlement_id' => $settlement->id,
        'bank_account_id' => $bankAccount->id,
        'amount' => 5000,
        'reference_number' => 'REF-123',
    ]);
});

it('posts bank slips to journal entries correctly', function () {
    // Setup Chart of Accounts
    $currency = Currency::factory()->base()->create();
    $accountType = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);

    $accounts = [
        'salesman_clearing' => ChartOfAccount::create([
            'account_code' => '1123',
            'account_name' => 'Salesman Clearing',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'normal_balance' => 'debit',
            'is_active' => true
        ]),
        'bank' => ChartOfAccount::create([
            'account_code' => '1001',
            'account_name' => 'Bank Al-Habib',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'normal_balance' => 'debit',
            'is_active' => true
        ]),
        // Create other required accounts to avoid validation errors in DistributionService
        'cash' => ChartOfAccount::create(['account_code' => '1121', 'account_name' => 'Cash', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_active' => true]),
        'debtors' => ChartOfAccount::create(['account_code' => '1111', 'account_name' => 'Debtors', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_active' => true]),
        'sales' => ChartOfAccount::create(['account_code' => '4110', 'account_name' => 'Sales', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'normal_balance' => 'credit', 'is_active' => true]),
        'stock_in_hand' => ChartOfAccount::create(['account_code' => '1151', 'account_name' => 'Stock In Hand', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_active' => true]),
        'van_stock' => ChartOfAccount::create(['account_code' => '1155', 'account_name' => 'Van Stock', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_active' => true]),
        'cogs' => ChartOfAccount::create(['account_code' => '5111', 'account_name' => 'COGS', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_active' => true]),
        'misc_expense' => ChartOfAccount::create(['account_code' => '5213', 'account_name' => 'Misc Expense', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_active' => true]),
        'cheques_in_hand' => ChartOfAccount::create(['account_code' => '1122', 'account_name' => 'Cheques In Hand', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_active' => true]),
        'advance_tax' => ChartOfAccount::create(['account_code' => '2130', 'account_name' => 'Advance Tax', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'normal_balance' => 'credit', 'is_active' => true]),
    ];

    // Create required cost centers
    DB::table('cost_centers')->insertOrIgnore([
        ['id' => 4, 'code' => 'CC004', 'name' => 'Sales', 'type' => 'cost_center', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'code' => 'CC006', 'name' => 'Warehouse', 'type' => 'cost_center', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Create Bank Account
    $bankAccount = BankAccount::create([
        'account_name' => 'Main Account',
        'account_number' => '1234567890',
        'bank_name' => 'Bank Al-Habib',
        'chart_of_account_id' => $accounts['bank']->id,
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => $warehouse->id,
        'vehicle_id' => $vehicle->id,
        'employee_id' => $employee->id,
        'stock_in_hand_account_id' => $accounts['stock_in_hand']->id,
        'van_stock_account_id' => $accounts['van_stock']->id,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'bank_slips_amount' => 5000,
    ]);

    // Add Bank Slip
    SalesSettlementBankSlip::create([
        'sales_settlement_id' => $settlement->id,
        'employee_id' => $employee->id,
        'bank_account_id' => $bankAccount->id,
        'amount' => 5000,
        'reference_number' => 'SLIP-001',
        'deposit_date' => now(),
    ]);

    // Post Settlement
    $service = app(DistributionService::class);

    try {
        $result = $service->postSalesSettlement($settlement);
    } catch (\Exception $e) {
        throw $e;
    }

    expect($result['success'])->toBeTrue();

    $settlement->refresh();
    expect($settlement->status)->toBe('posted');
    expect($settlement->journal_entry_id)->not->toBeNull();

    // Verify Journal Entry Lines
    $journalEntry = \App\Models\JournalEntry::with('details')->find($settlement->journal_entry_id);

    if (!$journalEntry->details->where('chart_of_account_id', $accounts['bank']->id)->first()) {
        dump("Expected Account ID: " . $accounts['bank']->id);
        dump("Failing Accounts Search: " . $journalEntry->details->pluck('chart_of_account_id')->implode(', '));
        dump($journalEntry->details->toArray());
    }

    // Check for Bank Debit
    $bankDebit = $journalEntry->details->where('chart_of_account_id', $accounts['bank']->id)->first();
    expect($bankDebit)->not->toBeNull();
    expect($bankDebit->debit)->toEqual(5000);
    expect($bankDebit->credit)->toEqual(0);

    // Check for Salesman Clearing Credit
    $clearingCredit = $journalEntry->details
        ->where('chart_of_account_id', $accounts['salesman_clearing']->id)
        ->where('credit', 5000)
        ->first();

    expect($clearingCredit)->not->toBeNull();
    expect($clearingCredit->debit)->toEqual(0);
});
