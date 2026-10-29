<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\ExpenseVoucher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Maker-checker flow of the daily expense vouchers. The maker drafts and submits; a different user
 * approves (which posts Dr expense account / Cr cash to the ledger) or rejects with a reason.
 */
class ExpenseVoucherService
{
    public function __construct(private AccountingService $accounting) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ExpenseVoucher
    {
        return DB::transaction(function () use ($data) {
            $data['voucher_number'] = $this->nextNumber();
            $data['status'] = ExpenseVoucher::STATUS_DRAFT;

            return ExpenseVoucher::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ExpenseVoucher $voucher, array $data): ExpenseVoucher
    {
        $this->ensureEditable($voucher);

        $voucher->update($data);

        return $voucher->fresh();
    }

    public function submit(ExpenseVoucher $voucher, User $maker): ExpenseVoucher
    {
        $this->ensureEditable($voucher);

        $voucher->update([
            'status' => ExpenseVoucher::STATUS_PENDING,
            'submitted_by' => $maker->id,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);

        return $voucher->fresh();
    }

    /**
     * The checker must not be the user who made the voucher.
     */
    public function approve(ExpenseVoucher $voucher, User $checker): ExpenseVoucher
    {
        $this->ensurePendingFor($voucher, $checker);

        return DB::transaction(function () use ($voucher, $checker) {
            $cashAccountId = ChartOfAccount::where('account_code', '1121')->value('id');

            if (! $cashAccountId) {
                throw new RuntimeException('The Cash account (1121) is missing; the voucher cannot be posted.');
            }

            $description = 'Expense voucher '.$voucher->voucher_number.' - '.$voucher->party_name;
            $result = $this->accounting->createJournalEntry([
                'entry_date' => $voucher->voucher_date->toDateString(),
                'reference' => $voucher->voucher_number,
                'description' => $description.($voucher->narration ? ' - '.$voucher->narration : ''),
                'reference_type' => ExpenseVoucher::class,
                'reference_id' => $voucher->id,
                'lines' => [
                    ['account_id' => $voucher->expense_account_id, 'debit' => (float) $voucher->amount, 'credit' => 0, 'description' => $description, 'cost_center_id' => 1],
                    ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => (float) $voucher->amount, 'description' => $description, 'cost_center_id' => 1],
                ],
                'auto_post' => true,
            ]);

            if (! $result['success']) {
                throw new RuntimeException('Posting failed: '.$result['message']);
            }

            $voucher->update([
                'status' => ExpenseVoucher::STATUS_APPROVED,
                'checked_by' => $checker->id,
                'checked_at' => now(),
                'journal_entry_id' => $result['data']->id,
                'rejection_reason' => null,
            ]);

            return $voucher->fresh();
        });
    }

    public function reject(ExpenseVoucher $voucher, User $checker, string $reason): ExpenseVoucher
    {
        $this->ensurePendingFor($voucher, $checker);

        $voucher->update([
            'status' => ExpenseVoucher::STATUS_REJECTED,
            'checked_by' => $checker->id,
            'checked_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $voucher->fresh();
    }

    private function ensureEditable(ExpenseVoucher $voucher): void
    {
        if (! $voucher->isEditable()) {
            throw new RuntimeException('Only a draft or rejected voucher can be changed.');
        }
    }

    private function ensurePendingFor(ExpenseVoucher $voucher, User $checker): void
    {
        if ($voucher->status !== ExpenseVoucher::STATUS_PENDING) {
            throw new RuntimeException('Only a voucher waiting for approval can be approved or rejected.');
        }

        if ((int) $voucher->submitted_by === $checker->id || (int) $voucher->created_by === $checker->id) {
            throw new RuntimeException('The maker of a voucher cannot also be its checker.');
        }
    }

    /**
     * EV-2026-0001: running number per year; call inside a transaction.
     */
    private function nextNumber(): string
    {
        $prefix = 'EV-'.now()->format('Y').'-';

        $last = ExpenseVoucher::withTrashed()
            ->where('voucher_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('voucher_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
