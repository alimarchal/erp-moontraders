<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an explicit link from `stock_movements` to the `goods_issue_items`
     * row that produced it. Before this column existed, callers attributed
     * movements to lines via `(reference_id, product_id)`, which silently
     * broke once a single Goods Issue could carry multiple lines for the
     * same product (introduced by the supplementary-items feature).
     *
     * The backfill walks each (goods_issue_id, product_id) bucket and pairs
     * movements with items in chronological order (sm.id ASC ↔ gii.line_no
     * ASC), consuming each item's quantity_issued before moving on. This is
     * the same order the posting service inserts them in, so the pairing is
     * faithful even when an item allocates from multiple batches (one item
     * → multiple movements). Movements without a matching item are left
     * NULL (e.g. damages, adjustments — those aren't goods-issue lines).
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('goods_issue_item_id')->nullable()->after('reference_id');
            $table->index('goods_issue_item_id');
            $table->foreign('goods_issue_item_id')
                ->references('id')
                ->on('goods_issue_items')
                ->nullOnDelete();
        });

        $buckets = DB::table('stock_movements')
            ->where('reference_type', 'App\Models\GoodsIssue')
            ->where('movement_type', 'transfer')
            ->whereNull('goods_issue_item_id')
            ->select('reference_id as goods_issue_id', 'product_id')
            ->distinct()
            ->get();

        foreach ($buckets as $bucket) {
            $movements = DB::table('stock_movements')
                ->where('reference_type', 'App\Models\GoodsIssue')
                ->where('reference_id', $bucket->goods_issue_id)
                ->where('product_id', $bucket->product_id)
                ->where('movement_type', 'transfer')
                ->whereNull('goods_issue_item_id')
                ->orderBy('id')
                ->get();

            $items = DB::table('goods_issue_items')
                ->where('goods_issue_id', $bucket->goods_issue_id)
                ->where('product_id', $bucket->product_id)
                ->orderBy('line_no')
                ->get(['id', 'quantity_issued']);

            $itemIndex = 0;
            $consumedFromCurrentItem = 0.0;

            foreach ($movements as $movement) {
                if ($itemIndex >= $items->count()) {
                    break;
                }

                $movementQty = abs((float) $movement->quantity);
                $currentItem = $items[$itemIndex];

                // Greedy: assign this movement to the current item.
                DB::table('stock_movements')
                    ->where('id', $movement->id)
                    ->update(['goods_issue_item_id' => $currentItem->id]);

                $consumedFromCurrentItem += $movementQty;

                // Advance to the next item once the current one is filled.
                if ($consumedFromCurrentItem + 0.0001 >= (float) $currentItem->quantity_issued) {
                    $itemIndex++;
                    $consumedFromCurrentItem = 0.0;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['goods_issue_item_id']);
            $table->dropIndex(['goods_issue_item_id']);
            $table->dropColumn('goods_issue_item_id');
        });
    }
};
