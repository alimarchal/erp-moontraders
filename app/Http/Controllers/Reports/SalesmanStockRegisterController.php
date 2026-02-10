<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\Supplier;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesmanStockRegisterController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->input('filter', []);
        $date = $filters['date'] ?? Carbon::today()->toDateString();
        $vehicleId = $filters['vehicle_id'] ?? null;
        $salesmanId = $filters['salesman_id'] ?? null;

        // Get Vehicles for dropdown
        $vehicles = Vehicle::orderBy('vehicle_number')->get();
        // Get Salesmen for dropdown - fetch all active employees
        $salesmen = \App\Models\Employee::where('is_active', true)->orderBy('name')->get();

        $suppliers = Supplier::orderBy('supplier_name')->get();

        // Fetch categories for dropdown
        $categories = \App\Models\Category::where('is_active', true)->orderBy('name')->get();

        $allProducts = Product::orderBy('product_name')->select('id', 'product_name', 'product_code')->get();

        $financials = null;

        // Initialize collections
        $products = collect();
        $bf = collect();
        $load = collect();
        $salesQty = collect();
        $returns = collect();

        // PRODUCTS QUERY - Always execute based on filters
        $products = Product::with('category')
            ->when($request->input('filter.search'), function ($query, $search) {
                $query->where('product_name', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%");
            })
            ->when($request->input('filter.supplier_id'), function ($query, $supplierId) {
                $query->where('supplier_id', $supplierId);
            })
            ->when($request->input('filter.category_id'), function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->input('filter.product_id'), function ($query, $productId) {
                $query->where('id', $productId);
            })
            ->orderBy('product_name')
            ->get();

        $productIds = $products->pluck('id');

        // Fetch stock data (Aggregated if no specific Salesman/Vehicle selected)

        // 1. Brought Forward
        $bfQuery = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds);

        if ($vehicleId) {
            $bfQuery->where('vehicle_id', $vehicleId)
                ->whereDate('date', '<', $date);
        } elseif ($salesmanId) {
            // Find vehicles used by this salesman on this date
            $salesmanVehicleInfo = InventoryLedgerEntry::where('employee_id', $salesmanId)
                ->whereDate('date', $date)
                ->whereNotNull('vehicle_id')
                ->select('vehicle_id', DB::raw('MIN(id) as first_transaction_id'))
                ->groupBy('vehicle_id')
                ->get()
                ->keyBy('vehicle_id');

            $salesmanVehicleIds = $salesmanVehicleInfo->keys()->toArray();

            if (!empty($salesmanVehicleIds)) {
                $bfQuery->where(function ($query) use ($date, $salesmanVehicleIds, $salesmanId, $salesmanVehicleInfo) {
                    foreach ($salesmanVehicleIds as $vId) {
                        $firstId = $salesmanVehicleInfo[$vId]->first_transaction_id;

                        $query->orWhere(function ($subQ) use ($date, $vId, $salesmanId, $firstId) {
                            $subQ->where('vehicle_id', $vId)
                                ->where(function ($q) use ($date, $salesmanId, $firstId) {
                                    // Previous days stock for this vehicle
                                    $q->whereDate('date', '<', $date)
                                        // OR Same day stock strictly BEFORE this salesman's first transaction
                                        ->orWhere(function ($q2) use ($date, $salesmanId, $firstId) {
                                        $q2->whereDate('date', $date)
                                            ->where('employee_id', '!=', $salesmanId)
                                            ->where('id', '<', $firstId);
                                    });
                                });
                        });
                    }
                });
            } else {
                // Fallback if no vehicle activity found today: Just show previous personal stock? 
                // Or previous vehicle stock if we knew the vehicle?
                // If no activity, BF is 0 effectively.
                $bfQuery->where('employee_id', $salesmanId)
                    ->whereDate('date', '<', $date)
                    ->whereNotNull('vehicle_id');
            }
        } else {
            // If All, sum of all Vans BF (Previous Date)
            $bfQuery->whereDate('date', '<', $date)
                ->whereNotNull('vehicle_id');
        }

        $bf = $bfQuery->select('product_id', DB::raw('SUM(debit_qty - credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 2. Issue (Transfer In to Van)
        $loadQuery = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('date', $date)
            ->where('transaction_type', 'transfer_in');

        if ($vehicleId) {
            $loadQuery->where('vehicle_id', $vehicleId);
        } elseif ($salesmanId) {
            $loadQuery->where('employee_id', $salesmanId)
                ->whereNotNull('vehicle_id');
        } else {
            // If All, sum all Van Issues
            $loadQuery->whereNotNull('vehicle_id');
        }

        $load = $loadQuery->select('product_id', DB::raw('SUM(debit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // Sales Qty
        $salesQuery = SalesSettlementItem::query()
            ->join('sales_settlements', 'sales_settlements.id', '=', 'sales_settlement_items.sales_settlement_id')
            ->whereDate('sales_settlements.settlement_date', $date)
            ->whereIn('product_id', $productIds);

        if ($vehicleId) {
            $salesQuery->where('sales_settlements.vehicle_id', $vehicleId);
        }
        if ($salesmanId) {
            $salesQuery->where('sales_settlements.employee_id', $salesmanId);
        }

        $salesQty = $salesQuery->select(
            'product_id',
            DB::raw('SUM(quantity_sold) as qty'),
            DB::raw('SUM(total_sales_value) as amount'),
            DB::raw('SUM(quantity_returned) as ret_qty'),
            DB::raw('SUM(quantity_shortage) as short_qty')
        )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // Map data
        $reportData = $products->map(function ($product) use ($bf, $load, $salesQty) {
            $pid = $product->id;
            $bfQty = $bf[$pid] ?? 0;
            $loadQty = $load[$pid] ?? 0;
            $saleData = $salesQty[$pid] ?? null;
            $saleQty = $saleData ? $saleData->qty : 0;
            $amount = $saleData ? $saleData->amount : 0;
            $retQty = $saleData ? $saleData->ret_qty : 0;
            $short = $saleData ? $saleData->short_qty : 0;

            $totalAvail = $bfQty + $loadQty;
            $closing = $totalAvail - $saleQty - $retQty - $short;

            return (object) [
                'id' => $pid,
                'sku' => $product->product_code,
                'product_name' => $product->product_name,
                'category' => $product->category->name ?? '-', // Use Category relationship
                // 'brand' => $product->brand, // Removing brand
                'tp' => $product->unit_sell_price ?? 0, // Trade Price
                'bf' => $bfQty,
                'load' => $loadQty,
                'total' => $totalAvail,
                'sale' => $saleQty,
                'return' => $retQty,
                'short' => $short,
                'amount' => $amount,
                // Helper for filtering
                'has_activity' => ($bfQty != 0 || $loadQty != 0 || $saleQty != 0 || $retQty != 0 || $short != 0)
            ];
        });

        // Filter Logic:
        // If specific Product/Brand/Supplier/Search filter is set, SHOW ALL matches (even if 0 activity).
        // If ONLY Salesman/Vehicle is set (or Date), SHOW ONLY items with activity.
        $hasSpecificFilter = $request->filled('filter.search') ||
            $request->filled('filter.supplier_id') ||
            $request->filled('filter.category_id') ||
            $request->filled('filter.product_id');

        if (!$hasSpecificFilter) {
            $reportData = $reportData->filter(function ($row) {
                return $row->has_activity;
            });
        }

        // Financials (only if vehicle/salesman selected)
        if ($salesQty->isNotEmpty()) {
            // Let's re-fetch Settlement Summary for the header cards
            $settlementQuery = SalesSettlement::whereDate('settlement_date', $date)
                ->with(['expenses', 'recoveries', 'cashDenominations', 'bankTransfers', 'cheques']);

            if ($vehicleId) {
                $settlementQuery->where('vehicle_id', $vehicleId);
            }
            if ($salesmanId) {
                $settlementQuery->where('employee_id', $salesmanId);
            }

            $settlements = $settlementQuery->get();

            if ($settlements->isNotEmpty()) {
                $financials = [
                    'total_sales_amount' => $reportData->sum('amount'), // Calculated from items
                    'settlement_sales_amount' => $settlements->sum('calculated_total_sales_amount') ?? 0,
                    'expenses' => $settlements->sum('calculated_total_expenses') ?? 0,
                    'recovery' => $settlements->flatMap->recoveries->sum('amount') ?? 0,
                    'cash' => $settlements->sum('total_cash_denomination_amount') ?? 0,
                    'bank' => $settlements->sum('total_bank_transfer_amount') ?? 0,
                    'cheque' => $settlements->sum('total_cheque_amount') ?? 0,
                ];
            }
        }

        $selectedVehicleId = $vehicleId;
        $selectedSalesmanId = $salesmanId;
        $selectedSupplierId = $request->input('filter.supplier_id');
        $selectedCategoryId = $request->input('filter.category_id');
        $selectedProductId = $request->input('filter.product_id');

        return view('reports.salesman-stock-register.index', compact(
            'vehicles',
            'salesmen',
            'suppliers',
            'categories',
            'allProducts',
            'date',
            'selectedVehicleId',
            'selectedSalesmanId',
            'selectedSupplierId',
            'selectedCategoryId',
            'selectedProductId',
            'reportData',
            'financials'
        ));
    }
}
