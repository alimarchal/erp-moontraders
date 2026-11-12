<?php

use App\Exports\GoodsIssueExport;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\User;
use App\Models\Vehicle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
});

it('counts drafts, issued, reversed and unsettled issues for the selected dates', function () {
    GoodsIssue::factory()->create(['status' => 'draft', 'issue_date' => now(), 'total_value' => 500]);
    $settled = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now(), 'total_value' => 1000]);
    GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now(), 'total_value' => 3000]);
    GoodsIssue::factory()->create(['status' => 'cancelled', 'issue_date' => now(), 'total_value' => 200]);
    GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()->subMonth(), 'total_value' => 9000]);
    SalesSettlement::factory()->create(['goods_issue_id' => $settled->id, 'status' => 'posted']);

    $response = $this->actingAs($this->user)->get(route('goods-issues.index', ['filter' => ['status' => 'all']]));

    $response->assertSuccessful();
    expect($response->viewData('stats'))->toMatchArray([
        'total' => 4, 'draft' => 1, 'issued' => 2, 'cancelled' => 1,
        'issued_value' => 4000.0, 'pending' => 1, 'pending_value' => 3000.0,
    ]);
});

it('filters to issues not settled yet and keeps the card counts', function () {
    $settled = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()]);
    $open = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()]);
    SalesSettlement::factory()->create(['goods_issue_id' => $settled->id, 'status' => 'verified']);

    $response = $this->actingAs($this->user)->get(route('goods-issues.index', ['filter' => ['settlement' => 'pending']]));

    expect($response->viewData('goodsIssues')->pluck('id')->all())->toBe([$open->id])
        ->and($response->viewData('stats')['issued'])->toBe(2);
    $response->assertSee('Not settled yet');
});

it('searches by issue number, vehicle and salesman name', function () {
    $mine = GoodsIssue::factory()->create([
        'issue_date' => now(),
        'vehicle_id' => Vehicle::factory()->create(['vehicle_number' => 'RIS-2196'])->id,
        'employee_id' => Employee::factory()->create(['name' => 'Ahtasham Shah'])->id,
    ]);
    $other = GoodsIssue::factory()->create(['issue_date' => now()]);

    foreach (['ris-2196', 'ahtasham', $mine->issue_number] as $term) {
        $ids = $this->actingAs($this->user)->get(route('goods-issues.index', ['filter' => ['search' => $term, 'status' => 'all']]))->viewData('goodsIssues')->pluck('id');
        expect($ids->all())->toBe([$mine->id]);
    }
    expect($other->id)->not->toBe($mine->id);
});

it('shows every row when all rows per page is chosen', function () {
    GoodsIssue::factory()->count(25)->create(['issue_date' => now()]);

    $response = $this->actingAs($this->user)->get(route('goods-issues.index', ['per_page' => 'all', 'filter' => ['status' => 'all']]));

    expect($response->viewData('goodsIssues')->count())->toBe(25)
        ->and($response->viewData('perPage'))->toBe('all');
});

it('downloads the filtered list as an Excel file', function () {
    Excel::fake();
    GoodsIssue::factory()->create(['issue_date' => now()]);

    $this->actingAs($this->user)->get(route('goods-issues.index', ['export' => 'xlsx', 'filter' => ['status' => 'all']]))->assertSuccessful();

    Excel::assertDownloaded('goods-issues-'.now()->toDateString().'_to_'.now()->toDateString().'.xlsx',
        fn (GoodsIssueExport $export) => $export->query()->count() === 1);
});

it('sorts by value in both directions', function () {
    $middle = GoodsIssue::factory()->create(['issue_date' => now(), 'total_value' => 500]);
    $high = GoodsIssue::factory()->create(['issue_date' => now(), 'total_value' => 900]);
    $low = GoodsIssue::factory()->create(['issue_date' => now(), 'total_value' => 100]);

    foreach (['total_value' => [$low->id, $middle->id, $high->id], '-total_value' => [$high->id, $middle->id, $low->id]] as $sort => $expected) {
        $ids = $this->actingAs($this->user)->get(route('goods-issues.index', ['sort' => $sort, 'filter' => ['status' => 'all']]))->viewData('goodsIssues')->pluck('id');
        expect($ids->all())->toBe($expected);
    }
});

it('writes names as text in the Excel file so they cannot run as formulas', function () {
    GoodsIssue::factory()->create([
        'issue_date' => now(),
        'total_value' => 750,
        'employee_id' => Employee::factory()->create(['name' => '=HYPERLINK("http://example.com","x")'])->id,
    ]);

    $file = $this->actingAs($this->user)->get(route('goods-issues.index', ['export' => 'xlsx', 'filter' => ['status' => 'all']]))->baseResponse->getFile();
    // The export's binder is set globally while writing; read the file back with the stock one.
    Cell::setValueBinder(new DefaultValueBinder);
    $sheet = IOFactory::load($file->getPathname())->getActiveSheet();

    expect($sheet->getCell('E2')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getCell('E2')->getValue())->toBe('=HYPERLINK("http://example.com","x")')
        ->and($sheet->getCell('J2')->getDataType())->toBe(DataType::TYPE_NUMERIC);
});

it('opens the goods issue list on Issued and shows everything on the All tab', function () {
    $issued = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()->toDateString()]);
    $draft = GoodsIssue::factory()->create(['status' => 'draft', 'issue_date' => now()->toDateString()]);

    $default = $this->actingAs($this->user)->get(route('goods-issues.index'))->viewData('goodsIssues')->pluck('id')->all();
    expect($default)->toContain($issued->id)->not->toContain($draft->id);

    $all = $this->actingAs($this->user)->get(route('goods-issues.index', ['filter' => ['status' => 'all']]))->viewData('goodsIssues')->pluck('id')->all();
    expect($all)->toContain($issued->id)->toContain($draft->id);
});

it('downloads the filtered list as an A4 PDF, portrait by default and landscape on request', function () {
    GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now(), 'total_value' => 1500]);
    $filter = ['filter' => ['status' => 'all', 'issue_date_from' => now()->toDateString(), 'issue_date_to' => now()->toDateString()]];

    $portrait = $this->actingAs($this->user)->get(route('goods-issues.index', $filter + ['format' => 'pdf']));
    $portrait->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($portrait->headers->get('content-disposition'))->toContain('goods-issues-');

    $this->actingAs($this->user)->get(route('goods-issues.index', $filter + ['format' => 'pdf', 'orientation' => 'landscape']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('keeps its own filter menu with searchable dropdowns and adds the header download and Excel buttons', function () {
    $this->actingAs($this->user)->get(route('goods-issues.index'))
        ->assertSuccessful()
        ->assertSee('id="gl-filter-panel"', false)
        ->assertSee('More filters')
        ->assertSee('Download as PDF (A4)')
        ->assertSee('Export Excel')
        ->assertSee('All salesmen');
});

it('carries an Urdu help window with every tab, column and the goods issue flow', function () {
    $this->actingAs($this->user)->get(route('goods-issues.index'))
        ->assertSee('Help: Goods Issues')
        ->assertSee('data-topic="tab-pending"', false)
        ->assertSee('data-topic="col-stage"', false)
        ->assertSee('flow-goods-issues', false);
});

it('shows one stage per issue: draft, on van, settlement draft, settled or reversed', function () {
    GoodsIssue::factory()->create(['status' => 'draft', 'issue_date' => now()]);
    GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()]);
    $draftSettlement = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()]);
    $settled = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()]);
    GoodsIssue::factory()->create(['status' => 'cancelled', 'issue_date' => now()]);
    SalesSettlement::factory()->create(['goods_issue_id' => $draftSettlement->id, 'status' => 'draft']);
    SalesSettlement::factory()->create(['goods_issue_id' => $settled->id, 'status' => 'posted']);

    $response = $this->actingAs($this->user)->get(route('goods-issues.index', ['filter' => ['status' => 'all']]));

    $response->assertSee('gl-stage-grey', false)->assertSee('On van · today')
        ->assertSee('Settlement draft')->assertSee('gl-stage-green', false)->assertSee('Reversed')
        ->assertSee('2 on vans, not settled')
        ->assertSee('aria-current="page"', false)
        ->assertDontSee('role="tablist"', false);
});
