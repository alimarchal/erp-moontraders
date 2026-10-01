<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;

class VanStockBatchReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-inventory-van-stock-batch'),
        ];
    }

    public function index(Request $request)
    {
        $vehicleId = $request->input('filter.vehicle_id');
        $productId = $request->input('filter.product_id');
        $expiryStatus = $request->input('filter.expiry_status');

        $query = StockMovement::query()
            ->select('vehicle_id', 'product_id', 'stock_batch_id')
            ->selectRaw("SUM(
                    CASE
                        WHEN movement_type = 'transfer' AND reference_type = ? THEN -quantity
                        WHEN movement_type = 'sale' THEN quantity
                        WHEN movement_type = 'return' THEN -quantity
                        WHEN movement_type = 'shortage' THEN quantity
                        ELSE 0
                    END
                ) as quantity_on_hand", ['App\\Models\\GoodsIssue'])
            ->whereNotNull('vehicle_id')
            ->groupBy('vehicle_id', 'product_id', 'stock_batch_id')
            ->havingRaw("SUM(
                    CASE
                        WHEN movement_type = 'transfer' AND reference_type = ? THEN -quantity
                        WHEN movement_type = 'sale' THEN quantity
                        WHEN movement_type = 'return' THEN -quantity
                        WHEN movement_type = 'shortage' THEN quantity
                        ELSE 0
                    END
                ) <> 0", ['App\\Models\\GoodsIssue']);

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $stocks = $this->netBatchAttribution(
            $query->with(['vehicle', 'product', 'stockBatch'])->get()
        );

        // Apply expiry status filter after fetching (since expiry_date is on stockBatch)
        if ($expiryStatus) {
            $stocks = $stocks->filter(function ($stock) use ($expiryStatus) {
                $expiryDate = $stock->stockBatch->expiry_date ?? null;

                if (! $expiryDate) {
                    return $expiryStatus === 'valid'; // Items without expiry are considered valid
                }

                return match ($expiryStatus) {
                    'expired' => $expiryDate->isPast(),
                    'expiring_soon' => ! $expiryDate->isPast() && $expiryDate->diffInDays(now()) < 30,
                    'valid' => $expiryDate->isFuture() && $expiryDate->diffInDays(now()) >= 30,
                    default => true,
                };
            });
        }

        // Group by vehicle
        $stocks = $stocks->groupBy('vehicle_id');

        $totals = [
            'total_quantity' => 0,
            'total_value_cost' => 0,
            'total_value_selling' => 0,
        ];

        foreach ($stocks as $vehicleStocks) {
            foreach ($vehicleStocks as $stock) {
                $batch = $stock->stockBatch;
                $sellingPrice = $batch->is_promotional ? ($batch->promotional_selling_price ?? $batch->selling_price) : $batch->selling_price;

                $totals['total_quantity'] += $stock->quantity_on_hand;
                $totals['total_value_cost'] += $stock->quantity_on_hand * ($batch->unit_cost ?? 0);
                $totals['total_value_selling'] += $stock->quantity_on_hand * ($sellingPrice ?? 0);

                // Attach calculated prices to the stock object for the view
                $stock->calculated_selling_price = $sellingPrice;
                $stock->calculated_unit_cost = $batch->unit_cost ?? 0;
            }
        }

        $vehicles = Vehicle::orderBy('vehicle_number')->get();
        $products = Product::orderBy('product_name')->get();

        return view('reports.van-stock-batch.index', [
            'stocks' => $stocks,
            'vehicles' => $vehicles,
            'products' => $products,
            'totals' => $totals,
            'selectedVehicle' => $vehicleId,
            'selectedProduct' => $productId,
        ]);
    }

    /**
     * Stock is issued to a van batch by batch, but a settlement can sell it from a slightly
     * different split (an issue of 767.06 + 663.94 settled as 767 + 664), which leaves one batch
     * a little over and its neighbour the same amount under. Showing only the batches that are
     * over would invent stock that is not on the van, so what a batch is under is taken off the
     * oldest batches of the same vehicle and product first, and anything below one hundredth of a
     * unit (what a two-decimal column can hold) is dropped.
     *
     * @param  Collection<int, StockMovement>  $stocks  one row per vehicle, product and batch
     * @return Collection<int, StockMovement>
     */
    private function netBatchAttribution(Collection $stocks): Collection
    {
        return $stocks
            ->groupBy(fn (StockMovement $stock): string => $stock->vehicle_id.'-'.$stock->product_id)
            ->flatMap(function (Collection $batches): Collection {
                $shortBy = abs((float) $batches->where('quantity_on_hand', '<', 0)->sum('quantity_on_hand'));

                return $batches
                    ->where('quantity_on_hand', '>', 0)
                    ->sortBy('stock_batch_id')
                    ->each(function (StockMovement $stock) use (&$shortBy): void {
                        $absorbed = min($shortBy, (float) $stock->quantity_on_hand);
                        $stock->setAttribute('quantity_on_hand', round((float) $stock->quantity_on_hand - $absorbed, 3));
                        $shortBy -= $absorbed;
                    });
            })
            ->filter(fn (StockMovement $stock): bool => (float) $stock->quantity_on_hand >= 0.005)
            ->values();
    }
}
