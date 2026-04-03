<?php

namespace App\Models;

use App\Traits\UserTracking;
use Database\Factories\InvestmentOpeningBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentOpeningBalance extends Model
{
    /** @use HasFactory<InvestmentOpeningBalanceFactory> */
    use HasFactory, UserTracking;

    protected $fillable = [
        'supplier_id',
        'date',
        'description',
        'amount',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
