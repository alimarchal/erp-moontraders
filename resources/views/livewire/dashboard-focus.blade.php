{{--
    Dashboard, Focus layout (Option A): the same figures as the Classic board (livewire/dashboard.blade.php),
    arranged calmly. A compact top bar, one row of key figures, then Sales, Leaders, Investment and Credit
    aging on the left and the short lists (needs attention, insights, sales mix) on the right; the late
    credit table runs full width at the bottom. Phones get one column, ordered by the fx-o* classes. Every card shows only when the Classic board would show its
    data. Styles: dashboard/focus-style.blade.php; the late-credit table is shared (dashboard/aging-table).
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
    $vsText = $period['mtd'] ? 'vs last month' : 'vs previous period';
    $delta = function (float $now, ?float $last): ?array {
        if ($last === null || $last == 0.0) {
            return null;
        }
        $pct = ($now - $last) / abs($last) * 100;

        return ['up' => $pct >= 0, 'text' => ($pct >= 0 ? '▲ ' : '▼ ').number_format(abs($pct), 0).'%'];
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
    $rl = fn (string $permission, string $route, string $text) => auth()->user()->can($permission) && \Illuminate\Support\Facades\Route::has($route)
        ? '<a href="'.e(route($route)).'">'.e($text).'</a>' : '';
    $salesNow = (float) ($k['totalSalesThisMonth'] ?? 0);
    $profitNow = (float) ($k['grossProfitThisMonth'] ?? 0);
    $margin = $salesNow > 0 ? round($profitNow / $salesNow * 100, 1) : 0;
    $canCreditors = auth()->user()->can('report-audit-creditors-ledger');
    $creditUrl = $canCreditors ? route('reports.creditors-ledger.index', ['filter' => array_filter(['supplier_id' => $scope['supplier_id'], 'has_balance' => 'yes'])]) : null;
    $customerStatement = fn (int $id, ?int $employeeId = null) => $canCreditors
        ? route('reports.creditors-ledger.customer-ledger', array_filter(['customer' => $id, 'filter' => $employeeId ? ['employee_id' => $employeeId] : null]))
        : null;
    $agingReportUrl = $canCreditors ? route('reports.creditors-ledger.aging-report', array_filter(['filter' => array_filter(['supplier_id' => $scope['supplier_id']])])) : null;
    $bucketNames = [1 => '31-60 days', 2 => '61-90 days', 3 => 'Over 90 days'];
    $agingSalesmen = collect($agingCustomers)->pluck('salesmen')->filter()->unique()->sort()->values()->all();
    $includesToday = $period['to'] === now()->toDateString();
    $inv = $investment;
    $invChange = (float) ($inv['change'] ?? 0);
    $keep = array_filter(['supplier' => $scope['can_pick_supplier'] ? request()->query('supplier') : null]);
    $dates = array_filter(['from' => request()->query('from'), 'to' => request()->query('to')]);
    $hourNow = (int) now()->format('G');
    $greeting = $hourNow < 12 ? 'Good morning' : ($hourNow < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->replaceMatches('/^(mr|mrs|ms|miss|dr)\.?\s+/i', '')->trim()->before(' ');

    $hasCredit = $sections['credit'] && isset($k['marketCredit']);
    $hasInvestment = $sections['investment'] && ! empty($inv['parts']);
    $hasStock = $sections['inventory'] && ! empty($topProductsByStockValue['labels'] ?? []);
    $methodTotal = array_sum($salesByPaymentMethod['values'] ?? []);
    $multiSupplier = count($salesBySupplier['labels'] ?? []) > 1;
    $hasDailySales = array_sum($dailySalesTrend['sales'] ?? []) > 0;
    $hasMonthlySales = array_sum($monthlySalesTrend['sales'] ?? []) > 0;
    $dailyByWeek = ($dailySalesTrend['unit'] ?? 'day') === 'week';

    // Period segmented control: three common periods, everything else is "Custom".
    $presetsByName = collect($period['presets'])->keyBy('name');
    $segments = ['This month', 'Last month', 'This year'];
    $isCustomPeriod = ! in_array($period['preset'], $segments, true);
    [$periodName, $periodRange] = array_pad(explode(' · ', $period['label'], 2), 2, null);

    // Key figures, in the Classic order.
    $kpis = [];
    if (isset($k['totalSalesThisMonth'])) {
        $cash = (float) ($k['cashCollectedThisMonth'] ?? 0);
        $creditSales = (float) ($k['creditSalesThisMonth'] ?? 0);
        $kpis[] = ['label' => 'Total sales', 'tone' => 'var(--fx-blue)', 'value' => $rs($salesNow), 'title' => $full($salesNow), 'delta' => $delta($salesNow, $lastMonth['sales'] ?? null), 'hint' => $includesToday ? 'Rs '.$rs($k['salesToday'] ?? 0).' today' : null, 'spark' => $hasDailySales ? 'fx-spark-sales' : null];
        $kpis[] = ['label' => 'Gross profit', 'tone' => 'var(--fx-green)', 'value' => $rs($profitNow), 'title' => $full($profitNow), 'delta' => $delta($profitNow, $lastMonth['profit'] ?? null), 'hint' => $margin.'% margin', 'spark' => $hasDailySales ? 'fx-spark-profit' : null];
        $kpis[] = ['label' => 'Cash received', 'tone' => 'var(--fx-blue)', 'value' => $rs($cash), 'title' => $full($cash), 'delta' => $delta($cash, $lastMonth['cash'] ?? null), 'hint' => ($salesNow > 0 ? round($cash / $salesNow * 100) : 0).'% of sales'];
        $kpis[] = ['label' => 'Credit sales', 'tone' => 'var(--fx-orange)', 'value' => $rs($creditSales), 'title' => $full($creditSales), 'hint' => ($salesNow > 0 ? round($creditSales / $salesNow * 100) : 0).'% of sales · Rs '.$rs($k['recoveriesThisMonth'] ?? 0).' recovered'];
    }
    if ($hasCredit) {
        $kpis[] = ['label' => 'Market credit', 'tone' => 'var(--fx-red)', 'value' => $rs($k['marketCredit']), 'title' => $full($k['marketCredit']), 'href' => $creditUrl, 'hint' => $n($k['creditCustomers'] ?? 0).' customers owe'];
    }
    if ($hasInvestment) {
        $kpis[] = ['label' => 'Investment', 'tone' => 'var(--fx-indigo)', 'value' => $rs($inv['total']), 'title' => $full($inv['total']), 'href' => auth()->user()->can('report-audit-investment-summary') ? route('reports.investment-summary.index') : null, 'delta' => ['up' => $invChange >= 0, 'text' => ($invChange >= 0 ? '▲ ' : '▼ ').'Rs '.$rs(abs($invChange))], 'hint' => 'since '.\Carbon\Carbon::parse($inv['before_on'])->format('d M')];
    }

    // Leaders: one ranked list per tab, only the tabs the Classic board would show.
    $ranked = function (array $labels, array $values, int $take = 8): array {
        $rows = [];
        foreach (array_slice($labels, 0, $take) as $i => $label) {
            $rows[] = ['name' => $label, 'value' => (float) ($values[$i] ?? 0)];
        }

        return $rows;
    };
    $leaders = [];
    if ($sections['sales']) {
        $leaders['products'] = ['tab' => 'Products', 'sub' => 'by sales value', 'tone' => 'var(--fx-blue)', 'empty' => 'No product sales in this period', 'rows' => $ranked($topProductsBySales['labels'] ?? [], $topProductsBySales['values'] ?? [])];
        $leaders['salesmen'] = ['tab' => 'Salesmen', 'sub' => 'by sales', 'tone' => 'var(--fx-blue)', 'empty' => 'No sales in this period', 'rows' => $ranked($topSalespersonBySales['labels'] ?? [], $topSalespersonBySales['values'] ?? [])];
    }
    if ($hasCredit) {
        $leaders['credit'] = ['tab' => 'Credit customers', 'sub' => 'biggest amounts owed', 'tone' => 'var(--fx-orange)', 'empty' => 'No credit outstanding', 'rows' => array_map(fn (array $c) => ['name' => $c['name'], 'note' => $c['city'], 'value' => (float) $c['used'], 'href' => $customerStatement($c['id'])], $topCreditCustomers)];
    }
    if ($hasStock) {
        $leaders['stock'] = ['tab' => 'Stock', 'sub' => 'Rs '.$rs($k['totalInventoryValue'] ?? 0).' in stock · '.$n($k['productsInStock'] ?? 0).' products', 'tone' => 'var(--fx-indigo)', 'empty' => 'No stock', 'rows' => $ranked($topProductsByStockValue['labels'] ?? [], $topProductsByStockValue['values'] ?? [])];
    }
    $firstLeader = array_key_first($leaders);

    // Sales mix: how the sales were paid, and (with more than one company) which company brought them.
    $methodTones = ['Cash' => 'var(--fx-blue)', 'Credit' => 'var(--fx-orange)', 'Bank Transfer' => 'var(--fx-grey)'];
    $methods = [];
    foreach ($salesByPaymentMethod['labels'] ?? [] as $i => $label) {
        $value = (float) ($salesByPaymentMethod['values'][$i] ?? 0);
        if ($value > 0) {
            $methods[] = ['name' => $label, 'value' => $value, 'share' => $methodTotal > 0 ? $value / $methodTotal * 100 : 0, 'tone' => $methodTones[$label] ?? 'var(--fx-grey)'];
        }
    }
    $companies = [];
    if ($multiSupplier) {
        $supplierTotal = array_sum($salesBySupplier['sales']);
        $companyTones = ['#0071e3', '#3a8ef0', '#6aa9f4', '#9cc5f8', '#c6ddfb', '#d1d1d6'];
        foreach (array_slice($salesBySupplier['labels'], 0, 5) as $i => $label) {
            $companies[] = ['name' => $label, 'value' => (float) $salesBySupplier['sales'][$i]];
        }
        $others = array_sum(array_slice($salesBySupplier['sales'], 5));
        if ($others > 0) {
            $companies[] = ['name' => 'Others', 'value' => (float) $others];
        }
        foreach ($companies as $i => $company) {
            $companies[$i]['share'] = $supplierTotal > 0 ? $company['value'] / $supplierTotal * 100 : 0;
            $companies[$i]['tone'] = $companyTones[$i] ?? '#d1d1d6';
        }
    }

    // Investment parts, in the order of the Investment Summary report; the supplier ledger is minus when we owe.
    $invParts = [];
    if ($hasInvestment) {
        foreach ([['stock', 'Stock in hand'], ['credit', 'Market credit'], ['claims', 'Claims'], ['expiry', 'Expiry (AMR)'], ['ledger', 'Supplier ledger']] as [$key, $name]) {
            $invParts[] = ['name' => $name, 'value' => (float) ($inv['parts'][$key] ?? 0)];
        }
    }
    $invMax = max(1, ...array_map(fn (array $p) => abs($p['value']), $invParts ?: [['value' => 1]]));

    $agingTotal = array_sum($creditAging['values'] ?? []);
    $agingTones = ['var(--fx-green)', '#ffcc00', 'var(--fx-orange)', 'var(--fx-red)'];
    $creditOwed = (float) ($k['creditOwed'] ?? 0);
    $overShare = $creditOwed > 0 ? round(($k['creditOverdue'] ?? 0) / $creditOwed * 100) : 0;

    $showMain = $sections['sales'] || $leaders !== [] || $hasCredit || $hasInvestment;
    $showSide = $pendingTotal > 0 || $insights || $sections['sales'];
    $chev = '<svg class="fx-chev" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 4.5 5.5 5.5-5.5 5.5" /></svg>';
@endphp

<div>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700&family=Noto+Nastaliq+Urdu:wght@400;600&display=swap" rel="stylesheet">
    @include('livewire.dashboard.style')
    @include('livewire.dashboard.focus-style')

    <div class="db-page ak-page fx-page" data-dashboard-layout="focus">

        {{-- Top bar: greeting and period on the left; period, supplier and refresh on the right --}}
        <header class="fx-bar" x-data="{ custom: {{ request()->hasAny(['from', 'to']) && ! $period['preset'] ? 'true' : 'false' }} }">
            <div class="fx-bar-title">
                <h1>{{ $greeting }}, {{ $firstName }}</h1>
                <p class="fx-bar-meta">
                    <span><b>{{ $periodName }}</b>{{ $periodRange ? ' · '.$periodRange : '' }}</span>
                    <span class="fx-dot-sep">compared with {{ $period['prev_label'] }}</span>
                    <span class="fx-dot-sep fx-live" title="Figures are kept for {{ intdiv(\App\Livewire\Dashboard::CACHE_SECONDS, 60) }} minutes; Refresh recalculates them"><i aria-hidden="true"></i>Updated {{ $refreshedAt }}</span>
                </p>
                <div class="fx-scope" aria-label="Whose figures these are">
                    <span class="fx-tag">{{ $scope['is_super_admin'] ? 'Super admin view' : ($scope['is_admin'] ? 'Admin view' : 'Your view') }}</span>
                    @unless ($scope['can_pick_supplier'] && $supplierOptions)
                        <span class="fx-tag {{ $scope['supplier_id'] ? 'is-limited' : '' }}">Supplier: {{ $scope['supplier'] }}</span>
                    @endunless
                    @if ($sections['sales'])<span class="fx-tag {{ $scope['own_settlements'] ? 'is-limited' : '' }}">Settlements: {{ $ownNote($scope['own_settlements']) }}</span>@endif
                    @if ($sections['distribution'])<span class="fx-tag {{ $scope['own_issues'] ? 'is-limited' : '' }}">Goods issues: {{ $ownNote($scope['own_issues']) }}</span>@endif
                </div>
            </div>

            <div class="fx-bar-actions">
                <nav class="fx-seg" aria-label="Period">
                    @foreach ($segments as $segment)
                        @php $preset = $presetsByName[$segment]; @endphp
                        <a href="{{ route('dashboard', array_merge($keep, ['from' => $preset['from'], 'to' => $preset['to']])) }}" class="{{ $period['preset'] === $segment ? 'is-on' : '' }}" @if ($period['preset'] === $segment) aria-current="page" @endif>{{ $segment }}</a>
                    @endforeach
                    <button type="button" class="{{ $isCustomPeriod ? 'is-on' : '' }}" @click="custom = ! custom" :aria-expanded="custom" aria-controls="fx-custom-period">Custom</button>
                </nav>
                @if ($scope['can_pick_supplier'] && $supplierOptions)
                    <label class="fx-select" title="Show the whole dashboard for one supplier">
                        <span class="sr-only">Supplier</span>
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.38c0-.62.5-1.12 1.13-1.12h3.75c.62 0 1.12.5 1.12 1.13V21" /></svg>
                        <select onchange="window.location = this.value">
                            <option value="{{ route('dashboard', $dates) }}">All suppliers</option>
                            @foreach ($supplierOptions as $sid => $sname)
                                <option value="{{ route('dashboard', array_merge(['supplier' => $sid], $dates)) }}" @selected($scope['supplier_id'] === $sid)>{{ $sname }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <a class="fx-btn" href="{{ route('dashboard', array_merge($keep, $dates, ['refresh' => 1])) }}" title="Work the figures out again now">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    Refresh
                </a>
            </div>

            <form method="GET" action="{{ route('dashboard') }}" id="fx-custom-period" class="fx-custom" x-show="custom" x-cloak aria-label="Choose the period">
                <div class="fx-custom-label">Periods</div>
                <div class="fx-chips">
                    @foreach ($period['presets'] as $preset)
                        <a class="fx-chip {{ $period['preset'] === $preset['name'] ? 'is-on' : '' }}" href="{{ route('dashboard', array_merge($keep, ['from' => $preset['from'], 'to' => $preset['to']])) }}">{{ $preset['name'] }}</a>
                    @endforeach
                </div>
                <div class="fx-custom-label">Custom dates</div>
                <div class="fx-range">
                    @foreach ($keep as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                    <label for="fx-from" class="sr-only">From</label><input id="fx-from" type="date" name="from" value="{{ $period['from'] }}" max="{{ now()->toDateString() }}">
                    <span>to</span>
                    <label for="fx-to" class="sr-only">To</label><input id="fx-to" type="date" name="to" value="{{ $period['to'] }}" max="{{ now()->toDateString() }}">
                    <button type="submit" class="fx-btn fx-btn-primary">Show</button>
                    <a class="fx-btn" href="{{ route('dashboard', $keep) }}">This month</a>
                </div>
            </form>
        </header>

        @unless ($hasAnySection)
            <div class="fx-card">
                <div class="fx-empty">
                    <b>Nothing to show yet</b>
                    No module is enabled for your account. Ask the administrator to give you a role on Settings &rarr; Users.
                    @can('ticket-list')
                        <div style="margin-top:12px"><a href="{{ route('tickets.index') }}" class="fx-btn">Open Tickets</a></div>
                    @endcan
                </div>
            </div>
        @endunless

        {{-- ============================== Key figures ============================== --}}
        @if ($kpis)
            <section class="fx-kpis {{ count($kpis) === 6 ? 'is-six' : '' }}" aria-label="Key figures">
                @foreach ($kpis as $kpi)
                    @php $tag = ! empty($kpi['href']) ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if ($tag === 'a') href="{{ $kpi['href'] }}" @endif class="fx-kpi" style="--tone: {{ $kpi['tone'] }}" title="{{ $kpi['title'] }}">
                        <span class="fx-kpi-label"><i aria-hidden="true"></i>{{ $kpi['label'] }}</span>
                        <span class="fx-kpi-value"><small>Rs</small>{{ $kpi['value'] }}</span>
                        <span class="fx-kpi-foot">
                            @if (! empty($kpi['delta']))<span class="fx-chipd {{ $kpi['delta']['up'] ? 'up' : 'down' }}" title="{{ $kpi['label'] === 'Investment' ? 'change in the period' : $vsText }}">{{ $kpi['delta']['text'] }}</span>@endif
                            @if (! empty($kpi['hint']))<span>{{ $kpi['hint'] }}</span>@endif
                        </span>
                        @if (! empty($kpi['spark']))<span id="{{ $kpi['spark'] }}" class="fx-spark" aria-hidden="true"></span>@endif
                    </{{ $tag }}>
                @endforeach
            </section>
        @endif

        {{-- ============================== Main grid ============================== --}}
        @if ($showMain || $showSide)
            <div class="fx-grid {{ $showMain && $showSide ? '' : 'is-single' }}">
                <div class="fx-col fx-col-main">
                    {{-- Sales: sales and gross profit, day by day or month by month --}}
                    @if ($sections['sales'])
                        <section class="fx-card fx-o2" aria-label="Sales" data-fx-card="sales">
                            <div class="fx-card-head">
                                <div>
                                    <h2>Sales</h2>
                                    <p class="fx-sub fx-num">Rs {{ $rs($salesNow) }} sold · Rs {{ $rs($profitNow) }} gross profit · {{ $margin }}% margin</p>
                                </div>
                                @if ($hasDailySales && $hasMonthlySales)
                                    <div class="fx-seg fx-seg-sm" role="group" aria-label="Show sales by">
                                        <button type="button" data-fx-mode="daily" aria-pressed="true">{{ $dailyByWeek ? 'Week' : 'Day' }}</button>
                                        <button type="button" data-fx-mode="monthly" aria-pressed="false">Month</button>
                                    </div>
                                @endif
                            </div>
                            @if ($hasDailySales || $hasMonthlySales)
                                <div id="fx-chart-sales" class="fx-chart" role="img" aria-label="Sales and gross profit {{ $hasDailySales ? ($dailyByWeek ? 'per week' : 'per day') : 'per month' }}"></div>
                            @else
                                <div class="fx-empty"><b>No sales in this period</b>Posted settlements show here.</div>
                            @endif
                            @php $income = $rl('report-financial-income-statement', 'reports.income-statement.index', 'Income statement'); @endphp
                            @if ($income)<p class="fx-card-head" style="justify-content:flex-end">{!! $income !!}</p>@endif
                        </section>
                    @endif

                    {{-- Leaders: one ranked list at a time --}}
                    @if ($leaders)
                        <section class="fx-card fx-o3" aria-label="Leaders" x-data="{ tab: '{{ $firstLeader }}' }" data-fx-card="leaders">
                            <div class="fx-card-head">
                                <div>
                                    <h2>Leaders</h2>
                                    @foreach ($leaders as $key => $leader)
                                        <p class="fx-sub" x-show="tab === '{{ $key }}'" @if ($key !== $firstLeader) x-cloak @endif>{{ $leader['sub'] }}{{ in_array($key, ['products', 'salesmen'], true) ? ', '.$periodName : '' }}</p>
                                    @endforeach
                                </div>
                                @if (count($leaders) > 1)
                                    <div class="fx-seg fx-seg-sm" role="tablist" aria-label="Leaders by">
                                        @foreach ($leaders as $key => $leader)
                                            <button type="button" role="tab" id="fx-tab-{{ $key }}" aria-controls="fx-panel-{{ $key }}" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'" aria-selected="{{ $key === $firstLeader ? 'true' : 'false' }}">{{ $leader['tab'] }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            @foreach ($leaders as $key => $leader)
                                @php $max = max(1, ...array_map(fn ($r) => abs($r['value']), $leader['rows'] ?: [['value' => 1]])); @endphp
                                <div id="fx-panel-{{ $key }}" role="tabpanel" aria-labelledby="fx-tab-{{ $key }}" x-show="tab === '{{ $key }}'" @if ($key !== $firstLeader) x-cloak @endif style="--tone: {{ $leader['tone'] }}">
                                    @if ($leader['rows'])
                                        <ol class="fx-rank">
                                            @foreach ($leader['rows'] as $row)
                                                <li>
                                                    <span class="fx-rank-n">{{ $loop->iteration }}</span>
                                                    <span class="fx-rank-name" title="{{ $row['name'] }}">
                                                        @if (! empty($row['href']))<a href="{{ $row['href'] }}">{{ $row['name'] }}</a>@else{{ $row['name'] }}@endif
                                                        @if (! empty($row['note']))<span>{{ $row['note'] }}</span>@endif
                                                    </span>
                                                    <span class="fx-rank-v" title="{{ $full($row['value']) }}">Rs {{ $rs($row['value']) }}</span>
                                                    <span class="fx-bar-track" aria-hidden="true"><i style="width: {{ round(abs($row['value']) / $max * 100, 1) }}%"></i></span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    @else
                                        <div class="fx-empty"><b>{{ $leader['empty'] }}</b></div>
                                    @endif
                                    @if ($key === 'stock')
                                        <p style="margin-top:12px"><a class="fx-link" href="{{ route('inventory.current-stock.index') }}">Current stock</a></p>
                                    @elseif ($key === 'credit' && $creditUrl)
                                        <p style="margin-top:12px"><a class="fx-link" href="{{ $creditUrl }}">Creditors ledger</a></p>
                                    @endif
                                </div>
                            @endforeach
                        </section>
                    @endif

                    {{-- Credit aging: one stacked bar, four buckets. Credit aging and Investment sit under Leaders on wide screens, which keeps the two columns about even. --}}
                    @if ($hasCredit)
                        <section class="fx-card fx-o7" aria-label="Credit aging" x-data>
                            <div class="fx-card-head"><div><h2>Credit aging</h2><p class="fx-sub">what customers owe, by age of the unpaid sale</p></div>@if ($creditUrl)<a href="{{ $creditUrl }}">Creditors ledger</a>@endif</div>
                            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
                                <span class="fx-big" title="{{ $full($creditOwed) }}"><small>Rs</small>{{ $rs($creditOwed) }}</span>
                                <span class="fx-chipd {{ ($k['creditOverdue'] ?? 0) > 0 ? 'warn' : 'up' }}">{{ $overShare }}% over 60 days</span>
                            </div>
                            @if ($agingTotal > 0)
                                <div class="fx-stack" role="img" aria-label="Credit owed by age">
                                    @foreach ($creditAging['values'] as $i => $value)
                                        @if ($value > 0)<i style="--tone: {{ $agingTones[$i] }}; flex: {{ round($value / $agingTotal * 100, 2) }} 1 0"></i>@endif
                                    @endforeach
                                </div>
                                <ul class="fx-legend">
                                    @foreach ($creditAging['values'] as $i => $value)
                                        <li>
                                            @if ($i > 0 && $agingCustomers)
                                                <button type="button" class="fx-leg" style="--tone: {{ $agingTones[$i] }}" @click="$dispatch('aging-bucket', {{ $i }})" title="Show these customers in the late credit list">
                                            @else
                                                <div class="fx-leg" style="--tone: {{ $agingTones[$i] }}">
                                            @endif
                                                <i aria-hidden="true"></i>
                                                <span>{{ $creditAging['labels'][$i] ?? '' }}<small>{{ $n($creditAging['counts'][$i] ?? 0) }}</small></span>
                                                <b title="{{ $full($value) }}">Rs {{ $rs($value) }}</b>
                                                <em>{{ round($value / $agingTotal * 100) }}%</em>
                                            @if ($i > 0 && $agingCustomers)</button>@else</div>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="fx-empty"><b>No credit outstanding</b></div>
                            @endif
                        </section>
                    @endif

                    {{-- Investment: the parts, with the change over the period --}}
                    @if ($hasInvestment)
                        <section class="fx-card fx-o6" aria-label="Investment">
                            <div class="fx-card-head"><div><h2>Investment</h2><p class="fx-sub">as of {{ \Carbon\Carbon::parse($inv['as_of'])->format('d M Y') }}</p></div>{!! $rl('report-audit-investment-summary', 'reports.investment-summary.index', 'Summary') !!}</div>
                            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
                                <span class="fx-big" title="{{ $full($inv['total']) }}"><small>Rs</small>{{ $rs($inv['total']) }}</span>
                                <span class="fx-chipd {{ $invChange >= 0 ? 'up' : 'down' }}">{{ $invChange >= 0 ? '▲' : '▼' }} Rs {{ $rs(abs($invChange)) }} since {{ \Carbon\Carbon::parse($inv['before_on'])->format('d M') }}</span>
                            </div>
                            <div class="fx-inv-list" style="--tone: var(--fx-indigo)">
                                @foreach ($invParts as $part)
                                    <div class="fx-inv">
                                        <span>{{ $part['name'] }}</span>
                                        <b class="{{ $part['value'] < 0 ? 'neg' : '' }}" title="{{ $full($part['value']) }}">Rs {{ $rs($part['value']) }}</b>
                                        <span class="fx-bar-track" aria-hidden="true"><i style="width: {{ round(abs($part['value']) / $invMax * 100, 1) }}%; {{ $part['value'] < 0 ? 'background: var(--fx-red)' : '' }}"></i></span>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>

                <div class="fx-col fx-col-side">
                    {{-- Needs attention: drafts and tickets waiting --}}
                    @if ($pendingTotal > 0)
                        <section class="fx-card fx-o1" aria-label="Needs attention" role="status">
                            <div class="fx-card-head"><div><h2>Needs attention</h2><p class="fx-sub">{{ $pendingTotal }} {{ \Illuminate\Support\Str::plural('item', $pendingTotal) }} waiting</p></div></div>
                            <ul class="fx-list">
                                @foreach ($pendingLinks as $key => [$label, $route])
                                    @if (($pendingItems[$key] ?? 0) > 0)
                                        <li><a class="fx-row" href="{{ $draftLinks[$key] ?? route($route) }}"><span>{{ $label }}</span><span class="fx-count">{{ $pendingItems[$key] }}</span>{!! $chev !!}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Insights: what the numbers say --}}
                    @if ($insights)
                        <section class="fx-card fx-o4" aria-label="Insights">
                            <div class="fx-card-head"><div><h2>Insights</h2><p class="fx-sub">what the numbers say</p></div></div>
                            <ul class="fx-list">
                                @foreach ($insights as $insight)
                                    <li class="fx-insight" style="--tone: {{ $insight['tone'] }}">
                                        <i aria-hidden="true"></i>
                                        <h3>{{ $insight['title'] }}</h3>
                                        <b>{{ $insight['value'] }}</b>
                                        <p>{{ $insight['text'] }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Sales mix: payment split and company share --}}
                    @if ($sections['sales'])
                        <section class="fx-card fx-o5" aria-label="Sales mix">
                            <div class="fx-card-head"><div><h2>Sales mix</h2><p class="fx-sub">how the sales were paid{{ $companies ? ', and who brought them' : '' }}</p></div>{!! $rl('report-sales-daily-sales', 'reports.daily-sales.index', 'Daily sales') !!}</div>
                            @if ($methods)
                                <div class="fx-split">
                                    <h3>Payment</h3>
                                    <div class="fx-stack" role="img" aria-label="Sales split into cash, credit and bank transfer">
                                        @foreach ($methods as $m)<i style="--tone: {{ $m['tone'] }}; flex: {{ round($m['share'], 2) }} 1 0"></i>@endforeach
                                    </div>
                                    <ul class="fx-legend">
                                        @foreach ($methods as $m)
                                            <li class="fx-leg" style="--tone: {{ $m['tone'] }}"><i aria-hidden="true"></i><span>{{ $m['name'] }}</span><b title="{{ $full($m['value']) }}">Rs {{ $rs($m['value']) }}</b><em>{{ round($m['share']) }}%</em></li>
                                        @endforeach
                                    </ul>
                                </div>
                                @if ($companies)
                                    <div class="fx-split">
                                        <h3>Company share</h3>
                                        <div class="fx-stack" role="img" aria-label="Sales share by company">
                                            @foreach ($companies as $c)<i style="--tone: {{ $c['tone'] }}; flex: {{ round($c['share'], 2) }} 1 0"></i>@endforeach
                                        </div>
                                        <ul class="fx-legend">
                                            @foreach ($companies as $c)
                                                <li class="fx-leg" style="--tone: {{ $c['tone'] }}"><i aria-hidden="true"></i><span title="{{ $c['name'] }}">{{ $c['name'] }}</span><b title="{{ $full($c['value']) }}">Rs {{ $rs($c['value']) }}</b><em>{{ round($c['share']) }}%</em></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @else
                                <div class="fx-empty"><b>No sales in this period</b></div>
                            @endif
                        </section>
                    @endif


                </div>
            </div>
        @endif

        {{-- ============================== Late credit: aging creditors with filters ============================== --}}
        @include('livewire.dashboard.aging-table')
    </div>

    @php
        $chartData = [
            'daily' => $sections['sales'] && $hasDailySales ? $dailySalesTrend : null,
            'monthly' => $sections['sales'] && $hasMonthlySales ? $monthlySalesTrend : null,
        ];
    @endphp

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof ApexCharts === 'undefined') { return; }
                const data = {{ \Illuminate\Support\Js::from($chartData) }};
                const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                const ink = '#86868b', grid = '#ececf0';
                const short = v => { const a = Math.abs(v), s = v < 0 ? '-' : ''; return a >= 1e7 ? s + (a / 1e7).toFixed(1) + ' Cr' : a >= 1e5 ? s + (a / 1e5).toFixed(1) + ' Lac' : a >= 1e3 ? s + (a / 1e3).toFixed(0) + 'k' : s + Math.round(a); };
                const rupees = v => 'Rs ' + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
                const font = "Inter, 'Noto Nastaliq Urdu', ui-sans-serif, system-ui, sans-serif";

                const spark = (id, series, color) => {
                    const el = document.getElementById(id);
                    if (!el) { return; }
                    new ApexCharts(el, {
                        chart: { type: 'area', height: 30, sparkline: { enabled: true }, fontFamily: font, animations: { enabled: false } },
                        series: [{ name: 'Daily', data: series }], colors: [color],
                        stroke: { curve: 'monotoneCubic', width: 1.5 },
                        fill: { type: 'gradient', gradient: { shadeIntensity: 0, opacityFrom: 0.16, opacityTo: 0, stops: [0, 100] } },
                        tooltip: { fixed: { enabled: false }, x: { show: false }, y: { formatter: rupees }, marker: { show: false } },
                    }).render();
                };
                if (data.daily) {
                    spark('fx-spark-sales', data.daily.sales, '#0071e3');
                    spark('fx-spark-profit', data.daily.profit || [], '#34c759');
                }

                const el = document.getElementById('fx-chart-sales');
                if (!el || (!data.daily && !data.monthly)) { return; }
                const seriesFor = mode => mode === 'daily'
                    ? { labels: data.daily.labels, sales: data.daily.sales, profit: data.daily.profit || [] }
                    : { labels: data.monthly.labels.map(l => l.slice(0, 3) + ' ’' + l.slice(-2)), sales: data.monthly.sales, profit: data.monthly.profits };
                let mode = data.daily ? 'daily' : 'monthly';
                const s = seriesFor(mode);
                const ticks = n => Math.min(n, window.innerWidth < 640 ? 5 : 10);
                const chart = new ApexCharts(el, {
                    chart: { type: 'area', height: 280, fontFamily: font, toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: !reduce, easing: 'easeout', speed: 320, animateGradually: { enabled: false }, dynamicAnimation: { enabled: !reduce, speed: 320 } } },
                    series: [{ name: 'Sales', data: s.sales }, { name: 'Gross profit', data: s.profit }],
                    colors: ['#0071e3', '#34c759'],
                    stroke: { curve: 'monotoneCubic', width: [2.25, 2] },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 0, opacityFrom: [0.2, 0.12], opacityTo: [0.01, 0], stops: [0, 100] } },
                    dataLabels: { enabled: false },
                    markers: { size: 0, hover: { size: 4 } },
                    grid: { borderColor: grid, strokeDashArray: 0, xaxis: { lines: { show: false } }, padding: { left: 8, right: 8, top: 0, bottom: 0 } },
                    legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', fontWeight: 500, labels: { colors: '#6e6e73' }, markers: { size: 5 }, itemMargin: { horizontal: 10 } },
                    xaxis: { categories: s.labels, tickAmount: ticks(s.labels.length), labels: { rotate: 0, hideOverlappingLabels: true, style: { colors: ink, fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
                    yaxis: { labels: { style: { colors: ink, fontSize: '11px' }, formatter: short } },
                    tooltip: { shared: true, intersect: false, y: { formatter: rupees } },
                    states: { active: { filter: { type: 'none' } } },
                    responsive: [{ breakpoint: 640, options: { chart: { height: 230 }, yaxis: { labels: { style: { colors: ink, fontSize: '10px' }, formatter: short } } } }],
                });
                chart.render();

                document.querySelectorAll('[data-fx-mode]').forEach(btn => btn.addEventListener('click', () => {
                    if (btn.dataset.fxMode === mode) { return; }
                    mode = btn.dataset.fxMode;
                    document.querySelectorAll('[data-fx-mode]').forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
                    const next = seriesFor(mode);
                    el.setAttribute('aria-label', 'Sales and gross profit ' + (mode === 'daily' ? '{{ $dailyByWeek ? 'per week' : 'per day' }}' : 'per month'));
                    chart.updateOptions({
                        xaxis: { categories: next.labels, tickAmount: ticks(next.labels.length) },
                        series: [{ name: 'Sales', data: next.sales }, { name: 'Gross profit', data: next.profit }],
                    });
                }));
            });
        </script>
    @endpush
</div>
