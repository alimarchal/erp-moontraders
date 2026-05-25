<?php

namespace App\Models;

use App\Traits\UserTracking;
use Database\Factories\RevenueDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RevenueDetail extends Model
{
    /** @use HasFactory<RevenueDetailFactory> */
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'revenue_category_id',
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

    public function revenueCategory(): BelongsTo
    {
        return $this->belongsTo(RevenueCategory::class);
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
