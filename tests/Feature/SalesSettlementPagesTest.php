<?php

use App\Exports\SalesSettlementExport;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Support\FormOnce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

/**
 * A super admin and a draft settlement made through the store route.
 *
 * @return array{user: User, settlement: SalesSettlement}
 */
function settlementPagesDraft(): array
{
    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    $uom = Uom::factory()->create();
    $product = Product::factory()->create();
    $issue = GoodsIssue::factory()->create([
        'status' => 'issued', 'warehouse_id' => Warehouse::factory()->create()->id, 'vehicle_id' => Vehicle::factory()->create()->id,
        'employee_id' => Employee::factory()->create()->id, 'issued_by' => $user->id,
    ]);
    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $issue->id, 'line_no' => 1, 'product_id' => $product->id, 'uom_id' => $uom->id,
        'quantity_issued' => 10, 'unit_cost' => 100, 'selling_price' => 150, 'total_value' => 1500,
    ]);

    test()->actingAs($user)->post(route('sales-settlements.store'), [
        'settlement_date' => now()->toDateString(), 'goods_issue_id' => $issue->id,
        'items' => [['product_id' => $product->id, 'quantity_issued' => 10, 'quantity_sold' => 10, 'quantity_returned' => 0,
            'quantity_shortage' => 0, 'unit_cost' => 100, 'selling_price' => 150, 'batches' => []]],
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0, 'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
        FormOnce::FIELD => FormOnce::token(),
    ])->assertRedirect();

    return ['user' => $user, 'settlement' => SalesSettlement::where('goods_issue_id', $issue->id)->firstOrFail()];
}

it('puts a help button on every section of the create and edit forms', function () {
    ['user' => $user, 'settlement' => $settlement] = settlementPagesDraft();

    foreach ([route('sales-settlements.create'), route('sales-settlements.edit', $settlement)] as $url) {
        $page = $this->actingAs($user)->get($url)->assertOk();

        foreach (['items', 'credit', 'recoveries', 'cheques', 'bank', 'cash', 'expenses', 'summary'] as $section) {
            $page->assertSee('data-help-section="'.$section.'"', false);
        }

        // The help window holds a short topic for every section.
        foreach (['items', 'credit', 'recoveries', 'cheques', 'bank', 'cash', 'expenses', 'summary', 'posting'] as $topic) {
            $page->assertSee('data-help-topic="'.$topic.'"', false);
        }
    }
});

it('opens the posting help from the settlement page', function () {
    ['user' => $user, 'settlement' => $settlement] = settlementPagesDraft();

    $this->actingAs($user)->get(route('sales-settlements.show', $settlement))->assertOk()
        ->assertSee('data-help-section="posting"', false)
        ->assertSee('data-help-topic="posting"', false);
});

it('numbers the list rows and opens a quick look from the settlement number', function () {
    ['user' => $user, 'settlement' => $settlement] = settlementPagesDraft();

    $this->actingAs($user)->get(route('sales-settlements.index', ['filter' => ['settlement_date_from' => now()->subMonth()->toDateString(), 'status' => 'draft']]))->assertOk()
        ->assertSee('<th scope="col" class="gl-no">#</th>', false)
        ->assertSee('ss-peek', false)
        ->assertSee('Open full settlement')
        ->assertSee($settlement->settlement_date->format('d M Y'));
});

it('gives the recoveries card on the edit page a header for every column its rows fill', function () {
    ['user' => $user, 'settlement' => $settlement] = settlementPagesDraft();

    $page = $this->actingAs($user)->get(route('sales-settlements.edit', $settlement))->assertOk()->getContent();
    $start = strrpos(substr($page, 0, strpos($page, 'id="recoveriesTableBody"')), '<thead>');
    $head = substr($page, $start, strpos($page, '</thead>', $start) - $start);

    expect(substr_count($head, '<th '))->toBe(4)
        ->and($head)->toContain('Method')->toContain('Bank Account');
});

it('downloads the filtered settlement list as Excel with sales and net profit', function () {
    Excel::fake();
    ['user' => $user, 'settlement' => $settlement] = settlementPagesDraft();
    $filter = ['filter' => ['status' => 'all']];

    $this->actingAs($user)->get(route('sales-settlements.index', $filter + ['export' => 'xlsx']))->assertSuccessful();

    Excel::assertDownloaded('sales-settlements-'.now()->toDateString().'_to_'.now()->toDateString().'.xlsx',
        function (SalesSettlementExport $export) use ($settlement) {
            $rows = $export->query()->get();
            $row = $export->map($rows->first());

            return $rows->count() === 1 && $row[0] === $settlement->settlement_number && $row[8] === 1500.0 && $row[12] === 500.0;
        });
});

it('downloads the filtered settlement list as an A4 PDF, portrait by default and landscape on request', function () {
    ['user' => $user] = settlementPagesDraft();
    $filter = ['filter' => ['status' => 'all']];

    $portrait = $this->actingAs($user)->get(route('sales-settlements.index', $filter + ['format' => 'pdf']));
    $portrait->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($portrait->headers->get('content-disposition'))->toContain('sales-settlements-');

    $this->actingAs($user)->get(route('sales-settlements.index', $filter + ['format' => 'pdf', 'orientation' => 'landscape']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});
