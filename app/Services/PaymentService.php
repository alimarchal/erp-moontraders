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

            // Validate bank account is selected for non-cash payment methods
            if (in_array($payment->payment_method, ['bank_transfer', 'cheque', 'online']) && !$payment->bank_account_id) {
                throw new \Exception('Bank account is required for ' . str_replace('_', ' ', $payment->payment_method) . ' payment method');
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
            $userName = auth()->user()->name ?? 'System';
            $description = "Payment to {$payment->supplier->supplier_name} ({$payment->payment_number}) - Password confirmed by: {$userName}";
            if ($payment->reference_number) {
                $description .= " - Ref: {$payment->reference_number}";
            }

            // Prepare journal entry data
            $journalEntryData = [
                'entry_date' => Carbon::parse($payment->payment_date)->toDateString(),
                'reference' => $payment->reference_number ?? $payment->payment_number,
                'description' => $description,
                'reference_type' => 'App\Models\SupplierPayment',
                'reference_id' => $payment->id,
                'lines' => [
                    [
                        'account_id' => $creditorsAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Payment to supplier - {$payment->payment_method}",
                        'cost_center_id' => 10, // CC010: Procurement & Purchasing
                    ],
                    [
                        'account_id' => $paymentAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => "Paid via {$payment->payment_method}" . ($payment->reference_number ? " ({$payment->reference_number})" : ""),
                        'cost_center_id' => 1, // CC001: Finance & Accounting
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
     * Compatible with MySQL/MariaDB and PostgreSQL
     */
    public function getUnpaidGrns(int $supplierId): array
    {
        $grns = DB::table('goods_receipt_notes as grn')
            ->leftJoin(DB::raw('(
                SELECT allocations.grn_id, SUM(allocations.allocated_amount) as total_paid
                FROM payment_grn_allocations allocations
                INNER JOIN supplier_payments sp ON allocations.supplier_payment_id = sp.id
                WHERE sp.status = ' . DB::connection()->getPdo()->quote('posted') . '
                GROUP BY allocations.grn_id
            ) as payments'), 'grn.id', '=', 'payments.grn_id')
            ->select([
                'grn.id',
                'grn.grn_number',
                'grn.receipt_date',
                'grn.grand_total',
                DB::raw('COALESCE(payments.total_paid, 0) as paid_amount'),
                DB::raw('grn.grand_total - COALESCE(payments.total_paid, 0) as balance'),
            ])
            ->where('grn.supplier_id', $supplierId)
            ->where('grn.status', 'posted')
            ->whereRaw('grn.grand_total - COALESCE(payments.total_paid, 0) > 0')
            ->orderBy('grn.receipt_date')
            ->get()
            ->toArray();

        return $grns;
    }

    /**
     * Reverse a posted supplier payment
     * Creates a reversing journal entry and marks payment as reversed
     */
    public function reverseSupplierPayment(SupplierPayment $payment): array
    {
        try {
            DB::beginTransaction();

            if ($payment->status !== 'posted') {
                throw new \Exception('Only posted payments can be reversed');
            }

            if ($payment->status === 'reversed') {
                throw new \Exception('Payment is already reversed');
            }

            // Create reversing journal entry
            $reversingEntry = $this->createReversingJournalEntry($payment);

            // Update payment status
            $payment->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => "Payment {$payment->payment_number} reversed successfully" . ($reversingEntry ? " with reversing journal entry" : ""),
                'data' => $payment->fresh(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to reverse payment {$payment->id}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to reverse payment: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Create reversing journal entry for supplier payment
     * Opposite of original entry: Dr. Bank/Cash, Cr. Creditors
     */
    protected function createReversingJournalEntry(SupplierPayment $payment)
    {
        try {
            // Find Creditors account
            $creditorsAccount = ChartOfAccount::where('account_code', '2111')->first();

            if (!$creditorsAccount) {
                Log::warning('Creditors account (2111) not found. Skipping reversing journal entry for payment: ' . $payment->id);
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
                Log::warning("Payment account ({$paymentAccountCode}) not found. Skipping reversing journal entry for payment: " . $payment->id);
                return null;
            }

            // Check if payment account is a leaf account
            $hasChildren = ChartOfAccount::where('parent_id', $paymentAccount->id)->exists();
            if ($hasChildren) {
                // If it's a parent, try to find first child (leaf)
                $paymentAccount = ChartOfAccount::where('parent_id', $paymentAccount->id)->first();
                if (!$paymentAccount) {
                    Log::warning("No leaf account found under {$paymentAccountCode}. Skipping reversing journal entry for payment: " . $payment->id);
                    return null;
                }
            }

            $amount = $payment->amount;

            if ($amount <= 0) {
                Log::warning('Payment amount is zero or negative. Skipping reversing journal entry for payment: ' . $payment->id);
                return null;
            }

            // Build description
            $description = "REVERSAL: Payment to {$payment->supplier->supplier_name} ({$payment->payment_number})";
            if ($payment->reference_number) {
                $description .= " - Ref: {$payment->reference_number}";
            }

            // Prepare reversing journal entry data (opposite of original)
            $journalEntryData = [
                'entry_date' => now()->toDateString(),
                'reference' => $payment->reference_number ?? $payment->payment_number,
                'description' => $description,
                'reference_type' => 'App\Models\SupplierPayment',
                'reference_id' => $payment->id,
                'lines' => [
                    [
                        'account_id' => $paymentAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Reversal of payment via {$payment->payment_method}",
                        'cost_center_id' => 1, // CC001: Finance & Accounting
                    ],
                    [
                        'account_id' => $creditorsAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => "Reversal - Payment to supplier returned",
                        'cost_center_id' => 10, // CC010: Procurement & Purchasing
                    ],
                ],
                'auto_post' => true,
            ];

            // Create reversing journal entry using AccountingService
            $accountingService = app(AccountingService::class);
            $result = $accountingService->createJournalEntry($journalEntryData);

            if ($result['success']) {
                Log::info("Reversing journal entry created for payment {$payment->payment_number}: JE #{$result['data']->entry_number}");
                return $result['data'];
            } else {
                Log::error("Failed to create reversing journal entry for payment {$payment->payment_number}: " . $result['message']);
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Exception creating reversing journal entry for payment {$payment->id}: " . $e->getMessage());
            return null;
        }
    }
}
