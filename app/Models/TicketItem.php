<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketItem extends Model
{
    /** Product columns a price-update ticket can change, keyed by the product attribute. */
    public const PRICE_FIELDS = [
        'unit_sell_price' => 'Selling Price',
        'cost_price' => 'Cost Price',
        'expiry_price' => 'Expiry Price',
        'reorder_level' => 'Reorder Level',
    ];

    protected $fillable = [
        'ticket_id',
        'product_id',
        'apply_to_all_batches',
        'batch_ids',
        'old_unit_sell_price',
        'new_unit_sell_price',
        'old_cost_price',
        'new_cost_price',
        'old_expiry_price',
        'new_expiry_price',
        'old_reorder_level',
        'new_reorder_level',
        'old_is_active',
        'new_is_active',
        'payload',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'apply_to_all_batches' => 'boolean',
            'batch_ids' => 'array',
            'payload' => 'array',
            'old_unit_sell_price' => 'decimal:2',
            'new_unit_sell_price' => 'decimal:2',
            'old_cost_price' => 'decimal:2',
            'new_cost_price' => 'decimal:2',
            'old_expiry_price' => 'decimal:2',
            'new_expiry_price' => 'decimal:2',
            'old_reorder_level' => 'decimal:2',
            'new_reorder_level' => 'decimal:2',
            'old_is_active' => 'boolean',
            'new_is_active' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Requested changes as [label, old, new, difference] rows.
     *
     * @return array<int, array{field: string, label: string, old: float|null, new: float, difference: float}>
     */
    public function priceChanges(): array
    {
        $changes = [];

        foreach (self::PRICE_FIELDS as $field => $label) {
            $new = $this->{'new_'.$field};
            if ($new === null) {
                continue;
            }

            $old = $this->{'old_'.$field};
            $changes[] = [
                'field' => $field,
                'label' => $label,
                'old' => $old === null ? null : (float) $old,
                'new' => (float) $new,
                'difference' => (float) $new - (float) ($old ?? 0),
            ];
        }

        return $changes;
    }
}
