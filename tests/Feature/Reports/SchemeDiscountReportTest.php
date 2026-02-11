<?php

namespace Tests\Feature\Reports;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementExpense;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SchemeDiscountReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::create(['name' => 'report-view-sales']);
    }

    public function test_scheme_discount_report_loads()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-view-sales');
        $this->actingAs($user);

        $response = $this->get(route('reports.scheme-discount.index'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.scheme-discount.index');
    }

    public function test_report_shows_data_correctly()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-view-sales');
        $this->actingAs($user);

        // Create Scheme Discount Account
        // Create Scheme Discount Account
        // Create dependencies
        $period = \App\Models\AccountingPeriod::create([
            'name' => 'Current Period',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]);

        $accountType = \App\Models\AccountType::factory()->create();
        $currency = \App\Models\Currency::factory()->create();

        // Create Scheme Discount Account
        $account = ChartOfAccount::create([
            'account_code' => '5292',
            'account_name' => 'Scheme Discount Expense',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'Debit',
        ]);

        // Create Salesman
        $salesman = Employee::factory()->create(['name' => 'Test Salesman']);

        // Create Posted Settlement
        $vehicle = \App\Models\Vehicle::factory()->create();
        $warehouse = \App\Models\Warehouse::factory()->create();

        // Goods Issue Dependencies
        $stockInHand = ChartOfAccount::create([
            'account_code' => '1151',
            'account_name' => 'Stock In Hand',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'Debit',
        ]);
        $vanStock = ChartOfAccount::create([
            'account_code' => '1155',
            'account_name' => 'Van Stock',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'Debit',
        ]);

        $goodsIssue = \App\Models\GoodsIssue::create([
            'issue_number' => 'GI-TEST-'.uniqid(),
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
        ]); // Add Scheme Discount Expense (using manual create to avoid factory issues if any)
        SalesSettlementExpense::create([
            'sales_settlement_id' => $settlement->id,
            'expense_account_id' => $account->id,
            'amount' => 1000,
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->get(route('reports.scheme-discount.index'));

        $response->assertStatus(200);
        $response->assertSee('Test Salesman');
        $response->assertSee('1,000');
    }

    public function test_report_excludes_draft_settlements()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-view-sales');
        $this->actingAs($user);

        // Create dependencies
        $period = \App\Models\AccountingPeriod::create([
            'name' => 'Current Period',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]);

        $accountType = \App\Models\AccountType::factory()->create();
        $currency = \App\Models\Currency::factory()->create();

        // Create Scheme Discount Account
        $account = ChartOfAccount::create([
            'account_code' => '5292',
            'account_name' => 'Scheme Discount Expense',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'Debit',
        ]);
        $salesman = Employee::factory()->create(['name' => 'Draft Salesman']);

        // Create Salesman
        // $salesman created above

        $vehicle = \App\Models\Vehicle::factory()->create();
        $warehouse = \App\Models\Warehouse::factory()->create();

        // Re-use or create new accounts for this test case? RefreshDatabase cleans up, so need to recreate.
        // Or finding existing.
        $stockInHand = ChartOfAccount::where('account_code', '1151')->first();
        if (! $stockInHand) {
            $stockInHand = ChartOfAccount::create([
                'account_code' => '1151',
                'account_name' => 'Stock In Hand',
                'account_type_id' => $accountType->id,
                'currency_id' => $currency->id,
                'is_group' => false,
                'is_active' => true,
                'normal_balance' => 'Debit',
            ]);
        }

        $vanStock = ChartOfAccount::where('account_code', '1155')->first();
        if (! $vanStock) {
            $vanStock = ChartOfAccount::create([
                'account_code' => '1155',
                'account_name' => 'Van Stock',
                'account_type_id' => $accountType->id,
                'currency_id' => $currency->id,
                'is_group' => false,
                'is_active' => true,
                'normal_balance' => 'Debit',
            ]);
        }

        $goodsIssue = \App\Models\GoodsIssue::create([
            'issue_number' => 'GI-TEST-'.uniqid(),
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
            'status' => 'draft',
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
            'amount' => 500,
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->get(route('reports.scheme-discount.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Draft Salesman');
    }

    public function test_filters_by_supplier_and_sorting()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-view-sales');
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
        $account = ChartOfAccount::create([
            'account_code' => '5292',
            'account_name' => 'Scheme Discount Expense',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'Debit',
        ]);

        $stockInHand = ChartOfAccount::create([
            'account_code' => '1151',
            'account_name' => 'Stock In Hand',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'Debit',
        ]);
        $vanStock = ChartOfAccount::create([
            'account_code' => '1155',
            'account_name' => 'Van Stock',
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'is_group' => false,
            'is_active' => true,
            'normal_balance' => 'Debit',
        ]);

        $vehicle = \App\Models\Vehicle::factory()->create();
        $warehouse = \App\Models\Warehouse::factory()->create();

        // Create Suppliers
        $supplierA = Supplier::factory()->create(['supplier_name' => 'Supplier A']);
        $supplierB = Supplier::factory()->create(['supplier_name' => 'Supplier B']);

        // Create Salesmen linked to Suppliers
        $salesmanA = Employee::factory()->create(['name' => 'Salesman A', 'supplier_id' => $supplierA->id]);
        $salesmanB = Employee::factory()->create(['name' => 'Salesman B', 'supplier_id' => $supplierB->id]);

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

        // Salesman A: 1000, Salesman B: 500
        $createData($salesmanA, 1000);
        $createData($salesmanB, 500);

        // Test 1: Filter by Supplier A -> Should only see Salesman A
        $response = $this->get(route('reports.scheme-discount.index', ['filter' => ['supplier_id' => $supplierA->id]]));
        $response->assertStatus(200);
        $response->assertSee('Salesman A');
        $response->assertDontSee('Salesman B');
        $response->assertSee('1,000');

        // Test 2: Filter by Supplier B -> Should only see Salesman B
        $response = $this->get(route('reports.scheme-discount.index', ['filter' => ['supplier_id' => $supplierB->id]]));
        $response->assertStatus(200);
        $response->assertSee('Salesman B');
        $response->assertDontSee('Salesman A'); // Salesman A is filtered out
        $response->assertSee('500');

        // Test 3: Sorting High to Low (Both suppliers)
        // Should list Salesman A (1000) then Salesman B (500)
        $response = $this->get(route('reports.scheme-discount.index', ['filter' => ['sort_order' => 'high_to_low']]));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Salesman A', 'Salesman B']);

        // Test 4: Sorting Low to High
        // Should list Salesman B (500) then Salesman A (1000)
        $response = $this->get(route('reports.scheme-discount.index', ['filter' => ['sort_order' => 'low_to_high']]));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Salesman B', 'Salesman A']);
    }

    public function test_filters_by_designation()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('report-view-sales');
        $this->actingAs($user);

        // Setup Common Dependencies - Simplified
        $period = \App\Models\AccountingPeriod::create(['name' => 'Current Period', 'start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth(), 'status' => 'open']);
        $accountType = \App\Models\AccountType::factory()->create();
        $currency = \App\Models\Currency::factory()->create();
        $account = ChartOfAccount::create(['account_code' => '5292', 'account_name' => 'Scheme Discount', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'is_group' => false, 'is_active' => true, 'normal_balance' => 'Debit']);

        $stockInHand = ChartOfAccount::create(['account_code' => '1151', 'account_name' => 'Stock', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'is_group' => false, 'is_active' => true, 'normal_balance' => 'Debit']);
        $vanStock = ChartOfAccount::create(['account_code' => '1155', 'account_name' => 'Van Stock', 'account_type_id' => $accountType->id, 'currency_id' => $currency->id, 'is_group' => false, 'is_active' => true, 'normal_balance' => 'Debit']);

        $vehicle = \App\Models\Vehicle::factory()->create();
        $warehouse = \App\Models\Warehouse::factory()->create();

        // Create Salesmen with Designations
        $salesmanManager = Employee::factory()->create(['name' => 'Manager Dave', 'designation' => 'Manager']);
        $salesmanDriver = Employee::factory()->create(['name' => 'Driver Steve', 'designation' => 'Driver']);

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
                'journal_entry_id' => \App\Models\JournalEntry::factory()->create(['status' => 'posted', 'entry_date' => now(), 'currency_id' => $currency->id, 'accounting_period_id' => $period->id])->id,
                'total_quantity_issued' => 0,
                'total_value_issued' => 0,
                'credit_recoveries' => 0,
            ]);

            SalesSettlementExpense::create(['sales_settlement_id' => $settlement->id, 'expense_account_id' => $account->id, 'amount' => $amount, 'expense_date' => now()->format('Y-m-d')]);
        };

        $createData($salesmanManager, 100);
        $createData($salesmanDriver, 200);

        // Filter by Designation 'Manager'
        $response = $this->get(route('reports.scheme-discount.index', ['filter' => ['designation' => 'Manager']]));
        $response->assertStatus(200);
        $response->assertSee('Manager Dave');
        $response->assertDontSee('Driver Steve');

        // Filter by Designation 'Driver'
        $response = $this->get(route('reports.scheme-discount.index', ['filter' => ['designation' => 'Driver']]));
        $response->assertStatus(200);
        $response->assertSee('Driver Steve');
        $response->assertDontSee('Manager Dave');
    }
}
