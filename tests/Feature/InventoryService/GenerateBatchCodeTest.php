<?php

use App\Models\StockBatch;
use App\Services\InventoryService;

beforeEach(function () {
    $this->service = app(InventoryService::class);

    $reflection = new ReflectionMethod(InventoryService::class, 'generateBatchCode');
    $reflection->setAccessible(true);
    $this->generateBatchCode = fn () => $reflection->invoke($this->service);
});

it('generates first batch code of the year', function () {
    $code = ($this->generateBatchCode)();

    $year = now()->year;
    expect($code)->toBe("BATCH-{$year}-0001");
});

it('increments batch code sequentially', function () {
    $year = now()->year;
    StockBatch::factory()->create(['batch_code' => "BATCH-{$year}-0005"]);

    $code = ($this->generateBatchCode)();

    expect($code)->toBe("BATCH-{$year}-0006");
});

it('handles batch codes beyond 9999', function () {
    $year = now()->year;
    StockBatch::factory()->create(['batch_code' => "BATCH-{$year}-9999"]);

    $code = ($this->generateBatchCode)();

    expect($code)->toBe("BATCH-{$year}-10000");
});

it('correctly increments 5-digit batch codes', function () {
    $year = now()->year;
    StockBatch::factory()->create(['batch_code' => "BATCH-{$year}-10000"]);

    $code = ($this->generateBatchCode)();

    expect($code)->toBe("BATCH-{$year}-10001");
});

it('finds the highest batch number regardless of creation order', function () {
    $year = now()->year;
    StockBatch::factory()->create(['batch_code' => "BATCH-{$year}-0003"]);
    StockBatch::factory()->create(['batch_code' => "BATCH-{$year}-0050"]);
    StockBatch::factory()->create(['batch_code' => "BATCH-{$year}-0010"]);

    $code = ($this->generateBatchCode)();

    expect($code)->toBe("BATCH-{$year}-0051");
});

it('ignores batch codes from other years', function () {
    $year = now()->year;
    StockBatch::factory()->create(['batch_code' => 'BATCH-'.($year - 1).'-0500']);

    $code = ($this->generateBatchCode)();

    expect($code)->toBe("BATCH-{$year}-0001");
});
