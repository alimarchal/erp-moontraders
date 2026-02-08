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

        // Get Products (optionally filtered by Supplier)
        $productsQuery = Product::query()
            ->where('is_active', true)
            ->with(['supplier', 'uom']);

        if ($supplierId) {
            $productsQuery->where('supplier_id', $supplierId);
        }

        $products = $productsQuery->orderBy('product_name')->get();
        $productIds = $products->pluck('id')->toArray();

        // 1. Warehouse Opening (Before Date)
        $whOpening = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('warehouse_id')
            ->whereDate('transaction_date', '<', $date)
            ->select('product_id', DB::raw('SUM(debit_qty - credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 2. Warehouse Purchase (On Date) - GRN
        // transaction_type for GRN is usually 'purchase' (needs verification of string key)
        // Based on InventoryLedgerService, creates entries with specific types.
        // Let's assume 'purchase' or check DB. 
        // Backfill uses: 'purchase' for GRN.
        $whPurchase = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('warehouse_id')
            ->whereDate('transaction_date', $date)
            ->where('transaction_type', 'purchase')
            ->select('product_id', DB::raw('SUM(debit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 3. Van Brought Forward (Before Date)
        $vanBf = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id') // Van context
            ->whereDate('transaction_date', '<', $date)
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
            ->whereDate('transaction_date', $date)
            ->where('transaction_type', 'transfer_in') // Checking type 'transfer_in' (Van Debit)
            ->select('product_id', DB::raw('SUM(debit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 5. Sales (On Date)
        $vanSales = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id')
            ->whereDate('transaction_date', $date)
            ->where('transaction_type', 'sale')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 6. Returns (Van -> WH) (On Date)
        // Van Credit, Type 'return'
        $vanReturns = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id')
            ->whereDate('transaction_date', $date)
            ->where('transaction_type', 'return')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // 7. Shortage (On Date)
        // Van Credit, Type 'shortage'
        $vanShortage = InventoryLedgerEntry::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('vehicle_id')
            ->whereDate('transaction_date', $date)
            ->where('transaction_type', 'shortage')
            ->select('product_id', DB::raw('SUM(credit_qty) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        // Assemble Data
        $reportData = $products->map(function ($product) use ($whOpening, $whPurchase, $vanBf, $vanIssue, $vanSales, $vanReturns, $vanShortage) {
            $pId = $product->id;

            // Warehouse Side
            $openStock = $whOpening[$pId] ?? 0;
            $purchase = $whPurchase[$pId] ?? 0;
            $totalStock = $openStock + $purchase;

            // Van Side
            $broughtForward = $vanBf[$pId] ?? 0;
            $issue = $vanIssue[$pId] ?? 0;
            $totalIssue = $broughtForward + $issue;

            $sale = $vanSales[$pId] ?? 0;
            $return = $vanReturns[$pId] ?? 0;
            $short = $vanShortage[$pId] ?? 0;

            // Closing (In Hand)
            // Van Closing = Total Issue - (Sale + Return + Short)
            $inHand = $totalIssue - $sale - $return - $short;

            return (object) [
                'id' => $product->id,
                'sku' => $product->product_name, // User called it SKU but meant Product Name
                'code' => $product->product_code,
                'opening_stock' => $openStock, // Column 3
                'total_stock' => $totalStock,   // Column 4
                'brought_forward' => $broughtForward, // Column 5
                'issue' => $issue,             // Column 6
                'total_issue' => $totalIssue,   // Column 7
                'return' => $return,           // Column 8
                'short' => $short,             // Column 9
                'sale' => $sale,               // Column 10
                'in_hand' => $inHand,          // Column 11
            ];
        });

        // Filter out zero rows if needed? User didn't specify, but "Show all supplier products" implies showing even zeros.
        // User said: "supplier ke sare stocks... jo nahi hoga zero show ho". So KEEP ALL.

        return view('reports.daily-stock-register.index', [
            'reportData' => $reportData,
            'date' => $date,
            'suppliers' => Supplier::orderBy('supplier_name')->get(),
            'selectedSupplierId' => $supplierId,
        ]);
    }
}
