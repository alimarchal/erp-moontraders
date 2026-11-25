<?php

use App\Models\AppSetting;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $names = ['supplier-list', 'supplier-create', 'supplier-edit'];
    foreach ($names as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions($names);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

/**
 * @param  list<string>  $keys
 * @return array<int, array{key: string}>
 */
function grnColumnsPayload(array $keys): array
{
    return array_map(fn (string $key) => ['key' => $key], $keys);
}

it('saves the GRN line column order from the supplier edit form, each section on its own, leaving the sales tax rate alone', function () {
    $supplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan', 'sales_tax' => 18]);

    // The rate drives GRN sales tax, so the supplier form shows it but never changes it.
    $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), [
        'supplier_name' => 'Nestle Pakistan',
        'sales_tax' => '17.50',
        'grn_line_columns' => grnColumnsPayload(['excise_duty', 'discount_value', 'sales_tax_value', 'unit_price_per_case']),
    ])->assertRedirect(route('suppliers.index'));

    $supplier->refresh();
    expect((float) $supplier->sales_tax)->toBe(18.0)
        // A tax never moves into Price & discount: each section keeps its own columns, in the order sent.
        ->and(array_column($supplier->grnLineColumns(), 'key'))->toBe([
            'discount_value', 'unit_price_per_case', 'fmr_allowance',
            'excise_duty', 'sales_tax_value', 'advance_income_tax', 'other_charges', 'withholding_tax',
        ])
        ->and($supplier->grn_line_columns[0])->toBe(['key' => 'discount_value']);

    $this->actingAs($this->admin)->get(route('suppliers.show', $supplier))
        ->assertSuccessful()
        ->assertSeeInOrder(['GRN line columns', 'Price &amp; discount', 'Discount', 'Price / case', 'Taxes &amp; charges', 'Excise duty', 'Sales tax'], false)
        ->assertSee('18.00%');
});

it('keeps the saved column order when an update does not send it', function () {
    $supplier = Supplier::factory()->create([
        'supplier_name' => 'Nestle Pakistan', 'sales_tax' => 17,
        'grn_line_columns' => [['key' => 'excise_duty']],
    ]);

    $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), ['supplier_name' => 'Nestle Pakistan'])
        ->assertRedirect(route('suppliers.index'));

    $supplier->refresh();
    expect((float) $supplier->sales_tax)->toBe(17.0)
        ->and($supplier->grn_line_columns)->toBe([['key' => 'excise_duty']]);
});

it('rejects a column named twice and redisplays the edit form after a malformed column list', function () {
    $supplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);

    $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), [
        'supplier_name' => 'Nestle Pakistan',
        'grn_line_columns' => grnColumnsPayload(['excise_duty', 'excise_duty']),
    ])->assertSessionHasErrors('grn_line_columns.1.key');

    $this->actingAs($this->admin)->from(route('suppliers.edit', $supplier))->put(route('suppliers.update', $supplier), [
        'supplier_name' => 'Nestle Pakistan',
        'grn_line_columns' => 'not-a-list',
    ])->assertRedirect(route('suppliers.edit', $supplier))->assertSessionHasErrors('grn_line_columns');

    $this->actingAs($this->admin)->get(route('suppliers.edit', $supplier))
        ->assertSuccessful()
        ->assertSee('GRN line columns: order');
});

it('rejects an unknown GRN line column', function () {
    $supplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);

    $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), [
        'supplier_name' => 'Nestle Pakistan',
        'grn_line_columns' => grnColumnsPayload(['freight']),
    ])->assertSessionHasErrors(['grn_line_columns.0.key']);

    expect($supplier->fresh()->grn_line_columns)->toBeNull();
});

it('offers the column order on edit only; a new supplier starts with the default order', function () {
    $this->actingAs($this->admin)->get(route('suppliers.create'))
        ->assertSuccessful()
        ->assertSee('GRN settings')
        ->assertSee('Change it from')
        ->assertDontSee('grn_line_columns[', false);

    $this->actingAs($this->admin)->post(route('suppliers.store'), ['supplier_name' => 'Engro Corporation', 'is_fmr_allowed' => '1'])
        ->assertRedirect(route('suppliers.index'));

    $engro = Supplier::where('supplier_name', 'Engro Corporation')->firstOrFail();
    expect($engro->grn_line_columns)->toBeNull()
        ->and($engro->is_fmr_allowed)->toBeTrue()
        ->and((float) $engro->sales_tax)->toBe(18.0)
        ->and(array_column($engro->grnLineColumns(), 'key'))->toBe(array_keys(Supplier::GRN_LINE_COLUMNS));

    $this->actingAs($this->admin)->get(route('suppliers.edit', $engro))
        ->assertSuccessful()
        ->assertSee('GRN line columns: order')
        ->assertSee('grn_line_columns[', false);
});

it('keeps a saved order usable when it lacks columns, names unknown ones or mixes the sections', function () {
    $columns = Supplier::resolveGrnLineColumns([['key' => 'excise_duty'], ['key' => 'freight'], 'fmr_allowance', ['key' => 'excise_duty']]);

    expect(array_column($columns, 'key'))->toBe([
        'fmr_allowance', 'unit_price_per_case', 'discount_value',
        'excise_duty', 'sales_tax_value', 'advance_income_tax', 'other_charges', 'withholding_tax',
    ])->and(array_column($columns, 'group'))->toBe(['price', 'price', 'price', 'taxes', 'taxes', 'taxes', 'taxes', 'taxes'])
        // No order saved: the GRN popup's own order, sales tax before excise.
        ->and(array_column(Supplier::resolveGrnLineColumns(null), 'key'))->toBe(array_keys(Supplier::GRN_LINE_COLUMNS))
        ->and(array_keys(Supplier::GRN_LINE_COLUMNS)[3])->toBe('sales_tax_value');
});

it('shows the GRN view columns and totals in the supplier order', function () {
    AppSetting::put('layout.goods_receipt_notes', 'i');
    $supplier = Supplier::factory()->create(['grn_line_columns' => [
        ['key' => 'unit_price_per_case'], ['key' => 'discount_value'], ['key' => 'fmr_allowance'],
        ['key' => 'excise_duty'], ['key' => 'sales_tax_value'], ['key' => 'advance_income_tax'],
    ]]);
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier->id, 'status' => 'draft']);
    GoodsReceiptNoteItem::factory()->create([
        'grn_id' => $grn->id, 'line_no' => 1, 'product_id' => Product::factory()->create()->id,
        'qty_in_purchase_uom' => 10, 'unit_price_per_case' => 1200, 'extended_value' => 12000, 'discount_value' => 500,
        'discounted_value_before_tax' => 11500, 'excise_duty' => 0, 'sales_tax_value' => 2070, 'advance_income_tax' => 13.25,
        'other_charges' => 0, 'total_value_with_taxes' => 13583.25,
    ]);

    $this->actingAs(User::factory()->create(['is_super_admin' => 'Yes']))
        ->get(route('goods-receipt-notes.show', $grn))
        ->assertSuccessful()
        ->assertSeeInOrder(['UP / case', 'Value', 'Discount', 'FMR', 'Before tax', 'Excise', 'Sales tax', 'Adv. IT', 'Other'])
        ->assertSeeInOrder(['Excise', 'Sales tax', '2,070.00', 'Adv. income tax', '13.25']);
});

it('gives the GRN form each supplier\'s column order', function () {
    AppSetting::put('layout.goods_receipt_notes', 'i');
    $supplier = Supplier::factory()->create(['grn_line_columns' => [['key' => 'discount_value']]]);
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier->id, 'status' => 'draft']);
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);

    $this->actingAs($admin)->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee('"grn_columns":[{"key":"discount_value"', false);

    $this->actingAs($admin)->get(route('goods-receipt-notes.edit', $grn))
        ->assertSuccessful()
        ->assertSee('lineColumns: [{"key":"discount_value"', false)
        // The popup keeps its sections; only the boxes inside them follow the order.
        ->assertSeeInOrder(['Price &amp; discount', "colOrder('unit_price_per_case')", 'Taxes &amp; charges', "colOrder('sales_tax_value')", "colOrder('excise_duty')"], false)
        ->assertDontSee('colShown(', false);
});
