<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductRecall extends Model
{
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'recall_number',
        'recall_date',
        'supplier_id',
        'warehouse_id',
        'grn_id',
        'recall_type',
        'status',
        'total_quantity_recalled',
        'total_value',
        'reason',
        'supplier_notification_sent_at',
        'claim_register_id',
        'stock_adjustment_id',
        'posted_by',
        'posted_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'recall_date' => 'date',
            'total_quantity_recalled' => 'decimal:3',
            'total_value' => 'decimal:2',
            'supplier_notification_sent_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'grn_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductRecallItem::class);
    }

    public function claimRegister(): BelongsTo
    {
        return $this->belongsTo(ClaimRegister::class);
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
