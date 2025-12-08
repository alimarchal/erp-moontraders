<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name',
        'abbr',
        'country',
        'tax_id',
        'domain',
        'phone_no',
        'email',
        'fax',
        'website',
        'company_logo',
        'company_description',
        'registration_details',
        'date_of_establishment',
        'date_of_incorporation',
        'date_of_commencement',
        'parent_company_id',
        'is_group',
        'lft',
        'rgt',
        'default_currency_id',
        'cost_center_id',
        'default_bank_account_id',
        'default_cash_account_id',
        'default_receivable_account_id',
        'default_payable_account_id',
        'default_expense_account_id',
        'default_income_account_id',
        'write_off_account_id',
        'round_off_account_id',
        'enable_perpetual_inventory',
        'default_inventory_account_id',
        'stock_adjustment_account_id',
        'allow_account_creation_against_child_company',
        'credit_limit',
        'monthly_sales_target',
    ];

    protected $casts = [
        'date_of_establishment' => 'date',
        'date_of_incorporation' => 'date',
        'date_of_commencement' => 'date',
        'is_group' => 'boolean',
        'enable_perpetual_inventory' => 'boolean',
        'allow_account_creation_against_child_company' => 'boolean',
        'credit_limit' => 'decimal:2',
        'monthly_sales_target' => 'decimal:2',
    ];

    // Relationships

    /**
     * Parent company relationship (self-referencing)
     */
    public function parentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_company_id');
    }

    /**
     * Child companies relationship
     */
    public function childCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'parent_company_id');
    }

    /**
     * Default currency relationship
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    /**
     * Default cost center relationship
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    /**
     * Default bank account relationship
     */
    public function defaultBankAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_bank_account_id');
    }

    /**
     * Default cash account relationship
     */
    public function defaultCashAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_cash_account_id');
    }

    /**
     * Default receivable account relationship
     */
    public function defaultReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_receivable_account_id');
    }

    /**
     * Default payable account relationship
     */
    public function defaultPayableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_payable_account_id');
    }

    /**
     * Default expense account relationship
     */
    public function defaultExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_expense_account_id');
    }

    /**
     * Default income account relationship
     */
    public function defaultIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_income_account_id');
    }

    /**
     * Write-off account relationship
     */
    public function writeOffAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'write_off_account_id');
    }

    /**
     * Round-off account relationship
     */
    public function roundOffAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'round_off_account_id');
    }

    /**
     * Default inventory account relationship
     */
    public function defaultInventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_inventory_account_id');
    }

    /**
     * Stock adjustment account relationship
     */
    public function stockAdjustmentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'stock_adjustment_account_id');
    }

    /**
     * Get the warehouses for this company
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
