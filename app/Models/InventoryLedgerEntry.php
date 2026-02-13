<?php

namespace App\Models;

use App\Traits\UserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLedgerEntry extends Model
{
    use HasFactory, UserTracking;

    /**
     * Transaction types for inventory movements
     */
    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_ISSUE = 'issue';

    public const TYPE_SALE = 'sale';

    public const TYPE_RETURN = 'return';

    public const TYPE_SHORTAGE = 'shortage';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    protected $fillable = [
        'date',
        'transaction_type',
        'product_id',
        'stock_batch_id',
        'warehouse_id',
        'vehicle_id',
        'employee_id',
        'goods_receipt_note_id',
        'goods_issue_id',
        'sales_settlement_id',
        'debit_qty',
        'credit_qty',
        'unit_cost',
        'selling_price',
        'total_value',
        'running_balance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'debit_qty' => 'decimal:2',
            'credit_qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'total_value' => 'decimal:2',
            'running_balance' => 'decimal:2',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function salesSettlement(): BelongsTo
    {
        return $this->belongsTo(SalesSettlement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the net quantity change (debit - credit)
     */
    public function getNetQuantityAttribute(): float
    {
        return (float) $this->debit_qty - (float) $this->credit_qty;
    }

    /**
     * Check if this is an inward movement (debit > 0)
     */
    public function getIsInwardAttribute(): bool
    {
        return (float) $this->debit_qty > 0;
    }

    /**
     * Check if this is an outward movement (credit > 0)
     */
    public function getIsOutwardAttribute(): bool
    {
        return (float) $this->credit_qty > 0;
    }

    /**
     * Get a human-readable location
     */
    public function getLocationAttribute(): string
    {
        if ($this->warehouse_id) {
            return $this->warehouse?->warehouse_name ?? 'Warehouse #'.$this->warehouse_id;
        }
        if ($this->vehicle_id) {
            return $this->vehicle?->registration_number ?? 'Van #'.$this->vehicle_id;
        }

        return 'Unknown';
    }

    // ==================== SCOPES ====================

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForBatch($query, int $batchId)
    {
        return $query->where('stock_batch_id', $batchId);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeForVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeInward($query)
    {
        return $query->where('debit_qty', '>', 0);
    }

    public function scopeOutward($query)
    {
        return $query->where('credit_qty', '>', 0);
    }

    // ==================== STATIC HELPER METHODS ====================

    /**
     * Calculate running balance for a product at a specific location
     */
    public static function calculateRunningBalance(
        int $productId,
        ?int $warehouseId = null,
        ?int $vehicleId = null,
        ?string $upToDate = null
    ): float {
        $query = static::where('product_id', $productId);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }
        if ($upToDate) {
            $query->where('date', '<=', $upToDate);
        }

        return (float) $query->selectRaw('COALESCE(SUM(debit_qty), 0) - COALESCE(SUM(credit_qty), 0) as balance')
            ->value('balance');
    }

    /**
     * Get opening balance for a product at a location before a date
     */
    public static function getOpeningBalance(
        int $productId,
        ?int $warehouseId = null,
        ?int $vehicleId = null,
        ?string $beforeDate = null
    ): float {
        $query = static::where('product_id', $productId);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }
        if ($beforeDate) {
            $query->where('date', '<', $beforeDate);
        }

        return (float) $query->selectRaw('COALESCE(SUM(debit_qty), 0) - COALESCE(SUM(credit_qty), 0) as balance')
            ->value('balance');
    }
}
