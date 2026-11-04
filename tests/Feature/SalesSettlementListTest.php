<?php

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementExpense;
use App\Models\SalesSettlementItem;
use App\Models\User;
use App\Models\Vehicle;

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
});

/** Adds one sold line to a settlement. */
function addSettlementLine(SalesSettlement $settlement, float $sales, float $cogs): void
{
    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => Product::factory()->create()->id,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => $sales / 10,
        'total_sales_value' => $sales,
        'unit_cost' => $cogs / 10,
        'total_cogs' => $cogs,
    ]);
}

it('counts drafts and posted settlements for the dates and shows the totals of all pages', function () {
    $draft = SalesSettlement::factory()->create(['status' => 'draft']);
    $posted = SalesSettlement::factory()->create(['status' => 'posted']);
    SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => now()->subMonth()]);
    addSettlementLine($draft, 1000, 800);
    addSettlementLine($posted, 5000, 4000);
    SalesSettlementExpense::create(['sales_settlement_id' => $posted->id, 'expense_date' => now()->toDateString(), 'expense_account_id' => ChartOfAccount::factory()->create()->id, 'amount' => 300]);

    $response = $this->actingAs($this->user)->get(route('sales-settlements.index', ['filter' => ['status' => 'all']]));

    $response->assertSuccessful();
    expect($response->viewData('stats'))->toMatchArray(['total' => 2, 'draft' => 1, 'posted' => 1, 'draft_sales' => 1000.0])
        ->and((float) $response->viewData('totals')->total_sales_amount)->toBe(6000.0)
        ->and((float) $response->viewData('totals')->total_net_profit)->toBe(900.0);
    $response->assertSee(['Drafts to post', 'Vans not settled', $draft->settlement_number, $posted->settlement_number]);
    $response->assertSee(route('sales-settlements.edit', $draft), false);
    $response->assertDontSee(route('sales-settlements.edit', $posted), false);
});

it('filters by the status tab and keeps the tab counts', function () {
    $draft = SalesSettlement::factory()->create(['status' => 'draft']);
    SalesSettlement::factory()->create(['status' => 'posted']);

    $response = $this->actingAs($this->user)->get(route('sales-settlements.index', ['filter' => ['status' => 'draft']]));

    expect($response->viewData('settlements')->pluck('id')->all())->toBe([$draft->id])
        ->and($response->viewData('stats')['posted'])->toBe(1);
});

it('searches by settlement number, goods issue number, vehicle and salesman', function () {
    $mine = SalesSettlement::factory()->create([
        'vehicle_id' => Vehicle::factory()->create(['registration_number' => 'RIS-2196'])->id,
        'employee_id' => Employee::factory()->create(['name' => 'Ahtasham Shah'])->id,
        'goods_issue_id' => GoodsIssue::factory()->create(['issue_number' => 'GI-7777-0001'])->id,
    ]);
    SalesSettlement::factory()->create();

    foreach (['ris-2196', 'ahtasham', 'gi-7777', $mine->settlement_number] as $term) {
        $ids = $this->actingAs($this->user)->get(route('sales-settlements.index', ['filter' => ['search' => $term]]))->viewData('settlements')->pluck('id');
        expect($ids->all())->toBe([$mine->id]);
    }
});

it('sorts by sales value and keeps the old settlement number filter', function () {
    $small = SalesSettlement::factory()->create();
    $big = SalesSettlement::factory()->create();
    addSettlementLine($small, 100, 50);
    addSettlementLine($big, 900, 500);

    $ids = $this->actingAs($this->user)->get(route('sales-settlements.index', ['sort' => '-total_sales', 'filter' => ['status' => 'all']]))->viewData('settlements')->pluck('id');
    expect($ids->all())->toBe([$big->id, $small->id]);

    $filtered = $this->actingAs($this->user)->get(route('sales-settlements.index', ['filter' => ['settlement_number' => $small->settlement_number]]));
    expect($filtered->viewData('settlements')->pluck('id')->all())->toBe([$small->id]);
    $filtered->assertSee('Settlement no.');
});

it('shows every settlement on one page with per page all', function () {
    SalesSettlement::factory()->count(3)->create();

    $response = $this->actingAs($this->user)->get(route('sales-settlements.index', ['per_page' => 'all', 'filter' => ['status' => 'all']]));

    expect($response->viewData('settlements')->count())->toBe(3)
        ->and($response->viewData('perPage'))->toBe('all');
});

it('opens on the Posted tab by default and shows every status with filter status all', function () {
    $draft = SalesSettlement::factory()->create(['status' => 'draft']);
    $posted = SalesSettlement::factory()->create(['status' => 'posted']);

    $default = $this->actingAs($this->user)->get(route('sales-settlements.index'));
    expect($default->viewData('settlements')->pluck('id')->all())->toBe([$posted->id]);

    $all = $this->actingAs($this->user)->get(route('sales-settlements.index', ['filter' => ['status' => 'all']]));
    expect($all->viewData('settlements')->pluck('id')->sort()->values()->all())->toBe([$draft->id, $posted->id]);

    // Any other filter (here a vehicle) means a deliberate search, so it is not narrowed to Posted.
    $byVehicle = $this->actingAs($this->user)->get(route('sales-settlements.index', ['filter' => ['vehicle_id' => $draft->vehicle_id]]));
    expect($byVehicle->viewData('settlements')->pluck('id')->all())->toContain($draft->id);
});

it('sorts by net profit', function () {
    $low = SalesSettlement::factory()->create();
    $high = SalesSettlement::factory()->create();
    addSettlementLine($low, 1000, 900);
    addSettlementLine($high, 1000, 400);
    SalesSettlementExpense::create(['sales_settlement_id' => $low->id, 'expense_date' => now()->toDateString(), 'expense_account_id' => ChartOfAccount::factory()->create()->id, 'amount' => 300]);

    $desc = $this->actingAs($this->user)->get(route('sales-settlements.index', ['sort' => '-net_profit', 'filter' => ['status' => 'all']]));
    expect($desc->viewData('settlements')->pluck('id')->all())->toBe([$high->id, $low->id]);

    $asc = $this->actingAs($this->user)->get(route('sales-settlements.index', ['sort' => 'net_profit', 'filter' => ['status' => 'all']]));
    expect($asc->viewData('settlements')->pluck('id')->all())->toBe([$low->id, $high->id]);
});
