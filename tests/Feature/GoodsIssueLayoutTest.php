<?php

use App\Models\AppSetting;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Uom;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
});

it('keeps the classic goods issues list when no layout is chosen', function () {
    $this->actingAs($this->user)->get(route('goods-issues.index'))
        ->assertSuccessful()
        ->assertViewIs('goods-issues.index')
        ->assertSee('Help: Goods Issues');
});

it('shows the Apple goods issues list when Option A is chosen, in English only', function () {
    AppSetting::put('layout.goods_issues_index', 'a');
    $draft = GoodsIssue::factory()->create(['status' => 'draft', 'issue_date' => now(), 'total_value' => 540]);
    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $draft->id, 'line_no' => 1, 'product_id' => Product::factory()->create(['product_name' => 'Olper TBA 250ml'])->id,
        'uom_id' => Uom::factory()->create()->id, 'quantity_issued' => 12, 'selling_price' => 45, 'total_value' => 540,
    ]);
    $settled = GoodsIssue::factory()->create(['status' => 'issued', 'issue_date' => now()]);
    SalesSettlement::factory()->create(['goods_issue_id' => $settled->id, 'status' => 'posted']);

    $html = $this->actingAs($this->user)->get(route('goods-issues.index', ['filter' => ['status' => 'all']]))
        ->assertSuccessful()
        ->assertViewIs('goods-issues.index-apple')
        ->assertSee('data-layout="goods-issues-apple"', false)
        ->assertSeeInOrder(['All', 'Drafts', 'Issued', 'Not settled', 'Reversed'])
        ->assertSee('Today · '.now()->format('d M Y'))
        ->assertSee($draft->issue_number)
        ->assertSee('Olper TBA 250ml')
        ->assertSee('gx-cap-grey', false)
        ->assertSee('Settled')
        ->assertSee('Edit draft '.$draft->issue_number)
        ->assertDontSee('Help: Goods Issues')
        ->assertDontSee('<table', false)
        ->getContent();

    expect(preg_match('/\p{Arabic}/u', strip_tags($html)))->toBe(0);
});

it('keeps the per-page choice and the filters in the Apple list', function () {
    AppSetting::put('layout.goods_issues_index', 'a');
    GoodsIssue::factory()->count(3)->create(['status' => 'issued', 'issue_date' => now()]);

    $this->actingAs($this->user)->get(route('goods-issues.index', ['per_page' => 50, 'filter' => ['status' => 'all', 'settlement' => 'pending']]))
        ->assertSuccessful()
        ->assertViewHas('perPage', '50')
        ->assertSee('name="per_page" value="50"', false)
        ->assertSee('Settlement: Not settled yet');
});

it('offers the goods issues list layouts on Appearance with a preview for each', function () {
    $this->actingAs($this->user)->get(route('settings.appearance.edit'))
        ->assertSuccessful()
        ->assertSee('data-appearance-page="goods_issues_index"', false)
        ->assertSee('Option A: Apple')
        ->assertSee('images/layouts/gi-index-classic.jpg')
        ->assertSee('images/layouts/gi-index-a.jpg');

    expect(file_exists(public_path('images/layouts/gi-index-classic.jpg')))->toBeTrue()
        ->and(file_exists(public_path('images/layouts/gi-index-a.jpg')))->toBeTrue();
});
