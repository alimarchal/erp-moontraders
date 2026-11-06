<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\EmployeeAdvance;
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
    public const CASH_ACCOUNT = '1121';

    public const PAYABLES_ACCOUNT = '2113';

    public const ADVANCES_ACCOUNT = '1131';

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
            'checked_by' => null,
            'checked_at' => null,
            'rejection_reason' => null,
        ]);

        return $voucher->fresh();
    }

    /**
     * The checker must not be the user who made the voucher. What is posted depends on how it was paid:
     * cash is Dr expense / Cr Cash, credit is Dr expense / Cr Expense Payables (2113, paid later by a vendor
     * payment) and an advance is Dr Employee Advances (1131) / Cr Cash, with an advance record for payroll.
     */
    public function approve(ExpenseVoucher $voucher, User $checker): ExpenseVoucher
    {
        $this->ensurePendingFor($voucher, $checker);

        return DB::transaction(function () use ($voucher, $checker) {
            $creditSide = $this->accountId($voucher->isCredit() ? self::PAYABLES_ACCOUNT : self::CASH_ACCOUNT, $voucher->isCredit() ? 'Expense Payables (2113)' : 'Cash (1121)');
            $debitSide = $voucher->isAdvance() ? $this->accountId(self::ADVANCES_ACCOUNT, 'Employee Advances (1131)') : (int) $voucher->expense_account_id;

            $description = 'Expense voucher '.$voucher->voucher_number.' - '.$voucher->party_name;
            $result = $this->accounting->createJournalEntry([
                'entry_date' => $voucher->voucher_date->toDateString(),
                'reference' => $voucher->voucher_number,
                'description' => $description.($voucher->narration ? ' - '.$voucher->narration : ''),
                'reference_type' => ExpenseVoucher::class,
                'reference_id' => $voucher->id,
                'lines' => [
                    ['account_id' => $debitSide, 'debit' => (float) $voucher->amount, 'credit' => 0, 'description' => $description, 'cost_center_id' => 1],
                    ['account_id' => $creditSide, 'debit' => 0, 'credit' => (float) $voucher->amount, 'description' => $description, 'cost_center_id' => 1],
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
                'payment_status' => $voucher->isCredit() ? ExpenseVoucher::UNPAID : ExpenseVoucher::PAID,
                'paid_amount' => $voucher->isCredit() ? 0 : $voucher->amount,
            ]);

            if ($voucher->isAdvance()) {
                $this->openAdvance($voucher);
            }

            return $voucher->fresh();
        });
    }

    /**
     * The balance payroll will recover from the employee's salary.
     */
    private function openAdvance(ExpenseVoucher $voucher): void
    {
        EmployeeAdvance::create([
            'employee_id' => $voucher->employee_id,
            'expense_voucher_id' => $voucher->id,
            'supplier_id' => $voucher->supplier_id,
            'company_id' => $voucher->company_id,
            'advance_date' => $voucher->voucher_date,
            'amount' => $voucher->amount,
            'installment_amount' => $voucher->advance_installment,
            'recovered_amount' => 0,
            'status' => EmployeeAdvance::STATUS_OPEN,
            'notes' => $voucher->narration,
        ]);
    }

    private function accountId(string $code, string $label): int
    {
        $id = ChartOfAccount::where('account_code', $code)->value('id');

        if (! $id) {
            throw new RuntimeException('The '.$label.' account is missing; the voucher cannot be posted.');
        }

        return (int) $id;
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

        if ((int) $voucher->created_by === $checker->id) {
            throw new RuntimeException('The maker of a voucher cannot also be its checker.');
        }
    }

    /**
     * MT-EV-2026-000001: company code, document type, year and a six digit running number taken from
     * the year's counter row, which is locked for the length of the transaction so two vouchers can
     * never get the same number (the column is unique as well).
     */
    private function nextNumber(): string
    {
        $year = (int) now()->format('Y');

        DB::table('expense_voucher_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        $last = (int) DB::table('expense_voucher_sequences')->where('year', $year)->lockForUpdate()->value('last_number');
        $next = $last + 1;

        DB::table('expense_voucher_sequences')->where('year', $year)->update(['last_number' => $next]);

        $code = Company::query()->orderBy('id')->value('abbr') ?: 'MT';

        return strtoupper($code).'-EV-'.$year.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
