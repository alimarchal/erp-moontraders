<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBatchTransferRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\BatchTransferService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class BatchTransferController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role:super-admin'),
        ];
    }

    public function __construct(private readonly BatchTransferService $batchTransferService) {}

    public function createFromStock(Request $request)
    {
        $productId = (int) $request->get('product_id');
        $warehouseId = (int) $request->get('warehouse_id');

        // Step 1: no context yet — show supplier + product + warehouse picker
        if (! $productId || ! $warehouseId) {
            $supplierWarehouseMap = DB::table('stock_batches as sb')
                ->join('current_stock_by_batch as csb', 'csb.stock_batch_id', '=', 'sb.id')
                ->where('csb.quantity_on_hand', '>', 0)
                ->whereNotNull('sb.supplier_id')
                ->select('sb.supplier_id', 'csb.warehouse_id')
                ->distinct()
                ->get()
                ->groupBy('supplier_id')
                ->map(fn ($g) => $g->pluck('warehouse_id')->unique()->values());

            return view('batch-transfers.create-from-stock', [
                'product' => null,
                'warehouse' => null,
                'batches' => collect(),
                'products' => collect(),
                'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
                'allProducts' => Product::where('is_active', true)->orderBy('product_name')->get(['id', 'product_code', 'product_name']),
                'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
                'supplierWarehouseMap' => $supplierWarehouseMap,
            ]);
        }

        // Step 2: context provided — show full transfer form
        $product = Product::findOrFail($productId);
        $warehouse = Warehouse::findOrFail($warehouseId);

        $batches = $this->batchTransferService->getBatchesForProductWarehouse($productId, $warehouseId);

        $products = Product::where('is_active', true)
            ->orderBy('product_name')
            ->get(['id', 'product_code', 'product_name']);

        return view('batch-transfers.create-from-stock', [
            'product' => $product,
            'warehouse' => $warehouse,
            'batches' => $batches,
            'products' => $products,
            'suppliers' => collect(),
            'allProducts' => collect(),
            'warehouses' => collect(),
            'supplierWarehouseMap' => collect(),
        ]);
    }

    public function storeFromStock(StoreBatchTransferRequest $request)
    {
        $result = $this->batchTransferService->transfer(
            stockBatchId: (int) $request->input('stock_batch_id'),
            targetProductId: (int) $request->input('target_product_id'),
            quantity: (float) $request->input('quantity'),
            reason: $request->input('reason'),
        );

        if (! $result['success']) {
            return back()
                ->withInput()
                ->with('error', 'Transfer failed: '.$result['message']);
        }

        $data = $result['data'];
        $type = $data['type'] === 'full' ? 'Full batch transfer' : 'Partial transfer';
        $qty = number_format($data['quantity_transferred'], 2);
        $giNote = $data['draft_gi_items_updated'] > 0
            ? " ({$data['draft_gi_items_updated']} draft GI item(s) updated)"
            : '';

        $warehouseId = (int) $request->input('warehouse_id');
        $targetProductId = (int) $request->input('target_product_id');

        return redirect()
            ->route('inventory.current-stock.by-batch', [
                'product_id' => $targetProductId,
                'warehouse_id' => $warehouseId,
            ])
            ->with('success', "{$type} of {$qty} units completed successfully.{$giNote}");
    }
}
