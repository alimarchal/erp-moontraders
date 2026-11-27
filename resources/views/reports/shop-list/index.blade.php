{{--
    Shop Directory (/reports/shop-list), in the Goods Issues layout: header buttons (filters, PDF, print), a filter sheet,
    Active / Inactive tabs, removable chips, a column picker (remembered in this browser and carried into the PDF), a
    table that becomes cards on a tablet or phone, and a quick look at any shop in a pop-up like the Bank Register's.
--}}
@php
    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tab = $filters['is_active'] ?? '';
    $tabUrl = fn (string $key) => $withFilters(array_filter(array_merge(\Illuminate\Support\Arr::except($filters, 'is_active'), ['is_active' => $key]), fn ($v) => $v !== ''));
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === $field ? '-'.$field : $field, 'page' => null]);
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $chips = \Illuminate\Support\Arr::except($filters, array_merge(['is_active'], $canViewAllSuppliers ? [] : ['supplier_id']));
    $names = [
        'supplier_id' => $suppliers->pluck('supplier_name', 'id'), 'employee_id' => $employees->pluck('name', 'id'), 'sales_rep_id' => $salesRepOptions->pluck('name', 'id'),
    ];
    $chipLabels = [
        'search' => 'Search', 'customer_name' => 'Name', 'customer_code' => 'Code', 'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address', 'city' => 'City',
        'sub_locality' => 'Area', 'ntn' => 'NTN', 'owner_cnic' => 'CNIC', 'channel_type' => 'Channel', 'customer_category' => 'Category', 'sales_rep_id' => 'Sales rep',
        'it_status' => 'Filer', 'supplier_id' => 'Supplier', 'employee_id' => 'Salesman', 'balance' => 'Balance', 'no_sale' => 'No sale', 'missing' => 'Missing',
    ];
    $balanceOptions = ['owes' => 'Owes money', 'advance' => 'Paid in advance', 'clear' => 'Settled', 'none' => 'No credit account'];
    $noSaleOptions = ['30' => 'in 30 days', '60' => 'in 60 days', '90' => 'in 90 days', 'never' => 'ever (on credit)'];
    $missingOptions = ['phone' => 'Phone', 'owner_cnic' => 'Owner CNIC', 'ntn' => 'NTN', 'address' => 'Address'];
    $chipValue = fn (string $key, $value) => match (true) {
        isset($names[$key]) => $names[$key][$value] ?? $value,
        $key === 'customer_category' => 'Category '.$value,
        $key === 'it_status' => $value === '1' ? 'Filers' : 'Non-filers',
        $key === 'balance' => $balanceOptions[$value] ?? $value,
        $key === 'no_sale' => $noSaleOptions[$value] ?? $value,
        $key === 'missing' => $missingOptions[$value] ?? $value,
        default => $value,
    };
    $advancedCount = collect(['city', 'sales_rep_id', 'supplier_id', 'employee_id', 'balance', 'no_sale', 'it_status', 'missing'])->filter(fn ($k) => isset($filters[$k]) && ($k !== 'supplier_id' || $canViewAllSuppliers))->count();
    $allColumns = \App\Http\Controllers\Reports\ShopListController::COLUMNS;
    $defaultColumns = array_keys(array_filter($allColumns, fn ($c) => $c[1]));
    $canCustomer = auth()->user()->can('customer-list');
    $canStatement = auth()->user()->can('report-audit-customer-account-statement');
    $headerButton = 'inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 no-print';
    $pdfBase = request()->fullUrlWithQuery(['format' => 'pdf', 'page' => null, 'columns' => null, 'orientation' => null]);
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d M Y') : '—';
    $owed = (float) $totals->owed;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Shop Directory" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true" backRoute="reports.index">
            <x-slot name="actions">
                <div class="relative no-print" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button type="button" class="{{ $headerButton }}" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" title="Download PDF">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        <span class="sr-only">Download PDF</span>
                    </button>
                    <div x-show="open" x-cloak role="menu" class="rg-menu">
                        <p class="rg-menu-label">Download as PDF (A4) · the columns you see</p>
                        <a role="menuitem" data-sl-pdf="portrait" href="{{ $pdfBase }}&orientation=portrait" @click="open = false">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="6" y="3" width="12" height="18" rx="2" /></svg>
                            Portrait <span>up to 6 columns</span>
                        </a>
                        <a role="menuitem" data-sl-pdf="landscape" href="{{ $pdfBase }}&orientation=landscape" @click="open = false">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" /></svg>
                            Landscape <span>recommended</span>
                        </a>
                    </div>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <x-customer-apple-style />
    <style>
        /* Columns hidden with the picker */
        @foreach (array_keys($allColumns) as $key)
            .sl-table.sl-hide-{{ $key }} [data-col="{{ $key }}"] { display: none !important; }
        @endforeach
        .sl-cols { position: relative; }
        .sl-cols-panel { position: absolute; right: 0; top: calc(100% + 6px); z-index: 60; width: min(92vw, 320px); padding: 12px; border-radius: 14px; background: #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.06), 0 12px 32px rgba(0,0,0,.14); }
        .sl-cols-panel p { margin: 0 0 8px; font-size: 12px; color: #6e6e73; }
        .sl-cols-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 8px; }
        .sl-cols-grid label { display: flex; align-items: center; gap: 8px; min-height: 32px; padding: 0 6px; border-radius: 8px; font-size: 14px; color: #1d1d1f; cursor: pointer; }
        .sl-cols-grid label:hover { background: rgba(118,118,128,.1); }
        .sl-cols-grid input { width: 16px; height: 16px; accent-color: #0071e3; }
        .sl-cols-foot { display: flex; justify-content: space-between; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(60,60,67,.1); }
        .sl-toolbar-right { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .sl-summary b { font-variant-numeric: tabular-nums; }
        .sl-open { border: 0; background: none; padding: 2px 0; font: inherit; text-align: left; cursor: pointer; }
        /* Quick look pop-up: a centred sheet on a desktop, the full screen on a phone (as in the Bank Register) */
        .sl-modal { position: fixed; inset: 0; z-index: 80; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .sl-modal-bg { position: absolute; inset: 0; background: rgba(0,0,0,.28); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); }
        .sl-modal-box { position: relative; display: flex; flex-direction: column; width: min(96vw, 46rem); max-height: min(92vh, 52rem); max-height: min(92dvh, 52rem); overflow: hidden; border-radius: 22px; background: #fff; box-shadow: 0 0 0 .5px rgba(0,0,0,.1), 0 30px 80px rgba(0,0,0,.25); animation: sl-pop .25s cubic-bezier(.23, 1, .32, 1); }
        .sl-modal-head { display: flex; flex: none; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px 14px 22px; border-bottom: 1px solid rgba(60,60,67,.12); }
        .sl-modal-head h3 { min-width: 0; margin: 0; overflow: hidden; font-size: 17px; font-weight: 600; letter-spacing: -.02em; white-space: nowrap; text-overflow: ellipsis; }
        .sl-modal-head button { flex: none; height: 32px; padding: 0 14px; border: 0; border-radius: 980px; background: rgba(118,118,128,.14); font: inherit; font-size: 14px; color: #1d1d1f; cursor: pointer; }
        .sl-modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 18px 22px 24px; }
        .sl-peek { display: grid; gap: 16px; }
        .sl-peek-sub { margin: -6px 0 0; font-size: 14px; color: #424245; }
        .sl-peek-links { display: flex; flex-wrap: wrap; gap: 8px; }
        .sl-peek-h { margin: 0 0 6px; font-size: 15px; font-weight: 600; }
        .sl-peek-acc { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(60,60,67,.1); font-size: 14px; }
        .sl-peek-acc > div { min-width: 0; }
        .sl-peek-acc b { display: block; }
        .sl-peek-acc .ak-muted { font-size: 12.5px; }
        .sl-skeleton { display: grid; gap: 12px; } .sl-skeleton i { display: block; height: 18px; border-radius: 6px; background: rgba(118,118,128,.12); animation: sl-pulse 1s ease-in-out infinite alternate; }
        .sl-skeleton i:first-child { height: 84px; border-radius: 12px; }
        @keyframes sl-pop { from { opacity: 0; transform: translateY(10px) scale(.97); } to { opacity: 1; transform: none; } }
        @keyframes sl-pulse { from { opacity: 1; } to { opacity: .45; } }
        @media (max-width: 640px) {
            .sl-modal { padding: 0; align-items: stretch; }
            .sl-modal-box { width: 100%; max-height: none; height: 100vh; height: 100dvh; border-radius: 0; }
            .sl-modal-head { padding: max(10px, env(safe-area-inset-top)) 12px 10px 16px; }
            .sl-modal-body { padding: 14px 14px max(16px, env(safe-area-inset-bottom)); }
        }
        @media (prefers-reduced-motion: reduce) { .sl-modal-box, .sl-skeleton i { animation: none; } }
        @media print { .sl-cols, .sl-modal { display: none !important; } }
    </style>

    <div class="ak-page" x-data="shopDirectory()">
        <div class="cs-print-head">{{ config('app.name') }} · Shop Directory<small>{{ $filterSummary }} · {{ number_format($totals->shops) }} shops · printed {{ now()->format('d.m.Y H:i') }} by {{ auth()->user()->name }}</small></div>

        <section class="ak-card cs-filters" aria-label="Filters" x-data="{ open: false }" @cs-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="display:none">
            <form method="GET" action="{{ route('reports.shop-list.index') }}" class="ak-filters apl-fields"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[is_active]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                <div class="cs-filter-row" style="--cs-cols: 4">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off" placeholder="Name, code, phone or area" title="Searches name, code, business name, phone, address, area, city, NTN and CNIC">
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_channel">Channel</label>
                        <select id="f_channel" name="filter[channel_type]">
                            <option value="">All channels</option>
                            @foreach ($channelTypes as $type)<option value="{{ $type }}" @selected(($filters['channel_type'] ?? '') === $type)>{{ $type }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_category">Category</label>
                        <select id="f_category" name="filter[customer_category]">
                            <option value="">All categories</option>
                            @foreach ($customerCategories as $category)<option value="{{ $category }}" @selected(($filters['customer_category'] ?? '') === $category)>Category {{ $category }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_area">Area</label>
                        <input id="f_area" type="text" name="filter[sub_locality]" value="{{ $filters['sub_locality'] ?? '' }}" placeholder="Any area">
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\Reports\ShopListController::PER_PAGE_OPTIONS as $size)
                                <option value="{{ $size }}" @selected((string) $perPage === (string) $size)>{{ $size === 'all' ? 'All' : $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="cs-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy"><span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span></button>
                    </div>
                </div>
                <div id="cs-advanced" class="cs-advanced" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_balance">Balance</label>
                        <select id="f_balance" name="filter[balance]">
                            <option value="">Any balance</option>
                            @foreach ($balanceOptions as $value => $label)<option value="{{ $value }}" @selected(($filters['balance'] ?? '') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_no_sale">No credit sale</label>
                        <select id="f_no_sale" name="filter[no_sale]">
                            <option value="">Any time</option>
                            @foreach ($noSaleOptions as $value => $label)<option value="{{ $value }}" @selected(($filters['no_sale'] ?? '') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_supplier">Supplier</label>
                        <select id="f_supplier" name="filter[supplier_id]" class="cs-select" data-placeholder="All suppliers">
                            @if ($canViewAllSuppliers)<option value=""></option>@endif
                            @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) $supplierId === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_employee">Salesman</label>
                        <select id="f_employee" name="filter[employee_id]" class="cs-select" data-placeholder="All salesmen">
                            <option value=""></option>
                            @foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_rep">Sales rep</label>
                        <select id="f_rep" name="filter[sales_rep_id]" class="cs-select" data-placeholder="All reps">
                            <option value=""></option>
                            @foreach ($salesRepOptions as $rep)<option value="{{ $rep->id }}" @selected((string) ($filters['sales_rep_id'] ?? '') === (string) $rep->id)>{{ $rep->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_city">City</label>
                        <input id="f_city" type="text" name="filter[city]" value="{{ $filters['city'] ?? '' }}" placeholder="Any city">
                    </div>
                    <div class="ak-field">
                        <label for="f_filer">Filer (FBR)</label>
                        <select id="f_filer" name="filter[it_status]">
                            <option value="">Filers and non-filers</option>
                            <option value="1" @selected(($filters['it_status'] ?? '') === '1')>Filers</option>
                            <option value="0" @selected(($filters['it_status'] ?? '') === '0')>Non-filers</option>
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_missing">Missing details</label>
                        <select id="f_missing" name="filter[missing]">
                            <option value="">Show all</option>
                            @foreach ($missingOptions as $value => $label)<option value="{{ $value }}" @selected(($filters['missing'] ?? '') === $value)>No {{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <nav class="cs-ranges" aria-label="Quick filters">
                    <span>Quick</span>
                    <a href="{{ $withFilters(array_merge($filters, ['balance' => 'owes'])) }}" @if (($filters['balance'] ?? '') === 'owes') aria-current="true" @endif>Owes money</a>
                    <a href="{{ $withFilters(array_merge($filters, ['no_sale' => '30'])) }}" @if (($filters['no_sale'] ?? '') === '30') aria-current="true" @endif>No sale in 30 days</a>
                    <a href="{{ $withFilters(array_merge($filters, ['missing' => 'owner_cnic'])) }}" @if (($filters['missing'] ?? '') === 'owner_cnic') aria-current="true" @endif>No CNIC</a>
                    <a href="{{ $withFilters(array_merge($filters, ['it_status' => '0'])) }}" @if (($filters['it_status'] ?? '') === '0') aria-current="true" @endif>Non-filers</a>
                    <a href="{{ route('reports.shop-list.index') }}" class="cs-clear">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-card cs-card" aria-label="Shops">
            <nav class="ak-tabs" aria-label="Shop status">
                @foreach (['' => ['All', $stats['total']], '1' => ['Active', $stats['active']], '0' => ['Inactive', $stats['inactive']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl((string) $key) }}" @if ($tab === (string) $key) aria-current="page" @endif class="ak-tab {{ $tab === (string) $key ? 'is-active' : '' }}">{{ $label }} <span class="ak-count">{{ number_format($count) }}</span></a>
                @endforeach
            </nav>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}"><b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span></a>
                    @endforeach
                    <a href="{{ $withFilters($tab !== '' ? ['is_active' => $tab] : []) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($customers->count() > 0)
                <div class="ak-table-wrap">
                    <div class="ak-dt-toolbar">
                        <p class="sl-summary">
                            <b>{{ number_format($totals->shops) }}</b> {{ \Illuminate\Support\Str::plural('shop', $totals->shops) }}
                            @if ($owed > 0) &middot; owe <b class="cs-due">Rs {{ number_format($owed) }}</b>@endif
                            @if ((float) $totals->advance < 0) &middot; <b class="cs-advance">Rs {{ number_format(abs((float) $totals->advance)) }}</b> in advance @endif
                        </p>
                        <div class="sl-toolbar-right">
                            <div class="sl-cols no-print" @click.outside="colsOpen = false" @keydown.escape.window="colsOpen = false">
                                <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" @click="colsOpen = ! colsOpen" :aria-expanded="colsOpen" aria-controls="sl-cols-panel">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z" /></svg>
                                    Columns <span class="ak-count" x-text="cols.length"></span>
                                </button>
                                <div id="sl-cols-panel" class="sl-cols-panel" x-show="colsOpen" x-cloak x-transition.opacity role="group" aria-label="Columns to show">
                                    <p>Code and shop name are always shown. The rest show on screen, in print and in the PDF, and are remembered in this browser.</p>
                                    <div class="sl-cols-grid">
                                        @foreach ($allColumns as $key => [$label])
                                            <label><input type="checkbox" value="{{ $key }}" :checked="cols.includes(@js($key))" @change="toggleCol(@js($key))"> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    <div class="sl-cols-foot">
                                        <button type="button" class="cs-link" style="border:0;background:none;cursor:pointer" @click="resetCols()">Default columns</button>
                                        <button type="button" class="cs-link" style="border:0;background:none;cursor:pointer" @click="cols = @js(array_keys($allColumns)); saveCols()">Show all</button>
                                    </div>
                                </div>
                            </div>
                            <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                                <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                                <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                            </div>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Shops table">
                        <table class="ak-dt cs-table sl-table" :class="hiddenClasses()">
                            <caption class="sr-only">Shops, {{ $customers->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="cs-no">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('customer_code') }}"><a href="{{ $sortUrl('customer_code') }}">Code <span aria-hidden="true">{{ $sortMark('customer_code') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('customer_name') }}"><a href="{{ $sortUrl('customer_name') }}">Shop <span aria-hidden="true">{{ $sortMark('customer_name') }}</span></a></th>
                                    <th scope="col" data-col="business_name">Business name</th>
                                    <th scope="col" data-col="area" aria-sort="{{ $ariaSort('area') }}"><a href="{{ $sortUrl('area') }}">Area <span aria-hidden="true">{{ $sortMark('area') }}</span></a></th>
                                    <th scope="col" data-col="address">Address</th>
                                    <th scope="col" data-col="channel">Channel</th>
                                    <th scope="col" data-col="phone">Phone</th>
                                    <th scope="col" data-col="email">Email</th>
                                    <th scope="col" data-col="owner_cnic">Owner CNIC</th>
                                    <th scope="col" data-col="ntn">NTN</th>
                                    <th scope="col" data-col="it_status">Filer</th>
                                    <th scope="col" data-col="sales_rep">Sales rep</th>
                                    <th scope="col" data-col="payment_terms" class="cs-num">Terms</th>
                                    <th scope="col" data-col="credit_limit" class="cs-num">Credit limit</th>
                                    <th scope="col" data-col="accounts" class="cs-num" title="Salesmen with a credit account for this shop">Salesmen</th>
                                    <th scope="col" data-col="last_sale" aria-sort="{{ $ariaSort('last_sale') }}"><a href="{{ $sortUrl('last_sale') }}">Last sale <span aria-hidden="true">{{ $sortMark('last_sale') }}</span></a></th>
                                    <th scope="col" data-col="last_recovery">Last recovery</th>
                                    <th scope="col" data-col="balance" class="cs-num" aria-sort="{{ $ariaSort('balance') }}"><a href="{{ $sortUrl('balance') }}">Balance <span aria-hidden="true">{{ $sortMark('balance') }}</span></a></th>
                                    <th scope="col" data-col="status">Status</th>
                                    <th scope="col" class="ak-sticky-end no-print"><span class="sr-only">Quick look</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($customers as $customer)
                                    @php
                                        $balance = $customer->balance !== null ? (float) $customer->balance : null;
                                        $peekUrl = route('reports.shop-list.show', array_filter(['customer' => $customer->id, 'filter' => $supplierId ? ['supplier_id' => $supplierId] : null]));
                                    @endphp
                                    <tr>
                                        <td class="cs-no" data-label="#">{{ $customers->firstItem() + $loop->index }}</td>
                                        <td data-label="Code" style="white-space:nowrap"><span class="cs-code">{{ $customer->customer_code }}</span></td>
                                        <td data-label="Shop">
                                            <button type="button" class="sl-open cs-name" data-sl-peek="{{ $peekUrl }}" aria-haspopup="dialog" title="Quick look at {{ $customer->customer_name }}">{{ $customer->customer_name }}</button>
                                        </td>
                                        <td data-col="business_name" data-label="Business name">{{ $customer->business_name ?: '—' }}</td>
                                        <td class="cs-stack" data-col="area" data-label="Area">
                                            <div>{{ $customer->sub_locality ?: ($customer->city ?: '—') }}</div>
                                            @if ($customer->sub_locality && $customer->city)<div class="ak-muted cs-sub">{{ $customer->city }}</div>@endif
                                        </td>
                                        <td data-col="address" data-label="Address">{{ $customer->address ?: '—' }}</td>
                                        <td class="cs-stack" data-col="channel" data-label="Channel">
                                            <div>{{ $customer->channel_type }}</div>
                                            <div class="ak-muted cs-sub">Category {{ $customer->customer_category }}</div>
                                        </td>
                                        <td data-col="phone" data-label="Phone" style="white-space:nowrap">@if ($customer->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $customer->phone) }}" class="cs-link">{{ $customer->phone }}</a>@else<span class="ak-muted">—</span>@endif</td>
                                        <td data-col="email" data-label="Email">{{ $customer->email ?: '—' }}</td>
                                        <td data-col="owner_cnic" data-label="Owner CNIC" style="white-space:nowrap"><span class="cs-code">{{ $customer->owner_cnic ?: '—' }}</span></td>
                                        <td data-col="ntn" data-label="NTN"><span class="cs-code">{{ $customer->ntn ?: '—' }}</span></td>
                                        <td data-col="it_status" data-label="Filer"><span class="cs-cap {{ $customer->it_status ? 'cs-cap-blue' : 'cs-cap-grey' }}">{{ $customer->it_status ? 'Filer' : 'Non-filer' }}</span></td>
                                        <td data-col="sales_rep" data-label="Sales rep">{{ $customer->salesRep?->name ?? '—' }}</td>
                                        <td data-col="payment_terms" data-label="Terms" class="cs-num">{{ $customer->payment_terms !== null ? $customer->payment_terms.' days' : '—' }}</td>
                                        <td data-col="credit_limit" data-label="Credit limit" class="cs-num">{{ (float) $customer->credit_limit > 0 ? number_format((float) $customer->credit_limit) : '—' }}</td>
                                        <td data-col="accounts" data-label="Salesmen" class="cs-num">{{ $customer->accounts ? number_format($customer->accounts) : '—' }}</td>
                                        <td data-col="last_sale" data-label="Last sale" style="white-space:nowrap">{{ $date($customer->last_sale) }}</td>
                                        <td data-col="last_recovery" data-label="Last recovery" style="white-space:nowrap">{{ $date($customer->last_recovery) }}</td>
                                        <td data-col="balance" data-label="Balance" class="cs-num">
                                            @if ($balance === null || abs($balance) < 0.005)
                                                <span class="ak-muted">—</span>
                                            @elseif ($canStatement)
                                                <a href="{{ route('reports.customer-account-statement.index', ['filter' => array_filter(['customer_id' => $customer->id, 'supplier_id' => $supplierId])]) }}" class="cs-amount-link {{ $balance > 0 ? 'cs-due' : 'cs-advance' }}" title="Open the account statements">{{ number_format($balance) }}</a>
                                            @else
                                                <b class="{{ $balance > 0 ? 'cs-due' : 'cs-advance' }}">{{ number_format($balance) }}</b>
                                            @endif
                                        </td>
                                        <td data-col="status" data-label="Status"><span class="cs-cap {{ $customer->is_active ? 'cs-cap-green' : 'cs-cap-red' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></td>
                                        <td class="ak-sticky-end no-print" data-label="">
                                            <div class="cs-act">
                                                <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" data-sl-peek="{{ $peekUrl }}" aria-haspopup="dialog" aria-label="Quick look at {{ $customer->customer_name }}">
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    View
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($customers->hasPages())
                    <div class="ak-pager no-print">
                        <p>Showing <b>{{ number_format($customers->firstItem()) }}–{{ number_format($customers->lastItem()) }}</b> of <b>{{ number_format($customers->total()) }}</b> shops</p>
                        <div class="ak-pager-right"><div>{{ $customers->onEachSide(1)->links() }}</div></div>
                    </div>
                @endif
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No shops match these filters</h2>
                    <p>Remove a filter, or search with part of a name, phone or area.</p>
                    <a href="{{ route('reports.shop-list.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>
                </div>
            @endif
        </section>

        {{-- Quick look pop-up --}}
        <div class="sl-modal" x-show="peek.open" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="sl-peek-title" @keydown.escape.window="peek.open && closePeek()">
            <div class="sl-modal-bg" @click="closePeek()"></div>
            <div class="sl-modal-box" x-ref="peekBox" @keydown.tab="trap($event)">
                <div class="sl-modal-head">
                    <h3 id="sl-peek-title" x-text="peek.title"></h3>
                    <button type="button" x-ref="peekClose" @click="closePeek()">Close</button>
                </div>
                <div class="sl-modal-body" x-ref="peekBody" aria-live="polite"></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function shopDirectory() {
                const all = @js(array_keys($allColumns));
                const defaults = @js($defaultColumns);
                const fromUrl = @js(request()->filled('columns') ? $columns : null);
                const storageKey = 'moontraders.shop-directory.columns';
                let stored = null;
                try { stored = JSON.parse(window.localStorage.getItem(storageKey) || 'null'); } catch (error) { stored = null; }

                return {
                    cols: (fromUrl || (Array.isArray(stored) ? stored.filter((key) => all.includes(key)) : null) || defaults),
                    colsOpen: false,
                    compact: (() => { try { return window.localStorage.getItem('shop-directory-density') === 'compact'; } catch (error) { return false; } })(),
                    peek: { open: false, title: '', opener: null },

                    init() {
                        this.syncPdf();
                        this.$watch('cols', () => this.syncPdf());
                        this.$root.addEventListener('click', (event) => {
                            const trigger = event.target.closest('[data-sl-peek]');
                            if (trigger) { event.preventDefault(); this.openPeek(trigger); }
                        });
                    },
                    hiddenClasses() { return all.filter((key) => ! this.cols.includes(key)).map((key) => 'sl-hide-' + key).join(' '); },
                    toggleCol(key) { this.cols = this.cols.includes(key) ? this.cols.filter((c) => c !== key) : all.filter((c) => c === key || this.cols.includes(c)); this.saveCols(); },
                    resetCols() { this.cols = [...defaults]; this.saveCols(); },
                    saveCols() { try { window.localStorage.setItem(storageKey, JSON.stringify(this.cols)); } catch (error) {} },
                    setDensity(value) { this.compact = value; try { window.localStorage.setItem('shop-directory-density', value ? 'compact' : 'comfortable'); } catch (error) {} },
                    syncPdf() {
                        document.querySelectorAll('[data-sl-pdf]').forEach((link) => {
                            const url = new URL(link.href);
                            url.searchParams.set('columns', this.cols.join(','));
                            link.href = url.toString();
                        });
                    },
                    openPeek(trigger) {
                        this.peek = { open: true, title: 'Shop', opener: trigger };
                        this.$refs.peekBody.innerHTML = '<div class="sl-skeleton" role="status" aria-label="Loading the shop"><i></i><i></i><i></i><i></i></div>';
                        document.body.style.overflow = 'hidden';
                        this.$nextTick(() => this.$refs.peekClose.focus());
                        fetch(trigger.dataset.slPeek, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                            .then((response) => { if (! response.ok) { throw new Error(response.status); } return response.text(); })
                            .then((html) => {
                                this.$refs.peekBody.innerHTML = html;
                                this.$refs.peekBody.scrollTop = 0;
                                const named = this.$refs.peekBody.querySelector('[data-sl-title]');
                                if (named) { this.peek.title = named.dataset.slTitle; }
                            })
                            .catch(() => { this.$refs.peekBody.innerHTML = '<p class="ak-muted">This shop could not be loaded. Please try again.</p>'; });
                    },
                    closePeek() {
                        const opener = this.peek.opener;
                        this.peek.open = false;
                        document.body.style.overflow = '';
                        if (opener && document.contains(opener)) { opener.focus(); }
                    },
                    trap(event) {
                        const stops = Array.from(this.$refs.peekBox.querySelectorAll('a[href], button')).filter((el) => el.offsetParent !== null);
                        if (! stops.length) { return; }
                        const first = stops[0], last = stops[stops.length - 1];
                        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                        else if (! event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
