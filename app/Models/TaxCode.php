<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxCode extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tax_code',
        'name',
        'description',
        'tax_type',
        'calculation_method',
        'tax_payable_account_id',
        'tax_receivable_account_id',
        'is_active',
        'is_compound',
        'included_in_price',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_compound' => 'boolean',
        'included_in_price' => 'boolean',
    ];

    /**
     * Get the tax payable account.
     */
    public function taxPayableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'tax_payable_account_id');
    }

    /**
     * Get the tax receivable account.
     */
    public function taxReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'tax_receivable_account_id');
    }

    /**
     * Get the tax rates for this tax code.
     */
    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    /**
     * Get the product tax mappings for this tax code.
     */
    public function productTaxMappings(): HasMany
    {
        return $this->hasMany(ProductTaxMapping::class);
    }

    /**
     * Get the products through tax mappings.
     */
    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            ProductTaxMapping::class,
            'tax_code_id',
            'id',
            'id',
            'product_id'
        );
    }

    /**
     * Get available tax type options.
     *
     * @return array<string, string>
     */
    public static function taxTypeOptions(): array
    {
        return [
            'sales_tax' => 'Sales Tax',
            'gst' => 'GST (Goods & Services Tax)',
            'vat' => 'VAT (Value Added Tax)',
            'withholding_tax' => 'Withholding Tax',
            'excise' => 'Excise Duty',
            'customs_duty' => 'Customs Duty',
        ];
    }

    /**
     * Get available calculation method options.
     *
     * @return array<string, string>
     */
    public static function calculationMethodOptions(): array
    {
        return [
            'percentage' => 'Percentage',
            'fixed_amount' => 'Fixed Amount',
        ];
    }
}
