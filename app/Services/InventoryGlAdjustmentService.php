<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Keeps Stock In Hand (1151) and Van Stock (1155) in the general ledger equal to the stock
 * the inventory tables hold.
 *
 * Stock documents post their journal entry once, at the cost known at the time. When a
 * batch is re-costed afterwards (a GRN edit-special), its movements, van stock and ledgers
 * move to the new cost but the posted entries cannot — so the difference is posted here as
 * an adjusting entry instead.
 */
class InventoryGlAdjustmentService
{
    public const STOCK_IN_HAND = '1151';

    public const VAN_STOCK = '1155';

    public const COST_OF_GOODS_SOLD = '5111';

    public const INVENTORY_SHORTAGE = '5213';

    public const STOCK_LOSS_OTHER = '5273';

    /**
     * Post what a re-cost changed.
     *
     * Each delta is SUM(stored quantity × (new cost − old cost)) for one movement type,
     * with the quantity signed as stock_movements stores it (issues and sales negative,
     * returns positive, adjustments either way).
     *
     * Van stock is kept at a 2-decimal cost, so when the van rows' own value change is given
     * it is used for 1155, and the rounding against the movements goes to cost of goods sold.
     *
     * @param  array{transfer?: float, sale?: float, return?: float, shortage?: float, adjustment?: float}  $deltas
     */
    public function postRecostAdjustment(array $deltas, string $reference, string $description, ?string $date = null, ?float $vanValueDelta = null): ?JournalEntry
    {
        $transfer = (float) ($deltas['transfer'] ?? 0);
        $sale = (float) ($deltas['sale'] ?? 0);
        $return = (float) ($deltas['return'] ?? 0);
        $shortage = (float) ($deltas['shortage'] ?? 0);
        $adjustment = (float) ($deltas['adjustment'] ?? 0);

        // Van stock moves opposite to the warehouse for issues and returns, and loses
        // what was sold or found short.
        $vanDelta = -$transfer + $sale - $return + $shortage;
        $vanRounding = $vanValueDelta === null ? 0.0 : $vanDelta - $vanValueDelta;

        $amounts = [
            self::VAN_STOCK => round($vanValueDelta ?? $vanDelta, 2),
            self::COST_OF_GOODS_SOLD => round(-$sale + $vanRounding, 2),
            self::INVENTORY_SHORTAGE => round(-$shortage, 2),
            self::STOCK_LOSS_OTHER => round(-$adjustment, 2),
        ];
        // Stock In Hand takes the balancing figure, so rounding can never unbalance the entry.
        $amounts = [self::STOCK_IN_HAND => round(-array_sum($amounts), 2)] + $amounts;

        return $this->post($amounts, $reference, $description, $date, [
            self::STOCK_IN_HAND => 'Warehouse stock re-costed',
            self::VAN_STOCK => 'Van stock re-costed',
            self::COST_OF_GOODS_SOLD => 'Cost of goods already sold, at the corrected cost',
            self::INVENTORY_SHORTAGE => 'Van shortages, at the corrected cost',
            self::STOCK_LOSS_OTHER => 'Stock adjustments, at the corrected cost',
        ]);
    }

    /**
     * Ledger balance against physical stock value for 1151 and 1155.
     *
     * @return array<string, array{ledger: float, stock: float, gap: float}>
     */
    public function stockLedgerGaps(): array
    {
        $stock = [
            self::STOCK_IN_HAND => round((float) DB::table('current_stock_by_batch')
                ->selectRaw('COALESCE(SUM(quantity_on_hand * unit_cost), 0) as value')->value('value'), 2),
            self::VAN_STOCK => round((float) DB::table('van_stock_batches')
                ->selectRaw('COALESCE(SUM(quantity_on_hand * unit_cost), 0) as value')->value('value'), 2),
        ];

        $gaps = [];

        foreach ($stock as $code => $value) {
            $ledger = round((float) DB::table('journal_entry_details as line')
                ->join('journal_entries as je', 'je.id', '=', 'line.journal_entry_id')
                ->join('chart_of_accounts as account', 'account.id', '=', 'line.chart_of_account_id')
                ->where('je.status', 'posted')
                ->where('account.account_code', $code)
                ->selectRaw('COALESCE(SUM(line.debit - line.credit), 0) as balance')
                ->value('balance'), 2);

            $gaps[$code] = ['ledger' => $ledger, 'stock' => $value, 'gap' => round($value - $ledger, 2)];
        }

        return $gaps;
    }

    /**
     * Bring 1151 and 1155 to the stock value, against cost of goods sold.
     */
    public function postTrueUp(string $date): ?JournalEntry
    {
        $gaps = $this->stockLedgerGaps();

        $amounts = [
            self::STOCK_IN_HAND => $gaps[self::STOCK_IN_HAND]['gap'],
            self::VAN_STOCK => $gaps[self::VAN_STOCK]['gap'],
        ];
        $amounts[self::COST_OF_GOODS_SOLD] = round(-array_sum($amounts), 2);

        return $this->post($amounts, 'STOCK-GL-TRUEUP-'.$date, "Stock ledger true-up to physical stock value as of {$date}", $date, [
            self::STOCK_IN_HAND => 'Warehouse stock brought to its batch value',
            self::VAN_STOCK => 'Van stock brought to its batch value',
            self::COST_OF_GOODS_SOLD => 'Cost differences on documents posted before a re-cost',
        ]);
    }

    /**
     * @param  array<string, float>  $amounts  positive = debit, negative = credit
     * @param  array<string, string>  $descriptions
     */
    private function post(array $amounts, string $reference, string $description, ?string $date, array $descriptions): ?JournalEntry
    {
        $amounts = array_filter($amounts, fn (float $amount) => abs($amount) >= 0.01);

        if ($amounts === []) {
            return null;
        }

        $accounts = ChartOfAccount::whereIn('account_code', array_keys($amounts))->pluck('id', 'account_code');
        $missing = array_diff(array_keys($amounts), $accounts->keys()->all());

        if ($missing !== []) {
            throw new RuntimeException('Accounts '.implode(', ', $missing).' are missing from the Chart of Accounts.');
        }

        $costCenterId = CostCenter::where('code', 'CC006')->value('id');

        if (! $costCenterId) {
            throw new RuntimeException('Cost center CC006 (Warehouse & Inventory) is missing.');
        }

        $lines = [];

        foreach ($amounts as $code => $amount) {
            $lines[] = [
                'account_id' => $accounts[$code],
                'debit' => $amount > 0 ? $amount : 0,
                'credit' => $amount < 0 ? -$amount : 0,
                'description' => $descriptions[$code],
                'cost_center_id' => $costCenterId,
            ];
        }

        $result = app(AccountingService::class)->createJournalEntry([
            'entry_date' => $date ?? now()->toDateString(),
            'reference' => $reference,
            'description' => $description,
            'lines' => $lines,
            'auto_post' => true,
        ]);

        if (! $result['success']) {
            throw new RuntimeException("Could not post {$reference}: {$result['message']}");
        }

        return $result['data'];
    }
}
