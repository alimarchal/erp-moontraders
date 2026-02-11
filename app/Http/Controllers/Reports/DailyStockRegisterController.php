<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class DailyStockRegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-view-inventory'),
        ];
    }

    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id');
        $productId = $request->input('product_id');

        // Products for Dropdown (Filtered by Supplier if selected)
        $dropdownProductsQuery = Product::query()
            ->where('is_active', true)
            ->orderBy('product_name');

        if ($supplierId) {
            $dropdownProductsQuery->where('supplier_id', $supplierId);
        }
        $dropdownProducts = $dropdownProductsQuery->get();

        // Products for Report (Filtered by Supplier AND Product if selected)
        $productsQuery = Product::query()
            ->where('is_active', true)
            ->with(['supplier', 'uom']);

        if ($supplierId) {
            $productsQuery->where('supplier_id', $supplierId);
        }

        if ($productId) {
            $productsQuery->where('id', $productId);
        }

        $products = $productsQuery->orderBy('product_name')->get();
        $productIds = $products->pluck('id')->toArray();

        // 1. Warehouse Opening (Before Date)
        $whOpening = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('warehouse_id')
            ->whereDate('date', '<', $date)
            ->select('product_id', DB::raw('SUM(debit_qty - credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 2. Warehouse Purchase (On Date) - GRN
        // "Purchase" column strictly for GRN.
        $whPurchase = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('warehouse_id')
            ->whereDate('date', $date)
            ->where('transaction_type', 'purchase')
            ->select('product_id', DB::raw('SUM(debit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 3. Warehouse Returns (Inflow from Van)
        $whReturns = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('warehouse_id')
            ->whereDate('date', $date)
            ->where('transaction_type', 'return') // Returns to WH
            ->select('product_id', DB::raw('SUM(debit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 3. Van Brought Forward (Before Date)
        $vanBf = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id')
            ->whereDate('date', '<', $date)
            ->select('product_id', DB::raw('SUM(debit_qty - credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 4. Van Issue (Transfer In from WH)
        $vanIssue = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('date', $date)
            ->where('transaction_type', 'transfer_in')
            ->whereNotNull('vehicle_id')
            ->select('product_id', DB::raw('SUM(debit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 5. Sales (Global)
        $sales = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('date', $date)
            ->where('transaction_type', 'sale')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 6. Returns (Global - usually Van to WH)
        $returns = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('date', $date)
            ->where('transaction_type', 'return')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 7. Shortage (Global)
        $shortage = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('date', $date)
            ->where('transaction_type', 'shortage')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // Assemble Data
        $reportData = $products->map(function ($product) use ($whOpening, $whPurchase, $vanBf, $vanIssue, $sales, $returns, $shortage) {
            $pId = $product->id;

            // 1. Opening
            $open = $whOpening[$pId] ?? 0;
            // 2. Purchase
            $purch = $whPurchase[$pId] ?? 0;

            // 3. Brought Forward (Van)
            $bf = $vanBf[$pId] ?? 0;

            // 4. Issue (Van In)
            $issue = $vanIssue[$pId] ?? 0;

            // 5. Total Issue
            $totalIssue = $bf + $issue;

            // 6. Returns
            $ret = $returns[$pId] ?? 0;

            // 7. Sales
            $sale = $sales[$pId] ?? 0;

            // 8. Shortage
            $short = $shortage[$pId] ?? 0;

            // 9. In Hand (Closing) -> System Stock (WH + Van)
            // Logic: Opening (WH) + Purchase (WH) + BF (Van) - Sales (Van) - Shortage (Van).
            // This represents the total physical stock available in the company (Warhouse + Van).
            // Note: 'Issue' and 'Return' are internal transfers and do not change the System Stock total.
            $inHand = $open + $purch + $bf - $sale - $short;

            return (object) [
                'id' => $product->id,
                'sku' => $product->product_name,
                'code' => $product->product_code,
                'opening' => $open,
                'purchase' => $purch,
                'bf' => $bf,
                'issue' => $issue,
                'total_issue' => $totalIssue,
                'return' => $ret,
                'sale' => $sale,
                'shortage' => $short,
                'in_hand' => $inHand,
            ];
        });

        // Filter out zero rows if needed? User didn't specify, but "Show all supplier products" implies showing even zeros.
        // User said: "supplier ke sare stocks... jo nahi hoga zero show ho". So KEEP ALL.

        return view('reports.daily-stock-register.index', [
            'reportData' => $reportData,
            'date' => $date,
            'suppliers' => Supplier::orderBy('supplier_name')->get(),
            'products' => $dropdownProducts, // For the filter dropdown
            'selectedSupplierId' => $supplierId,
            'selectedProductId' => $productId,
        ]);
    }
}
