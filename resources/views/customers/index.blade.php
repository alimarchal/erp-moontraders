{{--
    Settings -> Customers list (/settings/customers). Same layout as Settings -> Users:
    KPI cards, Active / Inactive tabs, one search box, removable filter chips and a
    dense sortable table. Query parameters (filter[...], sort, per_page) are unchanged.
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', 'customer_name');
    $tab = $filters['is_active'] ?? '';
    $chips = \Illuminate\Support\Arr::except($filters, 'is_active');
    $perPage = (int) request('per_page', 25);

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $key) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, 'is_active');
        if ($key !== '') {
            $f['is_active'] = $key;
        }

        return $withFilters($f);
    };
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === $field ? '-'.$field : $field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');

    $repNames = $salesRepOptions->pluck('name', 'id');
    $chipLabels = [
        'search' => 'Search', 'channel_type' => 'Channel', 'customer_category' => 'Category', 'sales_rep_id' => 'Sales rep',
        'city' => 'City', 'sub_locality' => 'Area', 'customer_name' => 'Name', 'customer_code' => 'Code', 'phone' => 'Phone',
    ];
    $chipValue = fn (string $key, $value) => match ($key) {
        'sales_rep_id' => $repNames[$value] ?? $value,
        'customer_category' => 'Category '.$value,
        default => $value,
    };
    $advancedCount = collect(['city', 'sub_locality', 'sales_rep_id', 'customer_category'])->filter(fn ($k) => isset($filters[$k]))->count();
    $sortNames = ['customer_name' => 'Name', 'customer_code' => 'Code', 'city' => 'City', 'credit_limit' => 'Credit limit'];
    $sortField = ltrim($sort, '-');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>Customers</span>
                </nav>
                <h1 class="ak-title">Customers</h1>
                <p class="ak-sub">Shops and trade customers you sell to.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('settings.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('customers.export.excel', request()->query()) }}" class="ak-btn ak-btn-outline">Export Excel</a>
                @can('customer-create')
                    <a href="{{ route('customers.import.form') }}" class="ak-btn ak-btn-outline">Import Excel</a>
                    <a href="{{ route('customers.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add customer</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page" x-data="customerList()">
        <x-status-message />

        <section class="ak-kpis" aria-label="Customer summary" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
            <a href="{{ $tabUrl('') }}" class="ak-kpi{{ $tab === '' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.35M3.75 21V9.35m0 0a3 3 0 0 0 3.75-.62A3 3 0 0 0 12 9.35a3 3 0 0 0 4.5-.62 3 3 0 0 0 3.75.62M3.75 9.35 5.25 3h13.5l1.5 6.35" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Customers</span>
                    <span class="ak-kpi-value">{{ number_format($stats['total']) }}</span>
                    <span class="ak-kpi-hint">{{ $chips ? 'matching the filters' : 'all shops' }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('1') }}" class="ak-kpi{{ $tab === '1' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Active</span>
                    <span class="ak-kpi-value">{{ number_format($stats['active']) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($stats['inactive']) }} inactive</span>
                </span>
            </a>
        </section>

        <section class="ak-card" aria-label="Customers">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $stats['total']], '1' => ['Active', $stats['active']], '0' => ['Inactive', $stats['inactive']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl((string) $key) }}" role="tab" aria-selected="{{ $tab === (string) $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === (string) $key ? 'is-active' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('customers.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[is_active]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="ak-filter-row ak-filter-row-users">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Name, code, phone, city or area" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_channel">Channel</label>
                        <select id="f_channel" name="filter[channel_type]">
                            <option value="">All channels</option>
                            @foreach ($channelTypes as $type)
                                <option value="{{ $type }}" @selected(($filters['channel_type'] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_category">Category</label>
                        <select id="f_category" name="filter[customer_category]">
                            <option value="">All</option>
                            @foreach ($customerCategories as $category)
                                <option value="{{ $category }}" @selected(($filters['customer_category'] ?? '') === $category)>Category {{ $category }}</option>
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
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="ak-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>

                <div id="ak-advanced" class="ak-filter-advanced ak-filter-advanced-users" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_city">City</label>
                        <input id="f_city" type="text" name="filter[city]" value="{{ $filters['city'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_area">Area / sub locality</label>
                        <input id="f_area" type="text" name="filter[sub_locality]" value="{{ $filters['sub_locality'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_rep">Sales rep</label>
                        <select id="f_rep" name="filter[sales_rep_id]">
                            <option value="">All reps</option>
                            @foreach ($salesRepOptions as $rep)
                                <option value="{{ $rep->id }}" @selected((string) ($filters['sales_rep_id'] ?? '') === (string) $rep->id)>{{ $rep->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters($tab !== '' ? ['is_active' => $tab] : []) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($customers->count() > 0)
                <div class="ak-table-wrap">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($customers->total()) }}</b> {{ \Illuminate\Support\Str::plural('customer', $customers->total()) }}
                            &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Name' }}</b> ({{ str_starts_with($sort, '-') ? 'Z–A / high first' : 'A–Z / low first' }})
                        </p>
                    </div>
                    <div class="ak-dt-scroll" tabindex="0" aria-label="Customers table">
                        <table class="ak-dt">
                            <caption class="sr-only">Customers, {{ $customers->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('customer_name') }}"><a href="{{ $sortUrl('customer_name') }}">Customer <span aria-hidden="true">{{ $sortMark('customer_name') }}</span></a></th>
                                    <th scope="col">Phone</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('city') }}"><a href="{{ $sortUrl('city') }}">Location <span aria-hidden="true">{{ $sortMark('city') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('channel_type') }}"><a href="{{ $sortUrl('channel_type') }}">Channel <span aria-hidden="true">{{ $sortMark('channel_type') }}</span></a></th>
                                    <th scope="col" class="ak-c" aria-sort="{{ $ariaSort('credit_limit') }}"><a href="{{ $sortUrl('credit_limit') }}">Credit limit <span aria-hidden="true">{{ $sortMark('credit_limit') }}</span></a></th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-c"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($customers as $customer)
                                    <tr>
                                        <td class="ak-c" data-label=""><span class="ak-muted">{{ $customers->firstItem() + $loop->index }}</span></td>
                                        <td data-label="Customer">
                                            <a href="{{ route('customers.show', $customer) }}" class="ak-primary-link">{{ $customer->customer_name }}</a>
                                            <div class="ak-muted ak-mono">{{ $customer->customer_code }}@if ($customer->business_name) · {{ $customer->business_name }}@endif</div>
                                        </td>
                                        <td data-label="Phone">{{ $customer->phone ?: '—' }}</td>
                                        <td data-label="Location">
                                            <div>{{ $customer->sub_locality ?: '—' }}</div>
                                            <div class="ak-muted">{{ $customer->city ?: '—' }}</div>
                                        </td>
                                        <td data-label="Channel">
                                            <div>{{ $customer->channel_type }}</div>
                                            <div class="ak-muted">Category {{ $customer->customer_category }}@if ($customer->salesRep) · {{ $customer->salesRep->name }}@endif</div>
                                        </td>
                                        <td class="ak-c ak-mono" data-label="Credit limit">{{ number_format((float) $customer->credit_limit, 0) }}</td>
                                        <td data-label="Status">
                                            @if ($customer->is_active)
                                                <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                                            @else
                                                <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Inactive</span>
                                            @endif
                                        </td>
                                        <td data-label="">
                                            <div class="ak-actions">
                                                <a href="{{ route('customers.show', $customer) }}" class="ak-icon ak-icon-view" title="View customer" aria-label="View {{ $customer->customer_name }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                </a>
                                                <span class="ak-slot">
                                                    @can('customer-edit')
                                                        <a href="{{ route('customers.edit', $customer) }}" class="ak-icon" title="Edit customer" aria-label="Edit {{ $customer->customer_name }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                        </a>
                                                    @endcan
                                                </span>
                                                <span class="ak-slot">
                                                    @can('customer-delete')
                                                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" @submit.prevent="askDelete($el, @js($customer->customer_name))">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="ak-icon ak-icon-danger" title="Delete customer" aria-label="Delete {{ $customer->customer_name }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                                                            </button>
                                                        </form>
                                                    @endcan
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
                    <p>Showing <b>{{ number_format($customers->firstItem()) }}–{{ number_format($customers->lastItem()) }}</b> of <b>{{ number_format($customers->total()) }}</b> customers</p>
                    <div class="ak-pager-right">{{ $customers->onEachSide(1)->links() }}</div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    @if ($filters)
                        <h2>No customers match these filters</h2>
                        <p>Remove a filter or search with part of a name or phone.</p>
                        <a href="{{ route('customers.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>
                    @else
                        <h2>No customers yet</h2>
                        <p>Add a customer, or import many shops at once from Excel.</p>
                        @can('customer-create') <a href="{{ route('customers.import.form') }}" class="ak-btn ak-btn-primary">Import Excel</a> @endcan
                    @endif
                </div>
            @endif
        </section>

        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="cl-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon ak-pill-red" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="cl-confirm-title" x-text="confirm.title"></h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155">The customer will be removed from the list. Existing sales history is kept.</p>
                    </div>
                </div>
                <div class="uf-modal-foot">
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="button" class="ak-btn ak-btn-danger-outline" @click="runConfirm()" :disabled="confirm.busy" x-text="confirm.busy ? 'Working…' : 'Delete customer'"></button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function customerList() {
                return {
                    confirm: { open: false, title: '', busy: false, form: null },
                    askDelete(form, name) { this.confirm = { open: true, title: 'Delete ' + name + '?', busy: false, form }; },
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
