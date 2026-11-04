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
    $tab = ($filters['status'] ?? '') === 'all' ? '' : ($filters['status'] ?? '');
    $today = now()->toDateString();
    $dateFrom = $filters['settlement_date_from'] ?? $today;
    $dateTo = $filters['settlement_date_to'] ?? $today;

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $status) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, ['status']);
        $f['status'] = $status !== '' ? $status : 'all';

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
    $sortNames = ['settlement_date' => 'Date', 'settlement_number' => 'Settlement number', 'total_sales' => 'Sales', 'net_profit' => 'Net profit', 'created_at' => 'Created'];
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
    $activeFilterCount = count($chips);
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
    $lossCount = $settlements->getCollection()->filter(fn ($s) => $s->calculated_net_profit < 0)->count();
    // SETTLE-2026-2725 -> SS-2725 (the year is added only for older years, e.g. SS-25-2725); the full number stays in the tooltip and in search.
    $shortNumber = fn (string $n) => preg_match('/^SETTLE-(\d{4})-(\d+)$/', $n, $m) ? 'SS-'.($m[1] === now()->format('Y') ? '' : substr($m[1], 2).'-').$m[2] : $n;
    $shortGi = fn (string $n) => preg_replace('/^GI-\d{4}-0*/', 'GI-', $n);
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
                <button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('gl-toggle-filters')" aria-controls="gl-filter-panel" title="Show or hide the filters">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                    Filters @if($activeFilterCount)<span class="ak-count ak-count-dark">{{ $activeFilterCount }}</span>@endif
                </button>
                <a href="javascript:window.location.reload();" class="ak-btn ak-btn-outline" title="Refresh" aria-label="Refresh">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                </a>
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
        .gl-ranges a.gl-clear { border-color: #fecaca; color: #b91c1c; background: #fff; }
        .gl-ranges a.gl-clear:hover { background: #fef2f2; }
        #gl-filter-panel { overflow: hidden; }
        .gl-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
        .gl-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 900px) { .gl-filter-row { grid-template-columns: minmax(220px, 2fr) 150px 150px {{ $isAdmin ? 'minmax(160px, 1.3fr)' : '' }} 90px auto; } }
        .gl-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
        @media (min-width: 900px) { .gl-advanced { grid-template-columns: repeat({{ $isAdmin ? 4 : 3 }}, minmax(0, 1fr)); } }
        .gl-table { min-width: 820px; }
        .gl-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #16a34a; box-shadow: 0 0 0 3px #dcfce7; vertical-align: middle; margin-left: 4px; }
        .gl-dot-text { display: none; }
        @media screen and (max-width: 767px) { .gl-dot-text { display: inline; font-size: 13px; font-weight: 600; color: #15803d; margin-left: 8px; } }
        @media print { .gl-dot { display: none; } .gl-dot-text { display: inline; } }
        /* Date, Status and Actions shrink to their content so the spare width goes to the wide columns, not between Status and Actions. */
        @media (min-width: 768px) {
            .gl-table thead th:nth-last-child(-n+3), .gl-table tbody td:nth-last-child(-n+3) { width: 1%; white-space: nowrap; }
            .gl-table thead th:nth-last-child(2), .gl-table tbody td:nth-last-child(2) { text-align: center; padding-left: 18px; padding-right: 18px; }
            .gl-table thead th:nth-last-child(1), .gl-table tbody td:nth-last-child(1) { padding-left: 8px; }
        }
        .gl-act { display: flex; align-items: center; justify-content: flex-end; gap: 4px; white-space: nowrap; }
        .gl-view { gap: 6px; }
        .gl-more { width: 32px; height: 32px; }
        .gl-more svg { width: 18px; height: 18px; }
        .gl-menu { position: fixed; z-index: 70; min-width: 190px; padding: 4px; background: #fff; border: 1px solid var(--ak-border); border-radius: 10px; box-shadow: 0 10px 25px rgba(15, 23, 42, .15); }
        .gl-menu a, .gl-menu button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 12px; border: 0; border-radius: 6px; background: transparent; color: var(--ak-text); font-size: 14px; text-align: left; text-decoration: none; cursor: pointer; }
        .gl-menu a:hover, .gl-menu button:hover { background: var(--ak-soft); }
        .gl-menu svg { width: 17px; height: 17px; flex: none; color: var(--ak-muted); }
        .gl-menu hr { margin: 4px 0; border: 0; border-top: 1px solid #e5e7eb; }
        .gl-menu .is-danger, .gl-menu .is-danger svg { color: #b91c1c; }
        .gl-menu .is-danger:hover { background: #fef2f2; }
        .gl-ranges-inline { margin-top: 0; }
        .gl-tab-warn:not(.is-active) .ak-count { background: #fef3c7; color: #b45309; }
        /* Tabs stay in view while the page scrolls */
        section[aria-label="Sales settlements"] > .ak-tabs { position: sticky; top: 0; z-index: 25; background: #fff; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }
        a.ak-kpi-action:hover .ak-kpi-hint { text-decoration: underline; text-underline-offset: 2px; }
        @media (min-width: 768px) { .gl-table th.ak-num, .gl-table td.ak-num { min-width: 104px; padding-left: 26px; } }
        .gl-gi { color: inherit; text-decoration: none; }
        .gl-gi:hover { color: var(--ak-navy); text-decoration: underline; }
        .ak-dt-scroll { max-height: calc(100vh - 190px); min-height: 18rem; }
        .gl-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
        .gl-table > tbody > tr > td { border-bottom: 1px solid #e5e7eb; }
        .gl-table > tbody > tr:last-child > td { border-bottom: 0; }
        .gl-table .ak-primary-link, .gl-table td.ak-mono, .gl-nw { white-space: nowrap; }
        .gl-table .ak-actions { grid-template-columns: repeat(4, 32px); }
        .gl-lossrow > td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
        .gl-lossrow > td { background: #fef2f2; }
        .ak-kpis { gap: 10px; }
        .ak-kpis .ak-kpi { padding: 10px 14px; gap: 10px; align-items: center; }
        .ak-kpis .ak-kpi-icon { width: 34px; height: 34px; }
        .ak-kpis .ak-kpi-icon svg { width: 18px; height: 18px; }
        .ak-kpis .ak-kpi-value { font-size: 19px; }
        .ak-kpis .ak-kpi-label { font-size: 12px; }
        .ak-kpis .ak-kpi-hint { font-size: 11.5px; }
        .gl-draft > td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
        .gl-draft > td { background: #fffbeb; }
        .gl-table > tbody > tr:hover > td { background: #f1f5f9; }
        .gl-emp { color: inherit; text-decoration: none; }
        .gl-emp:hover { color: var(--ak-navy); text-decoration: underline; text-underline-offset: 3px; }
        .gl-loss { color: #b91c1c; }
        .gl-gain { color: #047857; }
        .gl-exp { color: #c2410c; }
        /* Searchable dropdowns (Select2) sized like the other filter fields */
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
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
        @media print {
            .gl-salesmen, .gl-ranges, .ak-filters, .ak-tabs { display: none !important; }
            .gl-table { min-width: 0 !important; }
            .gl-table tfoot td { position: static; }
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

        <section id="gl-filter-panel" class="ak-card" aria-label="Filters" x-data="{ open: false }" @gl-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="display:none">
            <form method="GET" action="{{ route('sales-settlements.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                <input type="hidden" name="filter[status]" value="{{ $tab !== '' ? $tab : 'all' }}">
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
                                @keydown.window.f="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $dispatch('gl-toggle-filters'); }"
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
                    <a href="{{ route('sales-settlements.index') }}" class="gl-clear" title="Remove every filter and go back to today">&times; Clear</a>
                </nav>
            </form>

        </section>

        {{-- KPI cards (also filters) --}}
        <section class="ak-kpis" aria-label="Settlement summary">
            <a href="{{ $tabUrl('') }}" class="ak-kpi" title="Rs {{ number_format($totals->total_sales_amount, 2) }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.31 4.31a11.95 11.95 0 0 1 5.81-5.52l2.74-1.22m0 0-5.94-2.28m5.94 2.28-2.28 5.94" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Sales</span>
                    <span class="ak-kpi-value">Rs {{ number_format($totals->total_sales_amount) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($settlements->total()) }} {{ \Illuminate\Support\Str::plural('settlement', $settlements->total()) }} &middot; credit {{ number_format($totals->total_credit_sales) }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('posted') }}" class="ak-kpi" title="Rs {{ number_format($totals->total_net_profit, 2) }}">
                <span class="ak-kpi-icon {{ $totals->total_net_profit < 0 ? 'ak-tone-amber' : 'ak-tone-navy' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.13C3 12.5 3.5 12 4.13 12h2.25c.62 0 1.12.5 1.12 1.13v6.75C7.5 20.5 7 21 6.38 21H4.13A1.13 1.13 0 0 1 3 19.88v-6.75ZM9.75 8.63c0-.63.5-1.13 1.13-1.13h2.25c.62 0 1.12.5 1.12 1.13v11.25c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V8.63ZM16.5 4.13c0-.63.5-1.13 1.13-1.13h2.25C20.5 3 21 3.5 21 4.13v15.75c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V4.13Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Net profit</span>
                    <span class="ak-kpi-value {{ $totals->total_net_profit < 0 ? 'gl-loss' : '' }}">Rs {{ number_format($totals->total_net_profit) }}</span>
                    <span class="ak-kpi-hint">gross {{ number_format($grossMargin, 1) }}% &middot; net {{ number_format($netMargin, 1) }}% &middot; expenses {{ number_format($totals->total_expenses) }}@if ($lossCount) &middot; <b class="gl-loss" title="Settlements with a loss{{ $settlements->hasPages() ? ' on this page' : '' }}">{{ $lossCount }} {{ $lossCount === 1 ? 'loss' : 'losses' }}</b>@endif</span>
                </span>
            </a>
            <a href="{{ $tabUrl('draft') }}" class="ak-kpi{{ $stats['draft'] ? ' ak-kpi-action' : '' }}">
                <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Drafts to post</span>
                    <span class="ak-kpi-value">{{ number_format($stats['draft']) }}</span>
                    <span class="ak-kpi-hint">{{ $stats['draft'] ? 'Rs '.number_format($stats['draft_sales']).' unposted · Review →' : 'nothing waiting' }}</span>
                </span>
            </a>
            @php $waitingCount = (int) ($waitingVans->issues ?? 0); @endphp
            @can('goods-issue-list')
                <a href="{{ route('goods-issues.index', ['filter' => ['settlement' => 'pending', 'issue_date_from' => '2020-01-01', 'issue_date_to' => $today]]) }}" class="ak-kpi{{ $waitingCount ? ' ak-kpi-action' : '' }}" title="Goods issues on vans with no posted settlement yet (any date)">
            @else
                <div class="ak-kpi">
            @endcan
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Vans not settled</span>
                    <span class="ak-kpi-value">{{ number_format($waitingCount) }}</span>
                    <span class="ak-kpi-hint">{{ $waitingCount ? 'Rs '.number_format((float) $waitingVans->value).' on vans · View →' : 'every van is settled' }}</span>
                </span>
            @can('goods-issue-list')
                </a>
            @else
                </div>
            @endcan
        </section>

        <section class="ak-card" aria-label="Sales settlements">
            <div class="ak-tabs" role="tablist">
                @foreach ($tabs as $key => [$label, $count])
                    <a href="{{ $tabUrl($key) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === $key ? 'is-active' : '' }}{{ $key === 'draft' && $count > 0 ? ' gl-tab-warn' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

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
                        <p><b>{{ number_format($settlements->total()) }}</b> {{ \Illuminate\Support\Str::plural('settlement', $settlements->total()) }} &middot; Rs <b>{{ number_format($totals->total_sales_amount) }}</b></p>
                        <nav class="gl-ranges gl-ranges-inline" aria-label="Quick dates">
                            @php $matched = false; @endphp
                            @foreach ($ranges as $label => [$from, $to])
                                @php $on = $dateFrom === $from && $dateTo === $to; $matched = $matched || $on; @endphp
                                <a href="{{ $rangeUrl($from, $to) }}" @if ($on) aria-current="true" @endif>{{ $label }}</a>
                            @endforeach
                            @unless ($matched)<a href="#" aria-current="true" onclick="return false" title="Custom period (change it in Filters)">{{ $periodLabel }}</a>@endunless
                        </nav>
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
                                    <th scope="col" aria-sort="{{ $ariaSort('settlement_number') }}"><a href="{{ $sortUrl('settlement_number') }}">Settlement <span aria-hidden="true">{{ $sortMark('settlement_number') }}</span></a></th>
                                    <th scope="col">Salesman &middot; vehicle</th>
                                    <th scope="col" class="ak-num" aria-sort="{{ $ariaSort('total_sales') }}" title="Sales, with the cost of goods sold (COGS) underneath"><a href="{{ $sortUrl('total_sales') }}">Sales <span aria-hidden="true">{{ $sortMark('total_sales') }}</span></a></th>
                                    <th scope="col" class="ak-num">Expenses</th>
                                    <th scope="col" class="ak-num" aria-sort="{{ $ariaSort('net_profit') }}"><a href="{{ $sortUrl('net_profit') }}">Net profit <span aria-hidden="true">{{ $sortMark('net_profit') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('settlement_date') }}"><a href="{{ $sortUrl('settlement_date') }}">Date <span aria-hidden="true">{{ $sortMark('settlement_date') }}</span></a></th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-sticky-end no-print" style="text-align:right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($settlements as $settlement)
                                    @php
                                        $netProfit = $settlement->calculated_net_profit;
                                        $rowSales = (float) $settlement->calculated_total_sales_amount;
                                        $rowNet = $rowSales > 0 ? $netProfit / $rowSales * 100 : 0;
                                    @endphp
                                    <tr class="{{ $settlement->status === 'draft' ? 'gl-draft' : ($netProfit < 0 ? 'gl-lossrow' : '') }}">
                                        <td data-label="Settlement">
                                            <a href="{{ route('sales-settlements.show', $settlement) }}" class="ak-primary-link" title="{{ $settlement->settlement_number }}">{{ $shortNumber($settlement->settlement_number) }}</a>
                                            @if ($settlement->goodsIssue)
                                                <div class="ak-muted gl-nw" title="Goods issue {{ $settlement->goodsIssue->issue_number }}">
                                                    @can('goods-issue-list')<a href="{{ route('goods-issues.show', $settlement->goodsIssue) }}" class="gl-gi">{{ $shortGi($settlement->goodsIssue->issue_number) }}</a>@else{{ $shortGi($settlement->goodsIssue->issue_number) }}@endcan
                                                </div>
                                            @endif
                                        </td>
                                        <td data-label="Salesman · vehicle">
                                            @if ($settlement->employee_id)<a href="{{ $withFilters(array_merge($filters, ['employee_id' => $settlement->employee_id])) }}" class="ak-strong gl-emp" title="Show only {{ $settlement->employee->name ?? '' }}">{{ $settlement->employee->name ?? 'N/A' }}</a>@else<span class="ak-strong">N/A</span>@endif
                                            <div class="ak-muted" title="{{ $settlement->supplier->supplier_name ?? '' }}{{ $settlement->warehouse ? ' · '.$settlement->warehouse->warehouse_name : '' }}">{{ $settlement->vehicle->registration_number ?? $settlement->vehicle->vehicle_number ?? 'N/A' }} &middot; {{ $settlement->supplier?->short_name ?: \Illuminate\Support\Str::limit($settlement->supplier->supplier_name ?? '—', 12, '') }}{{ $settlement->warehouse ? ' · '.$shortWarehouse($settlement->warehouse->warehouse_name) : '' }}</div>
                                        </td>
                                        <td class="ak-num" data-label="Sales (Rs)" title="Sales {{ number_format($settlement->calculated_total_sales_amount, 2) }} / COGS {{ number_format($settlement->calculated_total_cogs, 2) }}">
                                            <span class="ak-strong">{{ number_format($settlement->calculated_total_sales_amount) }}</span>
                                            <div class="ak-muted" style="font-weight:400">cost {{ number_format($settlement->calculated_total_cogs) }}</div>
                                        </td>
                                        <td class="ak-num gl-exp" data-label="Expenses (Rs)" title="{{ number_format($settlement->calculated_total_expenses, 2) }}">{{ number_format($settlement->calculated_total_expenses) }}</td>
                                        <td class="ak-num ak-strong {{ $netProfit > 0 ? 'gl-gain' : 'gl-loss' }}" data-label="Net profit (Rs)" title="{{ number_format($netProfit, 2) }}">{{ number_format($netProfit) }}<div class="ak-muted" style="font-weight:400">{{ number_format($rowNet, 1) }}%</div></td>
                                        @php
                                            $postedAt = $settlement->posted_at;
                                            $stamp = $postedAt
                                                ? ($postedAt->isSameDay($settlement->settlement_date) ? $postedAt->format('h:i A') : $postedAt->format('d M, h:i A'))
                                                : $settlement->created_at?->diffForHumans();
                                            $creatorName = $settlement->creator->name ?? 'N/A';
                                        @endphp
                                        <td class="ak-mono gl-nw" data-label="Date">
                                            {{ $settlement->settlement_date?->format('d M Y') }}
                                            <div class="ak-muted" title="{{ $postedAt ? 'Posted '.$postedAt->format('d M Y h:i A') : 'Created '.$settlement->created_at?->format('d M Y h:i A') }} by {{ $creatorName }}">{{ $postedAt ? 'Posted' : 'Created' }} {{ $stamp }}@if ($isAdmin)<span class="no-print"> &middot; {{ $creatorName }}</span>@endif</div>
                                        </td>
                                        <td data-label="Status">
                                            @if ($settlement->status === 'posted')
                                                <span class="gl-dot" title="Posted{{ $settlement->posted_at ? ' '.$settlement->posted_at->format('d M Y h:i A') : '' }}" role="img" aria-label="Posted"></span><span class="gl-dot-text">Posted</span>
                                            @else
                                                <span class="ak-status {{ $statusTone[$settlement->status] ?? 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ $statusLabels[$settlement->status] ?? \Illuminate\Support\Str::headline($settlement->status) }}</span>
                                            @endif
                                            @if ($settlement->status === 'draft' && $rowSales <= 0)<div class="ak-muted gl-loss" title="This draft has no sold items yet" style="font-weight:600">No items</div>@endif
                                        </td>
                                        <td class="ak-sticky-end no-print" data-label="">
                                            <div class="gl-act" x-data="{ open: false, pos: '',
                                                toggle(e) {
                                                    const r = e.currentTarget.getBoundingClientRect();
                                                    const flip = r.bottom + 190 > window.innerHeight;
                                                    this.pos = 'right:' + (window.innerWidth - r.right) + 'px;' + (flip ? 'bottom:' + (window.innerHeight - r.top + 4) + 'px' : 'top:' + (r.bottom + 4) + 'px');
                                                    this.open = ! this.open;
                                                } }" @keydown.escape.window="open = false" @scroll.window="open = false" @resize.window="open = false">
                                                <a href="{{ route('sales-settlements.show', $settlement) }}" class="ak-btn ak-btn-outline ak-btn-sm gl-view" title="Open {{ $settlement->settlement_number }}" aria-label="View {{ $settlement->settlement_number }}">
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    View
                                                </a>
                                                <button type="button" class="ak-icon gl-more" @click="toggle($event)" :aria-expanded="open" aria-haspopup="menu" title="More actions" aria-label="More actions for {{ $settlement->settlement_number }}">
                                                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="12" cy="19" r="1.7" /></svg>
                                                </button>
                                                <div class="gl-menu" role="menu" x-show="open" x-cloak style="display:none" :style="pos" @click.outside="open = false">
                                                    <a role="menuitem" href="{{ route('sales-settlements.show', [$settlement, 'layout' => 'print2', 'format' => 'pdf', 'orientation' => 'portrait']) }}">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                                        Download PDF
                                                    </a>
                                                    @if ($settlement->status === 'draft')
                                                        @can('sales-settlement-edit')
                                                            <a role="menuitem" href="{{ route('sales-settlements.edit', $settlement) }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                                Edit draft
                                                            </a>
                                                        @endcan
                                                        @can('sales-settlement-delete')
                                                            <hr>
                                                            <button type="button" role="menuitem" class="is-danger"
                                                                @click="open = false; confirm = { open: true, url: @js(route('sales-settlements.destroy', $settlement)), number: @js($settlement->settlement_number) }">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                                                                Delete draft
                                                            </button>
                                                        @endcan
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">Total &middot; {{ number_format($settlements->total()) }} {{ \Illuminate\Support\Str::plural('settlement', $settlements->total()) }}{{ $settlements->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td class="ak-num" title="Sales {{ number_format($totals->total_sales_amount, 2) }} / COGS {{ number_format($totals->total_cogs, 2) }}">{{ number_format($totals->total_sales_amount) }}<div class="ak-muted" style="font-weight:400">cost {{ number_format($totals->total_cogs) }}</div></td>
                                    <td class="ak-num">{{ number_format($totals->total_expenses) }}</td>
                                    <td class="ak-num {{ $totals->total_net_profit < 0 ? 'gl-loss' : '' }}">{{ number_format($totals->total_net_profit) }}</td>
                                    <td colspan="3"></td>
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
