<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock Received But Not Billed (2142), supplier by supplier.
 *
 * Journal lines carry no supplier, so the balance is rebuilt from the documents that
 * post to 2142: each GRN credits it with what the supplier is owed for the goods, each
 * ledger-register invoice debits it when the supplier bills them, and a clearing entry
 * moves whatever is left into Purchase Price Difference.
 */
class StockReceivedNotBilledService
{
    public const PURCHASE_PRICE_DIFFERENCE_CODE = '5274';

    public static function clearingReference(int $supplierId, string $upTo): string
    {
        return "GRNI-CLEAR-{$supplierId}-{$upTo}";
    }

    /**
     * Credit balance still on 2142 per supplier: received and not yet billed when
     * positive, billed for more than was received when negative.
     *
     * @return Collection<int, array{received: float, billed: float, cleared: float, balance: float}>
     */
    public function balancesBySupplier(?string $upTo = null): Collection
    {
        $received = DB::table('goods_receipt_notes as grn')
            ->join('journal_entries as je', 'je.id', '=', 'grn.journal_entry_id')
            ->join('journal_entry_details as line', 'line.journal_entry_id', '=', 'je.id')
            ->join('chart_of_accounts as account', 'account.id', '=', 'line.chart_of_account_id')
            ->where('grn.status', 'posted')
            ->where('grn.is_opening_stock', false)
            ->where('je.status', 'posted')
            ->whereIn('account.account_code', ['2111', InventoryService::STOCK_RECEIVED_NOT_BILLED_CODE])
            ->when($upTo, fn ($query) => $query->where('grn.receipt_date', '<=', $upTo))
            ->groupBy('grn.supplier_id')
            ->selectRaw('grn.supplier_id, SUM(line.credit - line.debit) as amount')
            ->pluck('amount', 'supplier_id');

        $billed = DB::table('supplier_ledger_registers')
            ->whereNotNull('journal_entry_id')
            ->where('invoice_amount', '>', 0)
            ->where(fn ($query) => $query->whereNull('document_type')->orWhere('document_type', '!=', 'OB'))
            ->when($upTo, fn ($query) => $query->where('transaction_date', '<=', $upTo))
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(invoice_amount) as amount')
            ->pluck('amount', 'supplier_id');

        $cleared = DB::table('journal_entries as je')
            ->join('journal_entry_details as line', 'line.journal_entry_id', '=', 'je.id')
            ->join('chart_of_accounts as account', 'account.id', '=', 'line.chart_of_account_id')
            ->where('je.status', 'posted')
            ->where('je.reference', 'like', 'GRNI-CLEAR-%')
            ->where('account.account_code', InventoryService::STOCK_RECEIVED_NOT_BILLED_CODE)
            ->when($upTo, fn ($query) => $query->where('je.entry_date', '<=', $upTo))
            ->selectRaw('je.reference, SUM(line.debit - line.credit) as amount')
            ->groupBy('je.reference')
            ->get()
            ->groupBy(fn ($row) => (int) explode('-', $row->reference)[2])
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        return $received->keys()->merge($billed->keys())->merge($cleared->keys())->unique()->sort()
            ->mapWithKeys(function ($supplierId) use ($received, $billed, $cleared) {
                $receivedAmount = round((float) ($received[$supplierId] ?? 0), 2);
                $billedAmount = round((float) ($billed[$supplierId] ?? 0), 2);
                $clearedAmount = round((float) ($cleared[$supplierId] ?? 0), 2);

                return [(int) $supplierId => [
                    'received' => $receivedAmount,
                    'billed' => $billedAmount,
                    'cleared' => $clearedAmount,
                    'balance' => round($receivedAmount - $billedAmount - $clearedAmount, 2),
                ]];
            });
    }
}
