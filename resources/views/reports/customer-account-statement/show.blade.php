{{--
    One customer account with one salesman: every entry with its running balance (/reports/customer-account-statement/{id}).
    The customer, the salesman's other accounts and each sales settlement are links. Filters sit in a sheet opened from
    the header; quick periods set the dates. Query parameters (filter[...], per_page) are unchanged.
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $rangeUrl = fn (?string $from, ?string $to) => $withFilters(array_filter(array_merge($filters, ['date_from' => $from, 'date_to' => $to])));
    $today = now()->toDateString();
    $ranges = [
        'This month' => [now()->startOfMonth()->toDateString(), $today],
        'Last month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        'Last 90 days' => [now()->subDays(89)->toDateString(), $today],
        'This year' => [now()->startOfYear()->toDateString(), $today],
        'All time' => [null, null],
    ];
    $chipLabels = ['date_from' => 'From', 'date_to' => 'To', 'transaction_type' => 'Type', 'payment_method' => 'Paid by',
        'reference_number' => 'Reference', 'invoice_number' => 'Invoice', 'description' => 'Description', 'amount_min' => 'Amount from', 'amount_max' => 'Amount up to'];
    $chipValue = fn (string $key, $value) => match (true) {
        in_array($key, ['date_from', 'date_to'], true) => \Carbon\Carbon::parse($value)->format('d M Y'),
        in_array($key, ['transaction_type', 'payment_method'], true) => \Illuminate\Support\Str::headline($value),
        default => $value,
    };
    $advancedCount = collect(['payment_method', 'reference_number', 'invoice_number', 'description', 'amount_min', 'amount_max'])->filter(fn ($k) => isset($filters[$k]))->count();
    $period = ($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'Start').' – '.($dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : 'today');
    $customer = $account->customer;
    $canCustomer = auth()->user()->can('customer-list');
    $canSettlement = auth()->user()->can('sales-settlement-list');
    $typeTone = ['credit_sale' => 'cs-cap-blue', 'recovery' => 'cs-cap-green', 'opening_balance' => 'cs-cap-grey', 'adjustment' => 'cs-cap-amber'];
    $closing = $summary['closing_balance'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Statement · {{ $account->account_number }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.customer-account-statement.index" />
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <x-customer-apple-style />

    <div class="ak-page">
        <div class="cs-print-head">SHAHZAIN TRADERS - MUZAFFARABAD<br>Customer Account Statement<br>
            {{ $customer?->customer_name }} ({{ $customer?->customer_code }}) · {{ $account->account_number }}<br>
            Salesman: {{ $account->employee?->name }} · Supplier: {{ $account->employee?->supplier?->supplier_name }} · Period: {{ $period }}
            <small>Printed {{ now()->format('d.m.Y H:i') }} by {{ auth()->user()->name }}</small></div>

        <section class="ak-card cs-filters" aria-label="Filters" x-data="{ open: false }" @cs-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="display:none">
            <form method="GET" action="{{ route('reports.customer-account-statement.show', $account) }}" class="ak-filters apl-fields"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                <div class="cs-filter-row" style="--cs-cols: 3">
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
                        <label for="filter_date_from">From</label>
                        <input id="filter_date_from" type="date" name="filter[date_from]" value="{{ $dateFrom }}">
                    </div>
                    <div class="ak-field">
                        <label for="filter_date_to">To</label>
                        <input id="filter_date_to" type="date" name="filter[date_to]" value="{{ $dateTo }}">
                    </div>
                    <div class="ak-field">
                        <label for="per_page">Rows</label>
                        <select id="per_page" name="per_page">
                            @foreach ([10, 25, 50, 100, 250, 'all'] as $size)
                                <option value="{{ $size }}" @selected((string) $perPage === (string) $size)>{{ $size === 'all' ? 'All' : $size }}</option>
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
                        <label for="filter_payment_method">Paid by</label>
                        <select id="filter_payment_method" name="filter[payment_method]">
                            <option value="">Any method</option>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method }}" @selected(($filters['payment_method'] ?? '') === $method)>{{ \Illuminate\Support\Str::headline($method) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="filter_reference_number">Reference</label>
                        <input id="filter_reference_number" type="text" name="filter[reference_number]" value="{{ $filters['reference_number'] ?? '' }}" placeholder="Settlement or reference no.">
                    </div>
                    <div class="ak-field">
                        <label for="filter_invoice_number">Invoice</label>
                        <input id="filter_invoice_number" type="text" name="filter[invoice_number]" value="{{ $filters['invoice_number'] ?? '' }}" placeholder="Invoice no.">
                    </div>
                    <div class="ak-field">
                        <label for="filter_description">Description</label>
                        <input id="filter_description" type="text" name="filter[description]" value="{{ $filters['description'] ?? '' }}" placeholder="Words in the description">
                    </div>
                    <div class="ak-field">
                        <label for="filter_amount_min">Amount from (Rs)</label>
                        <input id="filter_amount_min" type="number" step="0.01" name="filter[amount_min]" value="{{ $filters['amount_min'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="filter_amount_max">Amount up to (Rs)</label>
                        <input id="filter_amount_max" type="number" step="0.01" name="filter[amount_max]" value="{{ $filters['amount_max'] ?? '' }}">
                    </div>
                </div>
            </form>
        </section>

        <section class="ak-card cs-hero" aria-labelledby="cs-account-name">
            <div class="cs-hero-top">
                <span class="cs-cap {{ $account->status === 'active' ? 'cs-cap-green' : 'cs-cap-grey' }}">{{ ucfirst($account->status) }} account</span>
                <span class="cs-cap cs-cap-blue cs-code">{{ $account->account_number }}</span>
                @if ($account->employee?->supplier)<span class="cs-cap cs-cap-grey">{{ $account->employee->supplier->supplier_name }}</span>@endif
            </div>
            <div>
                <h2 id="cs-account-name">
                    @if ($canCustomer && $customer)
                        <a href="{{ route('customers.show', $customer) }}" class="cs-name" style="font-size:inherit" title="Open the customer">{{ $customer->customer_name }}</a>
                    @else
                        {{ $customer?->customer_name ?? 'Unknown customer' }}
                    @endif
                </h2>
                <p>
                    <span class="cs-code">{{ $customer?->customer_code }}</span>
                    @if ($customer?->business_name && $customer->business_name !== $customer->customer_name) · {{ $customer->business_name }}@endif
                    · Salesman <b>{{ $account->employee?->name ?? '—' }}</b>
                    @if ($customer?->phone) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $customer->phone) }}" class="cs-link">{{ $customer->phone }}</a>@endif
                </p>
                <p style="margin-top:8px; display:flex; flex-wrap:wrap; gap:4px 16px">
                    <a href="{{ route('reports.customer-account-statement.index', ['filter' => ['customer_id' => $account->customer_id]]) }}" class="cs-link">This customer's other accounts <span aria-hidden="true">→</span></a>
                    @if ($account->employee_id)
                        <a href="{{ route('reports.customer-account-statement.index', ['filter' => ['employee_id' => $account->employee_id]]) }}" class="cs-link">All customers of {{ $account->employee?->name }} <span aria-hidden="true">→</span></a>
                    @endif
                </p>
            </div>
            <div class="cs-stats">
                <div class="cs-stat"><span>Opening balance</span><b>{{ number_format($summary['opening_balance'], 2) }}</b><small>before {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'the first entry' }}</small></div>
                <div class="cs-stat"><span>Debit (sales)</span><b>{{ number_format($summary['total_debits'], 2) }}</b><small>{{ $period }}</small></div>
                <div class="cs-stat"><span>Credit (recoveries)</span><b>{{ number_format($summary['total_credits'], 2) }}</b><small>{{ $period }}</small></div>
                <div class="cs-stat"><span>Closing balance</span><b class="{{ $closing > 0.005 ? 'cs-due' : ($closing < -0.005 ? 'cs-advance' : '') }}">{{ number_format($closing, 2) }}</b><small>{{ $closing > 0.005 ? 'the customer owes' : ($closing < -0.005 ? 'paid in advance' : 'nothing due') }}</small></div>
            </div>
            <nav class="cs-ranges no-print" aria-label="Quick periods" style="margin-top:0">
                <span>Period</span>
                @foreach ($ranges as $label => [$from, $to])
                    <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </section>

        <section class="ak-card cs-card" aria-label="Entries">
            @php $chips = \Illuminate\Support\Arr::except($filters, ['date_from', 'date_to']); @endphp
            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['date_from', 'date_to'])) }}" class="ak-chips-clear">Clear all</a>
                </div>
                <p class="ak-muted" style="margin:0; padding:8px 20px 0; font-size:12.5px">With these filters the balance column adds up only the entries shown.</p>
            @endif

            <div class="ak-table-wrap">
                <div class="ak-dt-toolbar">
                    <p><b>{{ number_format($entries->total()) }}</b> {{ \Illuminate\Support\Str::plural('entry', $entries->total()) }} &middot; {{ $period }} &middot; oldest first</p>
                </div>
                <div class="ak-dt-scroll" tabindex="0" aria-label="Statement entries">
                    <table class="ak-dt cs-table">
                        <caption class="sr-only">Statement of {{ $account->account_number }}, {{ $entries->total() }} entries</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="cs-no">#</th>
                                <th scope="col">Date</th>
                                <th scope="col">Entry</th>
                                <th scope="col">Details</th>
                                <th scope="col" class="cs-num">Debit</th>
                                <th scope="col" class="cs-num">Credit</th>
                                <th scope="col" class="cs-num">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($entries->currentPage() === 1)
                                <tr>
                                    <td class="cs-no"></td>
                                    <td colspan="5" data-label=""><b>Opening balance</b> <span class="ak-muted">· before {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'the first entry' }}</span></td>
                                    <td class="cs-num" data-label="Balance"><b>{{ number_format($summary['opening_balance'], 2) }}</b></td>
                                </tr>
                            @endif
                            @forelse ($entries as $entry)
                                @php $reference = $entry->reference_number ?: $entry->salesSettlement?->settlement_number; @endphp
                                <tr>
                                    <td class="cs-no" data-label="#">{{ $entries->firstItem() + $loop->index }}</td>
                                    <td data-label="Date" style="white-space:nowrap">{{ optional($entry->transaction_date)->format('d M Y') }}</td>
                                    <td class="cs-stack" data-label="Entry">
                                        <span class="cs-cap {{ $typeTone[$entry->transaction_type] ?? 'cs-cap-grey' }}">{{ \Illuminate\Support\Str::headline($entry->transaction_type) }}</span>
                                        @if ($entry->payment_method)<div class="ak-muted cs-sub">{{ \Illuminate\Support\Str::headline($entry->payment_method) }}@if ($entry->bankAccount) · {{ $entry->bankAccount->account_name }}@endif</div>@endif
                                    </td>
                                    <td class="cs-stack" data-label="Details">
                                        <div>
                                            @if ($entry->salesSettlement && $canSettlement)
                                                <a href="{{ route('sales-settlements.show', $entry->salesSettlement) }}" class="cs-link" title="Open the sales settlement">{{ $reference }}</a>
                                            @else
                                                {{ $reference ?: '—' }}
                                            @endif
                                            @if ($entry->invoice_number)<span class="ak-muted"> · invoice {{ $entry->invoice_number }}</span>@endif
                                        </div>
                                        @if ($entry->description)<div class="ak-muted cs-sub" style="max-width:420px">{{ $entry->description }}</div>@endif
                                    </td>
                                    <td class="cs-num" data-label="Debit">{{ (float) $entry->debit ? number_format((float) $entry->debit, 2) : '—' }}</td>
                                    <td class="cs-num" data-label="Credit">{{ (float) $entry->credit ? number_format((float) $entry->credit, 2) : '—' }}</td>
                                    <td class="cs-num" data-label="Balance"><b>{{ number_format((float) $entry->running_balance, 2) }}</b></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="ak-muted" style="padding:28px; text-align:center">No entries in {{ $period }}{{ $chips ? ' with these filters' : '' }}.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="cs-no"></td>
                                <td colspan="3">Period totals{{ $entries->hasPages() ? ' (all pages)' : '' }}</td>
                                <td class="cs-num" data-label="Debit">{{ number_format($summary['total_debits'], 2) }}</td>
                                <td class="cs-num" data-label="Credit">{{ number_format($summary['total_credits'], 2) }}</td>
                                <td class="cs-num" data-label="Closing">{{ number_format($closing, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @if ($entries->hasPages())
                <div class="ak-pager no-print">
                    <p>Showing <b>{{ number_format($entries->firstItem()) }}–{{ number_format($entries->lastItem()) }}</b> of <b>{{ number_format($entries->total()) }}</b> entries</p>
                    <div class="ak-pager-right"><div>{{ $entries->onEachSide(1)->links() }}</div></div>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
