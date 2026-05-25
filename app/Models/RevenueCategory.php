<?php

namespace App\Models;

use Database\Factories\RevenueCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RevenueCategory extends Model
{
    /** @use HasFactory<RevenueCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function revenueDetails(): HasMany
    {
        return $this->hasMany(RevenueDetail::class);
    }
}
