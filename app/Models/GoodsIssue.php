<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class GoodsIssue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'issue_number',
        'issue_date',
        'warehouse_id',
        'vehicle_id',
        'active_vehicle_lock',
        'employee_id',
        'supplier_id',
        'issued_by',
        'stock_in_hand_account_id',
        'van_stock_account_id',
        'status',
        'total_quantity',
        'total_value',
        'notes',
        'posted_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'replaces_goods_issue_id',
    ];

    /**
     * Boot model events that keep `active_vehicle_lock` aligned with the
     * workflow state. The DB unique index on this column then guarantees, at
     * the row level, that a vehicle can only carry one active GI at a time —
     * closing the race condition window between validation and insert.
     */
    protected static function booted(): void
    {
        static::creating(function (self $goodsIssue) {
            // A new GI is always "active" — it's either draft (about to be
            // posted) or being created in some other in-flight state.
            if ($goodsIssue->active_vehicle_lock === null && in_array($goodsIssue->status, ['draft', 'issued'], true)) {
                $goodsIssue->active_vehicle_lock = $goodsIssue->vehicle_id;
            }
        });

        static::updating(function (self $goodsIssue) {
            // Keep the lock pointed at the current vehicle if a draft GI is
            // re-assigned to a different vehicle (only allowed pre-post).
            if ($goodsIssue->isDirty('vehicle_id') && $goodsIssue->active_vehicle_lock !== null) {
                $goodsIssue->active_vehicle_lock = $goodsIssue->vehicle_id;
            }
        });

        static::deleted(function (self $goodsIssue) {
            // Soft delete — release the vehicle so a replacement GI can be
            // created. Use a direct query to avoid re-triggering events.
            if ($goodsIssue->active_vehicle_lock !== null) {
                static::withTrashed()
                    ->whereKey($goodsIssue->getKey())
                    ->update(['active_vehicle_lock' => null]);
            }
        });
    }

    protected $casts = [
        'issue_date' => 'date',
        'total_value' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsIssueItem::class);
    }

    /**
     * The issue's lines in the order a settlement lists them: by line number, except that a
     * later line of a product already on the issue (an added supplementary line) sits right
     * under that product's first line, so the salesman's count for it is read in one place.
     *
     * @return Collection<int, GoodsIssueItem>
     */
    /**
     * Why a settlement's lines do not settle this issue in full, or null when they do. Every line of the
     * issue must be there exactly once with the quantity it was issued, so a line added with Add More
     * Items after the settlement page was opened cannot be left on the van unsettled.
     *
     * @param  iterable<int, array{goods_issue_item_id?: int|string|null, quantity_issued?: int|float|string|null}>  $settlementLines
     */
    public function settlementLinesMismatch(iterable $settlementLines): ?string
    {
        $listed = collect($settlementLines)->groupBy(fn (array $line) => (int) ($line['goods_issue_item_id'] ?? 0));

        // The settlement form names the goods issue line of every row; rows built without that link
        // (older hand-made data) cannot be matched to lines, so there is nothing to compare.
        if ($listed->keys()->filter()->isEmpty()) {
            return null;
        }

        $this->loadMissing('items.product');
        $problems = [];

        foreach ($this->itemsInSettlementOrder() as $item) {
            $lines = $listed->pull($item->id, collect());
            $issued = (float) $item->quantity_issued;
            $label = 'line '.$item->line_no.' ('.($item->product?->product_name ?? 'product '.$item->product_id).', '.(float) $issued.')';

            if ($lines->isEmpty()) {
                $problems[] = $label.' is missing';
            } elseif ($lines->count() > 1) {
                $problems[] = $label.' is listed '.$lines->count().' times';
            } elseif (abs((float) ($lines->first()['quantity_issued'] ?? 0) - $issued) > 0.0001) {
                $problems[] = $label.' is listed with '.(float) $lines->first()['quantity_issued'].' issued';
            }
        }

        if ($listed->isNotEmpty()) {
            $problems[] = $listed->flatten(1)->count().' line(s) are not on this goods issue';
        }

        return $problems === [] ? null : "The settlement does not match Goods Issue {$this->issue_number}: "
            .implode('; ', $problems).'. The goods issue may have changed after the page was opened: reload it and enter every line.';
    }

    public function itemsInSettlementOrder(): Collection
    {
        $firstLineOfProduct = $this->items->groupBy('product_id')->map(fn (Collection $lines) => $lines->min('line_no'));

        return $this->items
            ->sort(fn (GoodsIssueItem $a, GoodsIssueItem $b) => [$firstLineOfProduct[$a->product_id], $a->line_no, $a->id]
                <=> [$firstLineOfProduct[$b->product_id], $b->line_no, $b->id])
            ->values();
    }

    public function settlement(): HasMany
    {
        return $this->hasMany(SalesSettlement::class);
    }

    public function stockInHandAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_in_hand_account_id');
    }

    public function vanStockAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'van_stock_account_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * The reversed issue this draft was copied from.
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_goods_issue_id');
    }

    /**
     * The draft that was copied from this issue when it was reversed.
     */
    public function replacement(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_goods_issue_id');
    }

    /**
     * Whether a journal entry reference belongs to this issue: its own number, or a
     * supplementary posting on it (-S1, -S2 ...). A LIKE 'GI-…-S%' match alone would also
     * catch free-form references such as 'GI-2026-0001-SALE'.
     */
    public function ownsJournalReference(?string $reference): bool
    {
        return $reference !== null
            && preg_match('/^'.preg_quote($this->issue_number, '/').'(-S\d+)?$/', $reference) === 1;
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    /**
     * Next number in this year's GI-YYYY-NNNN sequence, counting soft-deleted issues
     * so a number is never handed out twice.
     */
    public static function nextIssueNumber(): string
    {
        $prefix = 'GI-'.now()->year.'-';

        $lastIssue = static::withTrashed()
            ->where('issue_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastIssue ? (int) str_replace($prefix, '', $lastIssue->issue_number) + 1 : 1;

        return sprintf('%s%04d', $prefix, $sequence);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function canBeEdited(): bool
    {
        return $this->status === 'draft';
    }

    public function canBePosted(): bool
    {
        return $this->status === 'draft' && $this->items()->count() > 0;
    }

    public function hasFinalizedSettlement(): bool
    {
        return $this->settlement()->whereIn('status', ['verified', 'posted'])->exists();
    }

    public function hasDraftSettlement(): bool
    {
        return $this->settlement()->where('status', 'draft')->exists();
    }

    /**
     * Whether this Goods Issue can accept supplementary items via the append
     * flow. Only `issued` (already-posted) GIs qualify: drafts should be
     * edited through the normal Edit flow instead, otherwise items added via
     * append would conflict with the edit form's duplicate-product guard the
     * next time the draft is opened for editing.
     */
    public function canAcceptSupplementaryItems(): bool
    {
        return $this->status === 'issued'
            && ! $this->hasFinalizedSettlement();
    }

    public function scopeIssueDateFrom($query, $date)
    {
        return $query->where('issue_date', '>=', $date);
    }

    public function scopeIssueDateTo($query, $date)
    {
        return $query->where('issue_date', '<=', $date);
    }
}
