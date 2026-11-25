{{--
    Goods Receipt Notes list (/goods-receipt-notes). Same layout as Goods Issues:
    hidden-by-default filters, sticky status tabs, quick dates, removable chips,
    a compact sortable table (View + more-actions menu) and a pager. Query parameters
    are unchanged (filter[...], sort, per_page).
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', '-receipt_date');
    $tab = ($filters['status'] ?? '') === 'all' ? '' : ($filters['status'] ?? '');
    $today = now()->toDateString();
    $dateFrom = $filters['receipt_date_from'] ?? $today;
    $dateTo = $filters['receipt_date_to'] ?? $today;

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $status) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, ['status']);
        $f['status'] = $status !== '' ? $status : 'all';

        return $withFilters($f);
    };
    $shortGrn = fn (?string $n) => preg_replace('/^GRN-\d{4}-0*/', 'GRN-', (string) $n);
    $shortSupplier = function (?string $name) {
        $short = trim(preg_replace('/\b(pvt\.?|private|ltd\.?|limited|corporation|corp\.?|company|co\.?|pakistan|foods?)\b|\(.*?\)/i', '', (string) $name));
        $short = trim(preg_replace('/\s+/', ' ', $short), ' .-,');

        return \Illuminate\Support\Str::limit($short !== '' ? $short : (string) $name, 18, '…');
    };
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
    $shortWarehouse = fn ($name) => str_replace(['Warehouse - I', 'Warehouse - II', 'Warehouse'], ['W-I', 'W-II', 'W'], (string) $name);
    $rangeUrl = fn (string $from, string $to) => $withFilters(array_merge($filters, ['receipt_date_from' => $from, 'receipt_date_to' => $to]));
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

    $names = [
        'supplier_id' => $suppliers->pluck('supplier_name', 'id'),
        'warehouse_id' => $warehouses->pluck('warehouse_name', 'id'),
    ];
    $chipLabels = [
        'search' => 'Search', 'grn_number' => 'GRN no.', 'supplier_invoice_number' => 'Invoice',
        'supplier_id' => 'Supplier', 'warehouse_id' => 'Warehouse',
    ];
    $chipValue = fn (string $key, $value) => isset($names[$key]) ? ($names[$key][$value] ?? $value) : $value;
    // Dates and status have their own controls, so they are not chips.
    $chips = \Illuminate\Support\Arr::except($filters, ['status', 'receipt_date_from', 'receipt_date_to']);
    $advancedKeys = ['warehouse_id', 'supplier_invoice_number', 'grn_number'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $periodLabel = $dateFrom === $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
        : \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y');
    $stageTone = ['draft' => 'amber', 'received' => 'grey', 'posted' => 'green', 'reversed' => 'red'];
    $tabCount = fn (string $status) => $status === '' ? (int) $statusTotals->sum('grn_count') : (int) ($statusTotals[$status]->grn_count ?? 0);
    $grnTabs = [['', 'All'], ['draft', 'Draft'], ['posted', 'Posted'], ['reversed', 'Reversed']];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Goods Receipt Notes" :createRoute="route('goods-receipt-notes.create')" createLabel="New GRN"
            createPermission="goods-receipt-note-create" :showSearch="true" :showRefresh="true" backRoute="dashboard">
            <x-slot name="afterFilter">
                <x-grn-help-link />
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <style>
        html.apl-on .rg-gi-filters { border: 0 !important; border-radius: 20px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08) !important; }
        html.apl-on .rg-gi-filters .ak-field label { font-size: 13px; font-weight: 500; color: #6e6e73; text-transform: none; letter-spacing: 0; }
        html.apl-on .rg-gi-filters .ak-btn { height: 38px; border-radius: 980px !important; text-transform: none; transition: transform .1s ease-out, background-color .15s ease; }
        html.apl-on .rg-gi-filters .ak-btn:active { transform: scale(.97); }
        html.apl-on .rg-gi-filters .ak-btn-primary { background: #0071e3 !important; border-color: #0071e3 !important; }
        html.apl-on .rg-gi-filters .ak-btn-ghost { background: rgba(120,120,128,.14) !important; border: 0 !important; color: #1d1d1f !important; }
        html.apl-on .rg-gi-filters .gl-ranges a { border: 0 !important; background: rgba(118,118,128,.12); color: #1d1d1f; font-weight: 500; font-size: 13px; padding: 5px 12px; }
        html.apl-on .rg-gi-filters .gl-ranges a:hover { background: rgba(118,118,128,.22); }
        html.apl-on .rg-gi-filters .gl-ranges a[aria-current] { background: #0071e3; color: #fff; }
        html.apl-on .rg-gi-filters .gl-ranges .gl-clear { background: rgba(255,59,48,.1); color: #d70015; }
        html.apl-on .rg-gi-filters .gl-ranges-label { font-size: 13px; font-weight: 500; color: #6e6e73; }
        .gl-stage { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .gl-stage::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .gl-stage-green { background: rgba(52,199,89,.14); color: #1f7a35; }
        .gl-stage-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .gl-stage-red { background: rgba(255,59,48,.12); color: #c4161c; }
        .gl-stage-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
        .gl-stage-sub { display: block; margin-top: 3px; font-size: 11.5px; color: #6e6e73; }
        html.apl-on .gl-table .ak-muted, html.apl-on .ak-tab .ak-count { color: #6e6e73 !important; }
        html.apl-on .gl-table .ak-primary-link { display: inline-block; padding: 4px 0; font-weight: 600; letter-spacing: -.01em; white-space: nowrap; }
        .gl-meta { white-space: nowrap; }
        .gl-c-no { width: 40px; } .gl-c-grn { width: 200px; } .gl-c-qty { width: 90px; } .gl-c-val { width: 120px; } .gl-c-stage { width: 150px; } .gl-c-act { width: 1%; }
        .gl-table td.gl-no { width: 40px; color: #8e8e93; font-size: 12.5px; font-variant-numeric: tabular-nums; }
        .ak-dt-scroll.is-compact .gl-table td { padding-top: 6px; padding-bottom: 6px; vertical-align: middle; }
        .ak-dt-scroll.is-compact .gl-table td .ak-muted { font-size: 12px; }
        .ak-dt-scroll.is-compact .gl-stage-sub { display: none; }
        .gl-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
        .gl-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
        .gl-filter-row .ak-field input, .gl-filter-row .ak-field select { height: 38px; box-sizing: border-box; }
        .gl-filter-row .ak-field input[type="date"] { padding: 0 10px; font-size: 14px; }
        .gl-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
        .gl-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
        .gl-ranges a:hover { background: var(--ak-soft); }
        .gl-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
        .gl-ranges a.gl-clear { border-color: #fecaca; color: #b91c1c; background: #fff; }
        .gl-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 900px) { .gl-filter-row { grid-template-columns: minmax(220px, 2fr) 160px 160px minmax(170px, 1.3fr) 90px auto; } }
        .gl-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
        @media (min-width: 900px) { .gl-advanced { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        #gl-filter-panel { overflow: hidden; }
        .gl-nw { white-space: nowrap; }
        @media (min-width: 768px) {
            .gl-table thead th:nth-last-child(-n+3), .gl-table tbody > tr > td:nth-last-child(-n+3) { width: 1%; white-space: nowrap; }
            .gl-table thead th:nth-last-child(2), .gl-table tbody > tr > td:nth-last-child(2) { padding-left: 18px; padding-right: 18px; }
        }
        .gl-act { display: flex; align-items: center; justify-content: flex-end; gap: 4px; white-space: nowrap; }
        .gl-view { gap: 6px; }
        .gl-more { width: 32px; height: 32px; }
        .gl-more svg { width: 18px; height: 18px; }
        .gl-menu { position: fixed; z-index: 70; min-width: 170px; padding: 4px; background: #fff; border: 1px solid var(--ak-border); border-radius: 10px; box-shadow: 0 10px 25px rgba(15, 23, 42, .15); }
        .gl-menu a, .gl-menu button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 12px; border: 0; border-radius: 6px; background: transparent; color: var(--ak-text); font-size: 14px; text-align: left; text-decoration: none; cursor: pointer; }
        .gl-menu a:hover, .gl-menu button:hover { background: var(--ak-soft); }
        .gl-menu svg { width: 17px; height: 17px; flex: none; color: var(--ak-muted); }
        .gl-menu hr { margin: 4px 0; border: 0; border-top: 1px solid #e5e7eb; }
        .gl-menu .is-danger, .gl-menu .is-danger svg { color: #b91c1c; }
        .gl-menu .is-danger:hover { background: #fef2f2; }
        section[aria-label="Goods receipt notes"] > .ak-tabs { position: sticky; top: 0; z-index: 25; background: #fff; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }
        .ak-dt-scroll { max-height: calc(100vh - 190px); min-height: 18rem; }
        .gl-draft > td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
        .gl-draft > td { background: #fffbeb; }
        .gl-table > tbody > tr:hover > td { background: #f1f5f9; }
        .gl-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
        .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
        @media screen and (max-width: 767px) { .gl-table td.gl-no { display: none !important; } }
        @media print {
            .gl-ranges, .ak-filters, .ak-tabs { display: none !important; }
            .gl-table tfoot td { position: static; }
        }
    </style>

    <div class="ak-page" x-data="{ confirm: { open: false, url: '', number: '' } }">
        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section id="gl-filter-panel" class="ak-card rg-gi-filters" aria-label="Filters" x-data="{ open: false }" @gl-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="display:none">
            <form method="GET" action="{{ route('goods-receipt-notes.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                <input type="hidden" name="filter[status]" value="{{ $tab !== '' ? $tab : 'all' }}">
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="gl-filter-row">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="GRN number, invoice or supplier" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_from">From</label>
                        <input id="f_from" type="date" name="filter[receipt_date_from]" value="{{ $dateFrom }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_to">To</label>
                        <input id="f_to" type="date" name="filter[receipt_date_to]" value="{{ $dateTo }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_supplier">Supplier</label>
                        <select id="f_supplier" class="gl-select" data-placeholder="All suppliers" name="filter[supplier_id]">
                            @if ($suppliers->count() !== 1)<option value=""></option>@endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" class="rg-select" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\GoodsReceiptNoteController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n }}</option>
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
                        <label for="f_grn">GRN number</label>
                        <input id="f_grn" type="text" name="filter[grn_number]" value="{{ $filters['grn_number'] ?? '' }}" placeholder="GRN-2025-0001">
                    </div>
                    <div class="ak-field">
                        <label for="f_inv">Supplier invoice</label>
                        <input id="f_inv" type="text" name="filter[supplier_invoice_number]" value="{{ $filters['supplier_invoice_number'] ?? '' }}" placeholder="INV-12345">
                    </div>
                    <div class="ak-field">
                        <label for="f_warehouse">Warehouse</label>
                        <select id="f_warehouse" class="gl-select" data-placeholder="All warehouses" name="filter[warehouse_id]">
                            <option value=""></option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <nav class="gl-ranges" aria-label="Quick dates">
                    <span class="gl-ranges-label">Quick dates</span>
                    @foreach ($ranges as $label => [$from, $to])
                        <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('goods-receipt-notes.index') }}" class="gl-clear" title="Remove every filter and go back to today">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-card" aria-label="Goods receipt notes">
            <nav class="ak-tabs" aria-label="Goods receipt note status">
                @foreach ($grnTabs as [$key, $label])
                    @php $on = $tab === $key; @endphp
                    <a href="{{ $tabUrl($key) }}" @if ($on) aria-current="page" @endif class="ak-tab {{ $on ? 'is-active' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($tabCount($key)) }}</span>
                    </a>
                @endforeach
            </nav>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['status', 'receipt_date_from', 'receipt_date_to'])) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($grns->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('grn-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('grn-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($grns->total()) }}</b> {{ \Illuminate\Support\Str::plural('receipt', $grns->total()) }}
                            &middot; Rs <b>{{ number_format($totalValue) }}</b>
                            @unless ($hasDateFilter)<span class="ak-muted">&middot; today's receipts and every draft</span>@endunless
                        </p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Goods receipt notes table">
                        <table class="ak-dt gl-table">
                            <caption class="sr-only">Goods receipt notes, {{ $grns->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="gl-c-no">#</th>
                                    <th scope="col" class="gl-c-grn" aria-sort="{{ $ariaSort('grn_number') }}"><a href="{{ $sortUrl('grn_number') }}">GRN <span aria-hidden="true">{{ $sortMark('grn_number') }}</span></a> &middot; <a href="{{ $sortUrl('receipt_date') }}">Date <span aria-hidden="true">{{ $sortMark('receipt_date') }}</span></a></th>
                                    <th scope="col">Supplier &middot; warehouse</th>
                                    <th scope="col" class="ak-num gl-c-qty" title="Total quantity received (units)">Qty</th>
                                    <th scope="col" class="ak-num gl-c-val" aria-sort="{{ $ariaSort('grand_total') }}"><a href="{{ $sortUrl('grand_total') }}">Value (Rs) <span aria-hidden="true">{{ $sortMark('grand_total') }}</span></a></th>
                                    <th scope="col" class="gl-c-stage">Status</th>
                                    <th scope="col" class="ak-sticky-end no-print gl-c-act"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            @foreach ($grns as $grn)
                                @php
                                    $canEdit = $grn->status === 'draft' && auth()->user()->can('goods-receipt-note-edit');
                                    $canDelete = $grn->status === 'draft' && auth()->user()->can('goods-receipt-note-delete');
                                    $tone = $stageTone[$grn->status] ?? 'grey';
                                    $ledgerPosted = $grn->status === 'posted' && $grn->ledgerEntry?->isPosted();
                                @endphp
                                <tbody>
                                    <tr class="{{ $grn->status === 'draft' ? 'gl-draft' : '' }}">
                                        <td class="gl-no" data-label="#">{{ $grns->firstItem() + $loop->index }}</td>
                                        <td data-label="GRN">
                                            <a href="{{ route('goods-receipt-notes.show', $grn) }}" class="ak-primary-link" title="{{ $grn->grn_number }}">{{ $shortGrn($grn->grn_number) }}</a>
                                            <div class="ak-muted gl-meta">{{ \Carbon\Carbon::parse($grn->receipt_date)->format('d M Y') }}@if ($grn->supplier_invoice_number) · Inv {{ $grn->supplier_invoice_number }}@endif</div>
                                        </td>
                                        <td data-label="Supplier · warehouse">
                                            <span class="ak-strong" title="{{ $grn->supplier->supplier_name ?? '' }}">{{ $shortSupplier($grn->supplier->supplier_name ?? '') ?: '—' }}</span>
                                            <div class="ak-muted" title="{{ $grn->warehouse->warehouse_name ?? '' }}">{{ $grn->warehouse ? $shortWarehouse($grn->warehouse->warehouse_name) : '—' }}</div>
                                        </td>
                                        <td class="ak-num" data-label="Qty">{{ $qty($grn->total_quantity) }}</td>
                                        <td class="ak-num ak-strong" data-label="Value (Rs)" title="{{ number_format((float) $grn->grand_total, 2) }}">{{ number_format((float) $grn->grand_total) }}</td>
                                        <td class="gl-nw" data-label="Status">
                                            <span class="gl-stage gl-stage-{{ $tone }}">{{ ucfirst($grn->status) }}</span>
                                            @if ($grn->status === 'posted')
                                                <span class="gl-stage-sub">Ledger {{ $ledgerPosted ? 'posted' : ($grn->ledgerEntry ? 'draft' : 'n/a') }}</span>
                                            @endif
                                        </td>
                                        <td class="ak-sticky-end no-print" data-label="">
                                            <div class="gl-act" x-data="{ open: false, pos: '',
                                                toggle(e) {
                                                    const r = e.currentTarget.getBoundingClientRect();
                                                    const flip = r.bottom + 130 > window.innerHeight;
                                                    this.pos = 'right:' + (window.innerWidth - r.right) + 'px;' + (flip ? 'bottom:' + (window.innerHeight - r.top + 4) + 'px' : 'top:' + (r.bottom + 4) + 'px');
                                                    this.open = ! this.open;
                                                } }" @keydown.escape.window="open = false" @scroll.window="open = false" @resize.window="open = false">
                                                <a href="{{ route('goods-receipt-notes.show', $grn) }}" class="ak-btn ak-btn-outline ak-btn-sm gl-view" title="Open {{ $grn->grn_number }}" aria-label="View {{ $grn->grn_number }}">
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    View
                                                </a>
                                                @if ($canEdit || $canDelete)
                                                    <button type="button" class="ak-icon gl-more" @click="toggle($event)" :aria-expanded="open" aria-haspopup="menu" title="More actions" aria-label="More actions for {{ $grn->grn_number }}">
                                                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="12" cy="19" r="1.7" /></svg>
                                                    </button>
                                                    <div class="gl-menu" role="menu" x-show="open" x-cloak style="display:none" :style="pos" @click.outside="open = false">
                                                        @if ($canEdit)
                                                            <a role="menuitem" href="{{ route('goods-receipt-notes.edit', $grn) }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                                Edit draft
                                                            </a>
                                                        @endif
                                                        @if ($canDelete)
                                                            @if ($canEdit)<hr>@endif
                                                            <button type="button" role="menuitem" class="is-danger"
                                                                @click="open = false; confirm = { open: true, url: @js(route('goods-receipt-notes.destroy', $grn)), number: @js($grn->grn_number) }">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                                                                Delete draft
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            @endforeach
                            <tfoot>
                                <tr>
                                    <td colspan="3">Total &middot; {{ number_format($grns->total()) }} {{ \Illuminate\Support\Str::plural('receipt', $grns->total()) }}{{ $grns->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td class="ak-num">{{ $qty($grns->getCollection()->sum('total_quantity')) }}{{ $grns->hasPages() ? '*' : '' }}</td>
                                    <td class="ak-num" title="{{ number_format($totalValue, 2) }}">{{ number_format($totalValue) }}</td>
                                    <td colspan="2" class="ak-muted" style="font-weight:400">{{ $grns->hasPages() ? '* quantity on this page' : '' }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($grns->firstItem()) }}–{{ number_format($grns->lastItem()) }}</b> of <b>{{ number_format($grns->total()) }}</b> receipts</p>
                    <div class="ak-pager-right">
                        <div>{{ $grns->onEachSide(1)->links() }}</div>
                    </div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No goods receipt notes for {{ $periodLabel }}{{ $chips || $tab ? ' with these filters' : '' }}</h2>
                    <p>Pick a wider date range above (for example “This month” or “All”), or remove a filter.</p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        <a href="{{ $rangeUrl(now()->startOfMonth()->toDateString(), $today) }}" class="ak-btn ak-btn-outline">Show this month</a>
                        @can('goods-receipt-note-create') <a href="{{ route('goods-receipt-notes.create') }}" class="ak-btn ak-btn-primary">＋ New GRN</a> @endcan
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
                        <p style="margin:8px 0 0; font-size:14px; color:#334155">The draft and its lines are removed. This cannot be undone.</p>
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
            document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('gl-toggle-filters')); });
            $(function () {
                $('.gl-select').each(function () {
                    const $select = $(this);
                    $select.select2({
                        width: '100%',
                        placeholder: $select.data('placeholder') || $select.find('option[value=""]').text() || 'Select',
                        allowClear: $select.find('option[value=""]').length > 0,
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
