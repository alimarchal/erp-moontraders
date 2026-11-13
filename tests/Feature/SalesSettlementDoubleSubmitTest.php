<?php

use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\SalesSettlementRevertService;
use App\Support\FormOnce;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A super admin and an issued goods issue with one line, enough for the store and update requests.
 *
 * @return array{user: User, issue: GoodsIssue, product: Product}
 */
function doubleSubmitSetup(): array
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

    return compact('user', 'issue', 'product');
}

/**
 * @return array<string, mixed>
 */
function doubleSubmitPayload(GoodsIssue $issue, Product $product, ?string $once = null): array
{
    return array_filter([
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $issue->id,
        'items' => [[
            'product_id' => $product->id, 'quantity_issued' => 10, 'quantity_sold' => 10, 'quantity_returned' => 0,
            'quantity_shortage' => 0, 'unit_cost' => 100, 'selling_price' => 150, 'batches' => [],
        ]],
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
        FormOnce::FIELD => $once,
    ], fn ($value) => $value !== null);
}

it('posts a settlement only once when the same stale model is posted twice', function () {
    $fx = issueFromTwoBatches();
    $settlement = draftSettlementForIssue($fx);
    $stale = SalesSettlement::findOrFail($settlement->id);

    $first = app(DistributionService::class)->postSalesSettlement($settlement);
    $second = app(DistributionService::class)->postSalesSettlement($stale);

    expect($first['success'])->toBeTrue($first['message'] ?? '')
        ->and($second['success'])->toBeFalse()
        ->and($second['message'])->toContain('already posted')
        ->and(JournalEntry::where('reference', $settlement->settlement_number)->count())->toBe(1);
});

it('reverts only once when revert runs twice on a stale model', function () {
    $fx = issueFromTwoBatches();
    $settlement = draftSettlementForIssue($fx);
    expect(app(DistributionService::class)->postSalesSettlement($settlement)['success'])->toBeTrue();
    $first = SalesSettlement::findOrFail($settlement->id);
    $stale = SalesSettlement::findOrFail($settlement->id);
    $reversalsBefore = JournalEntry::where('reference', 'like', 'REV-%')->count();

    $one = app(SalesSettlementRevertService::class)->revert($first);
    $two = app(SalesSettlementRevertService::class)->revert($stale);

    expect($one['success'])->toBeTrue($one['message'] ?? '')
        ->and($two['success'])->toBeFalse()
        ->and($two['message'])->toContain('Only posted settlements can be reverted')
        ->and(SalesSettlement::findOrFail($settlement->id)->status)->toBe('draft')
        ->and(JournalEntry::where('reference', 'like', 'REV-%')->count() - $reversalsBefore)->toBeLessThanOrEqual(1);
});

it('creates one draft when the same create form is sent twice', function () {
    ['user' => $user, 'issue' => $issue, 'product' => $product] = doubleSubmitSetup();
    $once = FormOnce::token();

    $this->actingAs($user)->post(route('sales-settlements.store'), doubleSubmitPayload($issue, $product, $once))->assertRedirect();
    $this->actingAs($user)->post(route('sales-settlements.store'), doubleSubmitPayload($issue, $product, $once))
        ->assertRedirect()
        ->assertSessionHas('error', FormOnce::alreadySentMessage('settlement'));

    expect(SalesSettlement::where('goods_issue_id', $issue->id)->count())->toBe(1);
});

it('treats a second create with a new token as the existing draft', function () {
    ['user' => $user, 'issue' => $issue, 'product' => $product] = doubleSubmitSetup();

    $this->actingAs($user)->post(route('sales-settlements.store'), doubleSubmitPayload($issue, $product, FormOnce::token()));
    $this->actingAs($user)->post(route('sales-settlements.store'), doubleSubmitPayload($issue, $product, FormOnce::token()));

    expect(SalesSettlement::where('goods_issue_id', $issue->id)->count())->toBe(1);
});

it('refuses a resent update form', function () {
    ['user' => $user, 'issue' => $issue, 'product' => $product] = doubleSubmitSetup();
    $this->actingAs($user)->post(route('sales-settlements.store'), doubleSubmitPayload($issue, $product, FormOnce::token()));
    $settlement = SalesSettlement::where('goods_issue_id', $issue->id)->firstOrFail();
    $once = FormOnce::token();
    expect(FormOnce::claim($once))->toBeTrue();

    $payload = doubleSubmitPayload($issue, $product, $once) + ['notes' => 'Changed on the second send'];

    $this->actingAs($user)->put(route('sales-settlements.update', $settlement), $payload)
        ->assertRedirect(route('sales-settlements.show', $settlement))
        ->assertSessionHas('error', FormOnce::alreadySentMessage('settlement'));

    expect($settlement->fresh()->notes)->not->toBe('Changed on the second send');
});

it('refuses a resent delete form', function () {
    ['user' => $user, 'issue' => $issue, 'product' => $product] = doubleSubmitSetup();
    $this->actingAs($user)->post(route('sales-settlements.store'), doubleSubmitPayload($issue, $product, FormOnce::token()));
    $settlement = SalesSettlement::where('goods_issue_id', $issue->id)->firstOrFail();
    $once = FormOnce::token();
    expect(FormOnce::claim($once))->toBeTrue();

    $this->actingAs($user)->delete(route('sales-settlements.destroy', $settlement), [FormOnce::FIELD => $once])
        ->assertRedirect(route('sales-settlements.index'))
        ->assertSessionHas('error', FormOnce::alreadySentMessage('settlement'));

    expect(SalesSettlement::whereKey($settlement->id)->exists())->toBeTrue();
});

it('refuses a resent post form', function () {
    $fx = issueFromTwoBatches();
    $settlement = draftSettlementForIssue($fx);
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $once = FormOnce::token();
    expect(FormOnce::claim($once))->toBeTrue();

    $this->actingAs($admin)->post(route('sales-settlements.post', $settlement), [FormOnce::FIELD => $once])
        ->assertRedirect(route('sales-settlements.show', $settlement))
        ->assertSessionHas('error', FormOnce::alreadySentMessage('settlement'));

    expect($settlement->fresh()->status)->toBe('draft');
});

it('refuses a resent revert form', function () {
    $fx = issueFromTwoBatches();
    $settlement = draftSettlementForIssue($fx);
    expect(app(DistributionService::class)->postSalesSettlement($settlement)['success'])->toBeTrue();
    $admin = User::factory()->create(['is_super_admin' => 'Yes', 'password' => bcrypt('secret-pass')]);
    $once = FormOnce::token();
    expect(FormOnce::claim($once))->toBeTrue();

    $this->actingAs($admin)->post(route('sales-settlements.revert', $settlement), ['password' => 'secret-pass', FormOnce::FIELD => $once])
        ->assertRedirect(route('sales-settlements.show', $settlement))
        ->assertSessionHas('error', FormOnce::alreadySentMessage('settlement'));

    expect($settlement->fresh()->status)->toBe('posted');
});

it('gives every settlement write form a one-time token and a locking button', function () {
    ['user' => $user, 'issue' => $issue, 'product' => $product] = doubleSubmitSetup();
    $this->actingAs($user)->post(route('sales-settlements.store'), doubleSubmitPayload($issue, $product, FormOnce::token()));
    $settlement = SalesSettlement::where('goods_issue_id', $issue->id)->firstOrFail();

    foreach ([route('sales-settlements.create'), route('sales-settlements.edit', $settlement)] as $url) {
        $this->actingAs($user)->get($url)->assertOk()
            ->assertSee('name="_once"', false)
            ->assertSee('data-submit-lock', false)
            ->assertSee('data-busy-label="Saving…"', false);
    }

    // The draft's show page: post and delete. The list: delete.
    expect(substr_count($this->actingAs($user)->get(route('sales-settlements.show', $settlement))->assertOk()->getContent(), 'name="_once"'))->toBeGreaterThanOrEqual(1);
    $this->actingAs($user)->get(route('sales-settlements.index', ['filter' => ['settlement_date_from' => now()->subYear()->toDateString()]]))->assertOk()
        ->assertSee('name="_once"', false)
        ->assertSee('Deleting…');

    $settlement->update(['status' => 'posted']);
    $this->actingAs($user)->get(route('sales-settlements.show', $settlement))->assertOk()
        ->assertSee('id="revertSettlementForm"', false)
        ->assertSee('name="_once"', false);
});
