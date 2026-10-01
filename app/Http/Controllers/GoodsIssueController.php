<?php

namespace App\Http\Controllers;

use App\Exports\GoodsIssueExport;
use App\Http\Requests\AppendGoodsIssueItemsRequest;
use App\Http\Requests\StoreGoodsIssueRequest;
use App\Http\Requests\UpdateGoodsIssueRequest;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use App\Services\GoodsIssueReversalService;
use App\Services\GoodsIssueStockCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GoodsIssueController extends Controller implements HasMiddleware
{
    /** Rows-per-page choices on the list. */
    public const PER_PAGE = [20, 50, 100, 500, 'all'];

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:goods-issue-list', only: ['index', 'show']),
            new Middleware('permission:goods-issue-create', only: ['create', 'store']),
            new Middleware('permission:goods-issue-edit', only: ['edit', 'update', 'appendItemsForm', 'appendItems']),
            new Middleware('permission:goods-issue-delete', only: ['destroy']),
            new Middleware('permission:goods-issue-post', only: ['post']),
            new Middleware('permission:goods-issue-reverse', only: ['reverse']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userSupplierId = $this->getUserSupplierScope();

        if (! $request->filled('filter.issue_date_from') && ! $request->filled('filter.issue_date_to')) {
            $request->merge([
                'filter' => array_merge($request->input('filter', []), [
                    'issue_date_from' => now()->toDateString(),
                    'issue_date_to' => now()->toDateString(),
                ]),
            ]);
        }

        $this->authorizeGoodsIssueFilterAccess($request, $userSupplierId);

        $perPage = (string) $request->input('per_page', '20');
        $perPage = in_array($perPage, array_map('strval', self::PER_PAGE), true) ? $perPage : '20';

        /** Issues this user may see: own issues without view-all, own supplier when scoped. */
        $visible = function () use ($userSupplierId) {
            return GoodsIssue::query()
                ->when(! auth()->user()->can('goods-issue-view-all'), fn ($query) => $query->where('goods_issues.issued_by', auth()->id()))
                ->when($userSupplierId, fn ($query, $supplierId) => $query->where('goods_issues.supplier_id', $supplierId));
        };

        $allowedFilters = [
            AllowedFilter::partial('issue_number'),
            AllowedFilter::exact('warehouse_id'),
            AllowedFilter::exact('vehicle_id'),
            AllowedFilter::exact('employee_id'),
            AllowedFilter::exact('supplier_id'),
            AllowedFilter::exact('status'),
            AllowedFilter::scope('issue_date_from'),
            AllowedFilter::scope('issue_date_to'),
            AllowedFilter::exact('issue_date'),
            AllowedFilter::callback('product_id', function ($query, $value) {
                $query->whereHas('items', function ($q) use ($value) {
                    $q->where('product_id', $value);
                });
            }),
            AllowedFilter::callback('search', function ($query, $value) {
                $term = '%'.mb_strtolower(trim((string) $value)).'%';
                $query->where(fn ($q) => $q
                    ->whereRaw('LOWER(issue_number) LIKE ?', [$term])
                    ->orWhereHas('vehicle', fn ($v) => $v->whereRaw('LOWER(vehicle_number) LIKE ?', [$term]))
                    ->orWhereHas('employee', fn ($e) => $e->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(employee_code) LIKE ?', [$term])));
            }),
            // Settlement: "pending" = issued but no verified/posted settlement yet; "settled" = has one.
            AllowedFilter::callback('settlement', function ($query, $value) {
                $finalized = fn ($q) => $q->whereIn('status', ['verified', 'posted']);
                if ($value === 'pending') {
                    $query->where('status', 'issued')->whereDoesntHave('settlement', $finalized);
                } elseif ($value === 'settled') {
                    $query->whereHas('settlement', $finalized);
                }
            }),
        ];
        $allowedSorts = ['issue_date', 'issue_number', 'total_value', 'created_at'];

        $goodsIssues = QueryBuilder::for($visible()->with(['warehouse', 'vehicle', 'employee', 'supplier', 'issuedBy', 'settlement'])->withCount('items'))
            ->allowedFilters($allowedFilters)
            ->allowedSorts($allowedSorts)
            ->defaultSort('-issue_date', '-id');

        if ($request->input('export') === 'xlsx') {
            $period = $request->input('filter.issue_date_from').'_to_'.$request->input('filter.issue_date_to');

            return Excel::download(new GoodsIssueExport($goodsIssues->getEloquentBuilder()->clone()), "goods-issues-{$period}.xlsx");
        }

        // Row preview (the lines of each issue) is loaded only for normal page sizes.
        if ($perPage !== 'all' && (int) $perPage <= 100) {
            $goodsIssues->with(['items' => fn ($query) => $query->orderBy('line_no'), 'items.product:id,product_name,product_code,uom_conversion_factor']);
        }

        if ($perPage === 'all') {
            $count = $goodsIssues->getEloquentBuilder()->clone()->reorder()->count();
            $goodsIssues = $goodsIssues->paginate(max($count, 1))->withQueryString();
        } else {
            $goodsIssues = $goodsIssues->paginate((int) $perPage)->withQueryString();
        }

        // Card and tab counts use every filter except status / settlement, so they show what each tab would hold.
        $statsRequest = new Request(['filter' => Arr::except((array) $request->input('filter', []), ['status', 'settlement'])]);
        $statsQuery = fn () => QueryBuilder::for($visible(), $statsRequest)->allowedFilters($allowedFilters)->getEloquentBuilder();
        $byStatus = $statsQuery()->reorder()->groupBy('status')->selectRaw('status, COUNT(*) as issues, COALESCE(SUM(total_value), 0) as value')->get()->keyBy('status');
        $pending = $statsQuery()->reorder()->where('status', 'issued')
            ->whereDoesntHave('settlement', fn ($q) => $q->whereIn('status', ['verified', 'posted']))
            ->selectRaw('COUNT(*) as issues, COALESCE(SUM(total_value), 0) as value')->first();
        $stats = [
            'total' => (int) $byStatus->sum('issues'),
            'draft' => (int) ($byStatus['draft']->issues ?? 0),
            'issued' => (int) ($byStatus['issued']->issues ?? 0),
            'cancelled' => (int) ($byStatus['cancelled']->issues ?? 0),
            'issued_value' => (float) ($byStatus['issued']->value ?? 0),
            'draft_value' => (float) ($byStatus['draft']->value ?? 0),
            'pending' => (int) ($pending->issues ?? 0),
            'pending_value' => (float) ($pending->value ?? 0),
        ];

        // Salesman-wise summary of what the filters show (reversed issues left out).
        $bySalesman = QueryBuilder::for($visible())
            ->allowedFilters($allowedFilters)
            ->getEloquentBuilder()
            ->reorder()
            ->where('goods_issues.status', '!=', 'cancelled')
            ->join('employees', 'employees.id', '=', 'goods_issues.employee_id')
            ->groupBy('goods_issues.employee_id', 'employees.name')
            ->selectRaw('goods_issues.employee_id, employees.name, COUNT(*) as issues, COALESCE(SUM(goods_issues.total_value), 0) as value')
            ->selectRaw("SUM(CASE WHEN goods_issues.status = 'issued' AND NOT EXISTS (SELECT 1 FROM sales_settlements ss WHERE ss.goods_issue_id = goods_issues.id AND ss.status IN ('verified', 'posted') AND ss.deleted_at IS NULL) THEN 1 ELSE 0 END) as pending")
            ->orderByDesc('value')
            ->get();

        $totalValue = QueryBuilder::for($visible())
            ->allowedFilters($allowedFilters)
            ->getEloquentBuilder()
            ->reorder()
            ->sum('total_value');

        return view('goods-issues.index', [
            'totalValue' => $totalValue,
            'goodsIssues' => $goodsIssues,
            'stats' => $stats,
            'bySalesman' => $bySalesman,
            'perPage' => $perPage,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('vehicle_number')->get(['id', 'vehicle_number', 'vehicle_type']),
            'employees' => Employee::where('is_active', true)
                ->when($userSupplierId, fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
                ->orderBy('name')->get(['id', 'name', 'employee_code']),
            'suppliers' => Supplier::query()
                ->where('disabled', false)
                ->when($userSupplierId, fn ($query, $supplierId) => $query->where('id', $supplierId))
                ->orderBy('supplier_name')
                ->get(['id', 'supplier_name']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $userSupplierId = $this->getUserSupplierScope();

        return view('goods-issues.create', [
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'suppliers' => Supplier::query()
                ->where('disabled', false)
                ->when($userSupplierId, fn ($query, $supplierId) => $query->where('id', $supplierId))
                ->orderBy('supplier_name')
                ->get(['id', 'supplier_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
            'canEnterCartons' => auth()->user()->can('goods-issue-carton-entry'),
        ]);
    }

    /**
     * Get product stock details for a specific warehouse (AJAX endpoint)
     * Returns selling price from the first priority stock layer with batch breakdown
     * Database-agnostic: Works with PostgreSQL, MySQL, and MariaDB
     */
    public function getProductStock(Request $request, $warehouseId, $productId)
    {
        $this->authorizeProductSupplierScope((int) $productId);

        $excludePromotional = $request->boolean('exclude_promotional');

        // Get total available quantity
        $totalStockQuery = DB::table('stock_valuation_layers')
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0);

        if ($excludePromotional) {
            $totalStockQuery->where('is_promotional', false);
        }

        $totalStock = $totalStockQuery->sum('quantity_remaining');

        // Calculate urgent date threshold (30 days from now)
        $urgentDate = now()->addDays(30)->toDateString();

        // Get ALL available stock layers ordered by priority
        // Priority Logic (STRICT ORDER):
        // 1) URGENT EXPIRY: Items expiring within 30 days (urgency_level = 1)
        // 2) PRIORITY ORDER: Lower numbers first (1 = Urgent, 99 = Normal FIFO)
        // 3) FIFO: Oldest receipt date first
        $stockLayersQuery = DB::table('stock_valuation_layers as svl')
            // Left join: a layer without a grn_item_id still counts towards the
            // available total above, so an inner join here would show stock that
            // can never be picked.
            ->leftJoin('goods_receipt_note_items as grni', 'svl.grn_item_id', '=', 'grni.id')
            ->leftJoin('stock_batches as sb', 'svl.stock_batch_id', '=', 'sb.id')
            ->where('svl.warehouse_id', $warehouseId)
            ->where('svl.product_id', $productId)
            ->where('svl.is_depleted', false)
            ->where('svl.quantity_remaining', '>', 0);

        if ($excludePromotional) {
            $stockLayersQuery->where('svl.is_promotional', false);
        }

        $stockLayers = $stockLayersQuery->selectRaw('
                COALESCE(grni.selling_price, sb.selling_price) as selling_price,
                svl.unit_cost,
                svl.priority_order,
                svl.receipt_date,
                svl.must_sell_before,
                svl.quantity_remaining,
                svl.is_promotional,
                sb.batch_code,
                CASE 
                    WHEN svl.must_sell_before IS NOT NULL AND svl.must_sell_before <= ? THEN 1
                    ELSE 2
                END as urgency_level
            ', [$urgentDate])
            ->orderByRaw('urgency_level ASC')      // 1st: Urgent items first
            ->orderBy('svl.priority_order', 'asc')  // 2nd: Priority (1, 2, 3...99)
            ->orderBy('svl.receipt_date', 'asc')    // 3rd: FIFO (oldest first)
            ->get();

        // Get the first layer for default price
        $firstLayer = $stockLayers->first();

        $product = Product::with(['uom', 'salesUom'])->find($productId);

        $position = app(GoodsIssueStockCheck::class)->positions(
            [(object) ['product_id' => $productId, 'quantity_issued' => 0]],
            (int) $warehouseId,
            $request->integer('goods_issue_id') ?: null
        )->first();

        // Format batch breakdown for display
        $batches = $stockLayers->map(function ($layer) {
            return [
                'batch_code' => $layer->batch_code ?? 'N/A',
                'quantity' => (float) $layer->quantity_remaining,
                'selling_price' => (float) $layer->selling_price,
                'unit_cost' => (float) $layer->unit_cost,
                'is_promotional' => (bool) $layer->is_promotional,
                'priority' => (int) $layer->priority_order,
            ];
        })->toArray();

        return response()->json([
            'available_quantity' => $totalStock ?? 0,
            'selling_price' => $firstLayer->selling_price ?? 0,
            'unit_cost' => $firstLayer->unit_cost ?? 0,
            'stock_uom_id' => $product->uom_id ?? null,
            'stock_uom_name' => $product->uom->uom_name ?? 'Piece',
            'sales_uom_id' => $product->sales_uom_id ?? null,
            'sales_uom_name' => $product->salesUom->uom_name ?? null,
            'conversion_factor' => (float) ($product->uom_conversion_factor ?? 1),
            'batches' => $batches,
            'has_multiple_prices' => $stockLayers->pluck('selling_price')->unique()->count() > 1,
            // Drafts do not hold stock, so the form shows what other drafts are counting on too.
            'other_drafts' => $position['other_drafts'],
            'in_other_drafts' => $position['in_other_drafts'],
            'product_name' => $product->product_name ?? '',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGoodsIssueRequest $request)
    {
        $userSupplierId = $this->getUserSupplierScope();
        $selectedSupplierIds = $this->normalizeSupplierIds($request->input('supplier_ids', []));
        $this->ensureAuthorizedSupplierIds($selectedSupplierIds);

        DB::beginTransaction();

        try {
            // Generate issue number
            $issueNumber = $this->generateIssueNumber();

            // Calculate totals
            $totalValue = 0;
            $totalQuantity = 0;
            foreach ($request->items as $item) {
                $totalValue += $item['quantity_issued'] * $item['selling_price'];
                $totalQuantity += $item['quantity_issued'];
            }

            // Get supplier_id from form submission, fallback to employee's supplier
            $supplierId = $request->input('supplier_ids.0') ?? Employee::findOrFail($request->employee_id)->supplier_id;

            if ($userSupplierId && (int) $supplierId !== $userSupplierId) {
                return back()
                    ->withInput()
                    ->with('error', 'You do not have permission to create a goods issue for this supplier.');
            }

            // Create goods issue
            $goodsIssue = GoodsIssue::create([
                'issue_number' => $issueNumber,
                'issue_date' => $request->issue_date,
                'warehouse_id' => $request->warehouse_id,
                'vehicle_id' => $request->vehicle_id,
                'employee_id' => $request->employee_id,
                'supplier_id' => $supplierId,
                'issued_by' => auth()->id(),
                // Resolve default GL accounts from COA codes for this row (stored on the record)
                'stock_in_hand_account_id' => optional(ChartOfAccount::where('account_code', '1151')->first())->id,
                'van_stock_account_id' => optional(ChartOfAccount::where('account_code', '1155')->first())->id,
                'status' => 'draft',
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue,
                'notes' => $request->notes,
            ]);

            // Create line items
            foreach ($request->items as $index => $item) {
                GoodsIssueItem::create([
                    'goods_issue_id' => $goodsIssue->id,
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'quantity_issued' => $item['quantity_issued'],
                    'unit_cost' => $item['unit_cost'],
                    'selling_price' => $item['selling_price'],
                    'uom_id' => $item['uom_id'],
                    'total_value' => $item['quantity_issued'] * $item['selling_price'],
                    'exclude_promotional' => (bool) ($item['exclude_promotional'] ?? false),
                ]);
            }

            DB::commit();

            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('success', "Goods Issue '{$goodsIssue->issue_number}' created successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating Goods Issue', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to create Goods Issue: '.$e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(GoodsIssue $goodsIssue)
    {
        $this->authorizeGoodsIssueSupplierAccess($goodsIssue);

        $goodsIssue->load([
            'warehouse',
            'vehicle',
            'employee',
            'supplier',
            'issuedBy',
            'stockInHandAccount',
            'vanStockAccount',
            'items.product',
            'items.uom',
            'reversedBy',
            'replaces',
            'replacement',
        ]);

        foreach ($goodsIssue->items as $item) {
            if (in_array($goodsIssue->status, ['issued', 'cancelled'], true) && $goodsIssue->posted_at) {
                // For posted goods issues, get ACTUAL batch breakdown from stock movements.
                // Filter by goods_issue_item_id so multi-line GIs (same product on
                // multiple lines) only return the movements that belong to *this* line.
                $stockMovements = DB::table('stock_movements as sm')
                    ->join('stock_batches as sb', 'sm.stock_batch_id', '=', 'sb.id')
                    ->where('sm.reference_type', 'App\Models\GoodsIssue')
                    ->where('sm.reference_id', $goodsIssue->id)
                    ->where('sm.goods_issue_item_id', $item->id)
                    ->where('sm.movement_type', 'transfer')
                    // A reversed issue also carries the offsetting rows; the note shows what was issued.
                    ->where('sm.quantity', '<', 0)
                    ->select(
                        'sb.batch_code',
                        DB::raw('ABS(sm.quantity) as quantity'),
                        'sb.selling_price',
                        'sb.is_promotional'
                    )
                    ->orderBy('sb.priority_order', 'asc')
                    ->get();

                $batchBreakdown = [];
                foreach ($stockMovements as $movement) {
                    $quantity = (float) $movement->quantity;
                    $sellingPrice = (float) $movement->selling_price;
                    $value = $quantity * $sellingPrice;

                    $batchBreakdown[] = [
                        'batch_code' => $movement->batch_code ?? 'N/A',
                        'quantity' => $quantity,
                        'selling_price' => $sellingPrice,
                        'value' => $value,
                        'is_promotional' => (bool) $movement->is_promotional,
                    ];
                }

                $item->batch_breakdown = $batchBreakdown;
                $item->calculated_total = collect($batchBreakdown)->sum('value');
            } else {
                // For draft goods issues, show THEORETICAL batch breakdown
                $urgentDate = now()->addDays(30)->toDateString();

                $stockLayersQuery = DB::table('stock_valuation_layers as svl')
                    // Left join, as in stockLayers() above: a layer without a
                    // grn_item_id must still be listed.
                    ->leftJoin('goods_receipt_note_items as grni', 'svl.grn_item_id', '=', 'grni.id')
                    ->leftJoin('stock_batches as sb', 'svl.stock_batch_id', '=', 'sb.id')
                    ->where('svl.warehouse_id', $goodsIssue->warehouse_id)
                    ->where('svl.product_id', $item->product_id)
                    ->where('svl.is_depleted', false)
                    ->where('svl.quantity_remaining', '>', 0);

                if ($item->exclude_promotional) {
                    $stockLayersQuery->where('svl.is_promotional', false);
                }

                $stockLayers = $stockLayersQuery->selectRaw('
                        COALESCE(grni.selling_price, sb.selling_price) as selling_price,
                        svl.unit_cost,
                        svl.priority_order,
                        svl.quantity_remaining,
                        svl.is_promotional,
                        sb.batch_code,
                        CASE 
                            WHEN svl.must_sell_before IS NOT NULL AND svl.must_sell_before <= ? THEN 1
                            ELSE 2
                        END as urgency_level
                    ', [$urgentDate])
                    ->orderByRaw('urgency_level ASC')
                    ->orderBy('svl.priority_order', 'asc')
                    ->orderBy('svl.receipt_date', 'asc')
                    ->get();

                // Calculate which batches would be used for this quantity
                $remainingQty = $item->quantity_issued;
                $batchBreakdown = [];

                foreach ($stockLayers as $layer) {
                    if ($remainingQty <= 0) {
                        break;
                    }

                    $qtyFromBatch = min($remainingQty, $layer->quantity_remaining);
                    $batchValue = $qtyFromBatch * $layer->selling_price;

                    $batchBreakdown[] = [
                        'batch_code' => $layer->batch_code ?? 'N/A',
                        'quantity' => (float) $qtyFromBatch,
                        'selling_price' => (float) $layer->selling_price,
                        'value' => (float) $batchValue,
                        'is_promotional' => (bool) $layer->is_promotional,
                    ];

                    $remainingQty -= $qtyFromBatch;
                }

                $item->batch_breakdown = $batchBreakdown;
                $item->calculated_total = collect($batchBreakdown)->sum('value');
            }
        }

        $goodsIssue->load(['settlement' => fn ($query) => $query->orderBy('settlement_date')]);

        // Journal entries this issue posted: the main transfer, any supplementary (-S1, -S2 ...)
        // entries, and the REV- entries that offset them when the issue was reversed.
        $journalEntries = JournalEntry::query()
            ->where(fn ($query) => $query
                ->where('reference', $goodsIssue->issue_number)
                ->orWhere('reference', 'like', $goodsIssue->issue_number.'-S%')
                ->orWhere('reference', 'REV-'.$goodsIssue->issue_number)
                ->orWhere('reference', 'like', 'REV-'.$goodsIssue->issue_number.'-S%'))
            ->withSum('details as total_debit', 'debit')
            ->orderBy('id')
            ->get(['id', 'reference', 'entry_date', 'status', 'description'])
            ->filter(fn (JournalEntry $entry) => $goodsIssue->ownsJournalReference(preg_replace('/^REV-/', '', (string) $entry->reference)))
            ->values();

        return view('goods-issues.show', [
            'goodsIssue' => $goodsIssue,
            'journalEntries' => $journalEntries,
            'reversalRefusal' => $goodsIssue->status === 'issued' && auth()->user()->can('goods-issue-reverse')
                ? app(GoodsIssueReversalService::class)->refusal($goodsIssue)
                : null,
            'stockPositions' => $goodsIssue->status === 'draft'
                ? app(GoodsIssueStockCheck::class)->positions(
                    $goodsIssue->items,
                    (int) $goodsIssue->warehouse_id,
                    $goodsIssue->id,
                    $goodsIssue->issue_date?->toDateString()
                )
                : collect(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GoodsIssue $goodsIssue)
    {
        $this->authorizeGoodsIssueSupplierAccess($goodsIssue);

        if ($goodsIssue->status !== 'draft') {
            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('error', 'Only draft Goods Issues can be edited.');
        }

        $goodsIssue->load('items');

        $userSupplierId = $this->getUserSupplierScope();

        return view('goods-issues.edit', [
            'goodsIssue' => $goodsIssue,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'suppliers' => Supplier::query()
                ->where('disabled', false)
                ->when($userSupplierId, fn ($query, $supplierId) => $query->where('id', $supplierId))
                ->orderBy('supplier_name')
                ->get(['id', 'supplier_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
            'canEnterCartons' => auth()->user()->can('goods-issue-carton-entry'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGoodsIssueRequest $request, GoodsIssue $goodsIssue)
    {
        $this->authorizeGoodsIssueSupplierAccess($goodsIssue);

        if ($goodsIssue->status !== 'draft') {
            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('error', 'Only draft Goods Issues can be updated.');
        }

        DB::beginTransaction();

        try {
            // Calculate totals
            $totalValue = 0;
            $totalQuantity = 0;
            foreach ($request->items as $item) {
                $totalValue += $item['quantity_issued'] * $item['selling_price'];
                $totalQuantity += $item['quantity_issued'];
            }

            // Get supplier_id from employee
            $employee = Employee::findOrFail($request->employee_id);
            $userSupplierId = $this->getUserSupplierScope();

            if ($userSupplierId && (int) $employee->supplier_id !== $userSupplierId) {
                return back()
                    ->withInput()
                    ->with('error', 'You do not have permission to update goods issues for this supplier.');
            }

            // Update goods issue
            $goodsIssue->update([
                'issue_date' => $request->issue_date,
                'warehouse_id' => $request->warehouse_id,
                'vehicle_id' => $request->vehicle_id,
                'employee_id' => $request->employee_id,
                'supplier_id' => $employee->supplier_id,
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue,
                'notes' => $request->notes,
            ]);

            // Delete old items and create new ones
            $goodsIssue->items()->delete();

            foreach ($request->items as $index => $item) {
                GoodsIssueItem::create([
                    'goods_issue_id' => $goodsIssue->id,
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'quantity_issued' => $item['quantity_issued'],
                    'unit_cost' => $item['unit_cost'],
                    'selling_price' => $item['selling_price'],
                    'uom_id' => $item['uom_id'],
                    'total_value' => $item['quantity_issued'] * $item['selling_price'],
                    'exclude_promotional' => (bool) ($item['exclude_promotional'] ?? false),
                ]);
            }

            DB::commit();

            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('success', "Goods Issue '{$goodsIssue->issue_number}' updated successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating Goods Issue', [
                'goods_issue_id' => $goodsIssue->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update Goods Issue. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GoodsIssue $goodsIssue)
    {
        $this->authorizeGoodsIssueSupplierAccess($goodsIssue);

        if ($goodsIssue->status !== 'draft') {
            return back()->with('error', 'Only draft Goods Issues can be deleted.');
        }

        DB::beginTransaction();

        try {
            $issueNumber = $goodsIssue->issue_number;
            $goodsIssue->items()->delete();
            $goodsIssue->delete();

            DB::commit();

            return redirect()
                ->route('goods-issues.index')
                ->with('success', "Goods Issue '{$issueNumber}' deleted successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Unable to delete Goods Issue.');
        }
    }

    /**
     * Reverse a posted goods issue and open the draft copied from it, so a wrong
     * salesman, van or quantity is corrected without editing a posted document.
     */
    public function reverse(Request $request, GoodsIssue $goodsIssue): RedirectResponse
    {
        $this->authorizeGoodsIssueSupplierAccess($goodsIssue);

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
            'password' => 'required|string',
        ]);

        if (! Hash::check($validated['password'], auth()->user()->password)) {
            Log::warning("Failed GI reversal attempt for {$goodsIssue->issue_number} - invalid password by user: ".auth()->user()->name);

            return back()->with('error', 'Invalid password. Reversing a goods issue requires your password confirmation.');
        }

        $result = app(GoodsIssueReversalService::class)->reverse($goodsIssue, $validated['reason']);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()
            ->route('goods-issues.edit', $result['replacement'])
            ->with('success', $result['message']);
    }

    /**
     * Post goods issue to transfer inventory from warehouse to vehicle
     */
    public function post(GoodsIssue $goodsIssue)
    {
        $this->authorizeGoodsIssueSupplierAccess($goodsIssue);

        if ($goodsIssue->status !== 'draft') {
            return back()->with('error', 'Only draft Goods Issues can be posted.');
        }

        $distributionService = app(DistributionService::class);
        $result = $distributionService->postGoodsIssue($goodsIssue);

        if ($result['success']) {
            return redirect()
                ->route('goods-issues.show', $goodsIssue->id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->with('error', $result['message']);
    }

    /**
     * Check if a vehicle has an existing unsettled Goods Issue.
     * Returns whether the user can append items to that GI or must create a new one.
     *
     * Mirrors the canonical block logic in StoreGoodsIssueRequest: a GI only
     * obstructs new issues when its workflow is incomplete — i.e. it is `issued`
     * AND has no settlement, or its settlement is `draft`/`verified`. Once the
     * settlement is `posted`, the workflow is complete and the GI is ignored.
     */
    public function checkVehicleGoodsIssue(Request $request): JsonResponse
    {
        $vehicleId = $request->query('vehicle_id');

        if (! $vehicleId) {
            return response()->json(['has_existing' => false]);
        }

        $this->authorizeVehicleSupplierScope((int) $vehicleId);

        $existing = DB::table('goods_issues')
            ->select(
                'goods_issues.id',
                'goods_issues.issue_number',
                'goods_issues.status',
                'goods_issues.employee_id',
                'employees.name as employee_name',
                'sales_settlements.id as settlement_id',
                'sales_settlements.settlement_number',
                'sales_settlements.status as settlement_status',
                'vehicles.vehicle_number'
            )
            ->leftJoin('sales_settlements', function ($join) {
                $join->on('sales_settlements.goods_issue_id', '=', 'goods_issues.id')
                    ->whereNull('sales_settlements.deleted_at');
            })
            ->leftJoin('vehicles', 'vehicles.id', '=', 'goods_issues.vehicle_id')
            ->leftJoin('employees', 'employees.id', '=', 'goods_issues.employee_id')
            ->where('goods_issues.vehicle_id', $vehicleId)
            ->whereNull('goods_issues.deleted_at')
            ->whereIn('goods_issues.status', ['draft', 'issued'])
            ->where(function ($q) {
                // Draft GIs are always considered (no settlement possible yet).
                // Issued GIs are only considered when their settlement workflow
                // is incomplete — posted settlements mean the GI is finished
                // and should not interfere with new issues for this vehicle.
                $q->where('goods_issues.status', 'draft')
                    ->orWhereNull('sales_settlements.id')
                    ->orWhereIn('sales_settlements.status', ['draft', 'verified']);
            })
            ->orderByDesc('goods_issues.id')
            ->first();

        if (! $existing) {
            return response()->json(['has_existing' => false]);
        }

        $vehicleLabel = $existing->vehicle_number ?? "Vehicle #{$vehicleId}";
        $settlementStatus = $existing->settlement_status;

        // Block only when the settlement has been verified — at that point the
        // settlement is locked in and the GI cannot accept further items.
        if ($settlementStatus === 'verified') {
            return response()->json([
                'has_existing' => true,
                'can_append' => false,
                'message' => "Cannot create a Goods Issue for vehicle {$vehicleLabel}: settlement {$existing->settlement_number} (Verified) for {$existing->issue_number} is awaiting posting. Post the settlement before issuing new stock.",
            ]);
        }

        // Draft GI: append flow is NOT allowed (the duplicate-product guard in
        // the edit form would conflict). Direct the user to edit the draft.
        if ($existing->status === 'draft') {
            return response()->json([
                'has_existing' => true,
                'can_append' => false,
                'is_draft' => true,
                'goods_issue_id' => $existing->id,
                'issue_number' => $existing->issue_number,
                'status' => $existing->status,
                'vehicle_label' => $vehicleLabel,
                'existing_employee_id' => $existing->employee_id,
                'existing_employee_name' => $existing->employee_name,
                'redirect_url' => route('goods-issues.edit', $existing->id),
            ]);
        }

        // Issued GI: allow append (either no settlement, or only a draft settlement exists).
        return response()->json([
            'has_existing' => true,
            'can_append' => true,
            'has_draft_settlement' => $settlementStatus === 'draft',
            'goods_issue_id' => $existing->id,
            'issue_number' => $existing->issue_number,
            'status' => $existing->status,
            'settlement_number' => $existing->settlement_number,
            'vehicle_label' => $vehicleLabel,
            'existing_employee_id' => $existing->employee_id,
            'existing_employee_name' => $existing->employee_name,
            'redirect_url' => route('goods-issues.append-items', $existing->id),
        ]);
    }

    /**
     * Show the form to append additional items to an existing Goods Issue.
     */
    public function appendItemsForm(GoodsIssue $goodsIssue)
    {
        $this->authorizeGoodsIssueSupplierAccess($goodsIssue);

        if (! $goodsIssue->canAcceptSupplementaryItems()) {
            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('error', 'This Goods Issue cannot accept supplementary items.');
        }

        $goodsIssue->load([
            'warehouse',
            'vehicle',
            'employee',
            'supplier',
            'items.product',
            'items.uom',
        ]);

        $draftSettlement = $goodsIssue->settlement()->where('status', 'draft')->first();
        $uoms = Uom::orderBy('uom_name')->get();
        $canEnterCartons = auth()->user()->can('goods-issue-enter-cartons');

        return view('goods-issues.append-items', [
            'goodsIssue' => $goodsIssue,
            'draftSettlement' => $draftSettlement,
            'uoms' => $uoms,
            'canEnterCartons' => $canEnterCartons,
        ]);
    }

    /**
     * Persist supplementary items for an existing Goods Issue.
     * If the GI is already 'issued', new items are posted immediately via the supplementary
     * posting flow (stock movements, van stock, journal entry). If the GI is still 'draft',
     * items are saved and will be posted when the user posts the GI normally.
     */
    public function appendItems(AppendGoodsIssueItemsRequest $request, GoodsIssue $goodsIssue)
    {
        $this->authorizeGoodsIssueSupplierAccess($goodsIssue);

        if (! $goodsIssue->canAcceptSupplementaryItems()) {
            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('error', 'This Goods Issue cannot accept supplementary items.');
        }

        // Saving the lines and posting their stock is one transaction: when posting fails
        // (stock taken by another issue, a closed period ...) the lines are not left on an
        // issued GI as if they had been loaded, where the settlement form would pick them up.
        DB::beginTransaction();

        try {
            // Locked and re-checked so a reversal cannot run between the check above and the insert.
            $goodsIssue = GoodsIssue::whereKey($goodsIssue->id)->lockForUpdate()->firstOrFail();

            if (! $goodsIssue->canAcceptSupplementaryItems()) {
                throw new \RuntimeException('this Goods Issue can no longer accept supplementary items.');
            }

            $maxLineNo = (int) $goodsIssue->items()->max('line_no');
            $newItems = collect();

            foreach ($request->input('items', []) as $item) {
                $maxLineNo++;
                $newItems->push(GoodsIssueItem::create([
                    'goods_issue_id' => $goodsIssue->id,
                    'line_no' => $maxLineNo,
                    'product_id' => $item['product_id'],
                    'quantity_issued' => $item['quantity_issued'],
                    'unit_cost' => $item['unit_cost'],
                    'selling_price' => $item['selling_price'],
                    'uom_id' => $item['uom_id'],
                    'total_value' => $item['quantity_issued'] * $item['selling_price'],
                    'exclude_promotional' => (bool) ($item['exclude_promotional'] ?? false),
                    'is_supplementary' => true,
                ]));
            }

            $result = app(DistributionService::class)->postSupplementaryItems($goodsIssue, $newItems);

            if (! $result['success']) {
                throw new \RuntimeException($result['message']);
            }

            $goodsIssue->update([
                'total_quantity' => $goodsIssue->items()->sum('quantity_issued'),
                'total_value' => $goodsIssue->items()->sum('total_value'),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error appending items to Goods Issue', [
                'goods_issue_id' => $goodsIssue->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to append items: '.$e->getMessage());
        }

        $message = "Items appended to Goods Issue '{$goodsIssue->issue_number}' successfully.";
        if ($goodsIssue->hasDraftSettlement()) {
            $message .= ' Note: a draft settlement exists for this GI — please update it manually to include the new items.';
        }

        return redirect()
            ->route('goods-issues.show', $goodsIssue)
            ->with('success', $message);
    }

    /**
     * Get employees (salesmen) filtered by supplier IDs (AJAX endpoint).
     * Returns employees belonging to the given suppliers + employees with no supplier (unassigned).
     */
    public function getEmployeesBySuppliers(Request $request): JsonResponse
    {
        $supplierIds = $this->normalizeSupplierIds($request->query('supplier_ids', []));
        $this->ensureAuthorizedSupplierIds($supplierIds);

        $userSupplierId = $this->getUserSupplierScope();
        if ($userSupplierId) {
            $supplierIds = $supplierIds ?: [$userSupplierId];
        }

        $employees = Employee::where('is_active', true)
            ->where(function ($query) use ($supplierIds) {
                if (! empty($supplierIds)) {
                    $query->whereIn('supplier_id', $supplierIds);
                }
                $query->orWhereNull('supplier_id');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'supplier_id']);

        return response()->json($employees);
    }

    /**
     * Get vehicles filtered by supplier IDs (AJAX endpoint).
     * Driver assignment does not affect which vehicles appear — all active supplier vehicles are returned.
     */
    public function getVehiclesBySuppliers(Request $request): JsonResponse
    {
        $supplierIds = $this->normalizeSupplierIds($request->query('supplier_ids', []));
        $this->ensureAuthorizedSupplierIds($supplierIds);

        $userSupplierId = $this->getUserSupplierScope();
        if ($userSupplierId) {
            $supplierIds = $supplierIds ?: [$userSupplierId];
        }

        $vehicles = Vehicle::where('is_active', true)
            ->when(! empty($supplierIds), fn ($q) => $q->whereIn('supplier_id', $supplierIds))
            ->orderBy('vehicle_number')
            ->get(['id', 'vehicle_number', 'vehicle_type', 'supplier_id', 'employee_id']);

        return response()->json($vehicles);
    }

    /**
     * Get products filtered by supplier IDs (AJAX endpoint).
     * Returns products belonging to the given suppliers + products with no supplier (unassigned).
     */
    public function getProductsBySuppliers(Request $request): JsonResponse
    {
        $supplierIds = $this->normalizeSupplierIds($request->query('supplier_ids', []));
        $this->ensureAuthorizedSupplierIds($supplierIds);

        $userSupplierId = $this->getUserSupplierScope();
        if ($userSupplierId) {
            $supplierIds = $supplierIds ?: [$userSupplierId];
        }

        $products = Product::where('is_active', true)
            ->where(function ($query) use ($supplierIds, $userSupplierId) {
                if (! empty($supplierIds)) {
                    $query->whereIn('supplier_id', $supplierIds);
                }

                if (! $userSupplierId) {
                    $query->orWhereNull('supplier_id');
                }
            })
            ->orderBy('product_name')
            ->get(['id', 'product_code', 'product_name', 'uom_id', 'sales_uom_id', 'uom_conversion_factor', 'supplier_id']);

        return response()->json($products);
    }

    private function authorizeGoodsIssueFilterAccess(Request $request, ?int $userSupplierId): void
    {
        if (! $userSupplierId) {
            return;
        }

        $requestedSupplierId = $request->input('filter.supplier_id');
        if ($requestedSupplierId && (int) $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        $requestedProductId = $request->input('filter.product_id');
        if ($requestedProductId) {
            $this->authorizeProductSupplierScope((int) $requestedProductId, 'You do not have permission to filter by this product.');
        }
    }

    private function getUserSupplierScope(): ?int
    {
        $user = auth()->user();

        if ($user->is_super_admin === 'Yes' || $user->hasRole('super-admin')) {
            return null;
        }

        if ($user->hasRole('admin')) {
            return null;
        }

        return $user->supplier_id ? (int) $user->supplier_id : null;
    }

    private function authorizeGoodsIssueSupplierAccess(GoodsIssue $goodsIssue): void
    {
        $userSupplierId = $this->getUserSupplierScope();

        if ($userSupplierId && (int) $goodsIssue->supplier_id !== $userSupplierId) {
            abort(403, 'You do not have permission to access this goods issue.');
        }
    }

    private function authorizeProductSupplierScope(int $productId, string $message = 'You do not have permission to access this product.'): void
    {
        $userSupplierId = $this->getUserSupplierScope();

        if (! $userSupplierId) {
            return;
        }

        $product = Product::query()
            ->select(['id', 'supplier_id'])
            ->find($productId);

        if ($product && (int) $product->supplier_id !== $userSupplierId) {
            abort(403, $message);
        }
    }

    private function authorizeVehicleSupplierScope(int $vehicleId): void
    {
        $userSupplierId = $this->getUserSupplierScope();

        if (! $userSupplierId) {
            return;
        }

        $vehicle = Vehicle::query()
            ->select(['id', 'supplier_id'])
            ->find($vehicleId);

        if ($vehicle && (int) $vehicle->supplier_id !== $userSupplierId) {
            abort(403, 'You do not have permission to access this vehicle.');
        }
    }

    /**
     * @return array<int, int>
     */
    private function normalizeSupplierIds(mixed $supplierIds): array
    {
        $ids = is_array($supplierIds) ? $supplierIds : [$supplierIds];

        return collect($ids)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $supplierIds
     */
    private function ensureAuthorizedSupplierIds(array $supplierIds): void
    {
        $userSupplierId = $this->getUserSupplierScope();

        if (! $userSupplierId || empty($supplierIds)) {
            return;
        }

        foreach ($supplierIds as $supplierId) {
            if ($supplierId !== $userSupplierId) {
                abort(403, 'You do not have permission to access this supplier.');
            }
        }
    }

    /**
     * Generate unique goods issue number
     */
    private function generateIssueNumber(): string
    {
        return GoodsIssue::nextIssueNumber();
    }
}
