{{--
    Goods Issues list, Apple layout (Settings → Appearance → Goods issues list → Option A). English only.
    One search field that is always in view, quick dates, segmented stage tabs, and the issues as a grouped list
    (one group per issue day) instead of a wide table, so it fits a phone without sideways scrolling. Tapping a row
    opens the Quick look; the full page opens with ⌘/Ctrl-click or from the Quick look. Same query parameters,
    data and counts as the classic list (filter[...], sort, per_page; dates default to today).
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', '-issue_date');
    $sortField = ltrim($sort, '-');
    $tab = ($filters['status'] ?? '') === 'all' ? '' : ($filters['status'] ?? '');
    $onPending = ($filters['settlement'] ?? '') === 'pending';
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
    $rangeUrl = fn (string $from, string $to) => $withFilters(array_merge($filters, ['issue_date_from' => $from, 'issue_date_to' => $to]));
    $ranges = [
        'Today' => [$today, $today],
        'Yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
        'This week' => [now()->startOfWeek()->toDateString(), $today],
        'This month' => [now()->startOfMonth()->toDateString(), $today],
        'Last 30 days' => [now()->subDays(29)->toDateString(), $today],
        'All' => ['2020-01-01', $today],
    ];
    $sorts = ['-issue_date' => 'Newest first', 'issue_date' => 'Oldest first', '-total_value' => 'Highest value', 'total_value' => 'Lowest value', 'issue_number' => 'Issue number'];

    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
    $displayName = fn (string $name) => mb_strtoupper($name) === $name ? \Illuminate\Support\Str::title(mb_strtolower($name)) : $name;
    $shortWarehouse = fn ($name) => str_replace(['Warehouse - I', 'Warehouse - II', 'Warehouse'], ['W-I', 'W-II', 'W'], (string) $name);
    $periodLabel = $dateFrom === $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
        : \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y');

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
    $chips = \Illuminate\Support\Arr::except($filters, ['status', 'issue_date_from', 'issue_date_to', 'issue_date', 'search']);
    $moreCount = collect(['supplier_id', 'warehouse_id', 'vehicle_id', 'employee_id', 'settlement'])->filter(fn ($k) => isset($filters[$k]))->count();

    $tabs = [
        ['All', $stats['total'], $tab === '' && ! $onPending, $tabUrl('')],
        ['Drafts', $stats['draft'], $tab === 'draft' && ! $onPending, $tabUrl('draft')],
        ['Issued', $stats['issued'], $tab === 'issued' && ! $onPending, $tabUrl('issued')],
        ['Not settled', $stats['pending'], $onPending, $pendingUrl],
        ['Reversed', $stats['cancelled'], $tab === 'cancelled' && ! $onPending, $tabUrl('cancelled')],
    ];

    // Where each issue stands, as on the classic list: draft, on van (days), settlement draft, settled or reversed.
    $stageOf = function ($gi) {
        $settlement = $gi->settlement->sortByDesc('id')->first();
        $daysOut = $gi->status === 'issued' && ! $settlement ? (int) abs($gi->issue_date?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0) : null;

        [$text, $tone] = match (true) {
            $gi->status === 'draft' => ['Draft', 'grey'],
            $gi->status === 'cancelled' => ['Reversed', 'red'],
            $settlement && in_array($settlement->status, ['verified', 'posted'], true) => ['Settled', 'green'],
            (bool) $settlement => ['Settlement draft', 'amber'],
            default => ['On van · '.($daysOut === 0 ? 'today' : $daysOut.' '.\Illuminate\Support\Str::plural('day', $daysOut)), $daysOut >= 2 ? 'red' : 'amber'],
        };

        return [$text, $tone, $settlement];
    };

    // Rows grouped by issue day when the list is sorted by date; one group otherwise.
    $groups = $sortField === 'issue_date'
        ? $goodsIssues->getCollection()->groupBy(fn ($gi) => $gi->issue_date?->toDateString() ?? '')
        : collect(['' => $goodsIssues->getCollection()]);
    $dayLabel = function (string $date) use ($today) {
        if ($date === '') {
            return 'No date';
        }
        $day = \Carbon\Carbon::parse($date);

        return match (true) {
            $date === $today => 'Today',
            $date === now()->subDay()->toDateString() => 'Yesterday',
            default => $day->format('l'),
        }.' · '.$day->format('d M Y');
    };
    $canEditAny = auth()->user()->can('goods-issue-edit');
    $canDeleteAny = auth()->user()->can('goods-issue-delete');
    $canSettlements = auth()->user()->can('sales-settlement-list');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Goods Issues" :createRoute="route('goods-issues.create')" createLabel="New goods issue"
            createPermission="goods-issue-create" :showSearch="false" :showRefresh="true" backRoute="dashboard">
            <x-slot name="actions">
                <x-register-header-actions page="goods-issues" :help="false" :excel-url="request()->fullUrlWithQuery(['export' => 'xlsx', 'page' => null])" />
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <x-customer-apple-style />
    <x-quick-look-style />
    <style>
        .gx { --gx-text: #1d1d1f; --gx-muted: #6e6e73; --gx-line: rgba(60,60,67,.12); --gx-blue: #0071e3; --gx-link: #0066cc; max-width: 1180px; margin: 0 auto; padding: 0 16px 40px; color: var(--gx-text); font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", system-ui, sans-serif; }
        .gx *, .gx *::before, .gx *::after { box-sizing: border-box; }
        .gx-hero { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 8px 24px; margin: 18px 0 14px; }
        .gx-hero p { margin: 4px 0 0; font-size: 15px; color: var(--gx-muted); }
        .gx-hero .gx-period { margin: 0; font-size: 20px; font-weight: 600; letter-spacing: -.015em; color: var(--gx-text); }
        .gx-hero .gx-period b { font-weight: 600; }
        .gx-hero p b { color: var(--gx-text); font-weight: 600; font-variant-numeric: tabular-nums; }
        .gx-hero-value { text-align: right; }
        .gx-hero-value strong { display: block; font-size: 28px; font-weight: 600; letter-spacing: -.02em; font-variant-numeric: tabular-nums; line-height: 1.1; }
        .gx-hero-value strong small { font-size: 15px; font-weight: 500; color: var(--gx-muted); margin-right: 3px; }
        .gx-hero-value span { font-size: 13px; color: var(--gx-muted); }
        .gx-warn { display: inline-flex; align-items: center; gap: 6px; margin-top: 6px; padding: 4px 12px; border-radius: 980px; background: rgba(255,159,10,.16); color: #8a4b00; font-size: 13px; font-weight: 600; text-decoration: none; }
        .gx-warn:hover { background: rgba(255,159,10,.26); }

        /* Search + filters: one sheet, search always in view */
        .gx-bar { padding: 14px; border-radius: 20px; background: #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08); }
        .gx-search-row { display: flex; gap: 8px; align-items: center; }
        .gx-search { position: relative; flex: 1 1 auto; min-width: 0; }
        .gx-search svg { position: absolute; left: 12px; top: 50%; width: 17px; height: 17px; transform: translateY(-50%); color: #8e8e93; pointer-events: none; }
        .gx-search input { width: 100%; height: 42px; padding: 0 12px 0 38px; border: 0 !important; border-radius: 12px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; font-size: 16px; color: var(--gx-text); }
        .gx-search input:focus { outline: none; background: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px var(--gx-blue) !important; }
        .gx-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 42px; padding: 0 16px; border: 0; border-radius: 980px; background: rgba(120,120,128,.14); color: var(--gx-text); font-size: 15px; font-weight: 500; white-space: nowrap; text-decoration: none; cursor: pointer; transition: transform .1s ease-out, background-color .15s ease; }
        .gx-btn:hover { background: rgba(120,120,128,.22); }
        .gx-btn:active { transform: scale(.97); }
        .gx-btn:focus-visible { outline: 2px solid var(--gx-blue); outline-offset: 2px; }
        .gx-btn-primary { background: var(--gx-blue); color: #fff; }
        .gx-btn-primary:hover { background: #0077ed; }
        .gx-btn svg { width: 17px; height: 17px; }
        .gx-btn .gx-badge { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 980px; background: var(--gx-blue); color: #fff; font-size: 12px; line-height: 20px; }
        .gx-more { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--gx-line); }
        @media (min-width: 640px) { .gx-more { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .gx-more { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .gx-field { min-width: 0; }
        .gx-field label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 500; color: var(--gx-muted); }
        .gx-field input, .gx-field select { width: 100%; height: 40px; padding: 0 12px; border: 0 !important; border-radius: 11px !important; background: rgba(118,118,128,.12) !important; box-shadow: none !important; font-size: 15px; color: var(--gx-text); }
        .gx-field input:focus, .gx-field select:focus { outline: none; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px var(--gx-blue) !important; background: #fff !important; }
        .gx-field .select2-container { width: 100% !important; display: block; }
        .gx-field .select2-container .select2-selection--single { height: 40px; display: flex; align-items: center; padding: 0 30px 0 12px; border: 0; border-radius: 11px; background: rgba(118,118,128,.12); font-size: 15px; }
        .gx-field .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); }
        .gx-more-foot { grid-column: 1 / -1; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
        .gx-dates { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
        .gx-dates a { padding: 6px 13px; border-radius: 980px; background: rgba(118,118,128,.12); color: var(--gx-text); font-size: 13px; font-weight: 500; text-decoration: none; transition: background-color .15s ease, transform .1s ease-out; }
        .gx-dates a:hover { background: rgba(118,118,128,.22); }
        .gx-dates a:active { transform: scale(.96); }
        .gx-dates a[aria-current] { background: var(--gx-blue); color: #fff; }
        .gx-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; font-size: 13px; color: var(--gx-muted); }
        .gx-chip { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; padding: 5px 10px 5px 12px; border-radius: 980px; background: rgba(0,113,227,.1); color: #0058b0; font-weight: 500; text-decoration: none; }
        .gx-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gx-chip:hover { background: rgba(0,113,227,.18); }
        .gx-chips a.gx-clear { color: #d70015; text-decoration: none; font-weight: 500; }

        /* Segmented stage tabs: wrap on a phone instead of scrolling sideways */
        .gx-seg { display: flex; flex-wrap: wrap; gap: 4px; margin: 16px 0 4px; padding: 3px; border-radius: 12px; background: rgba(118,118,128,.12); }
        .gx-seg a { flex: 1 1 auto; display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 34px; padding: 4px 12px; border-radius: 9px; color: var(--gx-text); font-size: 14px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: background-color .15s ease; }
        .gx-seg a:hover { background: rgba(255,255,255,.6); }
        .gx-seg a[aria-current] { background: #fff; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,.1), 0 0 0 .5px rgba(0,0,0,.04); }
        .gx-seg a b { font-weight: 500; font-size: 12.5px; color: var(--gx-muted); font-variant-numeric: tabular-nums; }
        .gx-seg a.gx-seg-warn b { color: #b45309; }
        .gx-tools { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin: 12px 2px 0; font-size: 13px; color: var(--gx-muted); }
        .gx-tools select { height: 32px; padding: 0 28px 0 10px; border: 0; border-radius: 9px; background-color: rgba(118,118,128,.12); font-size: 13px; color: var(--gx-text); }

        /* Grouped list: one inset group per day */
        .gx-day { margin-top: 18px; }
        .gx-day h2 { display: flex; justify-content: space-between; gap: 12px; margin: 0 6px 6px; font-size: 13px; font-weight: 600; color: var(--gx-muted); text-transform: none; }
        .gx-day h2 span:last-child { font-weight: 500; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .gx-list { margin: 0; padding: 0; list-style: none; border-radius: 16px; background: #fff; overflow: hidden; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 1px 3px rgba(0,0,0,.04); }
        .gx-row { position: relative; display: flex; align-items: stretch; }
        .gx-row + .gx-row::before { content: ''; position: absolute; top: 0; left: 16px; right: 0; border-top: 1px solid var(--gx-line); }
        .gx-row.is-draft { background: #fffbeb; }
        .gx-main { flex: 1 1 auto; min-width: 0; display: grid; grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "who val" "who stage" "what what"; gap: 3px 12px; align-items: center; padding: 12px 14px 12px 16px; color: inherit; text-decoration: none; transition: background-color .12s ease; }
        .gx-main:hover { background: rgba(0,0,0,.025); }
        .gx-main:active { background: rgba(0,0,0,.05); }
        .gx-main:focus-visible { outline: 2px solid var(--gx-blue); outline-offset: -2px; border-radius: 14px; }
        .gx-who { grid-area: who; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
        .gx-who b { font-size: 16px; font-weight: 600; letter-spacing: -.01em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gx-who span { font-size: 13px; color: var(--gx-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gx-what { grid-area: what; min-width: 0; font-size: 13px; color: var(--gx-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gx-what .gx-num { color: var(--gx-text); font-weight: 500; }
        .gx-val { grid-area: val; text-align: right; font-size: 16px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .gx-val small { font-size: 12px; font-weight: 500; color: var(--gx-muted); margin-right: 2px; }
        .gx-stage { grid-area: stage; justify-self: end; }
        .gx-cap { display: inline-flex; align-items: center; gap: 6px; padding: 2px 9px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .gx-cap::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .gx-cap-green { background: rgba(52,199,89,.14); color: #1f7a35; } .gx-cap-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .gx-cap-red { background: rgba(255,59,48,.12); color: #c4161c; } .gx-cap-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
        .gx-chev { flex: none; display: flex; align-items: center; padding-right: 14px; color: #c7c7cc; pointer-events: none; }
        .gx-chev svg { width: 8px; height: 13px; }
        .gx-edit { flex: none; align-self: center; margin-right: 6px; padding: 6px 12px; border-radius: 980px; background: rgba(0,113,227,.1); color: #0058b0; font-size: 13px; font-weight: 600; text-decoration: none; }
        .gx-edit:hover { background: rgba(0,113,227,.18); }
        /* Wider screens: one line per issue, columns lined up */
        @media (min-width: 900px) {
            .gx-main { grid-template-columns: minmax(180px, 1.1fr) minmax(0, 2fr) 120px 150px; grid-template-areas: "who what val stage"; padding-top: 13px; padding-bottom: 13px; }
            .gx-stage { justify-self: start; }
        }
        .gx-foot { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px 16px; margin: 18px 6px 0; font-size: 13px; color: var(--gx-muted); }
        .gx-foot b { color: var(--gx-text); font-variant-numeric: tabular-nums; }
        .gx-pager { margin-top: 14px; }
        .gx-empty { margin-top: 18px; padding: 44px 20px; border-radius: 16px; background: #fff; text-align: center; box-shadow: 0 0 0 1px rgba(0,0,0,.035); }
        .gx-empty h2 { margin: 0; font-size: 19px; font-weight: 600; letter-spacing: -.01em; }
        .gx-empty p { margin: 6px 0 16px; font-size: 14px; color: var(--gx-muted); }
        .gx-empty div { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; }
        .gq-foot-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .gq-act { display: inline-flex; align-items: center; height: 36px; padding: 0 14px; border: 0; border-radius: 980px; background: rgba(120,120,128,.14); color: #1d1d1f; font: inherit; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; }
        .gq-act-danger { background: rgba(255,59,48,.1); color: #d70015; }
        @media (max-width: 480px) {
            .gx { padding: 0 12px 32px; }
            .gx-hero .gx-period { font-size: 17px; }
            .gx-hero-value { text-align: left; }
            .gx-btn-label { display: none; }
            .gx-seg a { padding: 4px 8px; font-size: 13px; }
        }
        @media (prefers-reduced-motion: reduce) { .gx-btn, .gx-dates a, .gx-main { transition: none; } .gx-btn:active, .gx-dates a:active { transform: none; } }
        @media print { .gx-bar, .gx-seg, .gx-tools, .gx-pager, .gx-chev, .gx-edit { display: none !important; } .gx-list { box-shadow: none; border: 1px solid #ddd; } }
    </style>

    <div class="gx" data-layout="goods-issues-apple" x-data="{ more: {{ $moreCount ? 'true' : 'false' }}, confirm: { open: false, url: '', number: '' } }" @gl-toggle-filters.window="more = ! more">
        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="gx-hero">
            <div>
                <p class="gx-period">{{ $periodLabel }} · <b>{{ number_format($goodsIssues->total()) }}</b> {{ \Illuminate\Support\Str::plural('issue', $goodsIssues->total()) }}</p>
                @if ($stats['pending'] > 0 && ! $onPending)
                    <a href="{{ $pendingUrl }}" class="gx-warn">{{ number_format($stats['pending']) }} on vans, not settled yet <span aria-hidden="true">→</span></a>
                @endif
            </div>
            <div class="gx-hero-value">
                <strong title="Rs {{ number_format((float) $totalValue, 2) }}"><small>Rs</small>{{ number_format((float) $totalValue) }}</strong>
                <span>Total value of this list</span>
            </div>
        </div>

        <form method="GET" action="{{ route('goods-issues.index') }}" class="gx-bar" role="search" aria-label="Search and filter goods issues">
            <input type="hidden" name="filter[status]" value="{{ $tab !== '' ? $tab : 'all' }}">
            <input type="hidden" name="filter[issue_date_from]" value="{{ $dateFrom }}" x-bind:disabled="more">
            <input type="hidden" name="filter[issue_date_to]" value="{{ $dateTo }}" x-bind:disabled="more">
            @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
            <input type="hidden" name="per_page" value="{{ $perPage }}">

            <div class="gx-search-row">
                <div class="gx-search">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <label for="gx_search" class="sr-only">Search</label>
                    <input id="gx_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off" enterkeyhint="search" placeholder="GI number, vehicle or salesman">
                </div>
                <button type="button" class="gx-btn" @click="more = ! more" :aria-expanded="more" aria-controls="gx-more">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M7 12h10M10 17h4" /></svg>
                    <span class="gx-btn-label">Filters</span>
                    @if ($moreCount)<span class="gx-badge">{{ $moreCount }}</span>@endif
                </button>
                <button type="submit" class="gx-btn gx-btn-primary">Search</button>
            </div>

            <div id="gx-more" class="gx-more" x-show="more" x-cloak @if (! $moreCount) style="display:none" @endif>
                <div class="gx-field">
                    <label for="gx_from">From</label>
                    <input id="gx_from" type="date" name="filter[issue_date_from]" value="{{ $dateFrom }}" x-bind:disabled="! more">
                </div>
                <div class="gx-field">
                    <label for="gx_to">To</label>
                    <input id="gx_to" type="date" name="filter[issue_date_to]" value="{{ $dateTo }}" x-bind:disabled="! more">
                </div>
                <div class="gx-field">
                    <label for="gx_supplier">Supplier</label>
                    <select id="gx_supplier" class="gx-select" data-placeholder="All suppliers" name="filter[supplier_id]">
                        @if ($suppliers->count() !== 1)<option value=""></option>@endif
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gx-field">
                    <label for="gx_employee">Salesman</label>
                    <select id="gx_employee" class="gx-select" data-placeholder="All salesmen" name="filter[employee_id]">
                        <option value=""></option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->name }}{{ $employee->employee_code ? ' ('.$employee->employee_code.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gx-field">
                    <label for="gx_vehicle">Vehicle</label>
                    <select id="gx_vehicle" class="gx-select" data-placeholder="All vehicles" name="filter[vehicle_id]">
                        <option value=""></option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((string) ($filters['vehicle_id'] ?? '') === (string) $vehicle->id)>{{ $vehicle->vehicle_number }} ({{ $vehicle->vehicle_type }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="gx-field">
                    <label for="gx_warehouse">Warehouse</label>
                    <select id="gx_warehouse" class="gx-select" data-placeholder="All warehouses" name="filter[warehouse_id]">
                        <option value=""></option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gx-field">
                    <label for="gx_settlement">Settlement</label>
                    <select id="gx_settlement" name="filter[settlement]">
                        <option value="">Settled and not settled</option>
                        <option value="pending" @selected(($filters['settlement'] ?? '') === 'pending')>Not settled yet</option>
                        <option value="settled" @selected(($filters['settlement'] ?? '') === 'settled')>Settled</option>
                    </select>
                </div>
                <div class="gx-more-foot">
                    <a href="{{ route('goods-issues.index') }}" class="gx-btn">Reset</a>
                    <button type="submit" class="gx-btn gx-btn-primary">Apply filters</button>
                </div>
            </div>

            <nav class="gx-dates" aria-label="Quick dates">
                @foreach ($ranges as $label => [$from, $to])
                    <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            @if ($chips)
                <div class="gx-chips" aria-label="Active filters">
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="gx-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <span>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}: {{ $chipValue($key, $value) }}</span><span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['status', 'issue_date_from', 'issue_date_to', 'search'])) }}" class="gx-clear">Clear filters</a>
                </div>
            @endif
        </form>

        <nav class="gx-seg" aria-label="Goods issue stage">
            @foreach ($tabs as [$label, $count, $on, $url])
                <a href="{{ $url }}" @if ($on) aria-current="page" @endif class="{{ $label === 'Not settled' && $count > 0 ? 'gx-seg-warn' : '' }}">{{ $label }} <b>{{ number_format($count) }}</b></a>
            @endforeach
        </nav>

        <div class="gx-tools">
            <span>Tap an issue for a quick look.</span>
            <span style="display:flex; gap:8px; align-items:center">
                <label for="gx_sort" class="sr-only">Sort</label>
                <select id="gx_sort" onchange="window.location = this.value">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ request()->fullUrlWithQuery(['sort' => $value, 'page' => null]) }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="gx_rows" class="sr-only">Rows per page</label>
                <select id="gx_rows" onchange="window.location = this.value">
                    @foreach (\App\Http\Controllers\GoodsIssueController::PER_PAGE as $n)
                        <option value="{{ request()->fullUrlWithQuery(['per_page' => $n, 'page' => null]) }}" @selected($perPage === (string) $n)>{{ $n }} per page</option>
                    @endforeach
                </select>
            </span>
        </div>

        @if ($goodsIssues->count() > 0)
            @foreach ($groups as $date => $issues)
                <section class="gx-day" aria-label="{{ $sortField === 'issue_date' ? $dayLabel((string) $date) : 'Goods issues' }}">
                    @if ($sortField === 'issue_date')
                        <h2><span>{{ $dayLabel((string) $date) }}</span><span>{{ $issues->count() }} {{ \Illuminate\Support\Str::plural('issue', $issues->count()) }} · Rs {{ number_format((float) $issues->sum('total_value')) }}</span></h2>
                    @endif
                    <ul class="gx-list">
                        @foreach ($issues as $gi)
                            @php
                                [$stageText, $stageTone, $settlement] = $stageOf($gi);
                                $canEdit = $gi->status === 'draft' && $canEditAny;
                                $canDelete = $gi->status === 'draft' && $canDeleteAny;
                                $hasLines = $gi->relationLoaded('items');
                                $salesman = $displayName($gi->employee->name ?? '—');
                                $peek = $hasLines ? [
                                    'number' => $gi->issue_number,
                                    'url' => route('goods-issues.show', $gi),
                                    'edit' => $canEdit ? route('goods-issues.edit', $gi) : null,
                                    'delete' => $canDelete ? route('goods-issues.destroy', $gi) : null,
                                    'stage' => $stageText,
                                    'tone' => $stageTone,
                                    'settlement' => $settlement?->settlement_number,
                                    'settlementUrl' => $settlement && $canSettlements ? route('sales-settlements.show', $settlement) : null,
                                    'supplier' => $gi->supplier->supplier_name ?? '',
                                    'value' => number_format((float) $gi->total_value),
                                    'salesman' => $salesman,
                                    'vehicle' => $gi->vehicle->vehicle_number ?? '—',
                                    'warehouse' => $gi->warehouse->warehouse_name ?? '',
                                    'date' => $gi->issue_date?->format('D, d M Y').($gi->posted_at ? ' · posted '.$gi->posted_at->format('h:i A') : ' · not posted'),
                                    'qty' => $qty($gi->items->sum('quantity_issued')),
                                    'total' => number_format((float) $gi->items->sum('total_value'), 2),
                                    'lines' => $gi->items->map(fn ($line) => [
                                        'name' => $line->product->product_name ?? '—',
                                        'qty' => $qty($line->quantity_issued),
                                        'rate' => number_format((float) $line->selling_price, 2),
                                        'value' => number_format((float) $line->total_value, 2),
                                        'supplementary' => (bool) $line->is_supplementary,
                                    ])->values(),
                                ] : null;
                                $topProduct = $hasLines ? $gi->items->sortByDesc('total_value')->first() : null;
                                $lineCount = $hasLines ? $gi->items->count() : (int) $gi->items_count;
                            @endphp
                            <li class="gx-row {{ $gi->status === 'draft' ? 'is-draft' : '' }}">
                                <a href="{{ route('goods-issues.show', $gi) }}" class="gx-main"
                                    @if ($peek) x-data @click="if (! ($event.metaKey || $event.ctrlKey || $event.shiftKey)) { $event.preventDefault(); $dispatch('gi-peek', @js($peek)) }" aria-haspopup="dialog" @endif
                                    aria-label="{{ $gi->issue_number }}, {{ $salesman }}, Rs {{ number_format((float) $gi->total_value) }}, {{ $stageText }}">
                                    <span class="gx-who"><b>{{ $salesman }}</b><span>{{ $gi->vehicle->vehicle_number ?? '—' }}{{ $gi->warehouse ? ' · '.$shortWarehouse($gi->warehouse->warehouse_name) : '' }}</span></span>
                                    <span class="gx-what"><span class="gx-num">{{ $gi->issue_number }}</span>{{ $gi->supplier ? ' · '.$gi->supplier->supplier_name : '' }} · @if ($topProduct){{ $topProduct->product->product_name ?? '—' }}{{ $lineCount > 1 ? ' +'.($lineCount - 1).' more' : '' }}@else{{ $lineCount }} {{ \Illuminate\Support\Str::plural('product', $lineCount) }}@endif</span>
                                    <span class="gx-val"><small>Rs</small>{{ number_format((float) $gi->total_value) }}</span>
                                    <span class="gx-stage"><span class="gx-cap gx-cap-{{ $stageTone }}">{{ $stageText }}</span></span>
                                </a>
                                @if ($canEdit)
                                    <a href="{{ route('goods-issues.edit', $gi) }}" class="gx-edit" aria-label="Edit draft {{ $gi->issue_number }}">Edit</a>
                                @endif
                                <span class="gx-chev" aria-hidden="true"><svg viewBox="0 0 8 13" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m1.5 1.5 5 5-5 5" /></svg></span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <div class="gx-foot">
                <span>Showing <b>{{ number_format($goodsIssues->firstItem()) }}–{{ number_format($goodsIssues->lastItem()) }}</b> of <b>{{ number_format($goodsIssues->total()) }}</b></span>
                <span>Total value <b>Rs {{ number_format((float) $totalValue) }}</b>{{ $goodsIssues->hasPages() ? ' (all pages)' : '' }}</span>
            </div>
            <div class="gx-pager">{{ $goodsIssues->onEachSide(1)->links() }}</div>
        @else
            <div class="gx-empty">
                <h2>No goods issues for {{ $periodLabel }}</h2>
                <p>{{ $chips || $tab || isset($filters['search']) ? 'Try removing a filter or the search.' : 'Pick a wider date range, for example This month or All.' }}</p>
                <div>
                    <a href="{{ $rangeUrl(now()->startOfMonth()->toDateString(), $today) }}" class="gx-btn">Show this month</a>
                    @can('goods-issue-create')<a href="{{ route('goods-issues.create') }}" class="gx-btn gx-btn-primary">New goods issue</a>@endcan
                </div>
            </div>
        @endif

        {{-- Delete draft confirmation (opened from the Quick look) --}}
        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @gi-delete.window="confirm = { open: true, url: $event.detail.url, number: $event.detail.number }" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="gx-confirm-title">
            <div class="uf-modal-bg" @click="confirm.open = false"></div>
            <div class="uf-modal-box">
                <div class="uf-modal-body">
                    <div>
                        <h3 id="gx-confirm-title">Delete draft <span x-text="confirm.number"></span>?</h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#424245">The draft and its lines are removed and the vehicle is free for a new issue. This cannot be undone.</p>
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

    {{-- Quick look: the lines of one issue, with Open, Edit and Delete for drafts --}}
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
                            <span class="gq-chip gq-chip-dot" :class="'gq-' + gi.tone" x-text="gi.stage"></span>
                            <template x-if="gi.settlement">
                                <a class="gq-chip gq-blue" :href="gi.settlementUrl || '#'" x-text="gi.settlement" :style="gi.settlementUrl ? '' : 'pointer-events:none'"></a>
                            </template>
                            <span class="gq-chip gq-grey" x-show="gi.supplier" x-text="gi.supplier"></span>
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
                        <div class="gq-foot-actions">
                            <template x-if="gi.delete"><button type="button" class="gq-act gq-act-danger" @click="hide(); $dispatch('gi-delete', { url: gi.delete, number: gi.number })">Delete draft</button></template>
                            <template x-if="gi.edit"><a :href="gi.edit" class="gq-act">Edit draft</a></template>
                            <a :href="gi.url" class="gq-open">Open full issue <span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @push('scripts')
        <script>
            // The header's filter button opens the filters under the search field.
            document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('gl-toggle-filters')); });
            $(function () {
                $('.gx-select').each(function () {
                    const $select = $(this);
                    $select.select2({ width: '100%', placeholder: $select.data('placeholder') || 'Select', allowClear: $select.find('option[value=""]').length > 0 });
                });
            });
        </script>
        @if (request()->boolean('print'))
            <script>window.addEventListener('load', () => window.print());</script>
        @endif
    @endpush
</x-app-layout>
