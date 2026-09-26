<?php

use App\Enums\DocumentType;
use App\Livewire\Dashboard;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\LedgerRegister;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use App\Models\User;
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
        'supplier-payment-list', 'supplier-payment-create',
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

it('shows settlement status distribution', function () {
    $user = createSuperAdminUser();

    SalesSettlement::factory()->count(2)->create(['status' => 'draft']);
    SalesSettlement::factory()->count(3)->create([
        'status' => 'posted',
        'settlement_date' => now(),
        'total_sales_amount' => 1000,
    ]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->get('settlementStatusDistribution.labels'))->toContain('Draft');
    expect($component->get('settlementStatusDistribution.labels'))->toContain('Posted');
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

it('renders quick action links based on permissions', function () {
    createDashboardPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(['goods-receipt-note-create', 'sales-settlement-create']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('New GRN')
        ->assertSee('New Settlement')
        ->assertDontSee('New Journal')
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

    $low = Product::factory()->create(['reorder_level' => 50, 'is_active' => true]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('lowStock.0.name', $low->product_name)
        ->assertSet('sections.company', true)
        ->assertSet('scope.supplier', 'All suppliers')
        ->assertSet('salesBySupplier.labels', [$supplier->supplier_name])
        ->assertSee('Company overview');
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
        ->assertSet('kpiCards.outstandingPayables', 250000.0)
        ->assertSet('purchasesVsPayments.purchases.5', 400000.0)
        ->assertSee('Payable to suppliers');
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
        ->assertSet('creditBreakdown.by', 'supplier')
        ->assertSet('creditBreakdown.labels', ['Nestle', 'Engro'])
        ->assertSet('creditAging.values.0', 450000.0)
        ->assertSee('Credit by salesman')
        ->assertSee('Top creditors')
        ->assertSet('agingCustomers', []);

    expect($component->get('topCreditCustomers.0'))->toMatchArray(['name' => 'Big Store', 'used' => 400000.0, 'suppliers' => 'Nestle', 'last_paid_days' => 0]);

    // A Nestle user sees only Nestle's credit, split by salesman.
    $nestleUser = User::factory()->create(['supplier_id' => $nestle->id]);
    $nestleUser->givePermissionTo(Permission::firstOrCreate(['name' => 'report-audit-creditors-ledger']));

    Livewire::actingAs($nestleUser)->test(Dashboard::class)
        ->assertSet('kpiCards.marketCredit', 400000.0)
        ->assertCount('creditBySalesman.labels', 1)
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

it('shows the ledger balance per month and a supplier wise ledger table', function () {
    $user = createSuperAdminUser();
    Permission::firstOrCreate(['name' => 'report-audit-ledger-register']);
    $nestle = Supplier::factory()->create(['supplier_name' => 'Nestle']);
    $engro = Supplier::factory()->create(['supplier_name' => 'Engro']);

    foreach ([[$nestle, now()->subMonths(8), 0, 1000000], [$nestle, now(), 600000, 300000], [$engro, now(), 200000, 500000]] as [$supplier, $date, $invoice, $online]) {
        LedgerRegister::create([
            'supplier_id' => $supplier->id, 'transaction_date' => $date->toDateString(), 'document_type' => DocumentType::cases()[0],
            'invoice_amount' => $invoice, 'online_amount' => $online, 'opening_balance' => 0, 'expenses_amount' => 0,
            'za_point_five_percent_amount' => 0, 'claim_adjust_amount' => 0, 'balance' => 0,
        ]);
    }

    $component = Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('purchasesVsPayments.balance.0', 1000000.0)   // carried in from before the 6 months
        ->assertSet('purchasesVsPayments.balance.5', 1000000.0)   // +1,000,000 - 600,000 + 300,000 - 200,000 + 500,000
        ->assertSee('Ledger register by supplier');

    expect($component->get('supplierLedger'))->toHaveCount(2)
        ->and($component->get('supplierLedger.0'))->toMatchArray(['name' => 'Nestle', 'invoices' => 600000.0, 'payments' => 300000.0, 'balance' => 700000.0])
        ->and($component->get('supplierLedger.1'))->toMatchArray(['name' => 'Engro', 'balance' => 300000.0]);
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
