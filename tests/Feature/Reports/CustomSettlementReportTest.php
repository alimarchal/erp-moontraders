<?php

namespace Tests\Feature\Reports;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomSettlementReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::create(['name' => 'report-audit-custom-settlement']);
    }

    public function test_custom_settlement_report_loads()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-audit-custom-settlement');
        $this->actingAs($user);

        $response = $this->get(route('reports.custom-settlement.index'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.custom-settlement.index');
    }

    public function test_report_aggregates_multiple_settlements_on_same_day()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-audit-custom-settlement');
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
                'normal_balance' => 'debit',
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

        // Create customer-employee account ledger entries (source of truth for credit balance)
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();

        $account1 = CustomerEmployeeAccount::create([
            'account_number' => 'ACC-000001',
            'customer_id' => $customer1->id,
            'employee_id' => $salesman->id,
            'opened_date' => now()->subMonth(),
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $account2 = CustomerEmployeeAccount::create([
            'account_number' => 'ACC-000002',
            'customer_id' => $customer2->id,
            'employee_id' => $salesman->id,
            'opened_date' => now()->subMonth(),
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        // Credit sales (debit = customer owes)
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account1->id,
            'transaction_date' => now()->subDay(),
            'transaction_type' => 'credit_sale',
            'description' => 'Credit sale yesterday',
            'debit' => 5000,
            'credit' => 0,
            'created_by' => $user->id,
        ]);
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account1->id,
            'transaction_date' => now(),
            'transaction_type' => 'credit_sale',
            'description' => 'Credit sale today',
            'debit' => 3000,
            'credit' => 0,
            'created_by' => $user->id,
        ]);
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account2->id,
            'transaction_date' => now(),
            'transaction_type' => 'credit_sale',
            'description' => 'Credit sale today',
            'debit' => 2000,
            'credit' => 0,
            'created_by' => $user->id,
        ]);

        // Recoveries (credit = customer paid back)
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account1->id,
            'transaction_date' => now(),
            'transaction_type' => 'recovery_cash',
            'description' => 'Cash recovery',
            'debit' => 0,
            'credit' => 1500,
            'created_by' => $user->id,
        ]);

        // Expected Totals:
        // Sale: 50000 + 30000 = 80,000 (today only)
        // Perc Expense: 1000
        // Scheme Discount: 500
        // Today Cash: 20000 + 10000 = 30,000
        // Total Credit from ledger: (5000 + 3000 + 2000) - 1500 = 8,500 (net outstanding)

        $response = $this->get(route('reports.custom-settlement.index'));
        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('80,000'); // Total Sale
        $response->assertSee('1,000'); // Perc Expense
        $response->assertSee('500'); // Scheme
        $response->assertSee('30,000'); // Today Cash
        $response->assertSee('8,500'); // Total Credit (from ledger: net outstanding)
    }
}
