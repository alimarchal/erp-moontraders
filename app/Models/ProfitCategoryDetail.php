<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfitCategoryDetail extends Model
{
    /** @use HasFactory<\Database\Factories\ProfitCategoryDetailFactory> */
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'profit_category_id',
        'supplier_id',
        'transaction_date',
        'description',
        'amount',
        'notes',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function profitCategory(): BelongsTo
    {
        return $this->belongsTo(ProfitCategory::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }
}
