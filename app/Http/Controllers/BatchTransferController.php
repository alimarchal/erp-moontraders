<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBatchTransferRequest;
use App\Models\GoodsReceiptNote;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\BatchTransferService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BatchTransferController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role:super-admin'),
        ];
    }

    public function __construct(private readonly BatchTransferService $batchTransferService) {}

    /**
     * Show the batch transfer form for batches belonging to a specific GRN.
     * Accessible via the "Batch Transfer" button on the GRN show page.
     */
    public function create(GoodsReceiptNote $goodsReceiptNote)
    {
        // Load batches from this GRN that still have stock remaining
        $batches = $this->batchTransferService->getBatchesForGrn($goodsReceiptNote->id);

        // All active products for target selection (excluding the current batch's product)
        $products = Product::where('is_active', true)
            ->orderBy('product_name')
            ->get(['id', 'product_code', 'product_name']);

        return view('batch-transfers.create', [
            'grn' => $goodsReceiptNote,
            'batches' => $batches,
            'products' => $products,
        ]);
    }

    /**
     * Execute the batch transfer inside a DB transaction (handled in service).
     */
    public function store(StoreBatchTransferRequest $request, GoodsReceiptNote $goodsReceiptNote)
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

        return redirect()
            ->route('goods-receipt-notes.show', $goodsReceiptNote)
            ->with('success', "{$type} of {$qty} units completed successfully.{$giNote}");
    }

    /**
     * Show the batch transfer form for batches of a specific product+warehouse.
     * Accessible via the "Batch Transfer" button on the Current Stock by-batch page.
     */
    public function createFromStock(Request $request)
    {
        $productId = (int) $request->get('product_id');
        $warehouseId = (int) $request->get('warehouse_id');

        // Step 1: no context yet — show product+warehouse picker
        if (! $productId || ! $warehouseId) {
            return view('batch-transfers.create-from-stock', [
                'product' => null,
                'warehouse' => null,
                'batches' => collect(),
                'products' => collect(),
                'allProducts' => Product::where('is_active', true)->orderBy('product_name')->get(['id', 'product_code', 'product_name']),
                'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
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
            'allProducts' => collect(),
            'warehouses' => collect(),
        ]);
    }

    /**
     * Execute a batch transfer initiated from the Current Stock page.
     */
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
