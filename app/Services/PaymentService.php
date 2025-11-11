<?php

namespace App\Services;

use App\Models\SupplierPayment;
use App\Models\ChartOfAccount;
use App\Models\PaymentGrnAllocation;
use App\Models\GoodsReceiptNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentService
{
    /**
     * Post supplier payment and create journal entry
     * Dr. Creditors (2111) - Reduce liability
     * Cr. Bank/Cash - Reduce asset
     */
    public function postSupplierPayment(SupplierPayment $payment): array
    {
        try {
            DB::beginTransaction();

            if ($payment->status === 'posted') {
                throw new \Exception('Payment is already posted');
            }

            if ($payment->status === 'cancelled') {
                throw new \Exception('Cannot post cancelled payment');
            }

            // Create accounting journal entry
            $journalEntry = $this->createPaymentJournalEntry($payment);

            $payment->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id() ?? 1,
                'journal_entry_id' => $journalEntry ? $journalEntry->id : null,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => "Payment {$payment->payment_number} posted successfully" . ($journalEntry ? " with journal entry" : ""),
                'data' => $payment->fresh(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to post payment: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Create Journal Entry for Supplier Payment
     * Dr. Creditors (2111) - Reduce liability
     * Cr. Bank (1120) or Cash (1131) - Reduce asset
     */
    protected function createPaymentJournalEntry(SupplierPayment $payment)
    {
        try {
            // Find Creditors account
            $creditorsAccount = ChartOfAccount::where('account_code', '2111')->first();

            if (!$creditorsAccount) {
                Log::warning('Creditors account (2111) not found. Skipping journal entry for payment: ' . $payment->id);
                return null;
            }

            // Determine payment account based on payment method
            $paymentAccountCode = match ($payment->payment_method) {
                'cash' => '1131', // Cash
                'bank_transfer', 'cheque', 'online' => '1120', // Bank Accounts (parent)
                default => '1131',
            };

            // If bank payment and specific bank account selected, use that account
            if ($payment->bank_account_id && $payment->payment_method !== 'cash') {
                $bankAccount = $payment->bankAccount;
                if ($bankAccount && $bankAccount->chart_of_account_id) {
                    $paymentAccount = ChartOfAccount::find($bankAccount->chart_of_account_id);
                } else {
                    $paymentAccount = ChartOfAccount::where('account_code', $paymentAccountCode)->first();
                }
            } else {
                $paymentAccount = ChartOfAccount::where('account_code', $paymentAccountCode)->first();
            }

            if (!$paymentAccount) {
                Log::warning("Payment account ({$paymentAccountCode}) not found. Skipping journal entry for payment: " . $payment->id);
                return null;
            }

            // Check if payment account is a leaf account
            $hasChildren = ChartOfAccount::where('parent_id', $paymentAccount->id)->exists();
            if ($hasChildren) {
                // If it's a parent, try to find first child (leaf)
                $paymentAccount = ChartOfAccount::where('parent_id', $paymentAccount->id)->first();
                if (!$paymentAccount) {
                    Log::warning("No leaf account found under {$paymentAccountCode}. Skipping journal entry for payment: " . $payment->id);
                    return null;
                }
            }

            $amount = $payment->amount;

            if ($amount <= 0) {
                Log::warning('Payment amount is zero or negative. Skipping journal entry for payment: ' . $payment->id);
                return null;
            }

            // Build description
            $description = "Payment to {$payment->supplier->supplier_name}";
            if ($payment->reference_number) {
                $description .= " - Ref: {$payment->reference_number}";
            }

            // Prepare journal entry data
            $journalEntryData = [
                'entry_date' => Carbon::parse($payment->payment_date)->toDateString(),
                'description' => $description,
                'reference_type' => 'App\Models\SupplierPayment',
                'reference_id' => $payment->id,
                'lines' => [
                    [
                        'account_id' => $creditorsAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Payment to supplier - {$payment->payment_method}",
                        'cost_center_id' => 1,
                    ],
                    [
                        'account_id' => $paymentAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => "Paid via {$payment->payment_method}" . ($payment->reference_number ? " ({$payment->reference_number})" : ""),
                        'cost_center_id' => 1,
                    ],
                ],
                'auto_post' => true,
            ];

            // Create journal entry using AccountingService
            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry($journalEntryData);

            if ($result['success']) {
                Log::info("Journal entry created for payment {$payment->payment_number}: JE #{$result['data']->entry_number}");
                return $result['data'];
            } else {
                Log::error("Failed to create journal entry for payment {$payment->payment_number}: " . $result['message']);
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Exception creating journal entry for payment {$payment->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get supplier outstanding balance (total unpaid amount)
     */
    /**
     * Get supplier outstanding balance (total unpaid amount)
     */
    public function getSupplierBalance(int $supplierId): float
    {
        // Get total from all posted GRNs
        $totalPurchases = GoodsReceiptNote::where('supplier_id', $supplierId)
            ->where('status', 'posted')
            ->sum('grand_total');

        // Get total posted payments
        $totalPayments = SupplierPayment::where('supplier_id', $supplierId)
            ->where('status', 'posted')
            ->sum('amount');

        return (float) ($totalPurchases - $totalPayments);
    }

    /**
     * Get unpaid GRNs for a supplier with payment status
     */
    public function getUnpaidGrns(int $supplierId): array
    {
        $grns = DB::table('goods_receipt_notes as grn')
            ->select([
                'grn.id',
                'grn.grn_number',
                'grn.receipt_date',
                'grn.grand_total',
                DB::raw('COALESCE((
                    SELECT SUM(allocations.allocated_amount) 
                    FROM payment_grn_allocations allocations
                    INNER JOIN supplier_payments sp ON allocations.supplier_payment_id = sp.id
                    WHERE allocations.grn_id = grn.id 
                    AND sp.status = \'posted\'
                ), 0) as paid_amount'),
                DB::raw('grn.grand_total - COALESCE((
                    SELECT SUM(allocations.allocated_amount) 
                    FROM payment_grn_allocations allocations
                    INNER JOIN supplier_payments sp ON allocations.supplier_payment_id = sp.id
                    WHERE allocations.grn_id = grn.id 
                    AND sp.status = \'posted\'
                ), 0) as balance'),
            ])
            ->where('grn.supplier_id', $supplierId)
            ->where('grn.status', 'posted')
            ->havingRaw('grn.grand_total - COALESCE((
                SELECT SUM(allocations.allocated_amount) 
                FROM payment_grn_allocations allocations
                INNER JOIN supplier_payments sp ON allocations.supplier_payment_id = sp.id
                WHERE allocations.grn_id = grn.id 
                AND sp.status = \'posted\'
            ), 0) > 0')
            ->orderBy('grn.receipt_date')
            ->get()
            ->toArray();

        return $grns;
    }
}
