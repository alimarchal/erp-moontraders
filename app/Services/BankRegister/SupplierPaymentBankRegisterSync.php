<?php

namespace App\Services\BankRegister;

use App\Models\BankAccount;
use App\Models\BankEntryAuditLog;
use App\Models\BankStatementEntry;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\LedgerRegister;
use App\Models\Supplier;
use Illuminate\Support\Carbon;

/**
 * Puts an online payment posted from the supplier Ledger Register into the Bank Register, so it can be
 * reconciled with the bank statement. The ledger entry's own journal already credits the bank, so the
 * register never posts this money again: the line is "booked by supplier ledger", linked to that journal line.
 */
class SupplierPaymentBankRegisterSync
{
    public function __construct(private readonly BankEntryClassifier $classifier) {}

    /**
     * Record the money paid out of the bank for a ledger entry that was just posted. Run inside the posting's
     * own transaction.
     */
    public function record(LedgerRegister $entry, BankAccount $bank, JournalEntry $journalEntry, ?int $userId = null): ?BankStatementEntry
    {
        $amount = round((float) $entry->online_amount, 2);

        if ($amount <= 0) {
            return null;
        }

        $existing = BankStatementEntry::query()->where('supplier_ledger_register_id', $entry->id)->first();

        if ($existing) {
            return $existing;
        }

        $entry->loadMissing('supplier');
        $date = Carbon::parse($entry->transaction_date)->toDateString();
        $reference = filled($entry->document_number) ? (string) $entry->document_number : null;
        $narration = $this->narration($entry, $bank);

        $detail = JournalEntryDetail::query()
            ->where('journal_entry_id', $journalEntry->id)
            ->where('chart_of_account_id', $bank->chart_of_account_id)
            ->where('credit', '>', 0)
            ->first();

        $line = BankStatementEntry::create([
            'bank_account_id' => $bank->id,
            'supplier_ledger_register_id' => $entry->id,
            'posting_date' => $date,
            'value_date' => $date,
            'reference' => $reference,
            'narration' => $narration,
            'debit' => $amount,
            'credit' => 0,
            'party_name_in_statement' => $entry->supplier?->supplier_name,
            'company_id' => $entry->supplier_id,
            'counterparty_type' => Supplier::class,
            'counterparty_id' => $entry->supplier_id,
            'classification_source' => 'original',
            'source_statement' => "Supplier Ledger Register #{$entry->id}",
            'status' => BankStatementEntry::STATUS_UNCLASSIFIED,
            'dedupe_hash' => $this->uniqueHash($bank->id, $date, $reference, $amount, $narration),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->classifier->classify($line);

        $line->refresh()->forceFill([
            'status' => BankStatementEntry::STATUS_POSTED,
            'journal_entry_id' => $journalEntry->id,
            'journal_entry_detail_id' => $detail?->id,
            'needs_review' => $detail === null,
            'posted_by' => $userId,
            'posted_at' => now(),
            'updated_by' => $userId,
        ])->save();

        BankEntryAuditLog::record($line, 'booked_by_supplier_ledger', null, [
            'ledger_register_id' => $entry->id,
            'supplier_id' => $entry->supplier_id,
            'debit' => $amount,
            'reference' => $reference,
            'journal_entry_id' => $journalEntry->id,
            'journal_entry_detail_id' => $detail?->id,
        ], $userId);

        return $line;
    }

    private function narration(LedgerRegister $entry, BankAccount $bank): string
    {
        $supplier = $entry->supplier;
        $supplierLabel = $supplier ? trim($supplier->supplier_name.($supplier->short_name ? " ({$supplier->short_name})" : '')) : 'supplier';
        $bankLabel = trim(($bank->bank_name ? $bank->bank_name.' - ' : '').$bank->account_name)
            .($bank->account_number ? " (A/C No. {$bank->account_number})" : '');

        $parts = [
            "Online payment to {$supplierLabel}",
            "Paid from {$bankLabel}",
            filled($entry->document_number) ? "Document No. {$entry->document_number}" : null,
            'Supplier Ledger Register entry dated '.Carbon::parse($entry->transaction_date)->format('d-M-Y'),
            filled($entry->remarks) ? 'Remarks: '.trim((string) $entry->remarks) : null,
        ];

        return implode('; ', array_filter($parts)).'.';
    }

    private function uniqueHash(int $bankAccountId, string $date, ?string $reference, float $amount, string $narration): string
    {
        $occurrence = 1;

        do {
            $hash = BankStatementEntry::makeDedupeHash($bankAccountId, $date, $reference, $amount, 0.0, $narration, $occurrence++);
        } while (BankStatementEntry::withTrashed()->where('dedupe_hash', $hash)->exists());

        return $hash;
    }
}
