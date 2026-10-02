<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ages what a customer account still owes, the way receivables are aged:
 * recoveries settle the oldest credit sales first, so the balance left is made
 * of the newest credit sales, and each part is aged by its own sale date.
 *
 * A customer who cleared everything in June and took Rs 253,600 of new credit
 * on 30 September is therefore 2 days old, not "123 days since last payment".
 */
class CustomerCreditAging
{
    /** @var array<int, string> */
    public const LABELS = ['0-30 days', '31-60 days', '61-90 days', 'Over 90 days'];

    public static function bucketFor(int $days): int
    {
        return match (true) {
            $days <= 30 => 0,
            $days <= 60 => 1,
            $days <= 90 => 2,
            default => 3,
        };
    }

    /**
     * Adds to every account row (needs `account_id` and `balance`):
     *  - buckets: amount owed in each age band [0-30, 31-60, 61-90, 90+]
     *  - oldest_unpaid: date of the oldest credit sale not yet paid off
     *  - days / bucket: age of that oldest unpaid sale (the account's worst band)
     *
     * @param  Collection<int, object>  $accounts
     * @return Collection<int, object>
     */
    public function age(Collection $accounts, ?string $asOfDate = null): Collection
    {
        $asOf = Carbon::parse($asOfDate ?? now()->toDateString())->startOfDay();
        $owing = $accounts->filter(fn ($row) => (float) $row->balance > 0);
        $sales = $this->creditSales($owing->pluck('account_id')->all(), $asOf->toDateString());

        return $accounts->map(function ($row) use ($sales, $asOf) {
            $row->buckets = [0.0, 0.0, 0.0, 0.0];
            $row->oldest_unpaid = null;
            $row->days = 0;
            $row->bucket = 0;

            $left = round((float) $row->balance, 2);
            if ($left <= 0) {
                return $row;
            }

            // Newest sales first: they are what the balance is still made of.
            foreach ($sales->get($row->account_id, collect()) as $sale) {
                if ($left <= 0.004) {
                    break;
                }
                $part = min((float) $sale->debit, $left);
                $left = round($left - $part, 2);
                $days = (int) abs(Carbon::parse($sale->transaction_date)->startOfDay()->diffInDays($asOf));
                $row->buckets[self::bucketFor($days)] += $part;
                $row->oldest_unpaid = $sale->transaction_date;
                $row->days = $days;
            }

            // Balance not explained by any credit sale on record (should not happen): count it as oldest.
            if ($left > 0.004) {
                $row->buckets[3] += $left;
                $row->days = max($row->days, 91);
            }

            $row->buckets = array_map(fn ($amount) => round($amount, 2), $row->buckets);
            $row->bucket = self::bucketFor($row->days);

            return $row;
        });
    }

    /**
     * Credit sales (debits) of the accounts up to the date, newest first, keyed by account.
     *
     * A settlement revert offsets each ledger row with a reversal linked through
     * `reverses_transaction_id`. Both rows of a pair are left out: together they add
     * nothing to the balance, and keeping either would misplace it -- a reversed
     * recovery would look like fresh credit, and a reversed sale would stay "unpaid"
     * while its reversal paid off some other, older sale.
     *
     * @param  array<int, int>  $accountIds
     * @return Collection<int, Collection<int, object>>
     */
    private function creditSales(array $accountIds, string $asOfDate): Collection
    {
        return collect($accountIds)
            ->chunk(1000)
            ->flatMap(fn (Collection $ids) => DB::table('customer_employee_account_transactions')
                ->whereIn('customer_employee_account_id', $ids->all())
                ->whereNull('deleted_at')
                ->where(fn ($query) => $query->where('debit', '>', 0)->orWhereNotNull('reverses_transaction_id'))
                ->whereDate('transaction_date', '<=', $asOfDate)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->get(['id', 'customer_employee_account_id', 'transaction_date', 'debit', 'reverses_transaction_id']))
            ->groupBy('customer_employee_account_id')
            ->map(function (Collection $rows) {
                $reversed = $rows->pluck('reverses_transaction_id')->filter()->flip();

                return $rows
                    ->filter(fn ($row) => $row->reverses_transaction_id === null && ! $reversed->has($row->id) && (float) $row->debit > 0)
                    ->values();
            });
    }
}
