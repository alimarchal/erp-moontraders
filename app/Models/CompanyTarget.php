<?php

namespace App\Models;

use Database\Factories\CompanyTargetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyTarget extends Model
{
    /** @use HasFactory<CompanyTargetFactory> */
    use HasFactory, HasUuids;

    /** The Target column default, also used when a salesman row is added blank. */
    public const DEFAULT_LABEL = 'Corporate';

    /** Lowest Total the form accepts. The database column itself allows negatives. */
    public const MINIMUM_AMOUNT = 0;

    protected $fillable = [
        'supplier_id',
        'period_start',
        'period_end',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * Only the uuid is generated; the numeric id stays the primary key for relations.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CompanyTargetLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Target sheets of one supplier whose period overlaps the given dates.
     *
     * @param  Builder<CompanyTarget>  $query
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): void
    {
        $query->whereDate('period_start', '<=', $to)->whereDate('period_end', '>=', $from);
    }
}
