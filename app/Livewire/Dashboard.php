<?php

namespace App\Livewire;

use App\Enums\PageLayout;
use App\Models\AppSetting;
use App\Models\CurrentStock;
use App\Models\GoodsIssue;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\LedgerRegister;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementItem;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CustomerCreditAging;
use App\Services\InvestmentSummaryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
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
 * Sales, credit-given/recovered, top lists and ledger figures follow the chosen
 * period (?from=&to=, default: this month so far) and are compared with the
 * period before it; so does the day-by-day sales chart (by week for a long period). The monthly
 * trend and the weekday pattern use fixed windows. The result is cached for a couple
 * of minutes per user, supplier and period; ?refresh=1 recalculates it.
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
    public array $topProductsByStockValue = [];

    /** @var array<string, mixed> */
    public array $dailySalesTrend = [];

    /** @var array<string, mixed> */
    public array $topProductsBySales = [];

    /** @var array<string, mixed> */
    public array $topSalespersonBySales = [];

    /** @var array<string, mixed> */
    public array $pendingItems = [];

    /**
     * Sales per weekday over the last 90 days. Only the insights use it, so it stays out of the
     * Livewire snapshot.
     *
     * @var array{labels?: array<int, string>, values?: array<int, float>, trips?: array<int, int>}
     */
    private array $salesByDayOfWeek = [];

    /**
     * The chosen period and the one it is compared with.
     *
     * @var array{from: string, to: string, prev_from: string, prev_to: string, days: int, label: string, prev_label: string, preset: string|null, mtd: bool, presets: array<int, array{name: string, from: string, to: string}>}
     */
    public array $period = [];

    /**
     * What the business has tied up (same figures as the Investment Summary report).
     *
     * @var array{as_of?: string, parts?: array<string, float>, before?: array<string, float>, total?: float, change?: float}
     */
    public array $investment = [];

    /**
     * Plain-language findings worked out from the figures (run rate, outliers, concentration, cover).
     *
     * @var array<int, array{tone: string, value: string, title: string, text: string}>
     */
    public array $insights = [];

    public string $refreshedAt = '';

    /** Seconds the dashboard figures are kept before they are worked out again. */
    public const CACHE_SECONDS = 120;

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

    /**
     * The customers who owe the most, all of their salesman accounts added together.
     *
     * @var array<int, array{id: int, name: string, code: string|null, city: string|null, label: string, used: float, accounts: int, suppliers: string, salesmen: string, last_paid_days: int|null}>
     */
    public array $topCreditCustomers = [];

    /** @var array<string, mixed> */
    public array $salesBySupplier = [];

    /**
     * Outstanding credit by the age of each unpaid credit sale (see CustomerCreditAging).
     *
     * @var array{labels?: array<int, string>, values?: array<int, float>, counts?: array<int, int>}
     */
    public array $creditAging = [];

    /** @var array<int, array<string, mixed>> Accounts with credit older than 30 days (aging drill-down). */
    public array $agingCustomers = [];

    /**
     * Ready-made links to each module's draft list.
     *
     * @var array<string, string>
     */
    public array $draftLinks = [];

    /**
     * Suppliers a super admin / admin can narrow the dashboard to.
     *
     * @var array<int, string>
     */
    public array $supplierOptions = [];

    /** Draft lists open from at least this date (the module lists default to today). */
    public const DRAFTS_FROM = '2026-03-01';

    /** Everything the view needs that is worked out by the loaders (and so can be cached). */
    private const STATE = [
        'kpiCards', 'monthlySalesTrend', 'salesByPaymentMethod', 'topProductsByStockValue', 'dailySalesTrend',
        'topProductsBySales', 'topSalespersonBySales', 'pendingItems', 'lastMonth', 'topCreditCustomers',
        'salesBySupplier', 'creditAging', 'agingCustomers', 'draftLinks', 'investment', 'insights', 'refreshedAt',
    ];

    public function mount(): void
    {
        $user = auth()->user();

        $this->scope = $this->resolveScope($user);
        $this->resolvePeriod();
        $this->sections = [
            'sales' => $user->can('sales-settlement-list'),
            'distribution' => $user->can('goods-issue-list'),
            'purchases' => $user->can('goods-receipt-note-list') || $this->canSeeSupplierLedger($user),
            'inventory' => $user->can('inventory-view'),
            'accounting' => $user->can('journal-entry-list') || $user->can('accounting-view'),
            'company' => $this->scope['is_super_admin'] || $this->scope['is_admin'],
            'credit' => $this->canSeeCredit($user),
            'investment' => $this->scope['is_super_admin'] || $user->can('report-audit-investment-summary'),
        ];

        $key = $this->cacheKey($user);

        if (request()->boolean('refresh')) {
            Cache::forget($key);
        }

        $state = app()->runningUnitTests()
            ? $this->workOutState($user)
            : Cache::remember($key, self::CACHE_SECONDS, fn () => $this->workOutState($user));

        $this->fill($state);
    }

    private function cacheKey(User $user): string
    {
        return implode(':', [
            'dashboard', 'v2', $user->id, $this->scope['supplier_id'] ?? 'all', $this->period['from'], $this->period['to'],
            (int) $this->scope['own_settlements'], (int) $this->scope['own_issues'], (int) $this->scope['own_grns'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function workOutState(User $user): array
    {
        $this->loadKpiCards($user);
        $this->loadPendingItems($user);
        $this->buildDraftLinks();

        if ($this->canSeeSupplierLedger($user)) {
            $this->loadSupplierLedger();
        }

        if ($user->can('view-any-report') || $user->can('accounting-view')) {
            $this->loadMonthlySalesTrend();
        }

        if ($user->can('sales-settlement-list') || $user->can('view-any-report')) {
            $this->loadMonthlySalesTrend();
            $this->loadSalesByPaymentMethod();
            $this->loadDailySalesTrend();
            $this->loadTopProductsBySales();
            $this->loadTopSalespersonBySales();
            $this->loadSalesByDayOfWeek();
        }

        if ($user->can('inventory-view') || $user->can('view-any-report')) {
            $this->loadTopProductsByStockValue();
        }

        if ($this->sections['credit']) {
            $this->loadCredit();
        }

        if ($this->scope['supplier_id'] === null && ($user->can('sales-settlement-list') || $user->can('view-any-report'))) {
            $this->loadSalesBySupplier();
        }

        if ($this->sections['investment']) {
            $this->loadInvestment();
        }

        $this->loadInsights();
        $this->refreshedAt = now()->format('d M Y, h:i A');

        $state = [];
        foreach (self::STATE as $property) {
            $state[$property] = $this->{$property};
        }

        return $state;
    }

    /**
     * The chosen dates (?from=&to=, default: this month so far, never in the future) and
     * the period they are compared with: the same days of the previous month for a whole
     * month-to-date, otherwise the same number of days straight before.
     */
    private function resolvePeriod(): void
    {
        $today = Carbon::today();
        $parse = function (mixed $value): ?Carbon {
            if (! is_string($value) || $value === '') {
                return null;
            }

            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };

        $from = $parse(request()->query('from'));
        $to = $parse(request()->query('to'));

        if ($from === null && $to === null) {
            $from = $today->copy()->startOfMonth();
            $to = $today->copy();
        }

        $from ??= $to->copy();
        $to ??= $from->copy();

        // Swap first, then keep both dates out of the future, so a period that lies wholly
        // ahead (e.g. next January) becomes today rather than "today to next January".
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }
        if ($to->gt($today)) {
            $to = $today->copy();
        }
        if ($from->gt($today)) {
            $from = $today->copy();
        }
        if ($from->diffInDays($to) > 730) {
            $from = $to->copy()->subDays(730);
        }

        $days = (int) $from->diffInDays($to) + 1;
        $monthToDate = $from->isSameDay($from->copy()->startOfMonth()) && $from->isSameMonth($to);

        if ($monthToDate) {
            $prevFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
            $prevTo = $prevFrom->copy()->addDays($to->day - 1);
            if ($prevTo->gt($prevFrom->copy()->endOfMonth())) {
                $prevTo = $prevFrom->copy()->endOfMonth()->startOfDay();
            }
        } else {
            $prevTo = $from->copy()->subDay();
            $prevFrom = $prevTo->copy()->subDays($days - 1);
        }

        $range = fn (Carbon $a, Carbon $b): string => $a->isSameDay($b)
            ? $a->format('d M Y')
            : ($a->isSameYear($b) ? $a->format('d M') : $a->format('d M Y')).' – '.$b->format('d M Y');

        $presets = [
            'Today' => [$today->copy(), $today->copy()],
            'Yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'Last 7 days' => [$today->copy()->subDays(6), $today->copy()],
            'Last 30 days' => [$today->copy()->subDays(29), $today->copy()],
            'This month' => [$today->copy()->startOfMonth(), $today->copy()],
            'Last month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'This year' => [$today->copy()->startOfYear(), $today->copy()],
        ];
        $active = null;
        foreach ($presets as $name => [$a, $b]) {
            if ($a->isSameDay($from) && $b->isSameDay($to)) {
                $active = $name;
                break;
            }
        }

        $this->period = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'prev_from' => $prevFrom->toDateString(),
            'prev_to' => $prevTo->toDateString(),
            'days' => $days,
            'label' => $active === 'Today' ? 'Today, '.$to->format('d M Y') : ($active ?? $range($from, $to)).($active && $active !== 'Yesterday' ? ' · '.$range($from, $to) : ''),
            'prev_label' => $range($prevFrom, $prevTo),
            'preset' => $active,
            'mtd' => $monthToDate,
            'presets' => collect($presets)->map(fn ($r, $name) => ['name' => $name, 'from' => $r[0]->toDateString(), 'to' => $r[1]->toDateString()])->values()->all(),
        ];
    }

    private function periodFrom(): Carbon
    {
        return Carbon::parse($this->period['from'])->startOfDay();
    }

    private function periodTo(): Carbon
    {
        return Carbon::parse($this->period['to'])->endOfDay();
    }

    private function previousFrom(): Carbon
    {
        return Carbon::parse($this->period['prev_from'])->startOfDay();
    }

    private function previousTo(): Carbon
    {
        return Carbon::parse($this->period['prev_to'])->endOfDay();
    }

    /**
     * Classic is the original board; Option A ("Focus") is the calmer redesign. Both read the
     * same component data; a super admin picks one on Settings → Appearance.
     */
    public function render(): View
    {
        return view(AppSetting::layoutFor('dashboard') === PageLayout::OptionA ? 'livewire.dashboard-focus' : 'livewire.dashboard');
    }

    /**
     * @return array{supplier_id: int|null, supplier: string, own_settlements: bool, own_issues: bool, own_grns: bool, is_super_admin: bool, is_admin: bool, can_pick_supplier: bool}
     */
    private function resolveScope(User $user): array
    {
        $isSuperAdmin = $user->is_super_admin === 'Yes' || $user->hasRole('super-admin');
        $supplierId = ($isSuperAdmin || $user->hasRole('admin') || ! $user->supplier_id) ? null : (int) $user->supplier_id;
        $canPickSupplier = $supplierId === null;

        // Users who see every supplier may narrow the whole dashboard to one (?supplier=ID).
        if ($canPickSupplier) {
            $this->supplierOptions = Supplier::where('disabled', false)->orderBy('supplier_name')->pluck('supplier_name', 'id')->all();
            $picked = (int) request()->query('supplier', 0);
            if ($picked && isset($this->supplierOptions[$picked])) {
                $supplierId = $picked;
            }
        }

        return [
            'supplier_id' => $supplierId,
            'supplier' => $supplierId ? (Supplier::whereKey($supplierId)->value('supplier_name') ?? 'Your supplier') : 'All suppliers',
            'own_settlements' => ! $user->can('sales-settlement-view-all'),
            'own_issues' => ! $user->can('goods-issue-view-all'),
            'own_grns' => ! $user->can('goods-receipt-note-view-all'),
            'is_super_admin' => $isSuperAdmin,
            'is_admin' => ! $isSuperAdmin && $user->hasRole('admin'),
            'can_pick_supplier' => $canPickSupplier,
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
        $startOfMonth = $this->periodFrom();
        $endOfMonth = $this->periodTo();
        $lastStart = $this->previousFrom();
        $lastEnd = $this->previousTo();

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
            $this->lastMonth['cash'] = (float) $last->cash;
            $this->lastMonth['credit'] = (float) $last->credit;
            $this->lastMonth['expenses'] = (float) $last->expenses;
            $this->lastMonth['recoveries'] = (float) $last->recoveries;

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

        if ($this->canSeeDraftJournalEntries($user)) {
            $this->kpiCards['draftJournalEntries'] = JournalEntry::query()
                ->where('status', 'draft')
                ->count();
        }
    }

    /**
     * Journal entries carry no supplier, so their draft count is company-wide: it is shown only
     * on a dashboard that is not narrowed to one supplier.
     */
    private function canSeeDraftJournalEntries(User $user): bool
    {
        return $user->can('journal-entry-list') && $this->scope['supplier_id'] === null;
    }

    private function loadMonthlySalesTrend(): void
    {
        if ($this->monthlySalesTrend !== []) {
            return;
        }

        // startOfMonth() first: subMonths() on the 29th-31st would overflow into the next month.
        $from = Carbon::now()->startOfMonth()->subMonths(11);

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

        // Months before the first sale are only empty space on the chart.
        while (count($labels) > 6 && $sales[0] == 0 && $profits[0] == 0) {
            array_shift($labels);
            array_shift($sales);
            array_shift($profits);
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
            ->whereBetween('settlement_date', [$this->periodFrom(), $this->periodTo()])
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

    private function loadTopProductsByStockValue(): void
    {
        $data = $this->stock()
            ->where('current_stock.quantity_on_hand', '>', 0)
            ->select('products.id', 'products.product_name')
            ->selectRaw('SUM(current_stock.total_value) as total_value')
            ->groupBy('products.id', 'products.product_name')
            ->orderByDesc('total_value')
            ->limit(10)
            ->get();

        $this->topProductsByStockValue = [
            'labels' => $data->pluck('product_name')->toArray(),
            'values' => $data->pluck('total_value')->map(fn ($v) => round((float) $v, 2))->toArray(),
        ];
    }

    /** Ledger balance formula used by the Supplier Ledger Register report. */
    public const LEDGER_NET = 'opening_balance + online_amount - invoice_amount - expenses_amount + za_point_five_percent_amount + claim_adjust_amount';

    private function canSeeSupplierLedger(User $user): bool
    {
        return $user->can('report-audit-ledger-register');
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

        $now = $month($this->periodFrom(), $this->periodTo());
        $last = $month($this->previousFrom(), $this->previousTo());

        $balance = (float) $this->ledger()
            ->selectRaw('COALESCE(SUM('.self::LEDGER_NET.'), 0) as balance')
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
        if (isset($this->pendingItems['draftJournalEntries'])) {
            $this->draftLinks['draftJournalEntries'] = route('journal-entries.index', ['filter' => ['status' => 'draft']]);
        }
    }

    /** A period longer than this many days is charted by week instead of by day. */
    public const DAILY_CHART_MAX_DAYS = 62;

    /**
     * Sales and gross profit over the chosen period: one bar per day, or one per week
     * (Monday to Sunday, cut to the period) when the period is longer than about two months.
     */
    private function loadDailySalesTrend(): void
    {
        $from = $this->periodFrom();
        $to = $this->periodTo();

        $data = $this->settlements()
            ->where('status', 'posted')
            ->whereBetween('settlement_date', [$from, $to])
            ->selectRaw('DATE(settlement_date) as date')
            ->selectRaw('SUM(total_sales_amount) as total_sales, SUM(gross_profit) as total_profit')
            ->groupByRaw('DATE(settlement_date)')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->format('Y-m-d'));

        $byWeek = $this->period['days'] > self::DAILY_CHART_MAX_DAYS;
        $buckets = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $start = $byWeek ? $day->copy()->startOfWeek(Carbon::MONDAY)->max($from) : $day;
            $key = $start->format('Y-m-d');
            $buckets[$key] ??= ['label' => $start->format('d M'), 'sales' => 0.0, 'profit' => 0.0];
            $buckets[$key]['sales'] += (float) ($data[$day->format('Y-m-d')]->total_sales ?? 0);
            $buckets[$key]['profit'] += (float) ($data[$day->format('Y-m-d')]->total_profit ?? 0);
        }

        $this->dailySalesTrend = [
            'unit' => $byWeek ? 'week' : 'day',
            'labels' => array_column($buckets, 'label'),
            'sales' => array_map(fn (array $b) => round($b['sales'], 2), array_values($buckets)),
            'profit' => array_map(fn (array $b) => round($b['profit'], 2), array_values($buckets)),
        ];
    }

    private function loadTopProductsBySales(): void
    {
        $query = SalesSettlementItem::query()
            ->join('sales_settlements', 'sales_settlement_items.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('products', 'sales_settlement_items.product_id', '=', 'products.id')
            ->where('sales_settlements.status', 'posted')
            ->whereBetween('sales_settlements.settlement_date', [$this->periodFrom(), $this->periodTo()])
            ->whereNull('sales_settlements.deleted_at');

        $data = $this->scopeSettlements($query)
            ->select('products.id', 'products.product_name')
            ->selectRaw('SUM(sales_settlement_items.total_sales_value) as total_sales')
            ->groupBy('products.id', 'products.product_name')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();

        $this->topProductsBySales = [
            'labels' => $data->pluck('product_name')->toArray(),
            'values' => $data->pluck('total_sales')->map(fn ($v) => round((float) $v, 2))->toArray(),
        ];
    }

    private function loadTopSalespersonBySales(): void
    {
        $query = SalesSettlement::query()
            ->join('employees', 'sales_settlements.employee_id', '=', 'employees.id')
            ->where('sales_settlements.status', 'posted')
            ->whereBetween('sales_settlements.settlement_date', [$this->periodFrom(), $this->periodTo()]);

        $data = $this->scopeSettlements($query)
            ->select('employees.id', 'employees.name')
            ->selectRaw('SUM(sales_settlements.total_sales_amount) as total_sales')
            ->selectRaw('COUNT(*) as trips')
            ->groupBy('employees.id', 'employees.name')
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

        if ($this->canSeeDraftJournalEntries($user)) {
            $this->pendingItems['draftJournalEntries'] = JournalEntry::query()->where('status', 'draft')->count();
        }

        if ($user->can('ticket-list')) {
            $this->pendingItems['pendingTickets'] = Ticket::query()->visibleTo($user)->where('status', 'pending')->count();
        }
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

        // One row per customer-salesman account, straight from the customer account ledger.
        $accounts = $this->customerLedger()
            ->join('customers as c', 'a.customer_id', '=', 'c.id')
            ->leftJoin('suppliers as s', 'e.supplier_id', '=', 's.id')
            ->select('a.id as account_id', 'a.customer_id', 'c.customer_code', 'c.customer_name', 'c.city', 'a.employee_id', 'e.name as salesman', 'e.supplier_id', 's.supplier_name as supplier')
            ->selectRaw("{$balance} as balance")
            ->selectRaw('MAX(CASE WHEN t.credit > 0 THEN t.transaction_date END) as last_recovery')
            ->selectRaw('MAX(CASE WHEN t.debit > 0 THEN t.transaction_date END) as last_credit_sale')
            ->groupBy('a.id', 'a.customer_id', 'c.customer_code', 'c.customer_name', 'c.city', 'a.employee_id', 'e.name', 'e.supplier_id', 's.supplier_name')
            ->havingRaw("{$balance} <> 0")
            ->get();

        $owing = $accounts->filter(fn ($r) => (float) $r->balance > 0);
        $this->kpiCards['marketCredit'] = round((float) $accounts->sum('balance'), 2);
        // What customers owe before netting off advances: the base the aging shares are taken from.
        $this->kpiCards['creditOwed'] = round((float) $owing->sum('balance'), 2);

        $today = Carbon::today();
        // One row per customer-salesman account, so every row opens that salesman's own ledger for the customer.
        // Aged the receivables way: recoveries clear the oldest credit sales first, and what is still
        // owed is aged by the date of each unpaid sale (see CustomerCreditAging).
        $rows = app(CustomerCreditAging::class)->age($owing->values(), $today->toDateString())->map(fn ($r) => [
            'days' => (int) $r->days,
            'bucket' => (int) $r->bucket,
            'buckets' => $r->buckets,
            'in' => array_keys(array_filter($r->buckets, fn ($amount) => $amount > 0)),
            'oldest_unpaid' => $r->oldest_unpaid,
            'id' => (int) $r->customer_id,
            'employee_id' => $r->employee_id ? (int) $r->employee_id : null,
            'name' => $r->customer_name,
            'code' => $r->customer_code,
            'city' => $r->city,
            'used' => round((float) $r->balance, 2),
            'suppliers' => (string) $r->supplier,
            'salesmen' => (string) $r->salesman,
            'last_paid_days' => $r->last_recovery ? (int) abs(Carbon::parse($r->last_recovery)->diffInDays($today)) : null,
        ])->values();

        $this->creditAging = [
            'labels' => CustomerCreditAging::LABELS,
            'values' => array_map(fn ($b) => round((float) $rows->sum(fn ($row) => $row['buckets'][$b]), 2), [0, 1, 2, 3]),
            'counts' => array_map(fn ($b) => $rows->filter(fn ($row) => $row['buckets'][$b] > 0)->count(), [0, 1, 2, 3]),
        ];
        $this->kpiCards['creditOverdue'] = round($this->creditAging['values'][2] + $this->creditAging['values'][3], 2);
        $overdueRows = $rows->filter(fn ($row) => $row['buckets'][2] + $row['buckets'][3] > 0);
        $this->kpiCards['creditOverdueCustomers'] = $overdueRows->pluck('id')->unique()->count();
        $this->kpiCards['creditOverdueAccounts'] = $overdueRows->count();

        $this->kpiCards['creditCustomers'] = $rows->pluck('id')->unique()->count();
        // Grouped by customer before ranking, so a customer who buys from several salesmen shows
        // once with everything they owe; the chart and the "highest credit customer" both use it.
        $this->topCreditCustomers = $rows->groupBy('id')
            ->map(function ($accounts): array {
                $first = $accounts->first();
                $paidDays = $accounts->pluck('last_paid_days')->filter(fn ($days) => $days !== null);

                return [
                    'id' => $first['id'],
                    'name' => $first['name'],
                    'code' => $first['code'],
                    'city' => $first['city'],
                    'label' => $first['name'].($first['city'] ? ' ('.$first['city'].')' : ''),
                    'used' => round((float) $accounts->sum('used'), 2),
                    'accounts' => $accounts->count(),
                    'suppliers' => $accounts->pluck('suppliers')->filter()->unique()->implode(', '),
                    'salesmen' => $accounts->pluck('salesmen')->filter()->unique()->implode(', '),
                    'last_paid_days' => $paidDays->isEmpty() ? null : (int) $paidDays->min(),
                ];
            })
            ->sortByDesc('used')
            ->take(8)
            ->values()
            ->all();
        // Every account with credit older than 30 days, biggest first, for the aging drill-down list.
        $this->agingCustomers = $rows->filter(fn ($row) => $row['bucket'] > 0)->sortByDesc('used')->take(300)->values()->all();
    }

    private function loadSalesBySupplier(): void
    {
        $data = SalesSettlement::query()
            ->join('suppliers', 'sales_settlements.supplier_id', '=', 'suppliers.id')
            ->where('sales_settlements.status', 'posted')
            ->whereBetween('sales_settlements.settlement_date', [$this->periodFrom(), $this->periodTo()])
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

    /**
     * Investment as of the end of the period, and as of the day before it started, using the
     * same figures as reports/investment-summary: stock, market credit, claims, expiry
     * (AMR) and the supplier ledger (negative = we owe suppliers).
     */
    private function loadInvestment(): void
    {
        $service = app(InvestmentSummaryService::class);
        $supplierIds = $this->scope['supplier_id']
            ? [(int) $this->scope['supplier_id']]
            : Supplier::where('disabled', false)->pluck('id')->all();

        // All suppliers in one go: each part is one query across the list (the same sum as
        // asking supplier by supplier, which took about six queries per supplier and date).
        $sum = function (string $date) use ($service, $supplierIds): array {
            $parts = $service->mainInvestment($date, array_map('intval', $supplierIds));

            return array_map(fn (float $v) => round($v, 2), [
                'stock' => (float) $parts['stock'],
                'credit' => (float) $parts['credit'],
                'claims' => (float) $parts['claims'],
                'expiry' => (float) $parts['powder_expiry'] + (float) $parts['liquid_expiry'],
                'ledger' => (float) $parts['ledger'],
            ]);
        };

        $now = $sum($this->period['to']);
        $before = $sum(Carbon::parse($this->period['from'])->subDay()->toDateString());

        $this->investment = [
            'as_of' => $this->period['to'],
            'before_on' => Carbon::parse($this->period['from'])->subDay()->toDateString(),
            'parts' => $now,
            'before' => $before,
            'total' => round(array_sum($now), 2),
            'change' => round(array_sum($now) - array_sum($before), 2),
        ];
    }

    /**
     * Plain-language findings from the figures already loaded. Simple, explainable statistics:
     * run rate against the daily mean, days outside two standard deviations, Pareto share of
     * the top products, collection efficiency and days of credit/stock cover.
     */
    private function loadInsights(): void
    {
        $rs = function (float $v): string {
            $a = abs($v);
            $sign = $v < 0 ? '-' : '';

            return 'Rs '.$sign.match (true) {
                $a >= 10000000 => number_format($a / 10000000, 2).' Cr',
                $a >= 100000 => number_format($a / 100000, 1).' Lac',
                default => number_format($a),
            };
        };
        $k = $this->kpiCards;
        $insights = [];

        $daily = $this->dailySalesTrend['sales'] ?? [];
        $labels = $this->dailySalesTrend['labels'] ?? [];
        $unit = $this->dailySalesTrend['unit'] ?? 'day';
        $active = array_values(array_filter($daily, fn ($v) => $v > 0));

        if (count($active) >= 5) {
            $mean = array_sum($active) / count($active);
            $std = sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $active)) / (count($active) - 1));

            if ($unit === 'day' && $this->period['mtd'] && $this->period['to'] === Carbon::today()->toDateString() && isset($k['totalSalesThisMonth'])) {
                $now = Carbon::today();
                $elapsed = $now->day;
                $pace = $k['totalSalesThisMonth'] / max(1, $elapsed) * $now->daysInMonth;
                $spread = $std * sqrt(max(0, $now->daysInMonth - $elapsed));
                $lastFull = $this->lastMonth['sales'] ?? 0;
                $insights[] = [
                    'tone' => '#1d4ed8',
                    'value' => $rs($pace),
                    'title' => 'On pace for this month',
                    'text' => 'Month-end sales if the daily average of the last '.$elapsed.' days holds (likely '.$rs(max(0, $pace - $spread)).' to '.$rs($pace + $spread).')'
                        .($lastFull > 0 ? '; last month so far was '.$rs($lastFull).'.' : '.'),
                ];
            }

            $unusual = [];
            foreach ($daily as $i => $v) {
                if ($v > 0 && $std > 0 && abs($v - $mean) / $std >= 2) {
                    $unusual[] = ['label' => $labels[$i], 'value' => $v, 'z' => ($v - $mean) / $std];
                }
            }
            if ($unusual !== []) {
                usort($unusual, fn ($a, $b) => abs($b['z']) <=> abs($a['z']));
                $top = $unusual[0];
                $insights[] = [
                    'tone' => '#b45309',
                    'value' => count($unusual).' '.Str::plural($unit, count($unusual)),
                    'title' => 'Unusual sales '.Str::plural($unit).' in the period',
                    'text' => 'More than 2 standard deviations from the '.$rs($mean).' '.($unit === 'week' ? 'weekly' : 'daily').' average. Furthest: '.($unit === 'week' ? 'week of ' : '').$top['label'].' at '.$rs($top['value']).($top['z'] < 0 ? ' (a dip)' : ' (a peak)').'.',
                ];
            }
        }

        $dow = $this->salesByDayOfWeek;
        if (! empty($dow['values']) && array_sum($dow['values']) > 0) {
            $values = array_filter($dow['values'], fn ($v) => $v > 0);
            $best = array_search(max($values), $dow['values'], true);
            $worst = array_search(min($values), $dow['values'], true);
            $insights[] = [
                'tone' => '#0f766e',
                'value' => $dow['labels'][$best],
                'title' => 'Strongest weekday',
                'text' => 'Sells '.round(max($values) / max(1, min($values)), 1).'x the weakest working day ('.$dow['labels'][$worst].') over the last 90 days.',
            ];
        }

        $products = $this->topProductsBySales;
        $periodSales = (float) ($k['totalSalesThisMonth'] ?? 0);
        if (! empty($products['values']) && $periodSales > 0) {
            $topThree = array_sum(array_slice($products['values'], 0, 3));
            $insights[] = [
                'tone' => '#7c3aed',
                'value' => round($topThree / $periodSales * 100).'%',
                'title' => 'Sales from 3 products',
                'text' => ($products['labels'][0] ?? '').' leads. The top 3 products bring '.$rs($topThree).' of '.$rs($periodSales).' sold in the period.',
            ];
        }

        // Same settlement figures as the Credit sales tile, so the two always agree.
        $given = (float) ($k['creditSalesThisMonth'] ?? 0);
        $recovered = (float) ($k['recoveriesThisMonth'] ?? 0);
        if ($given > 0) {
            $insights[] = [
                'tone' => $recovered >= $given ? '#15803d' : '#b91c1c',
                'value' => round($recovered / $given * 100).'%',
                'title' => 'Recovery against new credit',
                'text' => $rs($recovered).' recovered against '.$rs($given).' given in the period'.($recovered >= $given ? ': market credit is shrinking.' : ': market credit is growing by '.$rs($given - $recovered).'.'),
            ];
        }

        $owed = (float) ($k['creditOwed'] ?? 0);
        if ($owed > 0 && isset($k['creditOverdue'])) {
            $insights[] = [
                'tone' => '#c2410c',
                'value' => round($k['creditOverdue'] / $owed * 100).'%',
                'title' => 'Credit older than 60 days',
                'text' => $rs((float) $k['creditOverdue']).' of '.$rs($owed).' owed is from sales more than 60 days old, across '.($k['creditOverdueCustomers'] ?? 0).' customers.',
            ];
        }

        if (isset($k['totalInventoryValue']) && $this->sections['sales']) {
            // Stock is the supplier's whole stock, so the cost of goods sold is too (every
            // user's settlements), not only the settlements this user created.
            $cogs30 = (float) SalesSettlement::query()
                ->when($this->scope['supplier_id'], fn ($q, $id) => $q->where('supplier_id', $id))
                ->where('status', 'posted')
                ->where('settlement_date', '>=', Carbon::today()->subDays(29))
                ->sum('total_cogs');
            if ($cogs30 > 0 && $k['totalInventoryValue'] > 0) {
                $cover = $k['totalInventoryValue'] / ($cogs30 / 30);
                $insights[] = [
                    'tone' => '#0e7490',
                    'value' => round($cover).' days',
                    'title' => 'Stock cover',
                    'text' => $rs((float) $k['totalInventoryValue']).' of stock lasts about '.round($cover).' days at the last 30 days\' cost of goods sold.',
                ];
            }
        }

        $this->insights = array_slice($insights, 0, 6);
    }
}
