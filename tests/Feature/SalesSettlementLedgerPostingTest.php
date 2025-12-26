<?php

use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementBankTransfer;
use App\Models\SalesSettlementCheque;
use App\Models\SalesSettlementCreditSale;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\SalesSettlementRecovery;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('skips cheque and bank transfer entries in customer employee ledger postings', function () {
    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $goodsIssue = GoodsIssue::factory()->create([
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $customer = Customer::factory()->create([
        'customer_code' => 'CUST-'.fake()->unique()->numerify('###'),
        'customer_name' => fake()->company(),
    ]);

    $bankAccount = BankAccount::factory()->create();

    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'settlement_date' => now(),
    ]);

    SalesSettlementCheque::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'bank_account_id' => $bankAccount->id,
        'cheque_number' => 'CHQ-0001',
        'amount' => 1200,
        'bank_name' => 'HBL',
        'cheque_date' => now(),
        'status' => 'pending',
    ]);

    SalesSettlementBankTransfer::create([
        'sales_settlement_id' => $settlement->id,
        'bank_account_id' => $bankAccount->id,
        'customer_id' => $customer->id,
        'amount' => 1500,
        'reference_number' => 'TRF-0001',
        'transfer_date' => now(),
    ]);

    SalesSettlementRecovery::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'employee_id' => $employee->id,
        'recovery_number' => 'REC-0001',
        'payment_method' => 'cash',
        'amount' => 800,
    ]);

    SalesSettlementCreditSale::create([
        'sales_settlement_id' => $settlement->id,
        'customer_id' => $customer->id,
        'employee_id' => $employee->id,
        'invoice_number' => 'INV-1001',
        'sale_amount' => 2500,
    ]);

    $service = app(LedgerService::class);
    $service->processSalesSettlement($settlement->fresh());

    $entries = $settlement->customerEmployeeTransactions()->get();

    expect($entries->pluck('payment_method'))->not->toContain('cheque')
        ->and($entries->pluck('payment_method'))->not->toContain('bank_transfer')
        ->and($entries->pluck('payment_method'))->toContain('cash')
        ->and($entries->pluck('payment_method'))->toContain('credit');
});

it('posts returns to stock in hand and shortages to van stock in settlement journal entries', function () {
    $currency = Currency::factory()->base()->create([
        'currency_code' => 'PKR',
        'currency_name' => 'Pakistani Rupee',
        'currency_symbol' => 'Rs',
    ]);

    // Create accounting period for journal entries
    \App\Models\AccountingPeriod::create([
        'name' => now()->format('F Y'),
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    // Create required cost centers (used by DistributionService for journal entries)
    // DistributionService uses cost_center_id 4 and 6 for sales and warehouse operations
    \Illuminate\Support\Facades\DB::table('cost_centers')->insert([
        ['id' => 4, 'code' => 'CC004', 'name' => 'Sales & Marketing', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'code' => 'CC006', 'name' => 'Warehouse & Inventory', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $accountType = AccountType::create([
        'type_name' => 'Asset',
        'report_group' => 'BalanceSheet',
        'description' => 'Test account type',
    ]);

    ChartOfAccount::create([
        'account_code' => '1121',
        'account_name' => 'Cash',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'account_code' => '1111',
        'account_name' => 'Debtors',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $stockInHand = ChartOfAccount::create([
        'account_code' => '1151',
        'account_name' => 'Stock In Hand',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $vanStock = ChartOfAccount::create([
        'account_code' => '1155',
        'account_name' => 'Van Stock',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'account_code' => '4110',
        'account_name' => 'Sales',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'credit',
        'is_group' => false,
        'is_active' => true,
    ]);

    ChartOfAccount::create([
        'account_code' => '5111',
        'account_name' => 'COGS',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $miscExpense = ChartOfAccount::create([
        'account_code' => '5213',
        'account_name' => 'Inventory Shortage',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    $goodsIssue = GoodsIssue::factory()->create([
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'issued_by' => $user->id,
        'stock_in_hand_account_id' => $stockInHand->id,
        'van_stock_account_id' => $vanStock->id,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => $warehouse->id,
        'settlement_date' => Carbon::now(),
    ]);

    $product = Product::factory()->create();
    $item = SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_issued' => 10,
        'quantity_sold' => 0,
        'quantity_returned' => 2,
        'quantity_shortage' => 1,
        'unit_selling_price' => 120,
        'total_sales_value' => 0,
        'unit_cost' => 80,
        'total_cogs' => 0,
    ]);

    $stockBatch = StockBatch::factory()->create([
        'product_id' => $product->id,
        'unit_cost' => 80,
        'selling_price' => 120,
    ]);

    SalesSettlementItemBatch::create([
        'sales_settlement_item_id' => $item->id,
        'stock_batch_id' => $stockBatch->id,
        'batch_code' => $stockBatch->batch_code,
        'quantity_issued' => 10,
        'quantity_sold' => 0,
        'quantity_returned' => 2,
        'quantity_shortage' => 1,
        'unit_cost' => 80,
        'selling_price' => 120,
        'is_promotional' => false,
    ]);

    $service = app(DistributionService::class);
    $method = new ReflectionMethod($service, 'createSalesJournalEntry');
    $method->setAccessible(true);

    $journalEntry = $method->invoke($service, $settlement);

    $returnDebit = $journalEntry->details->firstWhere('chart_of_account_id', $stockInHand->id);
    $shortageDebit = $journalEntry->details->firstWhere('chart_of_account_id', $miscExpense->id);
    $vanStockCredits = $journalEntry->details->where('chart_of_account_id', $vanStock->id);

    expect($returnDebit)->not->toBeNull()
        ->and($returnDebit->debit)->toEqual(160.0)
        ->and($shortageDebit)->not->toBeNull()
        ->and($shortageDebit->debit)->toEqual(80.0)
        ->and($vanStockCredits->sum('credit'))->toEqual(240.0);
});
