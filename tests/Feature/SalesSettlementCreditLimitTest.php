<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A super admin, an issued goods issue worth 1,500 and a customer whose credit limit is 1,000.
 *
 * @return array{user: User, issue: GoodsIssue, product: Product, customer: Customer}
 */
function creditLimitSetup(): array
{
    $user = User::factory()->create(['is_super_admin' => 'Yes']);
    $product = Product::factory()->create();
    $issue = GoodsIssue::factory()->create([
        'status' => 'issued', 'warehouse_id' => Warehouse::factory()->create()->id, 'vehicle_id' => Vehicle::factory()->create()->id,
        'employee_id' => Employee::factory()->create()->id, 'issued_by' => $user->id,
    ]);
    GoodsIssueItem::factory()->create([
        'goods_issue_id' => $issue->id, 'line_no' => 1, 'product_id' => $product->id, 'uom_id' => Uom::factory()->create()->id,
        'quantity_issued' => 10, 'unit_cost' => 100, 'selling_price' => 150, 'total_value' => 1500,
    ]);
    $customer = Customer::factory()->create(['customer_name' => 'Limit Test Store', 'credit_limit' => 1000]);

    return compact('user', 'issue', 'product', 'customer');
}

/**
 * @return array<string, mixed>
 */
function creditLimitPayload(array $fx, float $creditAmount): array
{
    return [
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $fx['issue']->id,
        'items' => [[
            'product_id' => $fx['product']->id, 'quantity_issued' => 10, 'quantity_sold' => 10, 'quantity_returned' => 0,
            'quantity_shortage' => 0, 'unit_cost' => 100, 'selling_price' => 150, 'batches' => [],
        ]],
        'credit_sales' => json_encode([['customer_id' => $fx['customer']->id, 'sale_amount' => $creditAmount]]),
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0, 'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
    ];
}

it('ignores the customer credit limit while the switch is off (the default)', function () {
    expect(config('sales_settlement.enforce_credit_limit'))->toBeFalse();
    $fx = creditLimitSetup();

    $this->actingAs($fx['user'])->post(route('sales-settlements.store'), creditLimitPayload($fx, 1200))
        ->assertSessionHasNoErrors();

    expect(SalesSettlement::where('goods_issue_id', $fx['issue']->id)->exists())->toBeTrue();
});

it('refuses a credit sale over the customer credit limit when the switch is on', function () {
    config(['sales_settlement.enforce_credit_limit' => true]);
    $fx = creditLimitSetup();

    $this->actingAs($fx['user'])->post(route('sales-settlements.store'), creditLimitPayload($fx, 1200))
        ->assertSessionHasErrors(['credit_sales' => 'Limit Test Store: credit limit Rs 1,000.00, already owed Rs 0.00, this settlement adds Rs 1,200.00 (Rs 200.00 over).']);

    expect(SalesSettlement::where('goods_issue_id', $fx['issue']->id)->exists())->toBeFalse();
});

it('accepts a credit sale within the limit when the switch is on', function () {
    config(['sales_settlement.enforce_credit_limit' => true]);
    $fx = creditLimitSetup();

    $this->actingAs($fx['user'])->post(route('sales-settlements.store'), creditLimitPayload($fx, 900))
        ->assertSessionHasNoErrors();

    expect(SalesSettlement::where('goods_issue_id', $fx['issue']->id)->exists())->toBeTrue();
});

it('treats a customer without a credit limit as unlimited', function () {
    config(['sales_settlement.enforce_credit_limit' => true]);
    $fx = creditLimitSetup();
    $fx['customer']->update(['credit_limit' => 0]);

    $this->actingAs($fx['user'])->post(route('sales-settlements.store'), creditLimitPayload($fx, 1200))
        ->assertSessionHasNoErrors();
});

it('checks the limit on update too', function () {
    $fx = creditLimitSetup();
    $this->actingAs($fx['user'])->post(route('sales-settlements.store'), creditLimitPayload($fx, 500))->assertSessionHasNoErrors();
    $settlement = SalesSettlement::where('goods_issue_id', $fx['issue']->id)->firstOrFail();
    config(['sales_settlement.enforce_credit_limit' => true]);

    $this->actingAs($fx['user'])->put(route('sales-settlements.update', $settlement), creditLimitPayload($fx, 1100))
        ->assertSessionHasErrors('credit_sales');
});
