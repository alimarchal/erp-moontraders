<?php

use App\Enums\DocumentType;
use App\Enums\PageLayout;
use App\Livewire\Dashboard;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\ClaimRegister;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\LedgerRegister;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InvestmentSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────

function createDashboardPermissions(): void
{
    $permissions = [
        'sales-settlement-list', 'sales-settlement-create',
        'goods-receipt-note-list', 'goods-receipt-note-create',
        'inventory-view',
        'goods-issue-list', 'goods-issue-create',
        'journal-entry-list', 'journal-entry-create',
        'report-financial-general-ledger', 'report-inventory-daily-stock-register', 'report-sales-daily-sales',
        'accounting-view',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm]);
    }
}

function createSuperAdminUser(): User
{
    createDashboardPermissions();

    $user = User::factory()->create(['is_super_admin' => 'Yes']);

    return $user;
}

// ── Basic Access ─────────────────────────────────────────────────────

it('renders the dashboard page for authenticated users', function () {
    $user = createSuperAdminUser();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSeeLivewire(Dashboard::class);
});

it('redirects unauthenticated users from dashboard', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

// ── Livewire Component ──────────────────────────────────────────────

it('loads KPI cards for super admin', function () {
    $user = createSuperAdminUser();

    SalesSettlement::factory()->create([
        'status' => 'posted',
        'settlement_date' => now(),
        'total_sales_amount' => 5000,
        'cash_sales_amount' => 3000,
        'credit_sales_amount' => 2000,
        'gross_profit' => 1500,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('kpiCards.totalSalesThisMonth', 5000.0)
        ->assertSet('kpiCards.cashCollectedThisMonth', 3000.0)
        ->assertSet('kpiCards.creditSalesThisMonth', 2000.0)
        ->assertSet('kpiCards.grossProfitThisMonth', 1500.0)
        ->assertSuccessful();
});

it('loads pending items count', function () {
    $user = createSuperAdminUser();

    SalesSettlement::factory()->count(3)->create(['status' => 'draft']);
    $supplier = Supplier::factory()->create();
    GoodsReceiptNote::factory()->count(2)->create(['status' => 'draft', 'supplier_id' => $supplier->id]);
    $currency = Currency::firstOrCreate(
        ['currency_code' => 'TST'],
        ['currency_name' => 'Test Currency', 'currency_symbol' => 'T', 'exchange_rate' => 1, 'is_base_currency' => false, 'is_active' => true]
    );
    $accountingPeriod = AccountingPeriod::factory()->create();
    JournalEntry::factory()->count(1)->create([
        'status' => 'draft',
        'entry_date' => now(),
        'currency_id' => $currency->id,
        'accounting_period_id' => $accountingPeriod->id,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('pendingItems.draftSettlements', 3)
        ->assertSet('pendingItems.draftGrns', 2)
        ->assertSet('pendingItems.draftJournalEntries', 1)
        ->assertSuccessful();
});

// ── Role-based visibility ───────────────────────────────────────────

it('hides sales data from users without sales permissions', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(['goods-receipt-note-list', 'inventory-view']);

    SalesSettlement::factory()->create([
        'status' => 'posted',
        'settlement_date' => now(),
        'total_sales_amount' => 5000,
    ]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->get('kpiCards'))->not->toHaveKey('totalSalesThisMonth');
    expect($component->get('salesByPaymentMethod'))->toBeEmpty();
    expect($component->get('dailySalesTrend'))->toBeEmpty();
});

it('shows inventory data for users with inventory permissions', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(['inventory-view', 'report-inventory-daily-stock-register', 'goods-receipt-note-list']);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->get('kpiCards'))->toHaveKey('totalInventoryValue');
    expect($component->get('kpiCards'))->toHaveKey('productsInStock');
});

it('shows accounting data for users with financial permissions', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(['report-financial-general-ledger', 'journal-entry-list']);

    $accountingPeriod = AccountingPeriod::factory()->create();
    JournalEntry::factory()->count(2)->create([
        'status' => 'draft',
        'entry_date' => now(),
        'currency_id' => Currency::factory()->create()->id,
        'accounting_period_id' => $accountingPeriod->id,
    ]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->get('kpiCards'))->toHaveKey('draftJournalEntries');
    expect($component->get('kpiCards.draftJournalEntries'))->toBe(2);
});

// ── View rendering ──────────────────────────────────────────────────

it('does not render quick action shortcuts', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(['goods-receipt-note-create', 'sales-settlement-create']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSee('Quick Actions')
        ->assertDontSee('New GRN')
        ->assertDontSee('New Settlement')
        ->assertSuccessful();
});

it('renders pending actions alert when drafts exist', function () {
    $user = createSuperAdminUser();

    SalesSettlement::factory()->count(2)->create(['status' => 'draft']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Pending Actions')
        ->assertSee('Draft Settlements')
        ->assertSuccessful();
});

it('hides pending actions alert when no drafts exist', function () {
    $user = createSuperAdminUser();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSee('Pending Actions')
        ->assertSuccessful();
});

// ── Scope: each user sees their own world ───────────────────────────

it('limits sales figures to the user\'s supplier', function () {
    createDashboardPermissions();
    Permission::firstOrCreate(['name' => 'sales-settlement-view-all']);

    $mine = Supplier::factory()->create();
    $other = Supplier::factory()->create();
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 1000, 'supplier_id' => $mine->id]);
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 9000, 'supplier_id' => $other->id]);

    $user = User::factory()->create(['supplier_id' => $mine->id]);
    $user->givePermissionTo(['sales-settlement-list', 'sales-settlement-view-all']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('kpiCards.totalSalesThisMonth', 1000.0)
        ->assertSet('scope.supplier', $mine->supplier_name)
        ->assertSet('sections.company', false);
});

it('shows only the user\'s own settlements without view-all', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo('sales-settlement-list');

    // created_by is filled from the signed-in user (UserTracking).
    $this->actingAs(User::factory()->create());
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 5000]);
    SalesSettlement::factory()->create(['status' => 'draft']);
    $this->actingAs($user);
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 700]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('kpiCards.totalSalesThisMonth', 700.0)
        ->assertSet('pendingItems.draftSettlements', 0)
        ->assertSet('scope.own_settlements', true);
});

it('gives super admins the company overview across all suppliers', function () {
    $user = createSuperAdminUser();
    $supplier = Supplier::factory()->create();
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 2500, 'supplier_id' => $supplier->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('sections.company', true)
        ->assertSet('scope.supplier', 'All suppliers')
        ->assertSet('salesBySupplier.labels', [$supplier->supplier_name])
        ->assertSee('Sales breakdown');
});

it('takes supplier invoices and payments from the supplier ledger register', function () {
    createDashboardPermissions();
    Permission::firstOrCreate(['name' => 'report-audit-ledger-register']);

    $mine = Supplier::factory()->create();
    $other = Supplier::factory()->create();
    foreach ([[$mine, 400000, 150000], [$other, 900000, 900000]] as [$supplier, $invoice, $online]) {
        LedgerRegister::create([
            'supplier_id' => $supplier->id, 'transaction_date' => now()->toDateString(), 'document_type' => DocumentType::cases()[0],
            'invoice_amount' => $invoice, 'online_amount' => $online, 'opening_balance' => 0, 'expenses_amount' => 0,
            'za_point_five_percent_amount' => 0, 'claim_adjust_amount' => 0, 'balance' => 0,
        ]);
    }

    $user = User::factory()->create(['supplier_id' => $mine->id]);
    $user->givePermissionTo('report-audit-ledger-register');

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('kpiCards.supplierInvoicesThisMonth', 400000.0)
        ->assertSet('kpiCards.paymentsThisMonth', 150000.0)
        ->assertSet('kpiCards.outstandingPayables', 250000.0);
});

it('opens draft settlements with a date range that includes older drafts', function () {
    $user = createSuperAdminUser();
    SalesSettlement::factory()->create(['status' => 'draft', 'settlement_date' => '2026-01-15']);

    $links = Livewire::actingAs($user)->test(Dashboard::class)->get('draftLinks');

    expect(urldecode($links['draftSettlements']))
        ->toContain('filter[settlement_date_from]=2026-01-15')
        ->toContain('filter[settlement_date_to]='.now()->toDateString())
        ->toContain('filter[status]=draft');
});

it('shows market credit company wise and the top creditors', function () {
    $user = createSuperAdminUser();
    $nestle = Supplier::factory()->create(['supplier_name' => 'Nestle']);
    $engro = Supplier::factory()->create(['supplier_name' => 'Engro']);

    $ledger = function (Supplier $supplier, string $customerName, float $debit, float $credit): void {
        $account = CustomerEmployeeAccount::create([
            'account_number' => 'ACC-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory()->create(['customer_name' => $customerName])->id,
            'employee_id' => Employee::factory()->create(['supplier_id' => $supplier->id])->id,
            'opened_date' => now()->toDateString(),
        ]);
        foreach ([['credit_sale', $debit, 0], ['recovery', 0, $credit]] as [$type, $dr, $cr]) {
            CustomerEmployeeAccountTransaction::create([
                'customer_employee_account_id' => $account->id, 'transaction_date' => now()->toDateString(),
                'transaction_type' => $type, 'description' => $type, 'debit' => $dr, 'credit' => $cr,
            ]);
        }
    };
    $ledger($nestle, 'Big Store', 500000, 100000);
    $ledger($engro, 'Small Shop', 80000, 30000);

    $component = Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('kpiCards.marketCredit', 450000.0)
        ->assertSet('kpiCards.creditCustomers', 2)
        ->assertSet('creditAging.values.0', 450000.0)
        ->assertSee('Top credit customers')
        ->assertSee('Credit market analysis')
        ->assertSet('agingCustomers', []);

    expect($component->get('topCreditCustomers.0'))->toMatchArray(['name' => 'Big Store', 'used' => 400000.0, 'suppliers' => 'Nestle', 'last_paid_days' => 0])
        ->and($component->get('topCreditCustomers.1'))->toMatchArray(['name' => 'Small Shop', 'used' => 50000.0, 'suppliers' => 'Engro']);

    // A Nestle user sees only Nestle's credit, split by salesman.
    $nestleUser = User::factory()->create(['supplier_id' => $nestle->id]);
    $nestleUser->givePermissionTo(Permission::firstOrCreate(['name' => 'report-audit-creditors-ledger']));

    Livewire::actingAs($nestleUser)->test(Dashboard::class)
        ->assertSet('kpiCards.marketCredit', 400000.0)
        ->assertCount('topCreditCustomers', 1)
        ->assertSet('topCreditCustomers.0.name', 'Big Store')
        ->assertDontSee('Credit by company')
        ->assertSet('sections.company', false);
});

it('gives the admin role the company view but hides what admin has no permission for', function () {
    createDashboardPermissions();
    $admin = User::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
    $admin->assignRole(Role::create(['name' => 'admin']));
    $admin->givePermissionTo('sales-settlement-list');

    Livewire::actingAs($admin)->test(Dashboard::class)
        ->assertSet('sections.company', true)
        ->assertSet('scope.supplier', 'All suppliers')
        ->assertSet('sections.credit', false)
        ->assertSee('Admin view')
        ->assertDontSee('Active users')
        ->assertDontSee('Top creditors');
});

it('keeps figures no dashboard card shows out of the Livewire snapshot', function () {
    $user = createSuperAdminUser();
    Permission::firstOrCreate(['name' => 'report-audit-ledger-register']);
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 1000]);

    $data = Livewire::actingAs($user)->test(Dashboard::class)->snapshot['data'];

    expect($data)->toHaveKeys(['kpiCards', 'dailySalesTrend', 'topCreditCustomers', 'agingCustomers', 'investment', 'insights'])
        ->not->toHaveKeys([
            'revenueVsCogs', 'purchasesVsPayments', 'journalEntryStatus', 'grnVsGoodsIssueTrend', 'cashVsCreditTrend',
            'warehouseStockDistribution', 'stockMovementBreakdown', 'recentSettlements', 'lowStock', 'creditBreakdown',
            'creditBySalesman', 'supplierLedger', 'companyOverview', 'salesByDayOfWeek',
        ]);
});

it('adds a customer owed to two salesmen into one top creditor but keeps one aging row per salesman ledger', function () {
    $user = createSuperAdminUser();
    $supplier = Supplier::factory()->create();
    $customer = Customer::factory()->create(['customer_name' => 'Baba Bakers']);
    $salesmen = Employee::factory()->count(2)->create(['supplier_id' => $supplier->id]);

    foreach ([[$salesmen[0], 300000], [$salesmen[1], 100000]] as [$salesman, $debit]) {
        $account = CustomerEmployeeAccount::create([
            'account_number' => 'ACC-'.fake()->unique()->numerify('######'),
            'customer_id' => $customer->id, 'employee_id' => $salesman->id, 'opened_date' => now()->subDays(95)->toDateString(),
        ]);
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account->id, 'transaction_date' => now()->subDays(95)->toDateString(),
            'transaction_type' => 'credit_sale', 'description' => 'credit sale', 'debit' => $debit, 'credit' => 0,
        ]);
    }

    $component = Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('kpiCards.creditCustomers', 1)
        ->assertSet('kpiCards.creditOverdueCustomers', 1)
        ->assertCount('topCreditCustomers', 1)
        ->assertSet('topCreditCustomers.0.used', 400000.0)
        ->assertSet('topCreditCustomers.0.accounts', 2)
        ->assertCount('agingCustomers', 2)
        ->assertSet('agingCustomers.0.employee_id', $salesmen[0]->id)
        ->assertSet('agingCustomers.0.used', 300000.0)
        ->assertSet('agingCustomers.1.employee_id', $salesmen[1]->id);

    $component->assertSee('Baba Bakers');
});

it('lists customers with no payment for 60+ days for the aging drill-down', function () {
    $user = createSuperAdminUser();
    $supplier = Supplier::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-900001',
        'customer_id' => Customer::factory()->create(['customer_name' => 'Slow Payer'])->id,
        'employee_id' => Employee::factory()->create(['supplier_id' => $supplier->id])->id,
        'opened_date' => now()->subDays(80)->toDateString(),
    ]);
    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id, 'transaction_date' => now()->subDays(80)->toDateString(),
        'transaction_type' => 'credit_sale', 'description' => 'credit sale', 'debit' => 250000, 'credit' => 0,
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('kpiCards.creditOverdue', 250000.0)
        ->assertSet('kpiCards.creditOverdueCustomers', 1)
        ->assertSet('creditAging.counts', [0, 0, 1, 0])
        ->assertSet('agingCustomers.0.name', 'Slow Payer')
        ->assertSet('agingCustomers.0.bucket', 2)
        ->assertSee('Slow Payer');
});

it('lets super admins narrow the dashboard to one supplier but not supplier users', function () {
    createDashboardPermissions();
    // Only enabled suppliers can be picked, and the factory disables one in ten.
    $nestle = Supplier::factory()->create(['supplier_name' => 'Nestle', 'disabled' => false]);
    $engro = Supplier::factory()->create(['supplier_name' => 'Engro', 'disabled' => false]);
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 1000, 'supplier_id' => $nestle->id]);
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 9000, 'supplier_id' => $engro->id]);

    $admin = createSuperAdminUser();
    Livewire::withQueryParams(['supplier' => $engro->id])->actingAs($admin)->test(Dashboard::class)
        ->assertSet('scope.supplier', 'Engro')
        ->assertSet('kpiCards.totalSalesThisMonth', 9000.0)
        ->assertSee('All suppliers');

    $nestleUser = User::factory()->create(['supplier_id' => $nestle->id]);
    $nestleUser->givePermissionTo(['sales-settlement-list', Permission::firstOrCreate(['name' => 'sales-settlement-view-all'])]);
    Livewire::withQueryParams(['supplier' => $engro->id])->actingAs($nestleUser)->test(Dashboard::class)
        ->assertSet('scope.supplier', 'Nestle')
        ->assertSet('kpiCards.totalSalesThisMonth', 1000.0)
        ->assertSet('supplierOptions', []);
});

it('ages dashboard credit by unpaid credit sales, so fresh credit after a cleared balance is not overdue', function () {
    $user = createSuperAdminUser();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'ACC-900777',
        'customer_id' => Customer::factory()->create(['customer_name' => 'Murree Traders II'])->id,
        'employee_id' => Employee::factory()->create(['supplier_id' => Supplier::factory()->create()->id])->id,
        'opened_date' => now()->subDays(150)->toDateString(),
    ]);
    foreach ([[144, 25442, 0], [140, 0, 25442], [100, 40000, 0], [2, 253600, 0]] as [$daysAgo, $debit, $credit]) {
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account->id, 'transaction_date' => now()->subDays($daysAgo)->toDateString(),
            'transaction_type' => $debit > 0 ? 'credit_sale' : 'recovery', 'description' => 'line', 'debit' => $debit, 'credit' => $credit,
        ]);
    }

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('creditAging.values', [253600.0, 0.0, 0.0, 40000.0])
        ->assertSet('creditAging.counts', [1, 0, 0, 1])
        ->assertSet('kpiCards.creditOverdue', 40000.0)
        ->assertSet('kpiCards.creditOverdueAccounts', 1)
        ->assertSet('agingCustomers.0.days', 100)
        ->assertSet('agingCustomers.0.buckets', [253600.0, 0.0, 0.0, 40000.0]);
});

it('limits goods issued to the user\'s own issues without view-all', function () {
    createDashboardPermissions();
    $user = User::factory()->create();
    $user->givePermissionTo(['goods-issue-list', 'inventory-view']);

    GoodsIssue::factory()->create(['status' => 'issued', 'total_value' => 9000, 'issued_by' => User::factory()->create()->id]);
    GoodsIssue::factory()->create(['status' => 'issued', 'total_value' => 700, 'issued_by' => $user->id]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('kpiCards.goodsIssuedThisMonth', 700.0);
});

it('keeps salesmen who share a name apart in top salesmen', function () {
    $user = createSuperAdminUser();
    foreach (Employee::factory()->count(2)->create(['name' => 'Muhammad Ali']) as $salesman) {
        SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 1000, 'employee_id' => $salesman->id]);
    }

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('topSalespersonBySales.labels', ['Muhammad Ali', 'Muhammad Ali'])
        ->assertSet('topSalespersonBySales.values', [1000.0, 1000.0]);
});

it('takes the overdue share from what customers owe, not from credit net of advances', function () {
    $user = createSuperAdminUser();
    $supplier = Supplier::factory()->create();
    foreach ([[80, 1000, 0], [5, 0, 600]] as [$daysAgo, $debit, $credit]) {
        $account = CustomerEmployeeAccount::create([
            'account_number' => 'ACC-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory()->create()->id,
            'employee_id' => Employee::factory()->create(['supplier_id' => $supplier->id])->id,
            'opened_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account->id, 'transaction_date' => now()->subDays($daysAgo)->toDateString(),
            'transaction_type' => $debit > 0 ? 'credit_sale' : 'recovery', 'description' => 'line', 'debit' => $debit, 'credit' => $credit,
        ]);
    }

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('kpiCards.marketCredit', 400.0)
        ->assertSet('kpiCards.creditOwed', 1000.0)
        ->assertSet('kpiCards.creditOverdue', 1000.0)
        ->assertSee('100% over 60 days');
});

it('shows the investment section for a super admin', function () {
    $admin = createSuperAdminUser();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSee('Investment')
        ->assertSuccessful();
});

it('resolves a custom period and compares it with the preceding window', function () {
    $admin = createSuperAdminUser();

    $component = Livewire::actingAs($admin)
        ->test(Dashboard::class, ['from' => '2026-09-01', 'to' => '2026-09-10']);

    $component->assertSuccessful();
});

// ── Figures from the dashboard review (issues 192 / 193) ─────────────

it('ranks top credit customers by everything they owe across salesmen and shows the same customer in the strip', function () {
    $user = createSuperAdminUser();
    $supplier = Supplier::factory()->create();
    $account = function (Customer $customer, float $debit) use ($supplier): void {
        $account = CustomerEmployeeAccount::create([
            'account_number' => 'ACC-'.fake()->unique()->numerify('######'),
            'customer_id' => $customer->id,
            'employee_id' => Employee::factory()->create(['supplier_id' => $supplier->id])->id,
            'opened_date' => now()->toDateString(),
        ]);
        CustomerEmployeeAccountTransaction::create([
            'customer_employee_account_id' => $account->id, 'transaction_date' => now()->toDateString(),
            'transaction_type' => 'credit_sale', 'description' => 'credit sale', 'debit' => $debit, 'credit' => 0,
        ]);
    };
    $split = Customer::factory()->create(['customer_name' => 'Split Buyer', 'city' => 'Murree']);
    $single = Customer::factory()->create(['customer_name' => 'Single Buyer']);
    $account($split, 300000);
    $account($split, 350000);
    $account($single, 500000);

    $component = Livewire::actingAs($user)->test(Dashboard::class)
        ->assertCount('topCreditCustomers', 2)
        ->assertSeeInOrder(['Highest credit customer', 'Split Buyer', 'Rs 6.5 Lac']);

    expect($component->get('topCreditCustomers.0'))->toMatchArray(['name' => 'Split Buyer', 'label' => 'Split Buyer (Murree)', 'used' => 650000.0, 'accounts' => 2])
        ->and($component->get('topCreditCustomers.1'))->toMatchArray(['name' => 'Single Buyer', 'used' => 500000.0, 'accounts' => 1]);
});

it('keeps the first month of the sales trend on the 31st of a month', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-03-31 10:00:00'));
    $user = createSuperAdminUser();
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => '2025-04-15', 'total_sales_amount' => 1000, 'gross_profit' => 100]);
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => '2026-03-02', 'total_sales_amount' => 2000, 'gross_profit' => 200]);

    $trend = Livewire::actingAs($user)->test(Dashboard::class)->get('monthlySalesTrend');

    expect($trend['labels'])->toHaveCount(12)
        ->and($trend['labels'][0])->toBe('Apr 2025')
        ->and($trend['sales'][0])->toEqual(1000.0)
        ->and($trend['labels'][11])->toBe('Mar 2026')
        ->and($trend['sales'][11])->toEqual(2000.0);
});

it('swaps reversed dates before keeping the period out of the future', function (string $from, string $to, string $expectedFrom, string $expectedTo) {
    $this->travelTo(Carbon\Carbon::parse('2026-11-16 10:00:00'));
    $user = createSuperAdminUser();

    Livewire::withQueryParams(['from' => $from, 'to' => $to])->actingAs($user)->test(Dashboard::class)
        ->assertSet('period.from', $expectedFrom)
        ->assertSet('period.to', $expectedTo);
})->with([
    'wholly in the future' => ['2027-01-01', '2027-01-05', '2026-11-16', '2026-11-16'],
    'reversed, ending in the future' => ['2026-11-20', '2026-11-10', '2026-11-10', '2026-11-16'],
    'reversed, in the past' => ['2026-10-31', '2026-10-01', '2026-10-01', '2026-10-31'],
]);

it('charts sales over the chosen period, by week when it is longer than two months', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-16 10:00:00'));
    $user = createSuperAdminUser();
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => '2026-09-02', 'total_sales_amount' => 1000]);
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => '2026-09-04', 'total_sales_amount' => 500]);

    $daily = Livewire::withQueryParams(['from' => '2026-09-01', 'to' => '2026-09-10'])->actingAs($user)->test(Dashboard::class)->get('dailySalesTrend');
    expect($daily['unit'])->toBe('day')
        ->and($daily['labels'])->toHaveCount(10)
        ->and($daily['labels'][0])->toBe('01 Sep')
        ->and($daily['sales'][1])->toEqual(1000.0);

    // 1 Jul (Wednesday) to 30 Sep: the first week is cut to start on 1 Jul, then Monday to Sunday.
    $weekly = Livewire::withQueryParams(['from' => '2026-07-01', 'to' => '2026-09-30'])->actingAs($user)->test(Dashboard::class)->get('dailySalesTrend');
    expect($weekly['unit'])->toBe('week')
        ->and($weekly['labels'][0])->toBe('01 Jul')
        ->and($weekly['labels'][1])->toBe('06 Jul')
        ->and($weekly['sales'][array_search('31 Aug', $weekly['labels'], true)])->toEqual(1500.0);
});

it('works out recovery against new credit from the same settlement figures as the credit sales tile', function () {
    $user = createSuperAdminUser();
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 5000, 'credit_sales_amount' => 2000, 'credit_recoveries' => 1500]);

    $insights = collect(Livewire::actingAs($user)->test(Dashboard::class)->get('insights'))->keyBy('title');

    expect($insights['Recovery against new credit']['value'])->toBe('75%');
});

it('does not show the company-wide draft journal entry count on a supplier user\'s dashboard', function () {
    createDashboardPermissions();
    $accountingPeriod = AccountingPeriod::factory()->create();
    JournalEntry::factory()->create([
        'status' => 'draft', 'entry_date' => now(),
        'currency_id' => Currency::factory()->create()->id, 'accounting_period_id' => $accountingPeriod->id,
    ]);
    $user = User::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
    $user->givePermissionTo('journal-entry-list');

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->get('kpiCards'))->not->toHaveKey('draftJournalEntries')
        ->and($component->get('pendingItems'))->not->toHaveKey('draftJournalEntries');
});

it('adds up investment for all enabled suppliers in one pass, matching supplier by supplier', function () {
    $user = createSuperAdminUser();
    $suppliers = Supplier::factory()->count(2)->create(['disabled' => false]);
    $disabled = Supplier::factory()->create(['disabled' => true]);
    foreach ([[$suppliers[0], 400000, 150000], [$suppliers[1], 90000, 300000], [$disabled, 777000, 0]] as [$supplier, $invoice, $online]) {
        LedgerRegister::create([
            'supplier_id' => $supplier->id, 'transaction_date' => now()->subMonth()->toDateString(), 'document_type' => DocumentType::cases()[0],
            'invoice_amount' => $invoice, 'online_amount' => $online, 'opening_balance' => 0, 'expenses_amount' => 0,
            'za_point_five_percent_amount' => 0, 'claim_adjust_amount' => 0, 'balance' => 0, 'posted_at' => now(),
        ]);
    }
    ClaimRegister::factory()->create(['supplier_id' => $suppliers[1]->id, 'transaction_date' => now()->subMonth()->toDateString(), 'debit' => 25000, 'credit' => 0, 'posted_at' => now()]);

    $investment = Livewire::actingAs($user)->test(Dashboard::class)->get('investment');

    $service = app(InvestmentSummaryService::class);
    $oneByOne = fn (string $date) => collect(Supplier::where('disabled', false)->pluck('id'))
        ->map(fn ($id) => $service->mainInvestment($date, (int) $id))
        ->reduce(fn (array $sum, array $p) => [
            'stock' => $sum['stock'] + $p['stock'], 'credit' => $sum['credit'] + $p['credit'], 'claims' => $sum['claims'] + $p['claims'],
            'expiry' => $sum['expiry'] + $p['powder_expiry'] + $p['liquid_expiry'], 'ledger' => $sum['ledger'] + $p['ledger'],
        ], ['stock' => 0.0, 'credit' => 0.0, 'claims' => 0.0, 'expiry' => 0.0, 'ledger' => 0.0]);

    expect($investment['parts'])->toEqual(array_map(fn ($v) => round($v, 2), $oneByOne($investment['as_of'])))
        ->and($investment['before'])->toEqual(array_map(fn ($v) => round($v, 2), $oneByOne($investment['before_on'])))
        ->and($investment['parts']['ledger'])->toEqual(-40000.0)
        ->and($investment['parts']['claims'])->toEqual(25000.0);
});

// ── Layouts ─────────────────────────────────────────────────────────

it('renders the focus layout when option A is chosen for the dashboard', function () {
    $user = createSuperAdminUser();
    SalesSettlement::factory()->create([
        'status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 5000,
        'cash_sales_amount' => 3000, 'credit_sales_amount' => 2000, 'gross_profit' => 1500,
    ]);
    SalesSettlement::factory()->create(['status' => 'draft']);
    AppSetting::put('layout.dashboard', PageLayout::OptionA->value);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertViewIs('livewire.dashboard-focus')
        ->assertSeeHtml('data-dashboard-layout="focus"')
        ->assertSeeHtml('id="fx-chart-sales"')
        ->assertSee(['Total sales', 'Gross profit', 'Needs attention', 'Draft Settlements', 'Leaders', 'Sales mix', 'This month', 'Custom'])
        ->assertDontSee('Pending Actions');
});

it('keeps the classic layout when classic is chosen for the dashboard', function () {
    $user = createSuperAdminUser();
    AppSetting::put('layout.dashboard', PageLayout::Classic->value);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertViewIs('livewire.dashboard')
        ->assertDontSeeHtml('data-dashboard-layout="focus"')
        ->assertSee('Sales breakdown');
});

it('hides the sales cards in the focus layout from users without sales permissions', function () {
    createDashboardPermissions();
    $user = User::factory()->create();
    $user->givePermissionTo(['goods-receipt-note-list', 'inventory-view']);
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now(), 'total_sales_amount' => 5000]);
    AppSetting::put('layout.dashboard', PageLayout::OptionA->value);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSeeHtml('data-dashboard-layout="focus"')
        ->assertDontSeeHtml('id="fx-chart-sales"')
        ->assertDontSee(['Total sales', 'Gross profit', 'Sales mix', 'Salesmen']);
});
