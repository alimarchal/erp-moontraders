{{--
    Sales Settlements list (/sales-settlements). Same layout as the Goods Issues list:
    KPI cards that double as filters, status tabs, one search box with quick date
    ranges, removable filter chips, a sortable table with comfortable / compact
    density, a pager with rows-per-page and the period summary tables underneath.
    Query parameters are unchanged (filter[...], per_page); dates still default to today.
--}}
@php
    $authUser = auth()->user();
    $isAdmin = $authUser->hasAnyRole(['super-admin', 'admin']);
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', '-settlement_date');
    $tab = $filters['status'] ?? '';
    $today = now()->toDateString();
    $dateFrom = $filters['settlement_date_from'] ?? $today;
    $dateTo = $filters['settlement_date_to'] ?? $today;

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $status) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, ['status']);
        if ($status !== '') {
            $f['status'] = $status;
        }

        return $withFilters($f);
    };
    $rangeUrl = fn (string $from, string $to) => $withFilters(array_merge($filters, ['settlement_date_from' => $from, 'settlement_date_to' => $to]));
    $ranges = [
        'Today' => [$today, $today],
        'Yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
        'This week' => [now()->startOfWeek()->toDateString(), $today],
        'This month' => [now()->startOfMonth()->toDateString(), $today],
        'Last 30 days' => [now()->subDays(29)->toDateString(), $today],
        'All' => ['2020-01-01', $today],
    ];
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === '-'.$field ? $field : '-'.$field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');
    $sortNames = ['settlement_date' => 'Date', 'settlement_number' => 'Settlement number', 'total_sales' => 'Sales', 'created_at' => 'Created'];
    $sortField = ltrim($sort, '-');

    $names = [
        'supplier_id' => $suppliers->pluck('supplier_name', 'id'),
        'warehouse_id' => $warehouses->pluck('warehouse_name', 'id'),
        'vehicle_id' => $vehicles->pluck('registration_number', 'id'),
        'employee_id' => $employees->pluck('name', 'id'),
        'created_by' => $creators->pluck('name', 'id'),
    ];
    $chipLabels = [
        'search' => 'Search', 'settlement_number' => 'Settlement no.', 'supplier_id' => 'Supplier', 'warehouse_id' => 'Warehouse',
        'vehicle_id' => 'Vehicle', 'employee_id' => 'Salesman', 'created_by' => 'Created by', 'product_id' => 'Product',
    ];
    $chipValue = fn (string $key, $value) => isset($names[$key]) ? ($names[$key][$value] ?? $value) : $value;
    // Dates and status have their own controls, so they are not chips.
    $chips = \Illuminate\Support\Arr::except($filters, ['status', 'settlement_date_from', 'settlement_date_to']);
    $advancedKeys = ['warehouse_id', 'vehicle_id', 'employee_id', 'created_by'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $periodLabel = $dateFrom === $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
        : \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y');
    $statusLabels = ['draft' => 'Draft', 'verified' => 'Verified', 'posted' => 'Posted'];
    $statusTone = ['draft' => 'ak-status-amber', 'verified' => 'ak-status-green', 'posted' => 'ak-status-green'];
    $tabs = ['' => ['All', $stats['total']], 'draft' => ['Draft', $stats['draft']]];
    if ($stats['verified'] > 0 || $tab === 'verified') {
        $tabs['verified'] = ['Verified', $stats['verified']];
    }
    $tabs['posted'] = ['Posted', $stats['posted']];
    $grossMargin = $totals->total_sales_amount > 0 ? ($totals->total_gross_profit / $totals->total_sales_amount) * 100 : 0;
    $netMargin = $totals->total_sales_amount > 0 ? ($totals->total_net_profit / $totals->total_sales_amount) * 100 : 0;
    $shortWarehouse = fn ($name) => str_replace(['Warehouse - I', 'Warehouse - II', 'Warehouse'], ['W-I', 'W-II', 'W'], (string) $name);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><span>Sales Settlements</span>
                </nav>
                <h1 class="ak-title">Sales Settlements</h1>
                <p class="ak-sub">What each van sold, collected and spent &middot; {{ $periodLabel }}</p>
            </div>
            <div class="ak-head-actions">
                <a href="javascript:window.location.reload();" class="ak-btn ak-btn-outline" title="Refresh">Refresh</a>
                @if ($settlements->hasPages())
                    <a href="{{ request()->fullUrlWithQuery(['per_page' => 'all', 'page' => null, 'print' => 1]) }}" class="ak-btn ak-btn-outline" title="Print all {{ number_format($settlements->total()) }} settlements, not only this page">Print</a>
                @else
                    <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @endif
                @can('report-sales-daily-sales')
                    <a href="{{ route('reports.daily-sales.index', ['filter' => ['start_date' => $dateFrom, 'end_date' => $dateTo]]) }}" class="ak-btn ak-btn-outline">Daily sales report</a>
                @endcan
                @can('sales-settlement-create')
                    <a href="{{ route('sales-settlements.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> New settlement</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>
        @page { margin: 10mm; }
        .gl-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
        .gl-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
        .gl-filter-row .ak-field input, .gl-filter-row .ak-field select { height: 38px; box-sizing: border-box; }
        .gl-filter-row .ak-field input[type="date"] { padding: 0 10px; font-size: 14px; }
        .gl-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
        .gl-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
        .gl-ranges a:hover { background: var(--ak-soft); }
        .gl-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
        .gl-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 900px) { .gl-filter-row { grid-template-columns: minmax(220px, 2fr) 150px 150px {{ $isAdmin ? 'minmax(160px, 1.3fr)' : '' }} 90px auto; } }
        .gl-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
        @media (min-width: 900px) { .gl-advanced { grid-template-columns: repeat({{ $isAdmin ? 4 : 3 }}, minmax(0, 1fr)); } }
        .gl-table { min-width: 1100px; }
        .gl-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
        .gl-table > tbody > tr > td { border-bottom: 1px solid #e5e7eb; }
        .gl-table > tbody > tr:last-child > td { border-bottom: 0; }
        .gl-table .ak-primary-link, .gl-table td.ak-mono, .gl-nw { white-space: nowrap; }
        .gl-table .ak-actions { grid-template-columns: repeat(4, 32px); }
        .gl-loss { color: #b91c1c; }
        .gl-gain { color: #047857; }
        .gl-exp { color: #c2410c; }
        /* Searchable dropdowns (Select2) sized like the other filter fields */
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 6px !important; }
        .ak-filters .select2-container--focus .select2-selection--single, .ak-filters .select2-container--open .select2-selection--single { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
        .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
        .select2-search--dropdown .select2-search__field { border-radius: 6px; padding: 6px 8px; }
        .gl-salesmen { padding: 14px 16px 16px; }
        .gl-salesmen-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
        .gl-salesmen-head h2 { margin: 0; font-size: 14px; font-weight: 700; color: var(--ak-text); }
        .gl-salesmen-head h2 .ak-muted { font-weight: 400; font-size: 12.5px; }
        .gl-salesmen-grid { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); }
        .gl-salesman { display: flex; flex-direction: column; gap: 5px; padding: 10px 12px; border: 1px solid var(--ak-line); border-radius: 10px; color: var(--ak-text); text-decoration: none; font-size: 13px; background: #fff; box-shadow: var(--ak-shadow); }
        .gl-salesman:hover { border-color: #a5b4fc; background: #f8faff; }
        .gl-salesman.is-on { border-color: var(--ak-navy); box-shadow: 0 0 0 2px #c7d2fe; }
        .gl-salesman-top { display: flex; justify-content: space-between; gap: 8px; }
        .gl-salesman-top b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gl-salesman-top span { font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .gl-bar { height: 5px; border-radius: 99px; background: #eef2ff; overflow: hidden; }
        .gl-bar i { display: block; height: 100%; background: var(--ak-navy); border-radius: 99px; }
        .gl-late { color: #b45309; font-weight: 600; }
        /* Period summary tables */
        .ss-sum { display: grid; gap: 14px; grid-template-columns: 1fr; margin-top: 16px; }
        @media (min-width: 700px) { .ss-sum { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1100px) { .ss-sum { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .ss-sum .ak-card { padding: 14px 16px; margin: 0; }
        .ss-sum h2 { margin: 0 0 8px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--ak-muted); }
        .ss-sum table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .ss-sum td { padding: 6px 0; border-bottom: 1px solid #eef2f7; }
        .ss-sum tr:last-child td { border-bottom: 0; }
        .ss-sum td:last-child { text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ss-sum .ss-hint { display: block; font-size: 11.5px; color: var(--ak-muted); font-weight: 400; }
        @media print {
            .gl-salesmen, .gl-ranges { display: none !important; }
            .gl-table { min-width: 0 !important; }
            .gl-table tfoot td { position: static; }
            .ss-sum { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; gap: 8px; }
            .ss-sum .ak-card { break-inside: avoid; box-shadow: none; border: 1px solid #000; padding: 6px 8px; }
            .ss-sum table { font-size: 10.5px; }
            .ss-sum td { padding: 2px 0; }
        }
    </style>

    <div class="ak-page" x-data="{ confirm: { open: false, url: '', number: '' } }">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Sales Settlements &middot; {{ $periodLabel }}</div>
            <table class="ak-print-meta">
                <tr><th>Filters</th><td>{{ collect(\Illuminate\Support\Arr::except($filters, ['settlement_date_from', 'settlement_date_to']))->map(fn ($v, $k) => ($chipLabels[$k] ?? \Illuminate\Support\Str::headline($k)).': '.($k === 'status' ? ($statusLabels[$v] ?? $v) : $chipValue($k, $v)))->implode(' · ') ?: 'None' }}</td>
                    <th>Printed</th><td>{{ now()->format('d.m.Y H:i') }} by {{ $authUser->name }}</td></tr>
            </table>
        </div>

        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        {{-- KPI cards (also filters) --}}
        <section class="ak-kpis" aria-label="Settlement summary">
            <a href="{{ $tabUrl('') }}" class="ak-kpi{{ $tab === '' ? ' is-active' : '' }}" title="Rs {{ number_format($totals->total_sales_amount, 2) }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.31 4.31a11.95 11.95 0 0 1 5.81-5.52l2.74-1.22m0 0-5.94-2.28m5.94 2.28-2.28 5.94" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Sales</span>
                    <span class="ak-kpi-value">Rs {{ number_format($totals->total_sales_amount) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($settlements->total()) }} {{ \Illuminate\Support\Str::plural('settlement', $settlements->total()) }} &middot; credit {{ number_format($totals->total_credit_sales) }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('posted') }}" class="ak-kpi{{ $tab === 'posted' ? ' is-active' : '' }}" title="Rs {{ number_format($totals->total_net_profit, 2) }}">
                <span class="ak-kpi-icon {{ $totals->total_net_profit < 0 ? 'ak-tone-amber' : 'ak-tone-navy' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.13C3 12.5 3.5 12 4.13 12h2.25c.62 0 1.12.5 1.12 1.13v6.75C7.5 20.5 7 21 6.38 21H4.13A1.13 1.13 0 0 1 3 19.88v-6.75ZM9.75 8.63c0-.63.5-1.13 1.13-1.13h2.25c.62 0 1.12.5 1.12 1.13v11.25c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V8.63ZM16.5 4.13c0-.63.5-1.13 1.13-1.13h2.25C20.5 3 21 3.5 21 4.13v15.75c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V4.13Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Net profit</span>
                    <span class="ak-kpi-value {{ $totals->total_net_profit < 0 ? 'gl-loss' : '' }}">Rs {{ number_format($totals->total_net_profit) }}</span>
                    <span class="ak-kpi-hint">gross {{ number_format($grossMargin, 1) }}% &middot; net {{ number_format($netMargin, 1) }}% &middot; expenses {{ number_format($totals->total_expenses) }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('draft') }}" class="ak-kpi{{ $stats['draft'] ? ' ak-kpi-action' : '' }}{{ $tab === 'draft' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Drafts to post</span>
                    <span class="ak-kpi-value">{{ number_format($stats['draft']) }}</span>
                    <span class="ak-kpi-hint">{{ $stats['draft'] ? 'Rs '.number_format($stats['draft_sales']).' not in the books yet →' : 'nothing waiting' }}</span>
                </span>
            </a>
            @php $waitingCount = (int) ($waitingVans->issues ?? 0); @endphp
            @can('goods-issue-list')
                <a href="{{ route('goods-issues.index', ['filter' => ['settlement' => 'pending', 'issue_date_from' => '2020-01-01', 'issue_date_to' => $today]]) }}" class="ak-kpi{{ $waitingCount ? ' ak-kpi-action' : '' }}" title="Goods issues on vans with no posted settlement yet">
            @else
                <div class="ak-kpi">
            @endcan
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Vans not settled</span>
                    <span class="ak-kpi-value">{{ number_format($waitingCount) }}</span>
                    <span class="ak-kpi-hint">{{ $waitingCount ? 'Rs '.number_format((float) $waitingVans->value).' still on vans (any date) →' : 'every van is settled' }}</span>
                </span>
            @can('goods-issue-list')
                </a>
            @else
                </div>
            @endcan
        </section>

        @if ($bySalesman->isNotEmpty())
            <section class="ak-card gl-salesmen" aria-label="By salesman" x-data="{ all: false }">
                <div class="gl-salesmen-head">
                    <h2>By salesman <span class="ak-muted">&middot; sales, {{ $periodLabel }}</span></h2>
                    @if ($bySalesman->count() > 6)
                        <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="all = !all" x-text="all ? 'Show top 6' : 'Show all {{ $bySalesman->count() }}'"></button>
                    @endif
                </div>
                @php $maxSales = max((float) $bySalesman->max('sales'), 1); @endphp
                <div class="gl-salesmen-grid">
                    @foreach ($bySalesman as $row)
                        @php
                            $on = (string) ($filters['employee_id'] ?? '') === (string) $row->employee_id;
                            $rowMargin = (float) $row->sales > 0 ? ((float) $row->sales - (float) $row->cogs) / (float) $row->sales * 100 : 0;
                        @endphp
                        <a href="{{ $withFilters($on ? \Illuminate\Support\Arr::except($filters, 'employee_id') : array_merge($filters, ['employee_id' => $row->employee_id])) }}"
                            class="gl-salesman{{ $on ? ' is-on' : '' }}" @if ($loop->index >= 6) x-show="all" x-cloak @endif
                            title="{{ $on ? 'Remove this filter' : 'Show only '.$row->name }}">
                            <span class="gl-salesman-top">
                                <b>{{ $row->name }}</b>
                                <span>Rs {{ number_format((float) $row->sales) }}</span>
                            </span>
                            <span class="gl-bar"><i style="width: {{ round((float) $row->sales / $maxSales * 100, 1) }}%"></i></span>
                            <span class="ak-muted">{{ $row->settlements }} {{ \Illuminate\Support\Str::plural('settlement', (int) $row->settlements) }} &middot; GP {{ number_format($rowMargin, 1) }}%@if ($row->drafts > 0) &middot; <span class="gl-late">{{ $row->drafts }} draft</span>@endif</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ak-card" aria-label="Sales settlements">
            <div class="ak-tabs" role="tablist">
                @foreach ($tabs as $key => [$label, $count])
                    <a href="{{ $tabUrl($key) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === $key ? 'is-active' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('sales-settlements.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[status]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                @if (isset($filters['settlement_number'])) <input type="hidden" name="filter[settlement_number]" value="{{ $filters['settlement_number'] }}"> @endif
                @if (isset($filters['product_id'])) <input type="hidden" name="filter[product_id]" value="{{ $filters['product_id'] }}"> @endif
                @if (! $isAdmin && isset($filters['supplier_id'])) <input type="hidden" name="filter[supplier_id]" value="{{ $filters['supplier_id'] }}"> @endif

                <div class="gl-filter-row">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Settlement no., GI no., vehicle or salesman" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_from">From</label>
                        <input id="f_from" type="date" name="filter[settlement_date_from]" value="{{ $dateFrom }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_to">To</label>
                        <input id="f_to" type="date" name="filter[settlement_date_to]" value="{{ $dateTo }}">
                    </div>
                    @if ($isAdmin)
                        <div class="ak-field">
                            <label for="f_supplier">Supplier</label>
                            <select id="f_supplier" class="gl-select" name="filter[supplier_id]">
                                <option value="">All suppliers</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\SalesSettlementController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="gl-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>

                <div id="gl-advanced" class="gl-advanced" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_employee">Salesman</label>
                        <select id="f_employee" class="gl-select" name="filter[employee_id]">
                            <option value="">All salesmen</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_vehicle">Vehicle</label>
                        <select id="f_vehicle" class="gl-select" name="filter[vehicle_id]">
                            <option value="">All vehicles</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected((string) ($filters['vehicle_id'] ?? '') === (string) $vehicle->id)>{{ $vehicle->registration_number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_warehouse">Warehouse</label>
                        <select id="f_warehouse" class="gl-select" name="filter[warehouse_id]">
                            <option value="">All warehouses</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($isAdmin)
                        <div class="ak-field">
                            <label for="f_creator">Created by</label>
                            <select id="f_creator" class="gl-select" name="filter[created_by]">
                                <option value="">All users</option>
                                @foreach ($creators as $creator)
                                    <option value="{{ $creator->id }}" @selected((string) ($filters['created_by'] ?? '') === (string) $creator->id)>{{ $creator->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <nav class="gl-ranges" aria-label="Quick dates">
                    <span class="gl-ranges-label">Quick dates</span>
                    @foreach ($ranges as $label => [$from, $to])
                        <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </form>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['status', 'settlement_date_from', 'settlement_date_to'])) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($settlements->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('ss-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('ss-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($settlements->total()) }}</b> {{ \Illuminate\Support\Str::plural('settlement', $settlements->total()) }}
                            &middot; sales Rs <b>{{ number_format($totals->total_sales_amount) }}</b> &middot; amounts in Rs
                            &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Date' }}</b> ({{ str_starts_with($sort, '-') ? 'newest / highest first' : 'oldest / lowest first' }})
                        </p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Sales settlements table">
                        <table class="ak-dt gl-table">
                            <caption class="sr-only">Sales settlements, {{ $settlements->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c" style="width:48px">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('settlement_number') }}"><a href="{{ $sortUrl('settlement_number') }}">Settlement <span aria-hidden="true">{{ $sortMark('settlement_number') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('settlement_date') }}"><a href="{{ $sortUrl('settlement_date') }}">Date <span aria-hidden="true">{{ $sortMark('settlement_date') }}</span></a></th>
                                    <th scope="col">Goods issue</th>
                                    <th scope="col">Salesman &middot; vehicle</th>
                                    <th scope="col" class="ak-num" title="Cost of goods sold (Rs)">COGS</th>
                                    <th scope="col" class="ak-num" aria-sort="{{ $ariaSort('total_sales') }}"><a href="{{ $sortUrl('total_sales') }}">Sales <span aria-hidden="true">{{ $sortMark('total_sales') }}</span></a></th>
                                    <th scope="col" class="ak-num">Expenses</th>
                                    <th scope="col" class="ak-num">Net profit</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-c ak-sticky-end no-print"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($settlements as $settlement)
                                    @php $netProfit = $settlement->calculated_net_profit; @endphp
                                    <tr>
                                        <td class="ak-c ak-muted">{{ $settlements->firstItem() + $loop->index }}</td>
                                        <td data-label="Settlement">
                                            <a href="{{ route('sales-settlements.show', $settlement) }}" class="ak-primary-link">{{ $settlement->settlement_number }}</a>
                                            <div class="ak-muted">{{ $settlement->supplier->supplier_name ?? '—' }}{{ $settlement->warehouse ? ' · '.$shortWarehouse($settlement->warehouse->warehouse_name) : '' }}</div>
                                        </td>
                                        <td class="ak-mono" data-label="Date">
                                            {{ $settlement->settlement_date?->format('d M Y') }}
                                            <div class="ak-muted">{{ $settlement->posted_at ? 'posted '.$settlement->posted_at->format('h:i A') : $settlement->created_at?->diffForHumans() }}</div>
                                            @if ($isAdmin)<div class="ak-muted no-print">by {{ $settlement->creator->name ?? 'N/A' }}</div>@endif
                                        </td>
                                        <td class="gl-nw" data-label="Goods issue">
                                            @if ($settlement->goodsIssue)
                                                @can('goods-issue-list')
                                                    <a href="{{ route('goods-issues.show', $settlement->goodsIssue) }}" class="ak-primary-link" style="font-weight:500">{{ $settlement->goodsIssue->issue_number }}</a>
                                                @else
                                                    {{ $settlement->goodsIssue->issue_number }}
                                                @endcan
                                            @else
                                                <span class="ak-muted">—</span>
                                            @endif
                                        </td>
                                        <td data-label="Salesman · vehicle">
                                            <span class="ak-strong">{{ $settlement->employee->name ?? 'N/A' }}</span>
                                            <div class="ak-muted">{{ $settlement->vehicle->registration_number ?? $settlement->vehicle->vehicle_number ?? 'N/A' }}</div>
                                        </td>
                                        <td class="ak-num" data-label="COGS (Rs)">{{ number_format($settlement->calculated_total_cogs, 2) }}</td>
                                        <td class="ak-num ak-strong" data-label="Sales (Rs)">{{ number_format($settlement->calculated_total_sales_amount, 2) }}</td>
                                        <td class="ak-num gl-exp" data-label="Expenses (Rs)">{{ number_format($settlement->calculated_total_expenses, 2) }}</td>
                                        <td class="ak-num ak-strong {{ $netProfit > 0 ? 'gl-gain' : 'gl-loss' }}" data-label="Net profit (Rs)">{{ number_format($netProfit, 2) }}</td>
                                        <td data-label="Status">
                                            <span class="ak-status {{ $statusTone[$settlement->status] ?? 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ $statusLabels[$settlement->status] ?? \Illuminate\Support\Str::headline($settlement->status) }}</span>
                                        </td>
                                        <td class="ak-sticky-end no-print" data-label="">
                                            <div class="ak-actions">
                                                <a href="{{ route('sales-settlements.show', $settlement) }}" class="ak-icon ak-icon-view" title="View" aria-label="View {{ $settlement->settlement_number }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                </a>
                                                <a href="{{ route('sales-settlements.show', [$settlement, 'layout' => 'print2', 'format' => 'pdf', 'orientation' => 'portrait']) }}" class="ak-icon" title="Download sheet (PDF)" aria-label="Download {{ $settlement->settlement_number }} as PDF">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                                </a>
                                                <span class="ak-slot">
                                                    @if ($settlement->status === 'draft')
                                                        @can('sales-settlement-edit')
                                                            <a href="{{ route('sales-settlements.edit', $settlement) }}" class="ak-icon" title="Edit draft" aria-label="Edit {{ $settlement->settlement_number }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                </span>
                                                <span class="ak-slot">
                                                    @if ($settlement->status === 'draft')
                                                        @can('sales-settlement-delete')
                                                            <button type="button" class="ak-icon ak-icon-danger" title="Delete draft" aria-label="Delete {{ $settlement->settlement_number }}"
                                                                @click="confirm = { open: true, url: @js(route('sales-settlements.destroy', $settlement)), number: @js($settlement->settlement_number) }">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                                                            </button>
                                                        @endcan
                                                    @endif
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5">Total &middot; {{ number_format($settlements->total()) }} {{ \Illuminate\Support\Str::plural('settlement', $settlements->total()) }}{{ $settlements->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td class="ak-num">{{ number_format($totals->total_cogs, 2) }}</td>
                                    <td class="ak-num">{{ number_format($totals->total_sales_amount, 2) }}</td>
                                    <td class="ak-num">{{ number_format($totals->total_expenses, 2) }}</td>
                                    <td class="ak-num {{ $totals->total_net_profit < 0 ? 'gl-loss' : '' }}">{{ number_format($totals->total_net_profit, 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($settlements->firstItem()) }}–{{ number_format($settlements->lastItem()) }}</b> of <b>{{ number_format($settlements->total()) }}</b> settlements</p>
                    <div class="ak-pager-right">
                        <div>{{ $settlements->onEachSide(1)->links() }}</div>
                    </div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No sales settlements for {{ $periodLabel }}{{ $chips || $tab ? ' with these filters' : '' }}</h2>
                    <p>Pick a wider date range above (for example “This month” or “All”), or remove a filter.</p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        <a href="{{ $rangeUrl(now()->startOfMonth()->toDateString(), $today) }}" class="ak-btn ak-btn-outline">Show this month</a>
                        @can('sales-settlement-create') <a href="{{ route('sales-settlements.create') }}" class="ak-btn ak-btn-primary">＋ New settlement</a> @endcan
                    </div>
                </div>
            @endif
        </section>

        {{-- Period summary (all pages, same filters) --}}
        @if ($settlements->count() > 0)
            <section class="ss-sum" aria-label="Period summary">
                <div class="ak-card">
                    <h2>Quantity</h2>
                    <table>
                        <tr><td>Sold</td><td>{{ number_format($totals->total_sold_qty, 2) }}</td></tr>
                        <tr><td>Returned</td><td>{{ number_format($totals->total_returned_qty, 2) }}</td></tr>
                        <tr><td>Shortage</td><td class="{{ $totals->total_shortage_qty > 0 ? 'gl-loss' : '' }}">{{ number_format($totals->total_shortage_qty, 2) }}</td></tr>
                    </table>
                </div>
                <div class="ak-card">
                    <h2>Cash management</h2>
                    <table>
                        <tr><td>Cash sales (gross)<span class="ss-hint">sales − credit − cheques − bank</span></td><td>{{ number_format($totals->total_cash_sales, 2) }}</td></tr>
                        <tr><td>Expenses</td><td>{{ number_format($totals->total_expenses, 2) }}</td></tr>
                        <tr><td>To deposit<span class="ss-hint">physical cash counted</span></td><td class="gl-gain">{{ number_format($totals->total_cash_deposit, 2) }}</td></tr>
                        <tr><td>Bank slips<span class="ss-hint">deposited by salesmen</span></td><td>{{ number_format($totals->total_bank_slips, 2) }}</td></tr>
                    </table>
                </div>
                <div class="ak-card">
                    <h2>Payment methods</h2>
                    <table>
                        <tr><td>Cash</td><td>{{ number_format($totals->total_cash_sales, 2) }}</td></tr>
                        <tr><td>Credit</td><td>{{ number_format($totals->total_credit_sales, 2) }}</td></tr>
                        <tr><td>Recoveries</td><td>{{ number_format($totals->total_recoveries, 2) }}</td></tr>
                        <tr><td>Cheque</td><td>{{ number_format($totals->total_cheque_sales, 2) }}</td></tr>
                        <tr><td>Bank transfer</td><td>{{ number_format($totals->total_bank_transfer, 2) }}</td></tr>
                    </table>
                </div>
                <div class="ak-card">
                    <h2>Profitability</h2>
                    <table>
                        <tr><td>Total sales</td><td>{{ number_format($totals->total_sales_amount, 2) }}</td></tr>
                        <tr><td>Gross profit</td><td>{{ number_format($totals->total_gross_profit, 2) }}</td></tr>
                        <tr><td>GP margin</td><td>{{ number_format($grossMargin, 2) }}%</td></tr>
                        <tr><td>Net profit</td><td class="{{ $totals->total_net_profit < 0 ? 'gl-loss' : 'gl-gain' }}">{{ number_format($totals->total_net_profit, 2) }}</td></tr>
                        <tr><td>NP margin</td><td>{{ number_format($netMargin, 2) }}%</td></tr>
                    </table>
                </div>
            </section>
        @endif

        {{-- Delete draft confirmation --}}
        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="gl-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon ak-pill-red" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="gl-confirm-title">Delete draft <span x-text="confirm.number"></span>?</h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155">The draft settlement and all its entries are removed and its goods issue can be settled again. This cannot be undone.</p>
                    </div>
                </div>
                <form method="POST" :action="confirm.url" class="uf-modal-foot">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="submit" class="ak-btn ak-btn-danger-outline">Delete draft</button>
                </form>
            </div>
        </div>
    </div>
    @push('scripts')
        @if (request()->boolean('print'))
            <script>window.addEventListener('load', () => window.print());</script>
        @endif
        <script>
            // Searchable dropdowns: type to find a supplier, salesman, vehicle, warehouse or user.
            $(function () {
                $('.gl-select').each(function () {
                    const $select = $(this);
                    $select.select2({
                        width: '100%',
                        placeholder: $select.find('option[value=""]').text() || 'Select',
                        allowClear: $select.find('option[value=""]').length > 0,
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
