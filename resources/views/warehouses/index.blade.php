{{--
    Settings -> Warehouses list (/settings/warehouses). Same layout as Settings -> Products:
    KPI cards, Active / Inactive tabs, one search box, removable filter chips and a
    dense sortable table. A warehouse is never deleted, only deactivated, and only when it holds no stock.
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', 'warehouse_name');
    $tab = $filters['disabled'] ?? '';
    $chips = \Illuminate\Support\Arr::except($filters, 'disabled');

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $key) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, 'disabled');
        if ($key !== '') {
            $f['disabled'] = $key;
        }

        return $withFilters($f);
    };
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === $field ? '-'.$field : $field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');

    $companyNames = $companyOptions->pluck('company_name', 'id');
    $typeNames = $warehouseTypeOptions->pluck('name', 'id');
    $chipLabels = ['search' => 'Search', 'warehouse_name' => 'Name', 'company_id' => 'Company', 'warehouse_type_id' => 'Type', 'is_group' => 'Group'];
    $chipValue = fn (string $key, $value) => match ($key) {
        'company_id' => $companyNames[$value] ?? $value,
        'warehouse_type_id' => $typeNames[$value] ?? $value,
        'is_group' => $value === '1' ? 'Yes' : 'No',
        default => $value,
    };
    $activeFilterCount = count($chips);
    $canDeactivate = auth()->user()->can('warehouse-delete');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>Warehouses</span>
                </nav>
                <h1 class="ak-title">Warehouses</h1>
                <p class="ak-sub">Places where stock is received, kept and issued from.</p>
            </div>
            <div class="ak-head-actions">
                <button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('wl-toggle-filters')" aria-controls="wl-filter-panel" title="Show or hide the filters">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                    Filters @if($activeFilterCount)<span class="ak-count ak-count-dark">{{ $activeFilterCount }}</span>@endif
                </button>
                <a href="javascript:window.location.reload();" class="ak-btn ak-btn-outline" title="Refresh" aria-label="Refresh">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                </a>
                @can('warehouse-create')
                    <a href="{{ route('warehouses.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add warehouse</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('settings.partials.select2-init')
    <style>
        #wl-filter-panel { overflow: hidden; }
        .wl-table { min-width: 760px; }
        .wl-table > tbody > tr > td { border-bottom: 1px solid #e5e7eb; }
        .wl-table > tbody > tr:last-child > td { border-bottom: 0; }
        .wl-table > tbody > tr:hover > td { background: #f1f5f9; }
        .wl-table .ak-primary-link { white-space: nowrap; }
        .wl-table .ak-actions { grid-template-columns: repeat(3, 32px); }
        .wl-inactive > td:first-child { box-shadow: inset 3px 0 0 #94a3b8; }
        .wl-inactive > td { background: #f8fafc; color: #64748b; }
        .ak-dt-scroll { max-height: calc(100vh - 190px); min-height: 18rem; }
        section[aria-label="Warehouses"] > .ak-tabs { position: sticky; top: 0; z-index: 25; background: #fff; border-radius: var(--ak-radius) var(--ak-radius) 0 0; }
        .ak-kpis { gap: 10px; }
        .ak-kpis .ak-kpi { padding: 10px 14px; gap: 10px; align-items: center; }
        .ak-kpis .ak-kpi-icon { width: 34px; height: 34px; }
        .ak-kpis .ak-kpi-icon svg { width: 18px; height: 18px; }
        .ak-kpis .ak-kpi-value { font-size: 19px; }
        .ak-kpis .ak-kpi-label { font-size: 12px; }
        .ak-kpis .ak-kpi-hint { font-size: 11.5px; }
        .ak-filters .select2-container { width: 100% !important; display: block; }
        @media print { .ak-filters, .ak-tabs { display: none !important; } .wl-table { min-width: 0 !important; } }
    </style>

    <div class="ak-page" x-data="warehouseList()">
        <x-status-message />

        <section id="wl-filter-panel" class="ak-card" aria-label="Filters" x-data="{ open: {{ $activeFilterCount ? 'true' : 'false' }} }" @wl-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $activeFilterCount ? '' : 'display:none' }}">
            <form method="GET" action="{{ route('warehouses.index') }}" class="ak-filters" x-data="{ busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[disabled]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="ak-filter-row ak-filter-row-users">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Warehouse name or city" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $dispatch('wl-toggle-filters'); $nextTick(() => $refs.search.focus()); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_company">Company</label>
                        <select id="f_company" name="filter[company_id]" class="js-select2">
                            <option value="">All companies</option>
                            @foreach ($companyOptions as $company)
                                <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_type">Warehouse type</label>
                        <select id="f_type" name="filter[warehouse_type_id]" class="js-select2">
                            <option value="">All types</option>
                            @foreach ($warehouseTypeOptions as $type)
                                <option value="{{ $type->id }}" @selected((string) ($filters['warehouse_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows per page</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach ($perPageOptions as $n)
                                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section class="ak-kpis" aria-label="Warehouse summary" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
            <a href="{{ $tabUrl('') }}" class="ak-kpi{{ $tab === '' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 21V9.75L12 4.5l8.25 5.25V21M9 21v-6h6v6" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Warehouses</span>
                    <span class="ak-kpi-value">{{ number_format($stats['total']) }}</span>
                    <span class="ak-kpi-hint">{{ $chips ? 'matching the filters' : 'all locations' }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('0') }}" class="ak-kpi{{ $tab === '0' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Active</span>
                    <span class="ak-kpi-value">{{ number_format($stats['active']) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($stats['inactive']) }} inactive</span>
                </span>
            </a>
        </section>

        <section class="ak-card" aria-label="Warehouses">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $stats['total']], '0' => ['Active', $stats['active']], '1' => ['Inactive', $stats['inactive']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl((string) $key) }}" role="tab" aria-selected="{{ $tab === (string) $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === (string) $key ? 'is-active' : '' }}">
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
                    <a href="{{ $withFilters($tab !== '' ? ['disabled' => $tab] : []) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($warehouses->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('wl-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('wl-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($warehouses->total()) }}</b> {{ \Illuminate\Support\Str::plural('warehouse', $warehouses->total()) }}
                            &middot; sorted by <b>{{ ltrim($sort, '-') === 'created_at' ? 'Date added' : 'Name' }}</b> ({{ str_starts_with($sort, '-') ? 'Z–A / newest first' : 'A–Z / oldest first' }})
                        </p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Warehouses table">
                        <table class="ak-dt wl-table">
                            <caption class="sr-only">Warehouses, {{ $warehouses->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('warehouse_name') }}"><a href="{{ $sortUrl('warehouse_name') }}">Warehouse <span aria-hidden="true">{{ $sortMark('warehouse_name') }}</span></a></th>
                                    <th scope="col">Company &amp; type</th>
                                    <th scope="col">Account</th>
                                    <th scope="col" class="ak-num">Stock on hand</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-sticky-end no-print" style="text-align:right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($warehouses as $warehouse)
                                    @php($stockOnHand = (float) $warehouse->stock_on_hand)
                                    <tr class="{{ $warehouse->disabled ? 'wl-inactive' : '' }}">
                                        <td class="ak-c" data-label=""><span class="ak-muted">{{ $warehouses->firstItem() + $loop->index }}</span></td>
                                        <td data-label="Warehouse">
                                            <a href="{{ route('warehouses.show', $warehouse) }}" class="ak-primary-link">{{ $warehouse->warehouse_name }}</a>
                                            <div class="ak-muted">{{ $warehouse->city ?: '—' }}@if ($warehouse->parentWarehouse) · under {{ $warehouse->parentWarehouse->warehouse_name }}@endif</div>
                                        </td>
                                        <td data-label="Company &amp; type">
                                            <div>{{ $warehouse->company?->company_name ?? '—' }}</div>
                                            <div class="ak-muted">{{ $warehouse->warehouseType?->name ?? '—' }}</div>
                                        </td>
                                        <td data-label="Account">
                                            @if ($warehouse->account)
                                                <span class="ak-mono">{{ $warehouse->account->account_code }}</span>
                                                <div class="ak-muted">{{ $warehouse->account->account_name }}</div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="ak-num {{ $stockOnHand > 0 ? 'ak-strong' : '' }}" data-label="Stock on hand">{{ number_format($stockOnHand, 2) }}</td>
                                        <td data-label="Status">
                                            @if ($warehouse->disabled)
                                                <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Inactive</span>
                                            @else
                                                <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                                            @endif
                                            @if ($warehouse->is_group) <span class="ak-pill">Group</span> @endif
                                            @if ($warehouse->is_rejected_warehouse) <span class="ak-pill">Rejected goods</span> @endif
                                        </td>
                                        <td class="ak-sticky-end no-print" data-label="">
                                            <div class="ak-actions">
                                                <a href="{{ route('warehouses.show', $warehouse) }}" class="ak-icon ak-icon-view" title="View warehouse" aria-label="View {{ $warehouse->warehouse_name }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                </a>
                                                <span class="ak-slot">
                                                    @can('warehouse-edit')
                                                        <a href="{{ route('warehouses.edit', $warehouse) }}" class="ak-icon" title="Edit warehouse" aria-label="Edit {{ $warehouse->warehouse_name }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                        </a>
                                                    @endcan
                                                </span>
                                                <span class="ak-slot">
                                                    @if ($canDeactivate && ! $warehouse->disabled)
                                                        @if ($stockOnHand > 0)
                                                            <button type="button" class="ak-icon" style="opacity:.45; cursor:not-allowed" disabled
                                                                title="Holds {{ number_format($stockOnHand, 2) }} units of stock. Empty the warehouse before deactivating it."
                                                                aria-label="{{ $warehouse->warehouse_name }} cannot be deactivated while it holds stock">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.36 6.64A9 9 0 1 1 5.64 6.64M12 3v9" /></svg>
                                                            </button>
                                                        @else
                                                            <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" @submit.prevent="askDeactivate($el, @js($warehouse->warehouse_name))">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="ak-icon ak-icon-danger" title="Deactivate warehouse" aria-label="Deactivate {{ $warehouse->warehouse_name }}">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.36 6.64A9 9 0 1 1 5.64 6.64M12 3v9" /></svg>
                                                                </button>
                                                            </form>
                                                        @endif
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
                    <p>Showing <b>{{ number_format($warehouses->firstItem()) }}–{{ number_format($warehouses->lastItem()) }}</b> of <b>{{ number_format($warehouses->total()) }}</b> warehouses</p>
                    <div class="ak-pager-right">{{ $warehouses->onEachSide(1)->links() }}</div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    @if ($filters)
                        <h2>No warehouses match these filters</h2>
                        <p>Remove a filter or search with part of a name.</p>
                        <a href="{{ route('warehouses.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>
                    @else
                        <h2>No warehouses yet</h2>
                        <p>Add the first place where stock is kept.</p>
                        @can('warehouse-create') <a href="{{ route('warehouses.create') }}" class="ak-btn ak-btn-primary">Add warehouse</a> @endcan
                    @endif
                </div>
            @endif
        </section>

        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="wl-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon ak-pill-red" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="wl-confirm-title" x-text="confirm.title"></h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155">The warehouse will be marked Inactive and can be re-activated from Edit. Nothing is deleted.</p>
                    </div>
                </div>
                <div class="uf-modal-foot">
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="button" class="ak-btn ak-btn-danger-outline" @click="runConfirm()" :disabled="confirm.busy" x-text="confirm.busy ? 'Working…' : 'Deactivate warehouse'"></button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function warehouseList() {
                return {
                    confirm: { open: false, title: '', busy: false, form: null },
                    askDeactivate(form, name) { this.confirm = { open: true, title: 'Deactivate ' + name + '?', busy: false, form }; },
                    runConfirm() {
                        if (this.confirm.busy || !this.confirm.form) return;
                        this.confirm.busy = true;
                        this.confirm.form.submit();
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
