<?php

use App\Models\GoodsReceiptNote;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CartonConversionService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function cartonProduct(array $overrides = []): Product
{
    return Product::factory()->create(array_merge([
        'pieces_per_carton' => 120,
        'units_per_piece' => 15,
    ], $overrides));
}

it('converts PTC invoice 200893117 row 1 to pieces, TH, total and unit cost', function () {
    $result = (new CartonConversionService)->convert(cartonProduct(), 2, 0, 10163.58);

    expect($result['pieces'])->toBe(240)
        ->and($result['qty_th'])->toBe(3.6)
        ->and($result['total'])->toBe(36588.89)
        ->and($result['unit_cost'])->toBe(round(36588.89 / 240, 6));
});

it('adds loose pieces on top of cartons', function () {
    $result = (new CartonConversionService)->convert(cartonProduct(), 1, 19, 10000);

    expect($result['pieces'])->toBe(139);
});

it('refuses carton mode for a product missing pack configuration', function () {
    (new CartonConversionService)->convert(cartonProduct(['units_per_piece' => null]), 2, 0, 10000);
})->throws(DomainException::class, 'Pouches per piece');

it('formats pieces as cartons plus loose pieces', function () {
    expect(cartonProduct()->formatCartons(139))->toBe('1 ctn + 19 pcs')
        ->and(Product::factory()->create(['pieces_per_carton' => null])->formatCartons(50))->toBeNull();
});

function cartonGrnPayload(Supplier $supplier, Product $product, array $itemOverrides = []): array
{
    $uom = Uom::factory()->create();

    return [
        'receipt_date' => now()->toDateString(),
        'supplier_id' => $supplier->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'items' => [array_merge([
            'product_id' => $product->id,
            'stock_uom_id' => $uom->id,
            'purchase_uom_id' => $uom->id,
            'qty_in_purchase_uom' => 1,
            'uom_conversion_factor' => 1,
            'qty_in_stock_uom' => 1,
            'quantity_received' => 1,
            'quantity_accepted' => 1,
            'unit_cost' => 1,
            'entered_cartons' => 2,
            'entered_loose_pieces' => 0,
            'rate_per_th' => 10163.58,
        ], $itemOverrides)],
    ];
}

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'goods-receipt-note-create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'goods-receipt-note-list', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['goods-receipt-note-create', 'goods-receipt-note-list']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('saves a carton-mode GRN in pieces with unit cost derived on the server', function () {
    $supplier = Supplier::factory()->create(['qty_entry_mode' => 'carton']);
    $product = cartonProduct(['supplier_id' => $supplier->id]);

    $this->actingAs($this->admin)
        ->post(route('goods-receipt-notes.store'), cartonGrnPayload($supplier, $product))
        ->assertSessionHasNoErrors();

    $item = GoodsReceiptNote::where('supplier_id', $supplier->id)->firstOrFail()->items()->firstOrFail();

    expect((float) $item->quantity_accepted)->toBe(240.0)
        ->and((float) $item->qty_in_stock_uom)->toBe(240.0)
        ->and((float) $item->extended_value)->toBe(36588.89)
        ->and((float) $item->unit_cost)->toBe(round(36588.89 / 240, 6))
        ->and((float) $item->entered_cartons)->toBe(2.0)
        ->and((float) $item->rate_per_th)->toBe(10163.58);
});

it('blocks a carton-mode GRN when the product has no pieces per carton', function () {
    $supplier = Supplier::factory()->create(['qty_entry_mode' => 'carton']);
    $product = cartonProduct(['supplier_id' => $supplier->id, 'pieces_per_carton' => null]);

    $this->actingAs($this->admin)
        ->post(route('goods-receipt-notes.store'), cartonGrnPayload($supplier, $product))
        ->assertSessionHasErrors('items.0.entered_cartons');

    expect(GoodsReceiptNote::where('supplier_id', $supplier->id)->count())->toBe(0);
});

it('leaves pieces-mode suppliers exactly as submitted', function () {
    $supplier = Supplier::factory()->create();
    $product = cartonProduct(['supplier_id' => $supplier->id]);

    $this->actingAs($this->admin)
        ->post(route('goods-receipt-notes.store'), cartonGrnPayload($supplier, $product, [
            'quantity_received' => 7, 'quantity_accepted' => 7, 'qty_in_stock_uom' => 7, 'unit_cost' => 12.5,
        ]))
        ->assertSessionHasNoErrors();

    $item = GoodsReceiptNote::where('supplier_id', $supplier->id)->firstOrFail()->items()->firstOrFail();

    expect((float) $item->quantity_accepted)->toBe(7.0)
        ->and((float) $item->unit_cost)->toBe(12.5)
        ->and($item->entered_cartons)->toBeNull()
        ->and($item->rate_per_th)->toBeNull();
});

it('rejects a GRN line whose accepted quantity is more than received', function () {
    $supplier = Supplier::factory()->create(['qty_entry_mode' => 'pieces']);
    $product = cartonProduct(['supplier_id' => $supplier->id]);

    $this->actingAs($this->admin)
        ->post(route('goods-receipt-notes.store'), cartonGrnPayload($supplier, $product, [
            'quantity_received' => 3,
            'quantity_accepted' => 5,
        ]))
        ->assertSessionHasErrors('items.0.quantity_accepted');

    expect(GoodsReceiptNote::count())->toBe(0);
});

it('rejects a GRN line whose accepted plus rejected does not equal received', function () {
    $supplier = Supplier::factory()->create(['qty_entry_mode' => 'pieces']);
    $product = cartonProduct(['supplier_id' => $supplier->id]);

    $this->actingAs($this->admin)
        ->post(route('goods-receipt-notes.store'), cartonGrnPayload($supplier, $product, [
            'quantity_received' => 3,
            'quantity_accepted' => 2,
        ]))
        ->assertSessionHasErrors('items.0.quantity_rejected');

    expect(GoodsReceiptNote::count())->toBe(0);
});
