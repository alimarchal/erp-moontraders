<?php

namespace App\Http\Controllers;

use App\Models\ClaimRegister;
use App\Models\ProductRecall;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ClaimRegisterService;
use App\Services\ProductRecallService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductRecallController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:product-recall-list', only: ['index', 'show']),
            new Middleware('permission:product-recall-create', only: ['create', 'store', 'getBatchesForSupplier']),
            new Middleware('permission:product-recall-edit', only: ['edit', 'update']),
            new Middleware('permission:product-recall-delete', only: ['destroy']),
            new Middleware('permission:product-recall-post', only: ['post']),
            new Middleware('permission:product-recall-cancel', only: ['cancel']),
        ];
    }

    public function index(Request $request)
    {
        $recalls = QueryBuilder::for(
            ProductRecall::query()->with(['supplier', 'warehouse', 'createdBy', 'postedBy'])
        )
            ->allowedFilters([
                AllowedFilter::partial('recall_number'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('recall_type'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('recall_date_from'),
                AllowedFilter::scope('recall_date_to'),
            ])
            ->defaultSort('-recall_date')
            ->paginate(20)
            ->withQueryString();

        return view('product-recalls.index', [
            'recalls' => $recalls,
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
        ]);
    }

    public function create()
    {
        return view('product-recalls.create', [
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(),
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recall_date' => 'required|date|before_or_equal:today',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'grn_id' => 'nullable|exists:goods_receipt_notes,id',
            'recall_type' => 'required|in:supplier_initiated,quality_issue,expiry,other',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.stock_batch_id' => 'required|exists:stock_batches,id',
            'items.*.grn_item_id' => 'nullable|exists:goods_receipt_note_items,id',
            'items.*.quantity_recalled' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        foreach ($validated['items'] as &$item) {
            $item['total_value'] = $item['quantity_recalled'] * $item['unit_cost'];
        }

        $service = app(ProductRecallService::class);
        $result = $service->createRecall($validated);

        if ($result['success']) {
            return redirect()->route('product-recalls.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message'])->withInput();
    }

    public function show(ProductRecall $productRecall)
    {
        $productRecall->load([
            'supplier',
            'warehouse',
            'grn',
            'items.product',
            'items.stockBatch',
            'items.grnItem',
            'stockAdjustment.journalEntry',
            'claimRegister',
            'createdBy',
            'postedBy',
        ]);

        return view('product-recalls.show', compact('productRecall'));
    }

    public function edit(ProductRecall $productRecall)
    {
        if ($productRecall->status !== 'draft') {
            return redirect()->route('product-recalls.show', $productRecall)
                ->with('error', 'Only draft recalls can be edited');
        }

        $productRecall->load('items');

        return view('product-recalls.edit', [
            'recall' => $productRecall,
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(),
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(),
        ]);
    }

    public function update(Request $request, ProductRecall $productRecall)
    {
        if ($productRecall->status !== 'draft') {
            return redirect()->route('product-recalls.show', $productRecall)
                ->with('error', 'Only draft recalls can be updated');
        }

        $validated = $request->validate([
            'recall_date' => 'required|date|before_or_equal:today',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'grn_id' => 'nullable|exists:goods_receipt_notes,id',
            'recall_type' => 'required|in:supplier_initiated,quality_issue,expiry,other',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.stock_batch_id' => 'required|exists:stock_batches,id',
            'items.*.grn_item_id' => 'nullable|exists:goods_receipt_note_items,id',
            'items.*.quantity_recalled' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $totalQty = 0;
        $totalValue = 0;
        foreach ($validated['items'] as &$item) {
            $item['total_value'] = $item['quantity_recalled'] * $item['unit_cost'];
            $totalQty += $item['quantity_recalled'];
            $totalValue += $item['total_value'];
        }

        $validated['total_quantity_recalled'] = $totalQty;
        $validated['total_value'] = $totalValue;

        $productRecall->update($validated);
        $productRecall->items()->delete();

        foreach ($validated['items'] as $item) {
            $productRecall->items()->create($item);
        }

        return redirect()->route('product-recalls.show', $productRecall)
            ->with('success', 'Product recall updated successfully');
    }

    public function destroy(ProductRecall $productRecall)
    {
        if ($productRecall->status !== 'draft') {
            return redirect()->route('product-recalls.index')
                ->with('error', 'Only draft recalls can be deleted');
        }

        $productRecall->delete();

        return redirect()->route('product-recalls.index')
            ->with('success', 'Product recall deleted successfully');
    }

    public function post(Request $request, ProductRecall $productRecall)
    {
        $request->validate([
            'password' => 'required',
        ]);

        if (! Hash::check($request->password, auth()->user()->password)) {
            return redirect()->back()->with('error', 'Invalid password');
        }

        $service = app(ProductRecallService::class);
        $result = $service->postRecall($productRecall);

        if ($result['success']) {
            return redirect()->route('product-recalls.show', $productRecall)
                ->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function cancel(ProductRecall $productRecall)
    {
        if ($productRecall->status !== 'draft') {
            return redirect()->route('product-recalls.show', $productRecall)
                ->with('error', 'Only draft recalls can be cancelled');
        }

        $productRecall->update(['status' => 'cancelled']);

        return redirect()->route('product-recalls.show', $productRecall)
            ->with('success', 'Product recall cancelled successfully');
    }

    public function createClaim(Request $request, ProductRecall $productRecall)
    {
        if ($productRecall->status !== 'posted') {
            return redirect()->back()->with('error', 'Only posted recalls can generate claims');
        }

        if ($productRecall->claim_register_id) {
            return redirect()->back()->with('error', 'Claim already created for this recall');
        }

        try {
            $claim = ClaimRegister::create([
                'claim_date' => now()->toDateString(),
                'supplier_id' => $productRecall->supplier_id,
                'transaction_type' => 'claim',
                'reference_number' => $productRecall->recall_number,
                'grn_id' => $productRecall->grn_id,
                'claim_amount' => $productRecall->total_value,
                'description' => "Product recall - {$productRecall->recall_number}: {$productRecall->reason}",
                'status' => 'pending',
            ]);

            $productRecall->update(['claim_register_id' => $claim->id]);

            return redirect()->route('claim-registers.show', $claim)
                ->with('success', 'Claim created successfully from product recall');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create claim: '.$e->getMessage());
        }
    }

    public function getBatchesForSupplier(Request $request, $supplierId)
    {
        $warehouseId = $request->input('warehouse_id');
        $filters = $request->only(['batch_code', 'expiry_from', 'expiry_to', 'mfg_date', 'product_id']);

        $service = app(ProductRecallService::class);
        $batches = $service->getAvailableBatches($supplierId, $warehouseId, $filters);

        return response()->json($batches);
    }
}
