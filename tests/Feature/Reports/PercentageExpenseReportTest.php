<?php

namespace Tests\Feature\Reports;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PercentageExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::create(['name' => 'report-view-audit']);
    }

    public function test_percentage_expense_report_loads()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-view-audit');
        $this->actingAs($user);

        $response = $this->get(route('reports.percentage-expense.index'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.percentage-expense.index');
    }

    public function test_report_shows_data_and_filters_by_designation()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-view-audit');
        $this->actingAs($user);

        // Setup Common Dependencies
        $period = \App\Models\AccountingPeriod::create([
            'name' => 'Current Period',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]);
        $accountType = \App\Models\AccountType::factory()->create();
        $currency = \App\Models\Currency::factory()->create();

        // Critical: Percentage Expense Account Code
        $account = ChartOfAccount::create([
            'account_code' => '5223',
            'account_name' => 'Percentage Expense',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'debit',
        ]);

        $stockInHand = ChartOfAccount::create([
            'account_code' => '1151',
            'account_name' => 'Stock In Hand',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'debit',
        ]);
        $vanStock = ChartOfAccount::create([
            'account_code' => '1155',
            'account_name' => 'Van Stock',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'debit',
        ]);

        $vehicle = \App\Models\Vehicle::factory()->create();
        $warehouse = \App\Models\Warehouse::factory()->create();

        // Create Salesmen with Designations
        $salesmanManager = Employee::factory()->create(['name' => 'Manager John', 'designation' => 'Manager']);
        $salesmanDriver = Employee::factory()->create(['name' => 'Driver Bob', 'designation' => 'Driver']);

        // Helper to create settlement and expense
        $createData = function ($salesman, $amount) use ($user, $vehicle, $warehouse, $period, $currency, $stockInHand, $vanStock, $account) {
            $goodsIssue = \App\Models\GoodsIssue::create([
                'issue_number' => 'GI-'.uniqid(),
                'issue_date' => now(),
                'status' => 'issued',
                'total_quantity' => 0,
                'total_value' => 0,
                'employee_id' => $salesman->id,
                'vehicle_id' => $vehicle->id,
                'warehouse_id' => $warehouse->id,
                'issued_by' => $user->id,
                'stock_in_hand_account_id' => $stockInHand->id,
                'van_stock_account_id' => $vanStock->id,
            ]);

            $settlement = SalesSettlement::create([
                'settlement_number' => 'SETTLE-'.uniqid(),
                'settlement_date' => now()->format('Y-m-d'),
                'status' => 'posted',
                'employee_id' => $salesman->id,
                'vehicle_id' => $vehicle->id,
                'warehouse_id' => $warehouse->id,
                'goods_issue_id' => $goodsIssue->id,
                'total_sales_amount' => 0,
                'cash_sales_amount' => 0,
                'credit_sales_amount' => 0,
                'cheque_sales_amount' => 0,
                'verified_by' => $user->id,
                'journal_entry_id' => \App\Models\JournalEntry::factory()->create([
                    'status' => 'posted',
                    'entry_date' => now(),
                    'currency_id' => $currency->id,
                    'accounting_period_id' => $period->id,
                ])->id,
                'total_quantity_issued' => 0,
                'total_value_issued' => 0,
                'credit_recoveries' => 0,
            ]);

            SalesSettlementExpense::create([
                'sales_settlement_id' => $settlement->id,
                'expense_account_id' => $account->id,
                'amount' => $amount,
                'expense_date' => now()->format('Y-m-d'),
            ]);
        };

        $createData($salesmanManager, 1000);
        $createData($salesmanDriver, 500);

        // Test 1: No Filter -> Should see both
        $response = $this->get(route('reports.percentage-expense.index'));
        $response->assertStatus(200);
        $response->assertSee('Manager John');
        $response->assertSee('Driver Bob');
        $response->assertSee('1,000');
        $response->assertSee('500');

        // Test 2: Filter by Designation 'Manager' -> Should only see Manager John
        $response = $this->get(route('reports.percentage-expense.index', ['filter' => ['designation' => 'Manager']]));
        $response->assertStatus(200);
        $response->assertSee('Manager John');
        $response->assertDontSee('Driver Bob');
        $response->assertSee('1,000');

        // Test 3: Filter by Designation 'Driver' -> Should only see Driver Bob
        $response = $this->get(route('reports.percentage-expense.index', ['filter' => ['designation' => 'Driver']]));
        $response->assertStatus(200);
        $response->assertSee('Driver Bob');
        $response->assertDontSee('Manager John');
        $response->assertSee('500');
    }
}
