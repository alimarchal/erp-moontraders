{{--
    Dashboard: a compact, colourful board. Greeting and period at the top, six solid-colour key figures,
    then charts for sales, investment, credit, stock and the people behind them. Every figure is already
    limited by App\Livewire\Dashboard to the user's supplier and, without "...-view-all", to the records
    they created. Styles: dashboard/style.blade.php; charts: ApexCharts.
--}}
@php
    $k = $kpiCards;
    $n = fn ($v) => number_format((float) $v);
    // Short money: 1.25 Cr / 45.2 Lac / 12,500 (exact figure in the title).
    $rs = function ($v): string {
        $v = (float) $v;
        $sign = $v < 0 ? '-' : '';
        $a = abs($v);

        return $sign.match (true) {
            $a >= 10000000 => number_format($a / 10000000, 2).' Cr',
            $a >= 100000 => number_format($a / 100000, 1).' Lac',
            default => number_format($a),
        };
    };
    $full = fn ($v) => 'Rs '.number_format((float) $v, 2);
    $vsText = $period['mtd'] ? 'vs last month' : 'vs previous';
    $delta = function (float $now, ?float $last) use ($vsText): ?array {
        if ($last === null || $last == 0.0) {
            return null;
        }
        $pct = ($now - $last) / abs($last) * 100;

        return ['up' => $pct >= 0, 'text' => ($pct >= 0 ? '▲ ' : '▼ ').number_format(abs($pct), 0).'% '.$vsText];
    };
    $ownNote = fn (bool $own) => $own ? 'your own entries' : 'all users';
    $pendingTotal = collect($pendingItems)->sum();
    $pendingLinks = [
        'draftSettlements' => ['Draft Settlements', 'sales-settlements.index'],
        'draftGoodsIssues' => ['Draft Goods Issues', 'goods-issues.index'],
        'draftGrns' => ['Draft GRNs', 'goods-receipt-notes.index'],
        'draftJournalEntries' => ['Draft Journal Entries', 'journal-entries.index'],
        'pendingTickets' => ['Pending Tickets', 'tickets.index'],
    ];
    $hasAnySection = collect($sections)->contains(true);
    // Card header link to the related report, only when the user may open it.
    $rl = fn (string $permission, string $route, string $text) => auth()->user()->can($permission) && \Illuminate\Support\Facades\Route::has($route)
        ? '<a href="'.e(route($route)).'">'.e($text).' →</a>' : '';
    $margin = ($k['totalSalesThisMonth'] ?? 0) > 0 ? round(($k['grossProfitThisMonth'] ?? 0) / $k['totalSalesThisMonth'] * 100, 1) : 0;
    $canCreditors = auth()->user()->can('report-audit-creditors-ledger');
    $creditUrl = $canCreditors ? route('reports.creditors-ledger.index', ['filter' => array_filter(['supplier_id' => $scope['supplier_id'], 'has_balance' => 'yes'])]) : null;
    $customerStatement = fn (int $id, ?int $employeeId = null) => $canCreditors
        ? route('reports.creditors-ledger.customer-ledger', array_filter(['customer' => $id, 'filter' => $employeeId ? ['employee_id' => $employeeId] : null]))
        : null;
    $agingReportUrl = $canCreditors ? route('reports.creditors-ledger.aging-report', array_filter(['filter' => array_filter(['supplier_id' => $scope['supplier_id']])])) : null;
    $bucketNames = [1 => '31-60 days', 2 => '61-90 days', 3 => 'Over 90 days'];
    $agingSalesmen = collect($agingCustomers)->pluck('salesmen')->filter()->unique()->sort()->values()->all();
    $salesNow = (float) ($k['totalSalesThisMonth'] ?? 0);
    $sD = $delta($salesNow, $lastMonth['sales'] ?? null);
    $pD = $delta((float) ($k['grossProfitThisMonth'] ?? 0), $lastMonth['profit'] ?? null);
    $cD = $delta((float) ($k['cashCollectedThisMonth'] ?? 0), $lastMonth['cash'] ?? null);
    $includesToday = $period['to'] === now()->toDateString();
    $inv = $investment;
    $invChange = (float) ($inv['change'] ?? 0);
    $keep = array_filter(['supplier' => $scope['can_pick_supplier'] ? request()->query('supplier') : null]);
    $hourNow = (int) now()->format('G');
    $greeting = $hourNow < 12 ? 'Good morning' : ($hourNow < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->replaceMatches('/^(mr|mrs|ms|miss|dr)\.?\s+/i', '')->trim()->before(' ');

    $hasSales = $sections['sales'] && $salesNow > 0;
    $hasInvestment = $sections['investment'] && ! empty($inv['parts']);
    $hasCredit = $sections['credit'] && isset($k['marketCredit']);
    $hasStock = $sections['inventory'] && ! empty($topProductsByStockValue['labels'] ?? []);
    $methodTotal = array_sum($salesByPaymentMethod['values'] ?? []);
    $multiSupplier = count($salesBySupplier['labels'] ?? []) > 1;

    // Investment parts, in the order of the Investment Summary report; the supplier ledger is minus when we owe.
    $invParts = [];
    if ($hasInvestment) {
        foreach ([['stock', 'Stock in hand', '#2563eb'], ['credit', 'Market credit', '#f97316'], ['claims', 'Claims', '#0891b2'], ['expiry', 'Expiry (AMR)', '#7c3aed'], ['ledger', 'Supplier ledger', '#e11d48']] as [$key, $name, $color]) {
            $invParts[] = ['name' => $name, 'value' => (float) ($inv['parts'][$key] ?? 0), 'color' => $color];
        }
    }

    // Top performers strip.
    $topProduct = ($topProductsBySales['labels'] ?? []) ? ['name' => $topProductsBySales['labels'][0], 'value' => $topProductsBySales['values'][0]] : null;
    $topCreditor = $topCreditCustomers[0] ?? null;
    $topSalesman = ($topSalespersonBySales['labels'] ?? []) ? ['name' => $topSalespersonBySales['labels'][0], 'value' => $topSalespersonBySales['values'][0]] : null;
    $topStock = ($topProductsByStockValue['labels'] ?? []) ? ['name' => $topProductsByStockValue['labels'][0], 'value' => $topProductsByStockValue['values'][0]] : null;
    $topSupplierSale = $multiSupplier ? ['name' => $salesBySupplier['labels'][0], 'value' => $salesBySupplier['sales'][0]] : null;
    $strip = array_values(array_filter([
        $sections['sales'] && $topProduct ? ['Best selling product', $topProduct['name'], 'Rs '.$rs($topProduct['value']), '#2563eb'] : null,
        $hasCredit && $topCreditor ? ['Highest credit customer', $topCreditor['name'], 'Rs '.$rs($topCreditor['used']), '#e11d48'] : null,
        $sections['sales'] && $topSalesman ? ['Best salesman', $topSalesman['name'], 'Rs '.$rs($topSalesman['value']), '#16a34a'] : null,
        $hasStock && $topStock ? ['Highest stock value', $topStock['name'], 'Rs '.$rs($topStock['value']), '#7c3aed'] : null,
        $sections['sales'] && $topSupplierSale ? ['Top company by sales', $topSupplierSale['name'], 'Rs '.$rs($topSupplierSale['value']), '#f97316'] : null,
    ]));
    $star = 'M11.48 3.5a.56.56 0 0 1 1.04 0l2.13 5.11 5.52.44c.5.04.7.66.32.99l-4.2 3.6 1.28 5.38a.56.56 0 0 1-.84.61L12 16.77l-4.73 2.89a.56.56 0 0 1-.84-.61l1.28-5.38-4.2-3.6a.56.56 0 0 1 .32-.99l5.52-.44 2.13-5.11Z';
@endphp

<div>
    <div class="db-page ak-page" style="gap:14px">
        @include('livewire.dashboard.style')

        {{-- Top bar: greeting, the period everything below follows, refresh --}}
        <header class="db-top" x-data="{ filters: {{ request()->hasAny(['from', 'to']) && ! $period['preset'] ? 'true' : 'false' }} }">
            <div class="db-top-row">
                <div class="db-top-title">
                    <h1 class="db-greet">{{ $greeting }}, {{ $firstName }}</h1>
                    <p>
                        <span class="db-period"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>{{ $period['label'] }}</span>
                        <span>compared with {{ $period['prev_label'] }}</span>
                        <span class="db-live" title="Figures are kept for {{ intdiv(\App\Livewire\Dashboard::CACHE_SECONDS, 60) }} minutes; Refresh recalculates them"><i></i> updated {{ $refreshedAt }}</span>
                    </p>
                </div>
                <div class="db-top-actions">
                    <button type="button" class="db-btn" @click="filters = ! filters" :aria-expanded="filters" aria-controls="db-filter-panel">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                        Period
                    </button>
                    <a class="db-btn" href="{{ route('dashboard', array_merge($keep, array_filter(['from' => request()->query('from'), 'to' => request()->query('to')]), ['refresh' => 1])) }}" title="Work the figures out again now">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                        Refresh
                    </a>
                </div>
            </div>
            <form method="GET" action="{{ route('dashboard') }}" id="db-filter-panel" class="db-filter-panel" x-show="filters" x-cloak aria-label="Choose the period">
                <div class="db-filter-label">Periods</div>
                <div class="db-chips">
                    @foreach ($period['presets'] as $preset)
                        <a class="db-chip {{ $period['preset'] === $preset['name'] ? 'is-on' : '' }}" href="{{ route('dashboard', array_merge($keep, ['from' => $preset['from'], 'to' => $preset['to']])) }}">{{ $preset['name'] }}</a>
                    @endforeach
                </div>
                <div class="db-filter-label">Custom dates</div>
                <div class="db-range">
                    @foreach ($keep as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                    <label for="db-from" class="sr-only">From</label><input id="db-from" type="date" name="from" value="{{ $period['from'] }}" max="{{ now()->toDateString() }}">
                    <span>to</span>
                    <label for="db-to" class="sr-only">To</label><input id="db-to" type="date" name="to" value="{{ $period['to'] }}" max="{{ now()->toDateString() }}">
                    <button type="submit" class="db-btn db-btn-primary">Show</button>
                    <a class="db-btn" href="{{ route('dashboard', $keep) }}">This month</a>
                </div>
            </form>
        </header>

        {{-- Whose figures these are, and what needs doing --}}
        <div class="db-scope">
            @if ($scope['can_pick_supplier'] && $supplierOptions)
                <label class="db-supplier-pick" title="Show the whole dashboard for one supplier">
                    <span>Supplier</span>
                    <select onchange="window.location = this.value">
                        <option value="{{ route('dashboard', array_filter(['from' => request()->query('from'), 'to' => request()->query('to')])) }}">All suppliers</option>
                        @foreach ($supplierOptions as $sid => $sname)
                            <option value="{{ route('dashboard', array_filter(['supplier' => $sid, 'from' => request()->query('from'), 'to' => request()->query('to')])) }}" @selected($scope['supplier_id'] === $sid)>{{ $sname }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($scope['supplier_id'])<a href="{{ route('dashboard') }}" class="ak-scope" style="text-decoration:none">✕ Clear</a>@endif
            @else
                <span class="ak-scope {{ $scope['supplier_id'] ? 'ak-scope-limited' : '' }}" title="Supplier data you can see">Supplier: {{ $scope['supplier'] }}</span>
            @endif
            @if ($sections['sales'])
                <span class="ak-scope {{ $scope['own_settlements'] ? 'ak-scope-limited' : '' }}">Settlements: {{ $ownNote($scope['own_settlements']) }}</span>
            @endif
            @if ($sections['distribution'])
                <span class="ak-scope {{ $scope['own_issues'] ? 'ak-scope-limited' : '' }}">Goods issues: {{ $ownNote($scope['own_issues']) }}</span>
            @endif
            @if ($scope['is_super_admin'] || $scope['is_admin'])
                <span class="ak-scope" style="background:#fee2e2; border-color:#fca5a5; color:#991b1b">{{ $scope['is_super_admin'] ? 'Super admin' : 'Admin' }} view</span>
            @else
                <span class="ak-scope" style="background:#e0e7ff; border-color:#a5b4fc; color:#312e81">Your view</span>
            @endif

            @if ($pendingTotal > 0)
                <div class="db-pending" role="status">
                    <span>Pending Actions ({{ $pendingTotal }})</span>
                    @foreach ($pendingLinks as $key => [$label, $route])
                        @if (($pendingItems[$key] ?? 0) > 0)
                            <a href="{{ $draftLinks[$key] ?? route($route) }}"><b>{{ $pendingItems[$key] }}</b> {{ $label }}</a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        @unless ($hasAnySection)
            <div class="db-card">
                <div class="db-empty">
                    <b>Nothing to show yet</b>
                    <span>No module is enabled for your account. Ask the administrator to give you a role on Settings &rarr; Users.</span>
                    @can('ticket-list')
                        <a href="{{ route('tickets.index') }}" class="ak-btn" style="margin-top:12px">Open Tickets &rarr;</a>
                    @endcan
                </div>
            </div>
        @endunless

        {{-- ============================== Key figures ============================== --}}
        @if (isset($k['totalSalesThisMonth']) || $hasCredit || $hasInvestment)
            <section class="db-tiles" aria-label="Key figures">
                @isset($k['totalSalesThisMonth'])
                    @include('livewire.dashboard.tile', ['label' => 'Total sales', 'prefix' => 'Rs', 'value' => $rs($salesNow), 'tone' => '#2563eb', 'icon' => 'sales', 'title' => $full($salesNow), 'badge' => $sD['text'] ?? null, 'hint' => $includesToday ? 'Rs '.$rs($k['salesToday'] ?? 0).' today' : null, 'spark' => 'spark-sales'])
                    @include('livewire.dashboard.tile', ['label' => 'Gross profit', 'prefix' => 'Rs', 'value' => $rs($k['grossProfitThisMonth'] ?? 0), 'tone' => '#16a34a', 'icon' => 'profit', 'title' => $full($k['grossProfitThisMonth'] ?? 0), 'badge' => $pD['text'] ?? null, 'hint' => $margin.'% margin', 'spark' => 'spark-profit'])
                    @include('livewire.dashboard.tile', ['label' => 'Cash received', 'prefix' => 'Rs', 'value' => $rs($k['cashCollectedThisMonth'] ?? 0), 'tone' => '#0891b2', 'icon' => 'cash', 'title' => $full($k['cashCollectedThisMonth'] ?? 0), 'badge' => $cD['text'] ?? null, 'hint' => ($salesNow > 0 ? round(($k['cashCollectedThisMonth'] ?? 0) / $salesNow * 100) : 0).'% of sales'])
                    @include('livewire.dashboard.tile', ['label' => 'Credit sales', 'prefix' => 'Rs', 'value' => $rs($k['creditSalesThisMonth'] ?? 0), 'tone' => '#f97316', 'icon' => 'card', 'title' => $full($k['creditSalesThisMonth'] ?? 0), 'hint' => ($salesNow > 0 ? round(($k['creditSalesThisMonth'] ?? 0) / $salesNow * 100) : 0).'% of sales · Rs '.$rs($k['recoveriesThisMonth'] ?? 0).' recovered'])
                @endisset
                @if ($hasCredit)
                    @include('livewire.dashboard.tile', ['label' => 'Market credit', 'prefix' => 'Rs', 'value' => $rs($k['marketCredit']), 'tone' => '#e11d48', 'icon' => 'alert', 'title' => $full($k['marketCredit']), 'href' => $creditUrl, 'hint' => $n($k['creditCustomers'] ?? 0).' customers owe'])
                @endif
                @if ($hasInvestment)
                    @include('livewire.dashboard.tile', ['label' => 'Investment', 'prefix' => 'Rs', 'value' => $rs($inv['total']), 'tone' => '#7c3aed', 'icon' => 'invest', 'title' => $full($inv['total']), 'href' => auth()->user()->can('report-audit-investment-summary') ? route('reports.investment-summary.index') : null, 'badge' => ($invChange >= 0 ? '▲ ' : '▼ ').'Rs '.$rs(abs($invChange)), 'hint' => 'since '.\Carbon\Carbon::parse($inv['before_on'])->format('d M')])
                @endif
            </section>
        @endif

        {{-- ============================== Sales ============================== --}}
        @if ($sections['sales'])
            <div class="db-row">
                <section class="db-card" style="--accent: var(--s1)" aria-label="Sales breakdown">
                    <div class="db-card-head"><div><h3>Sales breakdown</h3><p class="db-card-sub">cash, credit and bank transfer</p></div>{!! $rl('report-sales-daily-sales', 'reports.daily-sales.index', 'Daily sales') !!}</div>
                    @if ($methodTotal > 0)
                        <div id="db-method" class="db-chart" role="img" aria-label="Sales split into cash, credit and bank transfer"></div>
                    @else
                        <div class="db-empty"><b>No sales in this period</b></div>
                    @endif
                </section>
                <section class="db-card" style="--accent: var(--s3)" aria-label="Top selling products">
                    <div class="db-card-head"><div><h3>Top selling products</h3><p class="db-card-sub">by sales value, {{ $period['label'] }}</p></div></div>
                    @if (($topProductsBySales['labels'] ?? []) !== [])
                        <div id="db-products" class="db-chart" role="img" aria-label="Top selling products"></div>
                    @else
                        <div class="db-empty"><b>No product sales in this period</b></div>
                    @endif
                </section>
                <section class="db-card" style="--accent: var(--s2)" aria-label="{{ $multiSupplier ? 'Sales share by company' : 'Daily sales' }}">
                    <div class="db-card-head"><div><h3>{{ $multiSupplier ? 'Sales share by company' : 'Sales day by day' }}</h3><p class="db-card-sub">{{ $multiSupplier ? 'which company brings the sales' : $period['label'] }}</p></div></div>
                    @if ($multiSupplier || ($dailySalesTrend['sales'] ?? []) !== [])
                        <div id="{{ $multiSupplier ? 'db-supplier' : 'db-daily' }}" class="db-chart" role="img" aria-label="{{ $multiSupplier ? 'Sales share by company' : 'Sales per day' }}"></div>
                    @else
                        <div class="db-empty"><b>No sales in this period</b></div>
                    @endif
                </section>
            </div>
        @endif

        {{-- ============================== Trend, investment, credit ============================== --}}
        @if ($sections['sales'] || $hasInvestment || $hasCredit)
            <div class="db-row">
                @if ($sections['sales'])
                    <section class="db-card" style="--accent: var(--s1)" aria-label="Sales trend">
                        <div class="db-card-head"><div><h3>Sales trend</h3><p class="db-card-sub">sales and gross profit, last months</p></div>{!! $rl('report-financial-income-statement', 'reports.income-statement.index', 'Income statement') !!}</div>
                        @if (array_sum($monthlySalesTrend['sales'] ?? []) > 0)
                            <div id="db-trend" class="db-chart" role="img" aria-label="Sales and gross profit per month"></div>
                        @else
                            <div class="db-empty"><b>No posted sales yet</b></div>
                        @endif
                    </section>
                @endif
                @if ($hasInvestment)
                    <section class="db-card" style="--accent: var(--s4)" aria-label="Investment">
                        <div class="db-card-head"><div><h3>Investment</h3><p class="db-card-sub">as of {{ \Carbon\Carbon::parse($inv['as_of'])->format('d M Y') }}</p></div>{!! $rl('report-audit-investment-summary', 'reports.investment-summary.index', 'Investment summary') !!}</div>
                        <div class="db-big" title="{{ $full($inv['total']) }}"><small>Rs</small>{{ $rs($inv['total']) }}<em class="{{ $invChange >= 0 ? 'up' : 'down' }}">{{ $invChange >= 0 ? '▲' : '▼' }} Rs {{ $rs(abs($invChange)) }} since {{ \Carbon\Carbon::parse($inv['before_on'])->format('d M') }}</em></div>
                        <div id="db-invest" class="db-chart" style="min-height:215px" role="img" aria-label="Investment by part"></div>
                    </section>
                @endif
                @if ($hasCredit)
                    <section class="db-card" style="--accent: var(--s6)" aria-label="Credit market analysis">
                        <div class="db-card-head"><div><h3>Credit market analysis</h3><p class="db-card-sub">what customers owe, by age of the unpaid sale</p></div>{!! $canCreditors ? '<a href="'.e($creditUrl).'">Creditors ledger →</a>' : '' !!}</div>
                        <div class="db-big" title="{{ $full($k['creditOwed'] ?? 0) }}"><small>Rs</small>{{ $rs($k['creditOwed'] ?? 0) }}<em class="{{ ($k['creditOverdue'] ?? 0) > 0 ? 'down' : 'up' }}">{{ ($k['creditOwed'] ?? 0) > 0 ? round(($k['creditOverdue'] ?? 0) / $k['creditOwed'] * 100) : 0 }}% over 60 days</em></div>
                        @if (array_sum($creditAging['values'] ?? []) > 0)
                            <div id="db-aging" class="db-chart" style="min-height:215px" role="img" aria-label="Credit owed by age"></div>
                        @else
                            <div class="db-empty"><b>No credit outstanding</b></div>
                        @endif
                    </section>
                @endif
            </div>
        @endif

        {{-- ============================== People and stock ============================== --}}
        @php
            $creditByCustomer = collect($topCreditCustomers)->groupBy('id')->map(fn ($g) => ['name' => $g->first()['name'].($g->first()['city'] ? ' ('.$g->first()['city'].')' : ''), 'used' => $g->sum('used')])->sortByDesc('used')->take(8)->values();
            $topCreditChart = ['labels' => $creditByCustomer->pluck('name')->all(), 'values' => $creditByCustomer->pluck('used')->all()];
        @endphp
        @if ($hasCredit || $sections['sales'] || $hasStock)
            <div class="db-row">
                @if ($hasCredit)
                    <section class="db-card" style="--accent: var(--s6)" aria-label="Top credit customers">
                        <div class="db-card-head"><div><h3>Top credit customers</h3><p class="db-card-sub">biggest amounts owed</p></div></div>
                        @if ($topCreditChart['labels'] !== [])
                            <div id="db-topcredit" class="db-chart" role="img" aria-label="Customers who owe the most"></div>
                        @else
                            <div class="db-empty"><b>No credit outstanding</b></div>
                        @endif
                    </section>
                @endif
                @if ($sections['sales'])
                    <section class="db-card" style="--accent: var(--s3)" aria-label="Top salesmen">
                        <div class="db-card-head"><div><h3>Top salesmen</h3><p class="db-card-sub">by sales, {{ $period['label'] }}</p></div></div>
                        @if (($topSalespersonBySales['labels'] ?? []) !== [])
                            <div id="db-salesmen" class="db-chart" role="img" aria-label="Sales by salesman"></div>
                        @else
                            <div class="db-empty"><b>No sales in this period</b></div>
                        @endif
                    </section>
                @endif
                @if ($hasStock)
                    <section class="db-card" style="--accent: var(--s5)" aria-label="Top stock items">
                        <div class="db-card-head"><div><h3>Top stock items</h3><p class="db-card-sub">by value in stock</p></div><a href="{{ route('inventory.current-stock.index') }}">Current stock →</a></div>
                        <div class="db-big" title="{{ $full($k['totalInventoryValue'] ?? 0) }}"><small>Rs</small>{{ $rs($k['totalInventoryValue'] ?? 0) }}<em class="up">{{ $n($k['productsInStock'] ?? 0) }} products</em></div>
                        <div id="db-stock" class="db-chart" style="min-height:215px" role="img" aria-label="Products with the most stock value"></div>
                    </section>
                @endif
            </div>
        @endif

        {{-- ============================== Top performers ============================== --}}
        @if ($strip)
            <section class="db-strip" aria-label="Top performers">
                @foreach ($strip as [$label, $name, $value, $dot])
                    <div style="--dot: {{ $dot }}">
                        <i><svg viewBox="0 0 24 24" fill="currentColor"><path d="{{ $star }}" /></svg></i>
                        <p style="margin:0;min-width:0"><small>{{ $label }}</small><b title="{{ $name }}">{{ $name }}</b><span>{{ $value }}</span></p>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- ============================== What the numbers say ============================== --}}
        @if ($insights)
            <section class="db-insights" aria-label="Insights">
                @foreach (array_slice($insights, 0, 3) as $insight)
                    <div class="db-insight" style="--tone: {{ $insight['tone'] }}">
                        <b>{{ $insight['value'] }}</b>
                        <h3>{{ $insight['title'] }}</h3>
                        <p>{{ $insight['text'] }}</p>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- ============================== Late credit: aging creditors with filters ============================== --}}
        @if ($hasCredit && $agingCustomers)
            <section class="db-card db-aging" id="db-aging-list" aria-label="Late credit, aging creditors" style="--accent: var(--s6); padding: 0; overflow: hidden"
                x-data="{
                    aging: 'late', q: '', salesman: '',
                    inAging(bs) { return this.aging === 'all' ? true : (this.aging === 'late' ? bs.some(b => b >= 2) : bs.includes(this.aging)); },
                    partOf(parts) { return this.aging === 'all' ? parts.slice(1).reduce((a, b) => a + b, 0) : (this.aging === 'late' ? parts[2] + parts[3] : (parts[this.aging] ?? 0)); },
                    show(row) { return this.inAging(row.in) && (this.salesman === '' || row.salesmen === this.salesman) && (this.q === '' || row.hay.includes(this.q.toLowerCase())); },
                    shown: 0,
                }"
                @aging-bucket.window="aging = $event.detail; $el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                <div class="db-card-head" style="padding: 14px 16px 6px; flex-wrap: wrap; gap: 10px">
                    <div>
                        <h3>Late credit: aging creditors</h3>
                        <p class="db-card-sub">Customers with unpaid credit older than 30 days, biggest first. Payments clear the oldest sales first; "Days" is the age of the oldest unpaid sale.</p>
                    </div>
                    <div class="db-filters" aria-label="Filter the list">
                        <div class="db-aging-chips">
                            <button type="button" @click="aging = 'late'" :class="aging === 'late' && 'is-on'">60+ days <b>{{ $n($k['creditOverdueAccounts'] ?? 0) }}</b></button>
                            @foreach ($bucketNames as $i => $label)
                                <button type="button" @click="aging = {{ $i }}" :class="aging === {{ $i }} && 'is-on'">{{ $label }} <b>{{ $n($creditAging['counts'][$i] ?? 0) }}</b></button>
                            @endforeach
                            <button type="button" @click="aging = 'all'" :class="aging === 'all' && 'is-on'">All late</button>
                        </div>
                        <select x-model="salesman" aria-label="Salesman">
                            <option value="">All salesmen</option>
                            @foreach ($agingSalesmen as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
                        </select>
                        <input type="search" x-model="q" placeholder="Search customer or city" aria-label="Search customer or city">
                    </div>
                </div>
                <div class="db-aging-scroll">
                    <table class="ak-dt" style="min-width: 860px">
                        <thead>
                            <tr>
                                <th scope="col">Customer</th>
                                <th scope="col">{{ $scope['supplier_id'] === null ? 'Company · salesman' : 'Salesman' }}</th>
                                <th scope="col" class="ak-num">Owes (Rs)</th>
                                <th scope="col" class="ak-num">In this range</th>
                                <th scope="col" class="ak-c">Oldest unpaid sale</th>
                                <th scope="col" class="ak-c">Last paid</th>
                                <th scope="col" class="ak-c">Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agingCustomers as $cust)
                                @php
                                    $row = ['in' => $cust['in'], 'salesmen' => $cust['salesmen'], 'hay' => mb_strtolower($cust['name'].' '.$cust['code'].' '.$cust['city'])];
                                @endphp
                                <tr x-show="show({{ \Illuminate\Support\Js::from($row) }})">
                                    <td data-label="Customer">
                                        @if ($canCreditors)
                                            <a href="{{ $customerStatement($cust['id'], $cust['employee_id']) }}" class="ak-primary-link" title="Open {{ $cust['salesmen'] }}'s ledger for this customer">{{ $cust['name'] }}</a>
                                        @else
                                            <span class="ak-strong">{{ $cust['name'] }}</span>
                                        @endif
                                        <div class="ak-muted">{{ $cust['code'] }}{{ $cust['city'] ? ' · '.$cust['city'] : '' }}@if ($canCreditors) · <a href="{{ $customerStatement($cust['id']) }}" title="All salesmen for this customer">full statement</a>@endif</div>
                                    </td>
                                    <td data-label="{{ $scope['supplier_id'] === null ? 'Company · salesman' : 'Salesman' }}">
                                        @if ($scope['supplier_id'] === null)<div>{{ $cust['suppliers'] ?: '—' }}</div>@endif
                                        <div class="{{ $scope['supplier_id'] === null ? 'ak-muted' : '' }}">{{ $cust['salesmen'] ?: '—' }}</div>
                                    </td>
                                    <td class="ak-num ak-strong" data-label="Owes (Rs)">{{ number_format($cust['used']) }}</td>
                                    <td class="ak-num" data-label="In this range" x-text="Math.round(partOf({{ \Illuminate\Support\Js::from($cust['buckets']) }})).toLocaleString('en-PK')"></td>
                                    <td class="ak-c" data-label="Oldest unpaid sale">{{ $cust['oldest_unpaid'] ? \Carbon\Carbon::parse($cust['oldest_unpaid'])->format('d M Y') : '—' }}</td>
                                    <td class="ak-c" data-label="Last paid">{{ $cust['last_paid_days'] === null ? 'Never' : now()->subDays($cust['last_paid_days'])->format('d M Y') }}</td>
                                    <td class="ak-c" data-label="Days"><span class="ak-status {{ $cust['bucket'] >= 3 ? 'ak-status-red' : 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ $cust['days'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="ak-muted" style="padding: 8px 16px 10px; margin: 0; font-size: 12px">
                    Totals: 31-60 days Rs {{ $rs($creditAging['values'][1] ?? 0) }} &middot; 61-90 days Rs {{ $rs($creditAging['values'][2] ?? 0) }} &middot; over 90 days Rs {{ $rs($creditAging['values'][3] ?? 0) }}
                    @if (count($agingCustomers) >= 300) &middot; showing the 300 biggest @endif
                    @if ($agingReportUrl) &middot; <a href="{{ $agingReportUrl }}">Aging report →</a>@endif
                    @if ($creditUrl) &middot; <a href="{{ $creditUrl }}">Creditors ledger →</a>@endif
                </p>
            </section>
        @endif
    </div>

    @php
        $chartData = [
            'method' => $methodTotal > 0 ? $salesByPaymentMethod : null,
            'products' => ($topProductsBySales['labels'] ?? []) !== [] ? $topProductsBySales : null,
            'supplier' => $multiSupplier ? $salesBySupplier : null,
            'daily' => $dailySalesTrend ?: null,
            'monthly' => array_sum($monthlySalesTrend['sales'] ?? []) > 0 ? $monthlySalesTrend : null,
            'invest' => $hasInvestment ? ['labels' => array_column($invParts, 'name'), 'values' => array_column($invParts, 'value'), 'colors' => array_column($invParts, 'color')] : null,
            'aging' => array_sum($creditAging['values'] ?? []) > 0 ? $creditAging : null,
            'topCredit' => $topCreditChart['labels'] !== [] ? $topCreditChart : null,
            'salesmen' => ($topSalespersonBySales['labels'] ?? []) !== [] ? $topSalespersonBySales : null,
            'stock' => ($topProductsByStockValue['labels'] ?? []) !== [] ? $topProductsByStockValue : null,
        ];
    @endphp

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof ApexCharts === 'undefined') { return; }
                const data = {{ \Illuminate\Support\Js::from($chartData) }};
                const c = ['#2563eb', '#f97316', '#16a34a', '#7c3aed', '#0891b2', '#e11d48', '#f59e0b', '#64748b'];
                const ink = '#475569', grid = '#e2e8f0';
                const short = v => { const a = Math.abs(v), s = v < 0 ? '-' : ''; return a >= 1e7 ? s + (a / 1e7).toFixed(1) + ' Cr' : a >= 1e5 ? s + (a / 1e5).toFixed(1) + ' Lac' : a >= 1e3 ? s + (a / 1e3).toFixed(0) + 'k' : s + Math.round(a); };
                const rupees = v => 'Rs ' + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
                const cut = (t, n) => String(t).length > n ? String(t).slice(0, n - 1) + '…' : t;
                const base = {
                    chart: { fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false } },
                    dataLabels: { enabled: false },
                    grid: { borderColor: grid, strokeDashArray: 3, padding: { left: 4, right: 8, top: 0, bottom: 0 } },
                    legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', labels: { colors: ink }, markers: { size: 6 } },
                    xaxis: { labels: { style: { colors: ink, fontSize: '11px' } }, axisBorder: { color: grid }, axisTicks: { show: false } },
                    yaxis: { labels: { style: { colors: ink, fontSize: '11px' }, formatter: short } },
                    tooltip: { y: { formatter: rupees } },
                    states: { active: { filter: { type: 'none' } } },
                };
                const draw = (id, opts) => {
                    const el = document.getElementById(id);
                    if (el) { new ApexCharts(el, Object.assign({}, base, opts, { chart: Object.assign({}, base.chart, opts.chart) })).render(); }
                };
                const donut = (id, labels, values, colors, total, fmt = rupees, legendAt = 'right') => draw(id, {
                    chart: { type: 'donut', height: legendAt === 'bottom' ? 260 : 240 },
                    series: values, labels, colors,
                    stroke: { width: 2, colors: ['#fff'] },
                    tooltip: { y: { formatter: fmt } },
                    legend: Object.assign({}, base.legend, { position: legendAt, horizontalAlign: 'center', fontSize: '12px', formatter: (name, o) => name + '  ' + short(o.w.globals.series[o.seriesIndex]) }),
                    dataLabels: { enabled: true, formatter: v => v >= 6 ? Math.round(v) + '%' : '', dropShadow: { enabled: false }, style: { fontSize: '11px', fontWeight: 700 } },
                    plotOptions: { pie: { donut: { size: '62%', labels: { show: true, name: { fontSize: '11px', color: ink }, value: { fontSize: '16px', fontWeight: 800, color: '#0f172a', formatter: v => 'Rs ' + short(Number(v)) }, total: { show: true, label: total, color: ink, fontSize: '11px', formatter: w => 'Rs ' + short(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } },
                });
                const columns = (id, labels, values, colors, height = 230, fmt = short, events = {}) => draw(id, {
                    chart: { type: 'bar', height, events },
                    series: [{ name: 'Value', data: values }], colors,
                    plotOptions: { bar: { columnWidth: '56%', borderRadius: 5, borderRadiusApplication: 'end', distributed: true, dataLabels: { position: 'top' } } },
                    legend: { show: false },
                    dataLabels: { enabled: true, formatter: v => v > 0 ? fmt(v) : '', offsetY: -18, style: { fontSize: '11px', fontWeight: 700, colors: ['#0f172a'] } },
                    xaxis: Object.assign({}, base.xaxis, { categories: labels, labels: { style: { colors: ink, fontSize: '11px' }, trim: true, rotate: 0 } }),
                    yaxis: { show: false },
                    grid: { show: false, padding: { left: 6, right: 6, top: 12, bottom: 0 } },
                });
                const hbars = (id, labels, values, colors, height) => draw(id, {
                    chart: { type: 'bar', height: height || Math.max(200, labels.length * 34 + 24) },
                    series: [{ name: 'Value', data: values }], colors,
                    plotOptions: { bar: { horizontal: true, barHeight: '66%', borderRadius: 5, borderRadiusApplication: 'end', distributed: true, dataLabels: { position: 'top' } } },
                    legend: { show: false },
                    xaxis: Object.assign({}, base.xaxis, { categories: labels.map(l => cut(l, 26)), labels: { show: false }, axisBorder: { show: false }, max: Math.max(1, ...values.map(Math.abs)) * 1.3 }),
                    grid: { show: false, padding: { left: 6, right: 70, top: 0, bottom: 0 } },
                    dataLabels: { enabled: true, formatter: v => short(v), textAnchor: 'start', offsetX: 38, style: { fontSize: '11px', fontWeight: 700, colors: ['#0f172a'] }, dropShadow: { enabled: false } },
                    yaxis: { labels: { style: { colors: '#1e293b', fontSize: '12px' }, maxWidth: 190 } },
                    tooltip: { y: { formatter: rupees }, x: { formatter: (v, o) => labels[o.dataPointIndex] } },
                });
                const spark = (id, series) => draw(id, {
                    chart: { type: 'area', height: 26, sparkline: { enabled: true } },
                    series: [{ name: 'Daily', data: series }], colors: ['#ffffff'],
                    stroke: { curve: 'monotoneCubic', width: 2 },
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
                    tooltip: { fixed: { enabled: false }, x: { show: false }, y: { formatter: rupees }, marker: { show: false } },
                });

                if (data.daily) {
                    spark('spark-sales', data.daily.sales);
                    spark('spark-profit', data.daily.profit || []);
                }
                if (data.method) {
                    const keep = data.method.values.map((v, i) => [data.method.labels[i], v]).filter(p => p[1] > 0);
                    donut('db-method', keep.map(p => p[0]), keep.map(p => p[1]), ['#2563eb', '#f97316', '#0891b2'], 'Total sales');
                }
                if (data.products) {
                    hbars('db-products', data.products.labels.slice(0, 8), data.products.values.slice(0, 8), c);
                }
                if (data.supplier) {
                    const head = data.supplier.labels.slice(0, 5), vals = data.supplier.sales.slice(0, 5);
                    const rest = data.supplier.sales.slice(5).reduce((a, b) => a + b, 0);
                    if (rest > 0) { head.push('Others'); vals.push(rest); }
                    donut('db-supplier', head.map(l => cut(l, 18)), vals, c, 'Total sales', rupees, 'bottom');
                } else if (data.daily) {
                    draw('db-daily', {
                        chart: { type: 'bar', height: 240 },
                        series: [{ name: 'Sales', data: data.daily.sales }], colors: ['#f97316'],
                        plotOptions: { bar: { columnWidth: '68%', borderRadius: 2, borderRadiusApplication: 'end' } },
                        legend: { show: false },
                        xaxis: Object.assign({}, base.xaxis, { categories: data.daily.labels, labels: { style: { colors: ink, fontSize: '10px' }, rotate: -45, hideOverlappingLabels: true }, tickAmount: 10 }),
                    });
                }
                if (data.monthly) {
                    draw('db-trend', {
                        chart: { type: 'bar', height: 240 },
                        series: [{ name: 'Sales', data: data.monthly.sales }, { name: 'Gross profit', data: data.monthly.profits }],
                        colors: ['#2563eb', '#16a34a'],
                        plotOptions: { bar: { columnWidth: '62%', borderRadius: 4, borderRadiusApplication: 'end' } },
                        stroke: { show: true, width: 2, colors: ['#fff'] },
                        xaxis: Object.assign({}, base.xaxis, { categories: data.monthly.labels.map(l => l.slice(0, 3)) }),
                    });
                }
                if (data.invest) { hbars('db-invest', data.invest.labels, data.invest.values, data.invest.colors, 215); }
                if (data.aging) { columns('db-aging', data.aging.labels, data.aging.values, ['#16a34a', '#f59e0b', '#f97316', '#e11d48'], 215, short, { dataPointSelection: (e, ctx, o) => { if (o.dataPointIndex > 0) { window.dispatchEvent(new CustomEvent('aging-bucket', { detail: o.dataPointIndex })); } } }); }
                if (data.topCredit) { hbars('db-topcredit', data.topCredit.labels, data.topCredit.values, ['#e11d48', '#f97316', '#f59e0b', '#7c3aed', '#0891b2', '#64748b']); }
                if (data.salesmen) { hbars('db-salesmen', data.salesmen.labels, data.salesmen.values, c); }
                if (data.stock) { hbars('db-stock', data.stock.labels.slice(0, 8), data.stock.values.slice(0, 8), ['#0891b2', '#2563eb', '#7c3aed', '#16a34a', '#f59e0b', '#64748b', '#e11d48', '#f97316']); }
            });
        </script>
    @endpush
</div>
