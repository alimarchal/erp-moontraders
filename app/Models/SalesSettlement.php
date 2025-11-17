<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesSettlement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'settlement_number',
        'settlement_date',
        'goods_issue_id',
        'employee_id',
        'vehicle_id',
        'warehouse_id',
        'total_quantity_issued',
        'total_value_issued',
        'total_sales_amount',
        'cash_sales_amount',
        'cheque_sales_amount',
        'credit_sales_amount',
        'total_quantity_sold',
        'total_quantity_returned',
        'total_quantity_shortage',
        'cash_collected',
        'cheques_collected',
        'expenses_claimed',
        'cash_to_deposit',
        'status',
        'verified_by',
        'journal_entry_id',
        'notes',
        'posted_at',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'total_quantity_issued' => 'decimal:3',
        'total_value_issued' => 'decimal:2',
        'total_sales_amount' => 'decimal:2',
        'cash_sales_amount' => 'decimal:2',
        'cheque_sales_amount' => 'decimal:2',
        'credit_sales_amount' => 'decimal:2',
        'total_quantity_sold' => 'decimal:3',
        'total_quantity_returned' => 'decimal:3',
        'total_quantity_shortage' => 'decimal:3',
        'cash_collected' => 'decimal:2',
        'cheques_collected' => 'decimal:2',
        'expenses_claimed' => 'decimal:2',
        'cash_to_deposit' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesSettlementItem::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(SalesSettlementSale::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function canBeEdited(): bool
    {
        return $this->status === 'draft';
    }
}
