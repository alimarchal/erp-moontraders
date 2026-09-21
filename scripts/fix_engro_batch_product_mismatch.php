<?php

/**
 * Engro (supplier 4) — ledger rows booked against the wrong product.
 *
 * In May 2026 the Olper flavoured-milk batches were received and issued under the old products
 * (242–245) while the batches themselves belong to the CP products (785–788). Batches, current
 * stock and valuation layers already say CP; only the ledgers still carry the old product, so the
 * ledger-built daily snapshots show stock under the wrong product and over-state the total.
 *
 * This moves those ledger rows to their batch's product. Quantities, values and the GL are not
 * touched; documents (GRN / goods issue items) are left as they were entered.
 *
 * Dry run (default) — everything is rolled back:
 *   php artisan tinker --execute 'require base_path("scripts/fix_engro_batch_product_mismatch.php");'
 * Apply:
 *   APPLY=1 php artisan tinker --execute 'require base_path("scripts/fix_engro_batch_product_mismatch.php");'
 *
 * Safe to re-run: once applied there is nothing left to move and it stops.
 */

use Illuminate\Support\Facades\DB;

$apply = getenv('APPLY') === '1';
$supplierId = 4;

// old product => batch (CP) product
$expectedPairs = ['242-786', '243-787', '244-785', '245-788'];
$tables = ['stock_movements', 'stock_ledger_entries', 'inventory_ledger_entries'];

$mismatched = fn (string $table) => DB::table("{$table} as x")
    ->join('stock_batches as sb', 'sb.id', '=', 'x.stock_batch_id')
    ->whereColumn('x.product_id', '<>', 'sb.product_id');

$ledgerVsStock = fn (): array => DB::table('products as p')
    ->where('p.supplier_id', $supplierId)
    ->selectRaw("p.id, p.product_name,
        COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm
            WHERE sm.product_id = p.id AND sm.warehouse_id IS NOT NULL
            AND sm.movement_type IN ('grn','transfer','adjustment','return','damage','theft')), 0) as ledger_qty,
        COALESCE((SELECT SUM(c.quantity_on_hand) FROM current_stock_by_batch c WHERE c.product_id = p.id), 0) as stock_qty")
    ->get()
    ->filter(fn ($row) => abs((float) $row->ledger_qty - (float) $row->stock_qty) > 0.0001)
    ->values()
    ->all();

echo ($apply ? '*** APPLY ***' : '*** DRY RUN (rolled back) ***').PHP_EOL.PHP_EOL;

// ---- Pre-checks ----
$counts = [];
$problems = [];

foreach ($tables as $table) {
    $pairs = $mismatched($table)
        ->selectRaw("CONCAT(x.product_id, '-', sb.product_id) as pair, COUNT(*) as n")
        ->groupBy('x.product_id', 'sb.product_id')
        ->pluck('n', 'pair')
        ->all();

    $unexpected = array_diff(array_keys($pairs), $expectedPairs);
    if ($unexpected !== []) {
        $problems[] = "{$table}: unexpected product pairs ".implode(', ', $unexpected).'.';
    }

    $counts[$table] = array_sum($pairs);
}

if (array_sum($counts) === 0) {
    echo 'Nothing to move - already applied.'.PHP_EOL;

    return;
}

if ($problems !== []) {
    echo 'ABORTED - nothing written:'.PHP_EOL.' - '.implode(PHP_EOL.' - ', $problems).PHP_EOL;

    return;
}

echo 'Ledger vs current stock BEFORE:'.PHP_EOL;
foreach ($ledgerVsStock() as $row) {
    printf('  %-5d %-45s ledger %10s  stock %10s%s', $row->id, $row->product_name, number_format((float) $row->ledger_qty), number_format((float) $row->stock_qty), PHP_EOL);
}

DB::beginTransaction();

try {
    echo PHP_EOL.'Rows moved to their batch product:'.PHP_EOL;

    foreach ($tables as $table) {
        $updated = DB::table($table)
            ->join('stock_batches as sb', 'sb.id', '=', "{$table}.stock_batch_id")
            ->whereColumn("{$table}.product_id", '<>', 'sb.product_id')
            ->update(["{$table}.product_id" => DB::raw('sb.product_id')]);

        printf('  %-26s %4d (expected %d)%s', $table, $updated, $counts[$table], PHP_EOL);

        if ($updated !== $counts[$table]) {
            throw new RuntimeException("{$table}: updated {$updated} rows, expected {$counts[$table]}.");
        }
    }

    // ---- Post-checks: every Engro product's ledger must now equal its current stock ----
    $remaining = $ledgerVsStock();

    echo PHP_EOL.'Ledger vs current stock AFTER:'.PHP_EOL;
    foreach ($remaining as $row) {
        printf('  %-5d %-45s ledger %10s  stock %10s%s', $row->id, $row->product_name, number_format((float) $row->ledger_qty), number_format((float) $row->stock_qty), PHP_EOL);
    }

    // Product 239 (Olper 1000ml) is a separate 9-unit gap unrelated to this fix; it stays.
    $unexplained = array_filter($remaining, fn ($row) => (int) $row->id !== 239);
    if ($unexplained !== []) {
        throw new RuntimeException('Ledger still differs from current stock for: '.implode(', ', array_map(fn ($row) => $row->id, $unexplained)));
    }

    foreach ($tables as $table) {
        if ($mismatched($table)->count() !== 0) {
            throw new RuntimeException("{$table} still has rows on the wrong product.");
        }
    }

    echo PHP_EOL.'All checks passed.'.PHP_EOL;

    if ($apply) {
        DB::commit();
        echo 'COMMITTED. Now rebuild: php artisan inventory:snapshots:rebuild 2026-03-31 2026-08-31 --supplier_id=4 --with-vans'.PHP_EOL;
    } else {
        DB::rollBack();
        echo 'Rolled back - nothing saved. Run with APPLY=1 to save.'.PHP_EOL;
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo PHP_EOL.'ROLLED BACK: '.$e->getMessage().PHP_EOL;
}
