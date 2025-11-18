<?php

namespace App\Http\Controllers;

use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\SalesSettlementSale;
use App\Models\CreditSale;
use App\Models\GoodsIssue;
use App\Models\Customer;
use App\Http\Requests\StoreSalesSettlementRequest;
use App\Http\Requests\UpdateSalesSettlementRequest;
use App\Services\DistributionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

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
                        DB::raw('ABS(sm.total_value) as value'),
                        'sb.is_promotional'
                    )
                    ->orderBy('sb.priority_order', 'asc')
                    ->get();

                $batchBreakdown = [];
                foreach ($stockMovements as $movement) {
                    $batchBreakdown[] = [
                        'stock_batch_id' => $movement->stock_batch_id,
                        'batch_code' => $movement->batch_code ?? 'N/A',
                        'quantity' => (float) $movement->quantity,
                        'unit_cost' => (float) $movement->unit_cost,
                        'selling_price' => (float) $movement->selling_price,
                        'value' => (float) $movement->value,
                        'is_promotional' => (bool) $movement->is_promotional,
                    ];
                }

                $item->batch_breakdown = $batchBreakdown;
                $item->calculated_total = collect($batchBreakdown)->sum('value');
            }
        }

        return view('sales-settlements.create', [
            'goodsIssues' => $goodsIssues,
            'customers' => Customer::where('is_active', true)
                ->orderBy('customer_name')
                ->get(['id', 'customer_code', 'customer_name', 'receivable_balance']),
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

            // Generate settlement number
            $settlementNumber = $this->generateSettlementNumber();

            // Calculate totals
            $totalQuantityIssued = 0;
            $totalValueIssued = 0;
            $totalQuantitySold = 0;
            $totalQuantityReturned = 0;
            $totalQuantityShortage = 0;

            foreach ($request->items as $item) {
                $totalQuantityIssued += $item['quantity_issued'];
                $totalValueIssued += $item['quantity_issued'] * $item['unit_cost'];
                $totalQuantitySold += $item['quantity_sold'];
                $totalQuantityReturned += $item['quantity_returned'] ?? 0;
                $totalQuantityShortage += $item['quantity_shortage'] ?? 0;
            }

            $totalSalesAmount = ($request->cash_sales_amount ?? 0) +
                ($request->cheque_sales_amount ?? 0) +
                ($request->credit_sales_amount ?? 0);

            $cashToDeposit = ($request->cash_collected ?? 0) + ($request->credit_recoveries ?? 0) - ($request->expenses_claimed ?? 0);

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
                'status' => 'draft',
                'notes' => $request->notes,
            ]);

            // Create settlement items with batch breakdown
            foreach ($request->items as $index => $item) {
                $cogs = $item['quantity_sold'] * $item['unit_cost'];
                $salesValue = $item['quantity_sold'] * $item['selling_price'];

                $settlementItem = SalesSettlementItem::create([
                    'sales_settlement_id' => $settlement->id,
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
                        'sales_settlement_id' => $settlement->id,
                        'customer_id' => $sale['customer_id'],
                        'invoice_number' => $sale['invoice_number'] ?? null,
                        'sale_amount' => $sale['sale_amount'],
                        'payment_type' => $sale['payment_type'],
                    ]);
                }
            }

            // Create credit sales breakdown records if any
            if (!empty($request->credit_sales)) {
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

            DB::commit();

            return redirect()
                ->route('sales-settlements.show', $settlement)
                ->with('success', "Sales Settlement '{$settlement->settlement_number}' created successfully.");

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
            'creditSales.supplier'
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

            foreach ($request->items as $item) {
                $totalQuantityIssued += $item['quantity_issued'];
                $totalValueIssued += $item['quantity_issued'] * $item['unit_cost'];
                $totalQuantitySold += $item['quantity_sold'];
                $totalQuantityReturned += $item['quantity_returned'] ?? 0;
                $totalQuantityShortage += $item['quantity_shortage'] ?? 0;
            }

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
            if (!empty($request->credit_sales)) {
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
