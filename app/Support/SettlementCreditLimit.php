<?php

namespace App\Support;

use App\Models\Customer;

/**
 * Checks a settlement's credit sales against each customer's credit limit, when SS_ENFORCE_CREDIT_LIMIT is on.
 */
class SettlementCreditLimit
{
    public static function enforced(): bool
    {
        return (bool) config('sales_settlement.enforce_credit_limit');
    }

    /**
     * One sentence per customer this settlement would take past their credit limit; empty when none (or when off).
     *
     * @param  iterable<int, array{customer_id?: int|string|null, sale_amount?: int|float|string|null}>|null  $creditSales
     * @return array<int, string>
     */
    public static function problems(?iterable $creditSales): array
    {
        if (! self::enforced() || $creditSales === null) {
            return [];
        }

        $adding = collect($creditSales)
            ->filter(fn ($line) => is_array($line) && ! empty($line['customer_id']))
            ->groupBy(fn (array $line) => (int) $line['customer_id'])
            ->map(fn ($lines) => (float) $lines->sum(fn (array $line) => (float) ($line['sale_amount'] ?? 0)));

        if ($adding->isEmpty()) {
            return [];
        }

        $money = fn (float $value) => 'Rs '.number_format($value, 2);

        return Customer::whereIn('id', $adding->keys())->get()
            ->filter(fn (Customer $customer) => (float) $customer->credit_limit > 0)
            ->map(function (Customer $customer) use ($adding, $money) {
                $owed = (float) $customer->receivable_balance;
                $added = $adding[$customer->id];
                $over = $owed + $added - (float) $customer->credit_limit;

                return $over > 0.005
                    ? "{$customer->customer_name}: credit limit {$money((float) $customer->credit_limit)}, already owed {$money($owed)}, this settlement adds {$money($added)} ({$money($over)} over)."
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
