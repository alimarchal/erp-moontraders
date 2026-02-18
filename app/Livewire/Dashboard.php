<?php

namespace App\Livewire;

use App\Models\CurrentStock;
use App\Models\Customer;
use App\Models\GoodsIssue;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\StockMovement;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    /** @var array<string, mixed> */
    public array $kpiCards = [];

    /** @var array<string, mixed> */
    public array $monthlySalesTrend = [];

    /** @var array<string, mixed> */
    public array $salesByPaymentMethod = [];

    /** @var array<string, mixed> */
    public array $revenueVsCogs = [];

    /** @var array<string, mixed> */
    public array $topProductsByStockValue = [];

    /** @var array<string, mixed> */
    public array $purchasesVsPayments = [];

    /** @var array<string, mixed> */
    public array $settlementStatusDistribution = [];

    /** @var array<string, mixed> */
    public array $dailySalesTrend = [];

    /** @var array<string, mixed> */
    public array $journalEntryStatus = [];

    /** @var array<string, mixed> */
    public array $topProductsBySales = [];

    /** @var array<string, mixed> */
    public array $grnVsGoodsIssueTrend = [];

    /** @var array<string, mixed> */
    public array $topSalespersonBySales = [];

    /** @var array<string, mixed> */
    public array $pendingItems = [];

    /** @var array<string, mixed> */
    public array $profitMarginGauge = [];

    /** @var array<string, mixed> */
    public array $cashVsCreditTrend = [];

    /** @var array<string, mixed> */
    public array $salesByDayOfWeek = [];

    /** @var array<string, mixed> */
    public array $warehouseStockDistribution = [];

    /** @var array<string, mixed> */
    public array $stockMovementBreakdown = [];

    /** @var array<string, mixed> */
    public array $customerChannelDistribution = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->loadKpiCards($user);
        $this->loadPendingItems($user);

        if ($user->can('view-any-report') || $user->can('accounting-view')) {
            $this->loadMonthlySalesTrend();
            $this->loadRevenueVsCogs();
            $this->loadPurchasesVsPayments();
            $this->loadJournalEntryStatus();
        }

        if ($user->can('sales-settlement-list') || $user->can('view-any-report')) {
            $this->loadSalesByPaymentMethod();
            $this->loadDailySalesTrend();
            $this->loadTopProductsBySales();
            $this->loadSettlementStatusDistribution();
            $this->loadTopSalespersonBySales();
            $this->loadProfitMarginGauge();
            $this->loadCashVsCreditTrend();
            $this->loadSalesByDayOfWeek();
            $this->loadCustomerChannelDistribution();
        }

        if ($user->can('inventory-view') || $user->can('view-any-report')) {
            $this->loadTopProductsByStockValue();
            $this->loadGrnVsGoodsIssueTrend();
            $this->loadWarehouseStockDistribution();
            $this->loadStockMovementBreakdown();
        }
    }

    public function render()
    {
        return view('livewire.dashboard');
    }

    /**
     * @param  \App\Models\User  $user
     */
    private function loadKpiCards($user): void
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        if ($user->can('sales-settlement-list')) {
            $this->kpiCards['totalSalesThisMonth'] = (float) SalesSettlement::query()
                ->where('status', 'posted')
                ->whereBetween('settlement_date', [$startOfMonth, $endOfMonth])
                ->sum('total_sales_amount');

            $this->kpiCards['cashCollectedThisMonth'] = (float) SalesSettlement::query()
                ->where('status', 'posted')
                ->whereBetween('settlement_date', [$startOfMonth, $endOfMonth])
                ->sum('cash_sales_amount');

            $this->kpiCards['creditSalesThisMonth'] = (float) SalesSettlement::query()
                ->where('status', 'posted')
                ->whereBetween('settlement_date', [$startOfMonth, $endOfMonth])
                ->sum('credit_sales_amount');
        }

        if ($user->can('goods-receipt-note-list')) {
            $this->kpiCards['totalPurchasesThisMonth'] = (float) GoodsReceiptNote::query()
                ->where('status', 'posted')
                ->whereBetween('receipt_date', [$startOfMonth, $endOfMonth])
                ->sum('grand_total');
        }

        if ($user->can('supplier-payment-list')) {
            $totalGrnPosted = (float) GoodsReceiptNote::query()
                ->where('status', 'posted')
                ->sum('grand_total');
            $totalPaymentsPosted = (float) SupplierPayment::query()
                ->where('status', 'posted')
                ->sum('amount');
            $this->kpiCards['outstandingPayables'] = $totalGrnPosted - $totalPaymentsPosted;

            $this->kpiCards['paymentsThisMonth'] = (float) SupplierPayment::query()
                ->where('status', 'posted')
                ->whereBetween('payment_date', [$startOfMonth, $endOfMonth])
                ->sum('amount');
        }

        if ($user->can('inventory-view')) {
            $this->kpiCards['totalInventoryValue'] = (float) CurrentStock::query()->sum('total_value');
            $this->kpiCards['productsInStock'] = CurrentStock::query()->where('quantity_on_hand', '>', 0)->count();
            $this->kpiCards['totalProducts'] = Product::query()->where('is_active', true)->count();
        }

        if ($user->can('goods-issue-list')) {
            $this->kpiCards['goodsIssuedThisMonth'] = (float) GoodsIssue::query()
                ->where('status', 'issued')
                ->whereBetween('issue_date', [$startOfMonth, $endOfMonth])
                ->sum('total_value');
        }

        if ($user->can('journal-entry-list')) {
            $this->kpiCards['draftJournalEntries'] = JournalEntry::query()
                ->where('status', 'draft')
                ->count();
        }

        if ($user->can('goods-receipt-note-list')) {
            $this->kpiCards['grnCountThisMonth'] = GoodsReceiptNote::query()
                ->where('status', 'posted')
                ->whereBetween('receipt_date', [$startOfMonth, $endOfMonth])
                ->count();
        }

        if ($user->can('sales-settlement-list')) {
            $this->kpiCards['pendingSettlements'] = SalesSettlement::query()
                ->where('status', 'draft')
                ->count();

            $this->kpiCards['grossProfitThisMonth'] = (float) SalesSettlement::query()
                ->where('status', 'posted')
                ->whereBetween('settlement_date', [$startOfMonth, $endOfMonth])
                ->sum('gross_profit');
        }
    }

    private function loadMonthlySalesTrend(): void
    {
        $sixMonthsAgo = Carbon::now()->subMonths(11)->startOfMonth();

        $data = SalesSettlement::query()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', $sixMonthsAgo)
            ->selectRaw('EXTRACT(YEAR FROM settlement_date) as year, EXTRACT(MONTH FROM settlement_date) as month')
            ->selectRaw('SUM(total_sales_amount) as total_sales')
            ->selectRaw('SUM(gross_profit) as total_profit')
            ->groupByRaw('EXTRACT(YEAR FROM settlement_date), EXTRACT(MONTH FROM settlement_date)')
            ->orderByRaw('EXTRACT(YEAR FROM settlement_date), EXTRACT(MONTH FROM settlement_date)')
            ->get();

        $labels = [];
        $sales = [];
        $profits = [];

        foreach ($data as $row) {
            $labels[] = Carbon::createFromDate((int) $row->year, (int) $row->month, 1)->format('M Y');
            $sales[] = round((float) $row->total_sales, 2);
            $profits[] = round((float) $row->total_profit, 2);
        }

        $this->monthlySalesTrend = [
            'labels' => $labels,
            'sales' => $sales,
            'profits' => $profits,
        ];
    }

    private function loadSalesByPaymentMethod(): void
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $data = SalesSettlement::query()
            ->where('status', 'posted')
            ->whereBetween('settlement_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('SUM(cash_sales_amount) as cash')
            ->selectRaw('SUM(cheque_sales_amount) as cheque')
            ->selectRaw('SUM(bank_transfer_amount) as bank_transfer')
            ->selectRaw('SUM(credit_sales_amount) as credit')
            ->selectRaw('SUM(bank_slips_amount) as bank_slips')
            ->first();

        $this->salesByPaymentMethod = [
            'labels' => ['Cash', 'Cheque', 'Bank Transfer', 'Credit', 'Bank Slips'],
            'values' => [
                round((float) ($data->cash ?? 0), 2),
                round((float) ($data->cheque ?? 0), 2),
                round((float) ($data->bank_transfer ?? 0), 2),
                round((float) ($data->credit ?? 0), 2),
                round((float) ($data->bank_slips ?? 0), 2),
            ],
        ];
    }

    private function loadRevenueVsCogs(): void
    {
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        $data = SalesSettlement::query()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', $sixMonthsAgo)
            ->selectRaw('EXTRACT(YEAR FROM settlement_date) as year, EXTRACT(MONTH FROM settlement_date) as month')
            ->selectRaw('SUM(total_sales_amount) as revenue')
            ->selectRaw('SUM(total_cogs) as cogs')
            ->selectRaw('SUM(expenses_claimed) as expenses')
            ->groupByRaw('EXTRACT(YEAR FROM settlement_date), EXTRACT(MONTH FROM settlement_date)')
            ->orderByRaw('EXTRACT(YEAR FROM settlement_date), EXTRACT(MONTH FROM settlement_date)')
            ->get();

        $labels = [];
        $revenue = [];
        $cogs = [];
        $expenses = [];

        foreach ($data as $row) {
            $labels[] = Carbon::createFromDate((int) $row->year, (int) $row->month, 1)->format('M Y');
            $revenue[] = round((float) $row->revenue, 2);
            $cogs[] = round((float) $row->cogs, 2);
            $expenses[] = round((float) $row->expenses, 2);
        }

        $this->revenueVsCogs = [
            'labels' => $labels,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'expenses' => $expenses,
        ];
    }

    private function loadTopProductsByStockValue(): void
    {
        $data = CurrentStock::query()
            ->join('products', 'current_stock.product_id', '=', 'products.id')
            ->where('current_stock.quantity_on_hand', '>', 0)
            ->select('products.product_name', 'current_stock.total_value', 'current_stock.quantity_on_hand')
            ->orderByDesc('current_stock.total_value')
            ->limit(10)
            ->get();

        $this->topProductsByStockValue = [
            'labels' => $data->pluck('product_name')->toArray(),
            'values' => $data->pluck('total_value')->map(fn ($v) => round((float) $v, 2))->toArray(),
        ];
    }

    private function loadPurchasesVsPayments(): void
    {
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        $purchases = GoodsReceiptNote::query()
            ->where('status', 'posted')
            ->where('receipt_date', '>=', $sixMonthsAgo)
            ->selectRaw('EXTRACT(YEAR FROM receipt_date) as year, EXTRACT(MONTH FROM receipt_date) as month')
            ->selectRaw('SUM(grand_total) as total')
            ->groupByRaw('EXTRACT(YEAR FROM receipt_date), EXTRACT(MONTH FROM receipt_date)')
            ->orderByRaw('EXTRACT(YEAR FROM receipt_date), EXTRACT(MONTH FROM receipt_date)')
            ->get()
            ->keyBy(fn ($row) => $row->year.'-'.$row->month);

        $payments = SupplierPayment::query()
            ->where('status', 'posted')
            ->where('payment_date', '>=', $sixMonthsAgo)
            ->selectRaw('EXTRACT(YEAR FROM payment_date) as year, EXTRACT(MONTH FROM payment_date) as month')
            ->selectRaw('SUM(amount) as total')
            ->groupByRaw('EXTRACT(YEAR FROM payment_date), EXTRACT(MONTH FROM payment_date)')
            ->orderByRaw('EXTRACT(YEAR FROM payment_date), EXTRACT(MONTH FROM payment_date)')
            ->get()
            ->keyBy(fn ($row) => $row->year.'-'.$row->month);

        $labels = [];
        $purchaseValues = [];
        $paymentValues = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $key = $date->year.'-'.$date->month;
            $labels[] = $date->format('M Y');
            $purchaseValues[] = round((float) ($purchases[$key]->total ?? 0), 2);
            $paymentValues[] = round((float) ($payments[$key]->total ?? 0), 2);
        }

        $this->purchasesVsPayments = [
            'labels' => $labels,
            'purchases' => $purchaseValues,
            'payments' => $paymentValues,
        ];
    }

    private function loadSettlementStatusDistribution(): void
    {
        $data = SalesSettlement::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->settlementStatusDistribution = [
            'labels' => array_map(fn ($s) => ucfirst($s), array_keys($data)),
            'values' => array_values($data),
        ];
    }

    private function loadDailySalesTrend(): void
    {
        $thirtyDaysAgo = Carbon::now()->subDays(29)->startOfDay();

        $data = SalesSettlement::query()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(settlement_date) as date')
            ->selectRaw('SUM(total_sales_amount) as total_sales')
            ->selectRaw('COUNT(*) as settlement_count')
            ->groupByRaw('DATE(settlement_date)')
            ->orderByRaw('DATE(settlement_date)')
            ->get()
            ->keyBy('date');

        $labels = [];
        $sales = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('d M');
            $sales[] = round((float) ($data[$date]->total_sales ?? 0), 2);
        }

        $this->dailySalesTrend = [
            'labels' => $labels,
            'sales' => $sales,
        ];
    }

    private function loadJournalEntryStatus(): void
    {
        $data = JournalEntry::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->journalEntryStatus = [
            'labels' => array_map(fn ($s) => ucfirst($s), array_keys($data)),
            'values' => array_values($data),
        ];
    }

    private function loadTopProductsBySales(): void
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $data = SalesSettlementItem::query()
            ->join('sales_settlements', 'sales_settlement_items.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('products', 'sales_settlement_items.product_id', '=', 'products.id')
            ->where('sales_settlements.status', 'posted')
            ->whereBetween('sales_settlements.settlement_date', [$startOfMonth, $endOfMonth])
            ->whereNull('sales_settlements.deleted_at')
            ->select('products.product_name')
            ->selectRaw('SUM(sales_settlement_items.total_sales_value) as total_sales')
            ->groupBy('products.product_name')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();

        $this->topProductsBySales = [
            'labels' => $data->pluck('product_name')->toArray(),
            'values' => $data->pluck('total_sales')->map(fn ($v) => round((float) $v, 2))->toArray(),
        ];
    }

    private function loadGrnVsGoodsIssueTrend(): void
    {
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        $grns = GoodsReceiptNote::query()
            ->where('status', 'posted')
            ->where('receipt_date', '>=', $sixMonthsAgo)
            ->selectRaw('EXTRACT(YEAR FROM receipt_date) as year, EXTRACT(MONTH FROM receipt_date) as month')
            ->selectRaw('SUM(grand_total) as total')
            ->groupByRaw('EXTRACT(YEAR FROM receipt_date), EXTRACT(MONTH FROM receipt_date)')
            ->orderByRaw('EXTRACT(YEAR FROM receipt_date), EXTRACT(MONTH FROM receipt_date)')
            ->get()
            ->keyBy(fn ($row) => $row->year.'-'.$row->month);

        $issues = GoodsIssue::query()
            ->where('status', 'issued')
            ->where('issue_date', '>=', $sixMonthsAgo)
            ->selectRaw('EXTRACT(YEAR FROM issue_date) as year, EXTRACT(MONTH FROM issue_date) as month')
            ->selectRaw('SUM(total_value) as total')
            ->groupByRaw('EXTRACT(YEAR FROM issue_date), EXTRACT(MONTH FROM issue_date)')
            ->orderByRaw('EXTRACT(YEAR FROM issue_date), EXTRACT(MONTH FROM issue_date)')
            ->get()
            ->keyBy(fn ($row) => $row->year.'-'.$row->month);

        $labels = [];
        $grnValues = [];
        $issueValues = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $key = $date->year.'-'.$date->month;
            $labels[] = $date->format('M Y');
            $grnValues[] = round((float) ($grns[$key]->total ?? 0), 2);
            $issueValues[] = round((float) ($issues[$key]->total ?? 0), 2);
        }

        $this->grnVsGoodsIssueTrend = [
            'labels' => $labels,
            'grn' => $grnValues,
            'issues' => $issueValues,
        ];
    }

    private function loadTopSalespersonBySales(): void
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $data = SalesSettlement::query()
            ->join('employees', 'sales_settlements.employee_id', '=', 'employees.id')
            ->where('sales_settlements.status', 'posted')
            ->whereBetween('sales_settlements.settlement_date', [$startOfMonth, $endOfMonth])
            ->whereNull('sales_settlements.deleted_at')
            ->select('employees.name')
            ->selectRaw('SUM(sales_settlements.total_sales_amount) as total_sales')
            ->selectRaw('COUNT(*) as trips')
            ->groupBy('employees.name')
            ->orderByDesc('total_sales')
            ->limit(8)
            ->get();

        $this->topSalespersonBySales = [
            'labels' => $data->pluck('name')->toArray(),
            'values' => $data->pluck('total_sales')->map(fn ($v) => round((float) $v, 2))->toArray(),
            'trips' => $data->pluck('trips')->toArray(),
        ];
    }

    /**
     * @param  \App\Models\User  $user
     */
    private function loadPendingItems($user): void
    {
        if ($user->can('sales-settlement-list')) {
            $this->pendingItems['draftSettlements'] = SalesSettlement::query()
                ->where('status', 'draft')
                ->count();
        }

        if ($user->can('goods-receipt-note-list')) {
            $this->pendingItems['draftGrns'] = GoodsReceiptNote::query()
                ->where('status', 'draft')
                ->count();
        }

        if ($user->can('goods-issue-list')) {
            $this->pendingItems['draftGoodsIssues'] = GoodsIssue::query()
                ->where('status', 'draft')
                ->count();
        }

        if ($user->can('journal-entry-list')) {
            $this->pendingItems['draftJournalEntries'] = JournalEntry::query()
                ->where('status', 'draft')
                ->count();
        }

        if ($user->can('supplier-payment-list')) {
            $this->pendingItems['draftPayments'] = SupplierPayment::query()
                ->where('status', 'draft')
                ->count();
        }
    }

    private function loadProfitMarginGauge(): void
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $data = SalesSettlement::query()
            ->where('status', 'posted')
            ->whereBetween('settlement_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('SUM(total_sales_amount) as revenue, SUM(gross_profit) as profit')
            ->first();

        $revenue = (float) ($data->revenue ?? 0);
        $profit = (float) ($data->profit ?? 0);
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0;

        $this->profitMarginGauge = [
            'margin' => $margin,
            'revenue' => round($revenue, 2),
            'profit' => round($profit, 2),
        ];
    }

    private function loadCashVsCreditTrend(): void
    {
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        $data = SalesSettlement::query()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', $sixMonthsAgo)
            ->selectRaw('EXTRACT(YEAR FROM settlement_date) as year, EXTRACT(MONTH FROM settlement_date) as month')
            ->selectRaw('SUM(cash_sales_amount) as cash')
            ->selectRaw('SUM(credit_sales_amount) as credit')
            ->selectRaw('SUM(cheque_sales_amount) as cheque')
            ->selectRaw('SUM(bank_transfer_amount) as bank_transfer')
            ->groupByRaw('EXTRACT(YEAR FROM settlement_date), EXTRACT(MONTH FROM settlement_date)')
            ->orderByRaw('EXTRACT(YEAR FROM settlement_date), EXTRACT(MONTH FROM settlement_date)')
            ->get()
            ->keyBy(fn ($row) => $row->year.'-'.$row->month);

        $labels = [];
        $cashValues = [];
        $creditValues = [];
        $chequeValues = [];
        $bankValues = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $key = $date->year.'-'.$date->month;
            $labels[] = $date->format('M Y');
            $cashValues[] = round((float) ($data[$key]->cash ?? 0), 2);
            $creditValues[] = round((float) ($data[$key]->credit ?? 0), 2);
            $chequeValues[] = round((float) ($data[$key]->cheque ?? 0), 2);
            $bankValues[] = round((float) ($data[$key]->bank_transfer ?? 0), 2);
        }

        $this->cashVsCreditTrend = [
            'labels' => $labels,
            'cash' => $cashValues,
            'credit' => $creditValues,
            'cheque' => $chequeValues,
            'bank' => $bankValues,
        ];
    }

    private function loadSalesByDayOfWeek(): void
    {
        $ninetyDaysAgo = Carbon::now()->subDays(90)->startOfDay();
        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $data = SalesSettlement::query()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', $ninetyDaysAgo)
            ->selectRaw('DATE(settlement_date) as sale_date')
            ->selectRaw('SUM(total_sales_amount) as total_sales')
            ->selectRaw('COUNT(*) as trips')
            ->groupByRaw('DATE(settlement_date)')
            ->get();

        $byDow = array_fill(0, 7, ['sales' => 0, 'trips' => 0]);
        foreach ($data as $row) {
            $dow = Carbon::parse($row->sale_date)->dayOfWeekIso - 1;
            $byDow[$dow]['sales'] += (float) $row->total_sales;
            $byDow[$dow]['trips'] += (int) $row->trips;
        }

        $values = [];
        $trips = [];
        for ($i = 0; $i < 7; $i++) {
            $values[] = round($byDow[$i]['sales'], 2);
            $trips[] = $byDow[$i]['trips'];
        }

        $this->salesByDayOfWeek = [
            'labels' => $dayNames,
            'values' => $values,
            'trips' => $trips,
        ];
    }

    private function loadWarehouseStockDistribution(): void
    {
        $data = CurrentStock::query()
            ->join('warehouses', 'current_stock.warehouse_id', '=', 'warehouses.id')
            ->where('current_stock.quantity_on_hand', '>', 0)
            ->where('warehouses.disabled', false)
            ->select('warehouses.warehouse_name')
            ->selectRaw('SUM(current_stock.total_value) as total_value')
            ->selectRaw('SUM(current_stock.quantity_on_hand) as total_qty')
            ->selectRaw('COUNT(DISTINCT current_stock.product_id) as product_count')
            ->groupBy('warehouses.warehouse_name')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        $treemapData = $data->map(fn ($row) => [
            'x' => $row->warehouse_name,
            'y' => round((float) $row->total_value, 2),
        ])->toArray();

        $this->warehouseStockDistribution = [
            'labels' => $data->pluck('warehouse_name')->toArray(),
            'values' => $data->pluck('total_value')->map(fn ($v) => round((float) $v, 2))->toArray(),
            'treemap' => $treemapData,
            'quantities' => $data->pluck('total_qty')->map(fn ($v) => round((float) $v, 0))->toArray(),
            'products' => $data->pluck('product_count')->toArray(),
        ];
    }

    private function loadStockMovementBreakdown(): void
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30)->startOfDay();

        $data = StockMovement::query()
            ->where('movement_date', '>=', $thirtyDaysAgo)
            ->selectRaw('movement_type, SUM(quantity) as total_qty, SUM(total_value) as total_value')
            ->groupBy('movement_type')
            ->get();

        $inward = ['grn' => 0, 'goods_return' => 0, 'return' => 0];
        $outward = ['goods_issue' => 0, 'sale' => 0, 'damage' => 0, 'shortage' => 0, 'theft' => 0];

        foreach ($data as $row) {
            $type = $row->movement_type;
            $val = round((float) $row->total_value, 2);
            if (isset($inward[$type])) {
                $inward[$type] = $val;
            } elseif (isset($outward[$type])) {
                $outward[$type] = $val;
            }
        }

        $this->stockMovementBreakdown = [
            'labels' => ['GRN', 'Returns', 'Goods Issue', 'Sales', 'Damage/Loss'],
            'inward' => [
                $inward['grn'],
                $inward['goods_return'] + $inward['return'],
                0, 0, 0,
            ],
            'outward' => [
                0, 0,
                $outward['goods_issue'],
                $outward['sale'],
                $outward['damage'] + $outward['shortage'] + $outward['theft'],
            ],
        ];
    }

    private function loadCustomerChannelDistribution(): void
    {
        $data = Customer::query()
            ->where('is_active', true)
            ->selectRaw('channel_type, COUNT(*) as count')
            ->selectRaw('SUM(credit_used) as total_credit')
            ->groupBy('channel_type')
            ->orderByDesc('count')
            ->get();

        $this->customerChannelDistribution = [
            'labels' => $data->pluck('channel_type')->toArray(),
            'counts' => $data->pluck('count')->toArray(),
            'credit' => $data->pluck('total_credit')->map(fn ($v) => round((float) ($v ?? 0), 2))->toArray(),
        ];
    }
}
