<?php

namespace App\Models;

use App\Traits\UserTracking;
use Database\Factories\InvoiceSummaryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceSummary extends Model
{
    /** @use HasFactory<InvoiceSummaryFactory> */
    use HasFactory, SoftDeletes, UserTracking;

    protected $fillable = [
        'supplier_id',
        'invoice_date',
        'invoice_number',
        'cartons',
        'invoice_value',
        'za_on_invoices',
        'discount_value',
        'fmr_allowance',
        'discount_before_sales_tax',
        'excise_duty',
        'sales_tax_value',
        'advance_tax',
        'total_value_with_tax',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'cartons' => 'integer',
            'invoice_value' => 'decimal:2',
            'za_on_invoices' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'fmr_allowance' => 'decimal:2',
            'discount_before_sales_tax' => 'decimal:2',
            'excise_duty' => 'decimal:2',
            'sales_tax_value' => 'decimal:2',
            'advance_tax' => 'decimal:2',
            'total_value_with_tax' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($q) => $q->where('invoice_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('invoice_date', '<=', $to));
    }
}
