<?php

namespace App\Livewire;

use App\Models\CurrentStock;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\LedgerRegister;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Dashboard: every figure follows the same rules as the module screens, so a
 * user only sees their own world:
 *
 *  - Supplier: a user linked to a supplier sees only that supplier's data
 *    (super admins and the "admin" role see every supplier), like the
 *    GRN / Goods Issue / Settlement lists.
 *  - Own vs all: without "...-view-all" a user sees only the settlements,
 *    goods issues and GRNs they created, like those lists.
 *  - Sections appear only for modules the user can open.
 *
 * Super admins additionally get a company overview (suppliers, users, fleet).
 */
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

    /**
     * What the user is looking at: supplier + own/all per module.
     *
     * @var array{supplier_id: int|null, supplier: string, own_settlements: bool, own_issues: bool, own_grns: bool, is_super_admin: bool}
     */
    public array $scope = [];

    /**
     * Which dashboard sections this user gets.
     *
     * @var array<string, bool>
     */
    public array $sections = [];

    /**
     * Same figures for last month, for the "vs last month" hints.
     *
     * @var array<string, float>
     */
    public array $lastMonth = [];

    /** @var array<int, array<string, mixed>> */
    public array $recentSettlements = [];

    /** @var array<int, array<string, mixed>> */
    public array $lowStock = [];

    /** @var array<int, array<string, mixed>> */
    public array $topCreditCustomers = [];

    /** @var array<string, mixed> */
    public array $salesBySupplier = [];

    /**
     * Outstanding customer credit by supplier (or by salesman for a supplier user).
     *
     * @var array{by?: string, labels?: array<int, string>, values?: array<int, float>}
     */
    public array $creditBreakdown = [];

    /** @var array{labels?: array<int, string>, values?: array<int, float>} */
    public array $creditBySalesman = [];

    /** @var array<int, array{id: int, name: string, invoices: float, payments: float, balance: float, last_entry: string}> */
    public array $supplierLedger = [];

    /**
     * Outstanding credit by days since the customer last paid.
     *
     * @var array{labels?: array<int, string>, values?: array<int, float>}
     */
    public array $creditAging = [];

    /** @var array<int, array<string, mixed>> Customers with no payment for 30+ days (aging drill-down). */
    public array $agingCustomers = [];

    /** @var array<string, mixed> */
    public array $companyOverview = [];

    /**
     * Ready-made links to each module's draft list.
     *
     * @var array<string, string>
     */
    public array $draftLinks = [];

    /** Draft lists open from at least this date (the module lists default to today). */
    public const DRAFTS_FROM = '2026-03-01';

    public function mount(): void
    {
        $user = auth()->user();

        $this->scope = $this->resolveScope($user);
        $this->sections = [
            'sales' => $user->can('sales-settlement-list'),
            'distribution' => $user->can('goods-issue-list'),
            'purchases' => $user->can('goods-receipt-note-list') || $this->canSeeSupplierLedger($user),
            'inventory' => $user->can('inventory-view'),
            'accounting' => $user->can('journal-entry-list') || $user->can('accounting-view'),
            'company' => $this->scope['is_super_admin'] || $this->scope['is_admin'],
            'credit' => $this->canSeeCredit($user),
        ];

        $this->loadKpiCards($user);
        $this->loadPendingItems($user);
        $this->buildDraftLinks();

        if ($this->canSeeSupplierLedger($user)) {
            $this->loadSupplierLedger();
            $this->loadPurchasesVsPayments();
        }

        if ($user->can('view-any-report') || $user->can('accounting-view')) {
            $this->loadMonthlySalesTrend();
            $this->loadRevenueVsCogs();
            $this->loadJournalEntryStatus();
        }

        if ($user->can('sales-settlement-list') || $user->can('view-any-report')) {
            $this->loadMonthlySalesTrend();
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

        if ($user->can('sales-settlement-list')) {
            $this->loadRecentSettlements();
        }

        if ($user->can('inventory-view') || $user->can('view-any-report')) {
            $this->loadTopProductsByStockValue();
            $this->loadGrnVsGoodsIssueTrend();
            $this->loadWarehouseStockDistribution();
            $this->loadStockMovementBreakdown();
        }

        if ($user->can('inventory-view')) {
            $this->loadLowStock();
        }

        if ($this->sections['credit']) {
            $this->loadCredit();
        }

        if ($this->scope['supplier_id'] === null && ($user->can('sales-settlement-list') || $user->can('view-any-report'))) {
            $this->loadSalesBySupplier();
        }

        if ($this->sections['company']) {
            $this->loadCompanyOverview();
        }
    }

    public function render()
    {
        return view('livewire.dashboard');
    }

    /**
     * @return array{supplier_id: int|null, supplier: string, own_settlements: bool, own_issues: bool, own_grns: bool, is_super_admin: bool, is_admin: bool}
     */
    private function resolveScope(User $user): array
    {
        $isSuperAdmin = $user->is_super_admin === 'Yes' || $user->hasRole('super-admin');
        $supplierId = ($isSuperAdmin || $user->hasRole('admin') || ! $user->supplier_id) ? null : (int) $user->supplier_id;

        return [
            'supplier_id' => $supplierId,
            'supplier' => $supplierId ? (Supplier::whereKey($supplierId)->value('supplier_name') ?? 'Your supplier') : 'All suppliers',
            'own_settlements' => ! $user->can('sales-settlement-view-all'),
            'own_issues' => ! $user->can('goods-issue-view-all'),
            'own_grns' => ! $user->can('goods-receipt-note-view-all'),
            'is_super_admin' => $isSuperAdmin,
            'is_admin' => ! $isSuperAdmin && $user->hasRole('admin'),
        ];
    }

    /* ------------------------------------------------------------------
     | Scoped base queries: the same rules as the module lists.
     * ------------------------------------------------------------------ */

    private function settlements(string $table = 'sales_settlements'): Builder
    {
        return $this->scopeSettlements(SalesSettlement::query(), $table);
    }

    private function scopeSettlements(Builder $query, string $table = 'sales_settlements'): Builder
    {
        return $query
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where($table.'.supplier_id', $id))
            ->when($this->scope['own_settlements'], fn ($q) => $q->where($table.'.created_by', auth()->id()));
    }

    private function goodsIssues(): Builder
    {
        return GoodsIssue::query()
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($this->scope['own_issues'], fn ($q) => $q->where('issued_by', auth()->id()));
    }

    private function grns(): Builder
    {
        return GoodsReceiptNote::query()
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($this->scope['own_grns'], fn ($q) => $q->where('received_by', auth()->id()));
    }

    private function supplierPayments(): Builder
    {
        return SupplierPayment::query()
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('supplier_id', $id));
    }

    /** Current stock joined to products, limited to the user's supplier. */
    private function stock(): Builder
    {
        return CurrentStock::query()
            ->join('products', 'current_stock.product_id', '=', 'products.id')
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('products.supplier_id', $id));
    }

    /**
     * @param  User  $user
     */
    private function loadKpiCards($user): void
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $lastStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $lastEnd = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        if ($user->can('sales-settlement-list')) {
            $sums = fn (Carbon $from, Carbon $to) => $this->settlements()
                ->where('status', 'posted')
                ->whereBetween('settlement_date', [$from, $to])
                ->selectRaw('COALESCE(SUM(total_sales_amount), 0) as sales, COALESCE(SUM(cash_sales_amount), 0) as cash, COALESCE(SUM(credit_sales_amount), 0) as credit, COALESCE(SUM(gross_profit), 0) as profit, COALESCE(SUM(expenses_claimed), 0) as expenses, COALESCE(SUM(credit_recoveries), 0) as recoveries, COUNT(*) as trips')
                ->first();

            $now = $sums($startOfMonth, $endOfMonth);
            $last = $sums($lastStart, $lastEnd);

            $this->kpiCards['totalSalesThisMonth'] = (float) $now->sales;
            $this->kpiCards['cashCollectedThisMonth'] = (float) $now->cash;
            $this->kpiCards['creditSalesThisMonth'] = (float) $now->credit;
            $this->kpiCards['grossProfitThisMonth'] = (float) $now->profit;
            $this->kpiCards['expensesThisMonth'] = (float) $now->expenses;
            $this->kpiCards['recoveriesThisMonth'] = (float) $now->recoveries;
            $this->kpiCards['tripsThisMonth'] = (int) $now->trips;
            $this->kpiCards['salesToday'] = (float) $this->settlements()
                ->where('status', 'posted')
                ->whereDate('settlement_date', Carbon::today())
                ->sum('total_sales_amount');

            $this->lastMonth['sales'] = (float) $last->sales;
            $this->lastMonth['profit'] = (float) $last->profit;
            $this->lastMonth['trips'] = (float) $last->trips;

            $this->kpiCards['pendingSettlements'] = $this->settlements()->where('status', 'draft')->count();
        }

        if ($user->can('goods-receipt-note-list')) {
            $this->kpiCards['totalPurchasesThisMonth'] = (float) $this->grns()
                ->where('status', 'posted')
                ->whereBetween('receipt_date', [$startOfMonth, $endOfMonth])
                ->sum('grand_total');
            $this->lastMonth['purchases'] = (float) $this->grns()
                ->where('status', 'posted')
                ->whereBetween('receipt_date', [$lastStart, $lastEnd])
                ->sum('grand_total');
            $this->kpiCards['grnCountThisMonth'] = $this->grns()
                ->where('status', 'posted')
                ->whereBetween('receipt_date', [$startOfMonth, $endOfMonth])
                ->count();
        }

        if ($user->can('inventory-view')) {
            $this->kpiCards['totalInventoryValue'] = (float) $this->stock()->sum('current_stock.total_value');
            $this->kpiCards['productsInStock'] = (clone $this->stock())->where('current_stock.quantity_on_hand', '>', 0)->distinct()->count('current_stock.product_id');
            $this->kpiCards['totalProducts'] = Product::query()
                ->where('is_active', true)
                ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('supplier_id', $id))
                ->count();
        }

        if ($user->can('goods-issue-list')) {
            $issued = fn (Carbon $from, Carbon $to) => $this->goodsIssues()
                ->where('status', 'issued')
                ->whereBetween('issue_date', [$from, $to]);

            $this->kpiCards['goodsIssuedThisMonth'] = (float) $issued($startOfMonth, $endOfMonth)->sum('total_value');
            $this->kpiCards['goodsIssueCountThisMonth'] = $issued($startOfMonth, $endOfMonth)->count();
            $this->lastMonth['issued'] = (float) $issued($lastStart, $lastEnd)->sum('total_value');
            $this->kpiCards['issuedToday'] = $this->goodsIssues()->where('status', 'issued')->whereDate('issue_date', Carbon::today())->count();
        }

        if ($user->can('journal-entry-list')) {
            $this->kpiCards['draftJournalEntries'] = JournalEntry::query()
                ->where('status', 'draft')
                ->count();
        }
    }

    private function loadMonthlySalesTrend(): void
    {
        if ($this->monthlySalesTrend !== []) {
            return;
        }

        $from = Carbon::now()->subMonths(11)->startOfMonth();

        $data = $this->settlements()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', $from)
            ->selectRaw('EXTRACT(YEAR FROM settlement_date) as year, EXTRACT(MONTH FROM settlement_date) as month')
            ->selectRaw('SUM(total_sales_amount) as total_sales')
            ->selectRaw('SUM(gross_profit) as total_profit')
            ->groupByRaw('EXTRACT(YEAR FROM settlement_date), EXTRACT(MONTH FROM settlement_date)')
            ->get()
            ->keyBy(fn ($row) => (int) $row->year.'-'.(int) $row->month);

        $labels = [];
        $sales = [];
        $profits = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $row = $data[$date->year.'-'.$date->month] ?? null;
            $labels[] = $date->format('M Y');
            $sales[] = round((float) ($row->total_sales ?? 0), 2);
            $profits[] = round((float) ($row->total_profit ?? 0), 2);
        }

        $this->monthlySalesTrend = [
            'labels' => $labels,
            'sales' => $sales,
            'profits' => $profits,
        ];
    }

    private function loadSalesByPaymentMethod(): void
    {
        $data = $this->settlements()
            ->where('status', 'posted')
            ->whereBetween('settlement_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->selectRaw('SUM(cash_sales_amount) as cash')
            ->selectRaw('SUM(cheque_sales_amount) as cheque')
            ->selectRaw('SUM(bank_transfer_amount) as bank_transfer')
            ->selectRaw('SUM(credit_sales_amount) as credit')
            ->selectRaw('SUM(bank_slips_amount) as bank_slips')
            ->first();

        // A sale is split into cash + credit + bank transfer (see SalesSettlementController);
        // cheques and bank slips are how cash was banked, so they are not added again.
        $this->salesByPaymentMethod = [
            'labels' => ['Cash', 'Credit', 'Bank Transfer'],
            'values' => [
                round((float) ($data->cash ?? 0), 2),
                round((float) ($data->credit ?? 0), 2),
                round((float) ($data->bank_transfer ?? 0), 2),
            ],
        ];
    }

    private function loadRevenueVsCogs(): void
    {
        $data = $this->settlements()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->selectRaw('EXTRACT(YEAR FROM settlement_date) as year, EXTRACT(MONTH FROM settlement_date) as month')
            ->selectRaw('SUM(total_sales_amount) as revenue')
            ->selectRaw('SUM(total_cogs) as cogs')
            ->selectRaw('SUM(expenses_claimed) as expenses')
            ->groupByRaw('EXTRACT(YEAR FROM settlement_date), EXTRACT(MONTH FROM settlement_date)')
            ->get()
            ->keyBy(fn ($row) => (int) $row->year.'-'.(int) $row->month);

        $labels = [];
        $revenue = [];
        $cogs = [];
        $expenses = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $row = $data[$date->year.'-'.$date->month] ?? null;
            $labels[] = $date->format('M Y');
            $revenue[] = round((float) ($row->revenue ?? 0), 2);
            $cogs[] = round((float) ($row->cogs ?? 0), 2);
            $expenses[] = round((float) ($row->expenses ?? 0), 2);
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
        $data = $this->stock()
            ->where('current_stock.quantity_on_hand', '>', 0)
            ->select('products.product_name')
            ->selectRaw('SUM(current_stock.total_value) as total_value')
            ->groupBy('products.product_name')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        $this->topProductsByStockValue = [
            'labels' => $data->pluck('product_name')->toArray(),
            'values' => $data->pluck('total_value')->map(fn ($v) => round((float) $v, 2))->toArray(),
        ];
    }

    /**
     * Supplier invoices (what the supplier bills us) and online payments per
     * month, from the Supplier Ledger Register -- the same figures as
     * /reports/ledger-register.
     */
    private function loadPurchasesVsPayments(): void
    {
        $rows = $this->ledger()
            ->where('transaction_date', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->selectRaw('EXTRACT(YEAR FROM transaction_date) as year, EXTRACT(MONTH FROM transaction_date) as month')
            ->selectRaw('SUM(invoice_amount) as invoices, SUM(online_amount) as payments')
            ->selectRaw('SUM('.self::LEDGER_NET.') as net')
            ->groupByRaw('EXTRACT(YEAR FROM transaction_date), EXTRACT(MONTH FROM transaction_date)')
            ->get()
            ->keyBy(fn ($row) => (int) $row->year.'-'.(int) $row->month);

        // Running ledger balance at each month end (same formula as the report).
        $running = (float) $this->ledger()
            ->where('transaction_date', '<', Carbon::now()->subMonths(5)->startOfMonth())
            ->selectRaw('COALESCE(SUM('.self::LEDGER_NET.'), 0) as net')
            ->value('net');

        $labels = [];
        $purchaseValues = [];
        $paymentValues = [];
        $balanceValues = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $row = $rows[$date->year.'-'.$date->month] ?? null;
            $running += (float) ($row->net ?? 0);
            $labels[] = $date->format('M Y');
            $purchaseValues[] = round((float) ($row->invoices ?? 0), 2);
            $paymentValues[] = round((float) ($row->payments ?? 0), 2);
            $balanceValues[] = round($running, 2);
        }

        $this->purchasesVsPayments = [
            'labels' => $labels,
            'purchases' => $purchaseValues,
            'payments' => $paymentValues,
            'balance' => $balanceValues,
        ];

        $this->loadSupplierLedgerTable();
    }

    /** Ledger balance formula used by the Supplier Ledger Register report. */
    public const LEDGER_NET = 'opening_balance + online_amount - invoice_amount - expenses_amount + za_point_five_percent_amount + claim_adjust_amount';

    /**
     * One row per supplier: this month's invoices / payments and the running balance.
     */
    private function loadSupplierLedgerTable(): void
    {
        $from = Carbon::now()->startOfMonth()->toDateString();
        $to = Carbon::now()->endOfMonth()->toDateString();

        $this->supplierLedger = $this->ledger()
            ->join('suppliers', 'supplier_ledger_registers.supplier_id', '=', 'suppliers.id')
            ->select('suppliers.id', 'suppliers.supplier_name')
            ->selectRaw('COALESCE(SUM(CASE WHEN transaction_date BETWEEN ? AND ? THEN invoice_amount ELSE 0 END), 0) as invoices', [$from, $to])
            ->selectRaw('COALESCE(SUM(CASE WHEN transaction_date BETWEEN ? AND ? THEN online_amount ELSE 0 END), 0) as payments', [$from, $to])
            ->selectRaw('COALESCE(SUM('.self::LEDGER_NET.'), 0) as balance')
            ->selectRaw('MAX(transaction_date) as last_entry')
            ->groupBy('suppliers.id', 'suppliers.supplier_name')
            ->orderByRaw('ABS(COALESCE(SUM('.self::LEDGER_NET.'), 0)) DESC')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->supplier_name,
                'invoices' => round((float) $r->invoices, 2),
                'payments' => round((float) $r->payments, 2),
                'balance' => round((float) $r->balance, 2),
                'last_entry' => $r->last_entry ? Carbon::parse($r->last_entry)->format('d M Y') : '—',
            ])
            ->all();
    }

    private function canSeeSupplierLedger(User $user): bool
    {
        return $user->can('supplier-payment-list') || $user->can('report-audit-ledger-register');
    }

    private function ledger(): Builder
    {
        return LedgerRegister::query()
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('supplier_id', $id));
    }

    /**
     * This month's supplier invoices / online payments and the running
     * balance, same formula as the Supplier Ledger Register.
     */
    private function loadSupplierLedger(): void
    {
        $month = fn (Carbon $from, Carbon $to) => $this->ledger()
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(invoice_amount), 0) as invoices, COALESCE(SUM(online_amount), 0) as payments')
            ->first();

        $now = $month(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
        $last = $month(Carbon::now()->subMonthNoOverflow()->startOfMonth(), Carbon::now()->subMonthNoOverflow()->endOfMonth());

        $balance = (float) $this->ledger()
            ->selectRaw('COALESCE(SUM(opening_balance + online_amount - invoice_amount - expenses_amount + za_point_five_percent_amount + claim_adjust_amount), 0) as balance')
            ->value('balance');

        $this->kpiCards['supplierInvoicesThisMonth'] = (float) $now->invoices;
        $this->kpiCards['paymentsThisMonth'] = (float) $now->payments;
        // Positive = we owe suppliers; negative = advance lying with suppliers.
        $this->kpiCards['outstandingPayables'] = round(-$balance, 2);
        $this->lastMonth['invoices'] = (float) $last->invoices;
        $this->lastMonth['payments'] = (float) $last->payments;
    }

    /**
     * Links for the draft counts. The module lists default to today's date,
     * so the links carry a date range wide enough to include every draft.
     */
    private function buildDraftLinks(): void
    {
        $today = Carbon::today()->toDateString();
        $from = function (Builder $drafts, string $column): string {
            $oldest = $drafts->min($column);
            $floor = self::DRAFTS_FROM;

            return $oldest && Carbon::parse($oldest)->toDateString() < $floor ? Carbon::parse($oldest)->toDateString() : $floor;
        };

        if (isset($this->pendingItems['draftSettlements'])) {
            $this->draftLinks['draftSettlements'] = route('sales-settlements.index', ['filter' => [
                'status' => 'draft',
                'settlement_date_from' => $from($this->settlements()->where('status', 'draft'), 'settlement_date'),
                'settlement_date_to' => $today,
            ], 'per_page' => 50]);
        }
        if (isset($this->pendingItems['draftGoodsIssues'])) {
            $this->draftLinks['draftGoodsIssues'] = route('goods-issues.index', ['filter' => [
                'status' => 'draft',
                'issue_date_from' => $from($this->goodsIssues()->where('status', 'draft'), 'issue_date'),
                'issue_date_to' => $today,
            ]]);
        }
        if (isset($this->pendingItems['draftGrns'])) {
            $this->draftLinks['draftGrns'] = route('goods-receipt-notes.index', ['filter' => ['status' => 'draft']]);
        }
        if (isset($this->pendingItems['draftPayments'])) {
            $this->draftLinks['draftPayments'] = route('supplier-payments.index', ['filter' => ['status' => 'draft']]);
        }
        if (isset($this->pendingItems['draftJournalEntries'])) {
            $this->draftLinks['draftJournalEntries'] = route('journal-entries.index', ['filter' => ['status' => 'draft']]);
        }
    }

    private function loadSettlementStatusDistribution(): void
    {
        $data = $this->settlements()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->settlementStatusDistribution = [
            'labels' => array_map(fn ($s) => ucfirst($s), array_keys($data)),
            'values' => array_map('intval', array_values($data)),
        ];
    }

    private function loadDailySalesTrend(): void
    {
        $data = $this->settlements()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', Carbon::now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(settlement_date) as date')
            ->selectRaw('SUM(total_sales_amount) as total_sales')
            ->groupByRaw('DATE(settlement_date)')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->format('Y-m-d'));

        $labels = [];
        $sales = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('d M');
            $sales[] = round((float) ($data[$date->format('Y-m-d')]->total_sales ?? 0), 2);
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
            'values' => array_map('intval', array_values($data)),
        ];
    }

    private function loadTopProductsBySales(): void
    {
        $query = SalesSettlementItem::query()
            ->join('sales_settlements', 'sales_settlement_items.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('products', 'sales_settlement_items.product_id', '=', 'products.id')
            ->where('sales_settlements.status', 'posted')
            ->whereBetween('sales_settlements.settlement_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->whereNull('sales_settlements.deleted_at');

        $data = $this->scopeSettlements($query)
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
        $from = Carbon::now()->subMonths(5)->startOfMonth();

        $grns = GoodsReceiptNote::query()
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('supplier_id', $id))
            ->where('status', 'posted')
            ->where('receipt_date', '>=', $from)
            ->selectRaw('EXTRACT(YEAR FROM receipt_date) as year, EXTRACT(MONTH FROM receipt_date) as month')
            ->selectRaw('SUM(grand_total) as total')
            ->groupByRaw('EXTRACT(YEAR FROM receipt_date), EXTRACT(MONTH FROM receipt_date)')
            ->get()
            ->keyBy(fn ($row) => (int) $row->year.'-'.(int) $row->month);

        $issues = GoodsIssue::query()
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('supplier_id', $id))
            ->where('status', 'issued')
            ->where('issue_date', '>=', $from)
            ->selectRaw('EXTRACT(YEAR FROM issue_date) as year, EXTRACT(MONTH FROM issue_date) as month')
            ->selectRaw('SUM(total_value) as total')
            ->groupByRaw('EXTRACT(YEAR FROM issue_date), EXTRACT(MONTH FROM issue_date)')
            ->get()
            ->keyBy(fn ($row) => (int) $row->year.'-'.(int) $row->month);

        $labels = [];
        $grnValues = [];
        $issueValues = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
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
        $query = SalesSettlement::query()
            ->join('employees', 'sales_settlements.employee_id', '=', 'employees.id')
            ->where('sales_settlements.status', 'posted')
            ->whereBetween('sales_settlements.settlement_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);

        $data = $this->scopeSettlements($query)
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
            'trips' => $data->pluck('trips')->map(fn ($v) => (int) $v)->toArray(),
        ];
    }

    /**
     * @param  User  $user
     */
    private function loadPendingItems($user): void
    {
        if ($user->can('sales-settlement-list')) {
            $this->pendingItems['draftSettlements'] = $this->settlements()->where('status', 'draft')->count();
        }

        if ($user->can('goods-receipt-note-list')) {
            $this->pendingItems['draftGrns'] = $this->grns()->where('status', 'draft')->count();
        }

        if ($user->can('goods-issue-list')) {
            $this->pendingItems['draftGoodsIssues'] = $this->goodsIssues()->where('status', 'draft')->count();
        }

        if ($user->can('journal-entry-list')) {
            $this->pendingItems['draftJournalEntries'] = JournalEntry::query()->where('status', 'draft')->count();
        }

        if ($user->can('supplier-payment-list')) {
            $this->pendingItems['draftPayments'] = $this->supplierPayments()->where('status', 'draft')->count();
        }
    }

    private function loadProfitMarginGauge(): void
    {
        $data = $this->settlements()
            ->where('status', 'posted')
            ->whereBetween('settlement_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->selectRaw('SUM(total_sales_amount) as revenue, SUM(gross_profit) as profit')
            ->first();

        $revenue = (float) ($data->revenue ?? 0);
        $profit = (float) ($data->profit ?? 0);

        $this->profitMarginGauge = [
            'margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0,
            'revenue' => round($revenue, 2),
            'profit' => round($profit, 2),
        ];
    }

    private function loadCashVsCreditTrend(): void
    {
        $data = $this->settlements()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->selectRaw('EXTRACT(YEAR FROM settlement_date) as year, EXTRACT(MONTH FROM settlement_date) as month')
            ->selectRaw('SUM(cash_sales_amount) as cash')
            ->selectRaw('SUM(credit_sales_amount) as credit')
            ->selectRaw('SUM(cheque_sales_amount) as cheque')
            ->selectRaw('SUM(bank_transfer_amount) as bank_transfer')
            ->groupByRaw('EXTRACT(YEAR FROM settlement_date), EXTRACT(MONTH FROM settlement_date)')
            ->get()
            ->keyBy(fn ($row) => (int) $row->year.'-'.(int) $row->month);

        $labels = [];
        $cash = [];
        $credit = [];
        $cheque = [];
        $bank = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $row = $data[$date->year.'-'.$date->month] ?? null;
            $labels[] = $date->format('M Y');
            $cash[] = round((float) ($row->cash ?? 0), 2);
            $credit[] = round((float) ($row->credit ?? 0), 2);
            $cheque[] = round((float) ($row->cheque ?? 0), 2);
            $bank[] = round((float) ($row->bank_transfer ?? 0), 2);
        }

        $this->cashVsCreditTrend = [
            'labels' => $labels,
            'cash' => $cash,
            'credit' => $credit,
            'cheque' => $cheque,
            'bank' => $bank,
        ];
    }

    private function loadSalesByDayOfWeek(): void
    {
        $data = $this->settlements()
            ->where('status', 'posted')
            ->where('settlement_date', '>=', Carbon::now()->subDays(90)->startOfDay())
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

        $this->salesByDayOfWeek = [
            'labels' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'values' => array_map(fn ($d) => round($d['sales'], 2), $byDow),
            'trips' => array_map(fn ($d) => $d['trips'], $byDow),
        ];
    }

    private function loadWarehouseStockDistribution(): void
    {
        $data = $this->stock()
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

        $this->warehouseStockDistribution = [
            'labels' => $data->pluck('warehouse_name')->toArray(),
            'values' => $data->pluck('total_value')->map(fn ($v) => round((float) $v, 2))->toArray(),
            'treemap' => $data->map(fn ($row) => ['x' => $row->warehouse_name, 'y' => round((float) $row->total_value, 2)])->toArray(),
            'quantities' => $data->pluck('total_qty')->map(fn ($v) => round((float) $v, 0))->toArray(),
            'products' => $data->pluck('product_count')->map(fn ($v) => (int) $v)->toArray(),
        ];
    }

    private function loadStockMovementBreakdown(): void
    {
        $data = StockMovement::query()
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('products.supplier_id', $id))
            ->where('stock_movements.movement_date', '>=', Carbon::now()->subDays(30)->startOfDay())
            ->selectRaw('stock_movements.movement_type, SUM(stock_movements.total_value) as total_value')
            ->groupBy('stock_movements.movement_type')
            ->get();

        $inward = ['grn' => 0, 'goods_return' => 0, 'return' => 0];
        $outward = ['goods_issue' => 0, 'sale' => 0, 'damage' => 0, 'shortage' => 0, 'theft' => 0];

        foreach ($data as $row) {
            $value = abs(round((float) $row->total_value, 2));
            if (array_key_exists($row->movement_type, $inward)) {
                $inward[$row->movement_type] = $value;
            } elseif (array_key_exists($row->movement_type, $outward)) {
                $outward[$row->movement_type] = $value;
            }
        }

        $this->stockMovementBreakdown = [
            'labels' => ['GRN', 'Returns', 'Goods Issue', 'Sales', 'Damage/Loss'],
            'inward' => [$inward['grn'], $inward['goods_return'] + $inward['return'], 0, 0, 0],
            'outward' => [0, 0, $outward['goods_issue'], $outward['sale'], $outward['damage'] + $outward['shortage'] + $outward['theft']],
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
            'labels' => $data->pluck('channel_type')->map(fn ($v) => $v ?: 'Not set')->toArray(),
            'counts' => $data->pluck('count')->map(fn ($v) => (int) $v)->toArray(),
            'credit' => $data->pluck('total_credit')->map(fn ($v) => round((float) ($v ?? 0), 2))->toArray(),
        ];
    }

    private function loadRecentSettlements(): void
    {
        $this->recentSettlements = $this->settlements()
            ->with(['employee:id,name', 'supplier:id,supplier_name'])
            ->latest('settlement_date')
            ->latest('id')
            ->limit(6)
            ->get(['id', 'settlement_number', 'settlement_date', 'employee_id', 'supplier_id', 'status', 'total_sales_amount'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'number' => $s->settlement_number,
                'date' => $s->settlement_date ? Carbon::parse($s->settlement_date)->format('d M Y') : '—',
                'salesman' => $s->employee?->name ?? '—',
                'supplier' => $s->supplier?->supplier_name,
                'status' => (string) $s->status,
                'amount' => round((float) $s->total_sales_amount, 2),
            ])
            ->all();
    }

    private function loadLowStock(): void
    {
        $this->lowStock = Product::query()
            ->where('products.is_active', true)
            ->where('products.reorder_level', '>', 0)
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('products.supplier_id', $id))
            ->leftJoin('current_stock', 'current_stock.product_id', '=', 'products.id')
            ->select('products.id', 'products.product_name', 'products.product_code', 'products.reorder_level')
            ->selectRaw('COALESCE(SUM(current_stock.quantity_on_hand), 0) as on_hand')
            ->groupBy('products.id', 'products.product_name', 'products.product_code', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(current_stock.quantity_on_hand), 0) <= products.reorder_level')
            ->orderByRaw('COALESCE(SUM(current_stock.quantity_on_hand), 0) / products.reorder_level')
            ->limit(8)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->product_name,
                'code' => $p->product_code,
                'on_hand' => round((float) $p->on_hand, 2),
                'reorder' => round((float) $p->reorder_level, 2),
            ])
            ->all();
    }

    /**
     * Market credit = what customers still owe, from the customer-employee
     * account ledger (debit - credit), same source as the Creditors Ledger report.
     */
    private function canSeeCredit(User $user): bool
    {
        return $user->can('report-audit-creditors-ledger') || $user->can('customer-list');
    }

    private function customerLedger(): QueryBuilder
    {
        return DB::table('customer_employee_account_transactions as t')
            ->join('customer_employee_accounts as a', 't.customer_employee_account_id', '=', 'a.id')
            ->join('employees as e', 'a.employee_id', '=', 'e.id')
            ->whereNull('t.deleted_at')
            ->whereNull('a.deleted_at')
            ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('e.supplier_id', $id));
    }

    private function loadCredit(): void
    {
        $balance = 'COALESCE(SUM(t.debit), 0) - COALESCE(SUM(t.credit), 0)';

        $month = $this->customerLedger()
            ->whereBetween('t.transaction_date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])
            ->selectRaw("COALESCE(SUM(CASE WHEN t.transaction_type <> 'opening_balance' THEN t.debit ELSE 0 END), 0) as given, COALESCE(SUM(t.credit), 0) as recovered")
            ->first();
        $this->kpiCards['creditGivenThisMonth'] = round((float) ($month->given ?? 0), 2);
        $this->kpiCards['creditRecoveredThisMonth'] = round((float) ($month->recovered ?? 0), 2);

        // One row per customer-salesman account, straight from the customer account ledger.
        $accounts = $this->customerLedger()
            ->join('customers as c', 'a.customer_id', '=', 'c.id')
            ->leftJoin('suppliers as s', 'e.supplier_id', '=', 's.id')
            ->select('a.customer_id', 'c.customer_code', 'c.customer_name', 'c.city', 'e.name as salesman', 's.supplier_name as supplier')
            ->selectRaw("{$balance} as balance")
            ->selectRaw('MAX(CASE WHEN t.credit > 0 THEN t.transaction_date END) as last_recovery')
            ->selectRaw('MAX(CASE WHEN t.debit > 0 THEN t.transaction_date END) as last_credit_sale')
            ->groupBy('a.id', 'a.customer_id', 'c.customer_code', 'c.customer_name', 'c.city', 'e.name', 's.supplier_name')
            ->havingRaw("{$balance} <> 0")
            ->get();

        $owing = $accounts->filter(fn ($r) => (float) $r->balance > 0);
        $this->kpiCards['marketCredit'] = round((float) $accounts->sum('balance'), 2);

        $today = Carbon::today();
        $top = function ($groupKey, int $limit) use ($owing): array {
            $rows = $owing->groupBy($groupKey)
                ->map(fn ($g) => round((float) $g->sum('balance'), 2))
                ->sortDesc()
                ->take($limit);

            return ['labels' => $rows->keys()->map(fn ($k) => $k ?: 'Not set')->values()->all(), 'values' => $rows->values()->all()];
        };
        $this->creditBreakdown = ['by' => 'supplier'] + $top('supplier', 10);
        $this->creditBySalesman = $top('salesman', 10);

        // One row per customer (all their salesman accounts together).
        $customers = $accounts->groupBy('customer_id')->map(function ($rows) use ($today) {
            $lastPaid = $rows->pluck('last_recovery')->filter()->max();
            $since = $lastPaid ?? $rows->pluck('last_credit_sale')->filter()->max();
            $days = $since ? (int) abs(Carbon::parse($since)->diffInDays($today)) : 999;

            return [
                'days' => $days,
                // Aging bucket: days since the customer last paid (or since the credit sale if never paid).
                'bucket' => match (true) {
                    $days <= 30 => 0,
                    $days <= 60 => 1,
                    $days <= 90 => 2,
                    default => 3,
                },
                'name' => $rows->first()->customer_name,
                'code' => $rows->first()->customer_code,
                'city' => $rows->first()->city,
                'used' => round((float) $rows->sum('balance'), 2),
                'suppliers' => $rows->pluck('supplier')->filter()->unique()->values()->implode(', '),
                'salesmen' => $rows->pluck('salesman')->filter()->unique()->values()->implode(', '),
                'last_paid_days' => $lastPaid ? (int) abs(Carbon::parse($lastPaid)->diffInDays($today)) : null,
            ];
        })->filter(fn ($c) => $c['used'] > 0);

        $this->creditAging = [
            'labels' => ['0-30 days', '31-60 days', '61-90 days', 'Over 90 days'],
            'values' => array_map(fn ($b) => round((float) $customers->where('bucket', $b)->sum('used'), 2), [0, 1, 2, 3]),
            'counts' => array_map(fn ($b) => $customers->where('bucket', $b)->count(), [0, 1, 2, 3]),
        ];
        $this->kpiCards['creditOverdue'] = round($this->creditAging['values'][2] + $this->creditAging['values'][3], 2);
        $this->kpiCards['creditOverdueCustomers'] = $this->creditAging['counts'][2] + $this->creditAging['counts'][3];

        $this->kpiCards['creditCustomers'] = $customers->count();
        $this->topCreditCustomers = $customers->sortByDesc('used')->take(8)->values()->all();
        // Everyone past 30 days, biggest first, for the aging drill-down list.
        $this->agingCustomers = $customers->where('bucket', '>', 0)->sortByDesc('used')->take(300)->values()->all();
    }

    private function loadSalesBySupplier(): void
    {
        $data = SalesSettlement::query()
            ->join('suppliers', 'sales_settlements.supplier_id', '=', 'suppliers.id')
            ->where('sales_settlements.status', 'posted')
            ->whereBetween('sales_settlements.settlement_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->when($this->scope['own_settlements'], fn ($q) => $q->where('sales_settlements.created_by', auth()->id()))
            ->select('suppliers.supplier_name')
            ->selectRaw('SUM(sales_settlements.total_sales_amount) as sales, SUM(sales_settlements.gross_profit) as profit')
            ->groupBy('suppliers.supplier_name')
            ->orderByDesc('sales')
            ->limit(10)
            ->get();

        $this->salesBySupplier = [
            'labels' => $data->pluck('supplier_name')->toArray(),
            'sales' => $data->pluck('sales')->map(fn ($v) => round((float) $v, 2))->toArray(),
            'profit' => $data->pluck('profit')->map(fn ($v) => round((float) $v, 2))->toArray(),
        ];
    }

    private function loadCompanyOverview(): void
    {
        $this->companyOverview = [
            'users_active' => User::where('is_active', 'Yes')->count(),
            'users_total' => User::count(),
            'users_no_access' => User::where('is_active', 'Yes')->where('is_super_admin', '!=', 'Yes')->doesntHave('roles')->doesntHave('permissions')->count(),
            'suppliers' => Supplier::where('disabled', false)->count(),
            'vehicles' => Vehicle::where('is_active', true)->count(),
            'salesmen' => Employee::where('is_active', true)->count(),
            'customers' => Customer::where('is_active', true)->count(),
            'drafts_total' => SalesSettlement::where('status', 'draft')->count()
                + GoodsIssue::where('status', 'draft')->count()
                + GoodsReceiptNote::where('status', 'draft')->count()
                + SupplierPayment::where('status', 'draft')->count()
                + JournalEntry::where('status', 'draft')->count(),
        ];
    }
}
