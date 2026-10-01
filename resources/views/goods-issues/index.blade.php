{{--
    Goods Issues list (/goods-issues). Same layout as Settings -> Users:
    KPI cards that double as filters, status tabs, one search box with quick
    date ranges, removable filter chips, a sortable table with comfortable /
    compact density, and a pager with rows-per-page. Query parameters are
    unchanged (filter[...], sort, per_page); dates still default to today.
--}}
@php
    $authUser = auth()->user();
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', '-issue_date');
    $tab = $filters['status'] ?? '';
    $today = now()->toDateString();
    $dateFrom = $filters['issue_date_from'] ?? $today;
    $dateTo = $filters['issue_date_to'] ?? $today;

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $status) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, ['status', 'settlement']);
        if ($status !== '') {
            $f['status'] = $status;
        }

        return $withFilters($f);
    };
    $pendingUrl = $withFilters(array_merge(\Illuminate\Support\Arr::except($filters, ['status']), ['settlement' => 'pending']));
    $rangeUrl = fn (string $from, string $to) => $withFilters(array_merge($filters, ['issue_date_from' => $from, 'issue_date_to' => $to]));
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
    $sortNames = ['issue_date' => 'Issue date', 'issue_number' => 'Issue number', 'total_value' => 'Value', 'created_at' => 'Created'];
    $sortField = ltrim($sort, '-');

    $names = [
        'supplier_id' => $suppliers->pluck('supplier_name', 'id'),
        'warehouse_id' => $warehouses->pluck('warehouse_name', 'id'),
        'vehicle_id' => $vehicles->pluck('vehicle_number', 'id'),
        'employee_id' => $employees->pluck('name', 'id'),
    ];
    $chipLabels = [
        'search' => 'Search', 'issue_number' => 'Issue no.', 'supplier_id' => 'Supplier', 'warehouse_id' => 'Warehouse',
        'vehicle_id' => 'Vehicle', 'employee_id' => 'Salesman', 'settlement' => 'Settlement', 'product_id' => 'Product',
    ];
    $chipValue = fn (string $key, $value) => match ($key) {
        'settlement' => $value === 'pending' ? 'Not settled yet' : 'Settled',
        default => isset($names[$key]) ? ($names[$key][$value] ?? $value) : $value,
    };
    // Dates and status have their own controls, so they are not chips.
    $chips = \Illuminate\Support\Arr::except($filters, ['status', 'issue_date_from', 'issue_date_to', 'issue_date']);
    $advancedKeys = ['warehouse_id', 'vehicle_id', 'employee_id', 'settlement'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $periodLabel = $dateFrom === $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
        : \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y');
    $statusLabels = ['draft' => 'Draft', 'issued' => 'Issued', 'cancelled' => 'Reversed'];
    $statusTone = ['draft' => 'ak-status-amber', 'issued' => 'ak-status-green', 'cancelled' => 'ak-status-red'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><span>Goods Issues</span>
                </nav>
                <h1 class="ak-title">Goods Issues</h1>
                <p class="ak-sub">Stock loaded from the warehouse onto vans &middot; {{ $periodLabel }}</p>
            </div>
            <div class="ak-head-actions">
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx', 'page' => null]) }}" class="ak-btn ak-btn-outline" title="Download the filtered list as Excel">Export Excel</a>
                @can('report-sales-goods-issue')
                    <a href="{{ route('reports.goods-issue.index', ['filter' => ['start_date' => $dateFrom, 'end_date' => $dateTo]]) }}" class="ak-btn ak-btn-outline">Goods issue report</a>
                @endcan
                @can('goods-issue-create')
                    <a href="{{ route('goods-issues.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> New goods issue</a>
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
        @media (min-width: 900px) { .gl-filter-row { grid-template-columns: minmax(220px, 2fr) 160px 160px minmax(170px, 1.3fr) 90px auto; } }
        .gl-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
        @media (min-width: 900px) { .gl-advanced { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .gl-table { min-width: 1060px; }
        .gl-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
        .gl-settle a { color: inherit; text-decoration: none; }
        .gl-settle a:hover .ak-status { text-decoration: underline; }
        .gl-age { display: block; margin-top: 2px; font-size: 11.5px; }
        .gl-late { color: #b91c1c; font-weight: 600; }
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
        .gl-salesman { display: flex; flex-direction: column; gap: 5px; padding: 10px 12px; border: 1px solid var(--ak-line); border-radius: 10px; color: var(--ak-text); text-decoration: none; font-size: 13px; background: #fff; }
        .gl-salesman:hover { border-color: #a5b4fc; background: #f8faff; }
        .gl-salesman.is-on { border-color: var(--ak-navy); box-shadow: 0 0 0 2px #c7d2fe; }
        .gl-salesman-top { display: flex; justify-content: space-between; gap: 8px; }
        .gl-salesman-top b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gl-salesman-top span { font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .gl-bar { height: 5px; border-radius: 99px; background: #eef2ff; overflow: hidden; }
        .gl-bar i { display: block; height: 100%; background: var(--ak-navy); border-radius: 99px; }
        .gl-table > tbody > tr:not(.gl-lines) > td { border-bottom: 1px solid #e5e7eb; }
        .gl-table > tbody:last-of-type > tr:last-child > td { border-bottom: 0; }
        .gl-toggle { border: 0; background: none; padding: 0 4px 0 0; color: var(--ak-muted); cursor: pointer; font-size: 11px; vertical-align: 1px; }
        .gl-toggle:hover { color: var(--ak-navy); }
        .gl-lines td { background: #f8fafc !important; padding-top: 4px !important; padding-bottom: 10px !important; }
        .gl-lines table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .gl-lines th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--ak-muted); padding: 4px 8px; border-bottom: 1px solid var(--ak-line); background: transparent !important; position: static !important; }
        .gl-lines td td { padding: 3px 8px !important; border-bottom: 1px solid #eef2f7; background: transparent !important; }
        @media print {
            .gl-salesmen, .gl-toggle, .gl-lines { display: none !important; }
            .gl-ranges { display: none !important; }
            .gl-table { min-width: 0 !important; }
            .gl-table tfoot td { position: static; }
        }
    </style>

    <div class="ak-page" x-data="{ confirm: { open: false, url: '', number: '' } }">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Goods Issues &middot; {{ $periodLabel }}</div>
            <table class="ak-print-meta">
                <tr><th>Filters</th><td>{{ collect(\Illuminate\Support\Arr::except($filters, ['issue_date_from', 'issue_date_to']))->map(fn ($v, $k) => ($chipLabels[$k] ?? \Illuminate\Support\Str::headline($k)).': '.($k === 'status' ? ($statusLabels[$v] ?? $v) : $chipValue($k, $v)))->implode(' · ') ?: 'None' }}</td>
                    <th>Printed</th><td>{{ now()->format('d.m.Y H:i') }} by {{ $authUser->name }}</td></tr>
            </table>
        </div>

        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        {{-- KPI cards (also filters) --}}
        <section class="ak-kpis" aria-label="Goods issue summary">
            <a href="{{ $tabUrl('') }}" class="ak-kpi{{ $tab === '' && ! isset($filters['settlement']) ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.38a1.13 1.13 0 0 1-1.13-1.13V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.13c.62 0 1.13-.5 1.09-1.12a17.9 17.9 0 0 0-3.21-9.06 2.29 2.29 0 0 0-1.89-.95H14.25M16.5 18.75h-2.25m0-11.18v-.96c0-.57-.42-1.05-.98-1.12a48.6 48.6 0 0 0-10.04 0 1.13 1.13 0 0 0-.98 1.12v7.64m12 0v-6.68" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Issued to vans</span>
                    <span class="ak-kpi-value">Rs {{ number_format($stats['issued_value']) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($stats['issued']) }} posted {{ \Illuminate\Support\Str::plural('issue', $stats['issued']) }} &middot; {{ number_format($stats['total']) }} in all</span>
                </span>
            </a>
            <a href="{{ $tabUrl('draft') }}" class="ak-kpi{{ $stats['draft'] ? ' ak-kpi-action' : '' }}{{ $tab === 'draft' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Drafts to post</span>
                    <span class="ak-kpi-value">{{ number_format($stats['draft']) }}</span>
                    <span class="ak-kpi-hint">{{ $stats['draft'] ? 'Rs '.number_format($stats['draft_value']).' not on vans yet →' : 'nothing waiting' }}</span>
                </span>
            </a>
            <a href="{{ $pendingUrl }}" class="ak-kpi{{ $stats['pending'] ? ' ak-kpi-action' : '' }}{{ ($filters['settlement'] ?? '') === 'pending' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Not settled yet</span>
                    <span class="ak-kpi-value">{{ number_format($stats['pending']) }}</span>
                    <span class="ak-kpi-hint">{{ $stats['pending'] ? 'Rs '.number_format($stats['pending_value']).' still on vans →' : 'every van is settled' }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('cancelled') }}" class="ak-kpi{{ $tab === 'cancelled' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Reversed</span>
                    <span class="ak-kpi-value">{{ number_format($stats['cancelled']) }}</span>
                    <span class="ak-kpi-hint">stock returned to warehouse</span>
                </span>
            </a>
        </section>

        @if ($bySalesman->count() > 1)
            <section class="ak-card gl-salesmen" aria-label="By salesman" x-data="{ all: false }">
                <div class="gl-salesmen-head">
                    <h2>By salesman <span class="ak-muted">&middot; {{ $periodLabel }}, reversed left out</span></h2>
                    @if ($bySalesman->count() > 6)
                        <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="all = !all" x-text="all ? 'Show top 6' : 'Show all {{ $bySalesman->count() }}'"></button>
                    @endif
                </div>
                @php $maxValue = max((float) $bySalesman->max('value'), 1); @endphp
                <div class="gl-salesmen-grid">
                    @foreach ($bySalesman as $row)
                        @php $on = (string) ($filters['employee_id'] ?? '') === (string) $row->employee_id; @endphp
                        <a href="{{ $withFilters($on ? \Illuminate\Support\Arr::except($filters, 'employee_id') : array_merge($filters, ['employee_id' => $row->employee_id])) }}"
                            class="gl-salesman{{ $on ? ' is-on' : '' }}" @if ($loop->index >= 6) x-show="all" x-cloak @endif
                            title="{{ $on ? 'Remove this filter' : 'Show only '.$row->name }}">
                            <span class="gl-salesman-top">
                                <b>{{ $row->name }}</b>
                                <span>Rs {{ number_format((float) $row->value) }}</span>
                            </span>
                            <span class="gl-bar"><i style="width: {{ round((float) $row->value / $maxValue * 100, 1) }}%"></i></span>
                            <span class="ak-muted">{{ $row->issues }} {{ \Illuminate\Support\Str::plural('issue', (int) $row->issues) }}@if ($row->pending > 0) &middot; <span class="gl-late">{{ $row->pending }} not settled</span>@endif</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ak-card" aria-label="Goods issues">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $stats['total']], 'draft' => ['Draft', $stats['draft']], 'issued' => ['Issued', $stats['issued']], 'cancelled' => ['Reversed', $stats['cancelled']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl($key) }}" role="tab" aria-selected="{{ $tab === $key && ! isset($filters['settlement']) ? 'true' : 'false' }}" class="ak-tab {{ $tab === $key && ! isset($filters['settlement']) ? 'is-active' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('goods-issues.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[status]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="gl-filter-row">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="GI number, vehicle or salesman" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_from">From</label>
                        <input id="f_from" type="date" name="filter[issue_date_from]" value="{{ $dateFrom }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_to">To</label>
                        <input id="f_to" type="date" name="filter[issue_date_to]" value="{{ $dateTo }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_supplier">Supplier</label>
                        <select id="f_supplier" class="gl-select" name="filter[supplier_id]">
                            @if ($suppliers->count() !== 1)<option value="">All suppliers</option>@endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\GoodsIssueController::PER_PAGE as $n)
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
                                <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->name }}{{ $employee->employee_code ? ' ('.$employee->employee_code.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_vehicle">Vehicle</label>
                        <select id="f_vehicle" class="gl-select" name="filter[vehicle_id]">
                            <option value="">All vehicles</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected((string) ($filters['vehicle_id'] ?? '') === (string) $vehicle->id)>{{ $vehicle->vehicle_number }} ({{ $vehicle->vehicle_type }})</option>
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
                    <div class="ak-field">
                        <label for="f_settlement">Settlement</label>
                        <select id="f_settlement" name="filter[settlement]">
                            <option value="">Any</option>
                            <option value="pending" @selected(($filters['settlement'] ?? '') === 'pending')>Not settled yet</option>
                            <option value="settled" @selected(($filters['settlement'] ?? '') === 'settled')>Settled</option>
                        </select>
                    </div>
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
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['status', 'issue_date_from', 'issue_date_to'])) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($goodsIssues->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('gi-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('gi-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($goodsIssues->total()) }}</b> {{ \Illuminate\Support\Str::plural('issue', $goodsIssues->total()) }}
                            &middot; Rs <b>{{ number_format($totalValue) }}</b>
                            &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Issue date' }}</b> ({{ str_starts_with($sort, '-') ? 'newest / highest first' : 'oldest / lowest first' }})
                        </p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Goods issues table">
                        <table class="ak-dt gl-table">
                            <caption class="sr-only">Goods issues, {{ $goodsIssues->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c" style="width:48px">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('issue_number') }}"><a href="{{ $sortUrl('issue_number') }}">Issue <span aria-hidden="true">{{ $sortMark('issue_number') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('issue_date') }}"><a href="{{ $sortUrl('issue_date') }}">Date <span aria-hidden="true">{{ $sortMark('issue_date') }}</span></a></th>
                                    <th scope="col">Salesman &middot; vehicle</th>
                                    <th scope="col" class="ak-num">Lines</th>
                                    <th scope="col" class="ak-num" aria-sort="{{ $ariaSort('total_value') }}"><a href="{{ $sortUrl('total_value') }}">Value (Rs) <span aria-hidden="true">{{ $sortMark('total_value') }}</span></a></th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Settlement</th>
                                    <th scope="col" class="ak-c ak-sticky-end"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            @foreach ($goodsIssues as $gi)
                                @php
                                    $settlement = $gi->settlement->sortByDesc('id')->first();
                                    $daysOut = $gi->status === 'issued' && ! $settlement ? (int) abs($gi->issue_date?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0) : null;
                                    $canPeek = $gi->relationLoaded('items') && $gi->items->isNotEmpty();
                                @endphp
                                <tbody x-data="{ open: false }">
                                    <tr>
                                        <td class="ak-c ak-muted">
                                            @if ($canPeek)
                                                <button type="button" class="gl-toggle" @click="open = !open" :aria-expanded="open" aria-label="Show lines of {{ $gi->issue_number }}" title="Show lines"><span x-text="open ? '▾' : '▸'">▸</span></button>
                                            @endif
                                            {{ $goodsIssues->firstItem() + $loop->index }}
                                        </td>
                                        <td data-label="Issue">
                                            <a href="{{ route('goods-issues.show', $gi) }}" class="ak-primary-link">{{ $gi->issue_number }}</a>
                                            <div class="ak-muted ak-hide-compact">{{ $gi->supplier->supplier_name ?? '' }}{{ $gi->issuedBy ? ' · by '.$gi->issuedBy->name : '' }}</div>
                                        </td>
                                        <td class="ak-mono" data-label="Date">
                                            {{ $gi->issue_date?->format('d M Y') }}
                                            <div class="ak-muted ak-hide-compact">{{ $gi->posted_at ? 'posted '.$gi->posted_at->format('h:i A') : $gi->created_at?->diffForHumans() }}</div>
                                        </td>
                                        <td data-label="Salesman · vehicle">
                                            <span class="ak-strong">{{ $gi->employee->name ?? '—' }}</span>
                                            <div class="ak-muted">{{ $gi->vehicle->vehicle_number ?? '—' }}{{ $gi->warehouse ? ' · '.$gi->warehouse->warehouse_name : '' }}</div>
                                        </td>
                                        <td class="ak-num" data-label="Lines">{{ number_format($gi->items_count) }}</td>
                                        <td class="ak-num ak-strong" data-label="Value (Rs)">{{ number_format((float) $gi->total_value, 2) }}</td>
                                        <td data-label="Status">
                                            <span class="ak-status {{ $statusTone[$gi->status] ?? 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ $statusLabels[$gi->status] ?? \Illuminate\Support\Str::headline($gi->status) }}</span>
                                        </td>
                                        <td class="gl-settle" data-label="Settlement">
                                            @if ($settlement)
                                                @can('sales-settlement-list')
                                                    <a href="{{ route('sales-settlements.show', $settlement) }}" title="Open {{ $settlement->settlement_number }}">
                                                @endcan
                                                <span class="ak-status {{ $settlement->status === 'draft' ? 'ak-status-amber' : 'ak-status-green' }}"><i aria-hidden="true"></i>{{ \Illuminate\Support\Str::headline($settlement->status) }}</span>
                                                <span class="gl-age ak-muted ak-hide-compact">{{ $settlement->settlement_number }}</span>
                                                @can('sales-settlement-list')
                                                    </a>
                                                @endcan
                                            @elseif ($gi->status === 'issued')
                                                <span class="ak-muted">Not settled</span>
                                                <span class="gl-age {{ $daysOut >= 2 ? 'gl-late' : 'ak-muted' }}">{{ $daysOut === 0 ? 'out today' : $daysOut.' '.\Illuminate\Support\Str::plural('day', $daysOut).' on van' }}</span>
                                            @else
                                                <span class="ak-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="ak-sticky-end" data-label="">
                                            <div class="ak-actions">
                                                <a href="{{ route('goods-issues.show', $gi) }}" class="ak-icon ak-icon-view" title="View" aria-label="View {{ $gi->issue_number }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                </a>
                                                <span class="ak-slot">
                                                    @if ($gi->status === 'draft')
                                                        @can('goods-issue-edit')
                                                            <a href="{{ route('goods-issues.edit', $gi) }}" class="ak-icon" title="Edit draft" aria-label="Edit {{ $gi->issue_number }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                </span>
                                                <span class="ak-slot">
                                                    @if ($gi->status === 'draft')
                                                        @can('goods-issue-delete')
                                                            <button type="button" class="ak-icon ak-icon-danger" title="Delete draft" aria-label="Delete {{ $gi->issue_number }}"
                                                                @click="confirm = { open: true, url: @js(route('goods-issues.destroy', $gi)), number: @js($gi->issue_number) }">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                                                            </button>
                                                        @endcan
                                                    @endif
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    @if ($canPeek)
                                        <tr class="gl-lines" x-show="open" x-cloak>
                                            <td></td>
                                            <td colspan="8">
                                                <table>
                                                    <thead><tr><th>#</th><th>Product</th><th style="text-align:right">Qty</th><th style="text-align:right">Rate</th><th style="text-align:right">Value (Rs)</th></tr></thead>
                                                    <tbody>
                                                        @foreach ($gi->items as $line)
                                                            <tr>
                                                                <td class="ak-muted">{{ $line->line_no ?? $loop->iteration }}</td>
                                                                <td>{{ $line->product->product_name ?? '—' }}@if ($line->is_supplementary) <span class="ak-pill" style="margin:0">supplementary</span>@endif</td>
                                                                <td style="text-align:right">{{ rtrim(rtrim(number_format((float) $line->quantity_issued, 3), '0'), '.') }}</td>
                                                                <td style="text-align:right">{{ number_format((float) $line->selling_price, 2) }}</td>
                                                                <td style="text-align:right">{{ number_format((float) $line->total_value, 2) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            @endforeach
                            <tfoot>
                                <tr>
                                    <td colspan="4">Total &middot; {{ number_format($goodsIssues->total()) }} {{ \Illuminate\Support\Str::plural('issue', $goodsIssues->total()) }}{{ $goodsIssues->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td class="ak-num">{{ number_format($goodsIssues->getCollection()->sum('items_count')) }}{{ $goodsIssues->hasPages() ? '*' : '' }}</td>
                                    <td class="ak-num">{{ number_format((float) $totalValue, 2) }}</td>
                                    <td colspan="3" class="ak-muted" style="font-weight:400">{{ $goodsIssues->hasPages() ? '* lines on this page' : '' }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($goodsIssues->firstItem()) }}–{{ number_format($goodsIssues->lastItem()) }}</b> of <b>{{ number_format($goodsIssues->total()) }}</b> issues</p>
                    <div class="ak-pager-right">
                        <div>{{ $goodsIssues->onEachSide(1)->links() }}</div>
                    </div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No goods issues for {{ $periodLabel }}{{ $chips || $tab ? ' with these filters' : '' }}</h2>
                    <p>Pick a wider date range above (for example “This month” or “All”), or remove a filter.</p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        <a href="{{ $rangeUrl(now()->startOfMonth()->toDateString(), $today) }}" class="ak-btn ak-btn-outline">Show this month</a>
                        @can('goods-issue-create') <a href="{{ route('goods-issues.create') }}" class="ak-btn ak-btn-primary">＋ New goods issue</a> @endcan
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
                        <p style="margin:8px 0 0; font-size:14px; color:#334155">The draft and its lines are removed and the vehicle is free for a new issue. This cannot be undone.</p>
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
        <script>
            // Searchable dropdowns: type to find a supplier, salesman, vehicle or warehouse.
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
