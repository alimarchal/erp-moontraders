<?php

use App\Models\CurrentStock;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->warehouse = Warehouse::factory()->create(['disabled' => false, 'is_group' => true, 'is_rejected_warehouse' => true, 'city' => 'Lahore']);
});

function stockWarehouse(Warehouse $warehouse, float $quantity): void
{
    CurrentStock::create([
        'product_id' => Product::factory()->create()->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => $quantity,
        'average_cost' => 10,
        'total_value' => $quantity * 10,
    ]);
}

it('saves every field of the edit form, including checkboxes that were un-ticked', function () {
    $this->actingAs($this->user)
        ->put(route('warehouses.update', $this->warehouse), [
            'warehouse_name' => 'Renamed Store',
            'disabled' => '0', 'is_group' => '0', 'is_rejected_warehouse' => '0',
            'city' => 'Karachi', 'phone_no' => '021-111', 'address_line_1' => 'Plot 5',
        ])
        ->assertRedirect(route('warehouses.index'))
        ->assertSessionHasNoErrors();

    expect($this->warehouse->refresh())
        ->warehouse_name->toBe('Renamed Store')
        ->is_group->toBeFalse()
        ->is_rejected_warehouse->toBeFalse()
        ->city->toBe('Karachi')
        ->phone_no->toBe('021-111')
        ->address_line_1->toBe('Plot 5');
});

it('disables and re-enables a warehouse from the edit form', function () {
    $this->actingAs($this->user)
        ->put(route('warehouses.update', $this->warehouse), ['warehouse_name' => $this->warehouse->warehouse_name, 'disabled' => '1'])
        ->assertSessionHasNoErrors();
    expect($this->warehouse->refresh()->disabled)->toBeTrue();

    $this->actingAs($this->user)
        ->put(route('warehouses.update', $this->warehouse), ['warehouse_name' => $this->warehouse->warehouse_name, 'disabled' => '0'])
        ->assertSessionHasNoErrors();
    expect($this->warehouse->refresh()->disabled)->toBeFalse();
});

it('renders the edit form so an un-ticked checkbox is still submitted', function () {
    $this->actingAs($this->user)->get(route('warehouses.edit', $this->warehouse))
        ->assertOk()
        ->assertSee('<input type="hidden" name="disabled" value="0">', false)
        ->assertSee('name="disabled" value="1"', false);
});

it('deactivates an empty warehouse instead of deleting it', function () {
    $this->actingAs($this->user)
        ->delete(route('warehouses.destroy', $this->warehouse))
        ->assertRedirect(route('warehouses.index'))
        ->assertSessionHas('success');

    expect(Warehouse::withTrashed()->find($this->warehouse->id))
        ->disabled->toBeTrue()
        ->deleted_at->toBeNull();
});

it('refuses to deactivate a warehouse that still holds stock', function () {
    stockWarehouse($this->warehouse, 12);

    $this->actingAs($this->user)
        ->from(route('warehouses.index'))
        ->delete(route('warehouses.destroy', $this->warehouse))
        ->assertRedirect(route('warehouses.index'))
        ->assertSessionHas('error');

    expect($this->warehouse->refresh()->disabled)->toBeFalse();
});

it('refuses to disable a stocked warehouse through the edit form', function () {
    stockWarehouse($this->warehouse, 3);

    $this->actingAs($this->user)
        ->put(route('warehouses.update', $this->warehouse), ['warehouse_name' => $this->warehouse->warehouse_name, 'disabled' => '1'])
        ->assertSessionHasErrors('disabled');

    expect($this->warehouse->refresh()->disabled)->toBeFalse();
});

it('allows deactivation once the stock is down to zero', function () {
    stockWarehouse($this->warehouse, 0);

    $this->actingAs($this->user)->delete(route('warehouses.destroy', $this->warehouse))->assertSessionHas('success');

    expect($this->warehouse->refresh()->disabled)->toBeTrue();
});

it('lists warehouses with status tabs and offers deactivate only for empty active ones', function () {
    $stocked = Warehouse::factory()->create(['disabled' => false, 'warehouse_name' => 'Stocked Store']);
    stockWarehouse($stocked, 5);
    $inactive = Warehouse::factory()->create(['disabled' => true, 'warehouse_name' => 'Closed Store']);

    $this->actingAs($this->user)->get(route('warehouses.index'))
        ->assertOk()
        ->assertSee('Deactivate '.$this->warehouse->warehouse_name)
        ->assertSee('Stocked Store cannot be deactivated while it holds stock')
        ->assertDontSee('Deactivate Stocked Store')
        ->assertDontSee('Deactivate Closed Store');

    $this->actingAs($this->user)->get(route('warehouses.index', ['filter' => ['disabled' => '1']]))
        ->assertOk()
        ->assertSee('Closed Store')
        ->assertDontSee('Stocked Store');
});

it('forbids deactivation without the warehouse-delete permission', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('warehouses.destroy', $this->warehouse))
        ->assertForbidden();

    expect($this->warehouse->refresh()->disabled)->toBeFalse();
});
