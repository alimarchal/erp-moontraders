<?php

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementExpense;
use App\Models\SchemeReceived;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'report-sales-trade-offer-dashboard', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'report-sales-trade-offer-dashboard-all-companies', 'guard_name' => 'web']);

    $this->nestle = Supplier::factory()->create(['supplier_name' => 'Nestlé Pakistan', 'short_name' => 'Nestle F&B', 'disabled' => false]);
    $this->kousar = Supplier::factory()->create(['supplier_name' => 'Kausar Oil & Ghee', 'short_name' => 'Kousar', 'disabled' => false]);
    $this->ebm = Supplier::factory()->create(['supplier_name' => 'English Biscuit Manufacturers', 'short_name' => 'EBM', 'disabled' => false]);
    $this->dalda = Supplier::factory()->create(['supplier_name' => 'Dalda Foods', 'short_name' => 'Dalda', 'disabled' => false]);

    $this->user = User::factory()->create(['supplier_id' => $this->nestle->id]);
    $this->user->givePermissionTo('report-sales-trade-offer-dashboard', 'report-sales-trade-offer-dashboard-all-companies');
    $this->actingAs($this->user);
});

/**
 * A settlement of one company's salesman with its scheme, promo and percentage expense lines.
 *
 * @param  array<string, float>  $expenses  account code => amount
 */
function tradeOfferSettlement(Supplier $supplier, string $date, float $sales, array $expenses = [], string $status = 'posted'): SalesSettlement
{
    $salesman = Employee::factory()->create(['supplier_id' => $supplier->id, 'designation' => 'Salesman']);

    $settlement = SalesSettlement::factory()->create([
        'supplier_id' => $supplier->id,
        'employee_id' => $salesman->id,
        'settlement_date' => $date,
        'status' => $status,
        'total_sales_amount' => $sales,
    ]);

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

    return $settlement;
}

function tradeOfferReceived(Supplier $supplier, string $category, string $date, float $amount, bool $active = true): SchemeReceived
{
    return SchemeReceived::create([
        'supplier_id' => $supplier->id,
        'category' => $category,
        'transaction_date' => $date,
        'description' => 'Company credit note',
        'amount' => $amount,
        'is_active' => $active,
    ]);
}

/**
 * The 13-Nov management sheet for Nestle F&B, Kousar and EBM, split over a few settlements, plus
 * entries that must be left out: last month, a draft, a deleted settlement, an inactive receipt and
 * the advance tax line (1161), which is not a trade offer.
 */
function seedSampleSheet(object $test): void
{
    tradeOfferSettlement($test->nestle, '2026-11-03', 12112760, ['5292' => 50503, '5287' => 1504.65, '5223' => 129631, '1161' => 18000]);
    tradeOfferSettlement($test->nestle, '2026-11-13', 20000000, ['5292' => 85000, '5287' => 2000, '5223' => 200000]);
    tradeOfferReceived($test->nestle, 'tts_received', '2026-11-05', 126190);
    tradeOfferReceived($test->nestle, 'promo_received', '2026-11-10', 3840.46);

    tradeOfferSettlement($test->kousar, '2026-11-08', 19876838, ['5292' => 718425]);
    tradeOfferReceived($test->kousar, 'tts_received', '2026-11-12', 274925);

    tradeOfferSettlement($test->ebm, '2026-11-11', 12522042, ['5223' => 22265]);

    tradeOfferSettlement($test->nestle, '2026-10-31', 999999, ['5292' => 9999]);
    tradeOfferSettlement($test->nestle, '2026-11-13', 555555, ['5292' => 5555], 'draft');
    tradeOfferSettlement($test->kousar, '2026-11-09', 777777, ['5292' => 7777])->delete();
    tradeOfferReceived($test->kousar, 'tts_received', '2026-11-12', 88888, active: false);
    tradeOfferReceived($test->ebm, 'tts_received', '2026-11-14', 4444);
}

it('is forbidden without the report permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('reports.sales-trade-offer.index'))
        ->assertForbidden();
});

it('lays every company side by side, all companies by default with its sale, TO received and passed, percentage and balance', function () {
    seedSampleSheet($this);

    $response = $this->get(route('reports.sales-trade-offer.index', ['filter' => ['start_date' => '2026-11-01', 'end_date' => '2026-11-13']]));

    $response->assertOk()
        ->assertSeeInOrder(['DAILY SALES &amp; TRADE OFFER DASHBOARD', 'Total Sales', '64,511,640.00', 'TO Received', '404,955.46', 'TO Passed', '857,432.65', 'Net Balance', '804,373.19)', 'Dated', '13-Nov'], false)
        ->assertSeeInOrder(['Particulars', 'Dalda', 'EBM', 'Kousar', 'Nestle F&amp;B', 'Grand Total'], false)
        ->assertSee('(338,608.19)')
        ->assertSee('(443,500.00)');

    $columns = $response->viewData('report')['columns']->keyBy('label');
    expect($columns->keys()->all())->toBe(['Dalda', 'EBM', 'Kousar', 'Nestle F&B'])
        ->and(array_sum($columns['Dalda']->figures))->toBe(0.0);
    expect($columns['Nestle F&B']->figures)->toBe([
        'total_sale' => 32112760.0,
        'to_received' => 126190.0,
        'promotion_received' => 3840.46,
        'total_to_received' => 130030.46,
        'to_passed' => 135503.0,
        'promotion_passed' => 3504.65,
        'total_to_passed' => 139007.65,
        'percentage' => 329631.0,
        'balance' => -338608.19,
    ]);
    expect($columns['Kousar']->figures['total_sale'])->toBe(19876838.0)
        ->and($columns['Kousar']->figures['balance'])->toBe(-443500.0);
    expect($columns['EBM']->figures['balance'])->toBe(-22265.0);
    expect($response->viewData('report')['totals'])->toBe([
        'total_sale' => 64511640.0,
        'to_received' => 401115.0,
        'promotion_received' => 3840.46,
        'total_to_received' => 404955.46,
        'to_passed' => 853928.0,
        'promotion_passed' => 3504.65,
        'total_to_passed' => 857432.65,
        'percentage' => 351896.0,
        'balance' => -804373.19,
    ]);
});

it('treats All Companies in the filter like no company filter, leaving out disabled companies without figures', function () {
    seedSampleSheet($this);
    $closed = Supplier::factory()->create(['supplier_name' => 'Closed Distributor', 'short_name' => 'Closed', 'disabled' => true]);
    $closedWithSales = Supplier::factory()->create(['supplier_name' => 'Old Distributor', 'short_name' => 'Old', 'disabled' => true]);
    tradeOfferSettlement($closedWithSales, '2026-11-02', 5000);

    $response = $this->get(route('reports.sales-trade-offer.index', ['filter' => [
        'start_date' => '2026-11-01', 'end_date' => '2026-11-13', 'supplier_ids' => ['all', $this->kousar->id],
    ]]));

    $response->assertOk()->assertSee('<option value="all" selected>All Companies</option>', false);
    expect($response->viewData('report')['columns']->pluck('label')->all())->toBe(['Dalda', 'EBM', 'Kousar', 'Nestle F&B', 'Old'])
        ->and($response->viewData('report')['totals']['total_sale'])->toBe(64516640.0);
});

it('rejects a company value that is neither an id nor all', function () {
    $this->get(route('reports.sales-trade-offer.index', ['filter' => ['supplier_ids' => ['nestle']]]))
        ->assertSessionHasErrors('filter.supplier_ids.0');
});

it('runs from the 1st of the month to today when no dates are chosen', function () {
    $this->travelTo('2026-11-13 18:00:00');
    tradeOfferSettlement($this->ebm, '2026-11-01', 1000);
    tradeOfferSettlement($this->ebm, '2026-11-13', 2500);
    tradeOfferSettlement($this->ebm, '2026-10-31', 4000);

    $response = $this->get(route('reports.sales-trade-offer.index'));

    $response->assertOk()->assertSee('01-Nov-2026')->assertSee('13-Nov-2026');
    expect($response->viewData('report')['totals']['total_sale'])->toBe(3500.0);
});

it('shows only the chosen companies, including one with nothing in the period', function () {
    seedSampleSheet($this);

    $response = $this->get(route('reports.sales-trade-offer.index', ['filter' => [
        'start_date' => '2026-11-01', 'end_date' => '2026-11-13', 'supplier_ids' => [$this->kousar->id, $this->dalda->id],
    ]]));

    $response->assertOk();
    $columns = $response->viewData('report')['columns'];
    expect($columns->pluck('label')->all())->toBe(['Dalda', 'Kousar'])
        ->and($columns->firstWhere('label', 'Dalda')->figures['total_sale'])->toBe(0.0)
        ->and($response->viewData('report')['totals']['balance'])->toBe(-443500.0);
});

it('orders the companies by total sale when asked', function () {
    seedSampleSheet($this);

    $response = $this->get(route('reports.sales-trade-offer.index', ['filter' => [
        'start_date' => '2026-11-01', 'end_date' => '2026-11-13', 'sort' => 'sales_desc',
    ]]));

    expect($response->viewData('report')['columns']->pluck('label')->all())->toBe(['Nestle F&B', 'Kousar', 'EBM', 'Dalda']);
});

it('keeps users without the all-companies permission to their own company', function () {
    seedSampleSheet($this);
    $user = User::factory()->create(['supplier_id' => $this->kousar->id]);
    $user->givePermissionTo('report-sales-trade-offer-dashboard');
    $this->actingAs($user);
    $period = ['start_date' => '2026-11-01', 'end_date' => '2026-11-13'];

    $own = $this->get(route('reports.sales-trade-offer.index', ['filter' => $period]));

    $own->assertOk()->assertDontSee('Nestle F&amp;B', false);
    expect($own->viewData('report')['columns']->pluck('label')->all())->toBe(['Kousar']);
    $this->get(route('reports.sales-trade-offer.index', ['filter' => $period + ['supplier_ids' => [$this->nestle->id]]]))
        ->assertForbidden();
});

it('rejects an end date before the start date', function () {
    $this->from(route('reports.index'))
        ->get(route('reports.sales-trade-offer.index', ['filter' => ['start_date' => '2026-11-13', 'end_date' => '2026-11-01']]))
        ->assertRedirect(route('reports.index'))
        ->assertSessionHasErrors(['filter.end_date' => 'The filter.end date field must be a date after or equal to filter.start date.']);
});

it('is listed on the reports page for users with the permission', function () {
    Permission::firstOrCreate(['name' => 'view-any-report', 'guard_name' => 'web']);
    $this->user->givePermissionTo('view-any-report');

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Daily Sales &amp; Trade Offer Dashboard', false)
        ->assertSee(route('reports.sales-trade-offer.index'), false);
});

it('creates both permissions for super-admin and admin in the production migration and removes them on rollback', function () {
    Permission::whereIn('name', ['report-sales-trade-offer-dashboard', 'report-sales-trade-offer-dashboard-all-companies'])->delete();
    $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $salesManager = Role::firstOrCreate(['name' => 'sales-manager', 'guard_name' => 'web']);
    $migration = require database_path('migrations/2026_11_24_120000_add_sales_trade_offer_dashboard_report_permissions.php');

    $migration->up();
    $migration->up();

    expect($superAdmin->fresh()->hasPermissionTo('report-sales-trade-offer-dashboard'))->toBeTrue()
        ->and($superAdmin->fresh()->hasPermissionTo('report-sales-trade-offer-dashboard-all-companies'))->toBeTrue()
        ->and($admin->fresh()->hasPermissionTo('report-sales-trade-offer-dashboard-all-companies'))->toBeTrue()
        ->and($salesManager->fresh()->permissions)->toBeEmpty();
    expect(Permission::where('name', 'report-sales-trade-offer-dashboard')->count())->toBe(1);

    $migration->down();

    $this->assertDatabaseMissing('permissions', ['name' => 'report-sales-trade-offer-dashboard']);
    $this->assertDatabaseMissing('permissions', ['name' => 'report-sales-trade-offer-dashboard-all-companies']);
});
