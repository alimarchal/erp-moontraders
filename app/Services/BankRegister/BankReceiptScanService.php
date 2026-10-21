<?php

namespace App\Services\BankRegister;

use App\Models\Attachment;
use App\Models\BankEntryAuditLog;
use App\Models\BankReceiptScan;
use App\Models\BankStatementEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BankReceiptScanService
{
    /**
     * Keep each scanned file as its own inbox card.
     *
     * @param  array<int, UploadedFile>  $files
     * @param  array{bank_account_id?: ?int, scan_date: string, amount?: ?float, direction?: ?string, note?: ?string}  $meta
     * @return Collection<int, BankReceiptScan>
     */
    public function store(array $files, array $meta, User $user): Collection
    {
        $disk = config('bank.attachments.disk');

        return collect($files)->map(fn (UploadedFile $file) => BankReceiptScan::create([
            'bank_account_id' => $meta['bank_account_id'] ?? null,
            'scan_date' => $meta['scan_date'],
            // one amount only makes sense for one file
            'amount' => count($files) === 1 ? ($meta['amount'] ?? null) : null,
            'direction' => count($files) === 1 ? ($meta['direction'] ?? null) : null,
            'note' => $meta['note'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->store('bank-receipt-scans', $disk),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'status' => BankReceiptScan::STATUS_PENDING,
            'uploaded_by' => $user->id,
        ]));
    }

    /**
     * Turn a scan into a normal attachment of a statement line (the file is moved, not copied).
     */
    public function link(BankReceiptScan $scan, BankStatementEntry $entry, User $user): BankReceiptScan
    {
        return DB::transaction(function () use ($scan, $entry, $user) {
            $scan = BankReceiptScan::query()->lockForUpdate()->findOrFail($scan->id);

            if ($scan->status === BankReceiptScan::STATUS_LINKED) {
                throw new BankRegisterException('This scan is already linked to an entry.');
            }

            if ($entry->status === BankStatementEntry::STATUS_RECONCILED) {
                throw new BankRegisterException('That entry is reconciled, so its attachments are locked.');
            }

            $disk = Storage::disk(config('bank.attachments.disk'));
            $target = "bank-attachments/{$entry->id}/".basename($scan->file_path);

            if ($disk->exists($scan->file_path)) {
                $disk->move($scan->file_path, $target);
            }

            Attachment::create([
                'attachable_type' => BankStatementEntry::class,
                'attachable_id' => $entry->id,
                'file_name' => $scan->file_name,
                'file_path' => $target,
                'file_type' => $scan->file_type,
                'file_size' => $scan->file_size,
                'description' => $scan->note,
                'label' => 'receipt',
                'uploaded_by' => $scan->uploaded_by,
            ]);

            $scan->forceFill([
                'status' => BankReceiptScan::STATUS_LINKED,
                'file_path' => $target,
                'bank_statement_entry_id' => $entry->id,
                'linked_by' => $user->id,
                'linked_at' => now(),
            ])->save();

            BankEntryAuditLog::record($entry, 'receipt_linked', null, ['scan_id' => $scan->id, 'file' => $scan->file_name], $user->id);

            return $scan;
        });
    }

    /**
     * Likely bank lines for a scan: same amount (and account when known), within five days, receipts missing first.
     *
     * @return Collection<int, BankStatementEntry>
     */
    public function suggestions(BankReceiptScan $scan): Collection
    {
        if (! $scan->amount) {
            return collect();
        }

        return BankStatementEntry::query()
            ->withCount('attachments')
            ->where('status', '!=', BankStatementEntry::STATUS_RECONCILED)
            ->whereRaw('(debit + credit) = ?', [$scan->amount])
            ->when($scan->bank_account_id, fn ($q, $id) => $q->where('bank_account_id', $id))
            ->when($scan->direction, fn ($q, $direction) => $q->where($direction, '>', 0))
            ->whereDate('posting_date', '>=', $scan->scan_date->copy()->subDays(5))
            ->whereDate('posting_date', '<=', $scan->scan_date->copy()->addDays(5))
            ->orderBy('attachments_count')
            ->orderByDesc('posting_date')
            ->limit(3)
            ->get();
    }
}
