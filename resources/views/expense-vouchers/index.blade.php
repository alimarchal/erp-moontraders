{{--
    Expense Vouchers list (/expense-vouchers), built like Goods Issues: the page header with icon buttons, a
    hidden-by-default filter panel, status tabs, removable chips, a table that fits its card from 1024px up
    (cards below that) with comfortable / compact density, and a pager. Urdu help is in the ? popup.
--}}
@php
    $filters = $filters ?? [];
    $opensOnPending = $opensOnPending ?? false;
    $today = now()->toDateString();
    $tab = ($filters['status'] ?? 'all') === 'all' ? '' : $filters['status'];
    // A bare visit opens on "Pending approval"; links built from it start from no filters at all.
    $linkFilters = $opensOnPending ? [] : $filters;
    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $status) use ($linkFilters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($linkFilters, ['status']);
        $f['status'] = $status === '' ? 'all' : $status;

        return $withFilters($f);
    };
    $rangeUrl = fn (string $from, string $to) => $withFilters(array_merge($linkFilters, ['date_from' => $from, 'date_to' => $to]));
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
        'owner' => $suppliers->mapWithKeys(fn ($s) => ['supplier:'.$s->id => $s->supplier_name])->union($companies->mapWithKeys(fn ($c) => ['company:'.$c->id => $c->company_name.' (general)'])),
        'expense_account_id' => $accounts->mapWithKeys(fn ($a) => [$a->id => $a->account_code.' - '.$a->account_name]),
        'category_id' => $categories->pluck('name', 'id'),
        'payment_type' => collect(\App\Models\ExpenseVoucher::paymentTypeLabels()),
        'payment_status' => collect(['unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid']),
        'created_by' => $people->pluck('name', 'id'),
        'checked_by' => $people->pluck('name', 'id'),
    ];
    $chipLabels = [
        'search' => 'Search', 'owner' => 'Company', 'expense_account_id' => 'Account', 'party_name' => 'Party', 'created_by' => 'Maker',
        'checked_by' => 'Checker', 'category_id' => 'Category', 'payment_type' => 'Payment', 'payment_status' => 'Credit status', 'amount_min' => 'Amount from', 'amount_max' => 'Amount to',
    ];
    $chipValue = fn (string $key, $value) => isset($names[$key]) ? ($names[$key][$value] ?? $value) : $value;
    $chips = \Illuminate\Support\Arr::except($filters, ['status', 'date_from', 'date_to']);
    $advancedKeys = ['expense_account_id', 'category_id', 'party_name', 'payment_type', 'payment_status', 'created_by', 'checked_by', 'amount_min', 'amount_max'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $activeFilterCount = count($chips) + (($filters['date_from'] ?? null) || ($filters['date_to'] ?? null) ? 1 : 0);
    $periodLabel = $opensOnPending ? 'all dates' : ($dateFrom === $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
        : \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y'));
    $statusTone = ['draft' => 'ak-status-slate', 'pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'rejected' => 'ak-status-red', 'reversed' => 'ak-status-red'];
    $statusTotal = $statusCounts->sum();
    $statusTabs = ['' => ['All', $statusTotal], 'draft' => ['Draft', $statusCounts['draft'] ?? 0], 'pending' => ['Pending approval', $statusCounts['pending'] ?? 0],
        'approved' => ['Approved', $statusCounts['approved'] ?? 0], 'rejected' => ['Rejected', $statusCounts['rejected'] ?? 0], 'reversed' => ['Reversed', $statusCounts['reversed'] ?? 0]];
    $helpTopic = ['' => 'statuses', 'draft' => 'statuses', 'pending' => 'approve', 'approved' => 'print', 'rejected' => 'statuses', 'reversed' => 'reverse'][$tab] ?? 'statuses';
    $methodLabels = \App\Models\ExpenseVoucher::paymentMethodLabels();
    $ownerShort = fn ($voucher) => $voucher->supplier?->short_name ?: ($voucher->supplier?->supplier_name ?? ($voucher->company?->abbr ?: ($voucher->company?->company_name ?? 'Moon Traders')));
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Expense Vouchers" :createRoute="route('expense-vouchers.create')" createLabel="New voucher"
            createPermission="expense-voucher-create" :showSearch="true" :showRefresh="true" backRoute="dashboard">
            <x-slot name="afterFilter">
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: @js($helpTopic) })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How vouchers work, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
            <x-slot name="actions">
                @include('expense-vouchers.partials.header-menu')
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="expense-vouchers" :has-supplier="true" />
    <x-apple-fields-style />
    @include('expense-vouchers.partials.list-style')

    <div class="ak-page">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Expense Vouchers &middot; {{ $periodLabel }}</div>
        </div>

        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section id="ev-filter-panel" class="ak-card rg-gi-filters apl-fields" aria-label="Filters" x-data="{ open: {{ $activeFilterCount ? 'true' : 'false' }} }" @ev-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $activeFilterCount ? '' : 'display:none' }}">
            <form method="GET" action="{{ route('expense-vouchers.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '' && ! $opensOnPending) <input type="hidden" name="filter[status]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="gl-filter-row">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Voucher no., party or narration" x-ref="search"
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
                        <select id="f_supplier" class="gl-select" data-placeholder="All companies" name="filter[owner]">
                            <option value=""></option>
                            @foreach ($companies as $company)
                                <option value="company:{{ $company->id }}" @selected(($filters['owner'] ?? '') === 'company:'.$company->id)>{{ $company->company_name }} (general)</option>
                            @endforeach
                            @foreach ($suppliers as $supplier)
                                <option value="supplier:{{ $supplier->id }}" @selected(($filters['owner'] ?? '') === 'supplier:'.$supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" class="rg-select" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\ExpenseVoucherController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="ev-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>

                <div id="ev-advanced" class="gl-advanced" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_account">Expense account</label>
                        <select id="f_account" class="gl-select" data-placeholder="All accounts" name="filter[expense_account_id]">
                            <option value=""></option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) ($filters['expense_account_id'] ?? '') === (string) $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_party">Party</label>
                        <select id="f_party" class="gl-select" data-placeholder="Any party" name="filter[party_name]">
                            <option value=""></option>
                            @foreach ($parties as $party)
                                <option value="{{ $party }}" @selected(($filters['party_name'] ?? '') === $party)>{{ $party }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_cat">Category</label>
                        <select id="f_cat" class="gl-select" data-placeholder="All categories" name="filter[category_id]">
                            <option value=""></option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_ptype">Payment</label>
                        <select id="f_ptype" class="rg-select" name="filter[payment_type]">
                            <option value="">Any</option>
                            @foreach (\App\Models\ExpenseVoucher::paymentTypeLabels() as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['payment_type'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_pstatus">Credit status</label>
                        <select id="f_pstatus" class="rg-select" name="filter[payment_status]">
                            <option value="">Any</option>
                            @foreach (['unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid'] as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['payment_status'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_maker">Maker</label>
                        <select id="f_maker" class="gl-select" data-placeholder="Anyone" name="filter[created_by]">
                            <option value=""></option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected((string) ($filters['created_by'] ?? '') === (string) $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_checker">Checker</label>
                        <select id="f_checker" class="gl-select" data-placeholder="Anyone" name="filter[checked_by]">
                            <option value=""></option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected((string) ($filters['checked_by'] ?? '') === (string) $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_min">Amount from</label>
                        <input id="f_min" type="number" step="0.01" min="0" name="filter[amount_min]" value="{{ $filters['amount_min'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_max">Amount to</label>
                        <input id="f_max" type="number" step="0.01" min="0" name="filter[amount_max]" value="{{ $filters['amount_max'] ?? '' }}">
                    </div>
                </div>
                <nav class="gl-ranges" aria-label="Quick dates">
                    <span class="gl-ranges-label">Quick dates</span>
                    @foreach ($ranges as $label => [$from, $to])
                        <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('expense-vouchers.index') }}" class="gl-clear" title="Remove every filter and go back to the vouchers waiting for approval">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-card" aria-label="Expense vouchers">
            <nav class="ak-tabs" aria-label="Voucher status">
                @foreach ($statusTabs as $key => [$label, $count])
                    <a href="{{ $tabUrl($key) }}" @if ($tab === $key) aria-current="page" @endif class="ak-tab {{ $tab === $key ? 'is-active' : '' }}{{ $key === 'pending' && $count > 0 ? ' gl-tab-warn' : '' }}">
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
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['status', 'date_from', 'date_to']) ?: ['status' => 'all']) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($vouchers->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('ev-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('ev-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p><b>{{ number_format($vouchers->total()) }}</b> {{ $opensOnPending ? \Illuminate\Support\Str::plural('voucher', $vouchers->total()).' waiting for approval' : \Illuminate\Support\Str::plural('voucher', $vouchers->total()) }} &middot; Rs <b>{{ number_format($totalAmount, 2) }}</b> &middot; {{ $periodLabel }}@if ($opensOnPending) &middot; <a href="{{ $tabUrl('') }}" class="ak-primary-link">Show all vouchers</a>@endif</p>
                        <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                            <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                            <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                        </div>
                    </div>
                    <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Expense vouchers table">
                        <table class="ak-dt ev-table">
                            <caption class="sr-only">Expense vouchers, {{ $vouchers->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ev-c-no">#</th>
                                    <th scope="col" class="ev-c-voucher" aria-sort="{{ $ariaSort(ltrim($sort, '-') === 'voucher_date' ? 'voucher_date' : 'voucher_number') }}"><a href="{{ $sortUrl('voucher_number') }}">Voucher <span aria-hidden="true">{{ $sortMark('voucher_number') }}</span></a> &middot; <a href="{{ $sortUrl('voucher_date') }}">Date <span aria-hidden="true">{{ $sortMark('voucher_date') }}</span></a></th>
                                    <th scope="col" class="ev-c-party">Paid to &middot; company</th>
                                    <th scope="col" class="ev-c-account">Account &middot; method</th>
                                    <th scope="col" class="ak-num ev-c-amount" aria-sort="{{ $ariaSort('amount') }}"><a href="{{ $sortUrl('amount') }}">Amount (Rs) <span aria-hidden="true">{{ $sortMark('amount') }}</span></a></th>
                                    <th scope="col" class="ev-c-status">Status &middot; maker / checker</th>
                                    <th scope="col" class="ak-sticky-end no-print ev-c-act"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vouchers as $voucher)
                                    <tr class="{{ $voucher->status === 'pending' ? 'ev-pending' : '' }}">
                                        <td class="ev-no" data-label="#">{{ $vouchers->firstItem() + $loop->index }}</td>
                                        <td data-label="Voucher">
                                            <span class="ev-vline"><a href="{{ route('expense-vouchers.show', $voucher) }}" class="ak-primary-link ev-num" title="{{ $voucher->voucher_number }}">{{ $voucher->voucher_number }}</a>@if($voucher->attachments->isNotEmpty()) <span class="ev-clip" title="Has an attachment" aria-label="Has an attachment">&#128206;</span>@endif</span>
                                            <div class="ak-muted ev-meta">{{ $voucher->voucher_date->format('d M Y') }}</div>
                                        </td>
                                        <td data-label="Paid to · company">
                                            <span class="ak-strong ev-clamp" title="{{ $voucher->party_name }}">{{ $voucher->party_name }}</span>
                                            <div class="ak-muted ev-clamp" title="{{ $voucher->supplier?->supplier_name ?? $voucher->company?->company_name }}">{{ $ownerShort($voucher) }}{{ $voucher->category ? ' · '.$voucher->category->name : '' }}</div>
                                        </td>
                                        <td data-label="Account · method">
                                            <span class="ev-clamp" title="{{ $voucher->expenseAccount?->account_code }} {{ $voucher->expenseAccount?->account_name }}">{{ $voucher->expenseAccount?->account_name }}</span>
                                            <div class="ak-muted ev-clamp">{{ $methodLabels[$voucher->payment_method] ?? $voucher->payment_method }}{{ $voucher->isAdvance() ? ' · advance' : '' }}{{ $voucher->isCredit() && $voucher->isApproved() ? ' · '.$voucher->payment_status : '' }}</div>
                                        </td>
                                        <td class="ak-num ak-strong" data-label="Amount (Rs)">{{ number_format((float) $voucher->amount, 2) }}</td>
                                        <td data-label="Status">
                                            <span class="gl-stage gl-stage-{{ ['draft' => 'grey', 'pending' => 'amber', 'approved' => 'green', 'rejected' => 'red', 'reversed' => 'red'][$voucher->status] ?? 'grey' }}">{{ $voucher->statusLabel() }}</span>
                                            <div class="ak-muted ev-clamp ev-people" title="Made by {{ $voucher->creator?->name ?? '—' }}{{ $voucher->checker ? ', checked by '.$voucher->checker->name : '' }}">{{ $voucher->creator?->name ?? '—' }} → {{ $voucher->checker?->name ?? 'not checked' }}</div>
                                        </td>
                                        <td class="ak-sticky-end no-print" data-label="">
                                            <div class="gl-act">
                                                <a href="{{ route('expense-vouchers.show', $voucher) }}" class="ak-btn ak-btn-outline ak-btn-sm gl-view" title="Open {{ $voucher->voucher_number }}" aria-label="View {{ $voucher->voucher_number }}">
                                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                    View
                                                </a>
                                                @if ($voucher->isApproved())
                                                    @can('expense-voucher-print')
                                                        <a href="{{ route('expense-vouchers.print', $voucher) }}" target="_blank" class="ak-icon gl-more" title="Print payment voucher (A4)" aria-label="Print {{ $voucher->voucher_number }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.83c-.24.03-.48.06-.72.1m.72-.1a42.4 42.4 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.17c.24.03.48.06.72.1m-.72-.1L17.66 18m0 0 .23 2.5a1.13 1.13 0 0 1-1.12 1.24H7.23a1.13 1.13 0 0 1-1.12-1.24L6.34 18m11.32 0h1.09A2.25 2.25 0 0 0 21 15.75V9.46c0-1.08-.77-2.01-1.84-2.18a48 48 0 0 0-1.41-.2M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.46c0-1.08.77-2.01 1.84-2.18.47-.07.94-.14 1.41-.2m0 0a48.6 48.6 0 0 1 11.5 0m-11.5 0V3.38c0-.62.5-1.13 1.13-1.13h9.24c.63 0 1.13.5 1.13 1.13v3.7M18 10.5h.01" /></svg>
                                                        </a>
                                                    @endcan
                                                @elseif ($voucher->isEditable() && (int) $voucher->created_by === auth()->id())
                                                    @can('expense-voucher-edit')
                                                        <a href="{{ route('expense-vouchers.edit', $voucher) }}" class="ak-icon gl-more" title="Edit" aria-label="Edit {{ $voucher->voucher_number }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                        </a>
                                                    @endcan
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4">Total &middot; {{ number_format($vouchers->total()) }} {{ \Illuminate\Support\Str::plural('voucher', $vouchers->total()) }}{{ $vouchers->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td class="ak-num">{{ number_format($totalAmount, 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($vouchers->firstItem()) }}–{{ number_format($vouchers->lastItem()) }}</b> of <b>{{ number_format($vouchers->total()) }}</b> vouchers</p>
                    <div class="ak-pager-right"><div>{{ $vouchers->onEachSide(1)->links() }}</div></div>
                </div>
            @elseif ($opensOnPending)
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <h2>Nothing is waiting for approval</h2>
                    <p>Every voucher has been checked. To find an older one, press <kbd>/</kbd> and search by voucher no., party or narration, or show all vouchers.</p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        <a href="{{ $tabUrl('') }}" class="ak-btn ak-btn-primary">Show all</a>
                        @can('expense-voucher-create') <a href="{{ route('expense-vouchers.create') }}" class="ak-btn ak-btn-ghost">＋ New voucher</a> @endcan
                    </div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No vouchers for {{ $periodLabel }}{{ $chips || $tab ? ' with these filters' : '' }}</h2>
                    <p>Pick a wider date range in the filters (for example “This month” or “All”), or remove a filter.</p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        <a href="{{ $rangeUrl(now()->startOfMonth()->toDateString(), $today) }}" class="ak-btn ak-btn-outline">Show this month</a>
                        @can('expense-voucher-create') <a href="{{ route('expense-vouchers.create') }}" class="ak-btn ak-btn-primary">＋ New voucher</a> @endcan
                    </div>
                </div>
            @endif
        </section>
    </div>

    @push('scripts')
        <script>
            // the header's filter button opens this page's own filter panel
            document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('ev-toggle-filters')); });
            // Searchable dropdowns: type to find a company, account, party or person.
            $(function () {
                $('.gl-select').each(function () {
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
