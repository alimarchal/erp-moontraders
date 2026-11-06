<?php

use App\Models\ExpenseCategory;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVoucher;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['expense-vendor-list', 'expense-vendor-create', 'expense-vendor-edit', 'expense-vendor-delete', 'expense-voucher-list', 'expense-voucher-create', 'expense-voucher-view-company'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->ledger = seedExpenseLedger();
    $this->supplier = Supplier::factory()->create();
    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['expense-voucher-list', 'expense-voucher-create']);
    $this->manager = User::factory()->create(['supplier_id' => $this->supplier->id])->givePermissionTo(['expense-vendor-list', 'expense-vendor-create', 'expense-vendor-edit', 'expense-vendor-delete']);
});

it('lets a voucher maker add a shop on the spot and warns about a duplicate until confirmed', function () {
    $this->actingAs($this->maker)->postJson(route('expense-vendors.store'), ['name' => 'Raza Auto Works', 'category' => 'workshop', 'city' => 'Lahore'])
        ->assertCreated()->assertJsonPath('text', 'Raza Auto Works - Lahore');

    $this->actingAs($this->maker)->postJson(route('expense-vendors.store'), ['name' => 'raza auto works', 'category' => 'workshop', 'city' => 'Lahore'])
        ->assertStatus(409)->assertJsonPath('duplicate.text', 'Raza Auto Works - Lahore');
    expect(ExpenseVendor::count())->toBe(1);

    $this->actingAs($this->maker)->postJson(route('expense-vendors.store'), ['name' => 'raza auto works', 'category' => 'workshop', 'city' => 'Lahore', 'confirm_duplicate' => true])->assertCreated();
    expect(ExpenseVendor::count())->toBe(2);
});

it('loads shops on request by name, phone or city, and only active ones', function () {
    ExpenseVendor::factory()->create(['name' => 'Tyre House', 'city' => 'Lahore', 'phone' => '0300111']);
    ExpenseVendor::factory()->create(['name' => 'Battery Point', 'city' => 'Multan', 'phone' => null]);
    ExpenseVendor::factory()->create(['name' => 'Tyre Closed', 'is_active' => false]);

    $names = fn ($response) => collect($response->json('results'))->pluck('text')->all();

    expect($names($this->actingAs($this->maker)->getJson(route('expense-vendors.search', ['q' => 'tyre']))))->toBe(['Tyre House - Lahore (0300111)'])
        ->and($names($this->actingAs($this->maker)->getJson(route('expense-vendors.search', ['q' => 'multan']))))->toBe(['Battery Point - Multan'])
        ->and($names($this->actingAs($this->maker)->getJson(route('expense-vendors.search'))))->toHaveCount(2);
});

it('keeps the shop list, edit and delete behind their own permissions', function () {
    $vendor = ExpenseVendor::factory()->create();

    $this->actingAs($this->maker)->get(route('expense-vendors.index'))->assertForbidden();
    $this->actingAs($this->maker)->put(route('expense-vendors.update', $vendor), ['name' => 'X', 'category' => 'shop'])->assertForbidden();
    $this->actingAs($this->maker)->delete(route('expense-vendors.destroy', $vendor))->assertForbidden();

    $this->actingAs($this->manager)->get(route('expense-vendors.index'))->assertOk()->assertSee($vendor->name);
    $this->actingAs($this->manager)->put(route('expense-vendors.update', $vendor), ['name' => 'Renamed Shop', 'category' => 'shop', 'is_active' => 1])->assertRedirect();
    expect($vendor->fresh()->name)->toBe('Renamed Shop');
});

it('does not delete a shop that already has vouchers', function () {
    $vendor = ExpenseVendor::factory()->create();
    ExpenseVoucher::factory()->create(['expense_vendor_id' => $vendor->id, 'expense_account_id' => $this->ledger['5221']->id]);

    $this->actingAs($this->manager)->delete(route('expense-vendors.destroy', $vendor))->assertSessionHas('error');
    expect($vendor->fresh())->not->toBeNull();

    $unused = ExpenseVendor::factory()->create();
    $this->actingAs($this->manager)->delete(route('expense-vendors.destroy', $unused))->assertRedirect(route('expense-vendors.index'));
    expect(ExpenseVendor::find($unused->id))->toBeNull();
});

it('takes the party name from the shop and refuses a shop voucher without a shop', function () {
    $vendor = ExpenseVendor::factory()->create(['name' => 'Tyre House']);
    $payload = ['payee_type' => 'vendor', 'expense_category_id' => ExpenseCategory::factory()->create()->id, 'voucher_date' => now()->toDateString(), 'owner' => 'supplier:'.$this->supplier->id, 'expense_account_id' => $this->ledger['5221']->id, 'amount' => 900, 'action' => 'draft'];

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), $payload)->assertSessionHasErrors('expense_vendor_id');

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), $payload + ['expense_vendor_id' => $vendor->id, 'payment_type' => 'credit', 'vendor_bill_no' => 'B-77'])->assertRedirect();

    $voucher = ExpenseVoucher::first();
    expect($voucher->party_name)->toBe('Tyre House')
        ->and($voucher->payee_type)->toBe('vendor')
        ->and($voucher->payment_type)->toBe('credit')
        ->and($voucher->vendor_bill_no)->toBe('B-77');
});

it('refuses a voucher for someone else with no name', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'payee_type' => 'other', 'expense_category_id' => ExpenseCategory::factory()->create()->id, 'voucher_date' => now()->toDateString(), 'owner' => 'supplier:'.$this->supplier->id,
        'expense_account_id' => $this->ledger['5221']->id, 'amount' => 100,
    ])->assertSessionHasErrors('party_name');
});

it('needs a category on a voucher, fills the account from it, and keeps a manually chosen account', function () {
    $category = ExpenseCategory::factory()->create(['name' => 'Toner', 'default_expense_account_id' => $this->ledger['5221']->id]);
    $base = ['payee_type' => 'other', 'party_name' => 'Print Shop', 'voucher_date' => now()->toDateString(), 'owner' => 'supplier:'.$this->supplier->id, 'amount' => 500, 'action' => 'draft'];

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), $base + ['expense_account_id' => $this->ledger['5281']->id])->assertSessionHasErrors('expense_category_id');

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), $base + ['expense_category_id' => $category->id])->assertRedirect();
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), $base + ['expense_category_id' => $category->id, 'expense_account_id' => $this->ledger['5281']->id])->assertRedirect();

    $accounts = ExpenseVoucher::orderBy('id')->pluck('expense_account_id')->all();
    expect($accounts)->toBe([$this->ledger['5221']->id, $this->ledger['5281']->id])
        ->and(ExpenseVoucher::first()->category->name)->toBe('Toner');
});

it('refuses an inactive category', function () {
    $category = ExpenseCategory::factory()->create(['is_active' => false]);

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'payee_type' => 'other', 'party_name' => 'X', 'expense_category_id' => $category->id, 'expense_account_id' => $this->ledger['5221']->id,
        'voucher_date' => now()->toDateString(), 'owner' => 'supplier:'.$this->supplier->id, 'amount' => 5,
    ])->assertSessionHasErrors('expense_category_id');
});

it('does not allow a future voucher date but allows today and the past', function () {
    $payload = ['payee_type' => 'other', 'party_name' => 'Landlord', 'expense_category_id' => ExpenseCategory::factory()->create()->id, 'expense_account_id' => $this->ledger['5221']->id,
        'owner' => 'supplier:'.$this->supplier->id, 'amount' => 90000, 'action' => 'draft'];

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), $payload + ['voucher_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('voucher_date');
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), $payload + ['voucher_date' => now()->toDateString()])->assertRedirect();
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), $payload + ['voucher_date' => now()->subDays(20)->toDateString()])->assertRedirect();
    expect(ExpenseVoucher::count())->toBe(2);
});

it('lets rent be booked to a landlord shop on credit and lists the category filter', function () {
    $landlord = ExpenseVendor::factory()->create(['name' => 'Mr Landlord', 'category' => 'rent']);
    $rent = ExpenseCategory::factory()->create(['name' => 'Landlord Rent']);

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'payee_type' => 'vendor', 'expense_vendor_id' => $landlord->id, 'payment_type' => 'credit', 'expense_category_id' => $rent->id, 'expense_account_id' => $this->ledger['5221']->id,
        'voucher_date' => now()->toDateString(), 'owner' => 'supplier:'.$this->supplier->id, 'amount' => 80000, 'action' => 'draft',
    ])->assertRedirect();

    $this->actingAs($this->maker->givePermissionTo('expense-voucher-view-company'))->get(route('expense-vouchers.index', ['filter' => ['category_id' => $rent->id]]))->assertOk()->assertSee('Mr Landlord');
    expect(ExpenseVendor::CATEGORIES)->toHaveKey('rent');
});

it('keeps expense categories behind their own permission', function () {
    Permission::findOrCreate('expense-category-manage');
    $category = ExpenseCategory::factory()->create(['name' => 'Old']);

    $this->actingAs($this->maker)->get(route('expense-categories.index'))->assertForbidden();

    $admin = User::factory()->create()->givePermissionTo('expense-category-manage');
    $this->actingAs($admin)->post(route('expense-categories.store'), ['name' => 'Generator Diesel'])->assertSessionHas('success');
    $this->actingAs($admin)->put(route('expense-categories.update', $category), ['name' => 'Renamed'])->assertSessionHas('success');
    $this->actingAs($admin)->post(route('expense-categories.store'), ['name' => 'Renamed'])->assertSessionHasErrors('name');

    expect($category->fresh()->name)->toBe('Renamed')->and($category->fresh()->is_active)->toBeFalse();
});
