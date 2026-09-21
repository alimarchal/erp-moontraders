<?php

/**
 * SA-2026-0012 (damage, 10 Aug 2026) took 12 units of Olper 1000ml out of batch 2059, but it was
 * posted after the batch had been issued down to 3. Current stock stopped at 0 while the ledger
 * went to -9 and the journal expensed all 12 units — 9 of them had already been sold and costed.
 *
 * This reverses the 9 units that were never in the batch, on the adjustment's own date:
 *   - stock movement +9 (and its stock / inventory ledger rows), so the ledger ends at 0 like current stock
 *   - journal entry Dr 1151 Stock In Hand / Cr 5243 Stock Loss - Damage for 9 x 347.52 = 3,127.68
 * The adjustment document and current stock are left as they are.
 *
 * Dry run (default) — everything is rolled back:
 *   php artisan tinker --execute 'require base_path("scripts/fix_sa_2026_0012_over_deduction.php");'
 * Apply:
 *   APPLY=1 php artisan tinker --execute 'require base_path("scripts/fix_sa_2026_0012_over_deduction.php");'
 */

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\StockAdjustment;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use App\Services\AccountingService;
use App\Services\InventoryLedgerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$apply = getenv('APPLY') === '1';
$batchId = 2059;
$productId = 239;
$reverseQuantity = 9.0;
$correctionReference = 'SA-2026-0012-COR';

echo ($apply ? '*** APPLY ***' : '*** DRY RUN (rolled back) ***').PHP_EOL.PHP_EOL;

$adjustment = StockAdjustment::where('adjustment_number', 'SA-2026-0012')->first();
$original = $adjustment
    ? StockMovement::where('reference_type', StockAdjustment::class)->where('reference_id', $adjustment->id)
        ->where('stock_batch_id', $batchId)->where('quantity', '<', 0)->first()
    : null;

$batchLedger = fn (): float => (float) DB::table('stock_movements')->where('stock_batch_id', $batchId)
    ->whereNotNull('warehouse_id')->whereIn('movement_type', ['grn', 'transfer', 'adjustment', 'return', 'damage', 'theft'])
    ->sum('quantity');
$productLedger = fn (): float => (float) DB::table('stock_movements')->where('product_id', $productId)
    ->whereNotNull('warehouse_id')->whereIn('movement_type', ['grn', 'transfer', 'adjustment', 'return', 'damage', 'theft'])
    ->sum('quantity');
$productStock = fn (): float => (float) DB::table('current_stock_by_batch')->where('product_id', $productId)->sum('quantity_on_hand');

// ---- Pre-checks ----
$problems = [];

if (DB::table('journal_entries')->where('reference', $correctionReference)->exists()) {
    echo "Nothing to do - {$correctionReference} already exists.".PHP_EOL;

    return;
}

if (! $adjustment || $adjustment->status !== 'posted' || ! $original || (float) $original->quantity !== -12.0) {
    $problems[] = 'SA-2026-0012 or its -12 movement on batch 2059 was not found as expected.';
}
if (abs($batchLedger() - (-$reverseQuantity)) > 0.001) {
    $problems[] = 'Batch 2059 ledger is '.$batchLedger().', expected -9.';
}
if (abs($productStock()) > 0.001) {
    $problems[] = 'Current stock of product 239 is '.$productStock().', expected 0.';
}

$stockInHand = ChartOfAccount::where('account_code', '1151')->first();
$damageLoss = ChartOfAccount::where('account_code', '5243')->first();
$warehouseCostCenter = CostCenter::where('code', 'CC006')->first();
if (! $stockInHand || ! $damageLoss) {
    $problems[] = 'Account 1151 or 5243 not found.';
}

if ($problems !== []) {
    echo 'ABORTED - nothing written:'.PHP_EOL.' - '.implode(PHP_EOL.' - ', $problems).PHP_EOL;

    return;
}

Auth::onceUsingId($adjustment->posted_by);

$unitCost = (float) $original->unit_cost;
$value = round($reverseQuantity * $unitCost, 2);
$date = $adjustment->adjustment_date->toDateString();
$notes = "Correction of {$adjustment->adjustment_number}: batch held 3 when posted, 9 of the 12 units had already been issued";

printf('Before: batch 2059 ledger %s | product 239 ledger %s, current stock %s%s', $batchLedger(), $productLedger(), $productStock(), PHP_EOL);

DB::beginTransaction();

try {
    $movement = StockMovement::create([
        'movement_type' => 'adjustment',
        'reference_type' => StockAdjustment::class,
        'reference_id' => $adjustment->id,
        'movement_date' => $date,
        'product_id' => $productId,
        'stock_batch_id' => $batchId,
        'warehouse_id' => $original->warehouse_id,
        'quantity' => $reverseQuantity,
        'uom_id' => $original->uom_id,
        'unit_cost' => $unitCost,
        'total_value' => $value,
        'created_by' => $adjustment->posted_by,
    ]);

    StockLedgerEntry::create([
        'product_id' => $productId,
        'warehouse_id' => $original->warehouse_id,
        'stock_batch_id' => $batchId,
        'entry_date' => $date,
        'stock_movement_id' => $movement->id,
        'quantity_in' => $reverseQuantity,
        'quantity_out' => 0,
        'quantity_balance' => 0,
        'valuation_rate' => $unitCost,
        'stock_value' => 0,
        'reference_type' => StockAdjustment::class,
        'reference_id' => $adjustment->id,
        'created_at' => now(),
    ]);

    app(InventoryLedgerService::class)->recordAdjustment(
        productId: $productId,
        warehouseId: $original->warehouse_id,
        vehicleId: null,
        debitQty: $reverseQuantity,
        creditQty: 0,
        unitCost: $unitCost,
        date: $date,
        notes: $notes,
        batchId: $batchId,
        stockAdjustmentId: $adjustment->id,
    );

    // Running balances of product 239 after inserting rows back-dated to 10 Aug.
    $balance = 0.0;
    foreach (DB::table('stock_ledger_entries')->where('product_id', $productId)->where('warehouse_id', $original->warehouse_id)->orderBy('id')->get() as $entry) {
        $balance += (float) $entry->quantity_in - (float) $entry->quantity_out;
        DB::table('stock_ledger_entries')->where('id', $entry->id)->update([
            'quantity_balance' => $balance,
            'stock_value' => round($balance * (float) $entry->valuation_rate, 4),
        ]);
    }
    $balance = 0.0;
    foreach (DB::table('inventory_ledger_entries')->where('product_id', $productId)->orderBy('date')->orderBy('id')->get() as $entry) {
        $balance += (float) $entry->debit_qty - (float) $entry->credit_qty;
        DB::table('inventory_ledger_entries')->where('id', $entry->id)->update(['running_balance' => $balance]);
    }

    $result = app(AccountingService::class)->createJournalEntry([
        'entry_date' => $date,
        'reference' => $correctionReference,
        'description' => $notes,
        'lines' => [
            ['line_no' => 1, 'account_id' => $stockInHand->id, 'debit' => $value, 'credit' => 0, 'description' => "Inventory restored - {$correctionReference}", 'cost_center_id' => $warehouseCostCenter?->id],
            ['line_no' => 2, 'account_id' => $damageLoss->id, 'debit' => 0, 'credit' => $value, 'description' => "Damage expense reversed for 9 units already issued - {$correctionReference}", 'cost_center_id' => $warehouseCostCenter?->id],
        ],
        'auto_post' => true,
    ]);

    if (! $result['success'] || $result['data']->status !== 'posted') {
        throw new RuntimeException('Journal entry failed: '.$result['message']);
    }

    $adjustment->update(['notes' => trim(($adjustment->notes ?? '')."\n".$notes." (JE {$correctionReference})")]);

    // ---- Post-checks ----
    printf('After:  batch 2059 ledger %s | product 239 ledger %s, current stock %s%s', $batchLedger(), $productLedger(), $productStock(), PHP_EOL);
    printf('JE #%d %s: Dr 1151 %s / Cr 5243 %s%s', $result['data']->id, $correctionReference, number_format($value, 2), number_format($value, 2), PHP_EOL);

    if (abs($batchLedger()) > 0.001 || abs($productLedger() - $productStock()) > 0.001) {
        throw new RuntimeException('Ledger still differs from current stock.');
    }

    echo PHP_EOL.'All checks passed.'.PHP_EOL;

    if ($apply) {
        DB::commit();
        echo 'COMMITTED.'.PHP_EOL;
    } else {
        DB::rollBack();
        echo 'Rolled back - nothing saved. Run with APPLY=1 to save.'.PHP_EOL;
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo PHP_EOL.'ROLLED BACK: '.$e->getMessage().PHP_EOL;
}
