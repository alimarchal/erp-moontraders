<?php

use App\Models\GoodsReceiptNote;
use App\Models\Supplier;
use Illuminate\Database\UniqueConstraintViolationException;

it('does not allow two live GRNs with the same number', function () {
    GoodsReceiptNote::factory()->create(['grn_number' => 'GRN-2026-9001', 'supplier_id' => Supplier::factory()]);

    GoodsReceiptNote::factory()->create(['grn_number' => 'GRN-2026-9001', 'supplier_id' => Supplier::factory()]);
})->throws(UniqueConstraintViolationException::class);

it('lets a deleted GRN\'s number be used again', function () {
    GoodsReceiptNote::factory()->create(['grn_number' => 'GRN-2026-9002', 'supplier_id' => Supplier::factory()])->delete();

    $reused = GoodsReceiptNote::factory()->create(['grn_number' => 'GRN-2026-9002', 'supplier_id' => Supplier::factory()]);

    expect($reused->exists)->toBeTrue()
        ->and(GoodsReceiptNote::withTrashed()->where('grn_number', 'GRN-2026-9002')->count())->toBe(2);
});
