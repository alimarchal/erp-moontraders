<?php

use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCashDenomination;
use App\Models\SalesSettlementExpense;
use App\Models\SalesSettlementItem;
use App\Models\SchemeReceived;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-audit-daily-business-summary', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'report-audit-daily-business-summary-all-companies', 'guard_name' => 'web']);

    $this->supplier = Supplier::factory()->create(['supplier_name' => 'Nestlé Pakistan', 'short_name' => 'Nestle', 'disabled' => false]);
    $this->otherSupplier = Supplier::factory()->create(['supplier_name' => 'Engro Foods', 'short_name' => 'Engro', 'disabled' => false]);

    $this->user = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->user->givePermissionTo('report-audit-daily-business-summary');
    $this->actingAs($this->user);
});

/**
 * One posted settlement for a salesman on the given date, with an item, the expense lines and the cash counted.
 *
 * @param  array<string, float>  $expenses  account code => amount
 */
function dailySummarySettlement(Employee $employee, string $date, float $sales, array $expenses, float $cash, float $bankSlips, ?Product $product = null): SalesSettlement
{
    $settlement = SalesSettlement::factory()->create([
        'supplier_id' => $employee->supplier_id,
        'employee_id' => $employee->id,
        'settlement_date' => $date,
        'status' => 'posted',
        'total_sales_amount' => $sales,
        'cash_collected' => $cash,
        'bank_slips_amount' => $bankSlips,
    ]);

    if ($product) {
        SalesSettlementItem::factory()->create([
            'sales_settlement_id' => $settlement->id,
            'product_id' => $product->id,
            'total_sales_value' => $sales,
        ]);
    }

    foreach ($expenses as $code => $amount) {
        $account = ChartOfAccount::query()->where('account_code', $code)->first()
            ?? ChartOfAccount::factory()->create(['account_code' => $code]);

        SalesSettlementExpense::create([
            'sales_settlement_id' => $settlement->id,
            'expense_account_id' => $account->id,
            'amount' => $amount,
            'expense_date' => $date,
        ]);
    }

    SalesSettlementCashDenomination::create(['sales_settlement_id' => $settlement->id, 'total_amount' => $cash]);

    return $settlement;
}

it('is empty until a company is chosen in the filters', function () {
    $this->get(route('reports.daily-business-summary.index'))
        ->assertOk()
        ->assertSee('No company selected')
        ->assertSee('Help: Daily Business Summary')
        ->assertSee('یہ رپورٹ کیا ہے؟')
        ->assertDontSee('DAILY SALESMAN-WISE PERFORMANCE');
});

it('is forbidden without the report permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reports.daily-business-summary.index'))
        ->assertForbidden();
});

it('builds the salesman rows, month-to-date sales by category and the TTS balance for one company', function () {
    $nutrition = Product::factory()->create(['supplier_id' => $this->supplier->id, 'category_id' => Category::create(['name' => 'Nutrition', 'slug' => 'nutrition'])->id]);
    $dairy = Product::factory()->create(['supplier_id' => $this->supplier->id, 'category_id' => Category::create(['name' => 'Dairy', 'slug' => 'dairy'])->id]);

    $salesman = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'designation' => 'Salesman', 'name' => 'Tanveer Malik']);
    $otherCompanySalesman = Employee::factory()->create(['supplier_id' => $this->otherSupplier->id, 'designation' => 'Salesman', 'name' => 'Engro Salesman']);

    // Report day: advance tax 1,588 + scheme 1,305 + Promotion OFF 250 = discount 3,143; amount = cash 34,500 + slips 70,990.
    // The unused 5287 "Promotion Passed" account is not read.
    dailySummarySettlement($salesman, '2026-11-14', 127581.48, ['1161' => 1588, '5292' => 1305, '5288' => 250, '5287' => 9999, '5223' => 400], 34500, 70990, $nutrition);
    // Earlier in the month: counts towards month-to-date sales and TTS, not the salesman row.
    dailySummarySettlement($salesman, '2026-11-02', 50000, ['5292' => 700, '5223' => 100], 50000, 0, $dairy);
    // Last month and another company: left out.
    dailySummarySettlement($salesman, '2026-10-31', 99999, ['5292' => 999], 99999, 0, $dairy);
    dailySummarySettlement($otherCompanySalesman, '2026-11-14', 77777, [], 77777, 0);

    SchemeReceived::create(['supplier_id' => $this->supplier->id, 'category' => 'tts_received', 'transaction_date' => '2026-11-05', 'amount' => 5000, 'is_active' => true]);

    $response = $this->get(route('reports.daily-business-summary.index', ['date' => '2026-11-14', 'supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->assertDontSee('Engro Salesman');

    $report = $response->viewData('report');
    $row = $report['salesmen']->firstWhere('name', 'Tanveer Malik');

    expect($row->sales)->toBe(127581.48)
        ->and($row->percentage)->toBe(1588.0)
        ->and($row->scheme)->toBe(1555.0)
        ->and($row->discount)->toBe(3143.0)
        ->and($row->cash)->toBe(105490.0)
        ->and($report['total_sales_mtd'])->toBe(177581.48)
        ->and($report['categories']->pluck('amount', 'name')->all())->toBe(['Nutrition' => 127581.48, 'Dairy' => 50000.0])
        ->and($report['category_groups'])->toBe(['F&B' => 50000.0, 'Nutrition' => 127581.48, 'Water' => 0.0])
        ->and($report['tts']['tts_received'])->toBe(5000.0)
        ->and($report['tts']['tts_passed'])->toBe(2005.0)
        ->and($report['tts']['promo_passed'])->toBe(250.0)
        ->and($report['tts']['total_passed'])->toBe(2255.0)
        ->and($report['tts']['percentage_passed'])->toBe(500.0)
        ->and($report['tts']['balance'])->toBe(2245.0);
});

it('adds every company together for all companies', function () {
    $this->user->givePermissionTo('report-audit-daily-business-summary-all-companies');

    $nestle = Employee::factory()->create(['supplier_id' => $this->supplier->id, 'designation' => 'Salesman', 'name' => 'Nestle Salesman']);
    $engro = Employee::factory()->create(['supplier_id' => $this->otherSupplier->id, 'designation' => 'Salesman', 'name' => 'Engro Salesman']);
    dailySummarySettlement($nestle, '2026-11-14', 1000, [], 600, 0);
    dailySummarySettlement($engro, '2026-11-14', 2000, [], 1500, 200);

    $response = $this->get(route('reports.daily-business-summary.index', ['date' => '2026-11-14', 'supplier_id' => 'all']))
        ->assertOk()
        ->assertSee('All Companies')
        ->assertSee('Nestle Salesman')
        ->assertSee('Engro Salesman');

    expect($response->viewData('report')['totals']['sales'])->toBe(3000.0)
        ->and($response->viewData('report')['totals']['cash'])->toBe(2300.0);
});

it('keeps users without the all-companies permission to their own company', function () {
    $this->get(route('reports.daily-business-summary.index', ['supplier_id' => $this->otherSupplier->id]))->assertForbidden();
    $this->get(route('reports.daily-business-summary.index', ['supplier_id' => 'all']))->assertForbidden();
    $this->get(route('reports.daily-business-summary.index', ['supplier_id' => $this->supplier->id]))->assertOk();
});
