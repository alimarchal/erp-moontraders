<?php

namespace App\Models;

use Database\Factories\BankEntryCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankEntryCategory extends Model
{
    /** @use HasFactory<BankEntryCategoryFactory> */
    use HasFactory, SoftDeletes;

    public const TYPES = ['receipt' => 'Receipt', 'payment' => 'Payment', 'both' => 'Receipt & Payment'];

    protected $fillable = [
        'name',
        'slug',
        'type',
        'default_chart_of_account_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_chart_of_account_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(BankStatementEntry::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Categories that can be used for a money-in (credit) or money-out (debit) entry.
     */
    public function scopeForDirection(Builder $query, string $direction): Builder
    {
        return $query->whereIn('type', ['both', $direction === 'credit' ? 'receipt' : 'payment']);
    }
}
