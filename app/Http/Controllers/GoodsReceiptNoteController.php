<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceiptNote;
use App\Models\Product;
use App\Models\PromotionalCampaign;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

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
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name', 'sales_tax']),
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            // Don't load all products - will be loaded via AJAX when supplier is selected
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
        ]);
    }

    /**
     * Get products for a specific supplier (AJAX endpoint)
     */
    public function getProductsBySupplier(Request $request, $supplierId)
    {
        $products = Product::where('is_active', true)
            ->where('supplier_id', $supplierId)
            ->orderBy('product_name')
            ->get(['id', 'product_code', 'product_name', 'unit_sell_price', 'supplier_id', 'uom_conversion_factor']);

        return response()->json($products);
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
            'items.*.stock_uom_id' => 'required|exists:uoms,id',
            'items.*.purchase_uom_id' => 'required|exists:uoms,id',
            'items.*.qty_in_purchase_uom' => 'nullable|numeric|min:0',
            'items.*.uom_conversion_factor' => 'required|numeric|min:0.0001',
            'items.*.qty_in_stock_uom' => 'required|numeric|min:0',
            'items.*.unit_price_per_case' => 'nullable|numeric|min:0',
            'items.*.extended_value' => 'nullable|numeric|min:0',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.fmr_allowance' => 'nullable|numeric|min:0',
            'items.*.discounted_value_before_tax' => 'nullable|numeric|min:0',
            'items.*.excise_duty' => 'nullable|numeric|min:0',
            'items.*.sales_tax_value' => 'nullable|numeric|min:0',
            'items.*.advance_income_tax' => 'nullable|numeric|min:0',
            'items.*.total_value_with_taxes' => 'nullable|numeric|min:0',
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
            $total_amount = 0; // Total inventory cost (includes FMR)
            $amount_payable = 0; // Amount payable to supplier (excludes FMR)

            foreach ($validated['items'] as $item) {
                $qty = $item['quantity_accepted'] ?? $item['quantity_received'];
                $total_quantity += $qty;
                // total_amount = inventory cost (includes FMR in unit_cost)
                $total_amount += $qty * $item['unit_cost'];
                // amount_payable = what we owe supplier (excludes FMR)
                $amount_payable += $item['total_value_with_taxes'] ?? 0;
            }

            // grand_total = amount payable to supplier + freight + other charges
            $grand_total = $amount_payable + ($validated['freight_charges'] ?? 0) + ($validated['other_charges'] ?? 0);

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

                // Auto-create or update promotional campaign if promotional_price is set
                $promotionalCampaignId = null;
                $isPromotional = false;
                if (!empty($item['promotional_price']) && $item['promotional_price'] > 0) {
                    $product = Product::find($item['product_id']);
                    $campaignCode = 'PROMO-' . $grn->grn_number . '-' . ($index + 1);
                    $campaign = PromotionalCampaign::updateOrCreate(
                        ['campaign_code' => $campaignCode],
                        [
                            'campaign_name' => 'GRN Promo: ' . ($product->product_name ?? 'Product ' . $item['product_id']),
                            'description' => 'Auto-created promotional campaign for GRN ' . $grn->grn_number,
                            'start_date' => $validated['receipt_date'],
                            'end_date' => $item['must_sell_before'] ?? now()->addMonths(3),
                            'discount_type' => 'special_price',
                            'discount_value' => $item['promotional_price'],
                            'is_active' => true,
                            'is_auto_apply' => false,
                            'created_by' => auth()->id(),
                        ]
                    );
                    $promotionalCampaignId = $campaign->id;
                    $isPromotional = true;
                }

                $grn->items()->create([
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'stock_uom_id' => $item['stock_uom_id'],
                    'purchase_uom_id' => $item['purchase_uom_id'],
                    'qty_in_purchase_uom' => $item['qty_in_purchase_uom'] ?? null,
                    'uom_conversion_factor' => $item['uom_conversion_factor'],
                    'qty_in_stock_uom' => $item['qty_in_stock_uom'],
                    'unit_price_per_case' => $item['unit_price_per_case'] ?? null,
                    'extended_value' => $item['extended_value'] ?? 0,
                    'discount_value' => $item['discount_value'] ?? 0,
                    'fmr_allowance' => $item['fmr_allowance'] ?? 0,
                    'discounted_value_before_tax' => $item['discounted_value_before_tax'] ?? 0,
                    'excise_duty' => $item['excise_duty'] ?? 0,
                    'sales_tax_value' => $item['sales_tax_value'] ?? 0,
                    'advance_income_tax' => $item['advance_income_tax'] ?? 0,
                    'total_value_with_taxes' => $item['total_value_with_taxes'] ?? 0,
                    'quantity_received' => $item['quantity_received'],
                    'quantity_accepted' => $qty_accepted,
                    'quantity_rejected' => $qty_rejected,
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $qty_accepted * $item['unit_cost'],
                    'selling_price' => $item['selling_price'] ?? null,
                    'promotional_campaign_id' => $promotionalCampaignId,
                    'is_promotional' => $isPromotional,
                    'promotional_price' => $item['promotional_price'] ?? null,
                    'promotional_discount_percent' => null,
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
        $goodsReceiptNote->load(['supplier', 'warehouse', 'receivedBy', 'verifiedBy', 'items.product', 'items.stockUom', 'items.purchaseUom', 'items.promotionalCampaign']);

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
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name', 'sales_tax']),
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
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
            'items.*.stock_uom_id' => 'required|exists:uoms,id',
            'items.*.purchase_uom_id' => 'nullable|exists:uoms,id',
            'items.*.qty_in_purchase_uom' => 'nullable|numeric|min:0',
            'items.*.uom_conversion_factor' => 'nullable|numeric|min:0',
            'items.*.qty_in_stock_uom' => 'nullable|numeric|min:0',
            'items.*.unit_price_per_case' => 'nullable|numeric|min:0',
            'items.*.extended_value' => 'nullable|numeric|min:0',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.fmr_allowance' => 'nullable|numeric|min:0',
            'items.*.discounted_value_before_tax' => 'nullable|numeric|min:0',
            'items.*.excise_duty' => 'nullable|numeric|min:0',
            'items.*.sales_tax_value' => 'nullable|numeric|min:0',
            'items.*.advance_income_tax' => 'nullable|numeric|min:0',
            'items.*.total_value_with_taxes' => 'nullable|numeric|min:0',
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
            'items.*.selling_strategy' => 'nullable|string',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.lot_number' => 'nullable|string|max:100',
            'items.*.manufacturing_date' => 'nullable|date',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.storage_location' => 'nullable|string|max:100',
            'items.*.quality_status' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Calculate totals
            $total_quantity = 0;
            $total_amount = 0; // Total inventory cost (includes FMR)
            $amount_payable = 0; // Amount payable to supplier (excludes FMR)

            foreach ($validated['items'] as $item) {
                $qty = $item['quantity_accepted'] ?? $item['quantity_received'];
                $total_quantity += $qty;
                // total_amount = inventory cost (includes FMR in unit_cost)
                $total_amount += $qty * $item['unit_cost'];
                // amount_payable = what we owe supplier (excludes FMR)
                $amount_payable += $item['total_value_with_taxes'] ?? 0;
            }

            // grand_total = amount payable to supplier + freight + other charges
            $grand_total = $amount_payable + ($validated['freight_charges'] ?? 0) + ($validated['other_charges'] ?? 0);

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

                // Auto-create or update promotional campaign if promotional_price is set
                $promotionalCampaignId = null;
                $isPromotional = false;
                if (!empty($item['promotional_price']) && $item['promotional_price'] > 0) {
                    $product = Product::find($item['product_id']);
                    $campaignCode = 'PROMO-' . $goodsReceiptNote->grn_number . '-' . ($index + 1);
                    $campaign = PromotionalCampaign::updateOrCreate(
                        ['campaign_code' => $campaignCode],
                        [
                            'campaign_name' => 'GRN Promo: ' . ($product->product_name ?? 'Product ' . $item['product_id']),
                            'description' => 'Auto-created promotional campaign for GRN ' . $goodsReceiptNote->grn_number,
                            'start_date' => $validated['receipt_date'],
                            'end_date' => $item['must_sell_before'] ?? now()->addMonths(3),
                            'discount_type' => 'special_price',
                            'discount_value' => $item['promotional_price'],
                            'is_active' => true,
                            'is_auto_apply' => false,
                            'created_by' => auth()->id(),
                        ]
                    );
                    $promotionalCampaignId = $campaign->id;
                    $isPromotional = true;
                }

                $goodsReceiptNote->items()->create([
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'stock_uom_id' => $item['stock_uom_id'],
                    'purchase_uom_id' => $item['purchase_uom_id'] ?? null,
                    'qty_in_purchase_uom' => $item['qty_in_purchase_uom'] ?? null,
                    'uom_conversion_factor' => $item['uom_conversion_factor'] ?? 1,
                    'qty_in_stock_uom' => $item['qty_in_stock_uom'] ?? $item['quantity_received'],
                    'unit_price_per_case' => $item['unit_price_per_case'] ?? null,
                    'extended_value' => $item['extended_value'] ?? 0,
                    'discount_value' => $item['discount_value'] ?? 0,
                    'fmr_allowance' => $item['fmr_allowance'] ?? 0,
                    'discounted_value_before_tax' => $item['discounted_value_before_tax'] ?? 0,
                    'excise_duty' => $item['excise_duty'] ?? 0,
                    'sales_tax_value' => $item['sales_tax_value'] ?? 0,
                    'advance_income_tax' => $item['advance_income_tax'] ?? 0,
                    'total_value_with_taxes' => $item['total_value_with_taxes'] ?? 0,
                    'quantity_received' => $item['quantity_received'],
                    'quantity_accepted' => $qty_accepted,
                    'quantity_rejected' => $qty_rejected,
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $qty_accepted * $item['unit_cost'],
                    'selling_price' => $item['selling_price'] ?? null,
                    'promotional_campaign_id' => $promotionalCampaignId,
                    'is_promotional' => $isPromotional,
                    'promotional_price' => $item['promotional_price'] ?? null,
                    'promotional_discount_percent' => null,
                    'selling_strategy' => $item['selling_strategy'] ?? 'fifo',
                    'priority_order' => $item['priority_order'] ?? 99,
                    'must_sell_before' => $item['must_sell_before'] ?? null,
                    'batch_number' => $item['batch_number'] ?? null,
                    'lot_number' => $item['lot_number'] ?? null,
                    'manufacturing_date' => $item['manufacturing_date'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'storage_location' => $item['storage_location'] ?? null,
                    'quality_status' => $item['quality_status'] ?? 'approved',
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
                $lastPayment = \App\Models\SupplierPayment::withTrashed()
                    ->whereYear('created_at', $year)
                    ->where('payment_number', 'LIKE', "PAY-{$year}-%")
                    ->lockForUpdate()
                    ->orderBy('id', 'desc')
                    ->first();

                $sequence = $lastPayment ? ((int) substr($lastPayment->payment_number, -6)) + 1 : 1;
                $paymentNumber = sprintf('PAY-%d-%06d', $year, $sequence);

                // Get default bank account for auto-generated payment
                $defaultBankAccount = \App\Models\BankAccount::where('is_active', true)
                    ->orderBy('id')
                    ->first();

                // Create draft payment
                $payment = \App\Models\SupplierPayment::create([
                    'payment_number' => $paymentNumber,
                    'supplier_id' => $grn->supplier_id,
                    'bank_account_id' => $defaultBankAccount?->id,
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
    public function reverse(Request $request, GoodsReceiptNote $goodsReceiptNote)
    {
        // Validate password
        $request->validate([
            'password' => 'required|string',
        ]);

        // Verify user's password
        if (!Hash::check($request->password, auth()->user()->password)) {
            Log::warning("Failed GRN reversal attempt for {$goodsReceiptNote->grn_number} - Invalid password by user: " . auth()->user()->name);

            return redirect()
                ->back()
                ->with('error', 'Invalid password. GRN reversal requires your password confirmation.');
        }

        // Log password confirmation
        Log::info("GRN reversal password confirmed for {$goodsReceiptNote->grn_number} by user: " . auth()->user()->name . ' (ID: ' . auth()->id() . ')');

        // Check if GRN has any posted payments
        $hasPostedPayments = $goodsReceiptNote->payments()
            ->where('status', 'posted')
            ->exists();

        if ($hasPostedPayments) {
            return redirect()
                ->back()
                ->with('error', 'Cannot reverse GRN that has posted payments. Please reverse the payments first.');
        }

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
