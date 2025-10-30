<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    /** @use HasFactory<\Database\Factories\JournalEntryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'currency_id',
        'accounting_period_id',
        'fx_rate_to_base',
        'entry_date',
        'description',
        'reference',
        'status',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'fx_rate_to_base' => 'decimal:6',
    ];

    /**
     * Get the detail lines for the journal entry.
     */
    public function details(): HasMany
    {
        return $this->hasMany(JournalEntryDetail::class);
    }

    /**
     * Get the currency for this journal entry.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the attachments for this journal entry.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Get the accounting period for this journal entry.
     */
    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    /**
     * Get the user who posted this entry.
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
