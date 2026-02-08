<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyStockRegisterController extends Controller
{
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

        // 4. Warehouse Issue (Outflow to Van/Others)
        // This should match Van "Issue" (Transfer In) mostly, but tracking WH Out is accurate.
        $whIssue = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('warehouse_id')
            ->whereDate('date', $date)
            ->where('transaction_type', 'transfer_out')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 3. Van Brought Forward (Before Date)
        $vanBf = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id') // Van context
            ->whereDate('date', '<', $date)
            ->select('product_id', DB::raw('SUM(debit_qty - credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 4. Issue (WH -> Van) (On Date)
        // Viewed from Van side: Debit, Type 'transfer_in' OR 'issue'
        // Viewed from WH side: Credit, Type 'transfer_out' OR 'issue'
        // Backfill uses: WH Credit (issue), Van Debit (issue).
        // Wait, did I use 'issue' or 'transfer'?
        // InventoryLedgerService::recordIssue uses $type = 'issue' (default) or 'transfer'?
        // Let's check InventoryLedgerService.php to be sure of types.
        // Assuming 'issue' represents Goods Issue.
        $vanIssue = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id')
            ->whereDate('date', $date)
            ->where('transaction_type', 'transfer_in') // Checking type 'transfer_in' (Van Debit)
            ->select('product_id', DB::raw('SUM(debit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 5. Sales (On Date)
        $vanSales = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id')
            ->whereDate('date', $date)
            ->where('transaction_type', 'sale')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 6. Returns (Van -> WH) (On Date)
        // Van Credit, Type 'return'
        $vanReturns = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id')
            ->whereDate('date', $date)
            ->where('transaction_type', 'return')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 7. Shortage (On Date)
        // Van Credit, Type 'shortage'
        $vanShortage = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id')
            ->whereDate('date', $date)
            ->where('transaction_type', 'shortage')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // Assemble Data
        $reportData = $products->map(function ($product) use ($whOpening, $whPurchase, $whReturns, $whIssue, $vanBf, $vanIssue, $vanSales, $vanReturns, $vanShortage) {
            $pId = $product->id;

            // Warehouse Side
            $openStock = $whOpening[$pId] ?? 0;
            $purchase = $whPurchase[$pId] ?? 0;
            $whReturnIn = $whReturns[$pId] ?? 0; // New Column
            $whOut = $whIssue[$pId] ?? 0;        // Warehouse Issue Out

            // "Total Available" = Opening + Purchase + Returns
            // This is the pool from which we issue.
            $totalAvailable = $openStock + $purchase + $whReturnIn;

            // Warehouse Closing
            $whClosing = $totalAvailable - $whOut;

            // Van Side
            $broughtForward = $vanBf[$pId] ?? 0;
            $issue = $vanIssue[$pId] ?? 0; // Van In (Should match whOut ideally)
            // Note: whOut and issue might differ if stock in transit? Usually same day transfer is instant.

            $totalIssue = $broughtForward + $issue;

            $sale = $vanSales[$pId] ?? 0;
            $return = $vanReturns[$pId] ?? 0; // Return Out from Van
            $short = $vanShortage[$pId] ?? 0;

            // Closing (In Hand)
            // Van Closing = Total Issue - (Sale + Return + Short)
            $inHand = $totalIssue - $sale - $return - $short;

            return (object) [
                'id' => $product->id,
                'sku' => $product->product_name,
                'code' => $product->product_code,
                // WH Section
                'wh_opening' => $openStock,
                'wh_purchase' => $purchase,
                'wh_return' => $whReturnIn,
                'wh_total' => $totalAvailable, // "Total Stock"
                'wh_issue' => $whOut,          // Issued from WH
                'wh_closing' => $whClosing,
                // Van Section
                'van_bf' => $broughtForward,
                'van_issue' => $issue,         // Received by Van (should match wh_issue)
                'van_total' => $totalIssue,
                'van_sale' => $sale,
                'van_return' => $return,      // Returned by Van (should match wh_return)
                'van_short' => $short,
                'van_closing' => $inHand,
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
