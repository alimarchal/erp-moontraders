<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesSettlementRequest;
use App\Http\Requests\UpdateSalesSettlementRequest;
use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAdvanceTax;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\SalesSettlementSale;
use App\Services\DistributionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SalesSettlementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $settlements = QueryBuilder::for(
            SalesSettlement::query()->with(['employee', 'vehicle', 'warehouse', 'goodsIssue'])
        )
            ->allowedFilters([
                AllowedFilter::partial('settlement_number'),
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('settlement_date_from'),
                AllowedFilter::scope('settlement_date_to'),
            ])
            ->defaultSort('-settlement_date')
            ->paginate(20)
            ->withQueryString();

        return view('sales-settlements.index', [
            'settlements' => $settlements,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get issued goods issues that don't have settlements yet
        $goodsIssues = GoodsIssue::where('status', 'issued')
            ->whereDoesntHave('settlement', function ($query) {
                $query->where('status', 'posted');
            })
            ->with(['warehouse', 'vehicle', 'employee', 'items.product', 'items.uom'])
            ->orderBy('issue_date', 'desc')
            ->get();

        // Add batch breakdown to each goods issue item
        foreach ($goodsIssues as $gi) {
            foreach ($gi->items as $item) {
                $stockMovements = DB::table('stock_movements as sm')
                    ->join('stock_batches as sb', 'sm.stock_batch_id', '=', 'sb.id')
                    ->where('sm.reference_type', 'App\Models\GoodsIssue')
                    ->where('sm.reference_id', $gi->id)
                    ->where('sm.product_id', $item->product_id)
                    ->where('sm.movement_type', 'transfer')
                    ->select(
                        'sb.id as stock_batch_id',
                        'sb.batch_code',
                        DB::raw('ABS(sm.quantity) as quantity'),
                        'sm.unit_cost',
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
                        'stock_batch_id' => $movement->stock_batch_id,
                        'batch_code' => $movement->batch_code ?? 'N/A',
                        'quantity' => $quantity,
                        'unit_cost' => (float) $movement->unit_cost,
                        'selling_price' => $sellingPrice,
                        'value' => $value,
                        'is_promotional' => (bool) $movement->is_promotional,
                    ];
                }

                $item->batch_breakdown = $batchBreakdown;
                $item->calculated_total = collect($batchBreakdown)->sum('value');
            }
        }

        return view('sales-settlements.create', [
            'customers' => Customer::where('is_active', true)
                ->orderBy('customer_name')
                ->get(['id', 'customer_code', 'customer_name', 'receivable_balance']),
        ]);
    }

    /**
     * Fetch goods issues for Select2 dropdown (AJAX on-demand loading)
     */
    public function fetchGoodsIssues()
    {
        $goodsIssues = GoodsIssue::where('status', 'issued')
            ->whereDoesntHave('settlement', function ($query) {
                $query->where('status', 'posted');
            })
            ->with(['employee'])
            ->orderBy('issue_date', 'desc')
            ->get()
            ->map(function ($gi) {
                return [
                    'id' => $gi->id,
                    'text' => $gi->issue_number . ' - ' . $gi->employee->full_name . ' (' . $gi->issue_date->format('d M Y') . ')',
                ];
            });

        return response()->json($goodsIssues);
    }

    /**
     * Fetch single goods issue with items for settlement form (AJAX)
     */
    public function fetchGoodsIssueItems($id)
    {
        $goodsIssue = GoodsIssue::where('status', 'issued')
            ->with(['warehouse', 'vehicle', 'employee', 'items.product', 'items.uom'])
            ->findOrFail($id);

        // Add batch breakdown to each goods issue item
        foreach ($goodsIssue->items as $item) {
            $stockMovements = DB::table('stock_movements as sm')
                ->join('stock_batches as sb', 'sm.stock_batch_id', '=', 'sb.id')
                ->where('sm.reference_type', 'App\Models\GoodsIssue')
                ->where('sm.reference_id', $goodsIssue->id)
                ->where('sm.product_id', $item->product_id)
                ->where('sm.movement_type', 'transfer')
                ->select(
                    'sb.id as stock_batch_id',
                    'sb.batch_code',
                    DB::raw('ABS(sm.quantity) as quantity'),
                    'sm.unit_cost',
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
                    'stock_batch_id' => $movement->stock_batch_id,
                    'batch_code' => $movement->batch_code ?? 'N/A',
                    'quantity' => $quantity,
                    'unit_cost' => (float) $movement->unit_cost,
                    'selling_price' => $sellingPrice,
                    'value' => $value,
                    'is_promotional' => (bool) $movement->is_promotional,
                ];
            }

            $item->batch_breakdown = $batchBreakdown;
            $item->calculated_total = collect($batchBreakdown)->sum('value');
        }

        // Format items to ensure product and uom data are included
        $formattedItems = $goodsIssue->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity_issued' => $item->quantity_issued,
                'unit_cost' => $item->unit_cost,
                'calculated_total' => $item->calculated_total,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->product_name,
                    'product_code' => $item->product->product_code,
                ],
                'uom' => [
                    'id' => $item->uom->id,
                    'symbol' => $item->uom->symbol,
                    'name' => $item->uom->name,
                ],
                'batch_breakdown' => $item->batch_breakdown,
            ];
        });

        return response()->json([
            'id' => $goodsIssue->id,
            'issue_number' => $goodsIssue->issue_number,
            'issue_date' => $goodsIssue->issue_date->format('d M Y'),
            'employee' => $goodsIssue->employee->full_name,
            'vehicle' => $goodsIssue->vehicle->vehicle_number,
            'items' => $formattedItems,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSalesSettlementRequest $request)
    {
        DB::beginTransaction();

        try {
            // Get goods issue
            $goodsIssue = GoodsIssue::with('items')->findOrFail($request->goods_issue_id);

            // Debug: Log the incoming request data
            Log::info('Sales Settlement Request Data', [
                'goods_issue_id' => $request->goods_issue_id,
                'items_count' => count($request->items ?? []),
                'items_data' => $request->items,
            ]);

            // Generate settlement number
            $settlementNumber = $this->generateSettlementNumber();

            // Calculate totals
            $totalQuantityIssued = 0;
            $totalValueIssued = 0;
            $totalQuantitySold = 0;
            $totalQuantityReturned = 0;
            $totalQuantityShortage = 0;
            $totalCogs = 0;
            $totalSalesValue = 0;

            foreach ($request->items as $item) {
                $totalQuantityIssued += $item['quantity_issued'];
                $totalValueIssued += $item['quantity_issued'] * $item['unit_cost'];
                $totalQuantitySold += $item['quantity_sold'];
                $totalQuantityReturned += $item['quantity_returned'] ?? 0;
                $totalQuantityShortage += $item['quantity_shortage'] ?? 0;

                // Calculate COGS (Cost of Goods Sold) and Sales Value
                $itemCogs = $item['quantity_sold'] * $item['unit_cost'];
                $totalCogs += $itemCogs;

                // Calculate selling price from batches or use unit_cost as fallback
                $sellingPrice = $item['selling_price'] ?? null;
                if (!$sellingPrice && isset($item['batches']) && is_array($item['batches'])) {
                    $totalQty = 0;
                    $totalValue = 0;
                    foreach ($item['batches'] as $batch) {
                        $batchQty = $batch['quantity_issued'] ?? 0;
                        $batchPrice = $batch['selling_price'] ?? 0;
                        $totalQty += $batchQty;
                        $totalValue += $batchQty * $batchPrice;
                    }
                    $sellingPrice = $totalQty > 0 ? $totalValue / $totalQty : 0;
                }
                if (empty($sellingPrice)) {
                    $sellingPrice = $item['unit_cost'] ?? 0;
                }

                $itemSalesValue = $item['quantity_sold'] * $sellingPrice;
                $totalSalesValue += $itemSalesValue;
            }

            // Calculate Gross Profit = Sales Value - COGS
            $grossProfit = $totalSalesValue - $totalCogs;

            $totalSalesAmount = ($request->cash_sales_amount ?? 0) +
                ($request->cheque_sales_amount ?? 0) +
                ($request->credit_sales_amount ?? 0);

            $cashToDeposit = ($request->cash_collected ?? 0) + ($request->credit_recoveries ?? 0) - ($request->expenses_claimed ?? 0);

            // Calculate total expenses from individual fields
            $totalExpenses = ($request->expense_toll_tax ?? 0) +
                ($request->expense_amr_powder_claim ?? 0) +
                ($request->expense_amr_liquid_claim ?? 0) +
                ($request->expense_scheme ?? 0) +
                ($request->expense_advance_tax ?? 0) +
                ($request->expense_food_charges ?? 0) +
                ($request->expense_salesman_charges ?? 0) +
                ($request->expense_loader_charges ?? 0) +
                ($request->expense_percentage ?? 0) +
                ($request->expense_miscellaneous_amount ?? 0);

            // Prepare bank transfer details as JSON array
            $bankTransfers = null;
            $totalBankTransfers = 0;
            if ($request->has('bank_transfers') && is_array($request->bank_transfers)) {
                $bankTransfers = $request->bank_transfers;
                foreach ($bankTransfers as $transfer) {
                    $totalBankTransfers += floatval($transfer['amount'] ?? 0);
                }
            }

            // Prepare cheque details as JSON
            $chequeDetails = null;
            $totalCheques = 0;
            if ($request->has('cheques') && is_array($request->cheques)) {
                $chequeDetails = $request->cheques;
                foreach ($chequeDetails as $cheque) {
                    $totalCheques += floatval($cheque['amount'] ?? 0);
                }
            }

            // Create sales settlement
            $settlement = SalesSettlement::create([
                'settlement_number' => $settlementNumber,
                'settlement_date' => $request->settlement_date,
                'goods_issue_id' => $goodsIssue->id,
                'employee_id' => $goodsIssue->employee_id,
                'vehicle_id' => $goodsIssue->vehicle_id,
                'warehouse_id' => $goodsIssue->warehouse_id,
                'total_quantity_issued' => $totalQuantityIssued,
                'total_value_issued' => $totalValueIssued,
                'total_sales_amount' => $totalSalesAmount,
                'cash_sales_amount' => $request->cash_sales_amount ?? 0,
                'cheque_sales_amount' => $totalCheques,
                'credit_sales_amount' => $request->credit_sales_amount ?? 0,
                'credit_recoveries' => $request->credit_recoveries_total ?? 0,
                'total_quantity_sold' => $totalQuantitySold,
                'total_quantity_returned' => $totalQuantityReturned,
                'total_quantity_shortage' => $totalQuantityShortage,
                'cash_collected' => $request->summary_cash_received ?? 0,
                'cheques_collected' => $totalCheques,
                'expenses_claimed' => $totalExpenses,
                'gross_profit' => $grossProfit,
                'total_cogs' => $totalCogs,
                'expense_toll_tax' => $request->expense_toll_tax ?? 0,
                'expense_amr_powder_claim' => $request->expense_amr_powder_claim ?? 0,
                'expense_amr_liquid_claim' => $request->expense_amr_liquid_claim ?? 0,
                'expense_scheme' => $request->expense_scheme ?? 0,
                'expense_advance_tax' => $request->expense_advance_tax ?? 0,
                'expense_food_charges' => $request->expense_food_charges ?? 0,
                'expense_salesman_charges' => $request->expense_salesman_charges ?? 0,
                'expense_loader_charges' => $request->expense_loader_charges ?? 0,
                'expense_percentage' => $request->expense_percentage ?? 0,
                'expense_miscellaneous_amount' => $request->expense_miscellaneous_amount ?? 0,
                'cash_to_deposit' => $cashToDeposit,
                // Cash denomination breakdown
                'denom_5000' => $request->denom_5000 ?? 0,
                'denom_1000' => $request->denom_1000 ?? 0,
                'denom_500' => $request->denom_500 ?? 0,
                'denom_100' => $request->denom_100 ?? 0,
                'denom_50' => $request->denom_50 ?? 0,
                'denom_20' => $request->denom_20 ?? 0,
                'denom_10' => $request->denom_10 ?? 0,
                'denom_coins' => $request->denom_coins ?? 0,
                // Bank transfer details - Store as JSON array for multiple transfers
                'bank_transfer_amount' => $totalBankTransfers,
                'bank_account_id' => null, // Deprecated - now using bank_transfers array
                'bank_transfers' => $bankTransfers,
                // Cheque details
                'cheque_count' => is_array($chequeDetails) ? count($chequeDetails) : 0,
                'cheque_details' => $chequeDetails,
                'status' => 'draft',
                'notes' => $request->notes,
            ]);

            // Create settlement items with batch breakdown
            foreach ($request->items as $index => $item) {
                // Calculate selling price from batches if not provided at item level
                $sellingPrice = $item['selling_price'] ?? 0;
                if (!$sellingPrice && isset($item['batches']) && is_array($item['batches'])) {
                    $totalQty = 0;
                    $totalValue = 0;
                    foreach ($item['batches'] as $batch) {
                        $batchQty = $batch['quantity_issued'] ?? 0;
                        $batchPrice = $batch['selling_price'] ?? 0;
                        $totalQty += $batchQty;
                        $totalValue += $batchQty * $batchPrice;
                    }
                    $sellingPrice = $totalQty > 0 ? $totalValue / $totalQty : 0;
                }

                // Ensure selling price is never null or empty - use unit_cost as fallback
                if (empty($sellingPrice)) {
                    $sellingPrice = $item['unit_cost'] ?? 0;
                }

                $cogs = $item['quantity_sold'] * $item['unit_cost'];
                $salesValue = $item['quantity_sold'] * $sellingPrice;

                $settlementItem = SalesSettlementItem::create([
                    'sales_settlement_id' => $settlement->id,
                    'goods_issue_item_id' => $item['goods_issue_item_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'quantity_issued' => $item['quantity_issued'],
                    'quantity_sold' => $item['quantity_sold'],
                    'quantity_returned' => $item['quantity_returned'] ?? 0,
                    'quantity_shortage' => $item['quantity_shortage'] ?? 0,
                    'unit_cost' => $item['unit_cost'],
                    'unit_selling_price' => $sellingPrice,
                    'total_cogs' => $cogs,
                    'total_sales_value' => $salesValue,
                ]);

                // Store batch breakdown if available
                if (isset($item['batches']) && is_array($item['batches'])) {
                    foreach ($item['batches'] as $batch) {
                        SalesSettlementItemBatch::create([
                            'sales_settlement_item_id' => $settlementItem->id,
                            'stock_batch_id' => $batch['stock_batch_id'],
                            'batch_code' => $batch['batch_code'],
                            'quantity_issued' => $batch['quantity_issued'] ?? 0,
                            'quantity_sold' => $batch['quantity_sold'] ?? 0,
                            'quantity_returned' => $batch['quantity_returned'] ?? 0,
                            'quantity_shortage' => $batch['quantity_shortage'] ?? 0,
                            'unit_cost' => $batch['unit_cost'],
                            'selling_price' => $batch['selling_price'],
                            'is_promotional' => $batch['is_promotional'] ?? false,
                        ]);
                    }
                }
            }

            // Create credit sales records if any
            if (!empty($request->sales)) {
                foreach ($request->sales as $sale) {
                    SalesSettlementSale::create([
                        'sales_settlement_id' => $settlement->id,
                        'customer_id' => $sale['customer_id'],
                        'invoice_number' => $sale['invoice_number'] ?? null,
                        'sale_amount' => $sale['sale_amount'],
                        'sale_type' => $sale['payment_type'] ?? $sale['sale_type'] ?? 'cash',
                    ]);
                }
            }

            // Create credit sales breakdown records if any
            if (!empty($request->credit_sales) && is_array($request->credit_sales)) {
                foreach ($request->credit_sales as $creditSale) {
                    CreditSale::create([
                        'sales_settlement_id' => $settlement->id,
                        'employee_id' => $goodsIssue->employee_id,
                        'supplier_id' => $goodsIssue->employee->supplier_id,
                        'customer_id' => $creditSale['customer_id'],
                        'invoice_number' => $creditSale['invoice_number'] ?? null,
                        'sale_amount' => $creditSale['sale_amount'],
                        'notes' => $creditSale['notes'] ?? null,
                    ]);
                }
            }

            // Create advance tax breakdown records if any
            if (!empty($request->advance_taxes) && is_array($request->advance_taxes)) {
                foreach ($request->advance_taxes as $advanceTax) {
                    SalesSettlementAdvanceTax::create([
                        'sales_settlement_id' => $settlement->id,
                        'customer_id' => $advanceTax['customer_id'],
                        'sale_amount' => $advanceTax['sale_amount'] ?? 0,
                        'tax_rate' => $advanceTax['tax_rate'] ?? 0.25,
                        'tax_amount' => $advanceTax['tax_amount'],
                        'invoice_number' => $advanceTax['invoice_number'] ?? null,
                        'notes' => $advanceTax['notes'] ?? null,
                    ]);
                }
            }

            // NOTE: Ledger entries are created when the settlement is POSTED
            // via DistributionService::postSalesSettlement() to avoid duplicates

            DB::commit();

            return redirect()
                ->route('sales-settlements.show', $settlement)
                ->with('success', "Sales Settlement '{$settlement->settlement_number}' created successfully. Please POST the settlement to finalize.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating Sales Settlement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to create Sales Settlement: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(SalesSettlement $salesSettlement)
    {
        $salesSettlement->load([
            'goodsIssue',
            'employee',
            'vehicle',
            'warehouse',
            'verifiedBy',
            'journalEntry',
            'items.product',
            'items.batches.stockBatch',
            'sales.customer',
            'creditSales.customer',
            'creditSales.employee',
            'creditSales.supplier',
            'advanceTaxes.customer',
        ]);

        return view('sales-settlements.show', [
            'settlement' => $salesSettlement,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalesSettlement $salesSettlement)
    {
        if ($salesSettlement->status !== 'draft') {
            return redirect()
                ->route('sales-settlements.show', $salesSettlement)
                ->with('error', 'Only draft Sales Settlements can be edited.');
        }

        $salesSettlement->load('items', 'sales', 'creditSales');

        // Get issued goods issues
        $goodsIssues = GoodsIssue::where('status', 'issued')
            ->where(function ($query) use ($salesSettlement) {
                $query->whereDoesntHave('settlement', function ($q) {
                    $q->where('status', 'posted');
                })->orWhere('id', $salesSettlement->goods_issue_id);
            })
            ->with(['warehouse', 'vehicle', 'employee', 'items.product'])
            ->orderBy('issue_date', 'desc')
            ->get();

        return view('sales-settlements.edit', [
            'settlement' => $salesSettlement,
            'goodsIssues' => $goodsIssues,
            'customers' => Customer::where('is_active', true)->orderBy('customer_name')->get(['id', 'customer_code', 'customer_name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSalesSettlementRequest $request, SalesSettlement $salesSettlement)
    {
        if ($salesSettlement->status !== 'draft') {
            return redirect()
                ->route('sales-settlements.show', $salesSettlement)
                ->with('error', 'Only draft Sales Settlements can be updated.');
        }

        DB::beginTransaction();

        try {
            // Get goods issue
            $goodsIssue = GoodsIssue::with('items')->findOrFail($request->goods_issue_id);

            // Calculate totals
            $totalQuantityIssued = 0;
            $totalValueIssued = 0;
            $totalQuantitySold = 0;
            $totalQuantityReturned = 0;
            $totalQuantityShortage = 0;
            $totalCogs = 0;
            $totalSalesValue = 0;

            foreach ($request->items as $item) {
                $totalQuantityIssued += $item['quantity_issued'];
                $totalValueIssued += $item['quantity_issued'] * $item['unit_cost'];
                $totalQuantitySold += $item['quantity_sold'];
                $totalQuantityReturned += $item['quantity_returned'] ?? 0;
                $totalQuantityShortage += $item['quantity_shortage'] ?? 0;

                // Calculate COGS for this item
                $itemCogs = $item['quantity_sold'] * $item['unit_cost'];
                $totalCogs += $itemCogs;

                // Calculate selling price from batches if available
                $sellingPrice = $item['selling_price'] ?? null;
                if (!$sellingPrice && isset($item['batches']) && is_array($item['batches'])) {
                    $totalQty = 0;
                    $totalValue = 0;
                    foreach ($item['batches'] as $batch) {
                        $batchQty = $batch['quantity_issued'] ?? 0;
                        $batchPrice = $batch['selling_price'] ?? 0;
                        $totalQty += $batchQty;
                        $totalValue += $batchQty * $batchPrice;
                    }
                    $sellingPrice = $totalQty > 0 ? $totalValue / $totalQty : 0;
                }

                // Fall back to unit cost if no selling price
                if (empty($sellingPrice)) {
                    $sellingPrice = $item['unit_cost'] ?? 0;
                }

                // Calculate sales value for this item
                $itemSalesValue = $item['quantity_sold'] * $sellingPrice;
                $totalSalesValue += $itemSalesValue;
            }

            // Calculate gross profit
            $grossProfit = $totalSalesValue - $totalCogs;

            $totalSalesAmount = ($request->cash_sales_amount ?? 0) +
                ($request->cheque_sales_amount ?? 0) +
                ($request->credit_sales_amount ?? 0);

            $cashToDeposit = ($request->cash_collected ?? 0) + ($request->credit_recoveries ?? 0) - ($request->expenses_claimed ?? 0);

            // Update sales settlement
            $salesSettlement->update([
                'settlement_date' => $request->settlement_date,
                'goods_issue_id' => $goodsIssue->id,
                'employee_id' => $goodsIssue->employee_id,
                'vehicle_id' => $goodsIssue->vehicle_id,
                'warehouse_id' => $goodsIssue->warehouse_id,
                'total_quantity_issued' => $totalQuantityIssued,
                'total_value_issued' => $totalValueIssued,
                'total_sales_amount' => $totalSalesAmount,
                'cash_sales_amount' => $request->cash_sales_amount ?? 0,
                'cheque_sales_amount' => $request->cheque_sales_amount ?? 0,
                'credit_sales_amount' => $request->credit_sales_amount ?? 0,
                'credit_recoveries' => $request->credit_recoveries ?? 0,
                'total_quantity_sold' => $totalQuantitySold,
                'total_quantity_returned' => $totalQuantityReturned,
                'total_quantity_shortage' => $totalQuantityShortage,
                'cash_collected' => $request->cash_collected ?? 0,
                'cheques_collected' => $request->cheques_collected ?? 0,
                'expenses_claimed' => $request->expenses_claimed ?? 0,
                'cash_to_deposit' => $cashToDeposit,
                'gross_profit' => $grossProfit,
                'total_cogs' => $totalCogs,
                'notes' => $request->notes,
            ]);

            // Delete old items and create new ones
            $salesSettlement->items()->delete();
            $salesSettlement->sales()->delete();
            $salesSettlement->creditSales()->delete();

            foreach ($request->items as $index => $item) {
                $cogs = $item['quantity_sold'] * $item['unit_cost'];
                $salesValue = $item['quantity_sold'] * $item['selling_price'];

                $settlementItem = SalesSettlementItem::create([
                    'sales_settlement_id' => $salesSettlement->id,
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'quantity_issued' => $item['quantity_issued'],
                    'quantity_sold' => $item['quantity_sold'],
                    'quantity_returned' => $item['quantity_returned'] ?? 0,
                    'quantity_shortage' => $item['quantity_shortage'] ?? 0,
                    'unit_cost' => $item['unit_cost'],
                    'selling_price' => $item['selling_price'],
                    'total_cogs' => $cogs,
                    'total_sales_value' => $salesValue,
                ]);

                // Store batch breakdown if available
                if (isset($item['batches']) && is_array($item['batches'])) {
                    foreach ($item['batches'] as $batch) {
                        SalesSettlementItemBatch::create([
                            'sales_settlement_item_id' => $settlementItem->id,
                            'stock_batch_id' => $batch['stock_batch_id'],
                            'batch_code' => $batch['batch_code'],
                            'quantity_issued' => $batch['quantity_issued'] ?? 0,
                            'quantity_sold' => $batch['quantity_sold'] ?? 0,
                            'quantity_returned' => $batch['quantity_returned'] ?? 0,
                            'quantity_shortage' => $batch['quantity_shortage'] ?? 0,
                            'unit_cost' => $batch['unit_cost'],
                            'selling_price' => $batch['selling_price'],
                            'is_promotional' => $batch['is_promotional'] ?? false,
                        ]);
                    }
                }
            }

            // Create credit sales records if any
            if (!empty($request->sales)) {
                foreach ($request->sales as $sale) {
                    SalesSettlementSale::create([
                        'sales_settlement_id' => $salesSettlement->id,
                        'customer_id' => $sale['customer_id'],
                        'invoice_number' => $sale['invoice_number'] ?? null,
                        'sale_amount' => $sale['sale_amount'],
                        'payment_type' => $sale['payment_type'],
                    ]);
                }
            }

            // Create credit sales breakdown records if any
            if (!empty($request->credit_sales) && is_array($request->credit_sales)) {
                foreach ($request->credit_sales as $creditSale) {
                    CreditSale::create([
                        'sales_settlement_id' => $salesSettlement->id,
                        'employee_id' => $goodsIssue->employee_id,
                        'supplier_id' => $goodsIssue->employee->supplier_id,
                        'customer_id' => $creditSale['customer_id'],
                        'invoice_number' => $creditSale['invoice_number'] ?? null,
                        'sale_amount' => $creditSale['sale_amount'],
                        'notes' => $creditSale['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return redirect()
                ->route('sales-settlements.show', $salesSettlement)
                ->with('success', "Sales Settlement '{$salesSettlement->settlement_number}' updated successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating Sales Settlement', [
                'settlement_id' => $salesSettlement->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to update Sales Settlement. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalesSettlement $salesSettlement)
    {
        if ($salesSettlement->status !== 'draft') {
            return back()->with('error', 'Only draft Sales Settlements can be deleted.');
        }

        DB::beginTransaction();

        try {
            $settlementNumber = $salesSettlement->settlement_number;
            $salesSettlement->items()->delete();
            $salesSettlement->sales()->delete();
            $salesSettlement->creditSales()->delete();
            $salesSettlement->delete();

            DB::commit();

            return redirect()
                ->route('sales-settlements.index')
                ->with('success', "Sales Settlement '{$settlementNumber}' deleted successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Unable to delete Sales Settlement.');
        }
    }

    /**
     * Post sales settlement to record sales and update inventory
     *
     * TODO: Chart of Accounts (COA) Integration for Complete Accounting
     * ================================================================
     * When posting a sales settlement, the following journal entries should be created:
     *
     * 1. SALES REVENUE RECOGNITION:
     *    Dr. Cash / Accounts Receivable (Customer)  [Total Sales]
     *        Cr. Sales Revenue                      [Total Sales Value]
     *        Cr. Sales Tax Payable                  [If applicable]
     *
     * 2. COST OF GOODS SOLD (COGS):
     *    Dr. Cost of Goods Sold                     [Total COGS]
     *        Cr. Inventory                          [Reduce inventory at cost]
     *
     * 3. CASH RECONCILIATION:
     *    Dr. Cash in Hand                           [Denomination breakdown total]
     *    Dr. Cash at Bank                           [Bank transfer amount]
     *    Dr. Cheques Receivable                     [Cheque details total]
     *        Cr. Cash / AR (from sales above)       [Match against sales]
     *
     * 4. EXPENSE RECOGNITION:
     *    Dr. Toll Tax Expense                       [expense_toll_tax]
     *    Dr. AMR Powder Claim Expense               [expense_amr_powder_claim]
     *    Dr. AMR Liquid Claim Expense               [expense_amr_liquid_claim]
     *    Dr. Scheme Expense                         [expense_scheme]
     *    Dr. Advance Tax Expense                    [expense_advance_tax]
     *    Dr. Food Charges Expense                   [expense_food_charges]
     *    Dr. Salesman Charges Expense               [expense_salesman_charges]
     *    Dr. Loader Charges Expense                 [expense_loader_charges]
     *    Dr. Percentage Expense                     [expense_percentage]
     *    Dr. Miscellaneous Expense                  [expense_miscellaneous_amount]
     *        Cr. Cash in Hand                       [Total expenses claimed]
     *
     * 5. CREDIT SALES (AR) TRACKING:
     *    For each credit sale record:
     *    Dr. Accounts Receivable - Customer         [sale_amount]
     *        Cr. Sales Revenue                      [Already recorded above]
     *
     * 6. CREDIT RECOVERIES:
     *    Dr. Cash in Hand                           [credit_recoveries]
     *        Cr. Accounts Receivable - Customer     [Reduce AR balance]
     *
     * 7. BANK/CHEQUE DETAILS:
     *    For bank transfers:
     *    Dr. Bank Account [bank_account_id]         [bank_transfer_amount]
     *        Cr. Cash in Hand                       [Transfer from cash]
     *
     *    For cheques:
     *    Dr. Cheques in Hand                        [cheques_collected]
     *        Cr. Cash/AR                            [From customer payments]
     *
     * 8. SALESMAN ANALYSIS TRACKING:
     *    - Track per-salesman daily performance
     *    - Record BF (Brought Forward) balances
     *    - Maintain salesman-wise product movement
     *    - Store data for reports/analytics
     *
     * IMPLEMENTATION NOTES:
     * - Use AccountingService->createJournalEntry() for double-entry posting
     * - Ensure all debits equal all credits (accounting equation balance)
     * - Link journal_entry_id back to sales_settlement record
     * - Store cost_center_id for departmental accounting if applicable
     * - Create detailed audit trail with all COA account references
     */
    public function post(SalesSettlement $salesSettlement)
    {
        if ($salesSettlement->status !== 'draft') {
            return back()->with('error', 'Only draft Sales Settlements can be posted.');
        }

        $distributionService = app(DistributionService::class);
        $result = $distributionService->postSalesSettlement($salesSettlement);

        if ($result['success']) {
            return redirect()
                ->route('sales-settlements.show', $salesSettlement->id)
                ->with('success', $result['message']);
        }

        return redirect()
            ->back()
            ->with('error', $result['message']);
    }

    /**
     * Generate unique settlement number
     */
    private function generateSettlementNumber(): string
    {
        $year = now()->year;
        $lastSettlement = SalesSettlement::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastSettlement ? ((int) substr($lastSettlement->settlement_number, -4)) + 1 : 1;

        return sprintf('SETTLE-%d-%04d', $year, $sequence);
    }
}
