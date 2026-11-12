{{--
    Goods Issues list (/goods-issues). Same layout as Sales Settlements:
    hidden-by-default filters, sticky status tabs, quick dates, removable chips,
    a compact sortable table (View + more-actions menu) and a pager. Query parameters are
    unchanged (filter[...], sort, per_page); dates still default to today.
--}}
@php
    $authUser = auth()->user();
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', '-issue_date');
    $tab = ($filters['status'] ?? '') === 'all' ? '' : ($filters['status'] ?? '');
    $today = now()->toDateString();
    $dateFrom = $filters['issue_date_from'] ?? $today;
    $dateTo = $filters['issue_date_to'] ?? $today;

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $status) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, ['status', 'settlement']);
        $f['status'] = $status !== '' ? $status : 'all';

        return $withFilters($f);
    };
    $pendingUrl = $withFilters(array_merge(\Illuminate\Support\Arr::except($filters, ['status']), ['settlement' => 'pending']));
    $onPending = ($filters['settlement'] ?? '') === 'pending';
    $shortGi = fn (?string $n) => preg_replace('/^GI-\d{4}-0*/', 'GI-', (string) $n);
    // Engro Corporation Ltd -> Engro, Nestle Pakistan (Pvt.) Ltd -> Nestle: drop company suffixes, keep it short.
    $shortSupplier = function (?string $name) {
        $short = trim(preg_replace('/\b(pvt\.?|private|ltd\.?|limited|corporation|corp\.?|company|co\.?|pakistan|foods?)\b|\(.*?\)/i', '', (string) $name));
        $short = trim(preg_replace('/\s+/', ' ', $short), " .-,");

        return \Illuminate\Support\Str::limit($short !== '' ? $short : (string) $name, 14, '…');
    };
    // ALL CAPS names read as shouting: shown in title case (the stored name is unchanged)
    $displayName = fn (string $name) => mb_strtoupper($name) === $name ? \Illuminate\Support\Str::title(mb_strtolower($name)) : $name;
    $helpTopic = $onPending ? 'tab-pending' : ['' => 'tab-all', 'draft' => 'tab-draft', 'issued' => 'tab-issued', 'cancelled' => 'tab-reversed'][$tab] ?? 'tab-all';
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
    $shortWarehouse = fn ($name) => str_replace(['Warehouse - I', 'Warehouse - II', 'Warehouse'], ['W-I', 'W-II', 'W'], (string) $name);
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
        <x-page-header title="Goods Issues" :createRoute="route('goods-issues.create')" createLabel="New goods issue"
            createPermission="goods-issue-create" :showSearch="true" :showRefresh="true" backRoute="dashboard">
            <x-slot name="afterFilter">
                {{-- Help sits next to Filters and opens on the tab being looked at --}}
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: @js($helpTopic) })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How this list works, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
            <x-slot name="actions">
                <x-register-header-actions page="goods-issues" :help="false" :excel-url="request()->fullUrlWithQuery(['export' => 'xlsx', 'page' => null])" />
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="goods-issues" :has-supplier="true" />
    <style>
        /* Apple look for this page's own filter menu: filled fields, Select2 like the rest, pill buttons */
        html.apl-on .rg-gi-filters { border: 0 !important; border-radius: 20px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08) !important; }
        html.apl-on .rg-gi-filters .ak-field label { font-size: 13px; font-weight: 500; color: #6e6e73; text-transform: none; letter-spacing: 0; }
        html.apl-on .rg-gi-filters input[type=search], html.apl-on .rg-gi-filters input[type=date], html.apl-on .rg-gi-filters select:not(.select2-hidden-accessible) {
            height: 40px; border: 0 !important; border-radius: 11px !important; background-color: rgba(118,118,128,.12) !important; box-shadow: none !important; font-size: 15px; color: #1d1d1f; }
        html.apl-on .rg-gi-filters .ak-search { border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; }
        html.apl-on .rg-gi-filters .ak-search input { background: transparent !important; }
        html.apl-on .rg-gi-filters .ak-search kbd { border-radius: 6px; background: #fff; border-color: rgba(0,0,0,.08); }
        html.apl-on .rg-gi-filters select:not(.select2-hidden-accessible) { -webkit-appearance: none; appearance: none; padding-right: 34px !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important; background-repeat: no-repeat !important; background-position: right 12px center !important; }
        html.apl-on .rg-gi-filters input:focus, html.apl-on .rg-gi-filters select:focus, html.apl-on .rg-gi-filters .ak-search:focus-within { outline: none; background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        html.apl-on .rg-gi-filters .ak-search:focus-within input { box-shadow: none !important; }
        html.apl-on .rg-gi-filters .select2-container .select2-selection--single { height: 40px !important; border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; padding: 0 52px 0 12px !important; }
        html.apl-on .rg-gi-filters .select2-container .select2-selection__rendered { padding: 0 !important; font-size: 15px; color: #1d1d1f; }
        html.apl-on .rg-gi-filters .select2-container .select2-selection__placeholder { color: #8e8e93; }
        html.apl-on .rg-gi-filters .select2-container .select2-selection__arrow { top: 0 !important; right: 10px !important; width: 14px !important; height: 100% !important; transform: none !important;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat center / 12px 12px; }
        html.apl-on .rg-gi-filters .select2-container .select2-selection__arrow b { display: none !important; }
        html.apl-on .rg-gi-filters .select2-container--open .select2-selection__arrow { transform: rotate(180deg) !important; }
        html.apl-on .rg-gi-filters .select2-container .select2-selection__clear { position: absolute !important; top: 50% !important; right: 32px !important; float: none !important; margin: 0 !important; transform: translateY(-50%);
            display: grid !important; place-items: center; width: 18px; height: 18px; padding: 0 !important; border-radius: 50%; background: rgba(118,118,128,.38) !important; color: transparent !important; font-size: 0 !important; line-height: 0 !important; cursor: pointer; }
        html.apl-on .rg-gi-filters .select2-container .select2-selection__clear::before { content: ''; width: 8px; height: 8px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='4' stroke-linecap='round'%3E%3Cpath d='M6 6l12 12M18 6 6 18'/%3E%3C/svg%3E") no-repeat center / contain; }
        html.apl-on .rg-gi-filters .select2-container .select2-selection__clear:hover { background: rgba(118,118,128,.6) !important; }
        html.apl-on .rg-gi-filters .select2-container--focus .select2-selection--single, html.apl-on .rg-gi-filters .select2-container--open .select2-selection--single { background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        html.apl-on .rg-gi-filters .ak-btn { height: 38px; border-radius: 980px !important; text-transform: none; transition: transform .1s ease-out, background-color .15s ease; }
        html.apl-on .rg-gi-filters .ak-btn:active { transform: scale(.97); }
        html.apl-on .rg-gi-filters .ak-btn-primary { background: #0071e3 !important; border-color: #0071e3 !important; }
        html.apl-on .rg-gi-filters .ak-btn-ghost { background: rgba(120,120,128,.14) !important; border: 0 !important; color: #1d1d1f !important; }
        html.apl-on .rg-gi-filters .gl-ranges a { border: 0 !important; background: rgba(118,118,128,.12); color: #1d1d1f; font-weight: 500; font-size: 13px; padding: 5px 12px; transition: background-color .15s ease, transform .1s ease-out; }
        html.apl-on .rg-gi-filters .gl-ranges a:hover { background: rgba(118,118,128,.22); }
        html.apl-on .rg-gi-filters .gl-ranges a:active { transform: scale(.96); }
        html.apl-on .rg-gi-filters .gl-ranges a[aria-current] { background: #0071e3; color: #fff; }
        html.apl-on .rg-gi-filters .gl-ranges .gl-clear { background: rgba(255,59,48,.1); color: #d70015; }
        html.apl-on .gl-ranges-inline a { border: 0 !important; background: rgba(118,118,128,.12); color: #1d1d1f; font-weight: 500; }
        html.apl-on .gl-ranges-inline a:hover { background: rgba(118,118,128,.22); }
        html.apl-on .gl-ranges a[aria-current] { background: #0071e3 !important; border-color: #0071e3 !important; color: #fff !important; }
        html.apl-on .rg-gi-filters .gl-ranges-label { font-size: 13px; font-weight: 500; color: #6e6e73; }
        /* stage: one capsule for where the goods issue stands */
        html.apl-on .gl-stage-link { text-decoration: none; display: inline-flex; flex-direction: column; gap: 2px; }
        .gl-stage { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .gl-stage::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .gl-stage-green { background: rgba(52,199,89,.14); color: #1f7a35; }
        .gl-stage-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .gl-stage-red { background: rgba(255,59,48,.12); color: #c4161c; }
        .gl-stage-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
        .gl-stage-sub { display: block; margin-top: 3px; font-size: 11.5px; color: #6e6e73; }
        .gl-stage-link:hover .gl-stage-sub { color: #0071e3; text-decoration: underline; }
        /* products: one line each, name shortened, quantity kept */
        .gl-prod-line { display: flex; gap: 6px; min-width: 0; max-width: 300px; }
        .gl-prod-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gl-prod-qty { flex: none; color: #6e6e73; font-variant-numeric: tabular-nums; }
        .gl-pending-link { margin-left: 2px; color: #8a4b00; font-weight: 600; text-decoration: none; }
        .gl-pending-link:hover { text-decoration: underline; }
        /* accessibility: readable secondary text, 24px hit areas */
        html.apl-on .gl-table .ak-muted, html.apl-on .ak-tab .ak-count { color: #6e6e73 !important; }
        html.apl-on .gl-table .ak-primary-link { display: inline-block; padding: 4px 0; }
        .rg-info { position: relative; }
        .rg-info::after { content: ''; position: absolute; inset: -4px; }
        /* seven columns that fit the 7xl page without sideways scrolling */
        html.apl-on .gl-table td[data-label="Issue"] .ak-primary-link { font-weight: 600; letter-spacing: -.01em; white-space: nowrap; }
        .gl-meta { white-space: nowrap; }
        .gl-c-issue { width: 190px; } .gl-c-man { width: 190px; } .gl-c-qty { width: 72px; } .gl-c-val { width: 110px; } .gl-c-stage { width: 160px; } .gl-c-act { width: 1%; }
        html.apl-on .gl-stage-link { display: inline-flex; flex-direction: column; align-items: flex-start; }
        .gl-prod-line { max-width: 340px; }
        .ak-dt-scroll.is-compact .gl-prod-line + .gl-prod-line, .ak-dt-scroll.is-compact .gl-stage-sub { display: none; }
        .ak-dt-scroll.is-compact .gl-more-inline { display: inline; }
        .gl-more-inline { display: none; margin-left: 4px; color: #6e6e73; }
        .ak-dt-scroll.is-compact .gl-more-line { display: none; }
        .ak-dt-scroll.is-compact .gl-table td { padding-top: 6px; padding-bottom: 6px; vertical-align: middle; }
        .ak-dt-scroll.is-compact .gl-table td .ak-muted { font-size: 12px; }

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
        .gl-prod { font-size: 13px; line-height: 1.35; min-width: 190px; }
        #gl-filter-panel { overflow: hidden; }
        .gl-ranges a.gl-clear { border-color: #fecaca; color: #b91c1c; background: #fff; }
        .gl-ranges a.gl-clear:hover { background: #fef2f2; }
        .gl-ranges-inline { margin-top: 0; }
        .gl-nw { white-space: nowrap; }
        .gl-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #16a34a; box-shadow: 0 0 0 3px #dcfce7; vertical-align: middle; margin-left: 4px; }
        .gl-dot-text { display: none; }
        @media screen and (max-width: 767px) { .gl-dot-text { display: inline; font-size: 13px; font-weight: 600; color: #15803d; margin-left: 8px; } }
        @media print { .gl-dot { display: none; } .gl-dot-text { display: inline; } }
        @media (min-width: 768px) {
            .gl-table thead th:nth-last-child(-n+3), .gl-table tbody > tr > td:nth-last-child(-n+3) { width: 1%; white-space: nowrap; }
            .gl-table thead th:nth-last-child(2), .gl-table tbody > tr > td:nth-last-child(2) { padding-left: 18px; padding-right: 18px; }
            .gl-table thead th:nth-last-child(1), .gl-table tbody > tr > td:nth-last-child(1) { padding-left: 8px; }
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
        .gl-tab-warn:not(.is-active) .ak-count { background: #fef3c7; color: #b45309; }
        section[aria-label="Goods issues"] > .ak-tabs { position: sticky; top: 0; z-index: 25; background: #fff; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }
        .ak-dt-scroll { max-height: calc(100vh - 190px); min-height: 18rem; }
        .gl-draft > td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
        .gl-draft > td { background: #fffbeb; }
        .gl-table > tbody > tr:hover > td { background: #f1f5f9; }
        .gl-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
        .gl-settle a { color: inherit; text-decoration: none; }
        .gl-settle a:hover .ak-status { text-decoration: underline; }
        .gl-age { display: block; margin-top: 2px; font-size: 11.5px; }
        .gl-late { color: #b91c1c; font-weight: 600; }
        /* Searchable dropdowns (Select2) sized like the other filter fields */
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
        .ak-filters .select2-container--focus .select2-selection--single, .ak-filters .select2-container--open .select2-selection--single { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
        .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
        .select2-search--dropdown .select2-search__field { border-radius: 6px; padding: 6px 8px; }
        .gl-table > tbody > tr > td { border-bottom: 1px solid #e5e7eb; }
        .gl-table > tbody:last-of-type > tr:last-child > td { border-bottom: 0; }
/* Row number, and the issue number / products that open the Quick look */
        .gl-c-no { width: 40px; }
        .gl-table td.gl-no { width: 40px; color: #8e8e93; font-size: 12.5px; font-variant-numeric: tabular-nums; }
        .gl-prod-peek { display: block; width: 100%; padding: 0; border: 0; background: none; font: inherit; color: inherit; text-align: left; cursor: pointer; border-radius: 8px; }
        .gl-prod-peek:hover .gl-prod-name { color: #0066cc; }
        .gl-prod-peek:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; }
        @media screen and (max-width: 767px) { .gl-table td.gl-no { display: none !important; } }

        /* Quick look: one sheet for every row, Apple style */
        .gq-wrap { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .gq-scrim { position: absolute; inset: 0; background: rgba(0,0,0,.28); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); }
        .gq-box { position: relative; width: 100%; max-width: 560px; max-height: min(86vh, 760px); display: flex; flex-direction: column; overflow: hidden; background: #fff; border-radius: 22px; box-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 24px 64px -12px rgba(0,0,0,.35); transform-origin: center; }
        .gq-head { padding: 20px 22px 0; }
        .gq-top { display: flex; align-items: center; gap: 8px; }
        .gq-num { margin: 0; font-size: 15px; font-weight: 600; color: #1d1d1f; letter-spacing: -.01em; }
        .gq-close { margin-left: auto; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 50%; background: rgba(120,120,128,.14); color: #3a3a40; cursor: pointer; transition: transform .12s ease; }
        .gq-close:active { transform: scale(.94); }
        .gq-close:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; }
        .gq-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
        .gq-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; }
        .gq-chip-dot::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .gq-green { background: rgba(52,199,89,.14); color: #1f7a35; } .gq-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .gq-red { background: rgba(255,59,48,.12); color: #c4161c; } .gq-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
        .gq-blue { background: rgba(0,113,227,.1); color: #0058b0; }
        .gq-amount { margin: 14px 0 0; font-size: 34px; font-weight: 600; letter-spacing: -.03em; line-height: 1.05; color: #1d1d1f; font-variant-numeric: tabular-nums; }
        .gq-amount small { font-size: 17px; font-weight: 500; color: #6e6e73; margin-right: 4px; }
        .gq-who { margin: 6px 0 0; font-size: 14px; line-height: 1.5; color: #424245; }
        .gq-who b { color: #1d1d1f; font-weight: 600; }
        .gq-who span { color: #6e6e73; }
        .gq-list-head { display: flex; justify-content: space-between; margin: 16px 22px 0; padding-bottom: 6px; border-bottom: 1px solid rgba(60,60,67,.14); font-size: 11.5px; font-weight: 600; color: #6e6e73; }
        .gq-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; margin: 0; padding: 0 22px; list-style: none; }
        .gq-list li { display: flex; align-items: baseline; gap: 12px; padding: 9px 0; border-bottom: 1px solid rgba(60,60,67,.08); font-size: 14px; }
        .gq-list li:last-child { border-bottom: 0; }
        .gq-line-name { flex: 1 1 auto; min-width: 0; color: #1d1d1f; overflow-wrap: anywhere; }
        .gq-line-name small { display: block; margin-top: 1px; font-size: 12px; color: #6e6e73; }
        .gq-line-val { flex: none; font-weight: 600; color: #1d1d1f; font-variant-numeric: tabular-nums; }
        .gq-supp { margin-left: 4px; padding: 0 7px; border-radius: 980px; font-size: 10.5px; font-weight: 600; background: rgba(175,82,222,.14); color: #7a2fa3; }
        .gq-foot { padding: 12px 22px 18px; border-top: 1px solid rgba(60,60,67,.14); background: #fbfbfd; }
        .gq-totals { display: flex; justify-content: space-between; gap: 12px; font-size: 14px; color: #424245; }
        .gq-totals b { color: #1d1d1f; font-variant-numeric: tabular-nums; }
        .gq-open { display: flex; align-items: center; justify-content: center; gap: 6px; height: 40px; margin-top: 12px; border-radius: 980px; background: #0071e3; color: #fff !important; font-size: 14.5px; font-weight: 500; text-decoration: none !important; transition: transform .12s ease, background-color .15s ease; }
        .gq-open:hover { background: #0077ed; }
        .gq-open:active { transform: scale(.98); }
        .gq-enter { transition: opacity .2s cubic-bezier(.23,1,.32,1), transform .2s cubic-bezier(.23,1,.32,1); }
        .gq-leave { transition: opacity .12s ease-out, transform .12s ease-out; }
        .gq-from { opacity: 0; transform: scale(.96) translateY(6px); }
        .gq-fade-enter { transition: opacity .2s ease; } .gq-fade-leave { transition: opacity .12s ease; } .gq-fade-from { opacity: 0; }
        @media (max-width: 640px) {
            .gq-wrap { align-items: flex-end; padding: 0; }
            .gq-box { max-width: none; max-height: 90vh; border-radius: 20px 20px 0 0; }
            .gq-from { transform: translateY(24px); opacity: 0; }
            .gq-head { padding: 16px 18px 0; } .gq-list-head { margin: 14px 18px 0; } .gq-list { padding: 0 18px; } .gq-foot { padding: 12px 18px calc(16px + env(safe-area-inset-bottom)); }
            .gq-amount { font-size: 30px; }
        }
        @media (prefers-reduced-motion: reduce) { .gq-enter, .gq-leave { transition: opacity .15s ease; } .gq-from { transform: none; } }
        /* Laptops (1024–1279px): the table fits without sideways scrolling; Qty and the posting time step aside (both are in the Quick look) */
        @media screen and (min-width: 1024px) and (max-width: 1279px) {
            .gl-table { min-width: 0 !important; }
            .gl-table .gl-c-qty, .gl-table td[data-label="Qty"] { display: none; }
            .gl-table .ak-hide-compact { display: none; }
            .gl-prod { min-width: 150px; }
            .gl-prod-line { max-width: 220px; }
            .gl-table .gl-c-issue { width: auto; }
            .gl-table .gl-meta { white-space: normal; }
        }
        /* Tablets (768–1023px): rows become cards, as on a phone */
        @media screen and (min-width: 768px) and (max-width: 1023px) {
            .gl-table { min-width: 0 !important; }
            .gl-table thead { display: none; }
            .gl-table, .gl-table tbody, .gl-table tr, .gl-table td, .gl-table tfoot { display: block; width: 100%; }
            .gl-table tbody tr { margin: 12px; width: auto; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); overflow: hidden; }
            .gl-table td { display: flex; justify-content: space-between; gap: 12px; padding: 8px 12px; text-align: right; }
            .gl-table tbody > tr > td { width: 100% !important; white-space: normal; padding-left: 12px !important; padding-right: 12px !important; }
            .gl-table td::before { content: attr(data-label); font-size: 12px; font-weight: 700; color: var(--ak-muted); text-align: left; }
            .gl-table td[data-label=""]::before, .gl-table td.gl-no { display: none; }
            .gl-table .ak-sticky-end { position: static; box-shadow: none; width: auto; min-width: 0; }
            .gl-table tfoot tr { display: flex; justify-content: space-between; }
            .gl-table tfoot td { position: static; width: auto; }
        }
        /* Phones and tablets: the products button never runs out of its card */
        @media screen and (max-width: 1023px) {
            .gl-table td.gl-prod { min-width: 0; }
            .gl-prod-peek { flex: 1 1 auto; min-width: 0; width: auto; text-align: right; }
            .gl-prod-line { max-width: 100%; justify-content: flex-end; }
            .gl-prod-name { flex: 0 1 auto; }
            .gl-table tfoot tr { flex-wrap: wrap; gap: 4px 12px; }
            .gl-table tfoot td { width: auto !important; min-width: 0; }
            .gl-table tfoot td:empty { display: none; }
            /* Cards: label on the left, everything else stacked on the right */
            .gl-table td[data-label="Issue"], .gl-table td[data-label="Salesman · vehicle"] { display: grid !important; grid-template-columns: auto minmax(0, 1fr); column-gap: 12px; row-gap: 2px; align-items: start; align-content: start; }
            .gl-table td[data-label="Issue"]::before, .gl-table td[data-label="Salesman · vehicle"]::before { grid-row: 1; }
            .gl-table td[data-label="Issue"] > *, .gl-table td[data-label="Salesman · vehicle"] > * { grid-column: 2; justify-self: end; text-align: right; }
            .gl-table td.ak-num, .gl-table td.gl-nw { display: flex !important; justify-content: space-between !important; align-items: center; }
            .gl-table td.gl-nw > a, .gl-table td.gl-nw > span { margin-left: auto; }
            .gl-table td.ak-sticky-end { display: flex !important; justify-content: flex-end !important; }
        }
        @media print {
            .gl-ranges, .ak-filters, .ak-tabs { display: none !important; }
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

        <section id="gl-filter-panel" class="ak-card rg-gi-filters" aria-label="Filters" x-data="{ open: false }" @gl-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="display:none">
            <form method="GET" action="{{ route('goods-issues.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                <input type="hidden" name="filter[status]" value="{{ $tab !== '' ? $tab : 'all' }}">
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
                        <select id="f_employee" class="gl-select" data-placeholder="All salesmen" name="filter[employee_id]">
                            <option value=""></option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->name }}{{ $employee->employee_code ? ' ('.$employee->employee_code.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_vehicle">Vehicle</label>
                        <select id="f_vehicle" class="gl-select" data-placeholder="All vehicles" name="filter[vehicle_id]">
                            <option value=""></option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected((string) ($filters['vehicle_id'] ?? '') === (string) $vehicle->id)>{{ $vehicle->vehicle_number }} ({{ $vehicle->vehicle_type }})</option>
                            @endforeach
                        </select>
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
                    <div class="ak-field">
                        <label for="f_settlement">Settlement</label>
                        <select id="f_settlement" class="rg-select" name="filter[settlement]">
                            <option value="">Settled and not settled</option>
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
                    <a href="{{ route('goods-issues.index') }}" class="gl-clear" title="Remove every filter and go back to today">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-card" aria-label="Goods issues">
            <nav class="ak-tabs" aria-label="Goods issue stage">
                @php $giTabs = [['', 'All', $stats['total'], $tab === '' && ! $onPending, $tabUrl('')], ['draft', 'Draft', $stats['draft'], $tab === 'draft' && ! $onPending, $tabUrl('draft')], ['issued', 'Issued', $stats['issued'], $tab === 'issued' && ! $onPending, $tabUrl('issued')], ['pending', 'Not settled', $stats['pending'], $onPending, $pendingUrl], ['cancelled', 'Reversed', $stats['cancelled'], $tab === 'cancelled' && ! $onPending, $tabUrl('cancelled')]]; @endphp
                @foreach ($giTabs as [$key, $label, $count, $on, $url])
                    <a href="{{ $url }}" @if ($on) aria-current="page" @endif class="ak-tab {{ $on ? 'is-active' : '' }}{{ $key === 'pending' && $count > 0 ? ' gl-tab-warn' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
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
                            @if (($stats['pending'] ?? 0) > 0 && ! $onPending)
                                &middot; <a href="{{ $pendingUrl }}" class="gl-pending-link">{{ number_format($stats['pending']) }} on vans, not settled <span aria-hidden="true">→</span></a>
                            @endif
                        </p>
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
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Goods issues table">
                        <table class="ak-dt gl-table">
                            <caption class="sr-only">Goods issues, {{ $goodsIssues->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="gl-c-no">#</th>
                                    <th scope="col" class="gl-c-issue" aria-sort="{{ $ariaSort($sortField === 'issue_date' ? 'issue_date' : 'issue_number') }}"><a href="{{ $sortUrl('issue_number') }}">Issue <span aria-hidden="true">{{ $sortMark('issue_number') }}</span></a> &middot; <a href="{{ $sortUrl('issue_date') }}">Date <span aria-hidden="true">{{ $sortMark('issue_date') }}</span></a></th>
                                    <th scope="col" class="gl-c-man">Salesman &middot; vehicle</th>
                                    <th scope="col" class="gl-c-prod">Products</th>
                                    <th scope="col" class="ak-num gl-c-qty" title="Total quantity issued (units)">Qty</th>
                                    <th scope="col" class="ak-num gl-c-val" aria-sort="{{ $ariaSort('total_value') }}"><a href="{{ $sortUrl('total_value') }}">Value (Rs) <span aria-hidden="true">{{ $sortMark('total_value') }}</span></a></th>
                                    <th scope="col" class="gl-c-stage">Stage</th>
                                    <th scope="col" class="ak-sticky-end no-print gl-c-act"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            @foreach ($goodsIssues as $gi)
                                @php
                                    $settlement = $gi->settlement->sortByDesc('id')->first();
                                    $daysOut = $gi->status === 'issued' && ! $settlement ? (int) abs($gi->issue_date?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0) : null;
                                    $canPeek = $gi->relationLoaded('items') && $gi->items->isNotEmpty();
                                    $canEdit = $gi->status === 'draft' && auth()->user()->can('goods-issue-edit');
                                    $canDelete = $gi->status === 'draft' && auth()->user()->can('goods-issue-delete');
                                    $peek = null;
                                    if ($canPeek) {
                                        $peekSettled = $settlement && in_array($settlement->status, ['verified', 'posted'], true);
                                        [$peekStage, $peekTone] = match (true) {
                                            $gi->status === 'draft' => ['Draft', 'grey'],
                                            $gi->status === 'cancelled' => ['Reversed', 'red'],
                                            $peekSettled => ['Settled', 'green'],
                                            (bool) $settlement => ['Settlement draft', 'amber'],
                                            default => ['On van · '.($daysOut === 0 ? 'today' : $daysOut.' '.\Illuminate\Support\Str::plural('day', $daysOut)), $daysOut >= 2 ? 'red' : 'amber'],
                                        };
                                        $peek = [
                                            'number' => $gi->issue_number,
                                            'url' => route('goods-issues.show', $gi),
                                            'stage' => $peekStage,
                                            'tone' => $peekTone,
                                            'settlement' => $settlement?->settlement_number,
                                            'supplier' => $gi->supplier->supplier_name ?? '',
                                            'value' => number_format((float) $gi->total_value),
                                            'salesman' => $displayName($gi->employee->name ?? '—'),
                                            'vehicle' => $gi->vehicle->vehicle_number ?? '—',
                                            'warehouse' => $gi->warehouse->warehouse_name ?? '',
                                            'date' => $gi->issue_date?->format('D, d M Y').($gi->posted_at ? ' · posted '.$gi->posted_at->format('h:i A') : ' · not posted'),
                                            'qty' => $qty($gi->items->sum('quantity_issued')),
                                            'total' => number_format((float) $gi->items->sum('total_value'), 2),
                                            'lines' => $gi->items->map(fn ($line) => [
                                                'name' => $line->product->product_name ?? '—',
                                                'qty' => rtrim(rtrim(number_format((float) $line->quantity_issued, 3), '0'), '.'),
                                                'rate' => number_format((float) $line->selling_price, 2),
                                                'value' => number_format((float) $line->total_value, 2),
                                                'supplementary' => (bool) $line->is_supplementary,
                                            ])->values(),
                                        ];
                                    }
                                @endphp
                                <tbody>
                                    <tr class="{{ $gi->status === 'draft' ? 'gl-draft' : '' }}">
                                        <td class="gl-no" data-label="#">{{ $goodsIssues->firstItem() + $loop->index }}</td>
                                        <td data-label="Issue">
                                            <a href="{{ route('goods-issues.show', $gi) }}" class="ak-primary-link"
                                                @if ($peek) x-data @click="if (! ($event.metaKey || $event.ctrlKey || $event.shiftKey)) { $event.preventDefault(); $dispatch('gi-peek', @js($peek)) }" aria-haspopup="dialog" title="Quick look at {{ $gi->issue_number }}" @else title="{{ $gi->issue_number }} · issued by {{ $gi->issuedBy->name ?? '—' }}" @endif>{{ $shortGi($gi->issue_number) }}<span class="sr-only">, issued by {{ $gi->issuedBy->name ?? '—' }}</span></a>
                                            <div class="ak-muted gl-meta" title="{{ $gi->supplier->supplier_name ?? '' }} · {{ $gi->issue_date?->format('l, d M Y') }}{{ $gi->posted_at ? ' · posted '.$gi->posted_at->format('h:i A') : '' }}">{{ $shortSupplier($gi->supplier->supplier_name ?? '') ?: '—' }} · {{ $gi->issue_date?->format('d M Y') }}<span class="ak-hide-compact"> · {{ $gi->posted_at ? $gi->posted_at->format('h:i A') : 'not posted' }}</span></div>
                                        </td>
                                        <td data-label="Salesman · vehicle">
                                            <span class="ak-strong">{{ $displayName($gi->employee->name ?? '—') }}</span>
                                            <div class="ak-muted" title="{{ $gi->warehouse->warehouse_name ?? '' }}">{{ $gi->vehicle->vehicle_number ?? '—' }}{{ $gi->warehouse ? ' · '.$shortWarehouse($gi->warehouse->warehouse_name) : '' }}</div>
                                        </td>
                                        <td class="gl-prod" data-label="Products">
                                            @if ($peek)
                                                <button type="button" class="gl-prod-peek" x-data @click="$dispatch('gi-peek', @js($peek))" aria-haspopup="dialog" aria-label="Quick look at the products on {{ $gi->issue_number }}">
                                                @php $top = $gi->items->sortByDesc('total_value')->take(2); $more = $gi->items->count() - 1; @endphp
                                                @foreach ($top as $line)
                                                    <div class="gl-prod-line" title="{{ $line->product->product_name ?? '' }}"><span class="gl-prod-name">{{ $line->product->product_name ?? '—' }}</span><span class="gl-prod-qty">× {{ $qty($line->quantity_issued) }}</span>@if ($loop->first && $more > 0)<span class="gl-more-inline">+{{ $more }}</span>@endif</div>
                                                @endforeach
                                                @if ($gi->items->count() > 2)<div class="ak-muted gl-more-line">+ {{ $gi->items->count() - 2 }} more {{ \Illuminate\Support\Str::plural('product', $gi->items->count() - 2) }}</div>@endif
                                                </button>
                                            @else
                                                <span class="ak-muted">{{ number_format($gi->items_count) }} {{ \Illuminate\Support\Str::plural('line', $gi->items_count) }}</span>
                                            @endif
                                        </td>
                                        <td class="ak-num" data-label="Qty">{{ $gi->relationLoaded('items') ? $qty($gi->items->sum('quantity_issued')) : '—' }}</td>
                                        <td class="ak-num ak-strong" data-label="Value (Rs)" title="{{ number_format((float) $gi->total_value, 2) }}">{{ number_format((float) $gi->total_value) }}</td>
                                        <td class="gl-nw" data-label="Stage">
                                            @php
                                                $settled = $settlement && in_array($settlement->status, ['verified', 'posted'], true);
                                                [$stageText, $stageTone] = match (true) {
                                                    $gi->status === 'draft' => ['Draft', 'grey'],
                                                    $gi->status === 'cancelled' => ['Reversed', 'red'],
                                                    $settled => ['Settled', 'green'],
                                                    (bool) $settlement => ['Settlement draft', 'amber'],
                                                    default => ['On van · '.($daysOut === 0 ? 'today' : $daysOut.' '.\Illuminate\Support\Str::plural('day', $daysOut)), $daysOut >= 2 ? 'red' : 'amber'],
                                                };
                                            @endphp
                                            @if ($settlement)
                                                @can('sales-settlement-list')<a href="{{ route('sales-settlements.show', $settlement) }}" class="gl-stage-link" title="Open {{ $settlement->settlement_number }}">@endcan
                                            @endif
                                            <span class="gl-stage gl-stage-{{ $stageTone }}">{{ $stageText }}</span>
                                            @if ($settlement)
                                                <span class="gl-stage-sub">{{ $settlement->settlement_number }} · {{ \Illuminate\Support\Str::headline($settlement->status) }}</span>
                                                @can('sales-settlement-list')</a>@endcan
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
                                                <a href="{{ route('goods-issues.show', $gi) }}" class="ak-btn ak-btn-outline ak-btn-sm gl-view" title="Open {{ $gi->issue_number }}" aria-label="View {{ $gi->issue_number }}">
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    View
                                                </a>
                                                @if ($canEdit || $canDelete)
                                                    <button type="button" class="ak-icon gl-more" @click="toggle($event)" :aria-expanded="open" aria-haspopup="menu" title="More actions" aria-label="More actions for {{ $gi->issue_number }}">
                                                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="12" cy="19" r="1.7" /></svg>
                                                    </button>
                                                    <div class="gl-menu" role="menu" x-show="open" x-cloak style="display:none" :style="pos" @click.outside="open = false">
                                                        @if ($canEdit)
                                                            <a role="menuitem" href="{{ route('goods-issues.edit', $gi) }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                                Edit draft
                                                            </a>
                                                        @endif
                                                        @if ($canDelete)
                                                            @if ($canEdit)<hr>@endif
                                                            <button type="button" role="menuitem" class="is-danger"
                                                                @click="open = false; confirm = { open: true, url: @js(route('goods-issues.destroy', $gi)), number: @js($gi->issue_number) }">
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
                                    <td colspan="3">Total &middot; {{ number_format($goodsIssues->total()) }} {{ \Illuminate\Support\Str::plural('issue', $goodsIssues->total()) }}{{ $goodsIssues->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td class="ak-muted" style="font-weight:400">{{ $goodsIssues->hasPages() ? '* quantity on this page' : '' }}</td>
                                    <td class="ak-num">{{ $goodsIssues->getCollection()->first()?->relationLoaded('items') ? $qty($goodsIssues->getCollection()->sum(fn ($g) => $g->items->sum('quantity_issued'))) : '' }}{{ $goodsIssues->hasPages() ? '*' : '' }}</td>
                                    <td class="ak-num" title="{{ number_format((float) $totalValue, 2) }}">{{ number_format((float) $totalValue) }}</td>
                                    <td colspan="2"></td>
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
            // the header's filter button opens this page's own filter panel
            document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('gl-toggle-filters')); });
        </script>
        @if (request()->boolean('print'))
            <script>window.addEventListener('load', () => window.print());</script>
        @endif
        <script>
            // Searchable dropdowns: type to find a supplier, salesman, vehicle or warehouse.
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
    {{-- Quick look: opened by the issue number or the products of any row; View still opens the full page --}}
    <div x-data="{ open: false, gi: null, opener: null,
            show(detail) { this.opener = document.activeElement; this.gi = detail; this.open = true; this.$nextTick(() => this.$refs.close.focus()); },
            hide() { this.open = false; this.opener?.focus(); } }"
        @gi-peek.window="show($event.detail)" @keydown.escape.window="open && hide()">
        <template x-if="gi">
            <div class="gq-wrap" x-show="open" role="dialog" aria-modal="true" aria-labelledby="gq-title" x-trap.noscroll="open">
                <div class="gq-scrim" @click="hide()" x-show="open"
                    x-transition:enter="gq-fade-enter" x-transition:enter-start="gq-fade-from" x-transition:leave="gq-fade-leave" x-transition:leave-end="gq-fade-from"></div>
                <div class="gq-box" x-show="open"
                    x-transition:enter="gq-enter" x-transition:enter-start="gq-from" x-transition:leave="gq-leave" x-transition:leave-end="gq-from">
                    <div class="gq-head">
                        <div class="gq-top">
                            <h2 class="gq-num" id="gq-title" x-text="gi.number"></h2>
                            <button type="button" class="gq-close" x-ref="close" @click="hide()" aria-label="Close quick look">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                            </button>
                        </div>
                        <div class="gq-chips">
                            <span class="gq-chip gq-chip-dot" :class="'gq-' + gi.tone" x-text="gi.stage + (gi.settlement ? ' · ' + gi.settlement : '')"></span>
                            <span class="gq-chip gq-blue" x-show="gi.supplier" x-text="gi.supplier"></span>
                        </div>
                        <p class="gq-amount"><small>Rs</small><span x-text="gi.value"></span></p>
                        <p class="gq-who"><b x-text="gi.salesman"></b> · <span x-text="gi.vehicle"></span><template x-if="gi.warehouse"><span x-text="' · ' + gi.warehouse"></span></template><br><span x-text="gi.date"></span></p>
                    </div>
                    <div class="gq-list-head"><span x-text="gi.lines.length + (gi.lines.length === 1 ? ' product' : ' products')"></span><span>Value (Rs)</span></div>
                    <ul class="gq-list">
                        <template x-for="(line, index) in gi.lines" :key="index">
                            <li>
                                <span class="gq-line-name"><span x-text="line.name"></span><span class="gq-supp" x-show="line.supplementary">supplementary</span><small x-text="line.qty + ' × ' + line.rate"></small></span>
                                <span class="gq-line-val" x-text="line.value"></span>
                            </li>
                        </template>
                    </ul>
                    <div class="gq-foot">
                        <div class="gq-totals"><span>Quantity <b x-text="gi.qty"></b></span><span>Total <b x-text="'Rs ' + gi.total"></b></span></div>
                        <a :href="gi.url" class="gq-open">Open full issue <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>
