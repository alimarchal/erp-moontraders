<?php

namespace App\Services\BankRegister;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BankReceiptNumberGenerator
{
    /**
     * Next number such as BR-2024-25-000001 (money in) or BP-2024-25-000001 (money out).
     * Must be called inside a transaction: the sequence row is locked until it commits.
     */
    public function next(string $direction, CarbonInterface $date): string
    {
        $prefix = $direction === 'credit'
            ? config('bank.receipts.receipt_prefix')
            : config('bank.receipts.payment_prefix');
        $fiscalYear = $this->fiscalYear($date);

        DB::table('bank_receipt_sequences')->insertOrIgnore([
            'prefix' => $prefix,
            'fiscal_year' => $fiscalYear,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('bank_receipt_sequences')
            ->where('prefix', $prefix)
            ->where('fiscal_year', $fiscalYear)
            ->lockForUpdate()
            ->first();

        $number = $sequence->last_number + 1;

        DB::table('bank_receipt_sequences')->where('id', $sequence->id)->update([
            'last_number' => $number,
            'updated_at' => now(),
        ]);

        return sprintf('%s-%s-%06d', $prefix, $fiscalYear, $number);
    }

    /**
     * Fiscal year label for a date, July to June: 15 Aug 2024 and 10 Mar 2025 are both "2024-25".
     */
    public function fiscalYear(CarbonInterface $date): string
    {
        $startMonth = (int) config('bank.receipts.fiscal_year_start_month', 7);
        $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }
}
