<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\EmployeeAdvance;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCheque;
use App\Models\User;
use App\Support\CashNotes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Maker-checker flow of the daily expense vouchers. The maker drafts and submits; a different user
 * approves (which posts Dr expense account / Cr cash to the ledger) or rejects with a reason.
 */
class ExpenseVoucherService
{
    public const CASH_ACCOUNT = '1121';

    public const PAYABLES_ACCOUNT = '2113';

    public const ADVANCES_ACCOUNT = '1131';

    public const POSTING_FAILED = 'Posting failed. Please contact the admin.';

    public function __construct(private AccountingService $accounting) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ExpenseVoucher
    {
        return DB::transaction(function () use ($data) {
            $notes = Arr::pull($data, 'denom');
            $data['voucher_number'] = $this->nextNumber();
            $data['status'] = ExpenseVoucher::STATUS_DRAFT;

            $voucher = ExpenseVoucher::create($data);
            $this->syncCashPayout($voucher, $notes);

            return $voucher;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ExpenseVoucher $voucher, array $data): ExpenseVoucher
    {
        $this->ensureEditable($voucher);

        return DB::transaction(function () use ($voucher, $data) {
            $notes = Arr::pull($data, 'denom');
            $voucher->update($data);
            $this->syncCashPayout($voucher, $notes);

            return $voucher->fresh();
        });
    }

    /**
     * The notes handed out are kept with a cash voucher; a voucher paid any other way has none.
     *
     * @param  array<int|string, mixed>|null  $notes  note value => count, plus 'coins'; null leaves cash notes as they are
     */
    private function syncCashPayout(ExpenseVoucher $voucher, ?array $notes): void
    {
        if ($voucher->payment_method !== ExpenseVoucher::METHOD_CASH) {
            $voucher->cashPayout()->delete();

            return;
        }

        if ($notes !== null) {
            $voucher->cashPayout()->updateOrCreate([], CashNotes::fromInput($notes)->columns());
        }
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
     * The checker must not be the user who made the voucher. The voucher row is locked and checked again
     * inside the transaction, so two clicks (or two checkers) can never post it twice.
     *
     * Debit: Employee Advances (1131) for an advance, otherwise the expense account. Credit, by how it was
     * paid: Cash (1121), the bank's own ledger account for a bank transfer or cheque, or Expense Payables
     * (2113) for credit, paid later by a vendor payment. An advance also opens an advance record for payroll.
     */
    public function approve(ExpenseVoucher $voucher, User $checker): ExpenseVoucher
    {
        $this->ensurePendingFor($voucher, $checker);

        return DB::transaction(function () use ($voucher, $checker) {
            $voucher = ExpenseVoucher::query()->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();
            $this->ensurePendingFor($voucher, $checker);

            $creditSide = $this->paidFromAccountId($voucher);
            $debitSide = $voucher->isAdvance() ? $this->accountId(self::ADVANCES_ACCOUNT, 'Employee Advances (1131)') : (int) $voucher->expense_account_id;
            $costCenterId = (int) ($voucher->company?->cost_center_id ?: 1);

            $description = 'Expense voucher '.$voucher->voucher_number.' - '.$voucher->party_name;
            $result = $this->post([
                'entry_date' => $voucher->voucher_date->toDateString(),
                'reference' => $voucher->voucher_number,
                'description' => $description.($voucher->narration ? ' - '.$voucher->narration : ''),
                'reference_type' => ExpenseVoucher::class,
                'reference_id' => $voucher->id,
                'lines' => [
                    ['account_id' => $debitSide, 'debit' => (float) $voucher->amount, 'credit' => 0, 'description' => $description, 'cost_center_id' => $costCenterId],
                    ['account_id' => $creditSide, 'debit' => 0, 'credit' => (float) $voucher->amount, 'description' => $description, 'cost_center_id' => $costCenterId],
                ],
                'auto_post' => true,
            ], $voucher);

            $voucher->update([
                'status' => ExpenseVoucher::STATUS_APPROVED,
                'checked_by' => $checker->id,
                'checked_at' => now(),
                'journal_entry_id' => $result->id,
                'paid_from_account_id' => $creditSide,
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
     * Cash in hand of a day, for the approval warning only: settlement cash of the posted settlements plus
     * cash brought back for cheques cleared that day, less the approved cash vouchers of that day. Scoped
     * to one company when one is given. The full daily sheet is the cash collection report.
     */
    public function cashInHandOn(string $date, ?int $supplierId): float
    {
        $ofCompany = fn (Builder $query) => $query->when($supplierId, fn (Builder $q) => $q->where(fn (Builder $own) => $own
            ->where('supplier_id', $supplierId)
            ->orWhere(fn (Builder $viaEmployee) => $viaEmployee->whereNull('supplier_id')->whereHas('employee', fn (Builder $e) => $e->where('supplier_id', $supplierId)))));

        $settlementCash = $ofCompany(SalesSettlement::query())
            ->where('status', 'posted')
            ->whereDate('settlement_date', $date)
            ->with('cashDenominations')
            ->get()
            ->sum(function (SalesSettlement $settlement) {
                $counted = (float) $settlement->cashDenominations->first()?->total_amount;

                return $counted > 0 ? $counted : (float) $settlement->cash_collected;
            });

        $clearedCash = SalesSettlementCheque::query()
            ->where('status', 'cleared')
            ->where(fn (Builder $query) => $query->whereNull('cleared_via')->orWhere('cleared_via', 'cash'))
            ->whereDate('cleared_date', $date)
            ->whereHas('salesSettlement', $ofCompany)
            ->get()
            ->sum(fn (SalesSettlementCheque $cheque) => $cheque->clearedAmountOrFull());

        $paidOut = (float) ExpenseVoucher::query()
            ->where('status', ExpenseVoucher::STATUS_APPROVED)
            ->where('payment_method', ExpenseVoucher::METHOD_CASH)
            ->whereDate('voucher_date', $date)
            ->when($supplierId, fn (Builder $query) => $query->where('supplier_id', $supplierId))
            ->sum('amount');

        return round($settlementCash + $clearedCash - $paidOut, 2);
    }

    /**
     * The account the money left from, by payment method.
     */
    private function paidFromAccountId(ExpenseVoucher $voucher): int
    {
        if ($voucher->isCredit()) {
            return $this->accountId(self::PAYABLES_ACCOUNT, 'Expense Payables (2113)');
        }

        if ($voucher->isPaidByBank()) {
            $accountId = $voucher->bankAccount?->chart_of_account_id;

            if (! $accountId) {
                throw new RuntimeException('The chosen bank account is not linked to a ledger account; the voucher cannot be posted.');
            }

            return (int) $accountId;
        }

        return $this->accountId(self::CASH_ACCOUNT, 'Cash (1121)');
    }

    /**
     * Posts the journal. What went wrong is written to the log; the user only gets a plain message.
     *
     * @param  array<string, mixed>  $entry
     */
    private function post(array $entry, ExpenseVoucher $voucher): JournalEntry
    {
        try {
            $result = $this->accounting->createJournalEntry($entry);
        } catch (Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if (! $result['success']) {
            Log::error('Expense voucher posting failed', ['voucher_id' => $voucher->id, 'voucher_number' => $voucher->voucher_number, 'message' => $result['message'] ?? null]);

            throw new RuntimeException(self::POSTING_FAILED);
        }

        return $result['data'];
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
