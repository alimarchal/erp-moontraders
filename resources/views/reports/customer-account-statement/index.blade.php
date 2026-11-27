{{--
    Customer Account Statement: every customer account a salesman holds (/reports/customer-account-statement).
    Same layout as Goods Issues: filters in a sheet opened from the header, removable chips, totals for the whole list,
    a table that becomes cards on a tablet or phone. The account number and the Statement button open one account.
    Query parameters (filter[...], per_page) are unchanged.
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $names = [
        'customer_id' => $customers->mapWithKeys(fn ($c) => [$c->id => $c->customer_name]),
        'supplier_id' => $suppliers->pluck('supplier_name', 'id'),
        'employee_id' => $employees->pluck('name', 'id'),
    ];
    $chipLabels = [
        'search' => 'Search', 'customer_id' => 'Customer', 'supplier_id' => 'Supplier', 'employee_id' => 'Salesman',
        'date_from' => 'From', 'date_to' => 'To', 'transaction_type' => 'Type', 'status' => 'Status',
        'balance_min' => 'Balance from', 'balance_max' => 'Balance up to',
    ];
    $chipValue = fn (string $key, $value) => match (true) {
        isset($names[$key]) => $names[$key][$value] ?? $value,
        in_array($key, ['date_from', 'date_to'], true) => \Carbon\Carbon::parse($value)->format('d M Y'),
        $key === 'transaction_type' => \Illuminate\Support\Str::headline($value),
        $key === 'status' => ucfirst($value),
        default => $value,
    };
    // A supplier forced by the user's own company is not a removable filter
    $chips = $canViewAllSuppliers ? $filters : \Illuminate\Support\Arr::except($filters, 'supplier_id');
    $advancedCount = collect(['date_from', 'date_to', 'transaction_type', 'status', 'balance_min', 'balance_max'])->filter(fn ($k) => isset($filters[$k]))->count();
    $canCustomer = auth()->user()->can('customer-list');
    $statusTone = ['active' => 'cs-cap-green', 'closed' => 'cs-cap-grey', 'suspended' => 'cs-cap-amber'];
    $balanceLabel = $isPeriod ? 'Net in period' : 'Balance';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Customer Account Statement" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <x-customer-apple-style />

    <div class="ak-page">
        <div class="cs-print-head">SHAHZAIN TRADERS - MUZAFFARABAD<br>Customer Account Statement · accounts
            <small>{{ collect($chips)->map(fn ($v, $k) => ($chipLabels[$k] ?? $k).': '.$chipValue($k, $v))->implode(' · ') ?: 'All accounts' }} · printed {{ now()->format('d.m.Y H:i') }} by {{ auth()->user()->name }}</small></div>

        <section class="ak-card cs-filters" aria-label="Filters" x-data="{ open: {{ $filters ? 'false' : 'true' }} }" @cs-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition>
            <form method="GET" action="{{ route('reports.customer-account-statement.index') }}" class="ak-filters apl-fields"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                <div class="cs-filter-row" style="--cs-cols: 3">
                    <div class="ak-field">
                        <label for="filter_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="filter_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off" placeholder="Customer, account no., phone or salesman">
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="filter_customer_id">Customer</label>
                        <select id="filter_customer_id" name="filter[customer_id]" class="cs-select" data-placeholder="All customers">
                            <option value=""></option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>{{ $customer->customer_name }} ({{ $customer->customer_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="filter_supplier_id">Supplier</label>
                        <select id="filter_supplier_id" name="filter[supplier_id]" class="cs-select" data-placeholder="All suppliers">
                            @if ($canViewAllSuppliers)<option value=""></option>@endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) $supplierIdFilter === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="filter_employee_id">Salesman</label>
                        <select id="filter_employee_id" name="filter[employee_id]" class="cs-select" data-placeholder="All salesmen">
                            <option value=""></option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->name }}</option>
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
                        <label for="filter_date_from">Entries from</label>
                        <input id="filter_date_from" type="date" name="filter[date_from]" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="filter_date_to">Entries to</label>
                        <input id="filter_date_to" type="date" name="filter[date_to]" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="filter_transaction_type">Entry type</label>
                        <select id="filter_transaction_type" name="filter[transaction_type]">
                            <option value="">All types</option>
                            @foreach ($transactionTypes as $type)
                                <option value="{{ $type }}" @selected(($filters['transaction_type'] ?? '') === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="filter_status">Account status</label>
                        <select id="filter_status" name="filter[status]">
                            <option value="">All statuses</option>
                            @foreach (['active', 'closed', 'suspended'] as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="filter_balance_min">Balance from (Rs)</label>
                        <input id="filter_balance_min" type="number" step="0.01" name="filter[balance_min]" value="{{ $filters['balance_min'] ?? '' }}" placeholder="e.g. 1">
                    </div>
                    <div class="ak-field">
                        <label for="filter_balance_max">Balance up to (Rs)</label>
                        <input id="filter_balance_max" type="number" step="0.01" name="filter[balance_max]" value="{{ $filters['balance_max'] ?? '' }}" placeholder="Any">
                    </div>
                    <div class="ak-field">
                        <label for="per_page">Rows</label>
                        <select id="per_page" name="per_page">
                            @foreach ([10, 25, 50, 100, 250, 'all'] as $size)
                                <option value="{{ $size }}" @selected((string) $perPage === (string) $size)>{{ $size === 'all' ? 'All' : $size }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <nav class="cs-ranges" aria-label="Quick filters">
                    <span>Quick</span>
                    <a href="{{ $withFilters(array_merge($filters, ['balance_min' => '1'])) }}" @if (($filters['balance_min'] ?? '') === '1' && ! isset($filters['balance_max'])) aria-current="true" @endif>Owes money</a>
                    <a href="{{ $withFilters(array_merge(\Illuminate\Support\Arr::except($filters, 'balance_min'), ['balance_max' => '-1'])) }}" @if (($filters['balance_max'] ?? '') === '-1') aria-current="true" @endif>Paid in advance</a>
                    <a href="{{ route('reports.customer-account-statement.index') }}" class="cs-clear">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-kpis no-print" aria-label="Totals" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
            <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Accounts</span><span class="ak-kpi-value">{{ number_format($accounts->total()) }}</span><span class="ak-kpi-hint">{{ $filters ? 'matching the filters' : 'every salesman account' }}</span></span></div>
            <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Debit (sales)</span><span class="ak-kpi-value">{{ number_format($totals['debits'], 2) }}</span><span class="ak-kpi-hint">{{ $isPeriod ? 'in the period' : 'all time' }}</span></span></div>
            <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">Credit (recoveries)</span><span class="ak-kpi-value">{{ number_format($totals['credits'], 2) }}</span><span class="ak-kpi-hint">{{ $isPeriod ? 'in the period' : 'all time' }}</span></span></div>
            <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ $balanceLabel }}</span><span class="ak-kpi-value {{ $totals['balance'] > 0 ? 'cs-due' : '' }}">{{ number_format($totals['balance'], 2) }}</span><span class="ak-kpi-hint">debit − credit</span></span></div>
        </section>

        <section class="ak-card cs-card" aria-label="Customer accounts">
            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ route('reports.customer-account-statement.index') }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($accounts->count() > 0)
                <div class="ak-table-wrap">
                    <div class="ak-dt-toolbar">
                        <p><b>{{ number_format($accounts->total()) }}</b> {{ \Illuminate\Support\Str::plural('account', $accounts->total()) }} &middot; highest {{ strtolower($balanceLabel) }} first</p>
                    </div>
                    <div class="ak-dt-scroll" tabindex="0" aria-label="Customer accounts table">
                        <table class="ak-dt cs-table">
                            <caption class="sr-only">Customer accounts, {{ $accounts->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="cs-no">#</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Salesman</th>
                                    <th scope="col" class="cs-num">Entries</th>
                                    <th scope="col" class="cs-num">Debit</th>
                                    <th scope="col" class="cs-num">Credit</th>
                                    <th scope="col" class="cs-num">{{ $balanceLabel }}</th>
                                    <th scope="col" class="ak-sticky-end no-print"><span class="sr-only">Statement</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accounts as $account)
                                    @php
                                        $debit = (float) ($account->total_debits ?? 0);
                                        $credit = (float) ($account->total_credits ?? 0);
                                        $balance = $debit - $credit;
                                        $statementUrl = route('reports.customer-account-statement.show', array_filter(['customerEmployeeAccount' => $account->id, 'filter' => \Illuminate\Support\Arr::only($filters, ['date_from', 'date_to', 'transaction_type']) ?: null]));
                                    @endphp
                                    <tr>
                                        <td class="cs-no" data-label="#">{{ $accounts->firstItem() + $loop->index }}</td>
                                        <td class="cs-stack" data-label="Customer">
                                            @if ($canCustomer && $account->customer)
                                                <a href="{{ route('customers.show', $account->customer) }}" class="cs-name" title="Open the customer">{{ $account->customer->customer_name }}</a>
                                            @else
                                                <span class="cs-name">{{ $account->customer?->customer_name ?? '—' }}</span>
                                            @endif
                                            <div class="ak-muted cs-sub"><a href="{{ $statementUrl }}" class="cs-link cs-code" title="Open the statement">{{ $account->account_number }}</a> · {{ $account->customer?->customer_code }}@if ($account->customer?->phone) · {{ $account->customer->phone }}@endif</div>
                                        </td>
                                        <td class="cs-stack" data-label="Salesman">
                                            <div>{{ $account->employee?->name ?? '—' }}</div>
                                            <div class="ak-muted cs-sub">{{ $account->employee?->supplier?->short_name ?: $account->employee?->supplier?->supplier_name }}
                                                @if ($account->status !== 'active') · <span class="cs-cap {{ $statusTone[$account->status] ?? 'cs-cap-grey' }}">{{ ucfirst($account->status) }}</span>@endif</div>
                                        </td>
                                        <td class="cs-num" data-label="Entries">{{ number_format($account->transactions_count) }}</td>
                                        <td class="cs-num" data-label="Debit">{{ number_format($debit, 2) }}</td>
                                        <td class="cs-num" data-label="Credit">{{ number_format($credit, 2) }}</td>
                                        <td class="cs-num" data-label="{{ $balanceLabel }}"><a href="{{ $statementUrl }}" class="cs-amount-link {{ $balance > 0.005 ? 'cs-due' : ($balance < -0.005 ? 'cs-advance' : '') }}">{{ number_format($balance, 2) }}</a></td>
                                        <td class="ak-sticky-end no-print" data-label="">
                                            <div class="cs-act">
                                                <a href="{{ $statementUrl }}" class="ak-btn ak-btn-outline ak-btn-sm" aria-label="Statement of {{ $account->account_number }}">Statement <span aria-hidden="true">→</span></a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="cs-no"></td>
                                    <td colspan="3">Total &middot; {{ number_format($accounts->total()) }} {{ \Illuminate\Support\Str::plural('account', $accounts->total()) }}{{ $accounts->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td class="cs-num" data-label="Debit">{{ number_format($totals['debits'], 2) }}</td>
                                    <td class="cs-num" data-label="Credit">{{ number_format($totals['credits'], 2) }}</td>
                                    <td class="cs-num" data-label="{{ $balanceLabel }}">{{ number_format($totals['balance'], 2) }}</td>
                                    <td class="no-print"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="ak-pager no-print">
                    <p>Showing <b>{{ number_format($accounts->firstItem()) }}–{{ number_format($accounts->lastItem()) }}</b> of <b>{{ number_format($accounts->total()) }}</b> accounts</p>
                    <div class="ak-pager-right"><div>{{ $accounts->onEachSide(1)->links() }}</div></div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No customer accounts{{ $filters ? ' match these filters' : ' yet' }}</h2>
                    <p>{{ $filters ? 'Remove a filter, or search with part of a name or account number.' : 'An account opens the first time a salesman sells to a customer on credit.' }}</p>
                    @if ($filters)<a href="{{ route('reports.customer-account-statement.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>@endif
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
