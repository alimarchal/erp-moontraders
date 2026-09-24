<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Product;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

describe('van stock report', function () {
    beforeEach(function () {
        Permission::firstOrCreate(['name' => 'report-sales-daily-sales', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('report-sales-daily-sales');
        $this->actingAs($this->user);
    });

    it('names the member from the goods issue when the vehicle has no assigned employee', function () {
        $employee = Employee::factory()->create(['name' => 'Zeeshan Ismail']);
        $vehicle = Vehicle::factory()->create(['employee_id' => null]);
        $warehouse = Warehouse::factory()->create(['disabled' => false]);
        $product = Product::factory()->create();
        $uom = Uom::factory()->create();

        $goodsIssue = GoodsIssue::factory()->create([
            'warehouse_id' => $warehouse->id,
            'vehicle_id' => $vehicle->id,
            'employee_id' => $employee->id,
            'issued_by' => $this->user->id,
        ]);

        $item = GoodsIssueItem::create([
            'goods_issue_id' => $goodsIssue->id,
            'line_no' => 1,
            'product_id' => $product->id,
            'quantity_issued' => 10,
            'unit_cost' => 100,
            'selling_price' => 150,
            'uom_id' => $uom->id,
            'total_value' => 1500,
        ]);

        DB::table('van_stock_batches')->insert([
            'vehicle_id' => $vehicle->id,
            'product_id' => $product->id,
            'goods_issue_item_id' => $item->id,
            'goods_issue_number' => $goodsIssue->issue_number,
            'quantity_on_hand' => 10,
            'unit_cost' => 100,
            'selling_price' => 150,
        ]);

        $response = $this->get(route('reports.daily-sales.van-stock'));

        $response->assertOk()->assertSee('Zeeshan Ismail');
    });

    it('totals the quantity column as well as the value', function () {
        $vehicle = Vehicle::factory()->create();
        $warehouse = Warehouse::factory()->create(['disabled' => false]);
        $uom = Uom::factory()->create();

        $goodsIssue = GoodsIssue::factory()->create([
            'warehouse_id' => $warehouse->id,
            'vehicle_id' => $vehicle->id,
            'employee_id' => Employee::factory()->create()->id,
            'issued_by' => $this->user->id,
        ]);

        foreach ([[10, 100], [25, 40]] as $line => [$quantity, $unitCost]) {
            $item = GoodsIssueItem::create([
                'goods_issue_id' => $goodsIssue->id,
                'line_no' => $line + 1,
                'product_id' => Product::factory()->create()->id,
                'quantity_issued' => $quantity,
                'unit_cost' => $unitCost,
                'selling_price' => $unitCost + 50,
                'uom_id' => $uom->id,
                'total_value' => $quantity * ($unitCost + 50),
            ]);

            DB::table('van_stock_batches')->insert([
                'vehicle_id' => $vehicle->id,
                'product_id' => $item->product_id,
                'goods_issue_item_id' => $item->id,
                'goods_issue_number' => $goodsIssue->issue_number,
                'quantity_on_hand' => $quantity,
                'unit_cost' => $unitCost,
                'selling_price' => $unitCost + 50,
            ]);
        }

        $response = $this->get(route('reports.daily-sales.van-stock'));

        // The footer used to span the Qty column to reach the value, so the report
        // showed what the stock on the vans was worth but never how much of it
        // there was. 10 + 25 units, worth 10 × 100 + 25 × 40.
        $response->assertOk()
            ->assertSee('35.00')
            ->assertSee('2,000.00');
    });
});

describe('account balances report', function () {
    beforeEach(function () {
        Permission::firstOrCreate(['name' => 'report-financial-account-balances', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('report-financial-account-balances');
        $this->actingAs($this->user);

        Currency::factory()->base()->create();
    });

    it('falls back to today when as_of_date is not a date', function () {
        // The value reaches a raw SQL aggregate, so anything that is not a date must not
        // survive long enough to change the query.
        $response = $this->get(route('reports.account-balances.index', [
            'as_of_date' => "2026-01-01' OR '1'='1",
        ]));

        $response->assertOk();
    });

    it('renders for a valid as_of_date', function () {
        $response = $this->get(route('reports.account-balances.index', ['as_of_date' => '2026-06-30']));

        $response->assertOk();
    });
});

function makeUnbalancedPostedEntry(): array
{
    $currency = Currency::factory()->base()->create();
    $expenseType = AccountType::create([
        'type_name' => 'Expense', 'report_group' => 'IncomeStatement', 'description' => 'Expense',
    ]);
    $assetType = AccountType::create([
        'type_name' => 'Asset', 'report_group' => 'BalanceSheet', 'description' => 'Asset',
    ]);

    $roundOff = ChartOfAccount::create([
        'account_type_id' => $expenseType->id, 'currency_id' => $currency->id,
        'account_code' => '5271', 'account_name' => 'Round Off',
        'normal_balance' => 'debit', 'is_active' => true,
    ]);
    $stock = ChartOfAccount::create([
        'account_type_id' => $assetType->id, 'currency_id' => $currency->id,
        'account_code' => '1151', 'account_name' => 'Stock In Hand',
        'normal_balance' => 'debit', 'is_active' => true,
    ]);
    $creditors = ChartOfAccount::create([
        'account_type_id' => $assetType->id, 'currency_id' => $currency->id,
        'account_code' => '2111', 'account_name' => 'Creditors',
        'normal_balance' => 'credit', 'is_active' => true,
    ]);

    AccountingPeriod::create([
        'name' => 'June 2026', 'start_date' => '2026-06-01',
        'end_date' => '2026-06-30', 'status' => AccountingPeriod::STATUS_OPEN,
    ]);

    $entry = JournalEntry::create([
        'currency_id' => $currency->id, 'fx_rate_to_base' => 1,
        'entry_date' => '2026-06-03', 'description' => 'GRN with a stray paisa',
        'reference' => 'TEST-1', 'status' => 'posted', 'posted_at' => now(),
    ]);

    JournalEntryDetail::create([
        'journal_entry_id' => $entry->id, 'line_no' => 1,
        'chart_of_account_id' => $stock->id, 'debit' => 1000.00, 'credit' => 0,
    ]);
    $strayLine = JournalEntryDetail::create([
        'journal_entry_id' => $entry->id, 'line_no' => 2,
        'chart_of_account_id' => $roundOff->id, 'debit' => 0.01, 'credit' => 0,
    ]);
    JournalEntryDetail::create([
        'journal_entry_id' => $entry->id, 'line_no' => 3,
        'chart_of_account_id' => $creditors->id, 'debit' => 0, 'credit' => 1000.00,
    ]);

    return ['entry' => $entry, 'roundOff' => $roundOff, 'strayLine' => $strayLine];
}

describe('journal balance check', function () {
    it('reports a posted entry whose Round Off line leaves it out of balance', function () {
        $currency = Currency::factory()->base()->create();
        $expenseType = AccountType::create([
            'type_name' => 'Expense', 'report_group' => 'IncomeStatement', 'description' => 'Expense',
        ]);
        $assetType = AccountType::create([
            'type_name' => 'Asset', 'report_group' => 'BalanceSheet', 'description' => 'Asset',
        ]);

        $roundOff = ChartOfAccount::create([
            'account_type_id' => $expenseType->id, 'currency_id' => $currency->id,
            'account_code' => '5271', 'account_name' => 'Round Off',
            'normal_balance' => 'debit', 'is_active' => true,
        ]);
        $stock = ChartOfAccount::create([
            'account_type_id' => $assetType->id, 'currency_id' => $currency->id,
            'account_code' => '1151', 'account_name' => 'Stock In Hand',
            'normal_balance' => 'debit', 'is_active' => true,
        ]);
        $creditors = ChartOfAccount::create([
            'account_type_id' => $assetType->id, 'currency_id' => $currency->id,
            'account_code' => '2111', 'account_name' => 'Creditors',
            'normal_balance' => 'credit', 'is_active' => true,
        ]);

        AccountingPeriod::create([
            'name' => 'June 2026', 'start_date' => '2026-06-01',
            'end_date' => '2026-06-30', 'status' => AccountingPeriod::STATUS_OPEN,
        ]);

        $entry = JournalEntry::create([
            'currency_id' => $currency->id, 'fx_rate_to_base' => 1,
            'entry_date' => '2026-06-03', 'description' => 'GRN with a stray paisa',
            'reference' => 'TEST-1', 'status' => 'posted', 'posted_at' => now(),
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entry->id, 'line_no' => 1,
            'chart_of_account_id' => $stock->id, 'debit' => 1000.00, 'credit' => 0,
        ]);
        JournalEntryDetail::create([
            'journal_entry_id' => $entry->id, 'line_no' => 2,
            'chart_of_account_id' => $roundOff->id, 'debit' => 0.01, 'credit' => 0,
        ]);
        JournalEntryDetail::create([
            'journal_entry_id' => $entry->id, 'line_no' => 3,
            'chart_of_account_id' => $creditors->id, 'debit' => 0, 'credit' => 1000.00,
        ]);

        $this->artisan('accounting:check-journal-balance')
            ->expectsOutputToContain('no Round Off line at all')
            ->assertSuccessful();
    });

    it('reports nothing when every posted entry balances', function () {
        $this->artisan('accounting:check-journal-balance')
            ->expectsOutputToContain('Every posted journal entry balances')
            ->assertSuccessful();
    });
});

describe('journal rounding repair', function () {
    it('leaves an entry alone when it is further out than --max-difference', function () {
        ['entry' => $entry, 'roundOff' => $roundOff] = makeUnbalancedPostedEntry();

        $this->artisan('accounting:repair-journal-rounding', ['--max-difference' => 0.001])
            ->expectsOutputToContain('SKIP')
            ->assertSuccessful();

        expect(DB::table('journal_entry_details')
            ->where('journal_entry_id', $entry->id)
            ->where('chart_of_account_id', $roundOff->id)
            ->exists())->toBeTrue();
    });
});
