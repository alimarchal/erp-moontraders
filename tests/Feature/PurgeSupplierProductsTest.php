<?php

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;

beforeEach(function () {
    $this->company = Supplier::factory()->create(['supplier_name' => 'English Biscuit Manufacturers', 'short_name' => 'EBM']);
    $this->other = Supplier::factory()->create();
});

it('permanently deletes every product of the company, soft-deleted ones included', function () {
    $live = Product::factory()->create(['supplier_id' => $this->company->id]);
    $trashed = Product::factory()->create(['supplier_id' => $this->company->id]);
    $trashed->delete();
    $kept = Product::factory()->create(['supplier_id' => $this->other->id]);

    $this->artisan('products:purge-supplier', ['supplier' => 'ebm', '--force' => true])->assertSuccessful();

    expect(Product::withTrashed()->whereKey([$live->id, $trashed->id])->exists())->toBeFalse()
        ->and(Product::whereKey($kept->id)->exists())->toBeTrue();
});

it('only lists the products without --force', function () {
    Product::factory()->count(2)->create(['supplier_id' => $this->company->id]);

    $this->artisan('products:purge-supplier', ['supplier' => (string) $this->company->id])->assertSuccessful();

    expect(Product::where('supplier_id', $this->company->id)->count())->toBe(2);
});

it('refuses when a product of the company is already used', function () {
    $used = Product::factory()->create(['supplier_id' => $this->company->id]);
    StockBatch::factory()->create(['product_id' => $used->id]);

    $this->artisan('products:purge-supplier', ['supplier' => 'EBM', '--force' => true])->assertFailed();

    expect(Product::whereKey($used->id)->exists())->toBeTrue();
});
