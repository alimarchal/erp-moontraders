<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountingService
{
    /**
     * Create a journal entry with multiple lines.
     *
     * @return array{success: bool, data: ?JournalEntry, message: string}
     */
    public function createJournalEntry(array $data): array
    {
        return $this->persistJournalEntry(new JournalEntry, $data, true);
    }

    /**
     * Update an existing journal entry while enforcing double-entry rules.
     *
     * @return array{success: bool, data: ?JournalEntry, message: string}
     */
    public function updateJournalEntry(JournalEntry $journalEntry, array $data): array
    {
        if ($journalEntry->status === 'posted') {
            return [
                'success' => false,
                'data' => $journalEntry->load(['details.account', 'details.costCenter', 'currency']),
                'message' => 'Posted journal entries cannot be modified. Create a reversing entry instead.',
            ];
        }

        return $this->persistJournalEntry($journalEntry, $data, false);
    }

    /**
     * Post a draft journal entry (with rollback on error).
     */
    public function postJournalEntry(int $journalEntryId): array
    {
        try {
            return DB::transaction(function () use ($journalEntryId) {
                $journalEntry = JournalEntry::with(['details'])
                    ->lockForUpdate()
                    ->findOrFail($journalEntryId);

                if ($journalEntry->status === 'posted') {
                    throw new \Exception('Journal entry is already posted.');
                }

                if ($journalEntry->status === 'void') {
                    throw new \Exception('Cannot post a voided journal entry.');
                }

                $lines = $journalEntry->details->map(function (JournalEntryDetail $detail) {
                    return [
                        'account_id' => $detail->chart_of_account_id,
                        'debit' => (float) $detail->debit,
                        'credit' => (float) $detail->credit,
                        'description' => $detail->description,
                        'cost_center_id' => $detail->cost_center_id,
                    ];
                });

                $this->validateLines($lines);

                $journalEntry->accounting_period_id = $journalEntry->accounting_period_id
                    ?? $this->resolveAccountingPeriodId($journalEntry->entry_date);

                $this->markEntryAsPosted($journalEntry);

                Log::info('Journal entry posted successfully', [
                    'entry_id' => $journalEntry->id,
                    'reference' => $journalEntry->reference,
                ]);

                return [
                    'success' => true,
                    'data' => $journalEntry->load(['details.account', 'details.costCenter', 'currency']),
                    'message' => 'Journal entry posted successfully.',
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to post journal entry', [
                'entry_id' => $journalEntryId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to post journal entry: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Create a reversing entry for a posted journal entry.
     */
    public function reverseJournalEntry(int $journalEntryId, ?string $description = null): array
    {
        try {
            return DB::transaction(function () use ($journalEntryId, $description) {
                $originalEntry = JournalEntry::with(['details'])->findOrFail($journalEntryId);

                if ($originalEntry->status !== 'posted') {
                    throw new \Exception('Only posted entries can be reversed.');
                }

                $reversingEntry = new JournalEntry([
                    'currency_id' => $originalEntry->currency_id,
                    'entry_date' => now()->toDateString(),
                    'reference' => $originalEntry->reference
                        ? 'REV-'.$originalEntry->reference
                        : 'REV-'.$originalEntry->id,
                    'description' => $description ?? 'Reversal of entry #'.$originalEntry->id,
                    'status' => 'draft',
                    'fx_rate_to_base' => $originalEntry->fx_rate_to_base,
                ]);

                $reversingEntry->accounting_period_id = $this->resolveAccountingPeriodId($reversingEntry->entry_date);
                $reversingEntry->save();

                foreach ($originalEntry->details as $index => $detail) {
                    $reversingEntry->details()->create([
                        'chart_of_account_id' => $detail->chart_of_account_id,
                        'cost_center_id' => $detail->cost_center_id,
                        'line_no' => $index + 1,
                        'debit' => (float) $detail->credit,
                        'credit' => (float) $detail->debit,
                        'description' => $detail->description
                            ? 'Reversal: '.$detail->description
                            : 'Reversal entry',
                    ]);
                }

                $this->markEntryAsPosted($reversingEntry);

                Log::info('Journal entry reversed successfully', [
                    'original_entry_id' => $originalEntry->id,
                    'reversing_entry_id' => $reversingEntry->id,
                ]);

                return [
                    'success' => true,
                    'data' => $reversingEntry->load(['details.account', 'details.costCenter', 'currency']),
                    'message' => 'Reversing entry created and posted successfully.',
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to reverse journal entry', [
                'entry_id' => $journalEntryId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to reverse journal entry: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Quick cash receipt entry (with rollback on error).
     *
     * @param  array  $options  ['reference', 'cost_center_id', 'auto_post']
     */
    public function recordCashReceipt(float $amount, int $revenueAccountId, string $description, array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => $options['reference'] ?? 'CR-'.date('YmdHis'),
            'description' => $description,
            'lines' => [
                [
                    'account_id' => 7, // Cash account (code: 1131)
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Cash received',
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
                [
                    'account_id' => $revenueAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $description,
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }

    /**
     * Quick cash payment entry (with rollback on error).
     */
    public function recordCashPayment(float $amount, int $expenseAccountId, string $description, array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => $options['reference'] ?? 'CP-'.date('YmdHis'),
            'description' => $description,
            'lines' => [
                [
                    'account_id' => $expenseAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $description,
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
                [
                    'account_id' => 7, // Cash account (code: 1131)
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Cash paid',
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }

    /**
     * Record credit sale (receivable created).
     */
    public function recordCreditSale(float $amount, int $revenueAccountId, string $customerReference, string $description, array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => $options['reference'] ?? 'INV-'.date('YmdHis'),
            'description' => $description,
            'lines' => [
                [
                    'account_id' => 4, // Accounts receivable (code: 1111)
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Invoice to '.$customerReference,
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
                [
                    'account_id' => $revenueAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $description,
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }

    /**
     * Record payment received from customer (against receivable).
     */
    public function recordPaymentReceived(float $amount, string $customerReference, array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => $options['reference'] ?? 'REC-'.date('YmdHis'),
            'description' => 'Payment received from '.$customerReference,
            'lines' => [
                [
                    'account_id' => 7, // Cash (code: 1131)
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Cash received from '.$customerReference,
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
                [
                    'account_id' => 4, // Accounts receivable (code: 1111)
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Settlement of invoice '.($options['invoice_ref'] ?? ''),
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }

    /**
     * Record opening balance.
     */
    public function recordOpeningBalance(float $cashAmount, string $description = 'Opening balance - Owner capital', array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => $options['entry_date'] ?? now()->toDateString(),
            'reference' => $options['reference'] ?? 'OB-001',
            'description' => $description,
            'lines' => [
                [
                    'account_id' => 7, // Cash (code: 1131)
                    'debit' => $cashAmount,
                    'credit' => 0,
                    'description' => 'Opening cash balance',
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
                [
                    'account_id' => 29, // Capital stock (code: 3100)
                    'debit' => 0,
                    'credit' => $cashAmount,
                    'description' => 'Owner initial investment',
                    'cost_center_id' => $options['cost_center_id'] ?? null,
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }

    /**
     * Persist a journal entry and its detail lines.
     *
     * @return array{success: bool, data: ?JournalEntry, message: string}
     */
    protected function persistJournalEntry(JournalEntry $journalEntry, array $data, bool $isNew): array
    {
        try {
            return DB::transaction(function () use ($journalEntry, $data, $isNew) {
                $lines = $this->normalizeLines($data['lines'] ?? []);
                $this->validateLines($lines);

                $fxRate = $data['fx_rate_to_base'] ?? $data['fx_rate'] ?? 1.0;

                $journalEntry->fill([
                    'currency_id' => $data['currency_id'] ?? $journalEntry->currency_id ?? $this->getBaseCurrencyId(),
                    'entry_date' => $data['entry_date'],
                    'reference' => $data['reference'] ?? null,
                    'description' => $data['description'] ?? null,
                    'fx_rate_to_base' => $fxRate,
                ]);

                $journalEntry->accounting_period_id = $this->resolveAccountingPeriodId(
                    $journalEntry->entry_date,
                    $data['accounting_period_id'] ?? null
                );

                if ($isNew) {
                    $journalEntry->status = 'draft';
                }

                $journalEntry->save();

                $journalEntry->details()->delete();

                foreach ($lines->values() as $index => $line) {
                    $journalEntry->details()->create([
                        'chart_of_account_id' => $line['account_id'],
                        'cost_center_id' => $line['cost_center_id'],
                        'line_no' => $index + 1,
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'description' => $line['description'],
                    ]);
                }

                if ($data['auto_post'] ?? false) {
                    $this->markEntryAsPosted($journalEntry);
                    $message = $isNew
                        ? 'Journal entry created and posted successfully.'
                        : 'Journal entry updated and posted successfully.';
                } else {
                    $message = $isNew
                        ? 'Journal entry created successfully.'
                        : 'Journal entry updated successfully.';
                }

                $journalEntry->load(['details.account', 'details.costCenter', 'currency']);

                Log::info(
                    $isNew ? 'Journal entry created successfully' : 'Journal entry updated successfully',
                    [
                        'entry_id' => $journalEntry->id,
                        'reference' => $journalEntry->reference,
                        'status' => $journalEntry->status,
                    ]
                );

                return [
                    'success' => true,
                    'data' => $journalEntry,
                    'message' => $message,
                ];
            });
        } catch (\Exception $e) {
            Log::error(
                $isNew ? 'Failed to create journal entry' : 'Failed to update journal entry',
                [
                    'entry_id' => $journalEntry->id ?? null,
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]
            );

            return [
                'success' => false,
                'data' => $journalEntry->exists ? $journalEntry->loadMissing(['details']) : null,
                'message' => 'Failed to '.($isNew ? 'create' : 'update').' journal entry: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Normalise line payload.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<int, array<string, mixed>>
     */
    protected function normalizeLines(array $lines): Collection
    {
        return collect($lines)->map(function ($line) {
            return [
                'account_id' => (int) ($line['account_id'] ?? 0),
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'description' => $line['description'] ?? null,
                'cost_center_id' => $line['cost_center_id'] !== null
                    ? (int) $line['cost_center_id']
                    : null,
            ];
        });
    }

    /**
     * Validate a set of journal entry lines.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     *
     * @throws \Exception
     */
    protected function validateLines(Collection $lines): void
    {
        if ($lines->count() < 2) {
            throw new \Exception('At least two lines are required for a journal entry.');
        }

        $totalDebits = $lines->sum('debit');
        $totalCredits = $lines->sum('credit');

        if (abs($totalDebits - $totalCredits) > 0.01) {
            throw new \Exception("Entry is not balanced. Total Debits: {$totalDebits}, Total Credits: {$totalCredits}");
        }

        foreach ($lines as $index => $line) {
            if (empty($line['account_id'])) {
                throw new \Exception('Account is required for line '.($index + 1).'.');
            }

            $debit = $line['debit'];
            $credit = $line['credit'];

            if ($debit > 0 && $credit > 0) {
                throw new \Exception('Line '.($index + 1).' cannot have both debit and credit amounts.');
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \Exception('Line '.($index + 1).' must have either a debit or credit amount.');
            }
        }
    }

    /**
     * Resolve accounting period ID for a given entry date.
     */
    protected function resolveAccountingPeriodId(string $entryDate, ?int $preferred = null): ?int
    {
        if ($preferred) {
            return $preferred;
        }

        return AccountingPeriod::query()
            ->where('status', AccountingPeriod::STATUS_OPEN)
            ->whereDate('start_date', '<=', $entryDate)
            ->whereDate('end_date', '>=', $entryDate)
            ->value('id');
    }

    /**
     * Mark an entry as posted and set audit metadata.
     */
    protected function markEntryAsPosted(JournalEntry $journalEntry): void
    {
        $journalEntry->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => auth()->id(),
        ]);
    }

    /**
     * Get the base currency ID
     */
    protected function getBaseCurrencyId(): int
    {
        return \App\Models\Currency::where('is_base_currency', true)->value('id') ?? 1;
    }
}
