<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfitCategory extends Model
{
    /** @use HasFactory<\Database\Factories\ProfitCategoryFactory> */
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

    public function profitCategoryDetails(): HasMany
    {
        return $this->hasMany(ProfitCategoryDetail::class);
    }
}
