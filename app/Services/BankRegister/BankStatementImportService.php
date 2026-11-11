<?php

namespace App\Services\BankRegister;

use App\Models\BankAccount;
use App\Models\BankEntryAuditLog;
use App\Models\BankStatementEntry;
use App\Models\BankStatementImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;

class BankStatementImportService
{
    public function __construct(
        private readonly BankStatementParser $parser,
        private readonly BankEntryClassifier $classifier,
    ) {}

    /**
     * Store the file, read it and keep a summary (counts, totals, checks) for the user to confirm.
     *
     * @param  array{opening_balance?: ?float, closing_balance?: ?float, control_total_debit?: ?float, control_total_credit?: ?float}  $meta
     */
    public function preview(BankAccount $account, UploadedFile $file, array $meta, User $user): BankStatementImport
    {
        $path = $file->store('bank-statements', config('bank.attachments.disk'));

        $import = BankStatementImport::create([
            'bank_account_id' => $account->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'opening_balance' => $meta['opening_balance'] ?? null,
            'closing_balance' => $meta['closing_balance'] ?? null,
            'control_total_debit' => $meta['control_total_debit'] ?? null,
            'control_total_credit' => $meta['control_total_credit'] ?? null,
            'status' => 'previewed',
            'imported_by' => $user->id,
        ]);

        try {
            $analysis = $this->analyse($import);
        } catch (BankRegisterException $e) {
            Storage::disk(config('bank.attachments.disk'))->delete($path);
            $import->delete();

            throw $e;
        }

        $import->update([
            'period_from' => $analysis['date_from'],
            'period_to' => $analysis['date_to'],
            'total_rows' => $analysis['valid_rows'] + $analysis['error_rows'],
            'imported_rows' => 0,
            'skipped_rows' => $analysis['duplicate_rows'],
            'error_rows' => $analysis['error_rows'],
            'preview' => Arr::except($analysis, ['entries']),
        ]);

        return $import;
    }

    /**
     * Re-read the stored file and insert every new line. Blocked when the control totals do not tally.
     */
    public function commit(BankStatementImport $import, User $user): BankStatementImport
    {
        if ($import->status !== 'previewed') {
            throw new BankRegisterException('This import has already been processed.');
        }

        $analysis = $this->analyse($import);

        if (! $analysis['checks_ok']) {
            throw new BankRegisterException('The control totals or balances do not tally. Fix the file or the figures you entered and preview again.');
        }

        $inserted = 0;
        $merged = 0;

        DB::transaction(function () use ($import, $analysis, $user, &$inserted, &$merged) {
            foreach (array_chunk($analysis['entries'], 500) as $chunk) {
                $existing = BankStatementEntry::withTrashed()
                    ->whereIn('dedupe_hash', array_column($chunk, 'dedupe_hash'))
                    ->pluck('dedupe_hash')
                    ->flip();

                $now = now();
                $records = [];

                foreach ($chunk as $row) {
                    if (isset($existing[$row['dedupe_hash']])) {
                        continue;
                    }

                    // money a settlement already booked: the statement confirms that register line, no second line
                    if ($this->confirmSettlementLine($import, $row, $user)) {
                        $merged++;

                        continue;
                    }

                    $records[] = [
                        'bank_account_id' => $import->bank_account_id,
                        'import_id' => $import->id,
                        'statement_ref_no' => $row['statement_ref_no'],
                        'posting_date' => $row['posting_date'],
                        'value_date' => $row['value_date'],
                        'reference' => $row['reference'],
                        'narration' => $row['narration'],
                        'debit' => $row['debit'],
                        'credit' => $row['credit'],
                        'running_balance' => $row['running_balance'],
                        'party_name_in_statement' => $row['party'],
                        'classification_source' => $row['classification_source'],
                        'needs_review' => $row['needs_review'],
                        'status' => BankStatementEntry::STATUS_UNCLASSIFIED,
                        'source_statement' => $row['source_statement'],
                        'pdf_page' => $row['pdf_page'],
                        'dedupe_hash' => $row['dedupe_hash'],
                        'created_by' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($records !== []) {
                    BankStatementEntry::insert($records);
                    $inserted += count($records);
                }
            }
        });

        $this->classifier->classifyPending($import->id);

        $import->update([
            'status' => 'imported',
            'imported_rows' => $inserted + $merged,
            'skipped_rows' => $analysis['valid_rows'] - $inserted - $merged,
        ]);

        return $import;
    }

    /**
     * A statement row that is a deposit already booked by a sales settlement (same bank, same amount, within three
     * days) is written onto that register line instead of becoming a second line for the same money.
     *
     * @param  array<string, mixed>  $row
     */
    private function confirmSettlementLine(BankStatementImport $import, array $row, User $user): bool
    {
        if ((float) $row['credit'] <= 0) {
            return false;
        }

        $date = Carbon::parse($row['posting_date']);

        $entry = BankStatementEntry::query()
            ->where('bank_account_id', $import->bank_account_id)
            ->whereNotNull('sales_settlement_id')
            ->whereNull('import_id')
            ->where('status', BankStatementEntry::STATUS_POSTED)
            ->where('credit', $row['credit'])
            ->whereDate('posting_date', '>=', $date->copy()->subDays(BankEntryPostingService::MATCH_DAYS))
            ->whereDate('posting_date', '<=', $date->copy()->addDays(BankEntryPostingService::MATCH_DAYS))
            ->lockForUpdate()
            ->get()
            ->sortBy(fn (BankStatementEntry $candidate) => abs($candidate->posting_date->diffInDays($date)))
            ->first();

        if (! $entry) {
            return false;
        }

        $entry->forceFill([
            'import_id' => $import->id,
            'statement_ref_no' => $row['statement_ref_no'],
            'value_date' => $row['value_date'] ?? $entry->value_date,
            'running_balance' => $row['running_balance'],
            'pdf_page' => $row['pdf_page'],
            'updated_by' => $user->id,
        ])->save();

        BankEntryAuditLog::record($entry, 'confirmed_by_statement', null, ['import_id' => $import->id, 'statement_ref_no' => $row['statement_ref_no']], $user->id);

        return true;
    }

    /**
     * @return array<string, mixed> summary plus (not stored) the hashed entries
     */
    public function analyse(BankStatementImport $import): array
    {
        // The parser needs a real file. On S3 (BANK_ATTACHMENT_DISK=s3) the statement is copied to a temporary
        // file first, and that copy is removed again as soon as it has been read.
        $disk = Storage::disk(config('bank.attachments.disk'));
        $temporary = null;

        if ($disk->getAdapter() instanceof LocalFilesystemAdapter) {
            $absolute = $disk->path($import->file_path);
        } else {
            $temporary = tempnam(sys_get_temp_dir(), 'bank-statement-').'.'.pathinfo($import->file_path, PATHINFO_EXTENSION);
            file_put_contents($temporary, $disk->get($import->file_path));
            $absolute = $temporary;
        }

        try {
            $parsed = $this->parser->parse($absolute);
        } finally {
            if ($temporary !== null) {
                @unlink($temporary);
            }
        }

        $seen = [];
        $entries = [];
        $running = $import->opening_balance !== null ? (float) $import->opening_balance : null;

        foreach ($parsed['rows'] as $row) {
            $base = implode('|', [$row['posting_date'], $row['reference'], $row['debit'], $row['credit'], $row['narration']]);
            $seen[$base] = ($seen[$base] ?? 0) + 1;

            if ($running !== null) {
                $running = round($running + $row['credit'] - $row['debit'], 2);
                $row['running_balance'] ??= $running;
            }

            $row['dedupe_hash'] = BankStatementEntry::makeDedupeHash(
                $import->bank_account_id,
                $row['posting_date'],
                $row['reference'],
                $row['debit'],
                $row['credit'],
                $row['narration'],
                $seen[$base],
            );
            $entries[] = $row;
        }

        $hashes = array_column($entries, 'dedupe_hash');
        $duplicates = 0;
        foreach (array_chunk($hashes, 1000) as $chunk) {
            $duplicates += BankStatementEntry::withTrashed()->whereIn('dedupe_hash', $chunk)->count();
        }

        $debit = round(array_sum(array_column($entries, 'debit')), 2);
        $credit = round(array_sum(array_column($entries, 'credit')), 2);
        $dates = array_column($entries, 'posting_date');

        $checks = [];
        if ($import->control_total_debit !== null) {
            $checks[] = ['label' => 'Debit control total', 'expected' => (float) $import->control_total_debit, 'actual' => $debit];
        }
        if ($import->control_total_credit !== null) {
            $checks[] = ['label' => 'Credit control total', 'expected' => (float) $import->control_total_credit, 'actual' => $credit];
        }
        if ($import->opening_balance !== null && $import->closing_balance !== null) {
            $checks[] = [
                'label' => 'Closing balance (opening + credits - debits)',
                'expected' => (float) $import->closing_balance,
                'actual' => round((float) $import->opening_balance + $credit - $debit, 2),
            ];
        }

        $checks = array_map(fn (array $check) => $check + ['ok' => abs($check['expected'] - $check['actual']) < 0.01], $checks);

        return [
            'valid_rows' => count($entries),
            'error_rows' => count($parsed['errors']),
            'duplicate_rows' => $duplicates,
            'new_rows' => count($entries) - $duplicates,
            'total_debit' => $debit,
            'total_credit' => $credit,
            'date_from' => $dates ? min($dates) : null,
            'date_to' => $dates ? max($dates) : null,
            'needs_review_rows' => count(array_filter($entries, fn (array $row) => $row['needs_review'])),
            'checks' => $checks,
            'checks_ok' => collect($checks)->every(fn (array $check) => $check['ok']),
            'errors' => array_slice($parsed['errors'], 0, 50),
            'sample' => array_slice(array_map(fn (array $row) => collect($row)->only(['row', 'posting_date', 'party', 'reference', 'debit', 'credit', 'needs_review'])->all(), $entries), 0, 15),
            'entries' => $entries,
        ];
    }
}
