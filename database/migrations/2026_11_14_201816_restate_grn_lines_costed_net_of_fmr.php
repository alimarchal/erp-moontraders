<?php

use App\Services\GrnCostDriftService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FMR is income: the GRN journal credits it to 4210/4220 and the stock is carried at its cost before FMR. For
 * suppliers marked "FMR allowed" (Engro) the GRN form deducted FMR from the line cost as well, so every such GRN
 * put the allowance on Round Off (5271) to balance. This restates those lines at their cost before FMR, re-costs the
 * stock issued, sold and returned from them, and posts one entry per GRN that moves the Round Off to Stock In Hand,
 * Van Stock and cost of goods sold. Profit over the whole period does not change; Round Off, stock value and
 * cost of goods sold are each shown as they should be.
 *
 * A GRN that cannot be restated safely (revalued batch, Round Off not holding the amount) is left alone and logged.
 * Running again does nothing for a GRN already restated, since its lines are then costed before FMR.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(GrnCostDriftService::class);
        $grnIds = $service->fmrNettedGrnIds();

        if ($grnIds->isEmpty()) {
            return;
        }

        $totals = ['grns' => 0, 'lines' => 0, 'movements' => 0, 'settlements' => 0, 'round_off' => 0.0];
        $skipped = [];

        foreach ($grnIds as $grnId) {
            try {
                $result = DB::transaction(fn () => $service->repairFmrNettedCost($grnId));
            } catch (Throwable $e) {
                $skipped[] = "GRN #{$grnId}: {$e->getMessage()}";

                continue;
            }

            if ($result['note'] !== null) {
                $skipped[] = $result['note'];

                continue;
            }

            $totals['grns']++;
            $totals['lines'] += $result['lines'];
            $totals['movements'] += $result['movements'];
            $totals['settlements'] += $result['settlements'];
            $totals['round_off'] = round($totals['round_off'] + $result['round_off'], 2);
        }

        Log::info('GRN lines costed net of FMR restated at their cost before FMR', $totals + ['skipped' => $skipped]);

        if ($skipped !== []) {
            Log::warning('Some GRNs costed net of FMR were left for review', ['skipped' => $skipped]);
        }

        if ($totals['grns'] === 0) {
            return;
        }

        // The daily snapshots behind the stock reports carry the old costs from the first restated receipt on.
        $firstReceipt = DB::table('goods_receipt_notes')->whereIn('id', $grnIds)->min('receipt_date');

        Artisan::call('inventory:snapshots:rebuild', [
            'start_date' => substr((string) $firstReceipt, 0, 10),
            '--with-vans' => true,
            '--force' => true,
        ]);
    }

    /**
     * The restatement is a set of posted journal entries and re-costed stock documents; it is undone, if ever needed,
     * by reversing those entries (reference FMR-COST-FIX-*), not by rolling the migration back.
     */
    public function down(): void
    {
        //
    }
};
