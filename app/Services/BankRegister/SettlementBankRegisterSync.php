<?php

namespace App\Services\BankRegister;

use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\BankEntryAuditLog;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\BankStatementEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\JournalEntryDetail;
use App\Models\SalesSettlement;
use App\Services\SettlementAttachmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the Bank Register in step with the bank money of a sales settlement, so it can be reconciled with the bank
 * statement. The settlement's own journal already debits the bank (one line per bank account and kind of money), so
 * the register never posts this money again: when the settlement is posted, every online recovery, customer bank
 * transfer and salesman bank slip gets one register line "booked by settlement", linked to that journal line.
 *
 * When the settlement is reverted its journal is reversed by the settlement itself and its register lines are marked
 * reversed with it (a line already in a completed reconciliation is flagged for review instead). Posting the
 * settlement again links the same lines to the new journal. Cheques are not bank money until they are deposited:
 * they sit in Cheques in Hand and are cleared in the cheque register.
 */
class SettlementBankRegisterSync
{
    public const SECTION_RECOVERIES = 'recoveries';

    public const SECTION_BANK_TRANSFERS = 'bank_transfers';

    public const SECTION_BANK_SLIPS = 'bank_slips';

    /** How DistributionService::postSalesSettlement() starts the bank line of each kind of money. */
    private const JOURNAL_PREFIXES = [
        self::SECTION_RECOVERIES => 'Bank Recovery from Debtor',
        self::SECTION_BANK_TRANSFERS => 'Bank Transfer Sales',
        self::SECTION_BANK_SLIPS => 'Bank Deposit (Slip)',
    ];

    public function __construct(private readonly BankEntryClassifier $classifier) {}

    /**
     * Bring the register lines of a settlement in line with it. Run inside the settlement's own transaction, after it
     * was saved, posted or reverted.
     *
     * @return array{linked: int, kept: int, removed: int, reversed: int, flagged: int}
     */
    public function sync(SalesSettlement $settlement, ?int $userId = null): array
    {
        $existing = BankStatementEntry::query()->where('sales_settlement_id', $settlement->id)->lockForUpdate()->get();
        $result = ['linked' => 0, 'kept' => 0, 'removed' => 0, 'reversed' => 0, 'flagged' => 0];

        // Only a posted settlement has bank money in the books. A draft (new, or reverted) has no register lines.
        if ($settlement->status !== 'posted' || ! $settlement->journal_entry_id) {
            foreach ($existing as $entry) {
                $outcome = $this->takeBack($entry, $userId, "Sales Settlement {$settlement->settlement_number} is not posted (draft or reverted)");
                $result[$outcome]++;
            }

            return $result;
        }

        $details = JournalEntryDetail::query()->where('journal_entry_id', $settlement->journal_entry_id)->where('debit', '>', 0)->get();

        foreach ($this->lines($settlement) as $line) {
            $matchIndex = $existing->search(fn (BankStatementEntry $entry) => $this->matchKey($entry->settlement_section, $entry->bank_account_id, $entry->posting_date->toDateString(), (float) $entry->credit, $entry->counterparty_type, $entry->counterparty_id) === $line['key']);
            $detail = $this->journalLine($details, $line);

            if ($matchIndex !== false) {
                $this->refresh($existing->pull($matchIndex), $line, $settlement, $detail, $userId);
                $result['kept']++;

                continue;
            }

            $this->create($settlement, $line, $detail, $userId);
            $result['linked']++;
        }

        foreach ($existing as $orphan) {
            $outcome = $this->takeBack($orphan, $userId, "its line was changed or removed on Sales Settlement {$settlement->settlement_number}");
            $result[$outcome]++;
        }

        return $result;
    }

    /**
     * Withdraw every register line of a settlement that is being deleted (only drafts can be, and they have none).
     */
    public function withdrawAll(SalesSettlement $settlement, ?int $userId = null): void
    {
        BankStatementEntry::query()->where('sales_settlement_id', $settlement->id)->get()
            ->each(fn (BankStatementEntry $entry) => $this->takeBack($entry, $userId, "Sales Settlement {$settlement->settlement_number} was deleted"));
    }

    /**
     * The settlement journal line that debits this bank with this kind of money. The settlement books one line per
     * bank account and kind (recoveries, transfers, slips), so several register lines can share one journal line.
     *
     * @param  Collection<int, JournalEntryDetail>  $details
     * @param  array<string, mixed>  $line
     */
    private function journalLine(Collection $details, array $line): ?JournalEntryDetail
    {
        $onBank = $details->where('chart_of_account_id', $line['ledger_id']);
        $prefix = self::JOURNAL_PREFIXES[$line['section']];

        return $onBank->first(fn (JournalEntryDetail $detail) => str_starts_with((string) $detail->description, $prefix)) ?? $onBank->first();
    }

    /**
     * Point each register line at the proof attached to its settlement line. The file is not copied: the register
     * opens the settlement's own file. Run after the settlement is committed and its uploads are in their final folder.
     */
    public function linkAttachments(SalesSettlement $settlement, ?int $userId = null): void
    {
        $entries = BankStatementEntry::query()->where('sales_settlement_id', $settlement->id)->with('attachments')->get();

        if ($entries->isEmpty()) {
            return;
        }

        $rows = [
            self::SECTION_RECOVERIES => $settlement->recoveries()->get(['id', 'attachment'])->keyBy('id'),
            self::SECTION_BANK_TRANSFERS => $settlement->bankTransfers()->get(['id', 'attachment'])->keyBy('id'),
            self::SECTION_BANK_SLIPS => $settlement->bankSlips()->get(['id', 'attachment'])->keyBy('id'),
        ];

        foreach ($entries as $entry) {
            $path = $rows[$entry->settlement_section][$entry->settlement_row_id]->attachment ?? null;
            $linked = $entry->attachments->filter(fn (Attachment $attachment) => $attachment->isSettlementFile());

            $linked->reject(fn (Attachment $attachment) => $attachment->file_path === $path)->each->delete();

            if (! $path || $linked->contains('file_path', $path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $label = SettlementAttachmentService::SECTION_LABELS[$entry->settlement_section];

            $entry->attachments()->create([
                'file_name' => str_replace(' ', '-', "{$settlement->settlement_number} {$entry->settlement_section}".($entry->reference ? " {$entry->reference}" : '')).'.'.$extension,
                'file_path' => $path,
                'file_type' => $extension === 'pdf' ? 'application/pdf' : 'image/'.($extension === 'jpg' ? 'jpeg' : $extension),
                'file_size' => $this->fileSize($path),
                'label' => $entry->settlement_section === self::SECTION_BANK_SLIPS ? 'slip' : 'receipt',
                'description' => "Linked from Sales Settlement {$settlement->settlement_number} ({$label})",
                'uploaded_by' => $userId,
            ]);

            BankEntryAuditLog::record($entry, 'settlement_file_linked', null, ['settlement' => $settlement->settlement_number], $userId);
        }
    }

    /**
     * The bank money on a settlement, one array per register line.
     *
     * @return Collection<int, array{key: string, section: string, row_id: int, bank_account_id: int, date: string, amount: float, reference: ?string, party: ?string, counterparty_type: ?string, counterparty_id: ?int, narration: string}>
     */
    private function lines(SalesSettlement $settlement): Collection
    {
        $settlement->loadMissing(['employee', 'goodsIssue']);
        $settlementDate = Carbon::parse($settlement->settlement_date)->toDateString();
        $salesman = $settlement->employee;
        $lines = collect();

        foreach ($settlement->recoveries()->with(['customer', 'bankAccount'])->whereNotIn('payment_method', ['cash', 'cheque'])->orderBy('id')->get() as $recovery) {
            $lines->push($this->line(
                $settlement, self::SECTION_RECOVERIES, $recovery->id, $recovery->bankAccount, $settlementDate, (float) $recovery->amount,
                $recovery->recovery_number, $recovery->customer,
                'Online recovery of credit balance received from '.$this->customerLabel($recovery->customer),
                ["Recovery No. {$recovery->recovery_number}"], $recovery->notes,
            ));
        }

        foreach ($settlement->bankTransfers()->with(['customer', 'bankAccount'])->orderBy('id')->get() as $transfer) {
            $lines->push($this->line(
                $settlement, self::SECTION_BANK_TRANSFERS, $transfer->id, $transfer->bankAccount,
                $transfer->transfer_date?->toDateString() ?? $settlementDate, (float) $transfer->amount,
                $transfer->reference_number, $transfer->customer,
                'Online bank transfer received from '.$this->customerLabel($transfer->customer).' against sales',
                array_filter([$transfer->reference_number ? "Transaction Ref. {$transfer->reference_number}" : null]), $transfer->notes,
            ));
        }

        foreach ($settlement->bankSlips()->with('bankAccount')->orderBy('id')->get() as $slip) {
            $lines->push($this->line(
                $settlement, self::SECTION_BANK_SLIPS, $slip->id, $slip->bankAccount,
                $slip->deposit_date ? Carbon::parse($slip->deposit_date)->toDateString() : $settlementDate, (float) $slip->amount,
                $slip->reference_number, $salesman,
                'Cash deposited into bank by salesman '.$this->employeeLabel($salesman).' from sales collection',
                array_filter([$slip->reference_number ? "Deposit Slip No. {$slip->reference_number}" : null]), $slip->notes,
            ));
        }

        return $lines->filter()->values();
    }

    /**
     * @param  array<int, string>  $references
     * @return array<string, mixed>|null null when the line has no bank account or no amount
     */
    private function line(SalesSettlement $settlement, string $section, int $rowId, ?BankAccount $bank, string $date, float $amount, ?string $reference, Customer|Employee|null $party, string $opening, array $references, ?string $notes): ?array
    {
        if (! $bank || $amount <= 0) {
            return null;
        }

        $parts = array_merge(
            [$opening, 'Credited to '.$this->bankLabel($bank)],
            $references,
            [
                "Sales Settlement {$settlement->settlement_number} dated ".Carbon::parse($settlement->settlement_date)->format('d-M-Y'),
                $settlement->goodsIssue?->issue_number ? "Goods Issue {$settlement->goodsIssue->issue_number}" : null,
                $section === self::SECTION_BANK_SLIPS ? null : 'Salesman '.$this->employeeLabel($settlement->employee),
                filled($notes) ? 'Remarks: '.trim((string) $notes) : null,
            ],
        );

        return [
            'key' => $this->matchKey($section, $bank->id, $date, $amount, $party ? $party::class : null, $party?->id),
            'section' => $section,
            'row_id' => $rowId,
            'bank_account_id' => $bank->id,
            'ledger_id' => $bank->chart_of_account_id,
            'date' => $date,
            'amount' => round($amount, 2),
            'reference' => filled($reference) ? (string) $reference : null,
            'party' => $party instanceof Customer ? $party->customer_name : $party?->name,
            'counterparty_type' => $party ? $party::class : null,
            'counterparty_id' => $party?->id,
            'narration' => implode('; ', array_filter($parts)).'.',
        ];
    }

    private function matchKey(?string $section, int $bankAccountId, string $date, float $amount, ?string $partyType, ?int $partyId): string
    {
        return implode('|', [$section, $bankAccountId, $date, number_format($amount, 2, '.', ''), $partyType, $partyId]);
    }

    private function bankLabel(BankAccount $bank): string
    {
        $label = trim(($bank->bank_name ? $bank->bank_name.' - ' : '').$bank->account_name);

        return $label.($bank->account_number ? " (A/C No. {$bank->account_number})" : '').($bank->branch ? ", {$bank->branch} branch" : '');
    }

    private function customerLabel(?Customer $customer): string
    {
        return $customer ? trim($customer->customer_name.($customer->customer_code ? " ({$customer->customer_code})" : '')) : 'customer';
    }

    private function employeeLabel(?Employee $employee): string
    {
        return $employee ? trim($employee->name.($employee->employee_code ? " ({$employee->employee_code})" : '')) : 'not recorded';
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function create(SalesSettlement $settlement, array $line, ?JournalEntryDetail $detail, ?int $userId): void
    {
        $entry = BankStatementEntry::create([
            'bank_account_id' => $line['bank_account_id'],
            'sales_settlement_id' => $settlement->id,
            'settlement_section' => $line['section'],
            'settlement_row_id' => $line['row_id'],
            'posting_date' => $line['date'],
            'value_date' => $line['date'],
            'reference' => $line['reference'],
            'narration' => $line['narration'],
            'debit' => 0,
            'credit' => $line['amount'],
            'party_name_in_statement' => $line['party'],
            'company_id' => $settlement->supplier_id,
            'counterparty_type' => $line['counterparty_type'],
            'counterparty_id' => $line['counterparty_id'],
            'classification_source' => 'original',
            'source_statement' => "Sales Settlement {$settlement->settlement_number}",
            'status' => BankStatementEntry::STATUS_UNCLASSIFIED,
            'dedupe_hash' => $this->uniqueHash($line),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->classifier->classify($entry);
        $this->book($entry->refresh(), $settlement, $detail, $userId);

        BankEntryAuditLog::record($entry, 'created_from_settlement', null, [
            'settlement' => $settlement->settlement_number,
            'section' => $line['section'],
            'credit' => $line['amount'],
            'reference' => $line['reference'],
            'journal_entry_id' => $settlement->journal_entry_id,
        ], $userId);
    }

    /**
     * A line that is still on the settlement keeps its register entry: its wording follows the settlement and it is
     * linked to the settlement's current journal (a new one after the settlement was reverted and posted again).
     *
     * @param  array<string, mixed>  $line
     */
    private function refresh(BankStatementEntry $entry, array $line, SalesSettlement $settlement, ?JournalEntryDetail $detail, ?int $userId): void
    {
        $entry->settlement_row_id = $line['row_id'];

        if ($entry->status !== BankStatementEntry::STATUS_RECONCILED
            && ($entry->reference !== $line['reference'] || $entry->narration !== $line['narration'] || $entry->party_name_in_statement !== $line['party'])) {
            $entry->fill([
                'reference' => $line['reference'],
                'narration' => $line['narration'],
                'party_name_in_statement' => $line['party'],
                'dedupe_hash' => $this->uniqueHash($line, $entry->id),
                'updated_by' => $userId,
            ]);
        }

        $entry->save();

        if ($entry->status !== BankStatementEntry::STATUS_RECONCILED && (int) $entry->journal_entry_id !== (int) $settlement->journal_entry_id) {
            $this->book($entry, $settlement, $detail, $userId);
        }
    }

    /**
     * Mark a line as booked by the settlement's journal. No journal is created here.
     */
    private function book(BankStatementEntry $entry, SalesSettlement $settlement, ?JournalEntryDetail $detail, ?int $userId): void
    {
        $entry->forceFill([
            'status' => BankStatementEntry::STATUS_POSTED,
            'journal_entry_id' => $settlement->journal_entry_id,
            'journal_entry_detail_id' => $detail?->id,
            'needs_review' => $detail === null,
            'posted_by' => $userId,
            'posted_at' => now(),
            'updated_by' => $userId,
        ])->save();

        BankEntryAuditLog::record($entry, 'booked_by_settlement', null, ['settlement' => $settlement->settlement_number, 'journal_entry_detail_id' => $detail?->id], $userId);
    }

    /**
     * Take a line back because its settlement was reverted, deleted or changed. A line that only waited is removed;
     * a line that was booked is marked reversed (the settlement reversed its journal); a line already in a completed
     * reconciliation stays and is flagged, because the bank statement may really show that money.
     *
     * @return 'removed'|'reversed'|'flagged'
     */
    private function takeBack(BankStatementEntry $entry, ?int $userId, string $reason): string
    {
        if ($entry->status === BankStatementEntry::STATUS_RECONCILED) {
            $entry->forceFill([
                'needs_review' => true,
                'description' => trim(($entry->description ? $entry->description.' ' : '')."Review: {$reason} after this line was reconciled."),
                'updated_by' => $userId,
            ])->save();
            BankEntryAuditLog::record($entry, 'settlement_line_changed', null, ['reason' => $reason], $userId);

            return 'flagged';
        }

        // an open reconciliation lets go of the line
        BankReconciliationItem::query()->where('bank_statement_entry_id', $entry->id)
            ->whereHas('reconciliation', fn ($query) => $query->where('status', BankReconciliation::STATUS_IN_PROGRESS))
            ->delete();

        if ($entry->status === BankStatementEntry::STATUS_POSTED || $entry->import_id) {
            $old = ['status' => $entry->status, 'journal_entry_id' => $entry->journal_entry_id];
            $entry->forceFill([
                'status' => BankStatementEntry::STATUS_REVERSED,
                'journal_entry_id' => null,
                'journal_entry_detail_id' => null,
                'needs_review' => (bool) $entry->import_id,
                'updated_by' => $userId,
            ])->save();
            BankEntryAuditLog::record($entry, 'reversed_with_settlement', $old, ['reason' => $reason], $userId);

            return 'reversed';
        }

        $entry->attachments->filter(fn (Attachment $attachment) => $attachment->isSettlementFile())->each->delete();
        BankEntryAuditLog::record($entry, 'settlement_line_withdrawn', null, ['reason' => $reason], $userId);
        $entry->delete();

        return 'removed';
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function uniqueHash(array $line, ?int $ignoreId = null): string
    {
        $occurrence = 1;

        do {
            $hash = BankStatementEntry::makeDedupeHash($line['bank_account_id'], $line['date'], $line['reference'], 0.0, $line['amount'], $line['narration'], $occurrence++);
            $taken = BankStatementEntry::withTrashed()->where('dedupe_hash', $hash)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists();
        } while ($taken);

        return $hash;
    }

    private function fileSize(string $path): ?int
    {
        try {
            return Storage::disk(SettlementAttachmentService::disk())->size($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
