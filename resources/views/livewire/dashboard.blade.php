{{--
    Dashboard (same structure as the Nexus dashboard): one section per module the
    user can open, each with KPI cards, charts and a short list. Every figure is
    already limited by App\Livewire\Dashboard to the user's supplier and, without
    "...-view-all", to the records they created. Super admins also get a company
    overview. Styles: settings.partials.ui-style (ak-) + db- below; charts: ApexCharts.
--}}
@php
    $k = $kpiCards;
    $n = fn ($v) => number_format((float) $v);
    // Short money for tiles: 1.25 Cr / 45.2 Lac / 12,500 (exact figure in the title).
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
    // "▲ 12% vs last month" (null when there is nothing to compare with).
    $delta = function (float $now, ?float $last): ?array {
        if ($last === null || $last == 0.0) {
            return null;
        }
        $pct = ($now - $last) / abs($last) * 100;

        return ['up' => $pct >= 0, 'text' => ($pct >= 0 ? '▲ ' : '▼ ').number_format(abs($pct), 0).'% vs last month'];
    };
    $ownNote = fn (bool $own) => $own ? 'your own entries' : 'all users';
    $pendingTotal = collect($pendingItems)->sum();
    $pendingLinks = [
        'draftSettlements' => ['Draft Settlements', 'sales-settlements.index'],
        'draftGoodsIssues' => ['Draft Goods Issues', 'goods-issues.index'],
        'draftGrns' => ['Draft GRNs', 'goods-receipt-notes.index'],
        'draftPayments' => ['Draft Payments', 'supplier-payments.index'],
        'draftJournalEntries' => ['Draft Journal Entries', 'journal-entries.index'],
    ];
    $hasAnySection = collect($sections)->contains(true);
    $margin = ($k['totalSalesThisMonth'] ?? 0) > 0 ? round(($k['grossProfitThisMonth'] ?? 0) / $k['totalSalesThisMonth'] * 100, 1) : 0;
@endphp

<div>
    <style>
        .db-page { --s1: #2a78d6; --s2: #eb6834; --s3: #1baf7a; --s4: #eda100; --s5: #e87ba4; --s6: #7c5cd6; }
        .db-scope { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 13px; color: var(--ak-muted); }
        .db-section { scroll-margin-top: 16px; }
        .db-section-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid var(--ak-line); }
        .db-section-head h2 { margin: 0; font-size: 19px; font-weight: 700; color: var(--ak-text); }
        .db-section-head p { margin: 3px 0 0; font-size: 13px; color: var(--ak-muted); }
        .db-links { display: flex; flex-wrap: wrap; gap: 8px; }
        .db-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); margin-top: 16px; }
        @media (min-width: 1024px) {
            .db-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
            .db-grid.db-even { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        .db-card { background: #fff; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); box-shadow: var(--ak-shadow); padding: 16px 18px; min-width: 0; }
        .db-card h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--ak-text); }
        .db-card-sub { margin: 2px 0 0; font-size: 12px; color: var(--ak-muted); }
        .db-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
        .db-card-head a { font-size: 12px; font-weight: 600; color: var(--ak-navy); white-space: nowrap; text-decoration: none; }
        .db-card-head a:hover { text-decoration: underline; }
        .db-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; min-height: 160px; text-align: center; color: var(--ak-muted); font-size: 13px; }
        .db-empty b { color: var(--ak-text); font-size: 14px; }
        .db-list { list-style: none; margin: 0; padding: 0; }
        .db-list li + li { border-top: 1px solid var(--ak-line); }
        .db-list a, .db-list .db-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 2px; text-decoration: none; color: var(--ak-text); }
        .db-list a:hover { background: var(--ak-soft); }
        .db-l1 { display: block; font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .db-l2 { display: block; font-size: 12px; color: var(--ak-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .db-r { text-align: right; flex: none; font-size: 12px; color: var(--ak-muted); }
        .db-r b { display: block; font-size: 13px; color: var(--ak-text); font-variant-numeric: tabular-nums; }
        .db-badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .db-badge.posted { background: var(--ak-green-bg); color: #166534; }
        .db-badge.draft { background: var(--ak-amber-bg); color: #92400e; }
        .db-badge.other { background: #e2e8f0; color: #334155; }
        .db-meter { height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-top: 6px; }
        .db-meter > span { display: block; height: 100%; border-radius: 999px; background: var(--s1); }
        .db-meter > span.warn { background: #b45309; }
        .db-meter > span.over { background: #b91c1c; }
        .db-up { color: #15803d !important; font-weight: 600; }
        .db-down { color: #b91c1c !important; font-weight: 600; }
        .db-pending { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 14px 18px; border: 1px solid #fcd34d; border-radius: var(--ak-radius); background: var(--ak-amber-bg); }
        .db-pending h3 { margin: 0 8px 0 0; font-size: 14px; font-weight: 700; color: #78350f; }
        .db-pending a { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 8px; background: #fff; border: 1px solid #fde68a; font-size: 13px; font-weight: 600; color: #78350f; text-decoration: none; }
        .db-pending a:hover { border-color: #b45309; }
        .db-pending a b { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: #b45309; color: #fff; font-size: 12px; }
        .db-actions { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (min-width: 768px) { .db-actions { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
        .db-action { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 14px 8px; border: 1px solid var(--ak-border); border-radius: 10px; background: #fff; font-size: 12.5px; font-weight: 600; color: var(--ak-text); text-decoration: none; text-align: center; }
        .db-action:hover { border-color: var(--ak-navy); background: var(--ak-navy-tint); color: var(--ak-navy); }
        .db-action svg { width: 20px; height: 20px; color: var(--ak-navy); }
        .db-chart { min-height: 290px; }
        .db-chart-sm { min-height: 240px; }
        .db-stack > * + * { margin-top: 16px; }
        .db-aging-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .db-aging-chips button { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; background: #fff; font-size: 12px; font-weight: 600; color: var(--ak-text); cursor: pointer; }
        .db-aging-chips button b { color: #b45309; }
        .db-aging-chips button:hover, .db-aging-chips button.is-on { border-color: var(--ak-navy); background: var(--ak-navy-tint); color: var(--ak-navy); }
        .db-toc { display: flex; flex-wrap: wrap; gap: 6px; }
        .db-toc a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; background: #fff; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; }
        .db-toc a:hover { border-color: var(--ak-navy); color: var(--ak-navy); }
    </style>

    <div class="db-page ak-page" style="gap:28px">
        {{-- What am I looking at --}}
        <div class="db-scope" style="justify-content:space-between">
            <div class="db-scope">
                <span class="ak-scope {{ $scope['supplier_id'] ? 'ak-scope-limited' : '' }}" title="Supplier data you can see">Supplier: {{ $scope['supplier'] }}</span>
                @if ($sections['sales'])
                    <span class="ak-scope {{ $scope['own_settlements'] ? 'ak-scope-limited' : '' }}">Settlements: {{ $ownNote($scope['own_settlements']) }}</span>
                @endif
                @if ($sections['distribution'])
                    <span class="ak-scope {{ $scope['own_issues'] ? 'ak-scope-limited' : '' }}">Goods issues: {{ $ownNote($scope['own_issues']) }}</span>
                @endif
                @if ($scope['is_super_admin'] || $scope['is_admin'])
                    <span class="ak-scope" style="background:#fee2e2; border-color:#fca5a5; color:#991b1b">{{ $scope['is_super_admin'] ? 'Super admin' : 'Admin' }} view</span>
                @endif
            </div>
            <nav class="db-toc" aria-label="Jump to section">
                @if ($sections['company'])<a href="#db-company">Company</a>@endif
                @if ($sections['credit'])<a href="#db-credit">Credit</a>@endif
                @if ($sections['sales'])<a href="#db-sales">Sales</a>@endif
                @if ($sections['distribution'])<a href="#db-distribution">Distribution</a>@endif
                @if ($sections['purchases'])<a href="#db-purchases">Purchases</a>@endif
                @if ($sections['inventory'])<a href="#db-inventory">Inventory</a>@endif
                @if ($sections['accounting'])<a href="#db-accounting">Accounting</a>@endif
            </nav>
        </div>

        {{-- Pending Actions --}}
        @if ($pendingTotal > 0)
            <div class="db-pending" role="status">
                <h3>Pending Actions <span class="ak-count ak-count-dark">{{ $pendingTotal }}</span></h3>
                @foreach ($pendingLinks as $key => [$label, $route])
                    @if (($pendingItems[$key] ?? 0) > 0)
                        <a href="{{ $draftLinks[$key] ?? route($route) }}"><b>{{ $pendingItems[$key] }}</b> {{ $label }}</a>
                    @endif
                @endforeach
            </div>
        @endif

        @unless ($hasAnySection)
            <div class="db-card">
                <div class="db-empty">
                    <b>Nothing to show yet</b>
                    <span>No module is enabled for your account. Ask the administrator to give you a role on Settings &rarr; Users.</span>
                </div>
            </div>
        @endunless

        {{-- ============================== Key figures (top) ============================== --}}
        @php
            $owedTop = isset($k['outstandingPayables']) ? (float) $k['outstandingPayables'] : null;
            $creditUrl = auth()->user()->can('report-audit-creditors-ledger') ? route('reports.creditors-ledger.index', array_filter(['filter' => array_filter(['supplier_id' => $scope['supplier_id']])])) : null;
        @endphp
        @if (isset($k['marketCredit']) || isset($k['totalInventoryValue']) || isset($k['totalSalesThisMonth']) || $owedTop !== null)
            <section aria-label="Key figures">
                <div class="ak-kpis">
                    @isset($k['marketCredit'])
                        <a href="{{ $creditUrl ?? '#db-credit' }}" class="ak-kpi ak-kpi-action" title="{{ $full($k['marketCredit']) }}">
                            <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg></span>
                            <span class="ak-kpi-body">
                                <span class="ak-kpi-label">Market credit (customers owe)</span>
                                <span class="ak-kpi-value">Rs {{ $rs($k['marketCredit']) }}</span>
                                <span class="ak-kpi-hint">{{ $n($k['creditCustomers'] ?? 0) }} customers &middot; Creditors ledger →</span>
                            </span>
                        </a>
                    @endisset
                    @isset($k['totalInventoryValue'])
                        <a href="{{ route('inventory.current-stock.index') }}" class="ak-kpi" title="{{ $full($k['totalInventoryValue']) }}">
                            <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg></span>
                            <span class="ak-kpi-body">
                                <span class="ak-kpi-label">Inventory value (now)</span>
                                <span class="ak-kpi-value">Rs {{ $rs($k['totalInventoryValue']) }}</span>
                                <span class="ak-kpi-hint">{{ $n($k['productsInStock'] ?? 0) }} products in stock</span>
                            </span>
                        </a>
                    @endisset
                    @isset($k['totalSalesThisMonth'])
                        <a href="#db-sales" class="ak-kpi" title="{{ $full($k['totalSalesThisMonth']) }}">
                            <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.3 4.3a11.95 11.95 0 0 1 5.8-5.8l2.65-1.2m0 0-5.94-2.28m5.94 2.28-2.28 5.94" /></svg></span>
                            <span class="ak-kpi-body">
                                <span class="ak-kpi-label">Sales this month</span>
                                <span class="ak-kpi-value">Rs {{ $rs($k['totalSalesThisMonth']) }}</span>
                                <span class="ak-kpi-hint">Rs {{ $rs($k['grossProfitThisMonth'] ?? 0) }} gross profit</span>
                            </span>
                        </a>
                    @endisset
                    @if ($owedTop !== null)
                        <a href="#db-purchases" class="ak-kpi" title="{{ $full(abs($owedTop)) }}">
                            <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.35m-16.5 11.65V9.35m0 0a3 3 0 0 0 3.75-.62 3 3 0 0 0 4.5 0 3 3 0 0 0 4.5 0 3 3 0 0 0 3.75.62m-16.5 0a3 3 0 0 1-.62-4.72L4.2 3.44A1.5 1.5 0 0 1 5.26 3h13.48a1.5 1.5 0 0 1 1.06.44l1.19 1.19a3 3 0 0 1-.62 4.72" /></svg></span>
                            <span class="ak-kpi-body">
                                <span class="ak-kpi-label">{{ $owedTop > 0 ? 'Payable to suppliers' : 'Advance with suppliers' }}</span>
                                <span class="ak-kpi-value">Rs {{ $rs(abs($owedTop)) }}</span>
                                <span class="ak-kpi-hint">supplier ledger balance</span>
                            </span>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        {{-- ============================== Company (super admin & admin) ============================== --}}
        @if ($sections['company'] && $companyOverview)
            @php $c = $companyOverview; @endphp
            <section class="db-section" id="db-company" aria-labelledby="db-company-h">
                <div class="db-section-head">
                    <div>
                        <h2 id="db-company-h">Company overview</h2>
                        <p>Everything across all suppliers, users and fleet</p>
                    </div>
                    <div class="db-links">
                        @can('user-list')<a href="{{ route('users.index') }}" class="ak-btn ak-btn-outline">Users</a>@endcan
                        @can('view-any-report')<a href="{{ route('reports.index') }}" class="ak-btn ak-btn-outline">Reports</a>@endcan
                    </div>
                </div>
                <div class="ak-kpis">
                    @can('user-list')
                        <a href="{{ route('users.index') }}" class="ak-kpi{{ $c['users_no_access'] ? ' ak-kpi-action' : '' }}">
                            <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.13a9.38 9.38 0 0 0 2.63.37 9.34 9.34 0 0 0 4.12-.95 4.13 4.13 0 0 0-7.53-2.49M15 19.13v-.01a6.37 6.37 0 0 0-.97-3.4M15 19.13v.1A12.32 12.32 0 0 1 8.62 21a12.32 12.32 0 0 1-6.37-1.77v-.11a6.38 6.38 0 0 1 11.96-3.4M12 6.38a3.38 3.38 0 1 1-6.75 0 3.38 3.38 0 0 1 6.75 0Zm8.25 2.25a2.63 2.63 0 1 1-5.25 0 2.63 2.63 0 0 1 5.25 0Z" /></svg></span>
                            <span class="ak-kpi-body">
                                <span class="ak-kpi-label">Active users</span>
                                <span class="ak-kpi-value">{{ $n($c['users_active']) }} <small style="font-size:13px; color:var(--ak-muted)">/ {{ $n($c['users_total']) }}</small></span>
                                <span class="ak-kpi-hint">{{ $c['users_no_access'] ? $c['users_no_access'].' without any access →' : 'everyone has access set' }}</span>
                            </span>
                        </a>
                    @endcan
                    <div class="ak-kpi">
                        <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.35m-16.5 11.65V9.35m0 0a3 3 0 0 0 3.75-.62 3 3 0 0 0 4.5 0 3 3 0 0 0 4.5 0 3 3 0 0 0 3.75.62m-16.5 0a3 3 0 0 1-.62-4.72L4.2 3.44A1.5 1.5 0 0 1 5.26 3h13.48a1.5 1.5 0 0 1 1.06.44l1.19 1.19a3 3 0 0 1-.62 4.72" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Suppliers</span>
                            <span class="ak-kpi-value">{{ $n($c['suppliers']) }}</span>
                            <span class="ak-kpi-hint">{{ $n($c['customers']) }} active customers</span>
                        </span>
                    </div>
                    <div class="ak-kpi">
                        <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.38a1.13 1.13 0 0 1-1.13-1.13V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.13c.62 0 1.13-.5 1.13-1.13v-5.13a1.5 1.5 0 0 0-.44-1.06l-3.87-3.87a1.5 1.5 0 0 0-1.06-.44H14.25m0 11.63h-6m6 0V6.38a1.13 1.13 0 0 0-1.13-1.13H3.38a1.13 1.13 0 0 0-1.13 1.13v7.87" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Fleet &amp; salesmen</span>
                            <span class="ak-kpi-value ak-kpi-value-sm">{{ $n($c['vehicles']) }} vehicles</span>
                            <span class="ak-kpi-hint">{{ $n($c['salesmen']) }} active employees</span>
                        </span>
                    </div>
                    <div class="ak-kpi{{ $c['drafts_total'] ? ' ak-kpi-action' : '' }}">
                        <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Unposted drafts</span>
                            <span class="ak-kpi-value">{{ $n($c['drafts_total']) }}</span>
                            <span class="ak-kpi-hint">{{ $c['drafts_total'] ? 'across all users & modules' : 'everything is posted' }}</span>
                        </span>
                    </div>
                </div>

                @if (! empty($salesBySupplier['labels']))
                    <div class="db-card" style="margin-top:16px">
                        <div class="db-card-head"><div><h3>Sales by supplier</h3><p class="db-card-sub">This month, posted settlements</p></div></div>
                        <div id="db-sales-supplier" class="db-chart-sm" role="img" aria-label="Sales by supplier this month"></div>
                    </div>
                @endif
            </section>
        @endif

        {{-- ============================== Credit ============================== --}}
        @if ($sections['credit'])
            <section class="db-section" id="db-credit" aria-labelledby="db-credit-h"
                x-data="{
                    aging: null,
                    showAging(bucket) { this.aging = bucket; this.$nextTick(() => this.$refs.agingList?.scrollIntoView({ behavior: 'smooth', block: 'start' })); },
                    inAging(b) { return this.aging === 'late' ? b >= 2 : this.aging === b; },
                }"
                @aging-bucket.window="showAging($event.detail)">
                <div class="db-section-head">
                    <div>
                        <h2 id="db-credit-h">Customer credit</h2>
                        <p>{{ $scope['supplier'] }} &middot; what customers still owe (debit less recoveries)</p>
                    </div>
                    <div class="db-links">
                        @if ($creditUrl)<a href="{{ $creditUrl }}" class="ak-btn ak-btn-outline">Creditors ledger</a>@endif
                        @can('customer-list')<a href="{{ route('customers.index') }}" class="ak-btn ak-btn-outline">Customers</a>@endcan
                    </div>
                </div>
                <div class="ak-kpis">
                    <div class="ak-kpi" title="{{ $full($k['marketCredit'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Outstanding credit</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['marketCredit'] ?? 0) }}</span>
                            <span class="ak-kpi-hint">{{ $n($k['creditCustomers'] ?? 0) }} customers owe</span>
                        </span>
                    </div>
                    <div class="ak-kpi" title="{{ $full($k['creditGivenThisMonth'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Credit given this month</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['creditGivenThisMonth'] ?? 0) }}</span>
                            <span class="ak-kpi-hint">new credit sales</span>
                        </span>
                    </div>
                    <div class="ak-kpi" title="{{ $full($k['creditRecoveredThisMonth'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Recovered this month</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['creditRecoveredThisMonth'] ?? 0) }}</span>
                            @php $given = (float) ($k['creditGivenThisMonth'] ?? 0); $rec = (float) ($k['creditRecoveredThisMonth'] ?? 0); @endphp
                            <span class="ak-kpi-hint {{ $rec >= $given ? 'db-up' : 'db-down' }}">{{ $given > 0 ? round($rec / $given * 100).'% of credit given' : 'cash and cheque recoveries' }}</span>
                        </span>
                    </div>
                    <button type="button" @click="showAging('late')" class="ak-kpi{{ ($k['creditOverdue'] ?? 0) > 0 ? ' ak-kpi-action' : '' }}" style="text-align:left; cursor:pointer; font:inherit; width:100%" title="Show the customers — {{ $full($k['creditOverdue'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.3 3.38c-.87 1.5.22 3.37 1.95 3.37h14.7c1.73 0 2.82-1.87 1.95-3.37L13.95 3.38c-.87-1.5-3.03-1.5-3.9 0L2.7 16.13ZM12 15.75h.01" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">No payment for 60+ days</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['creditOverdue'] ?? 0) }}</span>
                            <span class="ak-kpi-hint">{{ $n($k['creditOverdueCustomers'] ?? 0) }} customers &middot; {{ ($k['marketCredit'] ?? 0) > 0 ? round(($k['creditOverdue'] ?? 0) / $k['marketCredit'] * 100).'% of credit' : '—' }} &middot; show list →</span>
                        </span>
                    </button>
                </div>

                <div class="db-grid db-even">
                    @if ($scope['supplier_id'] === null)
                        <div class="db-card">
                            <div class="db-card-head"><div><h3>Credit by company</h3><p class="db-card-sub">Outstanding now, per supplier</p></div></div>
                            @if (! empty($creditBreakdown['labels']))
                                <div id="db-credit-breakdown" class="db-chart-sm" role="img" aria-label="Outstanding credit by company"></div>
                            @else
                                <div class="db-empty"><b>No outstanding credit</b></div>
                            @endif
                        </div>
                    @endif
                    <div class="db-card">
                        <div class="db-card-head"><div><h3>Credit by salesman</h3><p class="db-card-sub">Outstanding now, top {{ count($creditBySalesman['labels'] ?? []) }}</p></div></div>
                        @if (! empty($creditBySalesman['labels']))
                            <div id="db-credit-salesman" class="db-chart-sm" role="img" aria-label="Outstanding credit by salesman"></div>
                        @else
                            <div class="db-empty"><b>No outstanding credit</b></div>
                        @endif
                    </div>
                    <div class="db-card">
                        <div class="db-card-head"><div><h3>Credit aging</h3><p class="db-card-sub">Outstanding by days since the customer last paid &middot; click a bar to see the customers</p></div></div>
                        @if (array_sum($creditAging['values'] ?? []) > 0)
                            <div id="db-credit-aging" class="db-chart-sm" role="img" aria-label="Outstanding credit by age" style="cursor:pointer"></div>
                            <div class="db-aging-chips">
                                @foreach ($creditAging['labels'] as $i => $label)
                                    @if ($i > 0)
                                        <button type="button" @click="showAging({{ $i }})" :class="aging === {{ $i }} && 'is-on'">
                                            {{ $label }} <b>{{ $n($creditAging['counts'][$i] ?? 0) }}</b>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="db-empty"><b>No outstanding credit</b></div>
                        @endif
                    </div>
                    <div class="db-card"{!! $scope['supplier_id'] === null ? '' : ' style="grid-column: 1 / -1"' !!}>
                        <div class="db-card-head">
                            <div><h3>Top creditors</h3><p class="db-card-sub">Customers who owe the most, from their customer accounts</p></div>
                            @if ($creditUrl)<a href="{{ $creditUrl }}">Creditors ledger →</a>@endif
                        </div>
                        @if ($topCreditCustomers)
                            <ul class="db-list">
                                @foreach ($topCreditCustomers as $cust)
                                    @php $late = $cust['last_paid_days'] === null || $cust['last_paid_days'] > 60; @endphp
                                    <li><div class="db-row">
                                        <span style="min-width:0">
                                            <span class="db-l1">{{ $loop->iteration }}. {{ $cust['name'] }}{{ $cust['code'] ? ' ('.$cust['code'].')' : '' }}{{ $cust['city'] ? ' · '.$cust['city'] : '' }}</span>
                                            <span class="db-l2">{{ $scope['supplier_id'] === null && $cust['suppliers'] ? $cust['suppliers'].' · ' : '' }}{{ $cust['salesmen'] ?: '—' }}</span>
                                        </span>
                                        <span class="db-r">
                                            <b title="{{ $full($cust['used']) }}">Rs {{ $rs($cust['used']) }}</b>
                                            <span class="{{ $late ? 'db-down' : '' }}">{{ $cust['last_paid_days'] === null ? 'never paid' : ($cust['last_paid_days'] === 0 ? 'paid today' : 'paid '.$cust['last_paid_days'].' '.\Illuminate\Support\Str::plural('day', $cust['last_paid_days']).' ago') }}</span>
                                        </span>
                                    </div></li>
                                @endforeach
                            </ul>
                        @else
                            <div class="db-empty"><b>No customer credit outstanding</b></div>
                        @endif
                    </div>
                </div>

                {{-- Aging drill-down: opened from the "No payment" card, the aging bars or chips --}}
                @php
                    $bucketNames = [1 => '31-60 days', 2 => '61-90 days', 3 => 'Over 90 days'];
                    $custLedger = fn ($code) => route('reports.creditors-ledger.index', ['filter' => array_filter(['customer_code' => $code, 'supplier_id' => $scope['supplier_id']])]);
                    $canCreditors = auth()->user()->can('report-audit-creditors-ledger');
                @endphp
                <div class="db-card" x-ref="agingList" x-show="aging !== null" x-cloak style="margin-top:16px; padding:0; overflow:hidden; scroll-margin-top:16px">
                    <div class="db-card-head" style="padding:16px 18px 8px; flex-wrap:wrap">
                        <div>
                            <h3 x-text="aging === 'late' ? 'No payment for 60+ days' : 'No payment for ' + ({{ \Illuminate\Support\Js::from($bucketNames) }})[aging]"></h3>
                            <p class="db-card-sub">Customers still owing, biggest first. Days are counted from the last payment (or the credit sale if they never paid).</p>
                        </div>
                        <div class="db-aging-chips" style="margin:0">
                            <button type="button" @click="aging = 'late'" :class="aging === 'late' && 'is-on'">60+ days <b>{{ $n($k['creditOverdueCustomers'] ?? 0) }}</b></button>
                            @foreach ($bucketNames as $i => $label)
                                <button type="button" @click="aging = {{ $i }}" :class="aging === {{ $i }} && 'is-on'">{{ $label }} <b>{{ $n($creditAging['counts'][$i] ?? 0) }}</b></button>
                            @endforeach
                            <button type="button" @click="aging = null" aria-label="Close list">✕</button>
                        </div>
                    </div>
                    <div style="max-height: 28rem; overflow:auto; border-top:1px solid var(--ak-line)">
                        <table class="ak-dt" style="min-width:720px">
                            <thead>
                                <tr>
                                    <th scope="col">Customer</th>
                                    <th scope="col">{{ $scope['supplier_id'] === null ? 'Company · salesman' : 'Salesman' }}</th>
                                    <th scope="col" class="ak-num">Owes (Rs)</th>
                                    <th scope="col" class="ak-c">Last paid</th>
                                    <th scope="col" class="ak-c">Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($agingCustomers as $cust)
                                    <tr x-show="inAging({{ $cust['bucket'] }})">
                                        <td data-label="Customer">
                                            @if ($canCreditors && $cust['code'])
                                                <a href="{{ $custLedger($cust['code']) }}" class="ak-primary-link">{{ $cust['name'] }}</a>
                                            @else
                                                <span class="ak-strong">{{ $cust['name'] }}</span>
                                            @endif
                                            <div class="ak-muted">{{ $cust['code'] }}{{ $cust['city'] ? ' · '.$cust['city'] : '' }}</div>
                                        </td>
                                        <td data-label="{{ $scope['supplier_id'] === null ? 'Company · salesman' : 'Salesman' }}">
                                            @if ($scope['supplier_id'] === null)<div>{{ $cust['suppliers'] ?: '—' }}</div>@endif
                                            <div class="{{ $scope['supplier_id'] === null ? 'ak-muted' : '' }}">{{ $cust['salesmen'] ?: '—' }}</div>
                                        </td>
                                        <td class="ak-num ak-strong" data-label="Owes (Rs)">{{ number_format($cust['used']) }}</td>
                                        <td class="ak-c" data-label="Last paid">{{ $cust['last_paid_days'] === null ? 'Never' : now()->subDays($cust['last_paid_days'])->format('d M Y') }}</td>
                                        <td class="ak-c" data-label="Days"><span class="ak-status {{ $cust['bucket'] >= 3 ? 'ak-status-red' : 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ $cust['days'] >= 999 ? '—' : $cust['days'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="ak-muted" style="padding:10px 18px; margin:0">
                        Totals: 31-60 days Rs {{ $rs($creditAging['values'][1] ?? 0) }} &middot; 61-90 days Rs {{ $rs($creditAging['values'][2] ?? 0) }} &middot; over 90 days Rs {{ $rs($creditAging['values'][3] ?? 0) }}
                        @if (count($agingCustomers) >= 300) &middot; showing the 300 biggest @endif
                        @if ($canCreditors && $creditUrl) &middot; <a href="{{ $creditUrl }}">Full creditors ledger →</a>@endif
                    </p>
                </div>
            </section>
        @endif

        {{-- ============================== Sales ============================== --}}
        @if ($sections['sales'])
            @php
                $salesDelta = $delta($k['totalSalesThisMonth'] ?? 0, $lastMonth['sales'] ?? null);
                $profitDelta = $delta($k['grossProfitThisMonth'] ?? 0, $lastMonth['profit'] ?? null);
                $hasSales = array_sum($monthlySalesTrend['sales'] ?? []) > 0;
            @endphp
            <section class="db-section" id="db-sales" aria-labelledby="db-sales-h">
                <div class="db-section-head">
                    <div>
                        <h2 id="db-sales-h">Sales &amp; settlements</h2>
                        <p>{{ $scope['supplier'] }} &middot; {{ $ownNote($scope['own_settlements']) }} &middot; {{ now()->format('F Y') }}</p>
                    </div>
                    <div class="db-links">
                        <a href="{{ route('sales-settlements.index') }}" class="ak-btn ak-btn-outline">Open settlements</a>
                        @can('sales-settlement-create')<a href="{{ route('sales-settlements.create') }}" class="ak-btn ak-btn-primary">＋ New Settlement</a>@endcan
                    </div>
                </div>

                <div class="ak-kpis">
                    <div class="ak-kpi" title="{{ $full($k['totalSalesThisMonth'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.3 4.3a11.95 11.95 0 0 1 5.8-5.8l2.65-1.2m0 0-5.94-2.28m5.94 2.28-2.28 5.94" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Sales this month</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['totalSalesThisMonth'] ?? 0) }}</span>
                            <span class="ak-kpi-hint {{ $salesDelta ? ($salesDelta['up'] ? 'db-up' : 'db-down') : '' }}">{{ $salesDelta['text'] ?? 'Rs '.$rs($k['salesToday'] ?? 0).' today' }}</span>
                        </span>
                    </div>
                    <div class="ak-kpi" title="{{ $full($k['grossProfitThisMonth'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.82.88.66c1.17.88 3.07.88 4.24 0 1.17-.88 1.17-2.3 0-3.18C13.54 12.22 12.77 12 12 12c-.72 0-1.45-.22-2-.66-1.1-.88-1.1-2.3 0-3.18s2.9-.88 4 0l.42.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Gross profit</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['grossProfitThisMonth'] ?? 0) }}</span>
                            <span class="ak-kpi-hint {{ $profitDelta ? ($profitDelta['up'] ? 'db-up' : 'db-down') : '' }}">{{ $margin }}% margin{{ $profitDelta ? ' · '.$profitDelta['text'] : '' }}</span>
                        </span>
                    </div>
                    <div class="ak-kpi" title="{{ $full($k['cashCollectedThisMonth'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75h19.5M3.75 6h16.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5V7.5A1.5 1.5 0 0 1 3.75 6ZM15 11.25a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Cash Collected</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['cashCollectedThisMonth'] ?? 0) }}</span>
                            <span class="ak-kpi-hint">Rs {{ $rs($k['recoveriesThisMonth'] ?? 0) }} credit recovered</span>
                        </span>
                    </div>
                    <div class="ak-kpi" title="{{ $full($k['creditSalesThisMonth'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Credit Sales</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['creditSalesThisMonth'] ?? 0) }}</span>
                            <span class="ak-kpi-hint">{{ $n($k['tripsThisMonth'] ?? 0) }} settlements &middot; Rs {{ $rs($k['expensesThisMonth'] ?? 0) }} expenses</span>
                        </span>
                    </div>
                </div>

                @if (! $hasSales && empty($recentSettlements))
                    <div class="db-card" style="margin-top:16px"><div class="db-empty"><b>No settlements yet</b><span>Settlements for {{ $scope['supplier'] }} will show here once they are entered.</span></div></div>
                @else
                    <div class="db-grid">
                        <div class="db-card">
                            <div class="db-card-head"><div><h3>Monthly Sales &amp; Profit</h3><p class="db-card-sub">Posted settlements, last 12 months</p></div></div>
                            <div id="db-sales-monthly" class="db-chart" role="img" aria-label="Monthly sales and gross profit"></div>
                        </div>
                        <div class="db-card">
                            <div class="db-card-head"><div><h3>How customers paid</h3><p class="db-card-sub">This month's sales: cash, credit and bank transfer</p></div></div>
                            @if (array_sum($salesByPaymentMethod['values'] ?? []) > 0)
                                <div id="db-sales-method" class="db-chart" role="img" aria-label="Sales by payment method"></div>
                            @else
                                <div class="db-empty"><b>No posted sales this month</b></div>
                            @endif
                        </div>
                    </div>

                    <div class="db-grid">
                        <div class="db-card">
                            <div class="db-card-head"><div><h3>Daily sales</h3><p class="db-card-sub">Last 30 days</p></div>
                                @can('report-sales-daily-sales')<a href="{{ route('reports.daily-sales.index') }}">Daily sales report →</a>@endcan
                            </div>
                            <div id="db-sales-daily" class="db-chart-sm" role="img" aria-label="Daily sales, last 30 days"></div>
                        </div>
                        <div class="db-card">
                            <div class="db-card-head"><div><h3>Top salesmen</h3><p class="db-card-sub">This month by sales</p></div></div>
                            @if (! empty($topSalespersonBySales['labels']))
                                <div id="db-sales-salesmen" class="db-chart-sm" role="img" aria-label="Top salesmen this month"></div>
                            @else
                                <div class="db-empty"><b>No posted sales this month</b></div>
                            @endif
                        </div>
                    </div>

                    <div class="db-grid">
                        <div class="db-card">
                            <div class="db-card-head"><div><h3>Best-selling products</h3><p class="db-card-sub">This month, top {{ count($topProductsBySales['labels'] ?? []) }}</p></div></div>
                            @if (! empty($topProductsBySales['labels']))
                                <div id="db-sales-products" class="db-chart" role="img" aria-label="Best-selling products this month"></div>
                            @else
                                <div class="db-empty"><b>No product sales this month</b></div>
                            @endif
                        </div>
                        <div class="db-card">
                            <div class="db-card-head"><div><h3>Latest settlements</h3><p class="db-card-sub">{{ $ownNote($scope['own_settlements']) }}</p></div>
                                <a href="{{ route('sales-settlements.index') }}">All →</a>
                            </div>
                            @if ($recentSettlements)
                                <ul class="db-list">
                                    @foreach ($recentSettlements as $s)
                                        <li>
                                            <a href="{{ route('sales-settlements.show', $s['id']) }}">
                                                <span style="min-width:0">
                                                    <span class="db-l1">{{ $s['number'] }} &middot; {{ $s['salesman'] }}</span>
                                                    <span class="db-l2">{{ $s['date'] }}{{ $s['supplier'] && ! $scope['supplier_id'] ? ' · '.$s['supplier'] : '' }}</span>
                                                </span>
                                                <span class="db-r">
                                                    <b title="{{ $full($s['amount']) }}">Rs {{ $rs($s['amount']) }}</b>
                                                    <span class="db-badge {{ in_array($s['status'], ['posted', 'draft'], true) ? $s['status'] : 'other' }}">{{ ucfirst($s['status']) }}</span>
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="db-empty"><b>No settlements yet</b></div>
                            @endif
                        </div>
                    </div>

                    <div class="db-grid db-even">
                        <div class="db-card">
                            <div class="db-card-head"><div><h3>Cash, credit &amp; bank transfer</h3><p class="db-card-sub">How sales were made, last 6 months</p></div></div>
                            <div id="db-sales-mix" class="db-chart-sm" role="img" aria-label="Sales by payment method per month"></div>
                        </div>
                        <div class="db-card">
                            <div class="db-card-head"><div><h3>Best days of the week</h3><p class="db-card-sub">Sales over the last 90 days</p></div></div>
                            <div id="db-sales-dow" class="db-chart-sm" role="img" aria-label="Sales by day of week"></div>
                        </div>
                    </div>

                    @if (! $sections['company'] && ! empty($salesBySupplier['labels']) && count($salesBySupplier['labels']) > 1)
                        <div class="db-card" style="margin-top:16px">
                            <div class="db-card-head"><div><h3>Sales by supplier</h3><p class="db-card-sub">This month, posted settlements</p></div></div>
                            <div id="db-sales-supplier" class="db-chart-sm" role="img" aria-label="Sales by supplier this month"></div>
                        </div>
                    @endif
                @endif
            </section>
        @endif

        {{-- ============================== Distribution ============================== --}}
        @if ($sections['distribution'])
            @php $issuedDelta = $delta($k['goodsIssuedThisMonth'] ?? 0, $lastMonth['issued'] ?? null); @endphp
            <section class="db-section" id="db-distribution" aria-labelledby="db-dist-h">
                <div class="db-section-head">
                    <div>
                        <h2 id="db-dist-h">Distribution (goods issue)</h2>
                        <p>{{ $scope['supplier'] }} &middot; {{ $ownNote($scope['own_issues']) }}</p>
                    </div>
                    <div class="db-links">
                        <a href="{{ route('goods-issues.index') }}" class="ak-btn ak-btn-outline">Open goods issues</a>
                        @can('goods-issue-create')<a href="{{ route('goods-issues.create') }}" class="ak-btn ak-btn-primary">＋ New Issue</a>@endcan
                    </div>
                </div>
                <div class="ak-kpis">
                    <div class="ak-kpi" title="{{ $full($k['goodsIssuedThisMonth'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Goods Issued this month</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['goodsIssuedThisMonth'] ?? 0) }}</span>
                            <span class="ak-kpi-hint {{ $issuedDelta ? ($issuedDelta['up'] ? 'db-up' : 'db-down') : '' }}">{{ $issuedDelta['text'] ?? 'stock value sent to vans' }}</span>
                        </span>
                    </div>
                    <div class="ak-kpi">
                        <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.11c0-1.13-.84-2.09-1.96-2.18a48.42 48.42 0 0 0-1.12-.08m-5.8 0c-.07.21-.1.44-.1.67 0 .41.34.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.67m-5.8 0A2.25 2.25 0 0 1 15 2.25h1.5a2.25 2.25 0 0 1 2.15 1.6m-5.8 0c-.38.03-.75.05-1.12.08C9.6 4.02 8.75 4.98 8.75 6.11V8.25m0 0H4.88c-.62 0-1.13.5-1.13 1.13v10.5c0 .62.5 1.12 1.13 1.12h9.75c.62 0 1.12-.5 1.12-1.12V9.38c0-.62-.5-1.13-1.12-1.13H8.25Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Issues this month</span>
                            <span class="ak-kpi-value">{{ $n($k['goodsIssueCountThisMonth'] ?? 0) }}</span>
                            <span class="ak-kpi-hint">{{ $n($k['issuedToday'] ?? 0) }} issued today</span>
                        </span>
                    </div>
                    <a href="{{ $draftLinks['draftGoodsIssues'] ?? route('goods-issues.index') }}" class="ak-kpi{{ ($pendingItems['draftGoodsIssues'] ?? 0) ? ' ak-kpi-action' : '' }}">
                        <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Draft issues</span>
                            <span class="ak-kpi-value">{{ $n($pendingItems['draftGoodsIssues'] ?? 0) }}</span>
                            <span class="ak-kpi-hint">{{ ($pendingItems['draftGoodsIssues'] ?? 0) ? 'Post them →' : 'nothing waiting' }}</span>
                        </span>
                    </a>
                    <a href="{{ $draftLinks['draftSettlements'] ?? route('sales-settlements.index') }}" class="ak-kpi{{ ($pendingItems['draftSettlements'] ?? 0) ? ' ak-kpi-action' : '' }}">
                        <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Settlements to post</span>
                            <span class="ak-kpi-value">{{ $n($pendingItems['draftSettlements'] ?? 0) }}</span>
                            <span class="ak-kpi-hint">{{ ($pendingItems['draftSettlements'] ?? 0) ? 'close the trips →' : 'all trips closed' }}</span>
                        </span>
                    </a>
                </div>
                @if (! empty($grnVsGoodsIssueTrend['labels']))
                    <div class="db-card" style="margin-top:16px">
                        <div class="db-card-head"><div><h3>Goods Receipt vs Goods Issue</h3><p class="db-card-sub">Stock in (GRN) and out to vans, last 6 months</p></div></div>
                        <div id="db-dist-flow" class="db-chart-sm" role="img" aria-label="Goods received vs goods issued per month"></div>
                    </div>
                @endif
            </section>
        @endif

        {{-- ============================== Purchases & payables ============================== --}}
        @if ($sections['purchases'])
            @php $purchaseDelta = $delta($k['totalPurchasesThisMonth'] ?? 0, $lastMonth['purchases'] ?? null); @endphp
            <section class="db-section" id="db-purchases" aria-labelledby="db-pur-h">
                <div class="db-section-head">
                    <div>
                        <h2 id="db-pur-h">Purchases &amp; supplier payables</h2>
                        <p>{{ $scope['supplier'] }}{{ isset($k['totalPurchasesThisMonth']) ? ' · GRNs: '.$ownNote($scope['own_grns']) : '' }}</p>
                    </div>
                    <div class="db-links">
                        @can('goods-receipt-note-list')<a href="{{ route('goods-receipt-notes.index') }}" class="ak-btn ak-btn-outline">Open GRNs</a>@endcan
                        @can('goods-receipt-note-create')<a href="{{ route('goods-receipt-notes.create') }}" class="ak-btn ak-btn-primary">＋ New GRN</a>@endcan
                    </div>
                </div>
                @php
                    $ledgerUrl = auth()->user()->can('report-audit-ledger-register') ? route('reports.ledger-register.index', array_filter(['filter' => array_filter(['supplier_id' => $scope['supplier_id'], 'date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->endOfMonth()->toDateString()])])) : null;
                    $invDelta = $delta($k['supplierInvoicesThisMonth'] ?? 0, $lastMonth['invoices'] ?? null);
                    $payDelta = $delta($k['paymentsThisMonth'] ?? 0, $lastMonth['payments'] ?? null);
                    $owed = (float) ($k['outstandingPayables'] ?? 0);
                @endphp
                <div class="ak-kpis">
                    @isset($k['supplierInvoicesThisMonth'])
                        <a href="{{ $ledgerUrl ?? '#' }}" class="ak-kpi" title="{{ $full($k['supplierInvoicesThisMonth']) }}">
                            <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.63a3.38 3.38 0 0 0-3.38-3.37h-1.5a1.13 1.13 0 0 1-1.12-1.13v-1.5a3.38 3.38 0 0 0-3.38-3.37H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.63c-.62 0-1.13.5-1.13 1.13v17.24c0 .63.5 1.13 1.13 1.13h12.74c.63 0 1.13-.5 1.13-1.13V11.25a9 9 0 0 0-9-9Z" /></svg></span>
                            <span class="ak-kpi-body">
                                <span class="ak-kpi-label">Supplier invoices this month</span>
                                <span class="ak-kpi-value">Rs {{ $rs($k['supplierInvoicesThisMonth']) }}</span>
                                <span class="ak-kpi-hint {{ $invDelta ? ($invDelta['up'] ? 'db-down' : 'db-up') : '' }}">{{ $invDelta['text'] ?? 'invoice amount billed to us' }}</span>
                            </span>
                        </a>
                        <a href="{{ $ledgerUrl ?? '#' }}" class="ak-kpi" title="{{ $full($k['paymentsThisMonth'] ?? 0) }}">
                            <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                            <span class="ak-kpi-body">
                                <span class="ak-kpi-label">Payments This Month</span>
                                <span class="ak-kpi-value">Rs {{ $rs($k['paymentsThisMonth'] ?? 0) }}</span>
                                <span class="ak-kpi-hint">{{ $payDelta['text'] ?? 'online amount paid to suppliers' }}</span>
                            </span>
                        </a>
                        <a href="{{ $ledgerUrl ?? '#' }}" class="ak-kpi{{ $owed > 0 ? ' ak-kpi-action' : '' }}" title="{{ $full(abs($owed)) }}">
                            <span class="ak-kpi-icon {{ $owed > 0 ? 'ak-tone-amber' : 'ak-tone-slate' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.47 0-2.87.26-4.17.75M12 20.25c1.47 0 2.87.26 4.17.75M18.75 4.97A48.42 48.42 0 0 0 12 4.5c-2.29 0-4.54.16-6.75.47m13.5 0c1.01.14 2.01.31 3 .52m-3-.52 2.62 10.73c.12.5-.11 1.03-.6 1.2a5.99 5.99 0 0 1-2.02.35 5.99 5.99 0 0 1-2.03-.35c-.48-.17-.72-.7-.6-1.2L18.75 4.97Zm-16.5.52c.99-.21 1.99-.38 3-.52m0 0 2.62 10.73c.12.5-.11 1.03-.6 1.2a5.99 5.99 0 0 1-2.03.35 5.99 5.99 0 0 1-2.02-.35c-.49-.17-.72-.7-.6-1.2L5.25 4.97Z" /></svg></span>
                            <span class="ak-kpi-body">
                                <span class="ak-kpi-label">{{ $owed > 0 ? 'Payable to suppliers' : 'Advance with suppliers' }}</span>
                                <span class="ak-kpi-value">Rs {{ $rs(abs($owed)) }}</span>
                                <span class="ak-kpi-hint">supplier ledger balance</span>
                            </span>
                        </a>
                    @endisset
                    @isset($k['totalPurchasesThisMonth'])
                        <div class="ak-kpi" title="{{ $full($k['totalPurchasesThisMonth']) }}">
                            <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.63 10.63a2.25 2.25 0 0 1-2.24 2.12H6.62a2.25 2.25 0 0 1-2.24-2.12L3.75 7.5m6 4.13h4.5M3.38 7.5h17.25c.62 0 1.12-.5 1.12-1.13v-1.5c0-.62-.5-1.12-1.12-1.12H3.38c-.63 0-1.13.5-1.13 1.12v1.5c0 .63.5 1.13 1.13 1.13Z" /></svg></span>
                            <span class="ak-kpi-body">
                                <span class="ak-kpi-label">Received (GRN) this month</span>
                                <span class="ak-kpi-value">Rs {{ $rs($k['totalPurchasesThisMonth']) }}</span>
                                <span class="ak-kpi-hint">{{ $n($k['grnCountThisMonth'] ?? 0) }} GRNs &middot; {{ $n($pendingItems['draftGrns'] ?? 0) }} drafts</span>
                            </span>
                        </div>
                    @endisset
                </div>
                @if (array_sum($purchasesVsPayments['purchases'] ?? []) + array_sum($purchasesVsPayments['payments'] ?? []) > 0)
                    <div class="db-card" style="margin-top:16px">
                        <div class="db-card-head">
                            <div><h3>Supplier invoices vs payments</h3><p class="db-card-sub">Invoice amount vs online amount, with the ledger balance at each month end (Supplier Ledger Register, last 6 months)</p></div>
                            @if ($ledgerUrl)<a href="{{ $ledgerUrl }}">Ledger register →</a>@endif
                        </div>
                        <div id="db-pur-pay" class="db-chart" role="img" aria-label="Supplier invoices versus payments per month, with balance"></div>
                    </div>
                @endif

                @if ($supplierLedger)
                    @php
                        $ledgerLink = fn ($id) => route('reports.ledger-register.index', ['filter' => ['supplier_id' => $id, 'date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->endOfMonth()->toDateString()]]);
                        $canLedger = auth()->user()->can('report-audit-ledger-register');
                    @endphp
                    <div class="db-card" style="margin-top:16px; padding:0; overflow:hidden">
                        <div class="db-card-head" style="padding:16px 18px 6px">
                            <div><h3>Ledger register by supplier</h3><p class="db-card-sub">{{ now()->format('F Y') }} invoices and payments, and the balance to date</p></div>
                        </div>
                        <div style="overflow-x:auto">
                            <table class="ak-dt" style="min-width:640px">
                                <thead>
                                    <tr>
                                        <th scope="col">Supplier</th>
                                        <th scope="col" class="ak-num">Invoices (month)</th>
                                        <th scope="col" class="ak-num">Payments (month)</th>
                                        <th scope="col" class="ak-num">Balance</th>
                                        <th scope="col">Position</th>
                                        <th scope="col" class="ak-c">Last entry</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($supplierLedger as $row)
                                        <tr>
                                            <td data-label="Supplier">
                                                @if ($canLedger)<a href="{{ $ledgerLink($row['id']) }}" class="ak-primary-link">{{ $row['name'] }}</a>@else<span class="ak-strong">{{ $row['name'] }}</span>@endif
                                            </td>
                                            <td class="ak-num" data-label="Invoices (month)" title="{{ $full($row['invoices']) }}">{{ number_format($row['invoices']) }}</td>
                                            <td class="ak-num" data-label="Payments (month)" title="{{ $full($row['payments']) }}">{{ number_format($row['payments']) }}</td>
                                            <td class="ak-num ak-strong" data-label="Balance" style="color: {{ $row['balance'] < 0 ? '#b91c1c' : '#15803d' }}">{{ number_format($row['balance']) }}</td>
                                            <td data-label="Position">
                                                @if (abs($row['balance']) < 1)
                                                    <span class="ak-status" style="background:#e2e8f0; color:#334155"><i aria-hidden="true"></i>Settled</span>
                                                @elseif ($row['balance'] < 0)
                                                    <span class="ak-status ak-status-red"><i aria-hidden="true"></i>We owe</span>
                                                @else
                                                    <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Advance</span>
                                                @endif
                                            </td>
                                            <td class="ak-c ak-mono" data-label="Last entry">{{ $row['last_entry'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                @if (count($supplierLedger) > 1)
                                    @php $sumB = array_sum(array_column($supplierLedger, 'balance')); @endphp
                                    <tfoot>
                                        <tr>
                                            <td class="ak-foot-label" style="text-align:left">Total ({{ count($supplierLedger) }} suppliers)</td>
                                            <td class="ak-num">{{ number_format(array_sum(array_column($supplierLedger, 'invoices'))) }}</td>
                                            <td class="ak-num">{{ number_format(array_sum(array_column($supplierLedger, 'payments'))) }}</td>
                                            <td class="ak-num" style="color: {{ $sumB < 0 ? '#b91c1c' : '#15803d' }}">{{ number_format($sumB) }}</td>
                                            <td colspan="2">{{ $sumB < 0 ? 'Net payable' : 'Net advance' }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        {{-- ============================== Inventory ============================== --}}
        @if ($sections['inventory'])
            <section class="db-section" id="db-inventory" aria-labelledby="db-inv-h">
                <div class="db-section-head">
                    <div>
                        <h2 id="db-inv-h">Inventory</h2>
                        <p>{{ $scope['supplier'] }} &middot; stock on hand now</p>
                    </div>
                    <div class="db-links">
                        <a href="{{ route('inventory.current-stock.index') }}" class="ak-btn ak-btn-outline">View Stock</a>
                    </div>
                </div>
                <div class="ak-kpis">
                    <div class="ak-kpi" title="{{ $full($k['totalInventoryValue'] ?? 0) }}">
                        <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Inventory Value</span>
                            <span class="ak-kpi-value">Rs {{ $rs($k['totalInventoryValue'] ?? 0) }}</span>
                            <span class="ak-kpi-hint">at average cost</span>
                        </span>
                    </div>
                    <div class="ak-kpi">
                        <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Products in stock</span>
                            <span class="ak-kpi-value">{{ $n($k['productsInStock'] ?? 0) }} <small style="font-size:13px; color:var(--ak-muted)">/ {{ $n($k['totalProducts'] ?? 0) }}</small></span>
                            <span class="ak-kpi-hint">{{ $n(max(0, ($k['totalProducts'] ?? 0) - ($k['productsInStock'] ?? 0))) }} active products out of stock</span>
                        </span>
                    </div>
                    <div class="ak-kpi{{ count($lowStock) ? ' ak-kpi-action' : '' }}">
                        <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.3 3.38c-.87 1.5.22 3.37 1.95 3.37h14.7c1.73 0 2.82-1.87 1.95-3.37L13.95 3.38c-.87-1.5-3.03-1.5-3.9 0L2.7 16.13ZM12 15.75h.01" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">At or below reorder level</span>
                            <span class="ak-kpi-value">{{ $n(count($lowStock)) }}{{ count($lowStock) >= 8 ? '+' : '' }}</span>
                            <span class="ak-kpi-hint">{{ count($lowStock) ? 'reorder soon' : 'stock levels fine' }}</span>
                        </span>
                    </div>
                    <div class="ak-kpi">
                        <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.38c0-.62.5-1.12 1.13-1.12h2.25c.62 0 1.12.5 1.12 1.12V21M3 3h12m-.75 4.5H21" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Warehouses holding stock</span>
                            <span class="ak-kpi-value">{{ $n(count($warehouseStockDistribution['labels'] ?? [])) }}</span>
                            <span class="ak-kpi-hint">{{ ! empty($warehouseStockDistribution['labels']) ? 'largest: '.$warehouseStockDistribution['labels'][0] : '—' }}</span>
                        </span>
                    </div>
                </div>

                <div class="db-grid">
                    <div class="db-card">
                        <div class="db-card-head"><div><h3>Top products by stock value</h3><p class="db-card-sub">Where the money sits</p></div></div>
                        @if (! empty($topProductsByStockValue['labels']))
                            <div id="db-inv-products" class="db-chart" role="img" aria-label="Top products by stock value"></div>
                        @else
                            <div class="db-empty"><b>No stock on hand</b></div>
                        @endif
                    </div>
                    <div class="db-card">
                        <div class="db-card-head"><div><h3>Reorder soon</h3><p class="db-card-sub">On hand at or below reorder level</p></div>
                            <a href="{{ route('inventory.current-stock.index') }}">Stock →</a>
                        </div>
                        @if ($lowStock)
                            <ul class="db-list">
                                @foreach ($lowStock as $p)
                                    @php $pct = $p['reorder'] > 0 ? min(100, round($p['on_hand'] / $p['reorder'] * 100)) : 0; @endphp
                                    <li><div class="db-row" style="display:block">
                                        <div style="display:flex; justify-content:space-between; gap:10px">
                                            <span style="min-width:0"><span class="db-l1">{{ $p['name'] }}</span><span class="db-l2">{{ $p['code'] }}</span></span>
                                            <span class="db-r"><b>{{ number_format($p['on_hand']) }}</b>reorder at {{ number_format($p['reorder']) }}</span>
                                        </div>
                                        <div class="db-meter"><span class="{{ $pct <= 25 ? 'over' : 'warn' }}" style="width: {{ max(3, $pct) }}%"></span></div>
                                    </div></li>
                                @endforeach
                            </ul>
                        @else
                            <div class="db-empty"><b>Nothing below reorder level</b><span>Set a reorder level on products to get alerts here.</span></div>
                        @endif
                    </div>
                </div>

                <div class="db-grid db-even">
                    <div class="db-card">
                        <div class="db-card-head"><div><h3>Stock by Warehouse</h3><p class="db-card-sub">Value held at each location</p></div></div>
                        @if (! empty($warehouseStockDistribution['labels']))
                            <div id="db-inv-warehouse" class="db-chart-sm" role="img" aria-label="Stock value by warehouse"></div>
                        @else
                            <div class="db-empty"><b>No stock on hand</b></div>
                        @endif
                    </div>
                    <div class="db-card">
                        <div class="db-card-head"><div><h3>Stock movement</h3><p class="db-card-sub">In and out, last 30 days (value)</p></div></div>
                        @if (array_sum($stockMovementBreakdown['inward'] ?? []) + array_sum($stockMovementBreakdown['outward'] ?? []) > 0)
                            <div id="db-inv-movement" class="db-chart-sm" role="img" aria-label="Stock movement in and out"></div>
                        @else
                            <div class="db-empty"><b>No stock movement in the last 30 days</b></div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- ============================== Accounting ============================== --}}
        @if ($sections['accounting'])
            <section class="db-section" id="db-accounting" aria-labelledby="db-acc-h">
                <div class="db-section-head">
                    <div>
                        <h2 id="db-acc-h">Accounting</h2>
                        <p>Revenue, cost of goods and journal entries</p>
                    </div>
                    <div class="db-links">
                        @can('journal-entry-list')<a href="{{ route('journal-entries.index') }}" class="ak-btn ak-btn-outline">Journal entries</a>@endcan
                        @can('report-financial-general-ledger')<a href="{{ route('reports.general-ledger.index') }}" class="ak-btn ak-btn-outline">General ledger</a>@endcan
                        @can('journal-entry-create')<a href="{{ route('journal-entries.create') }}" class="ak-btn ak-btn-primary">＋ New Journal</a>@endcan
                    </div>
                </div>
                <div class="db-grid">
                    <div class="db-card">
                        <div class="db-card-head"><div><h3>Revenue vs COGS vs Expenses</h3><p class="db-card-sub">From posted settlements, last 6 months</p></div></div>
                        @if (array_sum($revenueVsCogs['revenue'] ?? []) > 0)
                            <div id="db-acc-rev" class="db-chart" role="img" aria-label="Revenue, cost of goods and expenses per month"></div>
                        @else
                            <div class="db-empty"><b>No posted revenue in the last 6 months</b></div>
                        @endif
                    </div>
                    <div class="db-card">
                        <div class="db-card-head"><div><h3>Journal Entry Status</h3><p class="db-card-sub">All entries</p></div></div>
                        @isset($k['draftJournalEntries'])
                            <p style="margin:4px 0 0; font-size:13px">
                                <a href="{{ $draftLinks['draftJournalEntries'] ?? route('journal-entries.index') }}" style="color:{{ $k['draftJournalEntries'] ? '#b45309' : 'var(--ak-muted)' }}; font-weight:700; text-decoration:none">
                                    {{ $n($k['draftJournalEntries']) }} Draft Journals {{ $k['draftJournalEntries'] ? '— post them →' : '' }}
                                </a>
                            </p>
                        @endisset
                        @if (array_sum($journalEntryStatus['values'] ?? []) > 0)
                            <div id="db-acc-je" class="db-chart-sm" role="img" aria-label="Journal entries by status"></div>
                        @else
                            <div class="db-empty"><b>No journal entries yet</b></div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- ============================== Quick Actions ============================== --}}
        @php
            $actions = collect([
                ['goods-receipt-note-create', 'goods-receipt-notes.create', 'New GRN', 'M12 4.5v15m7.5-7.5h-15'],
                ['goods-issue-create', 'goods-issues.create', 'New Issue', 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5'],
                ['sales-settlement-create', 'sales-settlements.create', 'New Settlement', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ['journal-entry-create', 'journal-entries.create', 'New Journal', 'M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.59a1 1 0 0 1 .7.29l5.42 5.42a1 1 0 0 1 .29.7V19a2 2 0 0 1-2 2Z'],
                ['supplier-payment-create', 'supplier-payments.create', 'New Payment', 'M2.25 18.75h19.5M3.75 6h16.5a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5V7.5A1.5 1.5 0 0 1 3.75 6Z'],
                ['inventory-view', 'inventory.current-stock.index', 'View Stock', 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9'],
            ])->filter(fn ($a) => auth()->user()->can($a[0]));
        @endphp
        @if ($actions->isNotEmpty())
            <section class="db-card" aria-labelledby="db-actions-h">
                <div class="db-card-head"><div><h3 id="db-actions-h">Quick Actions</h3><p class="db-card-sub">Jump to what you do most</p></div></div>
                <div class="db-actions" style="margin-top:8px">
                    @foreach ($actions as [$perm, $route, $label, $icon])
                        <a href="{{ route($route) }}" class="db-action">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @php
        $chartData = [
            'monthly' => $monthlySalesTrend ?: null,
            'method' => $salesByPaymentMethod ?: null,
            'daily' => $dailySalesTrend ?: null,
            'salesmen' => $topSalespersonBySales ?: null,
            'products' => $topProductsBySales ?: null,
            'mix' => $cashVsCreditTrend ?: null,
            'dow' => $salesByDayOfWeek ?: null,
            'supplier' => $salesBySupplier ?: null,
            'credit' => $creditBreakdown ?: null,
            'creditSalesman' => $creditBySalesman ?: null,
            'aging' => $creditAging ?: null,
            'flow' => $grnVsGoodsIssueTrend ?: null,
            'purpay' => $purchasesVsPayments ?: null,
            'stockTop' => $topProductsByStockValue ?: null,
            'warehouse' => $warehouseStockDistribution ?: null,
            'movement' => $stockMovementBreakdown ?: null,
            'rev' => $revenueVsCogs ?: null,
            'je' => $journalEntryStatus ?: null,
        ];
    @endphp

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof ApexCharts === 'undefined') { return; }
                const data = {{ \Illuminate\Support\Js::from($chartData) }};
                const css = getComputedStyle(document.querySelector('.db-page'));
                const c = ['--s1', '--s2', '--s3', '--s4', '--s5', '--s6'].map(v => css.getPropertyValue(v).trim());
                const ink = '#475569', grid = '#e2e8f0';
                // Money in Pakistani units on axes; exact rupees in tooltips.
                const short = v => { const a = Math.abs(v), s = v < 0 ? '-' : ''; return a >= 1e7 ? s + (a / 1e7).toFixed(1) + ' Cr' : a >= 1e5 ? s + (a / 1e5).toFixed(1) + ' Lac' : a >= 1e3 ? s + (a / 1e3).toFixed(0) + 'k' : s + Math.round(a); };
                const rupees = v => 'Rs ' + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
                const whole = v => Math.round(v).toLocaleString();
                const base = {
                    chart: { fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false } },
                    dataLabels: { enabled: false },
                    grid: { borderColor: grid, strokeDashArray: 3, padding: { left: 6, right: 8 } },
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
                const hbar = (id, labels, series, colors, opts = {}) => draw(id, Object.assign({
                    chart: { type: 'bar', height: Math.max(190, labels.length * 32 * series.length + 80) },
                    series, colors,
                    plotOptions: { bar: { horizontal: true, barHeight: '62%', borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last', dataLabels: { position: 'top' } } },
                    legend: Object.assign({}, base.legend, { show: series.length > 1 }),
                    // Value printed on each bar instead of an axis: money ticks overlapped on narrow cards.
                    xaxis: Object.assign({}, base.xaxis, { categories: labels, labels: { show: false }, axisBorder: { show: false } }),
                    grid: Object.assign({}, base.grid, { xaxis: { lines: { show: false } }, yaxis: { lines: { show: false } }, padding: { left: 6, right: 64 } }),
                    dataLabels: { enabled: true, formatter: v => short(v), textAnchor: 'start', offsetX: 36, style: { fontSize: '11px', fontWeight: 700, colors: ['#0f172a'] }, dropShadow: { enabled: false } },
                    yaxis: { labels: { style: { colors: ink, fontSize: '12px' }, maxWidth: 180 } },
                }, opts));
                const donut = (id, labels, values, colors, total, fmt = rupees) => draw(id, {
                    chart: { type: 'donut', height: 290 },
                    series: values, labels, colors,
                    stroke: { width: 2, colors: ['#fff'] },
                    tooltip: { y: { formatter: fmt } },
                    legend: Object.assign({}, base.legend, { position: 'bottom', horizontalAlign: 'center' }),
                    dataLabels: { enabled: true, formatter: v => v >= 5 ? Math.round(v) + '%' : '', dropShadow: { enabled: false } },
                    plotOptions: { pie: { donut: { size: '64%', labels: { show: true, total: { show: true, label: total, color: ink, formatter: w => fmt === rupees ? 'Rs ' + short(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) : whole(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } },
                });
                const bars = (id, labels, series, colors, height = 250) => draw(id, {
                    chart: { type: 'bar', height },
                    series, colors,
                    plotOptions: { bar: { columnWidth: '58%', borderRadius: 4, borderRadiusApplication: 'end' } },
                    stroke: { show: true, width: 2, colors: ['#fff'] },
                    xaxis: Object.assign({}, base.xaxis, { categories: labels }),
                });

                if (data.monthly) {
                    draw('db-sales-monthly', {
                        chart: { type: 'area', height: 290 },
                        series: [{ name: 'Sales', data: data.monthly.sales }, { name: 'Gross profit', data: data.monthly.profits }],
                        colors: [c[0], c[2]],
                        stroke: { curve: 'monotoneCubic', width: 2 },
                        fill: { type: 'gradient', gradient: { opacityFrom: 0.28, opacityTo: 0.02 } },
                        markers: { size: 0, hover: { size: 5 } },
                        xaxis: Object.assign({}, base.xaxis, { categories: data.monthly.labels }),
                    });
                }
                if (data.method) {
                    const keep = data.method.values.map((v, i) => [data.method.labels[i], v]).filter(p => p[1] > 0);
                    donut('db-sales-method', keep.map(p => p[0]), keep.map(p => p[1]), c, 'This month');
                }
                if (data.daily) {
                    draw('db-sales-daily', {
                        chart: { type: 'bar', height: 250 },
                        series: [{ name: 'Sales', data: data.daily.sales }],
                        colors: [c[0]],
                        plotOptions: { bar: { columnWidth: '70%', borderRadius: 2, borderRadiusApplication: 'end' } },
                        legend: { show: false },
                        xaxis: Object.assign({}, base.xaxis, { categories: data.daily.labels, labels: { style: { colors: ink, fontSize: '10px' }, rotate: -45, hideOverlappingLabels: true }, tickAmount: 10 }),
                    });
                }
                if (data.salesmen && data.salesmen.labels.length) {
                    hbar('db-sales-salesmen', data.salesmen.labels, [{ name: 'Sales', data: data.salesmen.values }], [c[0]], {
                        tooltip: { y: { formatter: (v, o) => rupees(v) + ' · ' + data.salesmen.trips[o.dataPointIndex] + ' trips' } },
                    });
                }
                if (data.products && data.products.labels.length) {
                    hbar('db-sales-products', data.products.labels, [{ name: 'Sales', data: data.products.values }], [c[2]]);
                }
                if (data.mix) {
                    draw('db-sales-mix', {
                        chart: { type: 'bar', height: 250, stacked: true },
                        series: [{ name: 'Cash', data: data.mix.cash }, { name: 'Credit', data: data.mix.credit }, { name: 'Bank transfer', data: data.mix.bank }],
                        colors: [c[2], c[1], c[0]],
                        plotOptions: { bar: { columnWidth: '55%', borderRadius: 3, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' } },
                        stroke: { show: true, width: 1, colors: ['#fff'] },
                        xaxis: Object.assign({}, base.xaxis, { categories: data.mix.labels }),
                    });
                }
                if (data.dow) {
                    draw('db-sales-dow', {
                        chart: { type: 'bar', height: 250 },
                        series: [{ name: 'Sales', data: data.dow.values }],
                        colors: [c[5]],
                        plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end' } },
                        legend: { show: false },
                        tooltip: { y: { formatter: (v, o) => rupees(v) + ' · ' + data.dow.trips[o.dataPointIndex] + ' settlements' } },
                        xaxis: Object.assign({}, base.xaxis, { categories: data.dow.labels.map(d => d.slice(0, 3)) }),
                    });
                }
                if (data.supplier && data.supplier.labels.length) {
                    hbar('db-sales-supplier', data.supplier.labels, [{ name: 'Sales', data: data.supplier.sales }, { name: 'Gross profit', data: data.supplier.profit }], [c[0], c[2]], {
                        chart: { type: 'bar', height: Math.max(190, data.supplier.labels.length * 44 + 80) },
                    });
                }
                if (data.credit && data.credit.labels.length) {
                    hbar('db-credit-breakdown', data.credit.labels, [{ name: 'Outstanding credit', data: data.credit.values }], [c[1]]);
                }
                if (data.creditSalesman && data.creditSalesman.labels.length) {
                    hbar('db-credit-salesman', data.creditSalesman.labels, [{ name: 'Outstanding credit', data: data.creditSalesman.values }], [c[3]]);
                }
                if (data.aging) {
                    draw('db-credit-aging', {
                        // Clicking a bar (except 0-30 days) opens the customer list for that age.
                        chart: { type: 'bar', height: 230, events: { dataPointSelection: (e, ctx, o) => { if (o.dataPointIndex > 0) { window.dispatchEvent(new CustomEvent('aging-bucket', { detail: o.dataPointIndex })); } } } },
                        series: [{ name: 'Outstanding', data: data.aging.values }],
                        tooltip: { y: { formatter: (v, o) => rupees(v) + ' · ' + data.aging.counts[o.dataPointIndex] + ' customers' } },
                        states: { hover: { filter: { type: 'darken', value: 0.85 } }, active: { filter: { type: 'none' } } },
                        colors: [c[2], c[3], c[1], '#b91c1c'],
                        plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end', distributed: true, dataLabels: { position: 'top' } } },
                        legend: { show: false },
                        dataLabels: { enabled: true, formatter: v => v > 0 ? short(v) : '', offsetY: -18, style: { fontSize: '11px', colors: ['#0f172a'] } },
                        xaxis: Object.assign({}, base.xaxis, { categories: data.aging.labels }),
                    });
                }
                if (data.flow) { bars('db-dist-flow', data.flow.labels, [{ name: 'Received (GRN)', data: data.flow.grn }, { name: 'Issued to vans', data: data.flow.issues }], [c[0], c[1]]); }
                if (data.purpay) {
                    draw('db-pur-pay', {
                        chart: { type: 'line', height: 290 },
                        series: [
                            { name: 'Supplier invoices', type: 'column', data: data.purpay.purchases },
                            { name: 'Payments (online)', type: 'column', data: data.purpay.payments },
                            { name: 'Balance (month end)', type: 'line', data: data.purpay.balance },
                        ],
                        colors: [c[0], c[2], '#b91c1c'],
                        stroke: { width: [0, 0, 3], curve: 'straight' },
                        markers: { size: [0, 0, 5], strokeWidth: 0 },
                        plotOptions: { bar: { columnWidth: '58%', borderRadius: 4, borderRadiusApplication: 'end' } },
                        dataLabels: { enabled: true, enabledOnSeries: [2], formatter: v => short(v), offsetY: -8, style: { fontSize: '11px', colors: ['#b91c1c'] }, background: { enabled: true, foreColor: '#fff', borderWidth: 0, padding: 3 } },
                        tooltip: { shared: true, intersect: false, y: { formatter: (v, o) => o.seriesIndex === 2 ? rupees(v) + (v < 0 ? ' (we owe)' : ' (advance)') : rupees(v) } },
                        xaxis: Object.assign({}, base.xaxis, { categories: data.purpay.labels }),
                    });
                }
                if (data.stockTop && data.stockTop.labels.length) {
                    hbar('db-inv-products', data.stockTop.labels, [{ name: 'Stock value', data: data.stockTop.values }], [c[0]]);
                }
                if (data.warehouse && data.warehouse.labels.length) {
                    hbar('db-inv-warehouse', data.warehouse.labels, [{ name: 'Stock value', data: data.warehouse.values }], [c[3]], {
                        tooltip: { y: { formatter: (v, o) => rupees(v) + ' · ' + data.warehouse.products[o.dataPointIndex] + ' products' } },
                    });
                }
                if (data.movement) {
                    draw('db-inv-movement', {
                        chart: { type: 'bar', height: 250, stacked: true },
                        series: [{ name: 'In', data: data.movement.inward }, { name: 'Out', data: data.movement.outward }],
                        colors: [c[2], c[1]],
                        plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' } },
                        xaxis: Object.assign({}, base.xaxis, { categories: data.movement.labels }),
                    });
                }
                if (data.rev) { bars('db-acc-rev', data.rev.labels, [{ name: 'Revenue', data: data.rev.revenue }, { name: 'COGS', data: data.rev.cogs }, { name: 'Expenses', data: data.rev.expenses }], [c[0], c[1], c[3]], 290); }
                if (data.je && data.je.values.length) { donut('db-acc-je', data.je.labels, data.je.values, [c[3], c[2], c[1], c[0], c[4]], 'Entries', whole); }
            });
        </script>
    @endpush
</div>
