<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesSettlement extends Model
{
    use HasFactory, SoftDeletes;
    use UserTracking;

    protected $fillable = [
        'settlement_number',
        'settlement_date',
        'goods_issue_id',
        'employee_id',
        'vehicle_id',
        'warehouse_id',
        'supplier_id',
        'total_quantity_issued',
        'total_value_issued',
        'total_sales_amount',
        'cash_sales_amount',
        'cheque_sales_amount',
        'bank_transfer_amount',
        'bank_slips_amount',
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
        // Note: Individual expense fields removed - now in sales_settlement_expenses table
        // Note: Cash denomination fields removed - now in sales_settlement_cash_denominations table
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
        'bank_transfer_amount' => 'decimal:2',
        'bank_slips_amount' => 'decimal:2',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(SalesSettlementCreditSale::class);
    }

    public function advanceTaxes(): HasMany
    {
        return $this->hasMany(SalesSettlementAdvanceTax::class);
    }

    public function amrPowders(): HasMany
    {
        return $this->hasMany(SalesSettlementAmrPowder::class);
    }

    public function amrLiquids(): HasMany
    {
        return $this->hasMany(SalesSettlementAmrLiquid::class);
    }

    public function percentageExpenses(): HasMany
    {
        return $this->hasMany(SalesSettlementPercentageExpense::class);
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

    public function cashDenominations(): HasMany
    {
        return $this->hasMany(SalesSettlementCashDenomination::class);
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(SalesSettlementRecovery::class);
    }

    public function customerEmployeeTransactions(): HasMany
    {
        return $this->hasMany(CustomerEmployeeAccountTransaction::class);
    }

    public function bankSlips(): HasMany
    {
        return $this->hasMany(SalesSettlementBankSlip::class);
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

    /**
     * Get total cash denomination amount (calculated from relationship)
     */
    public function getTotalCashDenominationAmountAttribute(): float
    {
        $denominations = $this->cashDenominations->first();

        return $denominations ? $denominations->total_amount : 0.00;
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

    public function scopeSettlementDateFrom($query, $date)
    {
        return $query->where('settlement_date', '>=', $date);
    }

    public function scopeSettlementDateTo($query, $date)
    {
        return $query->where('settlement_date', '<=', $date);
    }

    /**
     * Get calculated total expenses from loaded sums (or relationships if not loaded)
     */
    public function getCalculatedTotalExpensesAttribute(): float
    {
        // Try to use loaded sums first (fastest for index page)
        $expensesSum = $this->getAttribute('expenses_sum_amount');
        $advanceTaxSum = $this->getAttribute('advance_taxes_sum_tax_amount');
        $amrPowderSum = $this->getAttribute('amr_powders_sum_amount');
        $amrLiquidSum = $this->getAttribute('amr_liquids_sum_amount');
        $percentageExpenseSum = $this->getAttribute('percentage_expenses_sum_amount');

        // If any sum is null (not loaded), we might want to lazy load or return 0 if we expect them to be loaded.
        // For robustness, if they are not set, we fall back to relationship aggregation (slower, but correct)
        // However, standard eloquent 'withSum' returns null if no rows, so we treat null as 0.
        // If the key doesn't exist at all in attributes, we should load it.

        $hasLoadedSums = array_key_exists('expenses_sum_amount', $this->getAttributes());

        if ($hasLoadedSums) {
            return (float) ($expensesSum ?? 0);
        }

        // Fallback: Calculate from relationships (N+1 risk if used in loop without eager load, but safe)
        return (float) $this->expenses()->sum('amount');
    }

    public function getCalculatedTotalSalesAmountAttribute(): float
    {
        if (array_key_exists('items_sum_total_sales_value', $this->getAttributes())) {
            return (float) ($this->getAttribute('items_sum_total_sales_value') ?? 0);
        }

        return (float) $this->items()->sum('total_sales_value');
    }

    public function getCalculatedTotalCogsAttribute(): float
    {
        if (array_key_exists('items_sum_total_cogs', $this->getAttributes())) {
            return (float) ($this->getAttribute('items_sum_total_cogs') ?? 0);
        }

        return (float) $this->items()->sum('total_cogs');
    }

    public function getCalculatedNetProfitAttribute(): float
    {
        // Gross Profit = Sales - COGS
        $grossProfit = $this->calculated_total_sales_amount - $this->calculated_total_cogs;

        // Net Profit = Gross Profit - Expenses
        return $grossProfit - $this->calculated_total_expenses;
    }
}
