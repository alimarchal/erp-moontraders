<?php

namespace App\Http\Controllers;

use App\Exports\GoodsReceiptNoteTemplateExport;
use App\Http\Requests\ImportGoodsReceiptNoteRequest;
use App\Imports\GoodsReceiptNoteItemsImport;
use App\Models\BankAccount;
use App\Models\GoodsReceiptNote;
use App\Models\Product;
use App\Models\PromotionalCampaign;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GoodsReceiptNoteController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:goods-receipt-note-list', only: ['index', 'show']),
            new Middleware('permission:goods-receipt-note-create', only: ['create', 'store', 'createDraftPayment']),
            new Middleware('permission:goods-receipt-note-edit', only: ['edit', 'update']),
            new Middleware('permission:goods-receipt-note-delete', only: ['destroy']),
            new Middleware('permission:goods-receipt-note-post', only: ['post']),
            new Middleware('permission:goods-receipt-note-reverse', only: ['reverse']),
            new Middleware('permission:goods-receipt-note-import', only: ['importItems', 'downloadImportTemplate']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $hasDateFilter = $request->filled('filter.receipt_date_from') || $request->filled('filter.receipt_date_to');

        $query = GoodsReceiptNote::query()->with(['supplier', 'warehouse', 'receivedBy']);

        if (! $hasDateFilter) {
            $today = now()->toDateString();

            $query->where(function ($grnQuery) use ($today) {
                $grnQuery->whereDate('receipt_date', $today)
                    ->orWhere('status', 'draft');
            });
        }

        if (! auth()->user()->can('goods-receipt-note-view-all')) {
            $query->where('received_by', auth()->id());
        }

        $userSupplierId = $this->getUserSupplierScope();
        if ($userSupplierId) {
            $query->where('supplier_id', $userSupplierId);
        }

        $grns = QueryBuilder::for($query)
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

        $suppliers = $userSupplierId
            ? Supplier::where('id', $userSupplierId)->orderBy('supplier_name')->get(['id', 'supplier_name'])
            : Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        return view('goods-receipt-notes.index', [
            'grns' => $grns,
            'suppliers' => $suppliers,
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $userSupplierId = $this->getUserSupplierScope();

        $suppliers = $userSupplierId
            ? Supplier::where('id', $userSupplierId)->where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name', 'sales_tax', 'is_fmr_allowed'])
            : Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name', 'sales_tax', 'is_fmr_allowed']);

        return view('goods-receipt-notes.create', [
            'suppliers' => $suppliers,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            // Don't load all products - will be loaded via AJAX when supplier is selected
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
            'withholdingTaxRate' => $this->resolveActiveWithholdingTaxRate(),
        ]);
    }

    /**
     * Get products for a specific supplier (AJAX endpoint)
     */
    public function getProductsBySupplier(Request $request, $supplierId)
    {
        $userSupplierId = $this->getUserSupplierScope();

        if ($userSupplierId && $supplierId != $userSupplierId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

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
            'items.*.other_charges' => 'nullable|numeric|min:0',
            'items.*.withholding_tax' => 'nullable|numeric|min:0',
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
                $total_amount += round($qty * (float) $item['unit_cost'], 4);
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
                if (! empty($item['promotional_price']) && $item['promotional_price'] > 0) {
                    $product = Product::find($item['product_id']);
                    $campaignCode = 'PROMO-'.$grn->grn_number.'-'.($index + 1);
                    $campaign = PromotionalCampaign::updateOrCreate(
                        ['campaign_code' => $campaignCode],
                        [
                            'campaign_name' => 'GRN Promo: '.($product->product_name ?? 'Product '.$item['product_id']),
                            'description' => 'Auto-created promotional campaign for GRN '.$grn->grn_number,
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
                    'other_charges' => $item['other_charges'] ?? 0,
                    'withholding_tax' => $item['withholding_tax'] ?? 0,
                    'total_value_with_taxes' => $item['total_value_with_taxes'] ?? 0,
                    'quantity_received' => $item['quantity_received'],
                    'quantity_accepted' => $qty_accepted,
                    'quantity_rejected' => $qty_rejected,
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => round($qty_accepted * (float) $item['unit_cost'], 4),
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
                ->with('error', 'Unable to create GRN: '.$e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorizeGrnSupplierAccess($goodsReceiptNote);

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
        $this->authorizeGrnSupplierAccess($goodsReceiptNote);

        if ($goodsReceiptNote->status !== 'draft') {
            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote)
                ->with('error', 'Only draft GRNs can be edited.');
        }

        $userSupplierId = $this->getUserSupplierScope();
        $goodsReceiptNote->load('items');

        $suppliers = $userSupplierId
            ? Supplier::where('id', $userSupplierId)->where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name', 'sales_tax', 'is_fmr_allowed'])
            : Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name', 'sales_tax', 'is_fmr_allowed']);

        return view('goods-receipt-notes.edit', [
            'grn' => $goodsReceiptNote,
            'suppliers' => $suppliers,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
            'withholdingTaxRate' => $this->resolveActiveWithholdingTaxRate(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorizeGrnSupplierAccess($goodsReceiptNote);

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
            'items.*.other_charges' => 'nullable|numeric|min:0',
            'items.*.withholding_tax' => 'nullable|numeric|min:0',
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
                $total_amount += round($qty * (float) $item['unit_cost'], 4);
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
                if (! empty($item['promotional_price']) && $item['promotional_price'] > 0) {
                    $product = Product::find($item['product_id']);
                    $campaignCode = 'PROMO-'.$goodsReceiptNote->grn_number.'-'.($index + 1);
                    $campaign = PromotionalCampaign::updateOrCreate(
                        ['campaign_code' => $campaignCode],
                        [
                            'campaign_name' => 'GRN Promo: '.($product->product_name ?? 'Product '.$item['product_id']),
                            'description' => 'Auto-created promotional campaign for GRN '.$goodsReceiptNote->grn_number,
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
                    'other_charges' => $item['other_charges'] ?? 0,
                    'withholding_tax' => $item['withholding_tax'] ?? 0,
                    'total_value_with_taxes' => $item['total_value_with_taxes'] ?? 0,
                    'quantity_received' => $item['quantity_received'],
                    'quantity_accepted' => $qty_accepted,
                    'quantity_rejected' => $qty_rejected,
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => round($qty_accepted * (float) $item['unit_cost'], 4),
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
        $this->authorizeGrnSupplierAccess($goodsReceiptNote);

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
        $prefix = "GRN-{$year}-";

        $lastGRN = GoodsReceiptNote::where('grn_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastGRN ? ((int) str_replace($prefix, '', $lastGRN->grn_number)) + 1 : 1;

        return sprintf('%s%04d', $prefix, $sequence);
    }

    /**
     * Post GRN to inventory
     */
    public function post(GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorizeGrnSupplierAccess($goodsReceiptNote);

        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->postGrnToInventory($goodsReceiptNote);

        if ($result['success']) {
            // Auto-create draft supplier payment
            $this->createDraftPayment($goodsReceiptNote);

            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote->id)
                ->with('success', $result['message'].' A draft payment has been created for this GRN.');
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
                $lastPayment = SupplierPayment::withTrashed()
                    ->whereYear('created_at', $year)
                    ->where('payment_number', 'LIKE', "PAY-{$year}-%")
                    ->lockForUpdate()
                    ->orderBy('id', 'desc')
                    ->first();

                $sequence = $lastPayment ? ((int) substr($lastPayment->payment_number, -6)) + 1 : 1;
                $paymentNumber = sprintf('PAY-%d-%06d', $year, $sequence);

                // Get default bank account for auto-generated payment
                $defaultBankAccount = BankAccount::where('is_active', true)
                    ->orderBy('id')
                    ->first();

                // Create draft payment
                $payment = SupplierPayment::create([
                    'payment_number' => $paymentNumber,
                    'supplier_id' => $grn->supplier_id,
                    'bank_account_id' => $defaultBankAccount?->id,
                    'payment_date' => now()->toDateString(),
                    'payment_method' => 'bank_transfer',
                    'reference_number' => 'Auto-generated for GRN: '.$grn->grn_number,
                    'amount' => $grn->grand_total,
                    'description' => 'Auto-generated payment for GRN: '.$grn->grn_number,
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
            \Log::error('Failed to create auto payment: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Download sample Excel template for GRN import
     */
    public function downloadImportTemplate()
    {
        return Excel::download(new GoodsReceiptNoteTemplateExport, 'grn_import_template.xlsx');
    }

    /**
     * Import GRN items from Excel file
     */
    public function importItems(ImportGoodsReceiptNoteRequest $request)
    {
        $validated = $request->validated();

        $userSupplierId = $this->getUserSupplierScope();
        if ($userSupplierId && $validated['supplier_id'] != $userSupplierId) {
            return back()
                ->with('error', 'You do not have permission to import GRNs for this supplier.');
        }

        $withholdingTaxRate = $this->resolveActiveWithholdingTaxRate();

        DB::beginTransaction();

        try {
            $import = new GoodsReceiptNoteItemsImport($validated['supplier_id'], $withholdingTaxRate);
            Excel::import($import, $request->file('import_file'));

            if ($import->hasErrors()) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->withErrors(['import_file' => array_values($import->getRowErrors())]);
            }

            $processedItems = $import->getProcessedItems();

            if (empty($processedItems)) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->withErrors(['import_file' => 'No valid items found in the Excel file.']);
            }

            $grnNumber = $this->generateGRNNumber();

            $totalQuantity = 0;
            $totalAmount = 0;
            $amountPayable = 0;

            foreach ($processedItems as $item) {
                $qty = $item['quantity_accepted'];
                $totalQuantity += $qty;
                $totalAmount += round($qty * (float) $item['unit_cost'], 4);
                $amountPayable += $item['total_value_with_taxes'];
            }

            $grandTotal = $amountPayable;

            $grn = GoodsReceiptNote::create([
                'grn_number' => $grnNumber,
                'receipt_date' => $validated['receipt_date'],
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'supplier_invoice_date' => $validated['supplier_invoice_date'] ?? null,
                'total_quantity' => $totalQuantity,
                'total_amount' => $totalAmount,
                'tax_amount' => 0,
                'freight_charges' => 0,
                'other_charges' => 0,
                'grand_total' => $grandTotal,
                'status' => 'draft',
                'received_by' => auth()->id(),
                'notes' => 'Imported from Excel file: '.$request->file('import_file')->getClientOriginalName(),
            ]);

            foreach ($processedItems as $index => $item) {
                $promotionalCampaignId = null;
                $isPromotional = false;

                if (! empty($item['promotional_price']) && $item['promotional_price'] > 0) {
                    $product = Product::find($item['product_id']);
                    $campaignCode = 'PROMO-'.$grn->grn_number.'-'.($index + 1);
                    $campaign = PromotionalCampaign::updateOrCreate(
                        ['campaign_code' => $campaignCode],
                        [
                            'campaign_name' => 'GRN Promo: '.($product->product_name ?? 'Product '.$item['product_id']),
                            'description' => 'Auto-created promotional campaign for GRN '.$grn->grn_number,
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
                    'qty_in_purchase_uom' => $item['qty_in_purchase_uom'],
                    'uom_conversion_factor' => $item['uom_conversion_factor'],
                    'qty_in_stock_uom' => $item['qty_in_stock_uom'],
                    'unit_price_per_case' => $item['unit_price_per_case'],
                    'extended_value' => $item['extended_value'],
                    'discount_value' => $item['discount_value'],
                    'fmr_allowance' => $item['fmr_allowance'],
                    'discounted_value_before_tax' => $item['discounted_value_before_tax'],
                    'excise_duty' => $item['excise_duty'],
                    'sales_tax_value' => $item['sales_tax_value'],
                    'advance_income_tax' => $item['advance_income_tax'],
                    'other_charges' => $item['other_charges'],
                    'withholding_tax' => $item['withholding_tax'] ?? 0,
                    'total_value_with_taxes' => $item['total_value_with_taxes'],
                    'quantity_received' => $item['quantity_received'],
                    'quantity_accepted' => $item['quantity_accepted'],
                    'quantity_rejected' => 0,
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => round((float) $item['quantity_accepted'] * (float) $item['unit_cost'], 4),
                    'selling_price' => $item['selling_price'],
                    'promotional_campaign_id' => $promotionalCampaignId,
                    'is_promotional' => $isPromotional,
                    'promotional_price' => $item['promotional_price'],
                    'promotional_discount_percent' => null,
                    'priority_order' => $item['priority_order'],
                    'must_sell_before' => $item['must_sell_before'],
                    'batch_number' => $item['batch_number'],
                    'manufacturing_date' => $item['manufacturing_date'],
                    'expiry_date' => $item['expiry_date'],
                    'quality_status' => 'approved',
                    'notes' => null,
                ]);
            }

            DB::commit();

            $itemCount = count($processedItems);

            return redirect()
                ->route('goods-receipt-notes.edit', $grn)
                ->with('success', "GRN '{$grn->grn_number}' created with {$itemCount} items from Excel import. Please review and submit.");

        } catch (ValidationException $e) {
            DB::rollBack();

            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = 'Row '.$failure->row().': '.$failure->attribute().' - '.implode(', ', $failure->errors());
            }

            return back()
                ->withInput()
                ->withErrors(['import_file' => $errors]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error importing GRN from Excel', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to import GRN: '.$e->getMessage());
        }
    }

    /**
     * Show special edit form for posted GRNs — Super Admin only.
     * Allows correcting uom_conversion_factor per line item.
     * total_cost stays unchanged; quantities and unit_cost recalculate.
     */
    public function editSpecial(GoodsReceiptNote $goodsReceiptNote)
    {
        abort_unless(
            auth()->user()->is_super_admin === 'Yes' || auth()->user()->hasRole('super-admin'),
            403
        );

        $goodsReceiptNote->load(['items.product', 'supplier', 'warehouse']);

        return view('goods-receipt-notes.edit-special', ['grn' => $goodsReceiptNote]);
    }

    /**
     * Apply special quantity correction to a posted GRN — Super Admin only.
     *
     * For each line item where uom_conversion_factor changed:
     *   new_qty       = qty_in_purchase_uom × new_factor
     *   unit_cost     = total_cost / new_qty   (invoice amount stays fixed)
     *   All inventory tables updated in one transaction.
     */
    public function updateSpecial(Request $request, GoodsReceiptNote $goodsReceiptNote)
    {
        abort_unless(
            auth()->user()->is_super_admin === 'Yes' || auth()->user()->hasRole('super-admin'),
            403
        );

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:goods_receipt_note_items,id',
            'items.*.uom_conversion_factor' => 'required|numeric|min:0.0001',
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->input('items') as $formItem) {
                $item = DB::table('goods_receipt_note_items')->where('id', $formItem['id'])->first();

                $newFactor = (float) $formItem['uom_conversion_factor'];

                // Skip lines where nothing changed
                if (abs($newFactor - (float) $item->uom_conversion_factor) < 0.0001) {
                    continue;
                }

                $newQty = round((float) $item->qty_in_purchase_uom * $newFactor, 2);
                $totalCost = (float) $item->total_cost; // invoice amount — unchanged
                $newUnitCost = $newQty > 0 ? round($totalCost / $newQty, 6) : (float) $item->unit_cost;
                $delta = $newQty - (float) $item->quantity_accepted;

                // 1. goods_receipt_note_items
                DB::table('goods_receipt_note_items')->where('id', $item->id)->update([
                    'qty_in_stock_uom' => $newQty,
                    'uom_conversion_factor' => $newFactor,
                    'quantity_received' => $newQty,
                    'quantity_accepted' => $newQty,
                    'unit_cost' => $newUnitCost,
                    // total_cost intentionally unchanged
                ]);

                // Resolve linked inventory rows
                $svl = DB::table('stock_valuation_layers')->where('grn_item_id', $item->id)->first();
                if (! $svl) {
                    continue;
                }

                $sle = DB::table('stock_ledger_entries')->where('stock_movement_id', $svl->stock_movement_id)->first();

                // 2. stock_movements — quantity only (total_value = invoice, unchanged)
                DB::table('stock_movements')->where('id', $svl->stock_movement_id)->update([
                    'quantity' => $newQty,
                ]);

                // 3a. stock_ledger_entries — GRN receipt row
                $newSleBalance = (float) $sle->quantity_balance + $delta;
                DB::table('stock_ledger_entries')->where('id', $sle->id)->update([
                    'quantity_in' => $newQty,
                    'quantity_balance' => $newSleBalance,
                    'valuation_rate' => $newUnitCost,
                    'stock_value' => round($newSleBalance * $newUnitCost, 4),
                ]);

                // 3b. Cascade running balance to all later SLE rows for same product+warehouse
                $laterRows = DB::table('stock_ledger_entries')
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $goodsReceiptNote->warehouse_id)
                    ->where('id', '>', $sle->id)
                    ->orderBy('id')
                    ->get(['id', 'quantity_balance', 'valuation_rate']);

                foreach ($laterRows as $laterRow) {
                    DB::table('stock_ledger_entries')->where('id', $laterRow->id)->update([
                        'quantity_balance' => (float) $laterRow->quantity_balance + $delta,
                        'stock_value' => round(((float) $laterRow->quantity_balance + $delta) * (float) $laterRow->valuation_rate, 4),
                    ]);
                }

                // 4. stock_valuation_layers
                $newRemaining = (float) $svl->quantity_remaining + $delta;
                DB::table('stock_valuation_layers')->where('id', $svl->id)->update([
                    'quantity_received' => $newQty,
                    'quantity_remaining' => $newRemaining,
                    'unit_cost' => $newUnitCost,
                    'total_value' => round($newQty * $newUnitCost, 4),
                    'value_remaining' => round($newRemaining * $newUnitCost, 4),
                ]);

                // 5. current_stock_by_batch
                $csbb = DB::table('current_stock_by_batch')->where('stock_batch_id', $svl->stock_batch_id)->first();
                if ($csbb) {
                    $newCsbbQty = (float) $csbb->quantity_on_hand + $delta;
                    DB::table('current_stock_by_batch')->where('id', $csbb->id)->update([
                        'quantity_on_hand' => $newCsbbQty,
                        'unit_cost' => $newUnitCost,
                        'total_value' => round($newCsbbQty * $newUnitCost, 4),
                        'last_updated' => now(),
                    ]);
                }

                // 6. current_stock — full recalc from valuation layers
                $svlAgg = DB::table('stock_valuation_layers')
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $goodsReceiptNote->warehouse_id)
                    ->where('quantity_remaining', '>', 0)
                    ->selectRaw('COALESCE(SUM(quantity_remaining), 0) as total_qty, COALESCE(SUM(quantity_remaining * unit_cost), 0) as total_val')
                    ->first();

                $totalQty = (float) $svlAgg->total_qty;
                $totalVal = (float) $svlAgg->total_val;
                $avgCost = $totalQty > 0 ? round($totalVal / $totalQty, 6) : 0;

                $cs = DB::table('current_stock')
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $goodsReceiptNote->warehouse_id)
                    ->first();

                if ($cs) {
                    DB::table('current_stock')
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $goodsReceiptNote->warehouse_id)
                        ->update([
                            'quantity_on_hand' => $totalQty,
                            'quantity_available' => $totalQty - ($cs->quantity_reserved ?? 0),
                            'average_cost' => $avgCost,
                            'total_value' => $totalVal,
                            'last_updated' => now(),
                        ]);
                }

                // 7. inventory_ledger_entries
                DB::table('inventory_ledger_entries')
                    ->where('goods_receipt_note_id', $goodsReceiptNote->id)
                    ->where('product_id', $item->product_id)
                    ->update([
                        'debit_qty' => $newQty,
                        'unit_cost' => $newUnitCost,
                    ]);
            }

            // 8. goods_receipt_notes header — recalculate total_quantity
            $newTotalQty = DB::table('goods_receipt_note_items')
                ->where('grn_id', $goodsReceiptNote->id)
                ->sum('qty_in_stock_uom');

            DB::table('goods_receipt_notes')->where('id', $goodsReceiptNote->id)->update([
                'total_quantity' => $newTotalQty,
            ]);

            DB::commit();

            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote)
                ->with('success', 'GRN quantities corrected successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error in GRN special update', [
                'grn_id' => $goodsReceiptNote->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Update failed: '.$e->getMessage());
        }
    }

    /**
     * Get the supplier ID scope for current user (null if no scope, user has full access).
     */
    private function getUserSupplierScope(): ?int
    {
        $user = auth()->user();

        if ($user->is_super_admin === 'Yes' || $user->hasRole('super-admin')) {
            return null;
        }

        if ($user->hasRole('admin')) {
            return null;
        }

        return (int) $user->supplier_id ?? null;
    }

    /**
     * Authorize GRN access based on user supplier scope.
     */
    private function authorizeGrnSupplierAccess(GoodsReceiptNote $grn): void
    {
        $userSupplierId = $this->getUserSupplierScope();

        if ($userSupplierId && $grn->supplier_id != $userSupplierId) {
            abort(403, 'You do not have permission to access this GRN.');
        }
    }

    private function resolveActiveWithholdingTaxRate(): float
    {
        $taxCode = TaxCode::query()
            ->where('tax_code', 'WHT-0.1')
            ->where('tax_type', 'withholding_tax')
            ->where('is_active', true)
            ->first();

        if (! $taxCode) {
            return 0.0;
        }

        $today = Carbon::today()->toDateString();

        $taxRate = TaxRate::query()
            ->where('tax_code_id', $taxCode->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            })
            ->orderByDesc('effective_from')
            ->first();

        return (float) ($taxRate?->rate ?? 0.0);
    }

    /**
     * Reverse a posted GRN
     */
    public function reverse(Request $request, GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorizeGrnSupplierAccess($goodsReceiptNote);

        // Validate password
        $request->validate([
            'password' => 'required|string',
        ]);

        // Verify user's password
        if (! Hash::check($request->password, auth()->user()->password)) {
            Log::warning("Failed GRN reversal attempt for {$goodsReceiptNote->grn_number} - Invalid password by user: ".auth()->user()->name);

            return redirect()
                ->back()
                ->with('error', 'Invalid password. GRN reversal requires your password confirmation.');
        }

        // Log password confirmation
        Log::info("GRN reversal password confirmed for {$goodsReceiptNote->grn_number} by user: ".auth()->user()->name.' (ID: '.auth()->id().')');

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
