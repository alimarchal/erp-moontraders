<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

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
