<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Supported valuation methods for costing.
     *
     * @var string[]
     */
    public const VALUATION_METHODS = ['FIFO', 'LIFO', 'Average', 'Standard'];

    protected $fillable = [
        'product_code',
        'product_name',
        'description',
        'category_id',
        'supplier_id',
        'uom_id',
        'sales_uom_id',
        'uom_conversion_factor',
        'pieces_per_carton',
        'units_per_piece',
        'weight',
        'pack_size',
        'barcode',
        'brand',
        'valuation_method',
        'reorder_level',
        'unit_sell_price',
        'cost_price',
        'expiry_price',
        'is_powder',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'uom_conversion_factor' => 'decimal:3',
        'pieces_per_carton' => 'integer',
        'units_per_piece' => 'integer',
        'reorder_level' => 'decimal:2',
        'unit_sell_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_powder' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Display-only carton hint for a piece quantity, e.g. "1 ctn + 19 pcs".
     * Returns null when the product has no pieces-per-carton configured.
     */
    public function formatCartons(float|int $pieces): ?string
    {
        if (! $this->pieces_per_carton) {
            return null;
        }

        $pieces = (int) round($pieces);

        return intdiv($pieces, $this->pieces_per_carton).' ctn + '.($pieces % $this->pieces_per_carton).' pcs';
    }

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the supplier that owns the product.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Get the base unit of measurement (inventory tracking).
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    /**
     * Get the sales unit of measurement.
     */
    public function salesUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'sales_uom_id');
    }
}
