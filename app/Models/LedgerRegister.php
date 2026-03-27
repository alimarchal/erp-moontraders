<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerRegister extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    protected $table = 'ledger_registers';

    protected $fillable = [
        'supplier_id',
        'transaction_date',
        'document_type',
        'document_number',
        'sap_code',
        'online_amount',
        'invoice_amount',
        'expenses_amount',
        'za_point_five_percent_amount',
        'claim_adjust_amount',
        'advance_tax_amount',
        'balance',
        'remarks',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'document_type' => DocumentType::class,
            'online_amount' => 'decimal:2',
            'invoice_amount' => 'decimal:2',
            'expenses_amount' => 'decimal:2',
            'za_point_five_percent_amount' => 'decimal:2',
            'claim_adjust_amount' => 'decimal:2',
            'advance_tax_amount' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('transaction_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('transaction_date', '<=', $to);
        }

        return $query;
    }

    /**
     * Recalculate running balances for all entries of a given supplier.
     */
    public static function recalculateBalances(int $supplierId): void
    {
        $entries = static::where('supplier_id', $supplierId)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $runningBalance = 0;

        foreach ($entries as $entry) {
            $runningBalance = $runningBalance
                + (float) $entry->online_amount
                - (float) $entry->invoice_amount
                - (float) $entry->expenses_amount
                + (float) $entry->za_point_five_percent_amount
                + (float) $entry->claim_adjust_amount;

            if ((float) $entry->balance !== round($runningBalance, 2)) {
                $entry->updateQuietly(['balance' => round($runningBalance, 2)]);
            }
        }
    }
}
