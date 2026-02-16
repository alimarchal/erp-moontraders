<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock-adjustment-list', only: ['index', 'show']),
            new Middleware('permission:stock-adjustment-create', only: ['create', 'store']),
            new Middleware('permission:stock-adjustment-edit', only: ['edit', 'update']),
            new Middleware('permission:stock-adjustment-delete', only: ['destroy']),
            new Middleware('permission:stock-adjustment-post', only: ['post']),
        ];
    }

    public function index(Request $request)
    {
        $adjustments = QueryBuilder::for(
            StockAdjustment::query()->with(['warehouse', 'createdBy', 'postedBy'])
        )
            ->allowedFilters([
                AllowedFilter::partial('adjustment_number'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('adjustment_type'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('adjustment_date_from'),
                AllowedFilter::scope('adjustment_date_to'),
            ])
            ->defaultSort('-adjustment_date')
            ->paginate(20)
            ->withQueryString();

        return view('stock-adjustments.index', [
            'adjustments' => $adjustments,
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
        ]);
    }

    public function create()
    {
        return view('stock-adjustments.create', [
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(),
            'products' => Product::where('is_active', true)->with('uom:id,uom_name')->orderBy('product_name')->get(),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'adjustment_date' => 'required|date|before_or_equal:today',
            'warehouse_id' => 'required|exists:warehouses,id',
            'adjustment_type' => 'required|in:damage,theft,count_variance,expiry,recall,other',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.stock_batch_id' => 'required|exists:stock_batches,id',
            'items.*.system_quantity' => 'required|numeric|min:0',
            'items.*.actual_quantity' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.uom_id' => 'required|exists:uoms,id',
        ]);

        foreach ($validated['items'] as &$item) {
            $item['adjustment_quantity'] = $item['actual_quantity'] - $item['system_quantity'];
            $item['adjustment_value'] = $item['adjustment_quantity'] * $item['unit_cost'];
        }

        $service = app(StockAdjustmentService::class);
        $result = $service->createAdjustment($validated);

        if ($result['success']) {
            return redirect()->route('stock-adjustments.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message'])->withInput();
    }

    public function show(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->load(['warehouse', 'items.product', 'items.stockBatch', 'items.uom', 'journalEntry', 'createdBy', 'postedBy']);

        return view('stock-adjustments.show', compact('stockAdjustment'));
    }

    public function edit(StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->status !== 'draft') {
            return redirect()->route('stock-adjustments.show', $stockAdjustment)
                ->with('error', 'Only draft adjustments can be edited');
        }

        $stockAdjustment->load('items.product', 'items.stockBatch', 'items.uom');

        return view('stock-adjustments.edit', [
            'adjustment' => $stockAdjustment,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(),
            'products' => Product::where('is_active', true)->with('uom:id,uom_name')->orderBy('product_name')->get(),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(),
        ]);
    }

    public function update(Request $request, StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->status !== 'draft') {
            return redirect()->route('stock-adjustments.show', $stockAdjustment)
                ->with('error', 'Only draft adjustments can be updated');
        }

        $validated = $request->validate([
            'adjustment_date' => 'required|date|before_or_equal:today',
            'warehouse_id' => 'required|exists:warehouses,id',
            'adjustment_type' => 'required|in:damage,theft,count_variance,expiry,recall,other',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.stock_batch_id' => 'required|exists:stock_batches,id',
            'items.*.system_quantity' => 'required|numeric|min:0',
            'items.*.actual_quantity' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.uom_id' => 'required|exists:uoms,id',
        ]);

        foreach ($validated['items'] as &$item) {
            $item['adjustment_quantity'] = $item['actual_quantity'] - $item['system_quantity'];
            $item['adjustment_value'] = $item['adjustment_quantity'] * $item['unit_cost'];
        }

        $stockAdjustment->update($validated);
        $stockAdjustment->items()->delete();

        foreach ($validated['items'] as $item) {
            $stockAdjustment->items()->create($item);
        }

        return redirect()->route('stock-adjustments.show', $stockAdjustment)
            ->with('success', 'Stock adjustment updated successfully');
    }

    public function destroy(StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->status !== 'draft') {
            return redirect()->route('stock-adjustments.index')
                ->with('error', 'Only draft adjustments can be deleted');
        }

        $stockAdjustment->delete();

        return redirect()->route('stock-adjustments.index')
            ->with('success', 'Stock adjustment deleted successfully');
    }

    public function post(Request $request, StockAdjustment $stockAdjustment)
    {
        $request->validate([
            'password' => 'required',
        ]);

        if (! Hash::check($request->password, auth()->user()->password)) {
            return redirect()->back()->with('error', 'Invalid password');
        }

        $service = app(StockAdjustmentService::class);
        $result = $service->postAdjustment($stockAdjustment);

        if ($result['success']) {
            return redirect()->route('stock-adjustments.show', $stockAdjustment)
                ->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function getBatchesForProduct(Request $request, $productId, $warehouseId)
    {
        $batches = StockBatch::where('product_id', $productId)
            ->where('status', 'active')
            ->with(['currentStockByBatch' => function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)->where('quantity_on_hand', '>', 0);
            }])
            ->get()
            ->filter(function ($batch) {
                return $batch->currentStockByBatch->isNotEmpty() &&
                       $batch->currentStockByBatch->sum('quantity_on_hand') > 0;
            })
            ->values();

        return response()->json($batches);
    }
}
