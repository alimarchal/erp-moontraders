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
        <x-page-header title="Sales Settlements" :createRoute="route('sales-settlements.create')" createLabel="New settlement"
            createPermission="sales-settlement-create" :showSearch="true" :showRefresh="true" backRoute="dashboard">
            <x-slot name="afterFilter">
                {{-- Help sits next to Filters, like on the Goods Issues list --}}
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('ss-help')" aria-haspopup="dialog" aria-label="Help in Urdu" title="How settlements work, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
            <x-slot name="actions">
                <x-register-header-actions page="sales-settlements" :help="false" :excel-url="request()->fullUrlWithQuery(['export' => 'xlsx', 'page' => null])" />
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
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
        .gl-tab-warn:not(.is-active) .ak-count { background: #fee2e2; color: #b91c1c; }
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
        .gl-draft > td:first-child { box-shadow: inset 4px 0 0 #dc2626; }
        .gl-draft > td { background: #fef2f2; }
        .gl-draft-alert { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px; border: 1px solid #fca5a5; border-left: 5px solid #dc2626; border-radius: 10px; background: #fef2f2; color: #991b1b; font-size: 14px; }
        .gl-draft-alert b { color: #7f1d1d; }
        .gl-draft-alert a { margin-left: auto; padding: 6px 14px; border-radius: 8px; background: #dc2626; color: #fff; font-weight: 600; text-decoration: none; }
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

        @if (($draftAlert->drafts ?? 0) > 0)
            <div class="gl-draft-alert" role="alert">
                <span><b>{{ number_format($draftAlert->drafts) }} draft {{ \Illuminate\Support\Str::plural('settlement', $draftAlert->drafts) }} not posted yet</b> &mdash; oldest is from {{ \Illuminate\Support\Carbon::parse($draftAlert->oldest)->format('d M Y') }}. Drafts always show here, whatever the date.</span>
                <a href="{{ $tabUrl('draft') }}">Review drafts</a>
            </div>
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
                                    <th scope="col" class="gl-no">#</th>
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
                                        $peek = [
                                            'number' => $settlement->settlement_number,
                                            'url' => route('sales-settlements.show', $settlement),
                                            'stage' => $statusLabels[$settlement->status] ?? \Illuminate\Support\Str::headline($settlement->status),
                                            'tone' => ['posted' => 'green', 'draft' => 'amber', 'verified' => 'blue'][$settlement->status] ?? 'grey',
                                            'issue' => $settlement->goodsIssue->issue_number ?? null,
                                            'supplier' => $settlement->supplier->supplier_name ?? '',
                                            'sales' => number_format($rowSales),
                                            'salesman' => $settlement->employee->name ?? '—',
                                            'vehicle' => $settlement->vehicle->vehicle_number ?? '—',
                                            'warehouse' => $settlement->warehouse->warehouse_name ?? '',
                                            'date' => $settlement->settlement_date?->format('D, d M Y').($settlement->posted_at ? ' · posted '.$settlement->posted_at->format('h:i A') : ' · draft'),
                                            'lines' => collect([
                                                ['Cash sale', $settlement->cash_sales_amount],
                                                ['Credit sales', $settlement->credit_sales_amount],
                                                ['Cheques', $settlement->cheque_sales_amount],
                                                ['Bank transfers', $settlement->bank_transfer_amount],
                                                ['Bank slips', $settlement->bank_slips_amount],
                                                ['Recoveries', $settlement->credit_recoveries],
                                                ['Expenses', $settlement->calculated_total_expenses],
                                                ['Cost of goods sold', $settlement->calculated_total_cogs],
                                            ])->map(fn ($line) => ['name' => $line[0], 'value' => number_format((float) $line[1], 2)])->values(),
                                            'qty' => 'Sold '.rtrim(rtrim(number_format((float) $settlement->total_quantity_sold, 3), '0'), '.').' · returned '.rtrim(rtrim(number_format((float) $settlement->total_quantity_returned, 3), '0'), '.').' · short '.rtrim(rtrim(number_format((float) $settlement->total_quantity_shortage, 3), '0'), '.'),
                                            'profit' => number_format($netProfit, 2),
                                            'profitTone' => $netProfit < 0 ? 'loss' : 'gain',
                                        ];
                                    @endphp
                                    <tr class="{{ $settlement->status === 'draft' ? 'gl-draft' : ($netProfit < 0 ? 'gl-lossrow' : '') }}">
                                        <td class="gl-no" data-label="#">{{ $settlements->firstItem() + $loop->index }}</td>
                                        <td data-label="Settlement">
                                            <a href="{{ route('sales-settlements.show', $settlement) }}" class="ak-primary-link" title="Quick look at {{ $settlement->settlement_number }}" aria-haspopup="dialog"
                                                x-data @click="if (! ($event.metaKey || $event.ctrlKey || $event.shiftKey)) { $event.preventDefault(); $dispatch('ss-peek', @js($peek)) }">{{ $shortNumber($settlement->settlement_number) }}</a>
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
                                    <td colspan="3">Total &middot; {{ number_format($settlements->total()) }} {{ \Illuminate\Support\Str::plural('settlement', $settlements->total()) }}{{ $settlements->hasPages() ? ' (all pages)' : '' }}</td>
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
                <form method="POST" :action="confirm.url" class="uf-modal-foot" x-data="{ sending: false }"
                    @submit="if (sending) { $event.preventDefault(); return; } sending = true">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="{{ \App\Support\FormOnce::FIELD }}" value="{{ \App\Support\FormOnce::token() }}">
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false" :disabled="sending">Cancel</button>
                    <button type="submit" class="ak-btn ak-btn-danger-outline" :disabled="sending" x-text="sending ? 'Deleting…' : 'Delete draft'">Delete draft</button>
                </form>
            </div>
        </div>
    </div>
    @include('sales-settlements.partials.help-modal')

    @push('scripts')
        <script>
            // the header's filter button opens this page's own filter panel
            document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('gl-toggle-filters')); });
        </script>
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
    {{-- Quick look: opened by the settlement number; View still opens the full page --}}
    <style>
        .gl-table th.gl-no, .gl-table td.gl-no { width: 40px; color: #8e8e93; font-size: 12.5px; font-variant-numeric: tabular-nums; }
        @media screen and (max-width: 767px) { .gl-table td.gl-no, .gl-table th.gl-no { display: none !important; } }
        .sq-wrap { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .sq-scrim { position: absolute; inset: 0; background: rgba(0,0,0,.28); }
        .sq-box { position: relative; width: 100%; max-width: 640px; max-height: min(86vh, 760px); display: flex; flex-direction: column; overflow: hidden; background: #fff; border-radius: 22px; box-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 24px 64px -12px rgba(0,0,0,.35); font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", system-ui, "Segoe UI", Roboto, sans-serif; }
        .sq-head { padding: 20px 22px 0; }
        .sq-top { display: flex; align-items: center; gap: 8px; }
        .sq-num { margin: 0; font-size: 15px; font-weight: 600; color: #1d1d1f; letter-spacing: -.01em; }
        .sq-close { margin-left: auto; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 50%; background: rgba(120,120,128,.14); color: #3a3a40; cursor: pointer; transition: transform 160ms cubic-bezier(.23,1,.32,1); }
        .sq-close:active { transform: scale(.94); }
        .sq-close:focus-visible, .sq-open:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; }
        .sq-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
        .sq-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; }
        .sq-dot::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .sq-green { background: rgba(52,199,89,.14); color: #1f7a35; } .sq-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .sq-grey { background: rgba(120,120,128,.14); color: #3a3a40; } .sq-blue { background: rgba(0,113,227,.1); color: #0058b0; }
        .sq-label { margin: 14px 0 0; font-size: 12px; font-weight: 600; color: #6e6e73; }
        .sq-amount { margin: 2px 0 0; font-size: 34px; font-weight: 600; letter-spacing: -.03em; line-height: 1.05; color: #1d1d1f; font-variant-numeric: tabular-nums; }
        .sq-amount small { font-size: 17px; font-weight: 500; color: #6e6e73; margin-right: 4px; }
        .sq-who { margin: 6px 0 0; font-size: 14px; line-height: 1.5; color: #424245; }
        .sq-who b { color: #1d1d1f; font-weight: 600; } .sq-who span { color: #6e6e73; }
        .sq-list-head { display: flex; justify-content: space-between; margin: 16px 22px 0; padding-bottom: 6px; border-bottom: 1px solid rgba(60,60,67,.14); font-size: 11.5px; font-weight: 600; color: #6e6e73; }
        .sq-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; margin: 0; padding: 0 22px; list-style: none; }
        .sq-list li { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid rgba(60,60,67,.08); font-size: 14px; color: #1d1d1f; }
        .sq-list li:last-child { border-bottom: 0; }
        .sq-list li b { font-weight: 600; font-variant-numeric: tabular-nums; }
        .sq-foot { padding: 12px 22px 18px; border-top: 1px solid rgba(60,60,67,.14); background: #fbfbfd; }
        .sq-totals { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px 12px; font-size: 13.5px; color: #424245; }
        .sq-totals b { font-variant-numeric: tabular-nums; } .sq-gain { color: #1a7f37; } .sq-loss { color: #d70015; }
        .sq-open { display: flex; align-items: center; justify-content: center; gap: 6px; height: 40px; margin-top: 12px; border-radius: 980px; background: #0071e3; color: #fff !important; font-size: 14.5px; font-weight: 500; text-decoration: none !important; transition: transform 160ms cubic-bezier(.23,1,.32,1), background-color .15s ease; }
        @media (hover: hover) and (pointer: fine) { .sq-open:hover { background: #0077ed; } }
        .sq-open:active { transform: scale(.98); }
        .sq-enter { transition: opacity .2s cubic-bezier(.23,1,.32,1), transform .2s cubic-bezier(.23,1,.32,1); }
        .sq-leave { transition: opacity .12s ease-out, transform .12s ease-out; }
        .sq-from { opacity: 0; transform: scale(.96) translateY(6px); }
        .sq-fade-enter { transition: opacity .2s ease; } .sq-fade-leave { transition: opacity .12s ease; } .sq-fade-from { opacity: 0; }
        @media (max-width: 640px) { .sq-wrap { padding: 0; align-items: flex-end; } .sq-box { max-width: none; border-radius: 22px 22px 0 0; padding-bottom: env(safe-area-inset-bottom); } }
        @media (prefers-reduced-motion: reduce) { .sq-enter, .sq-leave { transition: opacity .15s ease; } .sq-from { transform: none; } }
    </style>
    <div class="no-print" x-data="{ open: false, ss: null, opener: null,
            show(detail) { this.opener = document.activeElement; this.ss = detail; this.open = true; this.$nextTick(() => this.$refs.close.focus()); },
            hide() { this.open = false; this.opener?.focus(); } }"
        @ss-peek.window="show($event.detail)" @keydown.escape.window="open && hide()">
        <template x-if="ss">
            <div class="sq-wrap" x-show="open" role="dialog" aria-modal="true" aria-labelledby="sq-title" x-trap.noscroll="open">
                <div class="sq-scrim" @click="hide()" x-show="open"
                    x-transition:enter="sq-fade-enter" x-transition:enter-start="sq-fade-from" x-transition:leave="sq-fade-leave" x-transition:leave-end="sq-fade-from"></div>
                <div class="sq-box" x-show="open"
                    x-transition:enter="sq-enter" x-transition:enter-start="sq-from" x-transition:leave="sq-leave" x-transition:leave-end="sq-from">
                    <div class="sq-head">
                        <div class="sq-top">
                            <h2 class="sq-num" id="sq-title" x-text="ss.number"></h2>
                            <button type="button" class="sq-close" x-ref="close" @click="hide()" aria-label="Close quick look">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                            </button>
                        </div>
                        <div class="sq-chips">
                            <span class="sq-chip sq-dot" :class="'sq-' + ss.tone" x-text="ss.stage"></span>
                            <span class="sq-chip sq-grey" x-show="ss.issue" x-text="ss.issue"></span>
                            <span class="sq-chip sq-blue" x-show="ss.supplier" x-text="ss.supplier"></span>
                        </div>
                        <p class="sq-label">Net sale</p>
                        <p class="sq-amount"><small>Rs</small><span x-text="ss.sales"></span></p>
                        <p class="sq-who"><b x-text="ss.salesman"></b> · <span x-text="ss.vehicle"></span><template x-if="ss.warehouse"><span x-text="' · ' + ss.warehouse"></span></template><br><span x-text="ss.date"></span></p>
                    </div>
                    <div class="sq-list-head"><span>Where the money went</span><span>Rs</span></div>
                    <ul class="sq-list">
                        <template x-for="(line, index) in ss.lines" :key="index">
                            <li><span x-text="line.name"></span><b x-text="line.value"></b></li>
                        </template>
                    </ul>
                    <div class="sq-foot">
                        <div class="sq-totals"><span x-text="ss.qty"></span><span>Net profit <b :class="'sq-' + ss.profitTone" x-text="'Rs ' + ss.profit"></b></span></div>
                        <a :href="ss.url" class="sq-open">Open full settlement <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>
