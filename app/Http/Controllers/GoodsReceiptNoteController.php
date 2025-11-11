<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceiptNote;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Uom;
use App\Models\PromotionalCampaign;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class GoodsReceiptNoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $grns = QueryBuilder::for(
            GoodsReceiptNote::query()->with(['supplier', 'warehouse', 'receivedBy'])
        )
            ->allowedFilters([
                AllowedFilter::partial('grn_number'),
                AllowedFilter::partial('supplier_invoice_number'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('receipt_date_from'),
                AllowedFilter::scope('receipt_date_to'),
                AllowedFilter::scope('payment_status'),
            ])
            ->defaultSort('-receipt_date')
            ->paginate(20)
            ->withQueryString();

        return view('goods-receipt-notes.index', [
            'grns' => $grns,
            'suppliers' => Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']),
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('goods-receipt-notes.create', [
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'products' => Product::where('is_active', true)->orderBy('product_name')->get(['id', 'product_code', 'product_name', 'unit_price', 'supplier_id']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
            'campaigns' => PromotionalCampaign::where('is_active', true)
                ->whereDate('end_date', '>=', now())
                ->orderBy('campaign_name')
                ->get(['id', 'campaign_code', 'campaign_name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'receipt_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'supplier_invoice_date' => 'nullable|date',
            'tax_amount' => 'nullable|numeric|min:0',
            'freight_charges' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',

            // Line items
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.uom_id' => 'required|exists:uoms,id',
            'items.*.quantity_received' => 'required|numeric|min:0.01',
            'items.*.quantity_accepted' => 'required|numeric|min:0',
            'items.*.quantity_rejected' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.promotional_campaign_id' => 'nullable|exists:promotional_campaigns,id',
            'items.*.is_promotional' => 'nullable|boolean',
            'items.*.promotional_price' => 'nullable|numeric|min:0',
            'items.*.promotional_discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.priority_order' => 'nullable|integer|min:1|max:99',
            'items.*.must_sell_before' => 'nullable|date',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.manufacturing_date' => 'nullable|date',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Generate GRN number
            $grn_number = $this->generateGRNNumber();

            // Calculate totals
            $total_quantity = 0;
            $total_amount = 0;

            foreach ($validated['items'] as $item) {
                $qty = $item['quantity_accepted'] ?? $item['quantity_received'];
                $total_quantity += $qty;
                $total_amount += $qty * $item['unit_cost'];
            }

            $grand_total = $total_amount + ($validated['tax_amount'] ?? 0)
                + ($validated['freight_charges'] ?? 0) + ($validated['other_charges'] ?? 0);

            // Create GRN
            $grn = GoodsReceiptNote::create([
                'grn_number' => $grn_number,
                'receipt_date' => $validated['receipt_date'],
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'supplier_invoice_date' => $validated['supplier_invoice_date'] ?? null,
                'total_quantity' => $total_quantity,
                'total_amount' => $total_amount,
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'freight_charges' => $validated['freight_charges'] ?? 0,
                'other_charges' => $validated['other_charges'] ?? 0,
                'grand_total' => $grand_total,
                'status' => 'draft',
                'received_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create line items
            foreach ($validated['items'] as $index => $item) {
                $qty_accepted = $item['quantity_accepted'] ?? $item['quantity_received'];
                $qty_rejected = $item['quantity_rejected'] ?? 0;

                $grn->items()->create([
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'uom_id' => $item['uom_id'],
                    'quantity_received' => $item['quantity_received'],
                    'quantity_accepted' => $qty_accepted,
                    'quantity_rejected' => $qty_rejected,
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $qty_accepted * $item['unit_cost'],
                    'selling_price' => $item['selling_price'] ?? null,
                    'promotional_campaign_id' => $item['promotional_campaign_id'] ?? null,
                    'is_promotional' => !empty($item['promotional_campaign_id']),
                    'promotional_price' => $item['promotional_price'] ?? null,
                    'promotional_discount_percent' => $item['promotional_discount_percent'] ?? null,
                    'priority_order' => $item['priority_order'] ?? 99,
                    'must_sell_before' => $item['must_sell_before'] ?? null,
                    'batch_number' => $item['batch_number'] ?? null,
                    'manufacturing_date' => $item['manufacturing_date'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'quality_status' => 'approved',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('goods-receipt-notes.show', $grn)
                ->with('success', "GRN '{$grn->grn_number}' created successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating GRN', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to create GRN: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(GoodsReceiptNote $goodsReceiptNote)
    {
        $goodsReceiptNote->load(['supplier', 'warehouse', 'receivedBy', 'verifiedBy', 'items.product', 'items.uom', 'items.promotionalCampaign']);

        return view('goods-receipt-notes.show', [
            'grn' => $goodsReceiptNote,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GoodsReceiptNote $goodsReceiptNote)
    {
        if ($goodsReceiptNote->status !== 'draft') {
            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote)
                ->with('error', 'Only draft GRNs can be edited.');
        }

        $goodsReceiptNote->load('items');

        return view('goods-receipt-notes.edit', [
            'grn' => $goodsReceiptNote,
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'products' => Product::where('is_active', true)->orderBy('product_name')->get(['id', 'product_code', 'product_name', 'unit_price', 'supplier_id']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
            'campaigns' => PromotionalCampaign::where('is_active', true)
                ->whereDate('end_date', '>=', now())
                ->orderBy('campaign_name')
                ->get(['id', 'campaign_code', 'campaign_name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GoodsReceiptNote $goodsReceiptNote)
    {
        if ($goodsReceiptNote->status !== 'draft') {
            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote)
                ->with('error', 'Only draft GRNs can be updated.');
        }

        $validated = $request->validate([
            'receipt_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'supplier_invoice_date' => 'nullable|date',
            'tax_amount' => 'nullable|numeric|min:0',
            'freight_charges' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.uom_id' => 'required|exists:uoms,id',
            'items.*.quantity_received' => 'required|numeric|min:0.01',
            'items.*.quantity_accepted' => 'required|numeric|min:0',
            'items.*.quantity_rejected' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.promotional_campaign_id' => 'nullable|exists:promotional_campaigns,id',
            'items.*.is_promotional' => 'nullable|boolean',
            'items.*.promotional_price' => 'nullable|numeric|min:0',
            'items.*.priority_order' => 'nullable|integer|min:1|max:99',
            'items.*.must_sell_before' => 'nullable|date',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Calculate totals
            $total_quantity = 0;
            $total_amount = 0;

            foreach ($validated['items'] as $item) {
                $qty = $item['quantity_accepted'] ?? $item['quantity_received'];
                $total_quantity += $qty;
                $total_amount += $qty * $item['unit_cost'];
            }

            $grand_total = $total_amount + ($validated['tax_amount'] ?? 0)
                + ($validated['freight_charges'] ?? 0) + ($validated['other_charges'] ?? 0);

            // Update GRN
            $goodsReceiptNote->update([
                'receipt_date' => $validated['receipt_date'],
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'supplier_invoice_date' => $validated['supplier_invoice_date'] ?? null,
                'total_quantity' => $total_quantity,
                'total_amount' => $total_amount,
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'freight_charges' => $validated['freight_charges'] ?? 0,
                'other_charges' => $validated['other_charges'] ?? 0,
                'grand_total' => $grand_total,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Delete old items and create new ones
            $goodsReceiptNote->items()->delete();

            foreach ($validated['items'] as $index => $item) {
                $qty_accepted = $item['quantity_accepted'] ?? $item['quantity_received'];
                $qty_rejected = $item['quantity_rejected'] ?? 0;

                $goodsReceiptNote->items()->create([
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'uom_id' => $item['uom_id'],
                    'quantity_received' => $item['quantity_received'],
                    'quantity_accepted' => $qty_accepted,
                    'quantity_rejected' => $qty_rejected,
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $qty_accepted * $item['unit_cost'],
                    'selling_price' => $item['selling_price'] ?? null,
                    'promotional_campaign_id' => $item['promotional_campaign_id'] ?? null,
                    'is_promotional' => $item['is_promotional'] ?? false,
                    'promotional_price' => $item['promotional_price'] ?? null,
                    'priority_order' => $item['priority_order'] ?? 99,
                    'must_sell_before' => $item['must_sell_before'] ?? null,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'quality_status' => 'approved',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote)
                ->with('success', "GRN '{$goodsReceiptNote->grn_number}' updated successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating GRN', [
                'grn_id' => $goodsReceiptNote->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update GRN. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GoodsReceiptNote $goodsReceiptNote)
    {
        if ($goodsReceiptNote->status !== 'draft') {
            return back()->with('error', 'Only draft GRNs can be deleted.');
        }

        DB::beginTransaction();

        try {
            $grn_number = $goodsReceiptNote->grn_number;
            $goodsReceiptNote->items()->delete();
            $goodsReceiptNote->delete();

            DB::commit();

            return redirect()
                ->route('goods-receipt-notes.index')
                ->with('success', "GRN '{$grn_number}' deleted successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Unable to delete GRN.');
        }
    }

    /**
     * Generate unique GRN number
     */
    private function generateGRNNumber(): string
    {
        $year = now()->year;
        $lastGRN = GoodsReceiptNote::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastGRN ? ((int) substr($lastGRN->grn_number, -4)) + 1 : 1;

        return sprintf('GRN-%d-%04d', $year, $sequence);
    }

    /**
     * Post GRN to inventory
     */
    public function post(GoodsReceiptNote $goodsReceiptNote)
    {
        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->postGrnToInventory($goodsReceiptNote);

        if ($result['success']) {
            // Auto-create draft supplier payment
            $this->createDraftPayment($goodsReceiptNote);

            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote->id)
                ->with('status', $result['message'] . ' A draft payment has been created for this GRN.');
        }

        return redirect()
            ->back()
            ->with('error', $result['message']);
    }

    /**
     * Create draft payment for GRN
     */
    private function createDraftPayment(GoodsReceiptNote $grn)
    {
        try {
            return \DB::transaction(function () use ($grn) {
                // Generate payment number with lock to prevent duplicates
                $year = now()->year;
                $lastPayment = \App\Models\SupplierPayment::whereYear('created_at', $year)
                    ->lockForUpdate()
                    ->orderBy('id', 'desc')
                    ->first();

                $sequence = $lastPayment ? ((int) substr($lastPayment->payment_number, -6)) + 1 : 1;
                $paymentNumber = sprintf('PAY-%d-%06d', $year, $sequence);

                // Create draft payment
                $payment = \App\Models\SupplierPayment::create([
                    'payment_number' => $paymentNumber,
                    'supplier_id' => $grn->supplier_id,
                    'bank_account_id' => null,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => 'bank_transfer',
                    'reference_number' => 'Auto-generated for GRN: ' . $grn->grn_number,
                    'amount' => $grn->grand_total,
                    'description' => 'Auto-generated payment for GRN: ' . $grn->grn_number,
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ]);

                // Allocate to this GRN
                $payment->grnAllocations()->create([
                    'grn_id' => $grn->id,
                    'allocated_amount' => $grn->grand_total,
                ]);

                return $payment;
            });
        } catch (\Exception $e) {
            \Log::error('Failed to create auto payment: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Reverse a posted GRN
     */
    public function reverse(GoodsReceiptNote $goodsReceiptNote)
    {
        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->reverseGrnInventory($goodsReceiptNote);

        if ($result['success']) {
            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote->id)
                ->with('status', $result['message']);
        }

        return redirect()
            ->back()
            ->with('error', $result['message']);
    }
}
