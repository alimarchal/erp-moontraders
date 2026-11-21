<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class DailyStockRegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-inventory-daily-stock-register'),
        ];
    }

    public function index(Request $request): View
    {
        // A plain Y-m-d: the ledger's date column is a DATE, so it is compared as-is (no DATE() wrapper),
        // which lets MySQL use the (date, product_id) index instead of scanning every sale / return row.
        $request->validate(['date' => ['nullable', 'date']]);
        $date = Carbon::parse($request->input('date') ?: now())->toDateString();
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

        $viewData = [
            'date' => $date,
            'suppliers' => Supplier::orderBy('supplier_name')->get(),
            'products' => $dropdownProducts, // For the filter dropdown
            'selectedSupplierId' => $supplierId,
            'selectedProductId' => $productId,
            'showAdjustment' => $request->input('show_adjustment', '1') !== '0',
            'needsSupplier' => ! $supplierId && ! $productId,
        ];

        // Every company's products in one register is heavy and hard to read: nothing is worked out
        // until a supplier (or a single product) is picked.
        if ($viewData['needsSupplier']) {
            return view('reports.daily-stock-register.index', ['reportData' => collect()] + $viewData);
        }

        // Products for Report (Filtered by Supplier AND Product if selected), inactive ones included here and
        // dropped below unless they hold stock or move on the date.
        $productsQuery = Product::query()->with(['supplier', 'uom']);

        if ($supplierId) {
            $productsQuery->where('supplier_id', $supplierId);
        }

        if ($productId) {
            $productsQuery->where('id', $productId);
        }

        $products = $productsQuery->orderBy('product_name')->get();

        // Every column comes from ONE pass over the ledger up to the date (conditional sums), instead of a
        // query per column: a large supplier has most of the ledger's rows, and each separate query read them
        // all again. It leaves out settlement postings a revert took back (and their reversals), so a
        // settlement reverted and posted again counts its sales once.
        //   wh_opening  warehouse stock before the date        van_bf     van stock before the date
        //   wh_purchase GRN into a warehouse on the date        van_issue  transfers into a van on the date
        //   sale / ret / shortage  out of stock on the date     adjustment count differences on the date
        //   in_hand_value  Opening + Purchase + BF + Adjustment - Sales - Shortage at ledger unit cost; a cost
        //                  revaluation moves no quantity, so its row carries the value it moved in total_value
        //   net_qty / moved_today  decide whether an inactive product still belongs in the register
        $ledger = InventoryLedgerEntry::query()
            ->withoutRevertedSettlementPostings()
            ->whereIn('product_id', $products->pluck('id'))
            ->where('date', '<=', $date)
            ->select('product_id')
            ->selectRaw("
                SUM(CASE WHEN date < ? AND warehouse_id IS NOT NULL THEN debit_qty - credit_qty ELSE 0 END) AS wh_opening,
                SUM(CASE WHEN date = ? AND warehouse_id IS NOT NULL AND transaction_type = 'purchase' THEN debit_qty ELSE 0 END) AS wh_purchase,
                SUM(CASE WHEN date < ? AND vehicle_id IS NOT NULL THEN debit_qty - credit_qty ELSE 0 END) AS van_bf,
                SUM(CASE WHEN date = ? AND vehicle_id IS NOT NULL AND transaction_type = 'transfer_in' THEN debit_qty ELSE 0 END) AS van_issue,
                SUM(CASE WHEN date = ? AND transaction_type = 'sale' THEN credit_qty ELSE 0 END) AS sale,
                SUM(CASE WHEN date = ? AND transaction_type = 'return' THEN credit_qty ELSE 0 END) AS ret,
                SUM(CASE WHEN date = ? AND transaction_type = 'shortage' THEN credit_qty ELSE 0 END) AS shortage,
                SUM(CASE WHEN date = ? AND transaction_type = 'adjustment' THEN debit_qty - credit_qty ELSE 0 END) AS adjustment,
                SUM(CASE
                    WHEN date < ? AND (warehouse_id IS NOT NULL OR vehicle_id IS NOT NULL) THEN (debit_qty - credit_qty) * unit_cost
                        + (CASE WHEN debit_qty = 0 AND credit_qty = 0 THEN total_value ELSE 0 END)
                    WHEN date = ? AND transaction_type = 'purchase' AND warehouse_id IS NOT NULL THEN debit_qty * unit_cost
                    WHEN date = ? AND transaction_type IN ('sale', 'shortage') THEN -credit_qty * unit_cost
                    WHEN date = ? AND transaction_type = 'adjustment' AND (warehouse_id IS NOT NULL OR vehicle_id IS NOT NULL) THEN (debit_qty - credit_qty) * unit_cost
                        + (CASE WHEN debit_qty = 0 AND credit_qty = 0 THEN total_value ELSE 0 END)
                    ELSE 0 END) AS in_hand_value,
                SUM(debit_qty - credit_qty) AS net_qty,
                SUM(CASE WHEN date = ? THEN 1 ELSE 0 END) AS moved_today
            ", array_fill(0, 13, $date))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // Active products always show. An inactive (or deactivated-later) product still shows while it holds
        // stock or moves on the date, otherwise its stock is missing from the register and the totals.
        $products = $products->filter(function (Product $product) use ($ledger) {
            $row = $ledger->get($product->id);

            return $product->is_active || ($row && (abs((float) $row->net_qty) > 0.0001 || (int) $row->moved_today > 0));
        });

        $reportData = $products->map(function (Product $product) use ($ledger) {
            $row = $ledger->get($product->id);
            $sum = fn (string $column): float => (float) ($row?->{$column} ?? 0);

            $open = $sum('wh_opening');
            $purch = $sum('wh_purchase');
            $bf = $sum('van_bf');
            $issue = $sum('van_issue');
            $sale = $sum('sale');
            $short = $sum('shortage');
            $adj = $sum('adjustment');

            return (object) [
                'id' => $product->id,
                'sku' => $product->product_name.($product->is_active ? '' : ' (inactive)'),
                'code' => $product->product_code,
                'opening' => $open,
                'purchase' => $purch,
                'bf' => $bf,
                'issue' => $issue,
                'total_issue' => $bf + $issue,
                'return' => $sum('ret'),
                'sale' => $sale,
                'shortage' => $short,
                'adjustment' => $adj,
                // In Hand (closing) is the company's whole stock, warehouse + vans: Issue and Return are
                // transfers between the two and do not change it; the day's adjustments do.
                'in_hand' => $open + $purch + $bf + $adj - $sale - $short,
                'in_hand_value' => $sum('in_hand_value'),
            ];
        })->values();

        return view('reports.daily-stock-register.index', ['reportData' => $reportData] + $viewData);
    }
}
