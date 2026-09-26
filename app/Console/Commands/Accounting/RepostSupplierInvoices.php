<?php

namespace App\Console\Commands\Accounting;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\GoodsReceiptNote;
use App\Services\AccountingService;
use App\Services\InventoryService;
use App\Services\StockReceivedNotBilledService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RepostSupplierInvoices extends Command
{
    protected $signature = 'accounting:repost-supplier-invoices
        {--dry-run : Post everything inside a transaction, report the result, then roll it back}';

    protected $description = 'Take the double-counted purchases out of Stock In Hand and Creditors. Every purchase '
        .'reached the ledger twice: once from its GRN (Dr 1151 / Cr 2111) and again from the supplier invoice in the '
        .'ledger register (Dr 1151 / Cr 2111). This moves both onto Stock Received But Not Billed (2142), posts the '
        .'GRNs that never got a journal entry, and cancels the auto-drafted supplier payments.';

    /** @var array<string, int> */
    private array $accounts = [];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> */
    private array $skippedGrns = [];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> */
    private array $roundOffGrns = [];

    public function handle(StockReceivedNotBilledService $receivedNotBilled): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        foreach (['1151', '2111', InventoryService::STOCK_RECEIVED_NOT_BILLED_CODE] as $code) {
            $id = ChartOfAccount::where('account_code', $code)->value('id');

            if (! $id) {
                $this->error("Account {$code} is missing from the Chart of Accounts.");

                return self::FAILURE;
            }

            $this->accounts[$code] = (int) $id;
        }

        $before = $this->glBalances();

        DB::beginTransaction();

        try {
            $grnReclassed = $this->reclassifyGrnCredits();
            $invoicesReclassed = $this->reclassifyLedgerRegisterInvoices();
            $grnsPosted = $this->postMissingGrnEntries();
            $paymentsCancelled = $this->cancelDraftSupplierPayments();

            $after = $this->glBalances();
            $this->report($before, $after, $grnReclassed, $invoicesReclassed, $grnsPosted, $paymentsCancelled, $receivedNotBilled);

            $isDryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->warn('DRY RUN — everything above was rolled back. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        Log::info('Reposted supplier invoices onto Stock Received But Not Billed', [
            'grn_entries_reclassified' => $grnReclassed,
            'invoices_reclassified' => $invoicesReclassed,
            'missing_grn_entries_posted' => $grnsPosted,
            'draft_payments_cancelled' => $paymentsCancelled,
        ]);
        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * A GRN posted before 2142 existed credited Creditors with what the supplier is owed.
     * Move that credit to 2142, where the supplier's invoice will clear it.
     */
    private function reclassifyGrnCredits(): int
    {
        $count = 0;

        $grns = GoodsReceiptNote::where('status', 'posted')
            ->where('is_opening_stock', false)
            ->whereNotNull('journal_entry_id')
            ->orderBy('id')
            ->get();

        foreach ($grns as $grn) {
            $reference = InventoryService::grnReclassReference($grn);

            if ($this->alreadyPosted($reference)) {
                continue;
            }

            $entry = DB::table('journal_entries')->where('id', $grn->journal_entry_id)->where('status', 'posted')->first(['entry_date']);
            $creditorsCredit = $this->netCredit((int) $grn->journal_entry_id, $this->accounts['2111']);

            if (! $entry || $creditorsCredit <= 0) {
                continue;
            }

            $this->post($entry->entry_date, $reference, "Reclassify {$grn->grn_number}: goods received, billed through the ledger register", [
                [$this->accounts['2111'], $creditorsCredit, 0, 'Not payable until the supplier invoices it'],
                [$this->accounts[InventoryService::STOCK_RECEIVED_NOT_BILLED_CODE], 0, $creditorsCredit, "Received on {$grn->grn_number}, awaiting invoice"],
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * A posted ledger-register invoice debited Stock In Hand for goods its GRN had already
     * put there. Move that debit to 2142, so the invoice clears the GRN instead.
     */
    private function reclassifyLedgerRegisterInvoices(): int
    {
        $count = 0;

        $invoices = DB::table('supplier_ledger_registers')
            ->whereNotNull('journal_entry_id')
            ->where('invoice_amount', '>', 0)
            ->where(fn ($query) => $query->whereNull('document_type')->orWhere('document_type', '!=', 'OB'))
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            $reference = "LR-RECLASS-{$invoice->id}";

            if ($this->alreadyPosted($reference)) {
                continue;
            }

            $stockDebit = -$this->netCredit((int) $invoice->journal_entry_id, $this->accounts['1151']);

            if ($stockDebit <= 0) {
                continue;
            }

            $documentNumber = $invoice->document_number ?? "LR-{$invoice->id}";
            $this->post($invoice->transaction_date, $reference, "Reclassify ledger register invoice {$documentNumber}: stock was already received on its GRN", [
                [$this->accounts[InventoryService::STOCK_RECEIVED_NOT_BILLED_CODE], $stockDebit, 0, "Invoice {$documentNumber} bills goods already received"],
                [$this->accounts['1151'], 0, $stockDebit, 'Stock was put in hand by the GRN, not by the invoice'],
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * GRNs that were posted to stock without a journal entry of their own.
     */
    private function postMissingGrnEntries(): int
    {
        $service = app(InventoryService::class);
        $createEntry = (fn (GoodsReceiptNote $grn) => $this->createGrnJournalEntry($grn))->bindTo($service, InventoryService::class);
        $count = 0;

        $grns = GoodsReceiptNote::where('status', 'posted')
            ->where('is_opening_stock', false)
            ->whereNull('journal_entry_id')
            ->orderBy('id')
            ->get();

        foreach ($grns as $grn) {
            // Stock In Hand must be debited with what the stock ledger received. A line whose
            // total disagrees with quantity × unit cost is still right to post when the GRN's
            // stock movement carries that same total (the batch was valued and issued at it);
            // only a line that disagrees with its own movement is left until it is fixed.
            $mismatched = $service->itemsWithMismatchedTotals($grn);
            $unbacked = $mismatched->reject(fn ($item) => $this->movementCarriesLineTotal($grn, $item));

            if ($unbacked->isNotEmpty()) {
                foreach ($unbacked as $item) {
                    $this->skippedGrns[] = [
                        $grn->grn_number,
                        $item->product?->product_name ?? $item->product_id,
                        number_format((float) $item->total_cost, 2),
                        number_format((float) $item->quantity_accepted * (float) $item->unit_cost, 2),
                    ];
                }

                continue;
            }

            foreach ($mismatched as $item) {
                $this->roundOffGrns[] = [
                    $grn->grn_number,
                    $item->product?->product_name ?? $item->product_id,
                    number_format((float) $item->total_cost, 2),
                    number_format((float) $item->quantity_accepted * (float) $item->unit_cost, 2),
                ];
            }

            $entry = $createEntry($grn);

            if ($entry) {
                $grn->update(['journal_entry_id' => $entry->id]);
                $count++;
            }
        }

        return $count;
    }

    private function movementCarriesLineTotal(GoodsReceiptNote $grn, object $item): bool
    {
        $movementValue = (float) DB::table('stock_movements')
            ->where('reference_type', GoodsReceiptNote::class)
            ->where('reference_id', $grn->id)
            ->where('movement_type', 'grn')
            ->where('product_id', $item->product_id)
            ->sum('total_value');

        return abs($movementValue - (float) $item->total_cost) < 1;
    }

    /**
     * Every GRN used to raise a draft payment. Suppliers are paid through the ledger
     * register, so posting any of these would take the same money out of Creditors twice.
     */
    private function cancelDraftSupplierPayments(): int
    {
        return DB::table('supplier_payments')
            ->where('status', 'draft')
            ->whereNull('deleted_at')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    /**
     * @param  list<array{0: int, 1: float, 2: float, 3: string}>  $lines
     */
    private function post(string $date, string $reference, string $description, array $lines): void
    {
        $result = app(AccountingService::class)->createJournalEntry([
            'entry_date' => substr($date, 0, 10),
            'reference' => $reference,
            'description' => $description,
            'lines' => array_map(fn (array $line) => [
                'account_id' => $line[0],
                'debit' => round($line[1], 2),
                'credit' => round($line[2], 2),
                'description' => $line[3],
                'cost_center_id' => CostCenter::where('code', 'CC006')->value('id'),
            ], $lines),
            'auto_post' => true,
        ]);

        if (! $result['success']) {
            throw new RuntimeException("{$reference}: {$result['message']}");
        }
    }

    private function alreadyPosted(string $reference): bool
    {
        return DB::table('journal_entries')->where('reference', $reference)->where('status', 'posted')->exists();
    }

    private function netCredit(int $journalEntryId, int $accountId): float
    {
        return round((float) DB::table('journal_entry_details')
            ->where('journal_entry_id', $journalEntryId)
            ->where('chart_of_account_id', $accountId)
            ->selectRaw('COALESCE(SUM(credit - debit), 0) as amount')
            ->value('amount'), 2);
    }

    /**
     * @return array<string, float>
     */
    private function glBalances(): array
    {
        $balances = [];

        foreach ($this->accounts as $code => $id) {
            $balances[$code] = round((float) DB::table('journal_entry_details as line')
                ->join('journal_entries as je', 'je.id', '=', 'line.journal_entry_id')
                ->where('je.status', 'posted')
                ->where('line.chart_of_account_id', $id)
                ->selectRaw('COALESCE(SUM(line.debit - line.credit), 0) as balance')
                ->value('balance'), 2);
        }

        return $balances;
    }

    /**
     * @param  array<string, float>  $before
     * @param  array<string, float>  $after
     */
    private function report(array $before, array $after, int $grnReclassed, int $invoicesReclassed, int $grnsPosted, int $paymentsCancelled, StockReceivedNotBilledService $receivedNotBilled): void
    {
        $this->table(['Step', 'Count'], [
            ['GRN entries moved from Creditors to 2142', $grnReclassed],
            ['Ledger register invoices moved from Stock In Hand to 2142', $invoicesReclassed],
            ['GRNs given their missing journal entry', $grnsPosted],
            ['Draft supplier payments cancelled', $paymentsCancelled],
        ]);

        if ($this->roundOffGrns !== []) {
            $this->warn('Posted at the value the stock was received and issued at; the gap to the invoice went to Round Off (5271):');
            $this->table(['GRN', 'Product', 'Line total (stock value)', 'Qty × unit cost'], $this->roundOffGrns);
        }

        if ($this->skippedGrns !== []) {
            $this->warn('Left without a journal entry — a line total is not quantity × unit cost. Correct the line and run this again:');
            $this->table(['GRN', 'Product', 'Line total', 'Qty × unit cost'], $this->skippedGrns);
        }

        $warehouseStock = round((float) DB::table('current_stock_by_batch')->selectRaw('COALESCE(SUM(quantity_on_hand * unit_cost), 0) as value')->value('value'), 2);
        $names = ['1151' => 'Stock In Hand', '2111' => 'Creditors', InventoryService::STOCK_RECEIVED_NOT_BILLED_CODE => 'Stock Received But Not Billed'];

        $this->table(['Account', 'Before', 'After', 'Change'], collect($this->accounts)->keys()->map(fn ($code) => [
            "{$code} {$names[$code]}",
            number_format($before[$code], 2),
            number_format($after[$code], 2),
            number_format($after[$code] - $before[$code], 2),
        ])->all());
        $this->line(sprintf('Stock In Hand %s against warehouse stock %s (difference %s).',
            number_format($after['1151'], 2), number_format($warehouseStock, 2), number_format($after['1151'] - $warehouseStock, 2)));

        $suppliers = DB::table('suppliers')->pluck('short_name', 'id');
        $register = DB::table('supplier_ledger_registers')
            ->whereNotNull('journal_entry_id')
            ->whereNull('deleted_at')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(opening_balance + online_amount - invoice_amount - expenses_amount + za_point_five_percent_amount + claim_adjust_amount) as balance')
            ->pluck('balance', 'supplier_id');
        $unposted = DB::table('supplier_ledger_registers')
            ->whereNull('journal_entry_id')
            ->whereNull('deleted_at')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, COUNT(*) as entries')
            ->pluck('entries', 'supplier_id');

        $balances = $receivedNotBilled->balancesBySupplier();

        $this->newLine();
        $this->line('Ledger register balance (minus = we owe the supplier, plus = supplier holds our money) and 2142 by supplier:');
        $this->table(
            ['Supplier', 'Ledger register (posted)', 'Unposted entries', '2142 received not billed'],
            $register->keys()->merge($balances->keys())->unique()->sort()->map(fn ($supplierId) => [
                $suppliers[$supplierId] ?? $supplierId,
                number_format((float) ($register[$supplierId] ?? 0), 2),
                (int) ($unposted[$supplierId] ?? 0),
                number_format((float) ($balances[$supplierId]['balance'] ?? 0), 2),
            ])->values()->all()
        );
        $this->line(sprintf('Creditors (2111) %s against the posted ledger register total %s (difference %s).',
            number_format($after['2111'], 2), number_format((float) $register->sum(), 2), number_format($after['2111'] - (float) $register->sum(), 2)));
        $this->line('A 2142 balance is either goods still waiting for their invoice, or the difference between the invoice and '
            .'the GRN. Once every invoice up to a date is entered, run accounting:clear-stock-received-not-billed to post it.');
    }
}
