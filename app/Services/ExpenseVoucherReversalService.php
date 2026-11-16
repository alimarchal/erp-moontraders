<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\ExpenseVendorPaymentAllocation;
use App\Models\ExpenseVoucher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Takes back an approved expense voucher. The posted entry stays as it was; an offsetting entry dated
 * today undoes it, so the day the voucher was approved keeps its figures and the day it is reversed
 * shows the money coming back. The notes paid out stay on the voucher for the cash report.
 */
class ExpenseVoucherReversalService
{
    public function __construct(private AccountingService $accounting) {}

    public function reverse(ExpenseVoucher $voucher, User $by, string $reason): ExpenseVoucher
    {
        return DB::transaction(function () use ($voucher, $by, $reason) {
            $voucher = ExpenseVoucher::query()->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureReversible($voucher, $by);
            $advance = $this->lockedAdvanceOf($voucher);

            $result = $this->accounting->reverseJournalEntry(
                (int) $voucher->journal_entry_id,
                'REVERSAL: expense voucher '.$voucher->voucher_number.' - '.$reason,
                today()->toDateString(),
            );

            if (! ($result['success'] ?? false)) {
                Log::error('Expense voucher reversal failed', ['voucher_id' => $voucher->id, 'voucher_number' => $voucher->voucher_number, 'message' => $result['message'] ?? null]);

                throw new RuntimeException(ExpenseVoucherService::POSTING_FAILED);
            }

            $voucher->update([
                'status' => ExpenseVoucher::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversed_by' => $by->id,
                'reversal_reason' => $reason,
                'reversal_journal_entry_id' => $result['data']->id,
            ]);

            $advance?->update(['status' => EmployeeAdvance::STATUS_CANCELLED]);

            return $voucher->fresh();
        });
    }

    private function ensureReversible(ExpenseVoucher $voucher, User $by): void
    {
        if ($voucher->isReversed()) {
            throw new RuntimeException('This voucher has already been reversed.');
        }

        if (! $voucher->isApproved() || ! $voucher->journal_entry_id) {
            throw new RuntimeException('Only an approved voucher can be reversed.');
        }

        if ((int) $voucher->created_by === $by->id) {
            throw new RuntimeException('The maker of a voucher cannot also reverse it.');
        }

        if ($voucher->isCredit() && ((float) $voucher->paid_amount > 0 || ExpenseVendorPaymentAllocation::where('expense_voucher_id', $voucher->id)->exists())) {
            throw new RuntimeException('This credit voucher has already been paid in part; it cannot be reversed.');
        }
    }

    private function lockedAdvanceOf(ExpenseVoucher $voucher): ?EmployeeAdvance
    {
        if (! $voucher->isAdvance()) {
            return null;
        }

        $advance = EmployeeAdvance::query()->where('expense_voucher_id', $voucher->id)->lockForUpdate()->first();

        if ($advance && ((float) $advance->recovered_amount > 0 || $advance->recoveries()->exists())) {
            throw new RuntimeException('This advance has already been recovered in payroll; it cannot be reversed.');
        }

        return $advance;
    }
}
