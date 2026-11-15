<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * A batch of cheques cleared together, with the notes received when cleared as cash.
 */
class ChequeClearing extends Model
{
    use HasFactory;

    protected $fillable = [
        'cleared_date',
        'cleared_via',
        'bank_account_id',
        'supplier_id',
        'denom_5000',
        'denom_1000',
        'denom_500',
        'denom_100',
        'denom_50',
        'denom_20',
        'denom_10',
        'denom_coins',
        'total_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cleared_date' => 'date',
            'denom_coins' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ChequeClearing $clearing) {
            $clearing->created_by ??= Auth::id();
        });

        static::saving(function (ChequeClearing $clearing) {
            $clearing->total_amount =
                ($clearing->denom_5000 * 5000) +
                ($clearing->denom_1000 * 1000) +
                ($clearing->denom_500 * 500) +
                ($clearing->denom_100 * 100) +
                ($clearing->denom_50 * 50) +
                ($clearing->denom_20 * 20) +
                ($clearing->denom_10 * 10) +
                ($clearing->denom_coins);
        });
    }

    /**
     * @return HasMany<SalesSettlementCheque, $this>
     */
    public function cheques(): HasMany
    {
        return $this->hasMany(SalesSettlementCheque::class, 'cheque_clearing_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
