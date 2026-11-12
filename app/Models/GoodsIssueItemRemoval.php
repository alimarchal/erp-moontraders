<?php

namespace App\Models;

use Database\Factories\GoodsIssueItemRemovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One removal of a quantity from an issued goods issue line, before settlement: what came back,
 * from which batches, at what cost, why, and the journal it posted.
 */
class GoodsIssueItemRemoval extends Model
{
    /** @use HasFactory<GoodsIssueItemRemovalFactory> */
    use HasFactory;

    protected $fillable = [
        'goods_issue_id',
        'goods_issue_item_id',
        'quantity',
        'cost_value',
        'selling_value',
        'reason',
        'journal_entry_id',
        'batches',
        'removed_by',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'cost_value' => 'decimal:2',
            'selling_value' => 'decimal:2',
            'batches' => 'array',
            'removed_at' => 'datetime',
        ];
    }

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GoodsIssueItem::class, 'goods_issue_item_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }
}
