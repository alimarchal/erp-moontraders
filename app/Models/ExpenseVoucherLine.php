<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One expense line of a voucher's shop bill (toner, a repair, tea), booked to its own expense account. The
 * voucher's amount is always the sum of its lines.
 */
class ExpenseVoucherLine extends Model
{
    /** A voucher holds at most this many lines. */
    public const MAX_LINES = 30;

    protected $fillable = [
        'expense_voucher_id',
        'line_no',
        'expense_category_id',
        'expense_account_id',
        'vehicle_id',
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(ExpenseVoucher::class, 'expense_voucher_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Gives every voucher that has no line yet (deleted ones too) one line made of its own account, category,
     * vehicle, narration and amount. Safe to run again: vouchers that already have lines are skipped. Written
     * with the query builder in chunks, so the migration does not depend on the models and copes with a big table.
     *
     * @return int The number of lines added.
     */
    public static function backfillMissing(int $chunkSize = 500): int
    {
        $added = 0;

        DB::table('expense_vouchers as v')
            ->whereNotNull('v.expense_account_id')
            ->whereNotExists(fn (Builder $query) => $query->select(DB::raw(1))->from('expense_voucher_lines as l')->whereColumn('l.expense_voucher_id', 'v.id'))
            ->select(['v.id', 'v.expense_category_id', 'v.expense_account_id', 'v.vehicle_id', 'v.narration', 'v.amount'])
            ->chunkById($chunkSize, function ($vouchers) use (&$added) {
                $now = now();

                $rows = $vouchers->map(fn ($voucher) => [
                    'expense_voucher_id' => $voucher->id,
                    'line_no' => 1,
                    'expense_category_id' => $voucher->expense_category_id,
                    'expense_account_id' => $voucher->expense_account_id,
                    'vehicle_id' => $voucher->vehicle_id,
                    'description' => $voucher->narration !== null ? mb_substr($voucher->narration, 0, 255) : null,
                    'amount' => $voucher->amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('expense_voucher_lines')->insert($rows);
                $added += count($rows);
            }, 'v.id', 'id');

        return $added;
    }
}
