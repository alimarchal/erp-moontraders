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
        'credit_recoveries',
        'total_quantity_sold',
        'total_quantity_returned',
        'total_quantity_shortage',
        'cash_collected',
        'cheques_collected',
        'expenses_claimed',
        'gross_profit',
        'total_cogs',
        'cash_to_deposit',
        // Cash denominations
        'denom_5000',
        'denom_1000',
        'denom_500',
        'denom_100',
        'denom_50',
        'denom_20',
        'denom_10',
        'denom_coins',
        // Note: Individual expense fields removed - now in sales_settlement_expenses table
        // Note: bank_transfers JSON removed - now in sales_settlement_bank_transfers table
        // Note: cheque_details JSON removed - now in sales_settlement_cheques table
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
        'credit_recoveries' => 'decimal:2',
        'total_quantity_sold' => 'decimal:3',
        'total_quantity_returned' => 'decimal:3',
        'total_quantity_shortage' => 'decimal:3',
        'cash_collected' => 'decimal:2',
        'cheques_collected' => 'decimal:2',
        'expenses_claimed' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'total_cogs' => 'decimal:2',
        'cash_to_deposit' => 'decimal:2',
        'denom_coins' => 'decimal:2',
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

    public function creditSales(): HasMany
    {
        return $this->hasMany(CreditSale::class);
    }

    public function advanceTaxes(): HasMany
    {
        return $this->hasMany(SalesSettlementAdvanceTax::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(SalesSettlementExpense::class);
    }

    public function bankTransfers(): HasMany
    {
        return $this->hasMany(SalesSettlementBankTransfer::class);
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(SalesSettlementCheque::class);
    }

    /**
     * Get total bank transfer amount (calculated from relationship)
     */
    public function getTotalBankTransferAmountAttribute(): float
    {
        return (float) $this->bankTransfers()->sum('amount');
    }

    /**
     * Get total cheque amount (calculated from relationship)
     */
    public function getTotalChequeAmountAttribute(): float
    {
        return (float) $this->cheques()->sum('amount');
    }

    /**
     * Get cheque count (calculated from relationship)
     */
    public function getChequeCountAttribute(): int
    {
        return $this->cheques()->count();
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
