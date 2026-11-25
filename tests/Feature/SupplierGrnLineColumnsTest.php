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
 * @param  array<int, array{0: string, 1: bool}>  $columns
 * @return array<int, array{key: string, visible: string}>
 */
function grnColumnsPayload(array $columns): array
{
    return array_map(fn (array $column) => ['key' => $column[0], 'visible' => $column[1] ? '1' : '0'], $columns);
}

it('saves the GRN line column order, visibility and sales tax rate from the supplier edit form', function () {
    $supplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);

    $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), [
        'supplier_name' => 'Nestle Pakistan',
        'sales_tax' => '17.50',
        'grn_line_columns' => grnColumnsPayload([
            ['sales_tax_value', true], ['unit_price_per_case', false], ['excise_duty', true], ['other_charges', false],
        ]),
    ])->assertRedirect(route('suppliers.index'));

    $supplier->refresh();
    expect((float) $supplier->sales_tax)->toBe(17.5)
        ->and(array_column($supplier->grnLineColumns(), 'key'))->toBe([
            'sales_tax_value', 'unit_price_per_case', 'excise_duty', 'other_charges',
            'discount_value', 'fmr_allowance', 'advance_income_tax', 'withholding_tax',
        ])
        // The price is needed on every line, so it is stored as shown even when the box came unticked.
        ->and($supplier->grn_line_columns[1])->toBe(['key' => 'unit_price_per_case', 'visible' => true])
        ->and(collect($supplier->grnLineColumns())->firstWhere('key', 'other_charges')['visible'])->toBeFalse();

    $this->actingAs($this->admin)->get(route('suppliers.show', $supplier))
        ->assertSuccessful()
        ->assertSeeInOrder(['GRN line columns', 'Sales tax', 'Price / case', 'Excise duty', 'Other charges', '(hidden unless used)'])
        ->assertSee('17.50%');
});

it('rejects an unknown GRN line column and a sales tax rate above 100', function () {
    $supplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan']);

    $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), [
        'supplier_name' => 'Nestle Pakistan',
        'sales_tax' => '150',
        'grn_line_columns' => grnColumnsPayload([['freight', true]]),
    ])->assertSessionHasErrors(['sales_tax', 'grn_line_columns.0.key']);

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

it('keeps a saved order usable when it lacks columns or names unknown ones', function () {
    $columns = Supplier::resolveGrnLineColumns([['key' => 'excise_duty', 'visible' => false], ['key' => 'freight'], 'sales_tax_value', ['key' => 'excise_duty']]);

    expect(array_column($columns, 'key'))->toBe([
        'excise_duty', 'sales_tax_value', 'unit_price_per_case', 'discount_value',
        'fmr_allowance', 'advance_income_tax', 'other_charges', 'withholding_tax',
    ])->and($columns[0]['visible'])->toBeFalse()
        ->and($columns[1]['visible'])->toBeTrue();
});

it('shows the GRN view columns and totals in the supplier order, hiding unused hidden columns only', function () {
    AppSetting::put('layout.goods_receipt_notes', 'i');
    $supplier = Supplier::factory()->create(['grn_line_columns' => [
        ['key' => 'unit_price_per_case', 'visible' => true], ['key' => 'sales_tax_value', 'visible' => true],
        ['key' => 'excise_duty', 'visible' => true], ['key' => 'other_charges', 'visible' => false],
        ['key' => 'advance_income_tax', 'visible' => false],
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
        ->assertSeeInOrder(['UP / case', 'Value', 'Sales tax', 'Excise', 'Adv. IT', 'Discount', 'FMR', 'Before tax'])
        // Other charges is hidden and unused; advance income tax is hidden but has an amount, so it still shows.
        ->assertDontSee('Other charges')
        ->assertSeeInOrder(['Sales tax', '2,070.00', 'Excise', 'Adv. income tax', '13.25']);
});

it('gives the GRN form each supplier\'s column order', function () {
    AppSetting::put('layout.goods_receipt_notes', 'i');
    $supplier = Supplier::factory()->create(['grn_line_columns' => [['key' => 'sales_tax_value', 'visible' => true]]]);
    $grn = GoodsReceiptNote::factory()->create(['supplier_id' => $supplier->id, 'status' => 'draft']);
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);

    $this->actingAs($admin)->get(route('goods-receipt-notes.create'))
        ->assertSuccessful()
        ->assertSee('"grn_columns":[{"key":"sales_tax_value"', false);

    $this->actingAs($admin)->get(route('goods-receipt-notes.edit', $grn))
        ->assertSuccessful()
        ->assertSee('lineColumns: [{"key":"sales_tax_value"', false)
        ->assertSee("colShown(item, 'excise_duty')", false);
});
