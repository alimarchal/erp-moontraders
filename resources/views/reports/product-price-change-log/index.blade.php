{{--
    Product Price Change Log (/reports/product-price-change-log). Same layout as the Goods Issues list:
    hidden-by-default filters, sticky price type tabs, quick dates, removable chips, a compact sortable
    table and a pager. Query parameters are filter[...], sort and per_page; dates default to the last 30 days.
--}}
@php
    $authUser = auth()->user();
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', '-changed_at');
    $tab = $filters['price_type'] ?? '';
    $today = now()->toDateString();
    $dateFrom = $filters['changed_from'] ?? now()->subDays(29)->toDateString();
    $dateTo = $filters['changed_to'] ?? $today;

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $type) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, ['price_type']);
        if ($type !== '') {
            $f['price_type'] = $type;
        }

        return $withFilters($f);
    };
    $rangeUrl = fn (string $from, string $to) => $withFilters(array_merge($filters, ['changed_from' => $from, 'changed_to' => $to]));
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
        'supplier_id' => $supplierOptions->pluck('supplier_name', 'id'),
        'product_id' => $productOptions->mapWithKeys(fn ($p) => [$p->id => trim($p->product_code.' '.$p->product_name)]),
        'changed_by' => $userOptions->pluck('name', 'id'),
        'source' => collect($sourceOptions),
    ];
    $chipLabels = [
        'search' => 'Search', 'supplier_id' => 'Supplier', 'product_id' => 'Product', 'batch' => 'Batch',
        'source' => 'Source', 'changed_by' => 'Changed by', 'price_type' => 'Field',
    ];
    $chipValue = fn (string $key, $value) => match ($key) {
        'price_type' => $priceTypeOptions[$value] ?? $value,
        default => isset($names[$key]) ? ($names[$key][$value] ?? $value) : $value,
    };
    // Dates and the price type have their own controls, so they are not chips.
    $chips = \Illuminate\Support\Arr::except($filters, ['price_type', 'changed_from', 'changed_to']);
    $advancedKeys = ['batch', 'source', 'changed_by'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $periodLabel = $dateFrom === $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
        : \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y');
    $typeTone = ['selling_price' => 'pl-tag-blue', 'cost_price' => 'pl-tag-purple', 'expiry_price' => 'pl-tag-amber'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Product Price Change Log" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <style>
        /* Apple look for this page's filter menu, as on the Goods Issues list */
        html.apl-on .rg-pl-filters { border: 0 !important; border-radius: 20px !important; box-shadow: 0 0 0 1px rgba(0,0,0,.035), 0 2px 6px rgba(0,0,0,.04), 0 10px 28px -6px rgba(0,0,0,.08) !important; }
        html.apl-on .rg-pl-filters .ak-field label { font-size: 13px; font-weight: 500; color: #6e6e73; text-transform: none; letter-spacing: 0; }
        html.apl-on .rg-pl-filters .ak-btn { height: 38px; border-radius: 980px !important; text-transform: none; transition: transform .1s ease-out, background-color .15s ease; }
        html.apl-on .rg-pl-filters .ak-btn:active { transform: scale(.97); }
        html.apl-on .rg-pl-filters .ak-btn-primary { background: #0071e3 !important; border-color: #0071e3 !important; }
        html.apl-on .rg-pl-filters .ak-btn-ghost { background: rgba(120,120,128,.14) !important; border: 0 !important; color: #1d1d1f !important; }
        html.apl-on .rg-pl-filters .pl-ranges a { border: 0 !important; background: rgba(118,118,128,.12); color: #1d1d1f; font-weight: 500; font-size: 13px; padding: 5px 12px; transition: background-color .15s ease, transform .1s ease-out; }
        html.apl-on .rg-pl-filters .pl-ranges a:hover { background: rgba(118,118,128,.22); }
        html.apl-on .rg-pl-filters .pl-ranges a:active { transform: scale(.96); }
        html.apl-on .rg-pl-filters .pl-ranges a[aria-current] { background: #0071e3; color: #fff; }
        html.apl-on .rg-pl-filters .pl-ranges .pl-clear { background: rgba(255,59,48,.1); color: #d70015; }
        html.apl-on .rg-pl-filters .pl-ranges-label { font-size: 13px; font-weight: 500; color: #6e6e73; }
        html.apl-on .pl-table .ak-muted, html.apl-on .ak-tab .ak-count { color: #6e6e73 !important; }

        .pl-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
        .pl-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
        .pl-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
        .pl-ranges a:hover { background: var(--ak-soft); }
        .pl-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
        .pl-ranges a.pl-clear { border-color: #fecaca; color: #b91c1c; background: #fff; }
        .pl-filter-row .ak-field input, .pl-filter-row .ak-field select { height: 38px; box-sizing: border-box; }
        .pl-filter-row .ak-field input[type="date"], .pl-advanced .ak-field input[type="text"] { padding: 0 10px; font-size: 14px; }
        .pl-advanced .ak-field input { height: 38px; box-sizing: border-box; }
        .pl-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
        .pl-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 900px) { .pl-filter-row { grid-template-columns: minmax(200px, 1.6fr) 150px 150px minmax(160px, 1.2fr) minmax(180px, 1.4fr) 80px auto; } }
        .pl-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
        @media (min-width: 900px) { .pl-advanced { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        #pl-filter-panel { overflow: hidden; }

        section[aria-label="Price changes"] > .ak-tabs { position: sticky; top: 0; z-index: 25; background: #fff; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }
        .ak-dt-scroll { max-height: calc(100vh - 190px); min-height: 18rem; }
        .pl-table > tbody > tr:hover > td { background: #f1f5f9; }
        .pl-table td.pl-no { width: 40px; color: #8e8e93; font-size: 12.5px; font-variant-numeric: tabular-nums; }
        .pl-nw { white-space: nowrap; }
        .pl-tag { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .pl-tag-blue { background: rgba(0,113,227,.1); color: #0058b0; }
        .pl-tag-purple { background: rgba(175,82,222,.14); color: #7a2fa3; }
        .pl-tag-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .pl-tag-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
        .pl-old { color: #6e6e73; }
        .pl-old > span { text-decoration: line-through; }
        .pl-up { color: #c4161c; font-weight: 600; }
        .pl-down { color: #1f7a35; font-weight: 600; }
        .pl-batches { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ak-dt-scroll.is-compact .pl-table td { padding-top: 6px; padding-bottom: 6px; vertical-align: middle; }
        .ak-dt-scroll.is-compact .pl-hide-compact { display: none; }
        .pl-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }

        /* Searchable dropdowns (Select2) sized like the other filter fields */
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
        .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }

        /* Tablets and phones: rows become cards */
        @media screen and (max-width: 1023px) {
            .pl-table { min-width: 0 !important; }
            .pl-table thead { display: none; }
            .pl-table, .pl-table tbody, .pl-table tr, .pl-table td, .pl-table tfoot { display: block; width: 100%; }
            .pl-table tbody tr { margin: 12px; width: auto; border: 1px solid var(--ak-border); border-radius: var(--ak-radius); overflow: hidden; }
            .pl-table td { display: flex; justify-content: space-between; gap: 12px; padding: 8px 12px; text-align: right; }
            .pl-table td::before { content: attr(data-label); font-size: 12px; font-weight: 700; color: var(--ak-muted); text-align: left; }
            .pl-table td.pl-no { display: none; }
            .pl-table tfoot td { position: static; }
            .pl-batches { max-width: 60%; }
        }
        @media print {
            @page { margin: 10mm; }
            .pl-ranges, .ak-filters, .ak-tabs { display: none !important; }
            .pl-table { min-width: 0 !important; }
            .pl-table tfoot td { position: static; }
        }
    </style>

    <div class="ak-page">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Product Price Change Log &middot; {{ $periodLabel }}</div>
            <table class="ak-print-meta">
                <tr><th>Filters</th><td>{{ collect(\Illuminate\Support\Arr::except($filters, ['changed_from', 'changed_to']))->map(fn ($v, $k) => ($chipLabels[$k] ?? \Illuminate\Support\Str::headline($k)).': '.$chipValue($k, $v))->implode(' · ') ?: 'None' }}</td>
                    <th>Printed</th><td>{{ now()->format('d.m.Y H:i') }} by {{ $authUser->name }}</td></tr>
            </table>
        </div>

        <x-status-message />

        <section id="pl-filter-panel" class="ak-card rg-pl-filters" aria-label="Filters" x-data="{ open: false }" @pl-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="display:none">
            <form method="GET" action="{{ route('reports.product-price-change-log.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[price_type]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="pl-filter-row">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Product, SKU, supplier or user" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_from">From</label>
                        <input id="f_from" type="date" name="filter[changed_from]" value="{{ $dateFrom }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_to">To</label>
                        <input id="f_to" type="date" name="filter[changed_to]" value="{{ $dateTo }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_supplier">Supplier</label>
                        <select id="f_supplier" class="pl-select" data-placeholder="All suppliers" name="filter[supplier_id]">
                            @if ($canViewAllSuppliers)<option value=""></option>@endif
                            @foreach ($supplierOptions as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? $supplierId) === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_product">Product / SKU</label>
                        <select id="f_product" class="pl-select" data-placeholder="All products" name="filter[product_id]">
                            <option value=""></option>
                            @foreach ($productOptions as $product)
                                <option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->product_code }} — {{ $product->product_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" class="rg-select" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\Reports\ProductPriceChangeLogController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="pl-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>

                <div id="pl-advanced" class="pl-advanced" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_batch">Batch code</label>
                        <input id="f_batch" type="text" name="filter[batch]" value="{{ $filters['batch'] ?? '' }}" autocomplete="off" placeholder="e.g. BATCH-2026-0123">
                    </div>
                    <div class="ak-field">
                        <label for="f_source">Source</label>
                        <select id="f_source" class="rg-select" name="filter[source]">
                            <option value="">Every source</option>
                            @foreach ($sourceOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_changed_by">Changed by</label>
                        <select id="f_changed_by" class="pl-select" data-placeholder="All users" name="filter[changed_by]">
                            <option value=""></option>
                            @foreach ($userOptions as $user)
                                <option value="{{ $user->id }}" @selected((string) ($filters['changed_by'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <nav class="pl-ranges" aria-label="Quick dates">
                    <span class="pl-ranges-label">Quick dates</span>
                    @foreach ($ranges as $label => [$from, $to])
                        <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('reports.product-price-change-log.index') }}" class="pl-clear" title="Remove every filter and go back to the last 30 days">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-card" aria-label="Price changes">
            <nav class="ak-tabs" aria-label="Field changed">
                @php $plTabs = array_merge([['', 'All', $stats['total']]], collect($priceTypeOptions)->map(fn ($label, $type) => [$type, $label, $stats['by_type'][$type] ?? 0])->values()->all()); @endphp
                @foreach ($plTabs as [$key, $label, $count])
                    <a href="{{ $tabUrl($key) }}" @if ($tab === $key) aria-current="page" @endif class="ak-tab {{ $tab === $key ? 'is-active' : '' }}">
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
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['price_type', 'changed_from', 'changed_to'])) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($logs->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('pl-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('pl-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($logs->total()) }}</b> {{ \Illuminate\Support\Str::plural('change', $logs->total()) }}
                            &middot; <span class="pl-up">{{ number_format($stats['increases']) }} up</span>
                            &middot; <span class="pl-down">{{ number_format($stats['decreases']) }} down</span>
                            &middot; {{ $periodLabel }}
                        </p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Price changes table">
                        <table class="ak-dt pl-table">
                            <caption class="sr-only">Product price changes, {{ $logs->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('changed_at') }}"><a href="{{ $sortUrl('changed_at') }}">Date <span aria-hidden="true">{{ $sortMark('changed_at') }}</span></a></th>
                                    <th scope="col">Product &middot; supplier</th>
                                    <th scope="col">Field</th>
                                    <th scope="col" class="ak-num" aria-sort="{{ $ariaSort('old_price') }}"><a href="{{ $sortUrl('old_price') }}">Old <span aria-hidden="true">{{ $sortMark('old_price') }}</span></a></th>
                                    <th scope="col" class="ak-num" aria-sort="{{ $ariaSort('new_price') }}"><a href="{{ $sortUrl('new_price') }}">New <span aria-hidden="true">{{ $sortMark('new_price') }}</span></a></th>
                                    <th scope="col" class="ak-num">Change</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('impacted_batch_count') }}"><a href="{{ $sortUrl('impacted_batch_count') }}">Batches <span aria-hidden="true">{{ $sortMark('impacted_batch_count') }}</span></a></th>
                                    <th scope="col">Source</th>
                                    <th scope="col">Changed by</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    @php
                                        $diff = (float) $log->new_price - (float) $log->old_price;
                                        $percent = (float) $log->old_price > 0 ? $diff / (float) $log->old_price * 100 : null;
                                        $isAdjustment = $log->price_type === 'selling_price' && ($log->adjustment_number || $log->adjustment_ticket_number);
                                        $sourceTones = ['ticket' => 'pl-tag-blue', 'stock_adjustment' => 'pl-tag-amber', 'product_edit' => 'pl-tag-grey', 'import' => 'pl-tag-grey'];
                                        [$sourceLabel, $sourceRef, $sourceTone] = match (true) {
                                            $log->source !== null => [$sourceOptions[$log->source] ?? $log->source, $log->source_reference, $sourceTones[$log->source] ?? 'pl-tag-grey'],
                                            $isAdjustment => ['Stock adjustment', $log->adjustment_number ?? $log->adjustment_ticket_number, 'pl-tag-amber'],
                                            (bool) $log->price_ticket_number => ['Price ticket', $log->price_ticket_number, 'pl-tag-blue'],
                                            default => ['Product edit', null, 'pl-tag-grey'],
                                        };
                                        $batchCodes = $log->impacted_batch_codes ?? [];
                                    @endphp
                                    <tr>
                                        <td class="pl-no" data-label="#">{{ $logs->firstItem() + $loop->index }}</td>
                                        <td class="pl-nw" data-label="Date">
                                            <span class="ak-strong">{{ $log->changed_at?->format('d M Y') }}</span>
                                            <div class="ak-muted pl-hide-compact">{{ $log->changed_at?->format('h:i A') }}</div>
                                        </td>
                                        <td data-label="Product · supplier">
                                            <span class="ak-strong">{{ $log->product->product_name ?? '—' }}</span>
                                            <div class="ak-muted">{{ $log->product->product_code ?? '' }}{{ $log->product?->supplier ? ' · '.$log->product->supplier->supplier_name : '' }}</div>
                                        </td>
                                        <td data-label="Field"><span class="pl-tag {{ $typeTone[$log->price_type] ?? 'pl-tag-grey' }}">{{ $priceTypeOptions[$log->price_type] ?? $log->price_type }}</span></td>
                                        <td class="ak-num pl-old" data-label="Old"><span>{{ $log->old_price !== null ? number_format((float) $log->old_price, 2) : '—' }}</span></td>
                                        <td class="ak-num ak-strong" data-label="New">{{ $log->new_price !== null ? number_format((float) $log->new_price, 2) : '—' }}</td>
                                        <td class="ak-num pl-nw {{ $diff > 0 ? 'pl-up' : ($diff < 0 ? 'pl-down' : '') }}" data-label="Change">
                                            {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2) }}
                                            @if ($percent !== null)<div class="ak-muted pl-hide-compact">{{ $diff > 0 ? '+' : '' }}{{ number_format($percent, 1) }}%</div>@endif
                                        </td>
                                        <td data-label="Batches">
                                            @if (count($batchCodes))
                                                <div class="pl-batches" title="{{ implode(', ', $batchCodes) }}">{{ implode(', ', $batchCodes) }}</div>
                                                <div class="ak-muted pl-hide-compact">{{ count($batchCodes) }} {{ \Illuminate\Support\Str::plural('batch', count($batchCodes)) }}</div>
                                            @else
                                                <span class="ak-muted">—</span>
                                            @endif
                                        </td>
                                        <td data-label="Source">
                                            <span class="pl-tag {{ $sourceTone }}">{{ $sourceLabel }}</span>
                                            @if ($sourceRef)<div class="ak-muted pl-hide-compact">{{ $sourceRef }}</div>@endif
                                        </td>
                                        <td data-label="Changed by">{{ $log->changedBy->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4">Total &middot; {{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural('change', $logs->total()) }}{{ $logs->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td colspan="3" class="ak-num">{{ number_format($stats['increases']) }} up &middot; {{ number_format($stats['decreases']) }} down</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($logs->firstItem()) }}–{{ number_format($logs->lastItem()) }}</b> of <b>{{ number_format($logs->total()) }}</b> changes</p>
                    <div class="ak-pager-right">
                        <div>{{ $logs->onEachSide(1)->links() }}</div>
                    </div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No price changes for {{ $periodLabel }}{{ $chips || $tab ? ' with these filters' : '' }}</h2>
                    <p>Pick a wider date range (for example “All”), or remove a filter.</p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        <a href="{{ $rangeUrl('2020-01-01', $today) }}" class="ak-btn ak-btn-outline">Show all dates</a>
                    </div>
                </div>
            @endif
        </section>
    </div>

    @push('scripts')
        <script>
            // the header's filter button opens this page's own filter panel
            document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('pl-toggle-filters')); });
        </script>
        <script>
            // Searchable dropdowns: type to find a supplier, product or user.
            $(function () {
                $('.pl-select').each(function () {
                    const $select = $(this);
                    $select.select2({
                        width: '100%',
                        placeholder: $select.data('placeholder') || 'Select',
                        allowClear: $select.find('option[value=""]').length > 0,
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
