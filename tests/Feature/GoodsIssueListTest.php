<?php

use App\Exports\GoodsIssueExport;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\Supplier;
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

    $response = $this->actingAs($this->user)->get(route('goods-issues.index'));

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
        $ids = $this->actingAs($this->user)->get(route('goods-issues.index', ['filter' => ['search' => $term]]))->viewData('goodsIssues')->pluck('id');
        expect($ids->all())->toBe([$mine->id]);
    }
    expect($other->id)->not->toBe($mine->id);
});

it('shows every row when all rows per page is chosen', function () {
    GoodsIssue::factory()->count(25)->create(['issue_date' => now()]);

    $response = $this->actingAs($this->user)->get(route('goods-issues.index', ['per_page' => 'all']));

    expect($response->viewData('goodsIssues')->count())->toBe(25)
        ->and($response->viewData('perPage'))->toBe('all');
});

it('summarises issues by salesman and still filters by supplier', function () {
    $supplier = Supplier::factory()->create();
    $ali = Employee::factory()->create(['name' => 'Ali', 'supplier_id' => $supplier->id]);
    GoodsIssue::factory()->create(['employee_id' => $ali->id, 'supplier_id' => $supplier->id, 'status' => 'issued', 'issue_date' => now(), 'total_value' => 700]);
    GoodsIssue::factory()->create(['employee_id' => $ali->id, 'supplier_id' => $supplier->id, 'status' => 'cancelled', 'issue_date' => now(), 'total_value' => 999]);
    GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now(), 'total_value' => 100]);

    $response = $this->actingAs($this->user)->get(route('goods-issues.index', ['filter' => ['supplier_id' => $supplier->id, 'status' => 'issued']]));

    $response->assertSuccessful();
    $row = $response->viewData('bySalesman')->firstWhere('employee_id', $ali->id);
    expect($response->viewData('bySalesman'))->toHaveCount(1)
        ->and((float) $row->value)->toBe(700.0)
        ->and((int) $row->pending)->toBe(1);
});

it('downloads the filtered list as an Excel file', function () {
    Excel::fake();
    GoodsIssue::factory()->create(['issue_date' => now()]);

    $this->actingAs($this->user)->get(route('goods-issues.index', ['export' => 'xlsx']))->assertSuccessful();

    Excel::assertDownloaded('goods-issues-'.now()->toDateString().'_to_'.now()->toDateString().'.xlsx',
        fn (GoodsIssueExport $export) => $export->query()->count() === 1);
});

it('sorts by value in both directions', function () {
    $middle = GoodsIssue::factory()->create(['issue_date' => now(), 'total_value' => 500]);
    $high = GoodsIssue::factory()->create(['issue_date' => now(), 'total_value' => 900]);
    $low = GoodsIssue::factory()->create(['issue_date' => now(), 'total_value' => 100]);

    foreach (['total_value' => [$low->id, $middle->id, $high->id], '-total_value' => [$high->id, $middle->id, $low->id]] as $sort => $expected) {
        $ids = $this->actingAs($this->user)->get(route('goods-issues.index', ['sort' => $sort]))->viewData('goodsIssues')->pluck('id');
        expect($ids->all())->toBe($expected);
    }
});

it('writes names as text in the Excel file so they cannot run as formulas', function () {
    GoodsIssue::factory()->create([
        'issue_date' => now(),
        'total_value' => 750,
        'employee_id' => Employee::factory()->create(['name' => '=HYPERLINK("http://example.com","x")'])->id,
    ]);

    $file = $this->actingAs($this->user)->get(route('goods-issues.index', ['export' => 'xlsx']))->baseResponse->getFile();
    // The export's binder is set globally while writing; read the file back with the stock one.
    Cell::setValueBinder(new DefaultValueBinder);
    $sheet = IOFactory::load($file->getPathname())->getActiveSheet();

    expect($sheet->getCell('E2')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getCell('E2')->getValue())->toBe('=HYPERLINK("http://example.com","x")')
        ->and($sheet->getCell('J2')->getDataType())->toBe(DataType::TYPE_NUMERIC);
});

it('prints every matching issue, not only the current page', function () {
    GoodsIssue::factory()->count(21)->create(['issue_date' => now()]);

    $response = $this->actingAs($this->user)->get(route('goods-issues.index'));

    $response->assertSee('per_page=all', false)->assertSee('print=1', false);
});

it('keeps the salesman summary when one salesman matches, so the filter can be removed there', function () {
    $ali = Employee::factory()->create(['name' => 'Ali']);
    GoodsIssue::factory()->create(['employee_id' => $ali->id, 'status' => 'issued', 'issue_date' => now()]);

    $this->actingAs($this->user)->get(route('goods-issues.index', ['filter' => ['employee_id' => $ali->id]]))
        ->assertSee('Remove this filter');
});
