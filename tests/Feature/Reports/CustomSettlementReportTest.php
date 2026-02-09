<?php

namespace Tests\Feature\Reports;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementExpense;
use App\Models\User;
use App\Models\Supplier;
use App\Models\SalesSettlementBankSlip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomSettlementReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_settlement_report_loads()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('reports.custom-settlement.index'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.custom-settlement.index');
    }

    public function test_report_aggregates_multiple_settlements_on_same_day()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Setup Dependencies
        $period = \App\Models\AccountingPeriod::create(['name' => 'Current', 'start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth(), 'status' => 'open']);
        $accountType = \App\Models\AccountType::factory()->create();
        $currency = \App\Models\Currency::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create();
        $warehouse = \App\Models\Warehouse::factory()->create();

        $createAccount = function ($code, $name) use ($accountType, $currency) {
            return ChartOfAccount::create([
                'account_code' => $code,
                'account_name' => $name,
                'account_type_id' => $accountType->id,
                'currency_id' => $currency->id,
                'is_group' => false,
                'is_active' => true,
                'normal_balance' => 'Debit',
            ]);
        };

        $accPerc = $createAccount('5223', 'Percentage Expense');
        $accScheme = $createAccount('5292', 'Scheme Discount');
        $stockInHand = $createAccount('1151', 'Stock In Hand');
        $vanStock = $createAccount('1155', 'Van Stock');

        $salesman = Employee::factory()->create(['name' => 'John Doe', 'designation' => 'Manager']);

        // Settlement 1
        $goodsIssue1 = \App\Models\GoodsIssue::create([
            'issue_number' => 'GI-1',
            'issue_date' => now(),
            'status' => 'issued',
            'employee_id' => $salesman->id,
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'issued_by' => $user->id,
            'stock_in_hand_account_id' => $stockInHand->id,
            'van_stock_account_id' => $vanStock->id,
        ]);

        $settlement1 = SalesSettlement::create([
            'settlement_number' => 'SET-1',
            'settlement_date' => now()->format('Y-m-d'),
            'status' => 'posted',
            'employee_id' => $salesman->id,
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'goods_issue_id' => $goodsIssue1->id,
            'total_sales_amount' => 50000,
            'cash_collected' => 20000,
            'credit_sales_amount' => 5000,
            'verified_by' => $user->id,
            'journal_entry_id' => \App\Models\JournalEntry::factory()->create(['status' => 'posted', 'entry_date' => now(), 'currency_id' => $currency->id, 'accounting_period_id' => $period->id])->id,
        ]);
        SalesSettlementExpense::create(['sales_settlement_id' => $settlement1->id, 'expense_account_id' => $accPerc->id, 'amount' => 1000]);

        // Settlement 2 (Same Day)
        $goodsIssue2 = \App\Models\GoodsIssue::create([
            'issue_number' => 'GI-2',
            'issue_date' => now(),
            'status' => 'issued',
            'employee_id' => $salesman->id,
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'issued_by' => $user->id,
            'stock_in_hand_account_id' => $stockInHand->id,
            'van_stock_account_id' => $vanStock->id,
        ]);

        $settlement2 = SalesSettlement::create([
            'settlement_number' => 'SET-2',
            'settlement_date' => now()->format('Y-m-d'),
            'status' => 'posted',
            'employee_id' => $salesman->id,
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'goods_issue_id' => $goodsIssue2->id,
            'total_sales_amount' => 30000,
            'cash_collected' => 10000,
            'credit_sales_amount' => 2000,
            'verified_by' => $user->id,
            'journal_entry_id' => \App\Models\JournalEntry::factory()->create(['status' => 'posted', 'entry_date' => now(), 'currency_id' => $currency->id, 'accounting_period_id' => $period->id])->id,
        ]);
        SalesSettlementExpense::create(['sales_settlement_id' => $settlement2->id, 'expense_account_id' => $accScheme->id, 'amount' => 500]);

        // Expected Totals:
        // Sale: 50000 + 30000 = 80,000
        // Perc Expense: 1000
        // Scheme Discount: 500
        // Today Cash: 20000 + 10000 = 30,000
        // Credit: 5000 + 2000 = 7,000

        $response = $this->get(route('reports.custom-settlement.index'));
        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('80,000'); // Total Sale
        $response->assertSee('1,000'); // Perc Expense
        $response->assertSee('500'); // Scheme
        $response->assertSee('30,000'); // Today Cash
        $response->assertSee('7,000'); // Total Credit
    }
}
