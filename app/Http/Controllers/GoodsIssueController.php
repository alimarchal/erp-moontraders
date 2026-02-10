<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoodsIssueRequest;
use App\Http\Requests\UpdateGoodsIssueRequest;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GoodsIssueController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:goods-issue-list', only: ['index', 'show']),
            new Middleware('permission:goods-issue-create', only: ['create', 'store']),
            new Middleware('permission:goods-issue-edit', only: ['edit', 'update']),
            new Middleware('permission:goods-issue-delete', only: ['destroy']),
            new Middleware('permission:goods-issue-post', only: ['post']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $goodsIssues = QueryBuilder::for(
            GoodsIssue::query()->with(['warehouse', 'vehicle', 'employee', 'supplier', 'issuedBy'])
        )
            ->allowedFilters([
                AllowedFilter::partial('issue_number'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('issue_date_from'),
                AllowedFilter::scope('issue_date_to'),
                AllowedFilter::exact('issue_date'), // Added for direct day filtering
                AllowedFilter::callback('product_id', function ($query, $value) {
                    $query->whereHas('items', function ($q) use ($value) {
                        $q->where('product_id', $value);
                    });
                }),
            ])
            ->defaultSort('-issue_date')
            ->paginate(20)
            ->withQueryString();

        // Calculate totals based on the same filters (excluding pagination)
        $totalValue = QueryBuilder::for(GoodsIssue::class)
            ->allowedFilters([
                AllowedFilter::partial('issue_number'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('issue_date_from'),
                AllowedFilter::scope('issue_date_to'),
                AllowedFilter::exact('issue_date'),
                AllowedFilter::callback('product_id', function ($query, $value) {
                    $query->whereHas('items', function ($q) use ($value) {
                        $q->where('product_id', $value);
                    });
                }),
            ])
            ->sum('total_value');

        return view('goods-issues.index', [
            'totalValue' => $totalValue,
            'goodsIssues' => $goodsIssues,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('vehicle_number')->get(['id', 'vehicle_number', 'vehicle_type']),
            'employees' => Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('goods-issues.create', [
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
        ]);
    }

    /**
     * Get product stock details for a specific warehouse (AJAX endpoint)
     * Returns selling price from the first priority stock layer with batch breakdown
     * Database-agnostic: Works with PostgreSQL, MySQL, and MariaDB
     */
    public function getProductStock($warehouseId, $productId)
    {
        // Get total available quantity
        $totalStock = DB::table('stock_valuation_layers')
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0)
            ->sum('quantity_remaining');

        // Calculate urgent date threshold (30 days from now)
        $urgentDate = now()->addDays(30)->toDateString();

        // Get ALL available stock layers ordered by priority
        // Priority Logic (STRICT ORDER):
        // 1) URGENT EXPIRY: Items expiring within 30 days (urgency_level = 1)
        // 2) PRIORITY ORDER: Lower numbers first (1 = Urgent, 99 = Normal FIFO)
        // 3) FIFO: Oldest receipt date first
        $stockLayers = DB::table('stock_valuation_layers as svl')
            ->join('goods_receipt_note_items as grni', 'svl.grn_item_id', '=', 'grni.id')
            ->leftJoin('stock_batches as sb', 'svl.stock_batch_id', '=', 'sb.id')
            ->where('svl.warehouse_id', $warehouseId)
            ->where('svl.product_id', $productId)
            ->where('svl.is_depleted', false)
            ->where('svl.quantity_remaining', '>', 0)
            ->selectRaw('
                grni.selling_price,
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

        $product = Product::with('uom')->find($productId);

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
            'batches' => $batches,
            'has_multiple_prices' => $stockLayers->pluck('selling_price')->unique()->count() > 1,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGoodsIssueRequest $request)
    {
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

            // Get supplier_id from employee
            $employee = Employee::findOrFail($request->employee_id);

            // Create goods issue
            $goodsIssue = GoodsIssue::create([
                'issue_number' => $issueNumber,
                'issue_date' => $request->issue_date,
                'warehouse_id' => $request->warehouse_id,
                'vehicle_id' => $request->vehicle_id,
                'employee_id' => $request->employee_id,
                'supplier_id' => $employee->supplier_id,
                'issued_by' => auth()->id(),
                // Resolve default GL accounts from COA codes for this row (stored on the record)
                'stock_in_hand_account_id' => optional(\App\Models\ChartOfAccount::where('account_code', '1151')->first())->id,
                'van_stock_account_id' => optional(\App\Models\ChartOfAccount::where('account_code', '1155')->first())->id,
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
        $goodsIssue->load([
            'warehouse',
            'vehicle',
            'employee',
            'supplier',
            'issuedBy',
            'items.product',
            'items.uom',
        ]);

        foreach ($goodsIssue->items as $item) {
            if ($goodsIssue->status === 'issued') {
                // For posted goods issues, get ACTUAL batch breakdown from stock movements
                $stockMovements = DB::table('stock_movements as sm')
                    ->join('stock_batches as sb', 'sm.stock_batch_id', '=', 'sb.id')
                    ->where('sm.reference_type', 'App\Models\GoodsIssue')
                    ->where('sm.reference_id', $goodsIssue->id)
                    ->where('sm.product_id', $item->product_id)
                    ->where('sm.movement_type', 'transfer')
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

                $stockLayers = DB::table('stock_valuation_layers as svl')
                    ->join('goods_receipt_note_items as grni', 'svl.grn_item_id', '=', 'grni.id')
                    ->leftJoin('stock_batches as sb', 'svl.stock_batch_id', '=', 'sb.id')
                    ->where('svl.warehouse_id', $goodsIssue->warehouse_id)
                    ->where('svl.product_id', $item->product_id)
                    ->where('svl.is_depleted', false)
                    ->where('svl.quantity_remaining', '>', 0)
                    ->selectRaw('
                        grni.selling_price,
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

        return view('goods-issues.show', [
            'goodsIssue' => $goodsIssue,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GoodsIssue $goodsIssue)
    {
        if ($goodsIssue->status !== 'draft') {
            return redirect()
                ->route('goods-issues.show', $goodsIssue)
                ->with('error', 'Only draft Goods Issues can be edited.');
        }

        $goodsIssue->load('items');

        return view('goods-issues.edit', [
            'goodsIssue' => $goodsIssue,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'suppliers' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGoodsIssueRequest $request, GoodsIssue $goodsIssue)
    {
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
     * Post goods issue to transfer inventory from warehouse to vehicle
     */
    public function post(GoodsIssue $goodsIssue)
    {
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
     * Get employees (salesmen) filtered by supplier IDs (AJAX endpoint).
     * Returns employees belonging to the given suppliers + employees with no supplier (unassigned).
     */
    public function getEmployeesBySuppliers(Request $request): JsonResponse
    {
        $supplierIds = $request->query('supplier_ids', []);

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
     * Get vehicles filtered by employee (AJAX endpoint).
     * Returns vehicles belonging to the given employee + Walk vehicles (no employee assigned).
     */
    public function getVehiclesByEmployee(Employee $employee): JsonResponse
    {
        $vehicles = Vehicle::where('is_active', true)
            ->where(function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->orWhereNull('employee_id');
            })
            ->orderBy('vehicle_number')
            ->get(['id', 'vehicle_number', 'vehicle_type', 'employee_id']);

        return response()->json($vehicles);
    }

    /**
     * Get products filtered by supplier IDs (AJAX endpoint).
     * Returns products belonging to the given suppliers + products with no supplier (unassigned).
     */
    public function getProductsBySuppliers(Request $request): JsonResponse
    {
        $supplierIds = $request->query('supplier_ids', []);

        $products = Product::where('is_active', true)
            ->where(function ($query) use ($supplierIds) {
                if (! empty($supplierIds)) {
                    $query->whereIn('supplier_id', $supplierIds);
                }
                $query->orWhereNull('supplier_id');
            })
            ->orderBy('product_name')
            ->get(['id', 'product_code', 'product_name', 'uom_id', 'supplier_id']);

        return response()->json($products);
    }

    /**
     * Generate unique goods issue number
     */
    private function generateIssueNumber(): string
    {
        $year = now()->year;
        $prefix = "GI-{$year}-";

        $lastIssue = GoodsIssue::withTrashed()
            ->where('issue_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastIssue) {
            $lastSequence = (int) str_replace($prefix, '', $lastIssue->issue_number);
            $sequence = $lastSequence + 1;
        }

        return sprintf('%s%04d', $prefix, $sequence);
    }
}
