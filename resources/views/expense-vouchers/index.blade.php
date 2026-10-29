{{--
    Expense Vouchers list (/expense-vouchers). Same layout as Goods Issues: status tabs, one search box
    with quick date ranges, searchable dropdown filters, removable chips, a sortable table with
    comfortable / compact density and a pager. No summary cards.
--}}
@php
    $filters = $filters ?? [];
    $today = now()->toDateString();
    $tab = $filters['status'] ?? '';
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
        'supplier_id' => $suppliers->pluck('supplier_name', 'id')->put('general', 'Moon Traders (general)'),
        'expense_account_id' => $accounts->mapWithKeys(fn ($a) => [$a->id => $a->account_code.' - '.$a->account_name]),
        'created_by' => $people->pluck('name', 'id'),
        'checked_by' => $people->pluck('name', 'id'),
    ];
    $chipLabels = [
        'search' => 'Search', 'supplier_id' => 'Company', 'expense_account_id' => 'Account', 'party_name' => 'Party', 'created_by' => 'Maker',
        'checked_by' => 'Checker', 'amount_min' => 'Amount from', 'amount_max' => 'Amount to',
    ];
    $chipValue = fn (string $key, $value) => isset($names[$key]) ? ($names[$key][$value] ?? $value) : $value;
    $chips = \Illuminate\Support\Arr::except($filters, ['status', 'date_from', 'date_to']);
    $advancedKeys = ['expense_account_id', 'party_name', 'created_by', 'checked_by', 'amount_min', 'amount_max'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $periodLabel = $dateFrom === $dateTo
        ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
        : \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y');
    $statusTone = ['draft' => 'ak-status-slate', 'pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'rejected' => 'ak-status-red'];
    $statusTotal = $statusCounts->sum();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><span>Expense Vouchers</span>
                </nav>
                <h1 class="ak-title">Expense Vouchers</h1>
                <p class="ak-sub">Daily expenses with a maker and a checker &middot; {{ $periodLabel }}</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('expense-vouchers.help') }}" class="ak-btn ak-btn-outline" title="How the voucher flow works and what it posts">How it works</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @can('expense-voucher-create')
                    <a href="{{ route('expense-vouchers.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> New voucher</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>
        @page { margin: 10mm; }
        .ev-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
        .ev-ranges-label { font-size: 12px; font-weight: 600; color: var(--ak-muted); margin-right: 4px; }
        .ev-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
        .ev-ranges a:hover { background: var(--ak-soft); }
        .ev-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
        .ev-filter-row { display: grid; gap: 12px; grid-template-columns: 1fr; align-items: end; }
        @media (min-width: 900px) { .ev-filter-row { grid-template-columns: minmax(220px, 2fr) 160px 160px minmax(170px, 1.3fr) 90px auto; } }
        .ev-filter-row .ak-field input, .ev-filter-row .ak-field select { height: 38px; box-sizing: border-box; }
        .ev-filter-row .ak-field input[type="date"] { padding: 0 10px; font-size: 14px; }
        .ev-filter-row .ak-filter-buttons { display: flex; gap: 8px; }
        .ev-advanced { display: grid; gap: 12px; grid-template-columns: 1fr; margin-top: 12px; }
        @media (min-width: 900px) { .ev-advanced { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .ev-advanced .ak-field input { height: 38px; box-sizing: border-box; }
        .ev-table { min-width: 980px; }
        .ev-table tfoot td { position: sticky; bottom: 0; font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
        .ak-status-slate { background: #f1f5f9; color: #475569; }
        .ak-status-slate i { background: #64748b; }
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
        .ak-filters .select2-container--focus .select2-selection--single, .ak-filters .select2-container--open .select2-selection--single { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
        .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
        .select2-search--dropdown .select2-search__field { border-radius: 6px; padding: 6px 8px; }
        @media print { .ev-ranges, .ak-filters, .ak-tabs { display: none !important; } .ev-table { min-width: 0 !important; } .ev-table tfoot td { position: static; } }
    </style>

    <div class="ak-page">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Expense Vouchers &middot; {{ $periodLabel }}</div>
        </div>

        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="ak-card" aria-label="Expense vouchers">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $statusTotal], 'draft' => ['Draft', $statusCounts['draft'] ?? 0], 'pending' => ['Pending approval', $statusCounts['pending'] ?? 0], 'approved' => ['Approved', $statusCounts['approved'] ?? 0], 'rejected' => ['Rejected', $statusCounts['rejected'] ?? 0]] as $key => [$label, $count])
                    <a href="{{ $tabUrl($key) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === $key ? 'is-active' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('expense-vouchers.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[status]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="ev-filter-row">
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
                        <select id="f_supplier" class="ev-select" name="filter[supplier_id]">
                            <option value="">All companies</option>
                            @if (auth()->user()->hasAllSupplierAccess())
                                <option value="general" @selected(($filters['supplier_id'] ?? '') === 'general')>Moon Traders (general)</option>
                            @endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
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

                <div id="ev-advanced" class="ev-advanced" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_account">Expense account</label>
                        <select id="f_account" class="ev-select" name="filter[expense_account_id]">
                            <option value="">All accounts</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) ($filters['expense_account_id'] ?? '') === (string) $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_party">Party</label>
                        <input id="f_party" type="text" name="filter[party_name]" value="{{ $filters['party_name'] ?? '' }}" list="party-suggestions" autocomplete="off" placeholder="Any party (part of the name)">
                        <datalist id="party-suggestions">
                            @foreach ($parties as $party)
                                <option value="{{ $party }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="ak-field">
                        <label for="f_maker">Maker</label>
                        <select id="f_maker" class="ev-select" name="filter[created_by]">
                            <option value="">Anyone</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected((string) ($filters['created_by'] ?? '') === (string) $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_checker">Checker</label>
                        <select id="f_checker" class="ev-select" name="filter[checked_by]">
                            <option value="">Anyone</option>
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
                <nav class="ev-ranges" aria-label="Quick dates">
                    <span class="ev-ranges-label">Quick dates</span>
                    @foreach ($ranges as $label => [$from, $to])
                        <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </form>

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

            @if ($vouchers->count() > 0)
                <div class="ak-table-wrap" x-data="{
                        compact: (() => { try { return localStorage.getItem('ev-density') !== 'comfortable'; } catch (e) { return true; } })(),
                        setDensity(v) { this.compact = v; try { localStorage.setItem('ev-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                    }">
                    <div class="ak-dt-toolbar">
                        <p><b>{{ number_format($vouchers->total()) }}</b> {{ \Illuminate\Support\Str::plural('voucher', $vouchers->total()) }} &middot; Rs <b>{{ number_format($totalAmount, 2) }}</b></p>
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
                                    <th scope="col" class="ak-c" style="width:48px">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('voucher_number') }}"><a href="{{ $sortUrl('voucher_number') }}">Voucher <span aria-hidden="true">{{ $sortMark('voucher_number') }}</span></a></th>
                                    <th scope="col" aria-sort="{{ $ariaSort('voucher_date') }}"><a href="{{ $sortUrl('voucher_date') }}">Date <span aria-hidden="true">{{ $sortMark('voucher_date') }}</span></a></th>
                                    <th scope="col">Party &middot; company</th>
                                    <th scope="col">Expense account</th>
                                    <th scope="col" class="ak-num" aria-sort="{{ $ariaSort('amount') }}"><a href="{{ $sortUrl('amount') }}">Amount (Rs) <span aria-hidden="true">{{ $sortMark('amount') }}</span></a></th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Maker &middot; checker</th>
                                    <th scope="col" class="ak-c ak-sticky-end"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vouchers as $voucher)
                                    <tr>
                                        <td class="ak-c ak-muted">{{ $vouchers->firstItem() + $loop->index }}</td>
                                        <td data-label="Voucher">
                                            <a href="{{ route('expense-vouchers.show', $voucher) }}" class="ak-primary-link">{{ $voucher->voucher_number }}</a>
                                            <div class="ak-muted ak-hide-compact">{{ $voucher->created_at?->diffForHumans() }}</div>
                                        </td>
                                        <td class="ak-mono" data-label="Date">{{ $voucher->voucher_date->format('d M Y') }}</td>
                                        <td data-label="Party · company">
                                            <span class="ak-strong">{{ $voucher->party_name }}</span>
                                            <div class="ak-muted">{{ $voucher->supplier?->short_name ?? $voucher->supplier?->supplier_name ?? 'Moon Traders' }}</div>
                                        </td>
                                        <td data-label="Expense account">{{ $voucher->expenseAccount?->account_name }}<div class="ak-muted ak-hide-compact">{{ $voucher->expenseAccount?->account_code }}</div></td>
                                        <td class="ak-num ak-strong" data-label="Amount (Rs)">{{ number_format((float) $voucher->amount, 2) }}</td>
                                        <td data-label="Status">
                                            <span class="ak-status {{ $statusTone[$voucher->status] ?? 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ $voucher->statusLabel() }}</span>
                                        </td>
                                        <td data-label="Maker · checker">
                                            {{ $voucher->creator?->name ?? '—' }}
                                            <div class="ak-muted">{{ $voucher->checker?->name ?? 'not checked' }}</div>
                                        </td>
                                        <td class="ak-sticky-end" data-label="">
                                            <div class="ak-actions">
                                                <a href="{{ route('expense-vouchers.show', $voucher) }}" class="ak-icon ak-icon-view" title="View" aria-label="View {{ $voucher->voucher_number }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                </a>
                                                <span class="ak-slot">
                                                    @if ($voucher->isEditable())
                                                        @can('expense-voucher-edit')
                                                            <a href="{{ route('expense-vouchers.edit', $voucher) }}" class="ak-icon" title="Edit" aria-label="Edit {{ $voucher->voucher_number }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                </span>
                                                <span class="ak-slot">
                                                    @if ($voucher->isApproved())
                                                        @can('expense-voucher-print')
                                                            <a href="{{ route('expense-vouchers.print', $voucher) }}" target="_blank" class="ak-icon" title="Print thermal slip" aria-label="Print {{ $voucher->voucher_number }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.83c-.24.03-.48.06-.72.1m.72-.1a42.4 42.4 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.17c.24.03.48.06.72.1m-.72-.1L17.66 18m0 0 .23 2.5a1.13 1.13 0 0 1-1.12 1.24H7.23a1.13 1.13 0 0 1-1.12-1.24L6.34 18m11.32 0h1.09A2.25 2.25 0 0 0 21 15.75V9.46c0-1.08-.77-2.01-1.84-2.18a48 48 0 0 0-1.41-.2M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.46c0-1.08.77-2.01 1.84-2.18.47-.07.94-.14 1.41-.2m0 0a48.6 48.6 0 0 1 11.5 0m-11.5 0V3.38c0-.62.5-1.13 1.13-1.13h9.24c.63 0 1.13.5 1.13 1.13v3.7M18 10.5h.01" /></svg>
                                                            </a>
                                                        @endcan
                                                    @endif
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5">Total &middot; {{ number_format($vouchers->total()) }} {{ \Illuminate\Support\Str::plural('voucher', $vouchers->total()) }}{{ $vouchers->hasPages() ? ' (all pages)' : '' }}</td>
                                    <td class="ak-num">{{ number_format($totalAmount, 2) }}</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($vouchers->firstItem()) }}–{{ number_format($vouchers->lastItem()) }}</b> of <b>{{ number_format($vouchers->total()) }}</b> vouchers</p>
                    <div class="ak-pager-right"><div>{{ $vouchers->onEachSide(1)->links() }}</div></div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    <h2>No vouchers for {{ $periodLabel }}{{ $chips || $tab ? ' with these filters' : '' }}</h2>
                    <p>Pick a wider date range above (for example “This month” or “All”), or remove a filter.</p>
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
            // Searchable dropdowns: type to find a company, account, party or person.
            $(function () {
                $('.ev-select').each(function () {
                    const $select = $(this);
                    $select.select2({
                        width: '100%',
                        placeholder: $select.find('option[value=""]').text() || 'Select',
                        allowClear: $select.find('option[value=""]').length > 0,
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
