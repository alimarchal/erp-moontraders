<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyInventorySnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'product_id',
        'warehouse_id',
        'vehicle_id',
        'quantity_on_hand',
        'average_cost',
        'total_value',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity_on_hand' => 'decimal:3',
        'average_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeForVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Get the opening balance for a location on a specific date
     * (which is the closing balance of the previous day)
     */
    public static function getOpeningBalance(int $productId, $date, ?int $warehouseId = null, ?int $vehicleId = null): float
    {
        $previousDate = \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d');

        $query = self::where('product_id', $productId)
            ->where('date', $previousDate);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        } elseif ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        return (float) ($query->value('quantity_on_hand') ?? 0);
    }

    /**
     * Update or create a snapshot for a specific date and location
     */
    public static function recordSnapshot(
        int $productId,
        $date,
        float $quantityOnHand,
        float $averageCost = 0,
        ?int $warehouseId = null,
        ?int $vehicleId = null
    ): self {
        $attributes = [
            'date' => $date,
            'product_id' => $productId,
        ];

        if ($warehouseId) {
            $attributes['warehouse_id'] = $warehouseId;
        } elseif ($vehicleId) {
            $attributes['vehicle_id'] = $vehicleId;
        }

        return self::updateOrCreate($attributes, [
            'quantity_on_hand' => $quantityOnHand,
            'average_cost' => $averageCost,
            'total_value' => $quantityOnHand * $averageCost,
        ]);
    }
}
