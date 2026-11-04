<?php

use App\Models\AccountingPeriod;
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
use App\Models\SalesSettlementItem;
use App\Services\DistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Real case (settlement 2700): items sold for 20,064.80, customer paid 20,065.00 by bank transfer.
 * The extra 0.20 must land in Round Off (5271), keep Salesman Clearing (1123) at zero and keep the
 * bank line equal to what the bank statement shows.
 */
function makeRoundingScenario(float $itemsTotal, float $paid): array
{
    $currency = Currency::factory()->base()->create([
        'currency_code' => 'PKR',
        'currency_name' => 'Pakistani Rupee',
        'currency_symbol' => 'Rs',
    ]);

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

    $type = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet', 'description' => 'Test']);

    $accounts = [];
    foreach ([
        '1121' => ['Cash', 'debit'],
        '1111' => ['Debtors', 'debit'],
        '1123' => ['Salesman Clearing', 'debit'],
        '1151' => ['Stock In Hand', 'debit'],
        '1155' => ['Van Stock', 'debit'],
        '1171' => ['Bank Fallback', 'debit'],
        '1172' => ['Bank HBL', 'debit'],
        '4110' => ['Sales', 'credit'],
        '4250' => ['Settlement Excess Income', 'credit'],
        '5111' => ['COGS', 'debit'],
        '5213' => ['Inventory Shortage', 'debit'],
        '5271' => ['Round Off', 'debit'],
    ] as $code => [$name, $normal]) {
        $accounts[$code] = ChartOfAccount::create([
            'account_code' => $code,
            'account_name' => $name,
            'account_type_id' => $type->id,
            'currency_id' => $currency->id,
            'normal_balance' => $normal,
            'is_group' => false,
            'is_active' => true,
        ]);
    }

    $employee = Employee::factory()->create();
    $goodsIssue = GoodsIssue::factory()->create([
        'employee_id' => $employee->id,
        'stock_in_hand_account_id' => $accounts['1151']->id,
        'van_stock_account_id' => $accounts['1155']->id,
    ]);

    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'employee_id' => $employee->id,
        'vehicle_id' => $goodsIssue->vehicle_id,
        'warehouse_id' => $goodsIssue->warehouse_id,
        'settlement_date' => now(),
        'cash_collected' => 0,
    ]);

    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => Product::factory()->create()->id,
        'quantity_issued' => 1,
        'quantity_sold' => 1,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => $itemsTotal,
        'total_sales_value' => $itemsTotal,
        'unit_cost' => 0,
        'total_cogs' => 0,
    ]);

    $bankAccount = BankAccount::factory()->create(['chart_of_account_id' => $accounts['1172']->id]);

    SalesSettlementBankTransfer::create([
        'sales_settlement_id' => $settlement->id,
        'bank_account_id' => $bankAccount->id,
        'customer_id' => Customer::factory()->create()->id,
        'amount' => $paid,
        'reference_number' => 'TRF-ROUND',
        'transfer_date' => now(),
    ]);

    return [$settlement->fresh(), $accounts];
}

function postRoundingJournal(SalesSettlement $settlement)
{
    $service = app(DistributionService::class);
    $method = new ReflectionMethod($service, 'createSalesJournalEntry');
    $method->setAccessible(true);

    return $method->invoke($service, $settlement);
}

function validateRoundingSettlement(SalesSettlement $settlement): void
{
    $service = app(DistributionService::class);
    $method = new ReflectionMethod($service, 'validateSettlementForPosting');
    $method->setAccessible(true);
    $method->invoke($service, $settlement);
}

it('books the 0.20 payment excess of the 20,065 vs 20,064.80 case to Round Off and keeps the books balanced', function () {
    [$settlement, $accounts] = makeRoundingScenario(20064.80, 20065.00);

    validateRoundingSettlement($settlement); // must no longer block posting

    $entry = postRoundingJournal($settlement);
    $details = $entry->details;

    $net = fn (string $code, string $side) => round((float) $details->where('chart_of_account_id', $accounts[$code]->id)->sum($side), 2);

    // Bank statement shows 20,065.00 and so does the GL
    expect($net('1172', 'debit'))->toBe(20065.00)
        ->and($net('1172', 'credit'))->toBe(0.0);

    // Sales ends at exactly what the goods were worth
    expect(round($net('4110', 'credit') - $net('4110', 'debit'), 2))->toBe(20064.80);

    // The 0.20 sits in Round Off (credit = income side)
    expect($net('5271', 'credit'))->toBe(0.20)
        ->and($net('5271', 'debit'))->toBe(0.0);

    // Salesman Clearing nets to zero, no cash shortage / excess was invented
    expect(round($net('1123', 'debit') - $net('1123', 'credit'), 2))->toBe(0.0)
        ->and($net('5213', 'debit'))->toBe(0.0)
        ->and($net('4250', 'credit'))->toBe(0.0);

    // Entry balances
    expect(round((float) $details->sum('debit'), 2))->toBe(round((float) $details->sum('credit'), 2));
});

it('allows a payment excess up to Rs 5 and books it to Round Off', function () {
    [$settlement, $accounts] = makeRoundingScenario(20000.00, 20004.99);

    validateRoundingSettlement($settlement);

    $details = postRoundingJournal($settlement)->details;
    $roundOff = round((float) $details->where('chart_of_account_id', $accounts['5271']->id)->sum('credit'), 2);
    $clearing = round((float) $details->where('chart_of_account_id', $accounts['1123']->id)->sum('debit')
        - (float) $details->where('chart_of_account_id', $accounts['1123']->id)->sum('credit'), 2);

    expect($roundOff)->toBe(4.99)->and($clearing)->toBe(0.0);
});

it('still blocks a payment excess above Rs 5', function () {
    [$settlement] = makeRoundingScenario(20000.00, 20005.01);

    expect(fn () => validateRoundingSettlement($settlement))
        ->toThrow(RuntimeException::class, 'exceed the value of the goods sold');
});

it('computes the rounding helper only inside the tolerance', function () {
    expect(SalesSettlement::paymentRounding(-0.20))->toBe(0.20)
        ->and(SalesSettlement::paymentRounding(-5.00))->toBe(5.00)
        ->and(SalesSettlement::paymentRounding(-5.01))->toBe(0.0)
        ->and(SalesSettlement::paymentRounding(0.0))->toBe(0.0)
        ->and(SalesSettlement::paymentRounding(150.0))->toBe(0.0);
});
