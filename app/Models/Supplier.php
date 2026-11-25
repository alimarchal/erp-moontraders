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
        'is_advance_tax_income',
        'qty_entry_mode',
        'grn_line_columns',
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
        'is_advance_tax_income' => 'boolean',
        'disabled' => 'boolean',
        'ledger_opening_balance' => 'decimal:2',
        'ledger_opening_balance_date' => 'date',
        'grn_line_columns' => 'array',
    ];

    /**
     * The GRN line inputs whose order a supplier can set, in the default order (the order of the GRN
     * line popup), with their labels. Layout only: the order never changes any calculation.
     *
     * @var array<string, string>
     */
    public const GRN_LINE_COLUMNS = [
        'unit_price_per_case' => 'Price / case',
        'discount_value' => 'Discount',
        'fmr_allowance' => 'FMR allowance',
        'sales_tax_value' => 'Sales tax',
        'excise_duty' => 'Excise duty',
        'advance_income_tax' => 'Advance income tax',
        'other_charges' => 'Other charges',
        'withholding_tax' => 'Withholding tax',
    ];

    /**
     * The GRN line popup's sections. A column only moves inside its own section.
     *
     * @var array<string, array{label: string, keys: list<string>}>
     */
    public const GRN_LINE_COLUMN_GROUPS = [
        'price' => ['label' => 'Price & discount', 'keys' => ['unit_price_per_case', 'discount_value', 'fmr_allowance']],
        'taxes' => ['label' => 'Taxes & charges', 'keys' => ['sales_tax_value', 'excise_duty', 'advance_income_tax', 'other_charges', 'withholding_tax']],
    ];

    public function usesCartonEntry(): bool
    {
        return $this->qty_entry_mode === 'carton';
    }

    /**
     * This supplier's GRN line inputs in their order: the saved order first (unknown keys dropped),
     * then any column the saved order lacks, in the default order.
     *
     * @return list<array{key: string, label: string, group: string}>
     */
    public function grnLineColumns(): array
    {
        return self::resolveGrnLineColumns($this->grn_line_columns);
    }

    /**
     * The columns grouped by section (Price & discount first, then Taxes & charges), each section
     * keeping the saved order of its own columns.
     *
     * @param  mixed  $saved  The stored list, old form input, or anything else (treated as no order saved).
     * @return list<array{key: string, label: string, group: string}>
     */
    public static function resolveGrnLineColumns(mixed $saved): array
    {
        $ordered = [];

        foreach (is_array($saved) ? $saved : [] as $entry) {
            $key = is_array($entry) ? ($entry['key'] ?? null) : $entry;

            if (is_string($key) && isset(self::GRN_LINE_COLUMNS[$key]) && ! in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        $ordered = array_merge($ordered, array_diff(array_keys(self::GRN_LINE_COLUMNS), $ordered));
        $columns = [];

        foreach (self::GRN_LINE_COLUMN_GROUPS as $group => $section) {
            foreach ($ordered as $key) {
                if (in_array($key, $section['keys'], true)) {
                    $columns[] = ['key' => $key, 'label' => self::GRN_LINE_COLUMNS[$key], 'group' => $group];
                }
            }
        }

        return $columns;
    }

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
