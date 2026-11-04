{{--
    Tickets list (/tickets). Same layout as Sales Settlements: hidden-by-default filters, sticky status tabs,
    quick dates, removable chips, a compact sortable table (View + more-actions menu) and a pager.
    Query parameters: filter[...], sort, per_page.
--}}
@php
    use Illuminate\Support\Arr;
    use Illuminate\Support\Str;

    $authUser = auth()->user();
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', '-created_at');
    $tab = ($filters['status'] ?? '') === 'all' ? '' : ($filters['status'] ?? '');
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
        'type' => collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()]),
    ];
    $chipLabels = ['search' => 'Search', 'type' => 'Type', 'supplier_id' => 'Company', 'created_by' => 'Raised by'];
    $chipValue = fn (string $key, $value) => isset($names[$key]) ? ($names[$key][$value] ?? $value) : $value;
    $chips = Arr::except($filters, ['status', 'date_from', 'date_to']);
    $advancedKeys = ['created_by', 'type'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $statusTone = ['pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'rejected' => 'ak-status-red'];
    $periodLabel = ($dateFrom || $dateTo)
        ? ($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'start').' – '.($dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : 'today')
        : 'all dates';
    $isAdmin = $authUser->isTicketAdmin();
    // TKT-000012 -> TKT-12 (full number stays in the tooltip and in search)
    $shortNumber = fn (?string $n) => preg_match('/^TKT-0*(\d+)$/', $n, $m) ? 'TKT-'.$m[1] : (string) $n;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><span>Tickets</span>
                </nav>
                <h1 class="ak-title">Tickets</h1>
                <p class="ak-sub">Product change requests (price, new SKU, re-activation) waiting for admin approval &middot; {{ $periodLabel }}</p>
            </div>
            <div class="ak-head-actions">
                <button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('tk-toggle-filters')" aria-controls="tk-filter-panel" title="Show or hide the filters">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                    Filters @if (count($chips))<span class="ak-count ak-count-dark">{{ count($chips) }}</span>@endif
                </button>
                <a href="javascript:window.location.reload();" class="ak-btn ak-btn-outline" title="Refresh" aria-label="Refresh">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                </a>
                <a href="{{ route('tickets.help') }}" target="_blank" rel="noopener" class="ak-btn ak-btn-outline" title="User manual">Help</a>
                @can('report-audit-product-price-change-log')
                    <a href="{{ route('reports.product-price-change-log.index') }}" class="ak-btn ak-btn-outline">Price change log</a>
                @endcan
                @can('ticket-create')
                    <a href="{{ route('tickets.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> New ticket</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('tickets.partials.style')
    <style>
        .tk-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 900px) { .tk-filter-row { grid-template-columns: minmax(220px, 2fr) 150px 150px minmax(170px, 1.3fr) 90px auto; } }
        .tk-filter-row .ak-field input, .tk-filter-row .ak-field select { height: 38px; box-sizing: border-box; }
        .tk-filter-row .ak-field input[type="date"] { padding: 0 10px; font-size: 14px; }
        .tk-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
        .tk-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
        @media (min-width: 900px) { .tk-advanced { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .tk-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
        .tk-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
        .tk-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
        .tk-ranges a:hover { background: var(--ak-soft); }
        .tk-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
        .tk-table { min-width: 900px; }
        .tk-desc { min-width: 220px; max-width: 360px; color: #334155; font-size: 13px; line-height: 1.4; }
        .tk-desc-col { min-width: 220px; }
        #tk-filter-panel { overflow: hidden; }
        .tk-ranges a.tk-clear { border-color: #fecaca; color: #b91c1c; }
        .tk-ranges a.tk-clear:hover { background: #fef2f2; }
        .tk-ranges-inline { margin-top: 0; }
        .tk-nw { white-space: nowrap; }
        .tk-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #16a34a; box-shadow: 0 0 0 3px #dcfce7; vertical-align: middle; margin-left: 4px; }
        .tk-dot-text { display: none; }
        @media screen and (max-width: 767px) { .tk-dot-text { display: inline; font-size: 13px; font-weight: 600; color: #15803d; margin-left: 8px; } }
        @media print { .tk-dot { display: none; } .tk-dot-text { display: inline; } }
        @media (min-width: 768px) {
            .tk-table thead th:nth-last-child(-n+3), .tk-table tbody td:nth-last-child(-n+3) { width: 1%; white-space: nowrap; }
            .tk-table thead th:nth-last-child(2), .tk-table tbody td:nth-last-child(2) { padding-left: 18px; padding-right: 18px; }
            .tk-table thead th:nth-last-child(1), .tk-table tbody td:nth-last-child(1) { padding-left: 8px; }
        }
        .tk-act { display: flex; align-items: center; justify-content: flex-end; gap: 4px; white-space: nowrap; }
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
        .tk-table > tbody > tr > td { border-bottom: 1px solid #e5e7eb; }
        .tk-table > tbody > tr:last-child > td { border-bottom: 0; }
        .tk-table > tbody > tr:hover > td { background: #f1f5f9; }
        .tk-pending > td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
        .tk-pending > td { background: #fffbeb; }
        /* Searchable dropdowns (Select2) sized like the other filter fields, same as Goods Issues */
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
        .ak-filters .select2-container--focus .select2-selection--single, .ak-filters .select2-container--open .select2-selection--single { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
        .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
        .select2-search--dropdown .select2-search__field { border-radius: 6px; padding: 6px 8px; }
        @media print { .tk-ranges, .ak-filters, .ak-tabs { display: none !important; } .tk-table { min-width: 0 !important; } @page { margin: 10mm; } }
    </style>

    <div class="ak-page" x-data="{ confirm: { open: false, url: '', number: '' }, deleting: false }">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Tickets &middot; {{ $periodLabel }}</div>
            <table class="ak-print-meta"><tr><th>Printed</th><td>{{ now()->format('d.m.Y H:i') }} by {{ $authUser->name }}</td></tr></table>
        </div>

        @include('tickets.partials.flash')
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section id="tk-filter-panel" class="ak-card" aria-label="Filters" x-data="{ open: {{ $chips ? 'true' : 'false' }} }" @tk-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $chips ? '' : 'display:none' }}">
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
                                placeholder="Ticket number or title" x-ref="search"
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
                        <select id="f_supplier" class="tk-select" name="filter[supplier_id]">
                            @if ($suppliers->count() !== 1)<option value="">All companies</option>@endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
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
                        <select id="f_type" class="tk-select" name="filter[type]">
                            <option value="">All types</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_creator">Raised by</label>
                        <select id="f_creator" class="tk-select" name="filter[created_by]">
                            <option value="">Anyone</option>
                            @foreach ($creators as $creator)
                                <option value="{{ $creator->id }}" @selected((string) ($filters['created_by'] ?? '') === (string) $creator->id)>{{ $creator->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <nav class="tk-ranges" aria-label="Quick dates">
                    <span class="tk-ranges-label">Quick dates</span>
                    @foreach ($ranges as $label => [$from, $to])
                        <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('tickets.index') }}" class="tk-clear" title="Remove every filter">&times; Clear</a>
                </nav>
            </form>
        </section>
        <section class="ak-card" aria-label="Tickets">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $stats['total']], 'pending' => ['Pending', $stats['pending']], 'approved' => ['Approved', $stats['approved']], 'rejected' => ['Rejected', $stats['rejected']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl($key) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === $key ? 'is-active' : '' }}{{ $key === 'pending' && $count > 0 ? ' tk-tab-warn' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>


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
                            &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Raised on' }}</b> ({{ str_starts_with($sort, '-') ? 'newest first' : 'oldest first' }})
                        </p>
                        <nav class="tk-ranges tk-ranges-inline" aria-label="Quick dates">
                        @php $matched = ! $dateFrom && ! $dateTo; @endphp
                        @foreach ($ranges as $label => [$from, $to])
                            @php $on = $dateFrom === $from && $dateTo === $to; $matched = $matched || $on; @endphp
                            <a href="{{ $rangeUrl($from, $to) }}" @if ($on) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                        <a href="{{ $withFilters(Arr::except($filters, ['date_from', 'date_to'])) }}" @if (! $dateFrom && ! $dateTo) aria-current="true" @endif>All</a>
                        @unless ($matched)<a href="#" aria-current="true" onclick="return false" title="Custom period (change it in Filters)">{{ $periodLabel }}</a>@endunless
                    </nav>
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
                                    <th scope="col" aria-sort="{{ $ariaSort('ticket_number') }}"><a href="{{ $sortUrl('ticket_number') }}">Ticket <span aria-hidden="true">{{ $sortMark('ticket_number') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('title') }}"><a href="{{ $sortUrl('title') }}">Title &middot; type <span aria-hidden="true">{{ $sortMark('title') }}</span></a></th>
                                    <th scope="col" class="tk-desc-col">Description</th>
                                    <th scope="col" class="ak-num">Items</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('created_at') }}"><a href="{{ $sortUrl('created_at') }}">Raised <span aria-hidden="true">{{ $sortMark('created_at') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('status') }}"><a href="{{ $sortUrl('status') }}">Status <span aria-hidden="true">{{ $sortMark('status') }}</span></a></th>
                                    <th scope="col" class="ak-sticky-end no-print" style="text-align:right">Actions</th>
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
                                        <td data-label="Ticket">
                                            <a href="{{ route('tickets.show', $ticket) }}" class="ak-primary-link tk-nw" title="{{ $ticket->ticket_number }}">{{ $shortNumber($ticket->ticket_number) }}</a>
                                            <div class="ak-muted tk-nw" title="{{ $ticket->supplier->supplier_name ?? 'No company' }}">{{ Str::limit($ticket->supplier->supplier_name ?? 'No company', 18) }}</div>
                                        </td>
                                        <td data-label="Title">
                                            <span class="ak-strong">{{ $ticket->title }}</span>
                                            <div class="ak-muted">{{ $ticket->type->label() }}</div>
                                        </td>
                                        <td class="tk-desc" data-label="Description">
                                            @if (filled($ticket->description))
                                                <span title="{{ $ticket->description }}">{{ Str::limit(preg_replace('/\s+/', ' ', $ticket->description), 110) }}</span>
                                            @else
                                                <span class="ak-muted">—</span>
                                            @endif
                                            @if (filled($ticket->review_remarks))
                                                <div class="ak-muted" title="{{ $ticket->review_remarks }}">Admin: {{ Str::limit(preg_replace('/\s+/', ' ', $ticket->review_remarks), 80) }}</div>
                                            @endif
                                        </td>
                                        <td class="ak-num" data-label="Items">{{ number_format($ticket->items_count) }}</td>
                                        <td class="ak-mono tk-nw" data-label="Raised">
                                            {{ $ticket->created_at->format('d M Y') }}
                                            <div class="ak-muted" title="{{ $ticket->created_at->format('d M Y h:i A') }}">{{ $ticket->creator->name ?? '—' }}</div>
                                        </td>
                                        <td data-label="Status">
                                            @if ($ticket->status->value === 'approved')
                                                <span class="tk-dot" title="Approved{{ $ticket->reviewed_at ? ' '.$ticket->reviewed_at->format('d M Y h:i A') : '' }}" role="img" aria-label="Approved"></span><span class="tk-dot-text">Approved</span>
                                            @else
                                                <span class="ak-status {{ $statusTone[$ticket->status->value] ?? 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ $ticket->status->label() }}</span>
                                            @endif
                                            @if ($ticket->reviewer)
                                                <div class="ak-muted tk-nw" title="{{ $ticket->reviewed_at?->format('d M Y, h:i A') }}">by {{ $ticket->reviewer->name }}</div>
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
                                                <a href="{{ route('tickets.show', $ticket) }}" class="ak-btn ak-btn-outline ak-btn-sm" style="gap:6px" title="Open {{ $ticket->ticket_number }}" aria-label="View {{ $ticket->ticket_number }}">
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
                        @if ($filters)
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
            // Searchable dropdowns for company and requester.
            $(function () {
                $('.tk-select').each(function () {
                    const $select = $(this);
                    $select.select2({ width: '100%', placeholder: $select.find('option[value=""]').text() || 'Select', allowClear: $select.find('option[value=""]').length > 0 });
                });
            });
        </script>
    @endpush
</x-app-layout>
