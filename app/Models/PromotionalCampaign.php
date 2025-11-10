<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionalCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campaign_code',
        'campaign_name',
        'description',
        'start_date',
        'end_date',
        'discount_type',
        'discount_value',
        'minimum_quantity',
        'maximum_discount_amount',
        'is_active',
        'is_auto_apply',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'discount_value' => 'decimal:2',
        'minimum_quantity' => 'decimal:2',
        'maximum_discount_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_auto_apply' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActiveNow(): bool
    {
        return $this->is_active
            && $this->start_date <= now()
            && $this->end_date >= now();
    }
}
