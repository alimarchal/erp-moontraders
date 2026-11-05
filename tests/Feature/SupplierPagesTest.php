<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $names = ['supplier-list', 'supplier-create', 'supplier-edit', 'supplier-delete'];
    foreach ($names as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions($names);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists suppliers with search, status tabs and product counts', function () {
    $ptc = Supplier::factory()->create(['supplier_name' => 'Pakistan Tobacco Company', 'qty_entry_mode' => 'carton']);
    Product::factory()->count(2)->create(['supplier_id' => $ptc->id]);
    Supplier::factory()->create(['supplier_name' => 'Old Disabled Co', 'disabled' => true]);

    $this->actingAs($this->admin)->get(route('suppliers.index'))
        ->assertSuccessful()->assertSee('Pakistan Tobacco Company')->assertSee('Carton')->assertSee('Old Disabled Co');

    $this->actingAs($this->admin)->get(route('suppliers.index', ['filter' => ['status' => 'active']]))
        ->assertSuccessful()->assertSee('Pakistan Tobacco Company')->assertDontSee('Old Disabled Co');

    $this->actingAs($this->admin)->get(route('suppliers.index', ['filter' => ['search' => 'tobacco']]))
        ->assertSuccessful()->assertDontSee('Old Disabled Co');
});

it('creates, shows, edits and updates a supplier including its quantity entry mode', function () {
    $this->actingAs($this->admin)->get(route('suppliers.create'))->assertSuccessful()->assertSee('Quantity entry on GRN');

    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'supplier_name' => 'Pakistan Tobacco Company',
        'qty_entry_mode' => 'carton',
    ])->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::where('supplier_name', 'Pakistan Tobacco Company')->firstOrFail();
    expect($supplier->qty_entry_mode)->toBe('carton');

    $this->actingAs($this->admin)->get(route('suppliers.show', $supplier))->assertSuccessful()->assertSee('Carton');
    $this->actingAs($this->admin)->get(route('suppliers.edit', $supplier))->assertSuccessful();

    $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), [
        'supplier_name' => 'Pakistan Tobacco Company',
        'qty_entry_mode' => 'pieces',
    ])->assertRedirect(route('suppliers.index'));

    expect($supplier->fresh()->qty_entry_mode)->toBe('pieces');
});

it('rejects an unknown quantity entry mode', function () {
    $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'supplier_name' => 'Bad Mode Co',
        'qty_entry_mode' => 'pallet',
    ])->assertSessionHasErrors('qty_entry_mode');
});

it('shows the help catalog', function () {
    $this->actingAs($this->admin)->get(route('suppliers.help'))->assertSuccessful()->assertSee('How suppliers work');
});
