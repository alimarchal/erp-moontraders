{{--
    Customer Balances (/reports/customer-balances): what each customer owes across all their salesman accounts, as on a
    date, with the last credit sale and the last recovery. Tabs pick who owes / paid in advance / settled; the name opens
    the customer, the balance opens the customer's account statements.
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = fn (string $key) => $withFilters(array_merge($filters, ['status' => $key]));
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === '-'.$field ? $field : '-'.$field, 'page' => null]);
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $names = ['supplier_id' => $suppliers->pluck('supplier_name', 'id'), 'employee_id' => $employees->pluck('name', 'id')];
    $chipLabels = ['search' => 'Search', 'supplier_id' => 'Supplier', 'employee_id' => 'Salesman', 'channel_type' => 'Channel',
        'city' => 'City', 'sub_locality' => 'Area', 'min_balance' => 'Owes at least', 'as_of' => 'As on'];
    $chips = \Illuminate\Support\Arr::except($filters, array_merge(['status'], $canViewAllSuppliers ? [] : ['supplier_id']));
    $chipValue = fn (string $key, $value) => match (true) {
        isset($names[$key]) => $names[$key][$value] ?? $value,
        $key === 'as_of' => \Carbon\Carbon::parse($value)->format('d M Y'),
        default => $value,
    };
    $asOfDate = \Carbon\Carbon::parse($asOf);
    $asOfLabel = $asOfDate->isToday() ? 'today' : $asOfDate->format('d M Y');
    $canCustomer = auth()->user()->can('customer-list');
    $canStatement = auth()->user()->can('report-audit-customer-account-statement');
    $statementUrl = fn ($customer) => route('reports.customer-account-statement.index', ['filter' => array_filter([
        'customer_id' => $customer->id, 'supplier_id' => $supplierId, 'employee_id' => $filters['employee_id'] ?? null, 'date_to' => $asOfDate->isToday() ? null : $asOf,
    ])]);
    $daysSince = fn (?string $date) => $date ? (int) \Carbon\Carbon::parse($date)->startOfDay()->diffInDays($asOfDate->copy()->startOfDay()) : null;
    $advancedCount = collect(['channel_type', 'city', 'sub_locality', 'min_balance'])->filter(fn ($k) => isset($filters[$k]))->count();
    $tabs = ['owes' => 'Owes money', 'advance' => 'Paid in advance', 'clear' => 'Settled', 'all' => 'All'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Customer Balances" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <x-customer-apple-style />

    <div class="ak-page">
        <div class="cs-print-head">SHAHZAIN TRADERS - MUZAFFARABAD<br>Customer Balances as on {{ $asOfDate->format('d M Y') }} · {{ $tabs[$status] ?? 'All' }}
            <small>{{ collect($chips)->map(fn ($v, $k) => ($chipLabels[$k] ?? $k).': '.$chipValue($k, $v))->implode(' · ') ?: 'All customers' }} · printed {{ now()->format('d.m.Y H:i') }} by {{ auth()->user()->name }}</small></div>

        <section class="ak-card cs-filters" aria-label="Filters" x-data="{ open: false }" @cs-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="display:none">
            <form method="GET" action="{{ route('reports.customer-balances.index') }}" class="ak-filters apl-fields"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                <input type="hidden" name="filter[status]" value="{{ $status }}">
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                <div class="cs-filter-row" style="--cs-cols: 3">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off" placeholder="Name, code, phone or area">
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_supplier">Supplier</label>
                        <select id="f_supplier" name="filter[supplier_id]" class="cs-select" data-placeholder="All suppliers">
                            @if ($canViewAllSuppliers)<option value=""></option>@endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) $supplierId === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_employee">Salesman</label>
                        <select id="f_employee" name="filter[employee_id]" class="cs-select" data-placeholder="All salesmen">
                            <option value=""></option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_as_of">Balance as on</label>
                        <input id="f_as_of" type="date" name="filter[as_of]" value="{{ $asOf }}" max="{{ now()->toDateString() }}">
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
                        <label for="f_channel">Channel</label>
                        <select id="f_channel" name="filter[channel_type]">
                            <option value="">All channels</option>
                            @foreach ($channelTypes as $type)
                                <option value="{{ $type }}" @selected(($filters['channel_type'] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_city">City</label>
                        <input id="f_city" type="text" name="filter[city]" value="{{ $filters['city'] ?? '' }}" placeholder="Any city">
                    </div>
                    <div class="ak-field">
                        <label for="f_area">Area</label>
                        <input id="f_area" type="text" name="filter[sub_locality]" value="{{ $filters['sub_locality'] ?? '' }}" placeholder="Any area">
                    </div>
                    <div class="ak-field">
                        <label for="f_min">Owes at least (Rs)</label>
                        <input id="f_min" type="number" step="1" name="filter[min_balance]" value="{{ $filters['min_balance'] ?? '' }}" placeholder="e.g. 10000">
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" name="per_page">
                            @foreach (\App\Http\Controllers\Reports\CustomerBalancesController::PER_PAGE as $size)
                                <option value="{{ $size }}" @selected((string) $perPage === (string) $size)>{{ $size === 'all' ? 'All' : $size }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <nav class="cs-ranges" aria-label="Quick filters">
                    <span>Owes at least</span>
                    @foreach ([10000, 50000, 100000] as $amount)
                        <a href="{{ $withFilters(array_merge($filters, ['min_balance' => (string) $amount, 'status' => 'owes'])) }}" @if (($filters['min_balance'] ?? '') === (string) $amount) aria-current="true" @endif>Rs {{ number_format($amount) }}</a>
                    @endforeach
                    <a href="{{ route('reports.customer-balances.index') }}" class="cs-clear">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-kpis no-print" aria-label="Totals" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
            <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Customers</span><span class="ak-kpi-value">{{ number_format($totals['customers']) }}</span><span class="ak-kpi-hint">{{ strtolower($tabs[$status] ?? 'all') }} · as on {{ $asOfLabel }}</span></span></div>
            <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Owed to us (Rs)</span><span class="ak-kpi-value cs-due">{{ number_format($totals['owed']) }}</span><span class="ak-kpi-hint">customers in debit</span></span></div>
            <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Paid in advance (Rs)</span><span class="ak-kpi-value cs-advance">{{ number_format(abs($totals['advance'])) }}</span><span class="ak-kpi-hint">customers in credit</span></span></div>
            <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Net receivable (Rs)</span><span class="ak-kpi-value">{{ number_format($totals['net']) }}</span><span class="ak-kpi-hint">owed − advance</span></span></div>
        </section>

        <section class="ak-card cs-card" aria-label="Customer balances">
            <nav class="ak-tabs" aria-label="Balance">
                @foreach ($tabs as $key => $label)
                    <a href="{{ $tabUrl($key) }}" @if ($status === $key) aria-current="page" @endif class="ak-tab {{ $status === $key ? 'is-active' : '' }}">{{ $label }}</a>
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
                    <a href="{{ $withFilters(['status' => $status]) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($customers->count() > 0)
                <div class="ak-table-wrap">
                    <div class="ak-dt-toolbar">
                        <p><b>{{ number_format($customers->total()) }}</b> {{ \Illuminate\Support\Str::plural('customer', $customers->total()) }} &middot; balance as on {{ $asOfLabel }}</p>
                    </div>
                    <div class="ak-dt-scroll" tabindex="0" aria-label="Customer balances table">
                        <table class="ak-dt cs-table">
                            <caption class="sr-only">Customer balances as on {{ $asOfDate->format('d M Y') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="cs-no">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('customer_name') }}"><a href="{{ $sortUrl('customer_name') }}">Customer <span aria-hidden="true">{{ $sortMark('customer_name') }}</span></a></th>
                                    <th scope="col">Area · channel</th>
                                    <th scope="col">Last sale</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('last_recovery') }}"><a href="{{ $sortUrl('last_recovery') }}" title="Sort by the last recovery">Last recovery <span aria-hidden="true">{{ $sortMark('last_recovery') }}</span></a></th>
                                    <th scope="col" class="cs-num">Credit limit</th>
                                    <th scope="col" class="cs-num" aria-sort="{{ $ariaSort('balance') }}"><a href="{{ $sortUrl('balance') }}">Balance (Rs) <span aria-hidden="true">{{ $sortMark('balance') }}</span></a></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($customers as $customer)
                                    @php
                                        $balance = (float) $customer->balance;
                                        $limit = (float) $customer->credit_limit;
                                        $recoveryDays = $daysSince($customer->last_recovery);
                                        $overLimit = $limit > 0 && $balance > $limit;
                                    @endphp
                                    <tr>
                                        <td class="cs-no" data-label="#">{{ $customers->firstItem() + $loop->index }}</td>
                                        <td class="cs-stack" data-label="Customer">
                                            @if ($canCustomer)
                                                <a href="{{ route('customers.show', $customer) }}" class="cs-name">{{ $customer->customer_name }}</a>
                                            @else
                                                <span class="cs-name">{{ $customer->customer_name }}</span>
                                            @endif
                                            <div class="ak-muted cs-sub"><span class="cs-code">{{ $customer->customer_code }}</span>@if ($customer->phone) · {{ $customer->phone }}@endif · {{ $customer->accounts }} {{ \Illuminate\Support\Str::plural('account', $customer->accounts) }}</div>
                                        </td>
                                        <td class="cs-stack" data-label="Area · channel">
                                            <div>{{ collect([$customer->sub_locality, $customer->city])->filter()->implode(' · ') ?: '—' }}</div>
                                            <div class="ak-muted cs-sub">{{ $customer->channel_type }}@if ($customer->salesRep) · {{ $customer->salesRep->name }}@endif</div>
                                        </td>
                                        <td data-label="Last sale" style="white-space:nowrap">{{ $customer->last_sale ? \Carbon\Carbon::parse($customer->last_sale)->format('d M Y') : '—' }}</td>
                                        <td class="cs-stack" data-label="Last recovery" style="white-space:nowrap">
                                            <div>{{ $customer->last_recovery ? \Carbon\Carbon::parse($customer->last_recovery)->format('d M Y') : '—' }}</div>
                                            @if ($balance > 0.004)
                                                <div class="cs-sub">
                                                    @if ($recoveryDays === null)
                                                        <span class="cs-cap cs-cap-red">never recovered</span>
                                                    @else
                                                        <span class="cs-cap {{ $recoveryDays > 30 ? 'cs-cap-red' : ($recoveryDays > 7 ? 'cs-cap-amber' : 'cs-cap-green') }}">{{ $recoveryDays === 0 ? 'today' : $recoveryDays.' '.\Illuminate\Support\Str::plural('day', $recoveryDays).' ago' }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="cs-num" data-label="Credit limit">
                                            {{ $limit > 0 ? number_format($limit) : '—' }}
                                            @if ($overLimit)<div class="cs-sub"><span class="cs-cap cs-cap-red" title="Balance is above the credit limit">over limit</span></div>@endif
                                        </td>
                                        <td class="cs-num" data-label="Balance (Rs)">
                                            @if ($canStatement)
                                                <a href="{{ $statementUrl($customer) }}" class="cs-amount-link {{ $balance > 0.004 ? 'cs-due' : ($balance < -0.004 ? 'cs-advance' : '') }}" title="Open the account statements">{{ number_format($balance, 2) }}</a>
                                            @else
                                                <b class="{{ $balance > 0.004 ? 'cs-due' : ($balance < -0.004 ? 'cs-advance' : '') }}">{{ number_format($balance, 2) }}</b>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="cs-no"></td>
                                    <td colspan="5">Total &middot; {{ number_format($totals['customers']) }} {{ \Illuminate\Support\Str::plural('customer', $totals['customers']) }}{{ $customers->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td class="cs-num" data-label="Net (Rs)">{{ number_format($totals['net'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @if ($customers->hasPages())
                    <div class="ak-pager no-print">
                        <p>Showing <b>{{ number_format($customers->firstItem()) }}–{{ number_format($customers->lastItem()) }}</b> of <b>{{ number_format($customers->total()) }}</b> customers</p>
                        <div class="ak-pager-right"><div>{{ $customers->onEachSide(1)->links() }}</div></div>
                    </div>
                @endif
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No customers {{ strtolower($tabs[$status] ?? '') }} as on {{ $asOfLabel }}{{ $chips ? ' with these filters' : '' }}</h2>
                    <p>Try the All tab, an earlier date, or remove a filter.</p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
