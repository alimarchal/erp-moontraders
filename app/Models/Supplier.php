<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_name',
        'short_name',
        'country',
        'supplier_group',
        'supplier_type',
        'is_transporter',
        'is_internal_supplier',
        'is_fmr_allowed',
        'disabled',
        'default_currency_id',
        'default_bank_account_id',
        'default_price_list',
        'supplier_details',
        'website',
        'print_language',
        'supplier_primary_address',
        'supplier_primary_contact',
        'tax_id',
        'sales_tax',
        'pan_number',
        'ledger_opening_balance',
        'ledger_opening_balance_date',
    ];

    protected $casts = [
        'is_transporter' => 'boolean',
        'is_internal_supplier' => 'boolean',
        'is_fmr_allowed' => 'boolean',
        'disabled' => 'boolean',
        'ledger_opening_balance' => 'decimal:2',
        'ledger_opening_balance_date' => 'date',
    ];

    /**
     * Get the default currency for the supplier
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    /**
     * Get the default bank account for the supplier
     */
    public function defaultBankAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_bank_account_id');
    }

    /**
     * Get all employees associated with this supplier
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get all products associated with this supplier
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
