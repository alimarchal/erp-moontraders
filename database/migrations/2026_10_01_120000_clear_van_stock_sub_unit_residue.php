<?php

use App\Services\InventoryGlAdjustmentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Anything smaller than this cannot be a real quantity: stock_movements keeps two decimals,
     * so a van batch holding 0.001 shows as 0.00 in every report yet still carries value.
     */
    private const RESIDUE_BELOW = 0.01;

    /**
     * A goods issue of 0.001 rice was never sold or returned, so the van kept 0.001 and Van Stock
     * (1155) kept its 1.04. Zero the rows and write the value off, once; running it again finds
     * nothing to do.
     */
    public function up(): void
    {
        $residue = DB::table('van_stock_batches')
            ->where('quantity_on_hand', '>', 0)
            ->where('quantity_on_hand', '<', self::RESIDUE_BELOW)
            ->get(['id', 'vehicle_id', 'product_id', 'quantity_on_hand', 'unit_cost']);

        if ($residue->isEmpty()) {
            return;
        }

        $value = round((float) $residue->sum(fn (object $row): float => (float) $row->quantity_on_hand * (float) $row->unit_cost), 2);
        $date = now()->toDateString();

        DB::transaction(function () use ($residue, $value, $date): void {
            foreach ($residue->groupBy(fn (object $row): string => $row->vehicle_id.'-'.$row->product_id) as $rows) {
                $first = $rows->first();

                DB::table('van_stock_balances')
                    ->where('vehicle_id', $first->vehicle_id)
                    ->where('product_id', $first->product_id)
                    ->update([
                        'quantity_on_hand' => DB::raw('ROUND(GREATEST(quantity_on_hand - '.(float) $rows->sum('quantity_on_hand').', 0), 3)'),
                        'last_updated' => now(),
                    ]);
            }

            DB::table('van_stock_batches')
                ->whereIn('id', $residue->pluck('id'))
                ->update(['quantity_on_hand' => 0, 'updated_at' => now()]);

            $entry = $value >= 0.01
                ? app(InventoryGlAdjustmentService::class)->postVanResidueWriteOff($value, 'VAN-RESIDUE-'.$date, $date)
                : null;

            Log::info('Cleared van stock residue', [
                'batches' => $residue->pluck('id')->all(),
                'value' => $value,
                'journal_entry_id' => $entry?->id,
            ]);
        });
    }

    public function down(): void
    {
        // The written-off quantity was never real stock, so there is nothing to put back.
    }
};
