<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountingService
{
    /**
     * Create a journal entry with multiple lines (with automatic rollback on error)
     *
     * @param array $data [
     *   'entry_date' => '2025-10-31',
     *   'reference' => 'JE-001',
     *   'description' => 'Transaction description',
     *   'currency_id' => 1,
     *   'fx_rate' => 1.0,
     *   'cost_center_id' => null (optional),
     *   'auto_post' => false,
     *   'lines' => [
     *       ['account_id' => 1131, 'debit' => 1000, 'credit' => 0, 'description' => 'Line desc'],
     *       ['account_id' => 4100, 'debit' => 0, 'credit' => 1000, 'description' => 'Line desc'],
     *   ]
     * ]
     * @return array ['success' => bool, 'data' => JournalEntry|null, 'message' => string]
     */
    public function createJournalEntry(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {
                // Validate required fields
                if (empty($data['lines']) || count($data['lines']) < 2) {
                    throw new \Exception('At least two lines are required for a journal entry.');
                }

                // Validate lines balance
                $totalDebits = collect($data['lines'])->sum('debit');
                $totalCredits = collect($data['lines'])->sum('credit');

                if (abs($totalDebits - $totalCredits) > 0.01) {
                    throw new \Exception("Entry is not balanced. Total Debits: {$totalDebits}, Total Credits: {$totalCredits}");
                }

                // Validate each line has either debit or credit (not both, not neither)
                foreach ($data['lines'] as $index => $line) {
                    $debit = $line['debit'] ?? 0;
                    $credit = $line['credit'] ?? 0;

                    if ($debit == 0 && $credit == 0) {
                        throw new \Exception("Line " . ($index + 1) . " must have either debit or credit amount.");
                    }

                    if ($debit > 0 && $credit > 0) {
                        throw new \Exception("Line " . ($index + 1) . " cannot have both debit and credit amounts.");
                    }
                }

                // Create journal entry header
                $journalEntry = JournalEntry::create([
                    'currency_id' => $data['currency_id'] ?? 1,
                    'entry_date' => $data['entry_date'],
                    'reference' => $data['reference'] ?? null,
                    'description' => $data['description'],
                    'status' => 'draft',
                    'fx_rate_to_base' => $data['fx_rate'] ?? 1.0,
                ]);

                // Create journal entry lines
                $lineNo = 1;
                foreach ($data['lines'] as $line) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $journalEntry->id,
                        'chart_of_account_id' => $line['account_id'],
                        'line_no' => $lineNo++,
                        'debit' => $line['debit'] ?? 0.00,
                        'credit' => $line['credit'] ?? 0.00,
                        'description' => $line['description'] ?? null,
                        'cost_center_id' => $line['cost_center_id'] ?? $data['cost_center_id'] ?? null,
                    ]);
                }

                // Auto-post if requested
                if ($data['auto_post'] ?? false) {
                    $journalEntry->update([
                        'status' => 'posted',
                        'posted_at' => now(),
                        'posted_by' => auth()->id(),
                    ]);
                }

                Log::info('Journal entry created successfully', [
                    'entry_id' => $journalEntry->id,
                    'reference' => $journalEntry->reference,
                    'status' => $journalEntry->status
                ]);

                return [
                    'success' => true,
                    'data' => $journalEntry->load('journalEntryDetails'),
                    'message' => 'Journal entry created successfully.'
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to create journal entry', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create journal entry: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Post a draft journal entry (with rollback on error)
     *
     * @param int $journalEntryId
     * @return array ['success' => bool, 'data' => JournalEntry|null, 'message' => string]
     */
    public function postJournalEntry(int $journalEntryId): array
    {
        try {
            return DB::transaction(function () use ($journalEntryId) {
                $journalEntry = JournalEntry::findOrFail($journalEntryId);

                if ($journalEntry->status === 'posted') {
                    throw new \Exception('Journal entry is already posted.');
                }

                if ($journalEntry->status === 'void') {
                    throw new \Exception('Cannot post a voided journal entry.');
                }

                // Verify entry is balanced
                $details = $journalEntry->journalEntryDetails;
                $totalDebits = $details->sum('debit');
                $totalCredits = $details->sum('credit');

                if (abs($totalDebits - $totalCredits) > 0.01) {
                    throw new \Exception("Cannot post unbalanced entry. Debits: {$totalDebits}, Credits: {$totalCredits}");
                }

                $journalEntry->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => auth()->id(),
                ]);

                Log::info('Journal entry posted successfully', [
                    'entry_id' => $journalEntry->id,
                    'reference' => $journalEntry->reference
                ]);

                return [
                    'success' => true,
                    'data' => $journalEntry->fresh(),
                    'message' => 'Journal entry posted successfully.'
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to post journal entry', [
                'entry_id' => $journalEntryId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to post journal entry: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a reversing entry for a posted journal (with rollback on error)
     *
     * @param int $journalEntryId Original journal entry ID
     * @param string|null $description Override description
     * @return array ['success' => bool, 'data' => JournalEntry|null, 'message' => string]
     */
    public function reverseJournalEntry(int $journalEntryId, ?string $description = null): array
    {
        try {
            return DB::transaction(function () use ($journalEntryId, $description) {
                $originalEntry = JournalEntry::with('journalEntryDetails')->findOrFail($journalEntryId);

                if ($originalEntry->status !== 'posted') {
                    throw new \Exception('Only posted entries can be reversed.');
                }

                // Create reversing entry
                $reversingEntry = JournalEntry::create([
                    'currency_id' => $originalEntry->currency_id,
                    'entry_date' => now()->toDateString(),
                    'reference' => 'REV-' . $originalEntry->reference,
                    'description' => $description ?? 'REVERSAL: ' . $originalEntry->description,
                    'status' => 'draft',
                    'fx_rate_to_base' => $originalEntry->fx_rate_to_base,
                ]);

                // Create reversed lines (swap debit and credit)
                $lineNo = 1;
                foreach ($originalEntry->journalEntryDetails as $detail) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $reversingEntry->id,
                        'chart_of_account_id' => $detail->chart_of_account_id,
                        'line_no' => $lineNo++,
                        'debit' => $detail->credit,  // Swap
                        'credit' => $detail->debit,   // Swap
                        'description' => 'REVERSAL: ' . $detail->description,
                        'cost_center_id' => $detail->cost_center_id,
                    ]);
                }

                // Auto-post the reversing entry
                $reversingEntry->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => auth()->id(),
                ]);

                Log::info('Journal entry reversed successfully', [
                    'original_entry_id' => $originalEntry->id,
                    'reversing_entry_id' => $reversingEntry->id
                ]);

                return [
                    'success' => true,
                    'data' => $reversingEntry->load('journalEntryDetails'),
                    'message' => 'Reversing entry created and posted successfully.'
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to reverse journal entry', [
                'entry_id' => $journalEntryId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to reverse journal entry: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Quick cash receipt entry (with rollback on error)
     *
     * @param float $amount
     * @param int $revenueAccountId
     * @param string $description
     * @param array $options ['reference', 'cost_center_id', 'auto_post']
     * @return array
     */
    public function recordCashReceipt(float $amount, int $revenueAccountId, string $description, array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => $options['reference'] ?? 'CR-' . date('YmdHis'),
            'description' => $description,
            'cost_center_id' => $options['cost_center_id'] ?? null,
            'lines' => [
                [
                    'account_id' => 1131, // Cash account
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Cash received'
                ],
                [
                    'account_id' => $revenueAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $description
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }

    /**
     * Quick cash payment entry (with rollback on error)
     *
     * @param float $amount
     * @param int $expenseAccountId
     * @param string $description
     * @param array $options ['reference', 'cost_center_id', 'auto_post']
     * @return array
     */
    public function recordCashPayment(float $amount, int $expenseAccountId, string $description, array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => $options['reference'] ?? 'CP-' . date('YmdHis'),
            'description' => $description,
            'cost_center_id' => $options['cost_center_id'] ?? null,
            'lines' => [
                [
                    'account_id' => $expenseAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $description
                ],
                [
                    'account_id' => 1131, // Cash account
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Cash paid'
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }

    /**
     * Record credit sale (receivable created)
     *
     * @param float $amount
     * @param int $revenueAccountId
     * @param string $customerReference
     * @param string $description
     * @param array $options
     * @return array
     */
    public function recordCreditSale(float $amount, int $revenueAccountId, string $customerReference, string $description, array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => $options['reference'] ?? 'INV-' . date('YmdHis'),
            'description' => $description,
            'cost_center_id' => $options['cost_center_id'] ?? null,
            'lines' => [
                [
                    'account_id' => 1111, // Debtors/Accounts Receivable
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Invoice to ' . $customerReference
                ],
                [
                    'account_id' => $revenueAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $description
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }

    /**
     * Record payment received from customer (against receivable)
     *
     * @param float $amount
     * @param string $customerReference
     * @param array $options
     * @return array
     */
    public function recordPaymentReceived(float $amount, string $customerReference, array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'reference' => $options['reference'] ?? 'REC-' . date('YmdHis'),
            'description' => 'Payment received from ' . $customerReference,
            'lines' => [
                [
                    'account_id' => 1131, // Cash
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Cash received from ' . $customerReference
                ],
                [
                    'account_id' => 1111, // Debtors/Accounts Receivable
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Settlement of invoice ' . ($options['invoice_ref'] ?? '')
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }

    /**
     * Record opening balance
     *
     * @param float $cashAmount
     * @param string $description
     * @param array $options
     * @return array
     */
    public function recordOpeningBalance(float $cashAmount, string $description = 'Opening balance - Owner capital', array $options = []): array
    {
        return $this->createJournalEntry([
            'entry_date' => $options['entry_date'] ?? now()->toDateString(),
            'reference' => $options['reference'] ?? 'OB-001',
            'description' => $description,
            'lines' => [
                [
                    'account_id' => 1131, // Cash
                    'debit' => $cashAmount,
                    'credit' => 0,
                    'description' => 'Opening cash balance'
                ],
                [
                    'account_id' => 3100, // Capital Stock
                    'debit' => 0,
                    'credit' => $cashAmount,
                    'description' => 'Owner initial investment'
                ],
            ],
            'auto_post' => $options['auto_post'] ?? true,
        ]);
    }
}
