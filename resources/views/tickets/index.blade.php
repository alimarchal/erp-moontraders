{{--
    Tickets list (/tickets). Same layout as Goods Issues: the shared page header (New ticket, Filters, Help,
    Refresh, Back, Print), a hidden-by-default filter panel in the Apple look, sticky status tabs, quick dates,
    removable chips, a compact sortable table (#, View + more-actions menu, totals row) and a pager.
    Query parameters: filter[...], sort, per_page. The list opens on the Pending tab.
--}}
@php
    use Illuminate\Support\Arr;
    use Illuminate\Support\Str;

    $authUser = auth()->user();
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', '-created_at');
    $tab = ($filters['status'] ?? '') === 'all' ? '' : ($filters['status'] ?? '');
    $helpTopic = 'status';
    $today = now()->toDateString();
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $status) use ($filters, $withFilters) {
        $f = Arr::except($filters, ['status']);
        $f['status'] = $status !== '' ? $status : 'all';

        return $withFilters($f);
    };
    $rangeUrl = fn (string $from, string $to) => $withFilters(array_merge($filters, ['date_from' => $from, 'date_to' => $to]));
    $ranges = [
        'Today' => [$today, $today],
        'Yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
        'This week' => [now()->startOfWeek()->toDateString(), $today],
        'This month' => [now()->startOfMonth()->toDateString(), $today],
        'Last 30 days' => [now()->subDays(29)->toDateString(), $today],
    ];
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === '-'.$field ? $field : '-'.$field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');
    $sortNames = ['created_at' => 'Raised on', 'ticket_number' => 'Ticket number', 'title' => 'Title', 'status' => 'Status'];
    $sortField = ltrim($sort, '-');

    $names = [
        'supplier_id' => $suppliers->pluck('supplier_name', 'id'),
        'created_by' => $creators->pluck('name', 'id'),
        'reviewed_by' => $reviewers->pluck('name', 'id'),
        'type' => collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()]),
        'product_id' => $products->mapWithKeys(fn ($p) => [$p->id => $p->product_code.' — '.$p->product_name]),
        'warehouse_id' => $warehouses->pluck('warehouse_name', 'id'),
        'category_id' => $categories->pluck('name', 'id'),
        'adjustment_type' => collect($adjustmentTypes)->mapWithKeys(fn ($t) => [$t => Str::headline($t)]),
    ];
    $chipLabels = ['search' => 'Search', 'type' => 'Type', 'supplier_id' => 'Company', 'created_by' => 'Raised by', 'reviewed_by' => 'Decided by',
        'product_id' => 'Product', 'batch' => 'Batch', 'warehouse_id' => 'Warehouse', 'adjustment_type' => 'Adjustment type', 'category_id' => 'Category'];
    $chipValue = fn (string $key, $value) => isset($names[$key]) ? ($names[$key][$value] ?? $value) : $value;
    // Dates and status have their own controls, so they are not chips.
    $chips = Arr::except($filters, ['status', 'date_from', 'date_to']);
    $advancedKeys = ['type', 'created_by', 'reviewed_by', 'product_id', 'batch', 'warehouse_id', 'adjustment_type', 'category_id'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $statusLabels = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    $stageTone = ['pending' => 'amber', 'approved' => 'green', 'rejected' => 'red'];
    $periodLabel = ($dateFrom || $dateTo)
        ? ($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'start').' – '.($dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : 'today')
        : 'all dates';
    $isAdmin = $authUser->isTicketAdmin();
    // TKT-000012 -> TKT-12 (full number stays in the tooltip and in search)
    $shortNumber = fn (?string $n) => preg_match('/^TKT-0*(\d+)$/', (string) $n, $m) ? 'TKT-'.$m[1] : (string) $n;
    // Engro Corporation Ltd -> Engro: drop company suffixes, keep it short (same as Goods Issues).
    $shortSupplier = function (?string $name) {
        $short = trim(preg_replace('/\b(pvt\.?|private|ltd\.?|limited|corporation|corp\.?|company|co\.?|pakistan|foods?)\b|\(.*?\)/i', '', (string) $name));
        $short = trim(preg_replace('/\s+/', ' ', $short), ' .-,');

        return Str::limit($short !== '' ? $short : (string) $name, 14, '…');
    };
    $headerButton = 'inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 no-print';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tickets" :createRoute="route('tickets.create')" createLabel="New ticket"
            createPermission="ticket-create" :showSearch="true" :showRefresh="true" backRoute="dashboard">
            <x-slot name="afterFilter">
                {{-- Help sits next to Filters and opens on the tab being looked at, like Goods Issues --}}
                <button type="button" class="{{ $headerButton }}" x-data @click="$dispatch('rg-help', { topic: @js($helpTopic) })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How tickets work, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
            <x-slot name="actions">
                @can('report-audit-product-price-change-log')
                    <a href="{{ route('reports.product-price-change-log.index') }}" class="{{ $headerButton }}" title="Price change log">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <span class="sr-only">Price change log</span>
                    </a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-register-help page="tickets" />

    @include('settings.partials.ui-style')
    @include('tickets.partials.style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <style>
        /* Apple look for the filter menu, same as Goods Issues: filled fields, Select2 like the rest, pill buttons */
        html.apl-on .tk-filters { border: 0 !important; border-radius: 20px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08) !important; }
        html.apl-on .tk-filters .ak-field label { font-size: 13px; font-weight: 500; color: #6e6e73; text-transform: none; letter-spacing: 0; }
        html.apl-on .tk-filters .ak-btn { height: 38px; border-radius: 980px !important; text-transform: none; transition: transform .1s ease-out, background-color .15s ease; }
        html.apl-on .tk-filters .ak-btn:active { transform: scale(.97); }
        html.apl-on .tk-filters .ak-btn-primary { background: #0071e3 !important; border-color: #0071e3 !important; }
        html.apl-on .tk-filters .ak-btn-ghost { background: rgba(120,120,128,.14) !important; border: 0 !important; color: #1d1d1f !important; }
        html.apl-on .tk-filters .tk-ranges a { border: 0 !important; background: rgba(118,118,128,.12); color: #1d1d1f; font-weight: 500; font-size: 13px; padding: 5px 12px; transition: background-color .15s ease, transform .1s ease-out; }
        html.apl-on .tk-filters .tk-ranges a:hover { background: rgba(118,118,128,.22); }
        html.apl-on .tk-filters .tk-ranges a:active { transform: scale(.96); }
        html.apl-on .tk-filters .tk-ranges .tk-clear { background: rgba(255,59,48,.1); color: #d70015; }
        html.apl-on .tk-filters .tk-ranges-label { font-size: 13px; font-weight: 500; color: #6e6e73; }
        html.apl-on .tk-ranges-inline a { border: 0 !important; background: rgba(118,118,128,.12); color: #1d1d1f; font-weight: 500; }
        html.apl-on .tk-ranges-inline a:hover { background: rgba(118,118,128,.22); }
        html.apl-on .tk-ranges a[aria-current] { background: #0071e3 !important; border-color: #0071e3 !important; color: #fff !important; }
        /* stage: one capsule for where the ticket stands */
        .tk-stage { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .tk-stage::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .tk-stage-green { background: rgba(52,199,89,.14); color: #1f7a35; }
        .tk-stage-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .tk-stage-red { background: rgba(255,59,48,.12); color: #c4161c; }
        .tk-stage-sub { display: block; margin-top: 3px; font-size: 11.5px; color: #6e6e73; }
        html.apl-on .tk-table .ak-muted, html.apl-on .ak-tab .ak-count { color: #6e6e73 !important; }
        html.apl-on .tk-table .ak-primary-link { display: inline-block; padding: 4px 0; }
        html.apl-on .tk-table td[data-label="Ticket"] .ak-primary-link { font-weight: 600; letter-spacing: -.01em; white-space: nowrap; }
        .tk-meta { white-space: nowrap; }
        .tk-c-no { width: 40px; } .tk-c-ticket { width: 170px; } .tk-c-det { min-width: 320px; } .tk-c-man { width: 150px; } .tk-c-stage { width: 150px; } .tk-c-act { width: 1%; }
        .tk-table td.tk-no { width: 40px; color: #8e8e93; font-size: 12.5px; font-variant-numeric: tabular-nums; }
        .tk-desc { min-width: 180px; max-width: 280px; color: #334155; font-size: 13px; line-height: 1.4; }
        .tk-type { font-size: 12px; font-weight: 600; color: #0071e3; }
        /* Details: the figures a reviewer needs, per ticket type */
        .tk-det-cell { min-width: 320px; }
        .tk-det { font-size: 13px; line-height: 1.45; color: #1d1d1f; }
        .tk-det-line + .tk-det-line { margin-top: 6px; padding-top: 6px; border-top: 1px dashed #e5e7eb; }
        .tk-det-head { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .tk-det-sku { font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: -.01em; }
        .tk-det-name { color: #334155; }
        .tk-det-tag { display: inline-block; padding: 1px 8px; border-radius: 980px; background: rgba(0,113,227,.1); color: #0058b0; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
        .tk-det-changes { display: flex; flex-wrap: wrap; gap: 2px 12px; margin-top: 2px; }
        .tk-det-change { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .tk-det-table { width: 100%; margin-top: 4px; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .tk-det-table td { padding: 1px 8px 1px 0 !important; border: 0 !important; background: transparent !important; white-space: nowrap; font-size: 12.5px; }
        .tk-det-table td.ak-num { text-align: right; }
        .tk-det-foot { margin-top: 3px; font-size: 12.5px; }
        .ak-dt-scroll.is-compact .tk-table td { padding-top: 6px; padding-bottom: 6px; vertical-align: middle; }
        .ak-dt-scroll.is-compact .tk-table td .ak-muted { font-size: 12px; }
        .ak-dt-scroll.is-compact .tk-desc-remarks, .ak-dt-scroll.is-compact .tk-stage-sub { display: none; }

        @page { margin: 10mm; }
        .tk-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
        .tk-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
        .tk-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
        .tk-ranges a:hover { background: var(--ak-soft); }
        .tk-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
        .tk-ranges a.tk-clear { border-color: #fecaca; color: #b91c1c; background: #fff; }
        .tk-ranges a.tk-clear:hover { background: #fef2f2; }
        .tk-ranges-inline { margin-top: 0; }
        .tk-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 900px) { .tk-filter-row { grid-template-columns: minmax(220px, 2fr) 160px 160px minmax(170px, 1.3fr) 90px auto; } }
        .tk-filter-row .ak-field input, .tk-filter-row .ak-field select { height: 38px; box-sizing: border-box; }
        .tk-filter-row .ak-field input[type="date"] { padding: 0 10px; font-size: 14px; }
        .tk-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
        .tk-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
        @media (min-width: 900px) { .tk-advanced { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        #tk-filter-panel { overflow: hidden; }
        .tk-nw { white-space: nowrap; }
        @media (min-width: 768px) {
            .tk-table thead th:nth-last-child(-n+3), .tk-table tbody > tr > td:nth-last-child(-n+3) { width: 1%; white-space: nowrap; }
            .tk-table thead th:nth-last-child(2), .tk-table tbody > tr > td:nth-last-child(2) { padding-left: 18px; padding-right: 18px; }
            .tk-table thead th:nth-last-child(1), .tk-table tbody > tr > td:nth-last-child(1) { padding-left: 8px; }
        }
        .tk-act { display: flex; align-items: center; justify-content: flex-end; gap: 4px; white-space: nowrap; }
        .tk-view { gap: 6px; }
        .tk-more { width: 32px; height: 32px; }
        .tk-more svg { width: 18px; height: 18px; }
        .tk-menu { position: fixed; z-index: 70; min-width: 170px; padding: 4px; background: #fff; border: 1px solid var(--ak-border); border-radius: 10px; box-shadow: 0 10px 25px rgba(15, 23, 42, .15); }
        .tk-menu a, .tk-menu button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 12px; border: 0; border-radius: 6px; background: transparent; color: var(--ak-text); font-size: 14px; text-align: left; text-decoration: none; cursor: pointer; }
        .tk-menu a:hover, .tk-menu button:hover { background: var(--ak-soft); }
        .tk-menu svg { width: 17px; height: 17px; flex: none; color: var(--ak-muted); }
        .tk-menu hr { margin: 4px 0; border: 0; border-top: 1px solid #e5e7eb; }
        .tk-menu .is-danger, .tk-menu .is-danger svg { color: #b91c1c; }
        .tk-menu .is-danger:hover { background: #fef2f2; }
        .tk-tab-warn:not(.is-active) .ak-count { background: #fef3c7; color: #b45309; }
        section[aria-label="Tickets"] > .ak-tabs { position: sticky; top: 0; z-index: 25; background: #fff; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }
        .ak-dt-scroll { max-height: calc(100vh - 190px); min-height: 18rem; }
        .tk-pending > td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
        .tk-pending > td { background: #fffbeb; }
        .tk-table > tbody > tr:hover > td { background: #f1f5f9; }
        .tk-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
        /* Searchable dropdowns (Select2) sized like the other filter fields */
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
        .ak-filters .select2-container--focus .select2-selection--single, .ak-filters .select2-container--open .select2-selection--single { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
        .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
        .select2-search--dropdown .select2-search__field { border-radius: 6px; padding: 6px 8px; }
        @media screen and (max-width: 767px) { .tk-table td.tk-no { display: none !important; } }
        /* Laptops (1024–1279px): the table fits without sideways scrolling */
        @media screen and (min-width: 1024px) and (max-width: 1279px) {
            .tk-table { min-width: 0 !important; }
            .tk-desc { min-width: 150px; max-width: 220px; }
            .tk-det-cell { min-width: 260px; }
            .tk-table .tk-meta { white-space: normal; }
        }
        /* Tablets (768–1023px): rows become cards, as on a phone */
        @media screen and (min-width: 768px) and (max-width: 1023px) {
            .tk-table { min-width: 0 !important; }
            .tk-table thead { display: none; }
            .tk-table, .tk-table tbody, .tk-table tr, .tk-table td, .tk-table tfoot { display: block; width: 100%; }
            .tk-table tbody tr { margin: 12px; width: auto; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); overflow: hidden; }
            .tk-table td { display: flex; justify-content: space-between; gap: 12px; padding: 8px 12px; text-align: right; }
            .tk-table tbody > tr > td { width: 100% !important; max-width: none; white-space: normal; padding-left: 12px !important; padding-right: 12px !important; }
            .tk-table td::before { content: attr(data-label); font-size: 12px; font-weight: 700; color: var(--ak-muted); text-align: left; }
            .tk-table td[data-label=""]::before, .tk-table td.tk-no { display: none; }
            .tk-table .ak-sticky-end { position: static; box-shadow: none; width: auto; min-width: 0; }
            .tk-table tfoot tr { display: flex; justify-content: space-between; }
            .tk-table tfoot td { position: static; width: auto; }
        }
        /* Phones and tablets: label on the left, everything else stacked on the right */
        @media screen and (max-width: 1023px) {
            .tk-table tfoot tr { flex-wrap: wrap; gap: 4px 12px; }
            .tk-table tfoot td { width: auto !important; min-width: 0; }
            .tk-table tfoot td:empty { display: none; }
            .tk-table td[data-label="Details"] { display: block !important; text-align: left; min-width: 0; }
            .tk-table td[data-label="Details"]::before { display: block; margin-bottom: 4px; }
            .tk-table td[data-label="Details"] .tk-det-table td { display: table-cell; width: auto !important; padding: 1px 8px 1px 0 !important; }
            .tk-table td[data-label="Details"] .tk-det-table tr { display: table-row; margin: 0; border: 0; }
            .tk-table td[data-label="Details"] .tk-det-table, .tk-table td[data-label="Details"] .tk-det-table tbody { display: table; width: 100%; }
            .tk-table td[data-label="Details"] .tk-det-table tbody { display: table-row-group; }
            .tk-table td[data-label="Ticket"], .tk-table td[data-label="Title · type"], .tk-table td[data-label="Raised by"], .tk-table td[data-label="Status"] { display: grid !important; grid-template-columns: auto minmax(0, 1fr); column-gap: 12px; row-gap: 2px; align-items: start; align-content: start; }
            .tk-table td[data-label="Ticket"]::before, .tk-table td[data-label="Title · type"]::before, .tk-table td[data-label="Raised by"]::before, .tk-table td[data-label="Status"]::before { grid-row: 1; }
            .tk-table td[data-label="Ticket"] > *, .tk-table td[data-label="Title · type"] > *, .tk-table td[data-label="Raised by"] > *, .tk-table td[data-label="Status"] > * { grid-column: 2; justify-self: end; text-align: right; }
            .tk-table td.ak-num { display: flex !important; justify-content: space-between !important; align-items: center; }
            .tk-table td.ak-sticky-end { display: flex !important; justify-content: flex-end !important; }
        }
        @media print {
            .tk-ranges, .ak-filters, .ak-tabs { display: none !important; }
            .tk-table { min-width: 0 !important; }
            .tk-table tfoot td { position: static; }
        }
    </style>

    <div class="ak-page" x-data="{ confirm: { open: false, url: '', number: '' }, deleting: false }">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Tickets &middot; {{ $periodLabel }}</div>
            <table class="ak-print-meta">
                <tr><th>Filters</th><td>{{ collect(Arr::except($filters, ['date_from', 'date_to']))->map(fn ($v, $k) => ($chipLabels[$k] ?? Str::headline($k)).': '.($k === 'status' ? ($statusLabels[$v] ?? $v) : $chipValue($k, $v)))->implode(' · ') ?: 'None' }}</td>
                    <th>Printed</th><td>{{ now()->format('d.m.Y H:i') }} by {{ $authUser->name }}</td></tr>
            </table>
        </div>

        @include('tickets.partials.flash')
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section id="tk-filter-panel" class="ak-card tk-filters apl-fields" aria-label="Filters" x-data="{ open: false }" @tk-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="display:none">
            <form method="GET" action="{{ route('tickets.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                <input type="hidden" name="filter[status]" value="{{ $tab !== '' ? $tab : 'all' }}">
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="tk-filter-row">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Ticket no, title, SKU, product, customer, SA no or reference" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_from">From</label>
                        <input id="f_from" type="date" name="filter[date_from]" value="{{ $dateFrom }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_to">To</label>
                        <input id="f_to" type="date" name="filter[date_to]" value="{{ $dateTo }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_supplier">Company</label>
                        <select id="f_supplier" class="tk-select" data-placeholder="All companies" name="filter[supplier_id]">
                            @if ($suppliers->count() !== 1)<option value=""></option>@endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" class="rg-select" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\TicketController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="tk-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>

                <div id="tk-advanced" class="tk-advanced" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_type">Ticket type</label>
                        <select id="f_type" class="tk-select" data-placeholder="All types" name="filter[type]">
                            <option value=""></option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_creator">Raised by</label>
                        <select id="f_creator" class="tk-select" data-placeholder="Anyone" name="filter[created_by]">
                            <option value=""></option>
                            @foreach ($creators as $creator)
                                <option value="{{ $creator->id }}" @selected((string) ($filters['created_by'] ?? '') === (string) $creator->id)>{{ $creator->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_reviewer">Approved / rejected by</label>
                        <select id="f_reviewer" class="tk-select" data-placeholder="Anyone" name="filter[reviewed_by]">
                            <option value=""></option>
                            @foreach ($reviewers as $reviewer)
                                <option value="{{ $reviewer->id }}" @selected((string) ($filters['reviewed_by'] ?? '') === (string) $reviewer->id)>{{ $reviewer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_product">Product / SKU</label>
                        <select id="f_product" class="tk-select" data-placeholder="Any product" name="filter[product_id]">
                            <option value=""></option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->product_code }} — {{ $product->product_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_batch">Batch</label>
                        <input id="f_batch" type="search" name="filter[batch]" value="{{ $filters['batch'] ?? '' }}" autocomplete="off" placeholder="Batch code or part of it">
                    </div>
                    <div class="ak-field">
                        <label for="f_warehouse">Warehouse</label>
                        <select id="f_warehouse" class="tk-select" data-placeholder="Any warehouse" name="filter[warehouse_id]">
                            <option value=""></option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_adjustment_type">Adjustment type</label>
                        <select id="f_adjustment_type" class="tk-select" data-placeholder="Any adjustment" name="filter[adjustment_type]">
                            <option value=""></option>
                            @foreach ($adjustmentTypes as $adjustmentType)
                                <option value="{{ $adjustmentType }}" @selected(($filters['adjustment_type'] ?? '') === $adjustmentType)>{{ Str::headline($adjustmentType) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_category">Category</label>
                        <select id="f_category" class="tk-select" data-placeholder="Any category" name="filter[category_id]">
                            <option value=""></option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <nav class="tk-ranges" aria-label="Quick dates">
                    <span class="tk-ranges-label">Quick dates</span>
                    @foreach ($ranges as $label => [$from, $to])
                        <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                    <a href="{{ $withFilters(Arr::except($filters, ['date_from', 'date_to'])) }}" @if (! $dateFrom && ! $dateTo) aria-current="true" @endif>All</a>
                    <a href="{{ route('tickets.index') }}" class="tk-clear" title="Remove every filter and go back to Pending">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-card" aria-label="Tickets">
            <nav class="ak-tabs" aria-label="Ticket status">
                @foreach (['' => ['All', $stats['total']], 'pending' => ['Pending', $stats['pending']], 'approved' => ['Approved', $stats['approved']], 'rejected' => ['Rejected', $stats['rejected']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl($key) }}" @if ($tab === $key) aria-current="page" @endif class="ak-tab {{ $tab === $key ? 'is-active' : '' }}{{ $key === 'pending' && $count > 0 ? ' tk-tab-warn' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </nav>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters(Arr::only($filters, ['status', 'date_from', 'date_to'])) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($tickets->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('tk-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('tk-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($tickets->total()) }}</b> {{ Str::plural('ticket', $tickets->total()) }}
                            &middot; {{ $periodLabel }}
                            &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Raised on' }}</b> ({{ str_starts_with($sort, '-') ? 'newest first' : 'oldest first' }})
                        </p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Tickets table">
                        <table class="ak-dt tk-table">
                            <caption class="sr-only">Tickets, {{ $tickets->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="tk-c-no">#</th>
                                    <th scope="col" class="tk-c-ticket" aria-sort="{{ $ariaSort($sortField === 'created_at' ? 'created_at' : 'ticket_number') }}"><a href="{{ $sortUrl('ticket_number') }}">Ticket <span aria-hidden="true">{{ $sortMark('ticket_number') }}</span></a> &middot; <a href="{{ $sortUrl('created_at') }}">Date <span aria-hidden="true">{{ $sortMark('created_at') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('title') }}"><a href="{{ $sortUrl('title') }}">Title &middot; type <span aria-hidden="true">{{ $sortMark('title') }}</span></a></th>
                                    <th scope="col" class="tk-c-det">Details</th>
                                    <th scope="col" class="tk-c-man">Raised by</th>
                                    <th scope="col" class="tk-c-stage" aria-sort="{{ $ariaSort('status') }}"><a href="{{ $sortUrl('status') }}">Status <span aria-hidden="true">{{ $sortMark('status') }}</span></a></th>
                                    <th scope="col" class="ak-sticky-end no-print tk-c-act"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tickets as $ticket)
                                    @php
                                        $canChange = $ticket->isPending() && ($ticket->created_by === $authUser->id || $isAdmin);
                                        $canEdit = $canChange && $authUser->can('ticket-edit');
                                        $canDelete = $canChange && $authUser->can('ticket-delete');
                                    @endphp
                                    <tr class="{{ $ticket->isPending() ? 'tk-pending' : '' }}">
                                        <td class="tk-no" data-label="#">{{ $tickets->firstItem() + $loop->index }}</td>
                                        <td data-label="Ticket">
                                            <a href="{{ route('tickets.show', $ticket) }}" class="ak-primary-link" title="{{ $ticket->ticket_number }}">{{ $shortNumber($ticket->ticket_number) }}</a>
                                            <div class="ak-muted tk-meta" title="{{ $ticket->supplier->supplier_name ?? 'No company' }} · {{ $ticket->created_at->format('l, d M Y h:i A') }}">{{ $ticket->supplier ? $shortSupplier($ticket->supplier->supplier_name) : 'No company' }} · {{ $ticket->created_at->format('d M Y') }}</div>
                                        </td>
                                        <td class="tk-desc" data-label="Title · type">
                                            <span class="ak-strong">{{ $ticket->title }}</span>
                                            <div class="tk-type">{{ $ticket->type->label() }}</div>
                                            @if (filled($ticket->description))
                                                <div class="ak-muted tk-desc-remarks" title="{{ $ticket->description }}">{{ Str::limit(preg_replace('/\s+/', ' ', $ticket->description), 90) }}</div>
                                            @endif
                                            @if (filled($ticket->review_remarks))
                                                <div class="ak-muted tk-desc-remarks" title="{{ $ticket->review_remarks }}">Admin: {{ Str::limit(preg_replace('/\s+/', ' ', $ticket->review_remarks), 80) }}</div>
                                            @endif
                                        </td>
                                        <td class="tk-det-cell" data-label="Details">
                                            @include('tickets.partials.row-details', ['ticket' => $ticket, 'lookups' => $lookups])
                                        </td>
                                        <td data-label="Raised by">
                                            <span class="ak-strong">{{ $ticket->creator->name ?? '—' }}</span>
                                            <div class="ak-muted tk-nw">{{ $ticket->created_at->format('h:i A') }}</div>
                                        </td>
                                        <td class="tk-nw" data-label="Status">
                                            <span class="tk-stage tk-stage-{{ $stageTone[$ticket->status->value] ?? 'amber' }}">{{ $ticket->status->label() }}</span>
                                            @if ($ticket->reviewer)
                                                <span class="tk-stage-sub" title="{{ $ticket->reviewed_at?->format('d M Y, h:i A') }}">by {{ $ticket->reviewer->name }}{{ $ticket->reviewed_at ? ' · '.$ticket->reviewed_at->format('d M') : '' }}</span>
                                            @elseif ($ticket->isPending())
                                                <span class="tk-stage-sub">Waiting for admin</span>
                                            @endif
                                        </td>
                                        <td class="ak-sticky-end no-print" data-label="">
                                            <div class="tk-act" x-data="{ open: false, pos: '',
                                                toggle(e) {
                                                    const r = e.currentTarget.getBoundingClientRect();
                                                    const flip = r.bottom + 130 > window.innerHeight;
                                                    this.pos = 'right:' + (window.innerWidth - r.right) + 'px;' + (flip ? 'bottom:' + (window.innerHeight - r.top + 4) + 'px' : 'top:' + (r.bottom + 4) + 'px');
                                                    this.open = ! this.open;
                                                } }" @keydown.escape.window="open = false" @scroll.window="open = false" @resize.window="open = false">
                                                <a href="{{ route('tickets.show', $ticket) }}" class="ak-btn ak-btn-outline ak-btn-sm tk-view" title="Open {{ $ticket->ticket_number }}" aria-label="View {{ $ticket->ticket_number }}">
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    View
                                                </a>
                                                @if ($canEdit || $canDelete)
                                                    <button type="button" class="ak-icon tk-more" @click="toggle($event)" :aria-expanded="open" aria-haspopup="menu" title="More actions" aria-label="More actions for {{ $ticket->ticket_number }}">
                                                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="12" cy="19" r="1.7" /></svg>
                                                    </button>
                                                    <div class="tk-menu" role="menu" x-show="open" x-cloak style="display:none" :style="pos" @click.outside="open = false">
                                                        @if ($canEdit)
                                                            <a role="menuitem" href="{{ route('tickets.edit', $ticket) }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                                Edit ticket
                                                            </a>
                                                        @endif
                                                        @if ($canDelete)
                                                            @if ($canEdit)<hr>@endif
                                                            <button type="button" role="menuitem" class="is-danger"
                                                                @click="open = false; confirm = { open: true, url: @js(route('tickets.destroy', $ticket)), number: @js($ticket->ticket_number) }">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                                                                Delete ticket
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3">Total &middot; {{ number_format($tickets->total()) }} {{ Str::plural('ticket', $tickets->total()) }}{{ $tickets->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td title="Lines on these tickets">{{ number_format($itemsTotal) }} {{ Str::plural('line', $itemsTotal) }}</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($tickets->firstItem()) }}–{{ number_format($tickets->lastItem()) }}</b> of <b>{{ number_format($tickets->total()) }}</b> tickets</p>
                    <div class="ak-pager-right">
                        <div>{{ $tickets->onEachSide(1)->links() }}</div>
                    </div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No tickets found{{ $chips || $tab || $dateFrom || $dateTo ? ' with these filters' : '' }}</h2>
                    <p>Raise a ticket to request a price change, a new SKU or a re-activation, or widen the filters above.</p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        @if ($tab !== '')
                            <a href="{{ $tabUrl('') }}" class="ak-btn ak-btn-outline">Show all statuses</a>
                        @endif
                        @if ($chips || $dateFrom || $dateTo)
                            <a href="{{ route('tickets.index') }}" class="ak-btn ak-btn-outline">Clear filters</a>
                        @endif
                        @can('ticket-create') <a href="{{ route('tickets.create') }}" class="ak-btn ak-btn-primary">＋ New ticket</a> @endcan
                    </div>
                </div>
            @endif
        </section>

        {{-- Delete confirmation --}}
        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="tk-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon ak-pill-red" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="tk-confirm-title">Delete ticket <span x-text="confirm.number"></span>?</h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155">The ticket and its history are removed. Nothing in the system was changed by it. This cannot be undone.</p>
                    </div>
                </div>
                <form method="POST" :action="confirm.url" class="uf-modal-foot" @submit="if (deleting) { $event.preventDefault(); return; } deleting = true">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="submit" class="ak-btn ak-btn-danger-outline" :disabled="deleting">Delete ticket</button>
                </form>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            // the header's filter button opens this page's own filter panel
            document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('tk-toggle-filters')); });
        </script>
        @if (request()->boolean('print'))
            <script>window.addEventListener('load', () => window.print());</script>
        @endif
        <script>
            // Searchable dropdowns: type to find a company, ticket type or person.
            $(function () {
                $('.tk-select').each(function () {
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
