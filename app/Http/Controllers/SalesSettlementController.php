<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesSettlementRequest;
use App\Http\Requests\UpdateSalesSettlementRequest;
use App\Models\Customer;
use App\Models\GoodsIssue;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementAdvanceTax;
use App\Models\SalesSettlementBankTransfer;
use App\Models\SalesSettlementCashDenomination;
use App\Models\SalesSettlementCheque;
use App\Models\SalesSettlementExpense;
use App\Models\SalesSettlementItem;
use App\Models\SalesSettlementItemBatch;
use App\Models\SalesSettlementRecovery;
use App\Models\StockBatch;
use App\Models\VanStockBalance;
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

        // Get expense posting accounts (accounts starting with 5, non-group, active)
        $expenseAccounts = \App\Models\ChartOfAccount::where('account_code', 'LIKE', '5%')
            ->where('is_group', false)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);

        return view('sales-settlements.create', [
            'customers' => Customer::where('is_active', true)
                ->orderBy('customer_name')
                ->get(['id', 'customer_code', 'customer_name']),
            'expenseAccounts' => $expenseAccounts,
            'bankAccounts' => \App\Models\BankAccount::where('is_active', true)
                ->orderBy('account_name')
                ->get(['id', 'account_name', 'bank_name', 'account_number']),
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
                    'text' => $gi->issue_number.' - '.$gi->employee->full_name.' ('.$gi->issue_date->format('d M Y').')',
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

        // Get existing van stock balances (B/F - Brought Forward) for this vehicle
        $vanStockBalances = VanStockBalance::where('vehicle_id', $goodsIssue->vehicle_id)
            ->where('quantity_on_hand', '>', 0)
            ->get()
            ->keyBy('product_id');

        // Add batch breakdown to each goods issue item
        foreach ($goodsIssue->items as $item) {
            // Get current van stock for this product
            $vanStock = $vanStockBalances->get($item->product_id);
            $currentVanQty = $vanStock ? (float) $vanStock->quantity_on_hand : 0;

            // B/F (Brought Forward) = Current Van Stock - Quantity from THIS Goods Issue
            // Because van stock already includes the issued quantity from this goods issue
            $item->bf_quantity = max(0, $currentVanQty - (float) $item->quantity_issued);

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
                'bf_quantity' => $item->bf_quantity ?? 0,
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
            'employee_id' => $goodsIssue->employee_id,
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

            // Check if a settlement already exists for this goods issue
            $existingSettlement = SalesSettlement::where('goods_issue_id', $request->goods_issue_id)
                ->whereIn('status', ['draft', 'posted'])
                ->first();

            if ($existingSettlement) {
                // If it's posted, prevent creating another one
                if ($existingSettlement->status === 'posted') {
                    return back()
                        ->withInput()
                        ->with('error', "A posted settlement ({$existingSettlement->settlement_number}) already exists for this Goods Issue. Cannot create another settlement.");
                }

                // If it's draft, redirect to show page with message to edit
                return redirect()
                    ->route('sales-settlements.show', $existingSettlement)
                    ->with('info', "A draft settlement ({$existingSettlement->settlement_number}) already exists for this Goods Issue. You can view and edit it here.");
            }

            // Debug: Log the incoming request data
            Log::info('Sales Settlement Request Data', [
                'goods_issue_id' => $request->goods_issue_id,
                'items_count' => count($request->items ?? []),
                'items_data' => $request->items,
            ]);

            // Debug: Log batch-level details specifically
            foreach ($request->items as $itemIdx => $item) {
                if (isset($item['batches']) && is_array($item['batches'])) {
                    foreach ($item['batches'] as $batchIdx => $batch) {
                        Log::info("Batch Detail [Item {$itemIdx}][Batch {$batchIdx}]", [
                            'stock_batch_id' => $batch['stock_batch_id'] ?? 'N/A',
                            'batch_code' => $batch['batch_code'] ?? 'N/A',
                            'is_promotional' => $batch['is_promotional'] ?? 'N/A',
                            'quantity_sold' => $batch['quantity_sold'] ?? 'NOT_SET',
                            'quantity_returned' => $batch['quantity_returned'] ?? 'NOT_SET',
                            'quantity_shortage' => $batch['quantity_shortage'] ?? 'NOT_SET',
                        ]);
                    }
                }
            }

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

            $itemFinancials = [];

            foreach ($request->items as $index => $item) {
                $totalQuantityIssued += $item['quantity_issued'];
                $totalValueIssued += $item['quantity_issued'] * $item['unit_cost'];
                $totalQuantitySold += $item['quantity_sold'];
                $totalQuantityReturned += $item['quantity_returned'] ?? 0;
                $totalQuantityShortage += $item['quantity_shortage'] ?? 0;

                $financials = $this->calculateItemFinancialsUsingBatches($item);
                $itemFinancials[$index] = $financials;

                $totalCogs += $financials['total_cogs'];
                $totalSalesValue += $financials['total_sales_value'];
            }

            // Calculate Gross Profit = Sales Value - COGS
            $grossProfit = $totalSalesValue - $totalCogs;

            // Calculate denomination total
            $denomTotal = (float) (
                (($request->denom_5000 ?? 0) * 5000) +
                (($request->denom_1000 ?? 0) * 1000) +
                (($request->denom_500 ?? 0) * 500) +
                (($request->denom_100 ?? 0) * 100) +
                (($request->denom_50 ?? 0) * 50) +
                (($request->denom_20 ?? 0) * 20) +
                (($request->denom_10 ?? 0) * 10) +
                (float) ($request->denom_coins ?? 0)
            );

            // Prepare bank transfer totals
            $bankTransfersData = [];
            $totalBankTransfers = 0;
            if ($request->has('bank_transfers') && is_array($request->bank_transfers)) {
                foreach ($request->bank_transfers as $transfer) {
                    if (! empty($transfer['bank_account_id']) && floatval($transfer['amount'] ?? 0) > 0) {
                        $bankTransfersData[] = $transfer;
                        $totalBankTransfers += floatval($transfer['amount'] ?? 0);
                    }
                }
            }

            // Prepare cheque details
            $chequesData = [];
            $totalCheques = 0;
            if ($request->has('cheques') && is_array($request->cheques)) {
                foreach ($request->cheques as $cheque) {
                    if (! empty($cheque['cheque_number']) && floatval($cheque['amount'] ?? 0) > 0) {
                        $chequesData[] = $cheque;
                        $totalCheques += floatval($cheque['amount'] ?? 0);
                    }
                }
            }

            // Prepare recoveries details
            $recoveriesData = [];
            $totalRecoveries = 0;
            if ($request->has('recoveries_entries') && is_array($request->recoveries_entries)) {
                foreach ($request->recoveries_entries as $recovery) {
                    if (! empty($recovery['customer_id']) && floatval($recovery['amount'] ?? 0) > 0) {
                        $recoveriesData[] = $recovery;
                        $totalRecoveries += floatval($recovery['amount'] ?? 0);
                    }
                }
            }

            // Prepare credit sales details
            $creditSalesData = [];
            if ($request->has('credit_sales_entries') && is_array($request->credit_sales_entries)) {
                foreach ($request->credit_sales_entries as $creditSale) {
                    if (! empty($creditSale['customer_id']) && floatval($creditSale['sale_amount'] ?? 0) > 0) {
                        $creditSalesData[] = $creditSale;
                    }
                }
            }

            // Cash Sales Amount is now derived from denominations total
            $cashSalesAmount = $denomTotal;

            $totalSalesAmount = $cashSalesAmount +
                $totalCheques +
                $totalBankTransfers +
                ($request->credit_sales_amount ?? 0);

            // Cash collected is now strictly physical cash from denominations
            $cashCollected = $denomTotal;

            $cashToDeposit = $cashCollected + $totalRecoveries - ($request->expenses_claimed ?? 0);

            // Calculate total expenses from the expenses array (dynamic from Alpine.js)
            $totalExpenses = 0;
            if (! empty($request->expenses) && is_array($request->expenses)) {
                foreach ($request->expenses as $expense) {
                    $totalExpenses += floatval($expense['amount'] ?? 0);
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
                'cash_sales_amount' => $cashSalesAmount,
                'cheque_sales_amount' => $totalCheques,
                'bank_transfer_amount' => $totalBankTransfers,
                'credit_sales_amount' => $request->credit_sales_amount ?? 0,
                'credit_recoveries' => $totalRecoveries,
                'total_quantity_sold' => $totalQuantitySold,
                'total_quantity_returned' => $totalQuantityReturned,
                'total_quantity_shortage' => $totalQuantityShortage,
                'cash_collected' => $cashCollected,
                'cheques_collected' => $totalCheques,
                'expenses_claimed' => $totalExpenses,
                'gross_profit' => $grossProfit,
                'total_cogs' => $totalCogs,
                'cash_to_deposit' => $cashToDeposit,
                // Note: expenses, bank_transfers, cheques, cash_denominations now stored in normalized tables
                'status' => 'draft',
                'notes' => $request->notes,
            ]);

            // Create cash denomination record
            SalesSettlementCashDenomination::create([
                'sales_settlement_id' => $settlement->id,
                'denom_5000' => $request->denom_5000 ?? 0,
                'denom_1000' => $request->denom_1000 ?? 0,
                'denom_500' => $request->denom_500 ?? 0,
                'denom_100' => $request->denom_100 ?? 0,
                'denom_50' => $request->denom_50 ?? 0,
                'denom_20' => $request->denom_20 ?? 0,
                'denom_10' => $request->denom_10 ?? 0,
                'denom_coins' => $request->denom_coins ?? 0,
                'total_amount' => $denomTotal,
            ]);

            // Process bank transfers through customer_ledgers
            foreach ($bankTransfersData as $transfer) {
                if (floatval($transfer['amount'] ?? 0) > 0) {
                    // Create the bank transfer record for accounting purposes
                    // Customer ledger entries will be created when settlement is posted via LedgerService
                    SalesSettlementBankTransfer::create([
                        'sales_settlement_id' => $settlement->id,
                        'bank_account_id' => $transfer['bank_account_id'],
                        'customer_id' => $transfer['customer_id'] ?? null,
                        'amount' => floatval($transfer['amount'] ?? 0),
                        'reference_number' => $transfer['reference_number'] ?? null,
                        'transfer_date' => $transfer['transfer_date'] ?? $request->settlement_date,
                        'notes' => $transfer['notes'] ?? null,
                    ]);
                }
            }

            // Process cheques through customer_ledgers
            foreach ($chequesData as $cheque) {
                if (floatval($cheque['amount'] ?? 0) > 0) {
                    // Create the cheque record for tracking
                    // Customer ledger entries will be created when settlement is posted via LedgerService
                    SalesSettlementCheque::create([
                        'sales_settlement_id' => $settlement->id,
                        'customer_id' => $cheque['customer_id'] ?? null,
                        'bank_account_id' => $cheque['bank_account_id'] ?? null,
                        'cheque_number' => $cheque['cheque_number'],
                        'amount' => floatval($cheque['amount'] ?? 0),
                        'bank_name' => $cheque['bank_name'] ?? '',
                        'cheque_date' => $cheque['cheque_date'] ?? $request->settlement_date,
                        'account_holder_name' => $cheque['account_holder_name'] ?? null,
                        'status' => 'pending',
                        'notes' => $cheque['notes'] ?? null,
                    ]);
                }
            }

            // Create settlement items with batch breakdown
            foreach ($request->items as $index => $item) {
                $financials = $itemFinancials[$index] ?? [
                    'total_cogs' => 0,
                    'total_sales_value' => 0,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'unit_selling_price' => $item['selling_price'] ?? $item['unit_cost'] ?? 0,
                    'batch_financials' => [],
                ];

                $settlementItem = SalesSettlementItem::create([
                    'sales_settlement_id' => $settlement->id,
                    'goods_issue_item_id' => $item['goods_issue_item_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'quantity_issued' => $item['quantity_issued'],
                    'quantity_sold' => $item['quantity_sold'],
                    'quantity_returned' => $item['quantity_returned'] ?? 0,
                    'quantity_shortage' => $item['quantity_shortage'] ?? 0,
                    'unit_cost' => $financials['unit_cost'],
                    'unit_selling_price' => $financials['unit_selling_price'],
                    'total_cogs' => $financials['total_cogs'],
                    'total_sales_value' => $financials['total_sales_value'],
                ]);

                if (isset($item['batches']) && is_array($item['batches'])) {
                    // Auto-distribute item-level quantities to batches if batch-level values are all zero
                    $batchSoldSum = collect($item['batches'])->sum(fn ($b) => (float) ($b['quantity_sold'] ?? 0));
                    $batchReturnedSum = collect($item['batches'])->sum(fn ($b) => (float) ($b['quantity_returned'] ?? 0));
                    $batchShortageSum = collect($item['batches'])->sum(fn ($b) => (float) ($b['quantity_shortage'] ?? 0));

                    $autoDistribute = ($batchSoldSum == 0 && $batchReturnedSum == 0 && $batchShortageSum == 0);

                    $itemQtySold = (float) ($item['quantity_sold'] ?? 0);
                    $itemQtyReturned = (float) ($item['quantity_returned'] ?? 0);
                    $itemQtyShortage = (float) ($item['quantity_shortage'] ?? 0);

                    $remainingSold = $itemQtySold;
                    $remainingReturned = $itemQtyReturned;
                    $remainingShortage = $itemQtyShortage;

                    foreach ($item['batches'] as $batchIndex => $batch) {
                        $batchFinancial = $financials['batch_financials'][$batchIndex] ?? null;
                        $batchQtyIssued = (float) ($batch['quantity_issued'] ?? 0);

                        // If auto-distributing, allocate quantities proportionally based on issued qty
                        if ($autoDistribute && $batchQtyIssued > 0) {
                            $batchQtySold = min($batchQtyIssued, $remainingSold);
                            $batchQtyReturned = min($batchQtyIssued - $batchQtySold, $remainingReturned);
                            $batchQtyShortage = min($batchQtyIssued - $batchQtySold - $batchQtyReturned, $remainingShortage);

                            $remainingSold -= $batchQtySold;
                            $remainingReturned -= $batchQtyReturned;
                            $remainingShortage -= $batchQtyShortage;
                        } else {
                            $batchQtySold = (float) ($batch['quantity_sold'] ?? 0);
                            $batchQtyReturned = (float) ($batch['quantity_returned'] ?? 0);
                            $batchQtyShortage = (float) ($batch['quantity_shortage'] ?? 0);
                        }

                        SalesSettlementItemBatch::create([
                            'sales_settlement_item_id' => $settlementItem->id,
                            'stock_batch_id' => $batch['stock_batch_id'],
                            'batch_code' => $batch['batch_code'],
                            'quantity_issued' => $batchQtyIssued,
                            'quantity_sold' => $batchQtySold,
                            'quantity_returned' => $batchQtyReturned,
                            'quantity_shortage' => $batchQtyShortage,
                            'unit_cost' => $batchFinancial['unit_cost'] ?? $batch['unit_cost'] ?? $item['unit_cost'],
                            'selling_price' => $batchFinancial['selling_price'] ?? $batch['selling_price'] ?? $item['unit_cost'],
                            'is_promotional' => $batch['is_promotional'] ?? false,
                        ]);
                    }
                }
            }

            // Create advance tax breakdown records if any
            if (! empty($request->advance_taxes) && is_array($request->advance_taxes)) {
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

            // Create expense records in sales_settlement_expenses table (store ALL expenses including zero amounts)
            if (! empty($request->expenses) && is_array($request->expenses)) {
                foreach ($request->expenses as $expense) {
                    if (! empty($expense['expense_account_id'])) {
                        SalesSettlementExpense::create([
                            'sales_settlement_id' => $settlement->id,
                            'expense_date' => $request->settlement_date,
                            'expense_account_id' => $expense['expense_account_id'],
                            'amount' => floatval($expense['amount'] ?? 0),
                            'receipt_number' => $expense['receipt_number'] ?? null,
                            'description' => $expense['description'] ?? null,
                        ]);
                    }
                }
            }

            // Create recovery records in sales_settlement_recoveries table
            if (! empty($recoveriesData)) {
                foreach ($recoveriesData as $recovery) {
                    SalesSettlementRecovery::create([
                        'sales_settlement_id' => $settlement->id,
                        'customer_id' => $recovery['customer_id'],
                        'employee_id' => $goodsIssue->employee_id,
                        'recovery_number' => $recovery['recovery_number'] ?? null,
                        'payment_method' => $recovery['payment_method'] ?? 'cash',
                        'bank_account_id' => $recovery['bank_account_id'] ?? null,
                        'amount' => floatval($recovery['amount'] ?? 0),
                        'previous_balance' => floatval($recovery['previous_balance'] ?? 0),
                        'new_balance' => floatval($recovery['new_balance'] ?? 0),
                        'notes' => $recovery['notes'] ?? null,
                    ]);
                }
            }

            // Create credit sales records in sales_settlement_credit_sales table
            if (! empty($creditSalesData)) {
                foreach ($creditSalesData as $creditSale) {
                    SalesSettlementCreditSale::create([
                        'sales_settlement_id' => $settlement->id,
                        'customer_id' => $creditSale['customer_id'],
                        'employee_id' => $goodsIssue->employee_id,
                        'invoice_number' => $creditSale['invoice_number'] ?? null,
                        'sale_amount' => floatval($creditSale['sale_amount'] ?? 0),
                        'payment_received' => floatval($creditSale['payment_received'] ?? 0),
                        'previous_balance' => floatval($creditSale['previous_balance'] ?? 0),
                        'new_balance' => floatval($creditSale['new_balance'] ?? 0),
                        'notes' => $creditSale['notes'] ?? null,
                    ]);
                }
            }

            // NOTE: Credit sales are tracked in customer_employee_account_transactions
            // Ledger entries are created when the settlement is POSTED
            // Recalculate financials from the persisted batch data to avoid zeroed profit
            $this->recalcSettlementFinancials($settlement);

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
                ->with('error', 'Unable to create Sales Settlement: '.$e->getMessage());
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
            'customerEmployeeTransactions.account.customer',
            'customerEmployeeTransactions.account.employee',
            'advanceTaxes.customer',
            'expenses.expenseAccount',
            'cheques.bankAccount',
            'creditSales.customer',
            'recoveries.customer',
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

        $salesSettlement->load([
            'goodsIssue.items.product',
            'goodsIssue.items.uom',
            'goodsIssue.employee',
            'goodsIssue.vehicle',
            'goodsIssue.warehouse',
            'items.product',
            'items.batches',
            'creditSales.customer',
            'recoveries.customer',
            'advanceTaxes',
            'expenses',
        ]);

        // Get expense posting accounts (accounts starting with 5, non-group, active)
        $expenseAccounts = \App\Models\ChartOfAccount::where('account_code', 'LIKE', '5%')
            ->where('is_group', false)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);

        // Get customers for credit sales
        $customers = Customer::where('is_active', true)
            ->orderBy('customer_name')
            ->get(['id', 'customer_code', 'customer_name']);

        return view('sales-settlements.edit', [
            'settlement' => $salesSettlement,
            'expenseAccounts' => $expenseAccounts,
            'customers' => $customers,
            'bankAccounts' => \App\Models\BankAccount::where('is_active', true)
                ->orderBy('account_name')
                ->get(['id', 'account_name', 'bank_name', 'account_number']),
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

            // Check if another settlement exists for this goods issue (excluding current one)
            $existingSettlement = SalesSettlement::where('goods_issue_id', $request->goods_issue_id)
                ->where('id', '!=', $salesSettlement->id)
                ->whereIn('status', ['draft', 'posted'])
                ->first();

            if ($existingSettlement) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('error', "Another settlement ({$existingSettlement->settlement_number}) already exists for this Goods Issue. Cannot change to a Goods Issue that already has a settlement.");
            }

            // Calculate totals
            $totalQuantityIssued = 0;
            $totalValueIssued = 0;
            $totalQuantitySold = 0;
            $totalQuantityReturned = 0;
            $totalQuantityShortage = 0;
            $totalCogs = 0;
            $totalSalesValue = 0;

            $itemFinancials = [];

            foreach ($request->items as $index => $item) {
                $totalQuantityIssued += $item['quantity_issued'];
                $totalValueIssued += $item['quantity_issued'] * $item['unit_cost'];
                $totalQuantitySold += $item['quantity_sold'];
                $totalQuantityReturned += $item['quantity_returned'] ?? 0;
                $totalQuantityShortage += $item['quantity_shortage'] ?? 0;

                $financials = $this->calculateItemFinancialsUsingBatches($item);
                $itemFinancials[$index] = $financials;

                $totalCogs += $financials['total_cogs'];
                $totalSalesValue += $financials['total_sales_value'];
            }

            // Calculate gross profit
            $grossProfit = $totalSalesValue - $totalCogs;

            // Calculate denomination total
            $denomTotal = (float) (
                (($request->denom_5000 ?? 0) * 5000) +
                (($request->denom_1000 ?? 0) * 1000) +
                (($request->denom_500 ?? 0) * 500) +
                (($request->denom_100 ?? 0) * 100) +
                (($request->denom_50 ?? 0) * 50) +
                (($request->denom_20 ?? 0) * 20) +
                (($request->denom_10 ?? 0) * 10) +
                (float) ($request->denom_coins ?? 0)
            );

            // Prepare bank transfer totals
            $totalBankTransfers = 0;
            if ($request->has('bank_transfers') && is_array($request->bank_transfers)) {
                foreach ($request->bank_transfers as $transfer) {
                    $totalBankTransfers += floatval($transfer['amount'] ?? 0);
                }
            }

            // Prepare cheque totals
            $totalCheques = 0;
            if ($request->has('cheques') && is_array($request->cheques)) {
                foreach ($request->cheques as $cheque) {
                    $totalCheques += floatval($cheque['amount'] ?? 0);
                }
            }

            // Prepare recovery totals
            $totalRecoveries = 0;
            if ($request->has('recoveries_entries') && is_array($request->recoveries_entries)) {
                foreach ($request->recoveries_entries as $recovery) {
                    $totalRecoveries += floatval($recovery['amount'] ?? 0);
                }
            }

            // Cash Sales Amount is now derived from denominations total
            $cashSalesAmount = $denomTotal;

            $totalSalesAmount = $cashSalesAmount +
                $totalCheques +
                $totalBankTransfers +
                ($request->credit_sales_amount ?? 0);

            // Cash collected is now strictly physical cash from denominations
            $cashCollected = $denomTotal;

            $cashToDeposit = $cashCollected + $totalRecoveries - ($request->expenses_claimed ?? 0);

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
                'cash_sales_amount' => $cashSalesAmount,
                'cheque_sales_amount' => $totalCheques,
                'bank_transfer_amount' => $totalBankTransfers,
                'credit_sales_amount' => $request->credit_sales_amount ?? 0,
                'credit_recoveries' => $totalRecoveries,
                'total_quantity_sold' => $totalQuantitySold,
                'total_quantity_returned' => $totalQuantityReturned,
                'total_quantity_shortage' => $totalQuantityShortage,
                'cash_collected' => $cashCollected,
                'cheques_collected' => $totalCheques,
                'expenses_claimed' => $request->expenses_claimed ?? 0,
                'cash_to_deposit' => $cashToDeposit,
                'gross_profit' => $grossProfit,
                'total_cogs' => $totalCogs,
                'notes' => $request->notes,
            ]);

            // Delete old items and related records
            $salesSettlement->items()->delete();
            $salesSettlement->creditSales()->delete();
            $salesSettlement->recoveries()->delete();
            $salesSettlement->cashDenominations()->delete();
            $salesSettlement->expenses()->delete();
            $salesSettlement->bankTransfers()->delete();
            $salesSettlement->cheques()->delete();
            $salesSettlement->advanceTaxes()->delete();

            // Create cash denomination record
            SalesSettlementCashDenomination::create([
                'sales_settlement_id' => $salesSettlement->id,
                'denom_5000' => $request->denom_5000 ?? 0,
                'denom_1000' => $request->denom_1000 ?? 0,
                'denom_500' => $request->denom_500 ?? 0,
                'denom_100' => $request->denom_100 ?? 0,
                'denom_50' => $request->denom_50 ?? 0,
                'denom_20' => $request->denom_20 ?? 0,
                'denom_10' => $request->denom_10 ?? 0,
                'denom_coins' => $request->denom_coins ?? 0,
                'total_amount' => $denomTotal,
            ]);

            // Create settlement items with batch breakdown
            foreach ($request->items as $index => $item) {
                $financials = $itemFinancials[$index] ?? [
                    'total_cogs' => 0,
                    'total_sales_value' => 0,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'unit_selling_price' => $item['selling_price'] ?? $item['unit_cost'] ?? 0,
                    'batch_financials' => [],
                ];

                $settlementItem = SalesSettlementItem::create([
                    'sales_settlement_id' => $salesSettlement->id,
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'quantity_issued' => $item['quantity_issued'],
                    'quantity_sold' => $item['quantity_sold'],
                    'quantity_returned' => $item['quantity_returned'] ?? 0,
                    'quantity_shortage' => $item['quantity_shortage'] ?? 0,
                    'unit_cost' => $financials['unit_cost'],
                    'unit_selling_price' => $financials['unit_selling_price'],
                    'total_cogs' => $financials['total_cogs'],
                    'total_sales_value' => $financials['total_sales_value'],
                ]);

                // Store batch breakdown if available
                if (isset($item['batches']) && is_array($item['batches'])) {
                    // Auto-distribute item-level quantities to batches if batch-level values are all zero
                    $batchSoldSum = collect($item['batches'])->sum(fn ($b) => (float) ($b['quantity_sold'] ?? 0));
                    $batchReturnedSum = collect($item['batches'])->sum(fn ($b) => (float) ($b['quantity_returned'] ?? 0));
                    $batchShortageSum = collect($item['batches'])->sum(fn ($b) => (float) ($b['quantity_shortage'] ?? 0));

                    $autoDistribute = ($batchSoldSum == 0 && $batchReturnedSum == 0 && $batchShortageSum == 0);

                    $itemQtySold = (float) ($item['quantity_sold'] ?? 0);
                    $itemQtyReturned = (float) ($item['quantity_returned'] ?? 0);
                    $itemQtyShortage = (float) ($item['quantity_shortage'] ?? 0);

                    $remainingSold = $itemQtySold;
                    $remainingReturned = $itemQtyReturned;
                    $remainingShortage = $itemQtyShortage;

                    foreach ($item['batches'] as $batchIndex => $batch) {
                        $batchFinancial = $financials['batch_financials'][$batchIndex] ?? null;
                        $batchQtyIssued = (float) ($batch['quantity_issued'] ?? 0);

                        // If auto-distributing, allocate quantities proportionally based on issued qty
                        if ($autoDistribute && $batchQtyIssued > 0) {
                            $batchQtySold = min($batchQtyIssued, $remainingSold);
                            $batchQtyReturned = min($batchQtyIssued - $batchQtySold, $remainingReturned);
                            $batchQtyShortage = min($batchQtyIssued - $batchQtySold - $batchQtyReturned, $remainingShortage);

                            $remainingSold -= $batchQtySold;
                            $remainingReturned -= $batchQtyReturned;
                            $remainingShortage -= $batchQtyShortage;
                        } else {
                            $batchQtySold = (float) ($batch['quantity_sold'] ?? 0);
                            $batchQtyReturned = (float) ($batch['quantity_returned'] ?? 0);
                            $batchQtyShortage = (float) ($batch['quantity_shortage'] ?? 0);
                        }

                        SalesSettlementItemBatch::create([
                            'sales_settlement_item_id' => $settlementItem->id,
                            'stock_batch_id' => $batch['stock_batch_id'],
                            'batch_code' => $batch['batch_code'],
                            'quantity_issued' => $batchQtyIssued,
                            'quantity_sold' => $batchQtySold,
                            'quantity_returned' => $batchQtyReturned,
                            'quantity_shortage' => $batchQtyShortage,
                            'unit_cost' => $batchFinancial['unit_cost'] ?? $batch['unit_cost'] ?? $item['unit_cost'],
                            'selling_price' => $batchFinancial['selling_price'] ?? $batch['selling_price'] ?? $item['unit_cost'],
                            'is_promotional' => $batch['is_promotional'] ?? false,
                        ]);
                    }
                }
            }

            // Create advance tax breakdown records if any
            if (! empty($request->advance_taxes) && is_array($request->advance_taxes)) {
                foreach ($request->advance_taxes as $advanceTax) {
                    SalesSettlementAdvanceTax::create([
                        'sales_settlement_id' => $salesSettlement->id,
                        'customer_id' => $advanceTax['customer_id'],
                        'sale_amount' => $advanceTax['sale_amount'] ?? 0,
                        'tax_rate' => $advanceTax['tax_rate'] ?? 0.25,
                        'tax_amount' => $advanceTax['tax_amount'],
                        'invoice_number' => $advanceTax['invoice_number'] ?? null,
                        'notes' => $advanceTax['notes'] ?? null,
                    ]);
                }
            }

            // Create expense records in sales_settlement_expenses table (store ALL expenses including zero amounts)
            if (! empty($request->expenses) && is_array($request->expenses)) {
                foreach ($request->expenses as $expense) {
                    if (! empty($expense['expense_account_id'])) {
                        SalesSettlementExpense::create([
                            'sales_settlement_id' => $salesSettlement->id,
                            'expense_date' => $request->settlement_date,
                            'expense_account_id' => $expense['expense_account_id'],
                            'amount' => floatval($expense['amount'] ?? 0),
                            'receipt_number' => $expense['receipt_number'] ?? null,
                            'description' => $expense['description'] ?? null,
                        ]);
                    }
                }
            }

            // Create recovery records
            if (! empty($request->recoveries_entries) && is_array($request->recoveries_entries)) {
                foreach ($request->recoveries_entries as $recovery) {
                    if (! empty($recovery['customer_id']) && floatval($recovery['amount'] ?? 0) > 0) {
                        SalesSettlementRecovery::create([
                            'sales_settlement_id' => $salesSettlement->id,
                            'customer_id' => $recovery['customer_id'],
                            'employee_id' => $goodsIssue->employee_id,
                            'recovery_number' => $recovery['recovery_number'] ?? null,
                            'payment_method' => $recovery['payment_method'] ?? 'cash',
                            'bank_account_id' => $recovery['bank_account_id'] ?? null,
                            'amount' => floatval($recovery['amount'] ?? 0),
                            'previous_balance' => floatval($recovery['previous_balance'] ?? 0),
                            'new_balance' => floatval($recovery['new_balance'] ?? 0),
                            'notes' => $recovery['notes'] ?? null,
                        ]);
                    }
                }
            }

            if ($request->has('bank_transfers') && is_array($request->bank_transfers)) {
                foreach ($request->bank_transfers as $transfer) {
                    if (! empty($transfer['bank_account_id']) && floatval($transfer['amount'] ?? 0) > 0) {
                        SalesSettlementBankTransfer::create([
                            'sales_settlement_id' => $salesSettlement->id,
                            'bank_account_id' => $transfer['bank_account_id'],
                            'customer_id' => $transfer['customer_id'] ?? null,
                            'amount' => floatval($transfer['amount'] ?? 0),
                            'reference_number' => $transfer['reference_number'] ?? null,
                            'transfer_date' => $transfer['transfer_date'] ?? $request->settlement_date,
                            'notes' => $transfer['notes'] ?? null,
                        ]);
                    }
                }
            }

            if ($request->has('cheques') && is_array($request->cheques)) {
                foreach ($request->cheques as $cheque) {
                    if (! empty($cheque['cheque_number']) && floatval($cheque['amount'] ?? 0) > 0) {
                        SalesSettlementCheque::create([
                            'sales_settlement_id' => $salesSettlement->id,
                            'customer_id' => $cheque['customer_id'] ?? null,
                            'bank_account_id' => $cheque['bank_account_id'] ?? null,
                            'cheque_number' => $cheque['cheque_number'],
                            'amount' => floatval($cheque['amount'] ?? 0),
                            'bank_name' => $cheque['bank_name'] ?? '',
                            'cheque_date' => $cheque['cheque_date'] ?? $request->settlement_date,
                            'account_holder_name' => $cheque['account_holder_name'] ?? null,
                            'status' => 'pending',
                            'notes' => $cheque['notes'] ?? null,
                        ]);
                    }
                }
            }

            // Recalculate financials from persisted batches to keep profit in sync
            $this->recalcSettlementFinancials($salesSettlement);

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

            // Force delete all related records to allow settlement_number reuse
            foreach ($salesSettlement->items as $item) {
                $item->batches()->forceDelete();
                $item->forceDelete();
            }
            $salesSettlement->creditSales()->forceDelete();
            $salesSettlement->advanceTaxes()->forceDelete();
            $salesSettlement->expenses()->forceDelete();
            $salesSettlement->bankTransfers()->forceDelete();
            $salesSettlement->cheques()->forceDelete();
            $salesSettlement->forceDelete();

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
     * Calculate item-level financials using batch-level pricing and costs.
     */
    private function calculateItemFinancialsUsingBatches(array $item): array
    {
        $qtySold = (float) ($item['quantity_sold'] ?? 0);
        $unitCostFallback = (float) ($item['unit_cost'] ?? 0);
        $unitPriceFallback = (float) ($item['selling_price'] ?? $unitCostFallback);

        $totalSalesValue = 0.0;
        $totalCogs = 0.0;
        $soldQtyFromBatches = 0.0;
        $batchFinancials = [];

        if (isset($item['batches']) && is_array($item['batches'])) {
            foreach ($item['batches'] as $index => $batch) {
                $stockBatch = isset($batch['stock_batch_id']) ? StockBatch::find($batch['stock_batch_id']) : null;

                $effectiveSellingPrice = null;
                $batchUnitCost = $unitCostFallback;
                $isPromotional = (bool) ($batch['is_promotional'] ?? false);

                if ($stockBatch) {
                    $effectiveSellingPrice = $stockBatch->is_promotional
                        ? ($stockBatch->promotional_selling_price ?? $stockBatch->selling_price)
                        : $stockBatch->selling_price;
                    $batchUnitCost = (float) $stockBatch->unit_cost;
                    $isPromotional = (bool) $stockBatch->is_promotional;
                }

                if ($effectiveSellingPrice === null) {
                    $effectiveSellingPrice = $batch['selling_price'] ?? $unitPriceFallback;
                }

                $batchQtySold = (float) ($batch['quantity_sold'] ?? 0);

                $totalSalesValue += $batchQtySold * $effectiveSellingPrice;
                $totalCogs += $batchQtySold * $batchUnitCost;
                $soldQtyFromBatches += $batchQtySold;

                $batchFinancials[$index] = [
                    'selling_price' => $effectiveSellingPrice,
                    'unit_cost' => $batchUnitCost,
                    'is_promotional' => $isPromotional,
                ];
            }
        }

        if ($soldQtyFromBatches <= 0) {
            $totalSalesValue = $qtySold * $unitPriceFallback;
            $totalCogs = $qtySold * $unitCostFallback;
            $soldQtyFromBatches = $qtySold;
        }

        $unitSellingPrice = $soldQtyFromBatches > 0 ? $totalSalesValue / $soldQtyFromBatches : $unitPriceFallback;
        $unitCost = $soldQtyFromBatches > 0 ? $totalCogs / $soldQtyFromBatches : $unitCostFallback;

        return [
            'total_sales_value' => $totalSalesValue,
            'total_cogs' => $totalCogs,
            'unit_selling_price' => $unitSellingPrice,
            'unit_cost' => $unitCost,
            'batch_financials' => $batchFinancials,
        ];
    }

    /**
     * Recalculate item and settlement totals from persisted batch data.
     */
    private function recalcSettlementFinancials(SalesSettlement $settlement): void
    {
        // Force refresh items and batches from database to get newly created records
        $settlement->load(['items.batches']);

        foreach ($settlement->items as $item) {
            $quantitySold = (float) $item->batches->sum('quantity_sold');
            $quantityReturned = (float) $item->batches->sum('quantity_returned');

            $totalSalesValue = $item->batches->sum(function ($batch) {
                return (float) $batch->quantity_sold * (float) $batch->selling_price;
            });

            $totalCogs = $item->batches->sum(function ($batch) {
                return (float) $batch->quantity_sold * (float) $batch->unit_cost;
            });

            if ($quantitySold > 0 || $quantityReturned > 0 || $totalSalesValue > 0 || $totalCogs > 0) {
                $item->quantity_sold = $quantitySold;
                $item->quantity_returned = $quantityReturned;
                $item->total_sales_value = $totalSalesValue;
                $item->total_cogs = $totalCogs;
                $item->unit_selling_price = $quantitySold > 0 ? $totalSalesValue / $quantitySold : $item->unit_selling_price;
                $item->unit_cost = $quantitySold > 0 ? $totalCogs / $quantitySold : $item->unit_cost;
                $item->save();
            }
        }

        // Refresh settlement items to get the updated totals
        $settlement->refresh();

        $totalSalesValueAll = (float) $settlement->items->sum('total_sales_value');

        $settlement->total_quantity_sold = $settlement->items->sum('quantity_sold');
        $settlement->total_quantity_returned = $settlement->items->sum('quantity_returned');
        // @phpstan-ignore-next-line decimal columns accept floats
        $settlement->total_cogs = round($settlement->items->sum('total_cogs'), 2);
        // @phpstan-ignore-next-line decimal columns accept floats
        $settlement->gross_profit = round($totalSalesValueAll - (float) $settlement->total_cogs, 2);
        $settlement->save();
    }

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
