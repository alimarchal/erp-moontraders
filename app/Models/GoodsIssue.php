<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsIssue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'issue_number',
        'issue_date',
        'warehouse_id',
        'vehicle_id',
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
    ];

    protected $casts = [
        'issue_date' => 'date',
        'total_value' => 'decimal:2',
        'posted_at' => 'datetime',
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
}
