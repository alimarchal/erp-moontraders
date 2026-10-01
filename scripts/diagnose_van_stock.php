<?php

// READ-ONLY diagnostic for van stock residuals. Run from the project root:
//   php artisan tinker --execute="require 'scripts/diagnose_van_stock.php';"
// Safe to delete afterwards. It never writes.

use Illuminate\Support\Facades\DB;

$out = function (string $text): void {
    echo $text.PHP_EOL;
};

$out('== A. van_stock_batches with quantity_on_hand > 0 ==');
foreach (DB::table('van_stock_batches as b')
    ->join('vehicles as v', 'v.id', '=', 'b.vehicle_id')
    ->join('products as p', 'p.id', '=', 'b.product_id')
    ->where('b.quantity_on_hand', '>', 0)
    ->get(['b.id', 'v.vehicle_number', 'b.vehicle_id', 'b.product_id', 'p.product_code', 'b.goods_issue_number', 'b.quantity_on_hand', 'b.unit_cost']) as $row) {
    $out(json_encode($row));
}

$out('== B. van_stock_balances with quantity_on_hand != 0 ==');
foreach (DB::table('van_stock_balances')->where('quantity_on_hand', '!=', 0)->get() as $row) {
    $out(json_encode($row));
}

$out('== C. stock_movements residual per vehicle/product/batch (same formula as the report) ==');
$goodsIssue = 'App\\Models\\GoodsIssue';
$net = "SUM(CASE
    WHEN movement_type = 'transfer' AND reference_type = ? THEN -quantity
    WHEN movement_type = 'sale' THEN quantity
    WHEN movement_type = 'return' THEN -quantity
    WHEN movement_type = 'shortage' THEN quantity
    ELSE 0 END)";
$groups = DB::table('stock_movements')
    ->whereNotNull('vehicle_id')
    ->groupBy('vehicle_id', 'product_id', 'stock_batch_id')
    ->havingRaw("$net <> 0", [$goodsIssue])
    ->selectRaw("vehicle_id, product_id, stock_batch_id, $net as residual", [$goodsIssue])
    ->get();
foreach ($groups as $g) {
    $out(json_encode($g));
    foreach (DB::table('stock_movements')
        ->where('vehicle_id', $g->vehicle_id)->where('product_id', $g->product_id)->where('stock_batch_id', $g->stock_batch_id)
        ->orderBy('id')
        ->get(['id', 'movement_type', 'reference_type', 'reference_id', 'quantity', 'unit_cost', 'total_value', 'created_at']) as $m) {
        $out('    '.json_encode($m));
    }
}

$out('== D. Van Stock value: GL 1155 vs van_stock_batches vs movements ==');
$gl = DB::table('journal_entry_details as d')
    ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
    ->join('chart_of_accounts as a', 'a.id', '=', 'd.chart_of_account_id')
    ->where('e.status', 'posted')->where('a.account_code', '1155')
    ->selectRaw('SUM(d.debit) - SUM(d.credit) as bal')->value('bal');
$batches = DB::table('van_stock_batches')->selectRaw('SUM(quantity_on_hand * unit_cost) as v')->value('v');
$out("GL 1155 = {$gl} | van_stock_batches value = {$batches}");

$out('== E. Fractional quantities on documents (qty with decimals) ==');
$out('goods_issue_items: '.DB::table('goods_issue_items')->whereRaw('quantity_issued <> FLOOR(quantity_issued)')->count());
$out('sales_settlement_items sold/returned/shortage: '.DB::table('sales_settlement_items')
    ->whereRaw('quantity_sold <> FLOOR(quantity_sold) OR quantity_returned <> FLOOR(quantity_returned) OR quantity_shortage <> FLOOR(quantity_shortage)')->count());
$out('stock_movements (vehicle): '.DB::table('stock_movements')->whereNotNull('vehicle_id')->whereRaw('quantity <> FLOOR(quantity)')->count());
