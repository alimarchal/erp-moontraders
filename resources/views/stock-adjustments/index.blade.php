{{--
    Stock Adjustments list (/stock-adjustments). Same layout as Expense Vouchers: status tabs, search with
    quick date ranges, removable chips, a sortable table with comfortable / compact density and a pager.
--}}
@php
    $today = now()->toDateString();
    $tab = $filters['status'] ?? '';
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $status) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, ['status']);
        if ($status !== '') {
            $f['status'] = $status;
        }

        return $withFilters($f);
    };
    $rangeUrl = fn (string $from, string $to) => $withFilters(array_merge($filters, ['date_from' => $from, 'date_to' => $to]));
    $ranges = [
        'Today' => [$today, $today, 'آج'],
        'This week' => [now()->startOfWeek()->toDateString(), $today, 'اس ہفتے'],
        'This month' => [now()->startOfMonth()->toDateString(), $today, 'اس مہینے'],
        'Last 30 days' => [now()->subDays(29)->toDateString(), $today, 'پچھلے 30 دن'],
    ];
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === '-'.$field ? $field : '-'.$field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');
    $types = ['damage' => 'Damage', 'theft' => 'Theft', 'count_variance' => 'Count variance', 'expiry' => 'Expiry', 'recall' => 'Recall', 'other' => 'Other'];
    $chipLabels = ['search' => 'Search', 'warehouse_id' => 'Warehouse', 'adjustment_type' => 'Type'];
    $chipValue = fn (string $key, $value) => match ($key) {
        'warehouse_id' => $warehouses->firstWhere('id', $value)?->warehouse_name ?? $value,
        'adjustment_type' => $types[$value] ?? $value,
        default => $value,
    };
    $chips = \Illuminate\Support\Arr::except($filters, ['status', 'date_from', 'date_to']);
    $activeFilterCount = count($chips) + ($dateFrom || $dateTo ? 1 : 0);
    $statusTone = ['draft' => 'ak-status-slate', 'posted' => 'ak-status-green', 'cancelled' => 'ak-status-red'];
    $statusUrdu = ['draft' => 'مسودہ', 'posted' => 'پوسٹ شدہ', 'cancelled' => 'منسوخ'];
    $statusTotal = $statusCounts->sum();
    $signed = fn ($value) => ((float) $value > 0 ? '+' : '').number_format((float) $value, 2);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><span>Stock Adjustments</span>
                </nav>
                <h1 class="ak-title">Stock Adjustments <span class="ev-ur" lang="ur" dir="rtl">اسٹاک ایڈجسٹمنٹ</span></h1>
                <p class="ak-sub">Damage, theft, expiry, count differences and cost corrections &middot; posts to stock, valuation and the journal</p>
            </div>
            <div class="ak-head-actions">
                <button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('sa-toggle-filters')" aria-controls="sa-filter-panel">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                    Filters @if($activeFilterCount)<span class="ak-count ak-count-dark">{{ $activeFilterCount }}</span>@endif
                </button>
                <a href="{{ route('stock-adjustments.help') }}" class="ak-btn ak-btn-outline">How it works <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:16px">طریقہ کار</span></a>
                @can('stock-adjustment-create')
                    <a href="{{ route('stock-adjustments.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> New adjustment <span class="ev-ur" lang="ur" dir="rtl">نئی ایڈجسٹمنٹ</span></a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .ev-ur { font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Geeza Pro', serif; font-weight: 600; color: var(--ak-navy); margin-inline-start: 8px; line-height: 1.5; font-size: 1.1em; }
        .ak-btn .ev-ur, .ak-tab .ev-ur, .ak-status .ev-ur, .ak-seg .ev-ur, .ev-ranges a .ev-ur { color: inherit; margin-inline-start: 6px; }
        .ak-btn-primary .ev-ur { color: #fff; }
        .ak-title .ev-ur { font-size: 22px; margin-inline-start: 14px; }
        .ak-dt th .ev-ur { font-size: 14px; text-transform: none; letter-spacing: 0; color: var(--ak-navy); }
        .ak-dt th a .ev-ur { color: inherit; }
        .ev-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
        .ev-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
        .ev-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
        .ev-ranges a:hover { background: var(--ak-soft); }
        .ev-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
        .ev-ranges a.ev-clear { border-color: #fecaca; color: #b91c1c; }
        .sa-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 900px) { .sa-filter-row { grid-template-columns: minmax(220px, 2fr) 150px 150px minmax(160px, 1.2fr) 150px 90px auto; } }
        .sa-filter-row .ak-field input, .sa-filter-row .ak-field select { height: 38px; box-sizing: border-box; }
        .sa-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
        .sa-table { min-width: 980px; }
        .sa-table .ak-primary-link { white-space: nowrap; }
        .sa-up { color: #047857; } .sa-down { color: #b91c1c; }
        .ak-status-slate { background: #f1f5f9; color: #475569; } .ak-status-slate i { background: #64748b; }
        .sa-draft > td { background: #fffbeb; }
        @media print { .ev-ranges, .ak-filters, .ak-tabs { display: none !important; } }
    </style>

    <div class="ak-page">
        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section id="sa-filter-panel" class="ak-card" aria-label="Filters" x-data="{ open: {{ $activeFilterCount ? 'true' : 'false' }} }" @sa-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $activeFilterCount ? '' : 'display:none' }}">
            <form method="GET" action="{{ route('stock-adjustments.index') }}" class="ak-filters" x-data="{ busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[status]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                <div class="sa-filter-row">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off" placeholder="Number or reason">
                        </div>
                    </div>
                    <div class="ak-field"><label for="f_from">From</label><input id="f_from" type="date" name="filter[date_from]" value="{{ $dateFrom }}"></div>
                    <div class="ak-field"><label for="f_to">To</label><input id="f_to" type="date" name="filter[date_to]" value="{{ $dateTo }}"></div>
                    <div class="ak-field">
                        <label for="f_wh">Warehouse</label>
                        <select id="f_wh" name="filter[warehouse_id]">
                            <option value="">All warehouses</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_type">Type</label>
                        <select id="f_type" name="filter[adjustment_type]">
                            <option value="">All types</option>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['adjustment_type'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\StockAdjustmentController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy"><span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span></button>
                    </div>
                </div>
                <nav class="ev-ranges" aria-label="Quick dates">
                    <span class="ev-ranges-label">Quick dates</span>
                    @foreach ($ranges as $label => [$from, $to, $urdu])
                        <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }} <span class="ev-ur" lang="ur" dir="rtl">{{ $urdu }}</span></a>
                    @endforeach
                    <a href="{{ route('stock-adjustments.index') }}" class="ev-clear">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-card" aria-label="Stock adjustments">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $statusTotal, 'تمام'], 'draft' => ['Draft', $statusCounts['draft'] ?? 0, 'مسودہ'], 'posted' => ['Posted', $statusCounts['posted'] ?? 0, 'پوسٹ شدہ'], 'cancelled' => ['Cancelled', $statusCounts['cancelled'] ?? 0, 'منسوخ']] as $key => [$label, $count, $urdu])
                    <a href="{{ $tabUrl($key) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === $key ? 'is-active' : '' }}">
                        {{ $label }} <span class="ev-ur" lang="ur" dir="rtl">{{ $urdu }}</span> <span class="ak-count">{{ number_format($count) }}</span>
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
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['status', 'date_from', 'date_to'])) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($adjustments->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('sa-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('sa-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p><b>{{ number_format($adjustments->total()) }}</b> {{ \Illuminate\Support\Str::plural('adjustment', $adjustments->total()) }}</p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Stock adjustments table">
                        <table class="ak-dt sa-table">
                            <caption class="sr-only">Stock adjustments, {{ $adjustments->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c" style="width:48px">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('adjustment_number') }}"><a href="{{ $sortUrl('adjustment_number') }}">Adjustment <span class="ev-ur" lang="ur" dir="rtl">ایڈجسٹمنٹ</span> <span aria-hidden="true">{{ $sortMark('adjustment_number') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('adjustment_date') }}"><a href="{{ $sortUrl('adjustment_date') }}">Date <span class="ev-ur" lang="ur" dir="rtl">تاریخ</span> <span aria-hidden="true">{{ $sortMark('adjustment_date') }}</span></a></th>
                                    <th scope="col">Warehouse <span class="ev-ur" lang="ur" dir="rtl">گودام</span></th>
                                    <th scope="col">Type <span class="ev-ur" lang="ur" dir="rtl">قسم</span></th>
                                    <th scope="col" class="ak-num">Lines <span class="ev-ur" lang="ur" dir="rtl">لائنیں</span></th>
                                    <th scope="col" class="ak-num">Qty change <span class="ev-ur" lang="ur" dir="rtl">مقدار کا فرق</span></th>
                                    <th scope="col" class="ak-num">Value (Rs) <span class="ev-ur" lang="ur" dir="rtl">قدر</span></th>
                                    <th scope="col">Status <span class="ev-ur" lang="ur" dir="rtl">اسٹیٹس</span></th>
                                    <th scope="col">Created by <span class="ev-ur" lang="ur" dir="rtl">بنانے والا</span></th>
                                    <th scope="col" class="ak-c ak-sticky-end"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($adjustments as $adjustment)
                                    <tr class="{{ $adjustment->status === 'draft' ? 'sa-draft' : '' }}">
                                        <td class="ak-c ak-muted">{{ $adjustments->firstItem() + $loop->index }}</td>
                                        <td data-label="Adjustment">
                                            <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="ak-primary-link">{{ $adjustment->adjustment_number }}</a>
                                            <div class="ak-muted ak-hide-compact" title="{{ $adjustment->reason }}">{{ \Illuminate\Support\Str::limit($adjustment->reason, 40) }}</div>
                                        </td>
                                        <td class="ak-mono" data-label="Date">{{ $adjustment->adjustment_date->format('d M Y') }}</td>
                                        <td data-label="Warehouse">{{ $adjustment->warehouse->warehouse_name ?? '—' }}</td>
                                        <td data-label="Type">{{ $types[$adjustment->adjustment_type] ?? \Illuminate\Support\Str::headline($adjustment->adjustment_type) }}</td>
                                        <td class="ak-num" data-label="Lines">{{ $adjustment->items_count }}</td>
                                        <td class="ak-num {{ $adjustment->items_sum_adjustment_quantity > 0 ? 'sa-up' : ($adjustment->items_sum_adjustment_quantity < 0 ? 'sa-down' : '') }}" data-label="Qty change">{{ $signed($adjustment->items_sum_adjustment_quantity) }}</td>
                                        @php $lineValue = (float) $adjustment->items_sum_adjustment_value + (float) $adjustment->revaluation_value; @endphp
                                        <td class="ak-num ak-strong {{ $lineValue < 0 ? 'sa-down' : '' }}" data-label="Value (Rs)">{{ $signed($lineValue) }}@if (abs((float) $adjustment->revaluation_value) > 0.004)<div class="ak-muted ak-hide-compact" style="font-weight:400">incl. cost change</div>@endif</td>
                                        <td data-label="Status"><span class="ak-status {{ $statusTone[$adjustment->status] ?? 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ ucfirst($adjustment->status) }} <span class="ev-ur" lang="ur" dir="rtl">{{ $statusUrdu[$adjustment->status] ?? '' }}</span></span></td>
                                        <td data-label="Created by">{{ $adjustment->createdBy->name ?? '—' }}</td>
                                        <td class="ak-sticky-end" data-label="">
                                            <div class="ak-actions">
                                                <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="ak-icon ak-icon-view" title="View" aria-label="View {{ $adjustment->adjustment_number }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                </a>
                                                <span class="ak-slot">
                                                    @if ($adjustment->isDraft())
                                                        @can('stock-adjustment-edit')
                                                            <a href="{{ route('stock-adjustments.edit', $adjustment) }}" class="ak-icon" title="Edit" aria-label="Edit {{ $adjustment->adjustment_number }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                </span>
                                                <span class="ak-slot">
                                                    @if ($adjustment->isDraft())
                                                        @can('stock-adjustment-delete')
                                                            <form action="{{ route('stock-adjustments.destroy', $adjustment) }}" method="POST" onsubmit="return confirm('Delete this draft stock adjustment?');">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="ak-icon ak-icon-danger" title="Delete draft" aria-label="Delete {{ $adjustment->adjustment_number }}">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67a2.25 2.25 0 0 1-2.24 2.08H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                                                                </button>
                                                            </form>
                                                        @endcan
                                                    @endif
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($adjustments->firstItem()) }}–{{ number_format($adjustments->lastItem()) }}</b> of <b>{{ number_format($adjustments->total()) }}</b> adjustments</p>
                    <div class="ak-pager-right"><div>{{ $adjustments->onEachSide(1)->links() }}</div></div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No stock adjustments found{{ $chips || $tab || $dateFrom ? ' with these filters' : '' }} <span class="ev-ur" lang="ur" dir="rtl">کوئی ایڈجسٹمنٹ نہیں ملی</span></h2>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        <a href="{{ route('stock-adjustments.index') }}" class="ak-btn ak-btn-outline">Clear filters</a>
                        @can('stock-adjustment-create') <a href="{{ route('stock-adjustments.create') }}" class="ak-btn ak-btn-primary">＋ New adjustment</a> @endcan
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
