<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankEntryRule extends Model
{
    use HasFactory, SoftDeletes;

    public const FIELDS = ['narration' => 'Narration', 'party' => 'Party name', 'reference' => 'Reference'];

    public const MATCH_TYPES = ['contains' => 'Contains', 'starts_with' => 'Starts with', 'equals' => 'Equals'];

    public const DIRECTIONS = ['any' => 'Debit or credit', 'debit' => 'Debit only', 'credit' => 'Credit only'];

    protected $fillable = [
        'name',
        'match_field',
        'match_type',
        'match_value',
        'direction',
        'company_id',
        'bank_entry_category_id',
        'priority',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'company_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BankEntryCategory::class, 'bank_entry_category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function matches(BankStatementEntry $entry): bool
    {
        if ($this->direction !== 'any' && $this->direction !== $entry->direction) {
            return false;
        }

        $haystack = match ($this->match_field) {
            'party' => $entry->party_name_in_statement,
            'reference' => $entry->reference,
            default => $entry->narration,
        };

        $haystack = mb_strtolower((string) $haystack);
        $needle = mb_strtolower($this->match_value);

        if ($needle === '') {
            return false;
        }

        return match ($this->match_type) {
            'equals' => $haystack === $needle,
            'starts_with' => str_starts_with($haystack, $needle),
            default => str_contains($haystack, $needle),
        };
    }
}
