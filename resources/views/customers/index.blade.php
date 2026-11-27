{{--
    Settings -> Customers list (/settings/customers). Same layout as Goods Issues: header buttons, a filter sheet
    opened from the header, sticky Active / Inactive tabs, removable chips, a compact table (View + more-actions
    menu) that becomes cards on a tablet or phone, and a pager. Query parameters (filter[...], sort, per_page) are unchanged.
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', 'customer_name');
    $tab = $filters['is_active'] ?? '';
    $chips = \Illuminate\Support\Arr::except($filters, 'is_active');

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
    $advancedCount = collect(['city', 'sub_locality', 'sales_rep_id'])->filter(fn ($k) => isset($filters[$k]))->count();
    $sortNames = ['customer_name' => 'Name', 'customer_code' => 'Code', 'city' => 'City', 'credit_limit' => 'Credit limit', 'channel_type' => 'Channel'];
    $sortField = ltrim($sort, '-');
    $authUser = auth()->user();
    $canToggle = $authUser->can('customer-edit');
    $canStatement = $authUser->can('report-audit-customer-account-statement');
    $headerButton = 'inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 no-print';
    $statementUrl = fn ($customer) => route('reports.customer-account-statement.index', ['filter' => ['customer_id' => $customer->id]]);
    $pageBalance = $customers->getCollection()->sum(fn ($c) => (float) $c->account_debits - (float) $c->account_credits);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Customers" :createRoute="route('customers.create')" createLabel="Add customer"
            createPermission="customer-create" :showSearch="true" :showRefresh="true" backRoute="settings.index">
            @if (\App\Http\Controllers\CustomerController::canViewHelp($authUser))
                <x-slot name="afterFilter">
                    <a href="{{ route('customers.help') }}" class="{{ $headerButton }}" title="How customers work, in Urdu" aria-label="How customers work">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                    </a>
                </x-slot>
            @endif
            <x-slot name="actions">
                <a href="{{ route('customers.export.excel', request()->query()) }}" class="{{ $headerButton }}" title="Download the filtered customers as Excel" aria-label="Export Excel">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                </a>
                @can('customer-create')
                    <a href="{{ route('customers.import.form') }}" class="{{ $headerButton }}" title="Import customers from Excel" aria-label="Import Excel">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    </a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <x-customer-apple-style />

    <div class="ak-page" x-data="customerList()">
        <div class="cs-print-head">{{ config('app.name') }} · Customers<small>Printed {{ now()->format('d.m.Y H:i') }} by {{ $authUser->name }}</small></div>
        <x-status-message />

        <section id="cs-filter-panel" class="ak-card cs-filters" aria-label="Filters" x-data="{ open: false }" @cs-toggle-filters.window="open = ! open" @cs-open-filters.window="open = true" x-show="open" x-cloak x-transition style="display:none">
            <form method="GET" action="{{ route('customers.index') }}" class="ak-filters apl-fields"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[is_active]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="cs-filter-row" style="--cs-cols: 3">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Name, code, phone or area" title="Searches name, code, phone, address, area, NTN and CNIC" x-ref="search">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_channel">Channel</label>
                        <select id="f_channel" name="filter[channel_type]" class="cs-select" data-placeholder="All channels">
                            <option value=""></option>
                            @foreach ($channelTypes as $type)
                                <option value="{{ $type }}" @selected(($filters['channel_type'] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_category">Category</label>
                        <select id="f_category" name="filter[customer_category]">
                            <option value="">All categories</option>
                            @foreach ($customerCategories as $category)
                                <option value="{{ $category }}" @selected(($filters['customer_category'] ?? '') === $category)>Category {{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach ($perPageOptions as $n)
                                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="cs-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>

                <div id="cs-advanced" class="cs-advanced" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_city">City</label>
                        <input id="f_city" type="text" name="filter[city]" value="{{ $filters['city'] ?? '' }}" placeholder="Any city">
                    </div>
                    <div class="ak-field">
                        <label for="f_area">Area / sub locality</label>
                        <input id="f_area" type="text" name="filter[sub_locality]" value="{{ $filters['sub_locality'] ?? '' }}" placeholder="Any area">
                    </div>
                    <div class="ak-field">
                        <label for="f_rep">Sales rep</label>
                        <select id="f_rep" name="filter[sales_rep_id]" class="cs-select" data-placeholder="All reps">
                            <option value=""></option>
                            @foreach ($salesRepOptions as $rep)
                                <option value="{{ $rep->id }}" @selected((string) ($filters['sales_rep_id'] ?? '') === (string) $rep->id)>{{ $rep->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <nav class="cs-ranges" aria-label="Clear filters">
                    <a href="{{ route('customers.index') }}" class="cs-clear" title="Remove every filter">&times; Clear all filters</a>
                </nav>
            </form>
        </section>

        <section class="ak-card cs-card" aria-label="Customers">
            <nav class="ak-tabs" aria-label="Customer status">
                @foreach (['' => ['All', $stats['total']], '1' => ['Active', $stats['active']], '0' => ['Inactive', $stats['inactive']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl((string) $key) }}" @if ($tab === (string) $key) aria-current="page" @endif class="ak-tab {{ $tab === (string) $key ? 'is-active' : '' }}">
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
                    <a href="{{ $withFilters($tab !== '' ? ['is_active' => $tab] : []) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($customers->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('customers-density') === 'compact'; } catch (e) { return false; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('customers-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($customers->total()) }}</b> {{ \Illuminate\Support\Str::plural('customer', $customers->total()) }}
                            &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Name' }}</b> ({{ str_starts_with($sort, '-') ? 'Z–A / high first' : 'A–Z / low first' }})
                        </p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Customers table">
                        <table class="ak-dt cs-table">
                            <caption class="sr-only">Customers, {{ $customers->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="cs-no">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('customer_name') }}"><a href="{{ $sortUrl('customer_name') }}">Customer <span aria-hidden="true">{{ $sortMark('customer_name') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('city') }}"><a href="{{ $sortUrl('city') }}">Area <span aria-hidden="true">{{ $sortMark('city') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('channel_type') }}"><a href="{{ $sortUrl('channel_type') }}">Channel <span aria-hidden="true">{{ $sortMark('channel_type') }}</span></a></th>
                                    <th scope="col" class="cs-num" aria-sort="{{ $ariaSort('credit_limit') }}"><a href="{{ $sortUrl('credit_limit') }}">Credit limit <span aria-hidden="true">{{ $sortMark('credit_limit') }}</span></a></th>
                                    <th scope="col" class="cs-num" title="Debit minus credit on all of the customer's salesman accounts">Balance (Rs)</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-sticky-end no-print"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($customers as $customer)
                                    @php $balance = (float) $customer->account_debits - (float) $customer->account_credits; @endphp
                                    <tr>
                                        <td class="cs-no" data-label="#">{{ $customers->firstItem() + $loop->index }}</td>
                                        <td class="cs-stack" data-label="Customer">
                                            <a href="{{ route('customers.show', $customer) }}" class="cs-name">{{ $customer->customer_name }}</a>
                                            <div class="ak-muted cs-sub"><span class="cs-code">{{ $customer->customer_code }}</span>@if ($customer->business_name && $customer->business_name !== $customer->customer_name) · {{ $customer->business_name }}@endif</div>
                                        </td>
                                        <td class="cs-stack" data-label="Area">
                                            <div>{{ collect([$customer->sub_locality, $customer->city])->filter()->implode(' · ') ?: '—' }}</div>
                                            @if ($customer->address)<div class="ak-muted cs-sub">{{ $customer->address }}</div>@endif
                                        </td>
                                        <td class="cs-stack" data-label="Channel">
                                            <div>{{ $customer->channel_type }}</div>
                                            <div class="ak-muted cs-sub">Category {{ $customer->customer_category }}@if ($customer->salesRep) · {{ $customer->salesRep->name }}@endif</div>
                                        </td>
                                        <td class="cs-num" data-label="Credit limit">{{ number_format((float) $customer->credit_limit) }}</td>
                                        <td class="cs-num" data-label="Balance (Rs)">
                                            @if (abs($balance) < 0.005)
                                                <span class="ak-muted">—</span>
                                            @elseif ($canStatement)
                                                <a href="{{ $statementUrl($customer) }}" class="cs-amount-link {{ $balance > 0 ? 'cs-due' : 'cs-advance' }}" title="Open the account statements of {{ $customer->customer_name }}">{{ number_format($balance) }}</a>
                                            @else
                                                <span class="{{ $balance > 0 ? 'cs-due' : 'cs-advance' }}">{{ number_format($balance) }}</span>
                                            @endif
                                        </td>
                                        <td data-label="Status">
                                            <span class="cs-cap {{ $customer->is_active ? 'cs-cap-green' : 'cs-cap-red' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span>
                                        </td>
                                        <td class="ak-sticky-end no-print" data-label="">
                                            <div class="cs-act" x-data="{ open: false, pos: '',
                                                toggle(e) {
                                                    const r = e.currentTarget.getBoundingClientRect();
                                                    const flip = r.bottom + 150 > window.innerHeight;
                                                    this.pos = 'right:' + (window.innerWidth - r.right) + 'px;' + (flip ? 'bottom:' + (window.innerHeight - r.top + 4) + 'px' : 'top:' + (r.bottom + 4) + 'px');
                                                    this.open = ! this.open;
                                                } }" @keydown.escape.window="open = false" @scroll.window="open = false" @resize.window="open = false">
                                                <a href="{{ route('customers.show', $customer) }}" class="ak-btn ak-btn-outline ak-btn-sm" aria-label="View {{ $customer->customer_name }}">
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    View
                                                </a>
                                                @if ($canToggle || $canStatement)
                                                    <button type="button" class="ak-icon cs-more" @click="toggle($event)" :aria-expanded="open" aria-haspopup="menu" title="More actions" aria-label="More actions for {{ $customer->customer_name }}">
                                                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="12" cy="19" r="1.7" /></svg>
                                                    </button>
                                                    <div class="cs-menu" role="menu" x-show="open" x-cloak style="display:none" :style="pos" @click.outside="open = false">
                                                        @if ($canStatement)
                                                            <a role="menuitem" href="{{ $statementUrl($customer) }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.63a3.38 3.38 0 0 0-3.38-3.37h-1.5A1.13 1.13 0 0 1 13.5 7.13v-1.5a3.38 3.38 0 0 0-3.38-3.38H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.63c-.62 0-1.13.5-1.13 1.13v17.25c0 .62.5 1.12 1.13 1.12h12.75c.62 0 1.12-.5 1.12-1.12V11.25a9 9 0 0 0-9-9Z" /></svg>
                                                                Account statements
                                                            </a>
                                                        @endif
                                                        @if ($canToggle)
                                                            <a role="menuitem" href="{{ route('customers.edit', $customer) }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                                Edit customer
                                                            </a>
                                                            <hr>
                                                            <form method="POST" action="{{ route('customers.toggle-active', $customer) }}" @submit.prevent="open = false; askToggle($el, @js($customer->customer_name), {{ $customer->is_active ? 'true' : 'false' }})">
                                                                @csrf
                                                                <button type="submit" role="menuitem" class="{{ $customer->is_active ? 'is-danger' : 'is-ok' }}">
                                                                    @if ($customer->is_active)
                                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.36 6.64A9 9 0 1 1 5.64 6.64M12 3v9" /></svg>
                                                                        Deactivate
                                                                    @else
                                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                                                        Activate
                                                                    @endif
                                                                </button>
                                                            </form>
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
                                    <td class="cs-no"></td>
                                    <td colspan="4">This page &middot; {{ number_format($customers->count()) }} {{ \Illuminate\Support\Str::plural('customer', $customers->count()) }}</td>
                                    <td class="cs-num" data-label="Balance (Rs)">{{ number_format($pageBalance) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($customers->firstItem()) }}–{{ number_format($customers->lastItem()) }}</b> of <b>{{ number_format($customers->total()) }}</b> customers</p>
                    <div class="ak-pager-right"><div>{{ $customers->onEachSide(1)->links() }}</div></div>
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
                    <span class="uf-modal-icon" :class="confirm.active ? 'ak-pill-red' : 'ak-tone-green'" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="cl-confirm-title" x-text="confirm.title"></h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155" x-text="confirm.active ? 'The customer will be marked Inactive and can be activated again from this list. Nothing is deleted.' : 'The customer will be marked Active again.'"></p>
                    </div>
                </div>
                <div class="uf-modal-foot">
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="button" class="ak-btn" :class="confirm.active ? 'ak-btn-danger-outline' : 'ak-btn-primary'" @click="runConfirm()" :disabled="confirm.busy" x-text="confirm.busy ? 'Working…' : (confirm.active ? 'Deactivate customer' : 'Activate customer')"></button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function customerList() {
                return {
                    confirm: { open: false, title: '', busy: false, form: null, active: true },
                    askToggle(form, name, isActive) { this.confirm = { open: true, title: (isActive ? 'Deactivate ' : 'Activate ') + name + '?', busy: false, form, active: isActive }; },
                    runConfirm() {
                        if (this.confirm.busy || !this.confirm.form) return;
                        this.confirm.busy = true;
                        this.confirm.form.submit();
                    },
                };
            }
            // "/" jumps to the search box: the filter sheet opens first
            window.addEventListener('keydown', (event) => {
                if (event.key !== '/' || ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
                event.preventDefault();
                window.dispatchEvent(new CustomEvent('cs-open-filters'));
                setTimeout(() => document.getElementById('f_search')?.focus(), 60);
            });
        </script>
    @endpush
</x-app-layout>
