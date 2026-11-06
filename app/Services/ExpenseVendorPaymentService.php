<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\ExpenseVendor;
use App\Models\ExpenseVendorPayment;
use App\Models\ExpenseVoucher;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Month-end payment of a shop's credit vouchers. The maker picks the vouchers and amounts, a different
 * user approves: that posts Dr Expense Payables (2113) / Cr Cash or the bank account and marks each
 * voucher paid, partly paid or unpaid.
 */
class ExpenseVendorPaymentService
{
    public function __construct(private AccountingService $accounting, private DocumentNumberService $numbers) {}

    /**
     * Approved credit vouchers of the shop that still owe something.
     *
     * @return Collection<int, ExpenseVoucher>
     */
    public function outstandingVouchers(ExpenseVendor $vendor, ?User $viewer = null): Collection
    {
        return ExpenseVoucher::query()
            ->where('expense_vendor_id', $vendor->id)
            ->where('status', ExpenseVoucher::STATUS_APPROVED)
            ->where('payment_type', ExpenseVoucher::TYPE_CREDIT)
            ->whereColumn('paid_amount', '<', 'amount')
            ->when($viewer, fn ($query) => $query->visibleTo($viewer))
            ->with(['supplier:id,supplier_name,short_name', 'company:id,company_name,abbr'])
            ->orderBy('voucher_date')->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data  payment_date, expense_vendor_id, method, bank_account_id, reference, narration
     * @param  array<int, float|string>  $amounts  voucher id => amount to pay
     */
    public function create(array $data, array $amounts, User $maker): ExpenseVendorPayment
    {
        $amounts = array_filter(array_map(fn ($amount) => round((float) $amount, 2), $amounts), fn ($amount) => $amount > 0);

        if ($amounts === []) {
            throw new RuntimeException('Choose at least one voucher and an amount to pay.');
        }

        return DB::transaction(function () use ($data, $amounts) {
            $vouchers = ExpenseVoucher::query()->whereIn('id', array_keys($amounts))->lockForUpdate()->get();
            $owner = $this->singleOwner($vouchers, (int) $data['expense_vendor_id']);

            foreach ($vouchers as $voucher) {
                if ($amounts[$voucher->id] > $voucher->outstanding() + 0.001) {
                    throw new RuntimeException('Voucher '.$voucher->voucher_number.' only owes Rs. '.number_format($voucher->outstanding(), 2).'.');
                }
            }

            $payment = ExpenseVendorPayment::create($data + $owner + [
                'payment_number' => $this->numbers->next('vendor_payment', 'VP'),
                'amount' => array_sum($amounts),
                'status' => ExpenseVendorPayment::STATUS_PENDING,
            ]);

            foreach ($amounts as $voucherId => $amount) {
                $payment->allocations()->create(['expense_voucher_id' => $voucherId, 'amount' => $amount]);
            }

            return $payment;
        });
    }

    public function approve(ExpenseVendorPayment $payment, User $checker): ExpenseVendorPayment
    {
        $this->ensurePendingFor($payment, $checker);

        return DB::transaction(function () use ($payment, $checker) {
            $payment->load('allocations.voucher');
            $vouchers = ExpenseVoucher::query()->whereIn('id', $payment->allocations->pluck('expense_voucher_id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($payment->allocations as $allocation) {
                $voucher = $vouchers[$allocation->expense_voucher_id];

                if ((float) $allocation->amount > $voucher->outstanding() + 0.001) {
                    throw new RuntimeException('Voucher '.$voucher->voucher_number.' no longer owes this much (it may have been paid by another payment).');
                }
            }

            $payableId = $this->accountId(ExpenseVoucherService::PAYABLES_ACCOUNT, 'Expense Payables (2113)');
            $paidFromId = $payment->method === 'bank'
                ? (int) ($payment->bankAccount?->chart_of_account_id ?: throw new RuntimeException('The chosen bank account is not linked to a ledger account.'))
                : $this->accountId(ExpenseVoucherService::CASH_ACCOUNT, 'Cash (1121)');

            $description = 'Vendor payment '.$payment->payment_number.' - '.$payment->vendor->name;
            $result = $this->accounting->createJournalEntry([
                'entry_date' => $payment->payment_date->toDateString(),
                'reference' => $payment->payment_number,
                'description' => $description.($payment->narration ? ' - '.$payment->narration : ''),
                'reference_type' => ExpenseVendorPayment::class,
                'reference_id' => $payment->id,
                'lines' => [
                    ['account_id' => $payableId, 'debit' => (float) $payment->amount, 'credit' => 0, 'description' => $description, 'cost_center_id' => 1],
                    ['account_id' => $paidFromId, 'debit' => 0, 'credit' => (float) $payment->amount, 'description' => $description, 'cost_center_id' => 1],
                ],
                'auto_post' => true,
            ]);

            if (! $result['success']) {
                throw new RuntimeException('Posting failed: '.$result['message']);
            }

            foreach ($payment->allocations as $allocation) {
                $voucher = $vouchers[$allocation->expense_voucher_id];
                $paid = round((float) $voucher->paid_amount + (float) $allocation->amount, 2);
                $voucher->update([
                    'paid_amount' => $paid,
                    'payment_status' => $paid + 0.001 >= (float) $voucher->amount ? ExpenseVoucher::PAID : ExpenseVoucher::PARTIAL,
                ]);
            }

            $payment->update([
                'status' => ExpenseVendorPayment::STATUS_APPROVED,
                'checked_by' => $checker->id,
                'checked_at' => now(),
                'journal_entry_id' => $result['data']->id,
                'rejection_reason' => null,
            ]);

            return $payment->fresh();
        });
    }

    public function reject(ExpenseVendorPayment $payment, User $checker, string $reason): ExpenseVendorPayment
    {
        $this->ensurePendingFor($payment, $checker);

        $payment->update([
            'status' => ExpenseVendorPayment::STATUS_REJECTED,
            'checked_by' => $checker->id,
            'checked_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $payment->fresh();
    }

    /**
     * One payment settles vouchers of one company only, so the ledger of each company stays its own.
     *
     * @param  Collection<int, ExpenseVoucher>  $vouchers
     * @return array{supplier_id: int|null, company_id: int|null}
     */
    private function singleOwner(Collection $vouchers, int $vendorId): array
    {
        foreach ($vouchers as $voucher) {
            if ((int) $voucher->expense_vendor_id !== $vendorId || ! $voucher->isCredit() || ! $voucher->isApproved()) {
                throw new RuntimeException('Voucher '.$voucher->voucher_number.' cannot be paid through this shop.');
            }
        }

        $owners = $vouchers->map(fn (ExpenseVoucher $voucher) => $voucher->supplier_id ? 's'.$voucher->supplier_id : 'c'.$voucher->company_id)->unique();

        if ($owners->count() !== 1) {
            throw new RuntimeException('One payment can only settle vouchers of one company. Make a separate payment for each company.');
        }

        $first = $vouchers->first();

        return ['supplier_id' => $first->supplier_id, 'company_id' => $first->company_id];
    }

    private function ensurePendingFor(ExpenseVendorPayment $payment, User $checker): void
    {
        if ($payment->status !== ExpenseVendorPayment::STATUS_PENDING) {
            throw new RuntimeException('Only a payment waiting for approval can be approved or rejected.');
        }

        if ((int) $payment->created_by === $checker->id) {
            throw new RuntimeException('The maker of a payment cannot also be its checker.');
        }
    }

    private function accountId(string $code, string $label): int
    {
        $id = ChartOfAccount::where('account_code', $code)->value('id');

        if (! $id) {
            throw new RuntimeException('The '.$label.' account is missing; the payment cannot be posted.');
        }

        return (int) $id;
    }
}
