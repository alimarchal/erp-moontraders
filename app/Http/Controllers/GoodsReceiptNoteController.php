<?php

namespace App\Http\Controllers;

use App\Exports\GoodsReceiptNoteTemplateExport;
use App\Http\Requests\ImportGoodsReceiptNoteRequest;
use App\Http\Requests\PostGoodsReceiptNoteRequest;
use App\Imports\GoodsReceiptNoteItemsImport;
use App\Models\AppSetting;
use App\Models\GoodsReceiptNote;
use App\Models\Product;
use App\Models\PromotionalCampaign;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CartonConversionService;
use App\Services\GrnCorrectionService;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException as RequestValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GoodsReceiptNoteController extends Controller implements HasMiddleware
{
    public const PER_PAGE = [20, 50, 100, 500];

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:goods-receipt-note-list', only: ['index', 'show']),
            new Middleware('permission:goods-receipt-note-create', only: ['create', 'store']),
            new Middleware('permission:goods-receipt-note-edit', only: ['edit', 'update']),
            new Middleware('permission:goods-receipt-note-delete', only: ['destroy']),
            new Middleware('permission:goods-receipt-note-post', only: ['post']),
            new Middleware('permission:goods-receipt-note-reverse', only: ['reverse']),
            new Middleware('permission:goods-receipt-note-import', only: ['importItems', 'downloadImportTemplate']),
        ];
    }

    /**
     * The GRN help page is for people who create GRNs and for super admins.
     */
    public static function canViewHelp(?User $user): bool
    {
        return $user !== null
            && ($user->is_super_admin === 'Yes' || $user->hasRole('super-admin') || $user->can('goods-receipt-note-create'));
    }

    /**
     * How a GRN works: lifecycle, posting, supplier settings, ledger hand-off, reversal.
     */
    public function help()
    {
        abort_unless(self::canViewHelp(auth()->user()), 403);

        return view('goods-receipt-notes.help');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $hasDateFilter = $request->filled('filter.receipt_date_from') || $request->filled('filter.receipt_date_to');

        $query = GoodsReceiptNote::query()->with(['supplier', 'warehouse', 'receivedBy', 'ledgerEntry']);

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

        $filters = [
            AllowedFilter::partial('grn_number'),
            AllowedFilter::partial('supplier_invoice_number'),
            AllowedFilter::exact('supplier_id'),
            AllowedFilter::exact('warehouse_id'),
            AllowedFilter::callback('status', function ($grnQuery, $value) {
                if ($value !== 'all') {
                    $grnQuery->where('goods_receipt_notes.status', $value);
                }
            }),
            AllowedFilter::scope('receipt_date_from'),
            AllowedFilter::scope('receipt_date_to'),
            AllowedFilter::callback('search', function ($grnQuery, $value) {
                $term = '%'.mb_strtolower(trim((string) $value)).'%';
                $grnQuery->where(fn ($q) => $q
                    ->whereRaw('LOWER(grn_number) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(supplier_invoice_number) LIKE ?', [$term])
                    ->orWhereHas('supplier', fn ($s) => $s->whereRaw('LOWER(supplier_name) LIKE ?', [$term])));
            }),
        ];

        // Count and amount per status for the same filters (bar the status itself, so every tab has its count).
        $statsRequest = Request::create($request->url(), 'GET', ['filter' => Arr::except((array) $request->input('filter', []), 'status')]);
        $statusTotals = QueryBuilder::for(clone $query, $statsRequest)->allowedFilters($filters)->toBase()->reorder()
            ->selectRaw('status, count(*) as grn_count, coalesce(sum(grand_total), 0) as amount')
            ->groupBy('status')->get()->keyBy('status');

        $perPage = (string) $request->input('per_page', '20');
        $perPage = in_array($perPage, array_map('strval', self::PER_PAGE), true) ? $perPage : '20';

        $grnList = QueryBuilder::for($query)
            ->allowedFilters($filters)
            ->allowedSorts(['receipt_date', 'grn_number', 'grand_total', 'created_at'])
            ->defaultSort('-receipt_date', '-id');

        $totalValue = (float) $grnList->getEloquentBuilder()->clone()->toBase()->reorder()->sum('grand_total');

        $grns = $grnList->paginate((int) $perPage)->withQueryString();

        $suppliers = $userSupplierId
            ? Supplier::where('id', $userSupplierId)->orderBy('supplier_name')->get(['id', 'supplier_name'])
            : Supplier::orderBy('supplier_name')->get(['id', 'supplier_name']);

        return view('goods-receipt-notes.index', [
            'grns' => $grns,
            'suppliers' => $suppliers,
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'statusTotals' => $statusTotals,
            'hasDateFilter' => $hasDateFilter,
            'perPage' => $perPage,
            'totalValue' => $totalValue,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $userSupplierId = $this->getUserSupplierScope();

        $suppliers = $this->suppliersForGrnForm($userSupplierId);

        return view('goods-receipt-notes.create', [
            'suppliers' => $suppliers,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            // Don't load all products - will be loaded via AJAX when supplier is selected
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
            'withholdingTaxRate' => $this->resolveActiveWithholdingTaxRate(),
            'grnLayout' => AppSetting::layoutFor('goods_receipt_notes')->value,
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
            ->get(['id', 'product_code', 'product_name', 'unit_sell_price', 'supplier_id', 'uom_conversion_factor', 'pieces_per_carton', 'units_per_piece']);

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
            'items.*.entered_cartons' => 'nullable|numeric|min:0',
            'items.*.entered_loose_pieces' => 'nullable|integer|min:0',
            'items.*.rate_per_th' => 'nullable|numeric|min:0',
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

        $this->authorizeSupplierSelection($validated);

        $validated['items'] = $this->applyCartonEntry((int) $validated['supplier_id'], $validated['items']);

        $this->assertLineQuantitiesAddUp($validated['items']);
        $this->assertLineCostMatchesInvoice((int) $validated['supplier_id'], $validated['items']);
        $this->assertConversionFactorsAreUsual($request, $validated['items']);

        DB::beginTransaction();

        try {
            // Checked under a lock on the supplier, so two saves of the same invoice cannot both pass.
            $this->assertSupplierInvoiceIsNew($request, $validated);

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
                    'entered_cartons' => $item['entered_cartons'] ?? null,
                    'entered_loose_pieces' => $item['entered_loose_pieces'] ?? null,
                    'rate_per_th' => $item['rate_per_th'] ?? null,
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

        } catch (RequestValidationException $e) {
            DB::rollBack();

            throw $e;
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

        $goodsReceiptNote->load(['supplier', 'warehouse', 'receivedBy', 'verifiedBy', 'items.product', 'items.stockUom', 'items.purchaseUom', 'items.promotionalCampaign', 'corrections.correctedBy', 'corrections.lines.grnItem', 'corrections.lines.oldProduct', 'corrections.lines.newProduct']);

        return view('goods-receipt-notes.show', [
            'grn' => $goodsReceiptNote,
            'reversalRefusal' => $goodsReceiptNote->status === 'posted' && auth()->user()->can('goods-receipt-note-reverse')
                ? app(InventoryService::class)->grnReversalRefusal($goodsReceiptNote)
                : null,
            'grnLayout' => AppSetting::layoutFor('goods_receipt_notes')->value,
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

        $suppliers = $this->suppliersForGrnForm($userSupplierId);

        return view('goods-receipt-notes.edit', [
            'grn' => $goodsReceiptNote,
            'suppliers' => $suppliers,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
            'withholdingTaxRate' => $this->resolveActiveWithholdingTaxRate(),
            'grnLayout' => AppSetting::layoutFor('goods_receipt_notes')->value,
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
            'items.*.entered_cartons' => 'nullable|numeric|min:0',
            'items.*.entered_loose_pieces' => 'nullable|integer|min:0',
            'items.*.rate_per_th' => 'nullable|numeric|min:0',
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

        $this->authorizeSupplierSelection($validated);

        $validated['items'] = $this->applyCartonEntry((int) $validated['supplier_id'], $validated['items']);

        $this->assertLineQuantitiesAddUp($validated['items']);
        $this->assertLineCostMatchesInvoice((int) $validated['supplier_id'], $validated['items']);
        $this->assertConversionFactorsAreUsual($request, $validated['items'], $goodsReceiptNote->id);

        DB::beginTransaction();

        try {
            // Checked under a lock on the supplier, so two saves of the same invoice cannot both pass.
            $this->assertSupplierInvoiceIsNew($request, $validated, $goodsReceiptNote->id);

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
                    'entered_cartons' => $item['entered_cartons'] ?? null,
                    'entered_loose_pieces' => $item['entered_loose_pieces'] ?? null,
                    'rate_per_th' => $item['rate_per_th'] ?? null,
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

        } catch (RequestValidationException $e) {
            DB::rollBack();

            throw $e;
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
    /**
     * For carton-mode suppliers, derive pieces and cost server-side from cartons,
     * loose pieces and rate per TH. Pieces-mode suppliers are returned untouched.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function applyCartonEntry(int $supplierId, array $items): array
    {
        $supplier = Supplier::find($supplierId);

        if (! $supplier || ! $supplier->usesCartonEntry()) {
            foreach ($items as $index => $item) {
                $items[$index]['entered_cartons'] = null;
                $items[$index]['entered_loose_pieces'] = null;
                $items[$index]['rate_per_th'] = null;
            }

            return $items;
        }

        $service = app(CartonConversionService::class);

        foreach ($items as $index => $item) {
            $product = Product::find($item['product_id']);
            $cartons = (float) ($item['entered_cartons'] ?? 0);
            $loose = (int) ($item['entered_loose_pieces'] ?? 0);
            $rate = (float) ($item['rate_per_th'] ?? 0);

            try {
                $result = $service->convert($product, $cartons, $loose, $rate);
            } catch (\DomainException $e) {
                throw RequestValidationException::withMessages(["items.$index.entered_cartons" => $e->getMessage()]);
            }

            $discount = (float) ($item['discount_value'] ?? 0);
            $fmr = (float) ($item['fmr_allowance'] ?? 0);
            $beforeTax = round($result['total'] - $discount - $fmr, 2);
            $payable = round($beforeTax
                + (float) ($item['excise_duty'] ?? 0)
                + (float) ($item['sales_tax_value'] ?? 0)
                + (float) ($item['advance_income_tax'] ?? 0)
                + (float) ($item['other_charges'] ?? 0)
                + (float) ($item['withholding_tax'] ?? 0), 2);

            $items[$index] = array_merge($item, [
                'entered_cartons' => $cartons,
                'entered_loose_pieces' => $loose,
                'rate_per_th' => $rate,
                'qty_in_purchase_uom' => $cartons,
                'uom_conversion_factor' => $product->pieces_per_carton,
                'qty_in_stock_uom' => $result['pieces'],
                'quantity_received' => $result['pieces'],
                'quantity_accepted' => $result['pieces'],
                'extended_value' => $result['total'],
                'unit_price_per_case' => $cartons > 0 ? round($result['total'] / $cartons, 6) : null,
                'discounted_value_before_tax' => $beforeTax,
                'total_value_with_taxes' => $payable,
                // As on a pieces-mode line: the stock costs its value with taxes before FMR (FMR is income).
                'unit_cost' => $result['pieces'] > 0 ? round(($payable + $fmr) / $result['pieces'], 6) : $result['unit_cost'],
            ]);
        }

        return $items;
    }

    private function generateGRNNumber(): string
    {
        $year = now()->year;
        $prefix = "GRN-{$year}-";

        // Locked so two GRNs saved at the same time queue here instead of taking the same number.
        $lastGRN = GoodsReceiptNote::where('grn_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $sequence = $lastGRN ? ((int) str_replace($prefix, '', $lastGRN->grn_number)) + 1 : 1;

        return sprintf('%s%04d', $prefix, $sequence);
    }

    /**
     * Post GRN to inventory
     */
    public function post(PostGoodsReceiptNoteRequest $request, GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorizeGrnSupplierAccess($goodsReceiptNote);

        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->postGrnToInventory($goodsReceiptNote);

        // Posting also raises a draft invoice entry in the supplier Ledger Register
        // (see LedgerRegisterService::createDraftForGrn()).
        if ($result['success']) {
            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote->id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->with('error', $result['message']);
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
            // Checked under a lock on the supplier, so two saves of the same invoice cannot both pass.
            $this->assertSupplierInvoiceIsNew($request, $validated);

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

        } catch (RequestValidationException $e) {
            DB::rollBack();

            throw $e;
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
        $currentProductIds = $goodsReceiptNote->items->pluck('product_id');
        $products = Product::query()
            ->where('supplier_id', $goodsReceiptNote->supplier_id)
            ->where(function ($query) use ($currentProductIds) {
                $query->where('is_active', true)
                    ->orWhereIn('id', $currentProductIds);
            })
            ->orderBy('product_name')
            ->get(['id', 'product_code', 'product_name', 'supplier_id', 'uom_id', 'is_powder']);

        return view('goods-receipt-notes.edit-special', [
            'grn' => $goodsReceiptNote,
            'products' => $products,
        ]);
    }

    /**
     * Post a GRN Correction on a posted GRN — super admin only, with a reason and the user's password.
     *
     * The quantity difference is posted as a movement of its own on the GRN's receipt date and the old figures are
     * kept in grn_corrections; see GrnCorrectionService.
     */
    public function updateSpecial(Request $request, GoodsReceiptNote $goodsReceiptNote)
    {
        abort_unless(
            auth()->user()->is_super_admin === 'Yes' || auth()->user()->hasRole('super-admin'),
            403
        );

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
            'password' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:goods_receipt_note_items,id',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.uom_conversion_factor' => 'required|numeric|min:0.0001',
        ]);

        if (! Hash::check($validated['password'], auth()->user()->password)) {
            Log::warning("Failed GRN correction attempt on {$goodsReceiptNote->grn_number} - invalid password by user: ".auth()->user()->name);

            return back()->withInput($request->except('password'))->with('error', 'Invalid password. A GRN Correction needs your password confirmation.');
        }

        try {
            $result = app(GrnCorrectionService::class)->correct($goodsReceiptNote, $validated['items'], trim($validated['reason']));
        } catch (\Throwable $e) {
            Log::error('GRN correction failed', ['grn_id' => $goodsReceiptNote->id, 'error' => $e->getMessage()]);

            return back()->withInput($request->except('password'))->with('error', 'Correction failed: '.$e->getMessage());
        }

        $correction = $result['correction'];

        if (! $correction) {
            return back()->withInput($request->except('password'))->with('error', 'Nothing was changed: no line has a new product or conversion factor.');
        }

        $message = "{$correction->correction_number} posted.";

        if ($result['cost_jumps'] !== []) {
            $message .= ' Unit cost moved by more than 10% ('.implode('; ', $result['cost_jumps']).') — check it against the other receipts of the product.';
        }

        if ($correction->recosted_movements > 0) {
            $message .= sprintf(
                ' %d already-posted stock movement(s) were re-costed%s.',
                $correction->recosted_movements,
                $correction->journal_entry_id ? " and journal entry #{$correction->journal_entry_id} posts the cost difference (COGS {$this->signedAmount((float) $correction->cogs_delta)})" : ''
            );
        }

        return redirect()->route('goods-receipt-notes.show', $goodsReceiptNote)->with('success', $message);
    }

    private function signedAmount(float $amount): string
    {
        return ($amount >= 0 ? '+' : '−').number_format(abs($amount), 2);
    }

    /**
     * The same supplier invoice entered on two GRNs books the delivery twice. Refuse it unless the person says it is
     * one invoice delivered in parts. Reversed GRNs do not count.
     *
     * @param  array{supplier_id: int|string, supplier_invoice_number?: ?string}  $validated
     *
     * @throws RequestValidationException
     */
    private function assertSupplierInvoiceIsNew(Request $request, array $validated, ?int $exceptGrnId = null): void
    {
        $invoiceNumber = trim((string) ($validated['supplier_invoice_number'] ?? ''));

        if ($invoiceNumber === '' || $request->boolean('confirm_split_invoice')) {
            return;
        }

        Supplier::whereKey($validated['supplier_id'])->lockForUpdate()->first();

        $existing = GoodsReceiptNote::where('supplier_id', $validated['supplier_id'])
            ->where('supplier_invoice_number', $invoiceNumber)
            ->where('status', '!=', 'reversed')
            ->when($exceptGrnId, fn ($query, $id) => $query->whereKeyNot($id))
            ->value('grn_number');

        if ($existing) {
            throw RequestValidationException::withMessages([
                'supplier_invoice_number' => "Invoice {$invoiceNumber} of this supplier is already on {$existing}. If it is the same invoice delivered in parts, tick \"Same invoice, delivered in parts\" and save again.",
            ]);
        }
    }

    /**
     * The stock is carried at what the supplier's invoice line says it cost before FMR (FMR is income), so on a
     * pieces-mode line quantity × unit cost must equal the line's value with taxes plus its FMR allowance. A line
     * that disagrees would otherwise be absorbed by Round Off when the GRN is posted. Carton-mode lines are
     * costed by the server the same way, in applyCartonEntry().
     *
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws RequestValidationException
     */
    private function assertLineCostMatchesInvoice(int $supplierId, array $items): void
    {
        if (Supplier::find($supplierId)?->usesCartonEntry()) {
            return;
        }

        $errors = [];

        foreach ($items as $index => $item) {
            if (! isset($item['total_value_with_taxes']) || $item['total_value_with_taxes'] === '') {
                continue;
            }

            $lineCost = round((float) $item['quantity_received'] * (float) $item['unit_cost'], 2);
            $invoiceValue = round((float) $item['total_value_with_taxes'] + (float) ($item['fmr_allowance'] ?? 0), 2);

            if (abs($lineCost - $invoiceValue) > 1.0) {
                $errors["items.{$index}.unit_cost"] = sprintf(
                    'Line %d: quantity × unit cost is %s, but the line\'s value with taxes plus FMR is %s. Recheck the unit cost.',
                    $index + 1,
                    number_format($lineCost, 2),
                    number_format($invoiceValue, 2)
                );
            }
        }

        if ($errors !== []) {
            throw RequestValidationException::withMessages($errors);
        }
    }

    /**
     * A conversion factor unlike the one on the product's last posted GRN is the usual way a wrong quantity gets
     * posted (1 entered for a carton of 12), and it is costly to correct once stock is issued. Such lines are refused
     * until the person ticks that the factor is right.
     *
     * @param  array<int, array{product_id?: int|string, uom_conversion_factor?: float|int|string|null}>  $items
     *
     * @throws RequestValidationException
     */
    private function assertConversionFactorsAreUsual(Request $request, array $items, ?int $exceptGrnId = null): void
    {
        if ($request->boolean('confirm_unusual_factor')) {
            return;
        }

        $unusual = [];

        foreach ($items as $index => $item) {
            if (empty($item['product_id']) || ! isset($item['uom_conversion_factor']) || $item['uom_conversion_factor'] === '') {
                continue;
            }

            $lastFactor = DB::table('goods_receipt_note_items as item')
                ->join('goods_receipt_notes as grn', 'grn.id', '=', 'item.grn_id')
                ->where('grn.status', 'posted')
                ->where('item.product_id', $item['product_id'])
                ->when($exceptGrnId, fn ($query, $id) => $query->where('grn.id', '!=', $id))
                ->orderByDesc('grn.receipt_date')
                ->orderByDesc('item.id')
                ->value('item.uom_conversion_factor');

            if ($lastFactor !== null && abs((float) $lastFactor - (float) $item['uom_conversion_factor']) > 0.0001) {
                $unusual[] = sprintf('line %d is %s, the last GRN of this product used %s', $index + 1, (float) $item['uom_conversion_factor'], (float) $lastFactor);
            }
        }

        if ($unusual !== []) {
            throw RequestValidationException::withMessages([
                'confirm_unusual_factor' => 'Check the conversion factor: '.implode('; ', $unusual).'. If it is right, tick "The conversion factor is right" and save again.',
            ]);
        }
    }

    /**
     * Stock is posted from quantity_accepted, so it may not exceed what was received, and accepted plus rejected
     * must account for everything received.
     *
     * @param  array<int, array{quantity_received: float|int|string, quantity_accepted: float|int|string, quantity_rejected?: float|int|string|null}>  $items
     *
     * @throws RequestValidationException
     */
    private function assertLineQuantitiesAddUp(array $items): void
    {
        $errors = [];

        foreach ($items as $index => $item) {
            // Compared as stored: the quantity columns keep 2 decimals.
            $received = round((float) $item['quantity_received'], 2);
            $accepted = round((float) $item['quantity_accepted'], 2);
            $rejected = round((float) ($item['quantity_rejected'] ?? 0), 2);

            if ($accepted - $received > 0.0001) {
                $errors["items.{$index}.quantity_accepted"] = 'Line '.($index + 1).': accepted quantity cannot be more than the quantity received.';
            } elseif (abs($accepted + $rejected - $received) > 0.0001) {
                $errors["items.{$index}.quantity_rejected"] = 'Line '.($index + 1).': accepted plus rejected quantity must equal the quantity received.';
            }
        }

        if ($errors !== []) {
            throw RequestValidationException::withMessages($errors);
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
     * A supplier-scoped user may only enter GRNs for their own supplier, with that supplier's products.
     *
     * @param  array{supplier_id: int|string, items?: array<int, array{product_id?: int|string}>}  $validated
     */
    private function authorizeSupplierSelection(array $validated): void
    {
        $userSupplierId = $this->getUserSupplierScope();

        if (! $userSupplierId) {
            return;
        }

        if ((int) $validated['supplier_id'] !== $userSupplierId) {
            abort(403, 'You do not have permission to create GRNs for this supplier.');
        }

        $productIds = collect($validated['items'] ?? [])->pluck('product_id')->filter()->unique();

        $foreignProducts = Product::withTrashed()->whereIn('id', $productIds)
            ->where(fn ($query) => $query->where('supplier_id', '!=', $userSupplierId)->orWhereNull('supplier_id'))
            ->exists();

        if ($foreignProducts) {
            abort(403, 'You can only add products of your own supplier to this GRN.');
        }
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

    /**
     * The active suppliers a GRN can be raised for, with what the GRN form needs from each:
     * sales tax rate, FMR and carton flags, and the supplier's GRN line column order.
     *
     * @return EloquentCollection<int, Supplier>
     */
    private function suppliersForGrnForm(?int $userSupplierId): EloquentCollection
    {
        return Supplier::query()
            ->when($userSupplierId, fn ($query) => $query->where('id', $userSupplierId))
            ->where('disabled', false)
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name', 'sales_tax', 'is_fmr_allowed', 'qty_entry_mode', 'grn_line_columns'])
            ->each(fn (Supplier $supplier) => $supplier->setAttribute('grn_columns', $supplier->grnLineColumns())->makeHidden('grn_line_columns'));
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

        $inventoryService = app(InventoryService::class);

        if ($refusal = $inventoryService->grnReversalRefusal($goodsReceiptNote)) {
            return redirect()->back()->with('error', $refusal);
        }

        $result = $inventoryService->reverseGrnInventory($goodsReceiptNote);

        if ($result['success']) {
            return redirect()
                ->route('goods-receipt-notes.show', $goodsReceiptNote->id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->with('error', $result['message']);
    }
}
