<?php

use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\Uom;
use App\Models\User;
use App\Services\DistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

/**
 * An issued goods issue with Milk on line 1 and Cream on line 2, then two lines added later:
 * more Milk (line 3) and a new product, Juice (line 4).
 *
 * @return array{goodsIssue: GoodsIssue, milk: GoodsIssueItem, cream: GoodsIssueItem, moreMilk: GoodsIssueItem, juice: GoodsIssueItem}
 */
function goodsIssueWithAddedLines(): array
{
    actingAs(User::factory()->create(['is_super_admin' => 'Yes']));

    $goodsIssue = GoodsIssue::factory()->create(['status' => 'issued']);
    $uom = Uom::factory()->create();
    $milk = Product::factory()->create(['product_name' => 'Olper Milk']);
    $addedAt = now()->setDate(2026, 11, 9);

    $line = fn (int $lineNo, Product $product, bool $supplementary) => GoodsIssueItem::factory()->create([
        'goods_issue_id' => $goodsIssue->id,
        'line_no' => $lineNo,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'quantity_issued' => 10,
        'is_supplementary' => $supplementary,
        'supplementary_posted_at' => $supplementary ? $addedAt : null,
    ]);

    return [
        'goodsIssue' => $goodsIssue,
        'milk' => $line(1, $milk, false),
        'cream' => $line(2, Product::factory()->create(['product_name' => 'Olper Cream']), false),
        'moreMilk' => $line(3, $milk, true),
        'juice' => $line(4, Product::factory()->create(['product_name' => 'Olper Juice']), true),
    ];
}

it('lists an added line of a product under its first line, marked supplementary with its date', function () {
    $data = goodsIssueWithAddedLines();

    $lines = getJson(route('api.sales-settlements.goods-issues.items', $data['goodsIssue']->id))
        ->assertOk()
        ->json('items');

    expect(collect($lines)->pluck('id')->all())->toBe([$data['milk']->id, $data['moreMilk']->id, $data['cream']->id, $data['juice']->id])
        ->and(collect($lines)->pluck('is_supplementary')->all())->toBe([false, true, false, true])
        ->and(collect($lines)->pluck('supplementary_date')->all())->toBe([null, '09 Nov', null, '09 Nov']);
});

it('shows a settlement\'s supplementary line labelled, under its product\'s first line', function () {
    $data = goodsIssueWithAddedLines();
    $settlement = SalesSettlement::factory()->create([
        'goods_issue_id' => $data['goodsIssue']->id,
        'employee_id' => $data['goodsIssue']->employee_id,
        'vehicle_id' => $data['goodsIssue']->vehicle_id,
        'warehouse_id' => $data['goodsIssue']->warehouse_id,
    ]);

    // Saved in issue-line order, as a settlement made before the lines were added and edited afterwards.
    foreach (['milk', 'cream', 'moreMilk'] as $line) {
        SalesSettlementItem::create([
            'sales_settlement_id' => $settlement->id,
            'goods_issue_item_id' => $data[$line]->id,
            'product_id' => $data[$line]->product_id,
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_selling_price' => 1,
            'total_sales_value' => 10,
            'unit_cost' => 1,
            'total_cogs' => 10,
        ]);
    }

    $this->get(route('sales-settlements.show', $settlement))
        ->assertSuccessful()
        ->assertSeeInOrder(['Olper Milk', 'Olper Milk', 'Supplementary · 09 Nov', 'Olper Cream']);
});

/**
 * A draft settlement of the issue whose rows cover Milk, Cream and the added Milk line, but not Juice:
 * what a settlement page opened before Juice was added would save.
 *
 * @return array{settlement: SalesSettlement, rows: list<array<string, mixed>>}
 */
function settlementLeavingOutJuice(array $data): array
{
    $settlement = SalesSettlement::factory()->create([
        'status' => 'draft',
        'goods_issue_id' => $data['goodsIssue']->id,
        'employee_id' => $data['goodsIssue']->employee_id,
        'vehicle_id' => $data['goodsIssue']->vehicle_id,
        'warehouse_id' => $data['goodsIssue']->warehouse_id,
        'cash_collected' => 30,
    ]);

    $rows = collect(['milk', 'cream', 'moreMilk'])->map(fn (string $line) => [
        'goods_issue_item_id' => $data[$line]->id,
        'product_id' => $data[$line]->product_id,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'unit_cost' => 1,
        'selling_price' => 1,
        'batches' => [],
    ])->all();

    return compact('settlement', 'rows');
}

it('refuses to save a settlement that leaves out a line added to the goods issue', function () {
    $data = goodsIssueWithAddedLines();
    ['settlement' => $settlement, 'rows' => $rows] = settlementLeavingOutJuice($data);

    $this->put(route('sales-settlements.update', $settlement), [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $data['goodsIssue']->id,
        'items' => $rows,
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ])->assertSessionHas('error', fn (string $error) => str_contains($error, 'line 4 (Olper Juice, 10) is missing'));

    expect($settlement->items()->count())->toBe(0);
});

it('refuses to post a settlement that leaves out a line added to the goods issue', function () {
    $data = goodsIssueWithAddedLines();
    ['settlement' => $settlement, 'rows' => $rows] = settlementLeavingOutJuice($data);

    foreach ($rows as $row) {
        SalesSettlementItem::create([
            'sales_settlement_id' => $settlement->id,
            'goods_issue_item_id' => $row['goods_issue_item_id'],
            'product_id' => $row['product_id'],
            'quantity_issued' => 10,
            'quantity_sold' => 10,
            'quantity_returned' => 0,
            'quantity_shortage' => 0,
            'unit_selling_price' => 1,
            'total_sales_value' => 10,
            'unit_cost' => 1,
            'total_cogs' => 10,
        ]);
    }

    $result = app(DistributionService::class)->postSalesSettlement($settlement->fresh());

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('line 4 (Olper Juice, 10) is missing')
        ->and($settlement->fresh()->status)->toBe('draft');
});
