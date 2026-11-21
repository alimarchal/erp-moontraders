<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A correction posted against a GRN after it was posted: the reason, who made it, and the cost-difference journal.
 */
class GrnCorrection extends Model
{
    protected $fillable = [
        'correction_number',
        'goods_receipt_note_id',
        'reason',
        'journal_entry_id',
        'cogs_delta',
        'recosted_movements',
        'corrected_by',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'cogs_delta' => 'decimal:2',
            'recosted_movements' => 'integer',
            'corrected_at' => 'datetime',
        ];
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GrnCorrectionLine::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
