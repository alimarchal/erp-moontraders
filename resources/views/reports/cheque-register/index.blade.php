{{--
    Cheque Register (/reports/cheque-register), built like Goods Issues: the page header with icon buttons, a filter
    panel with "More filters", status tabs with counts, removable chips, a table that fits its card from 1024px up
    (cards below that) with comfortable / compact density, and a pager. It opens empty: nothing loads until a company
    is chosen and Apply is pressed. Managers clear, bounce or cancel through the status sheet, or clear several
    pending cheques through the bulk bar. Urdu help is in the ? popup.
--}}
@php
    $canManage = auth()->user()->can('report-audit-cheque-register-manage');
    $tab = $filters['status'] ?? '';
    $tab = in_array($tab, \App\Http\Controllers\Reports\ChequeRegisterController::STATUSES, true) ? $tab : '';
    $today = now()->toDateString();
    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null, 'export' => null, 'format' => null]);
    $tabUrl = function (string $status) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, ['status']);
        if ($status !== '') {
            $f['status'] = $status;
        }

        return $withFilters($f);
    };
    $dateFrom = $filters['cheque_date_from'] ?? null;
    $dateTo = $filters['cheque_date_to'] ?? null;
    $rangeUrl = fn (?string $from, ?string $to) => $withFilters(array_filter(array_merge($filters, ['cheque_date_from' => $from, 'cheque_date_to' => $to])));
    $ranges = [
        'Today' => [$today, $today],
        'Yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
        'This week' => [now()->startOfWeek()->toDateString(), $today],
        'This month' => [now()->startOfMonth()->toDateString(), $today],
        'Last 30 days' => [now()->subDays(29)->toDateString(), $today],
        'All' => [null, null],
    ];
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === '-'.$field ? $field : '-'.$field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');
    // Engro Corporation Ltd -> Engro: the stored short name, else the name without company suffixes (as on Goods Issues).
    $shortSupplier = function (?string $short, ?string $name) {
        if (filled($short)) {
            return $short;
        }
        $cut = trim(preg_replace('/\b(pvt\.?|private|ltd\.?|limited|corporation|corp\.?|company|co\.?|pakistan|foods?)\b|\(.*?\)/i', '', (string) $name));
        $cut = trim(preg_replace('/\s+/', ' ', $cut), ' .-,');

        return \Illuminate\Support\Str::limit($cut !== '' ? $cut : (string) $name, 14, '…');
    };
    $displayName = fn (?string $name) => $name !== null && mb_strtoupper($name) === $name ? \Illuminate\Support\Str::title(mb_strtolower($name)) : $name;

    $names = [
        'employee_id' => $employees->pluck('name', 'id'),
        'customer_id' => $customers->pluck('customer_name', 'id'),
        'deposit_bank_id' => $depositBanks->pluck('account_name', 'id'),
        'cleared_via' => collect(['cash' => 'Cash back', 'bank' => 'By bank']),
    ];
    $chipLabels = [
        'cheque_number' => 'Cheque no.', 'employee_id' => 'Salesman', 'customer_id' => 'Customer', 'bank_name' => 'Bank', 'settlement_number' => 'Settlement',
        'entry_date_from' => 'Entry from', 'entry_date_to' => 'Entry to', 'cleared_date_from' => 'Cleared from', 'cleared_date_to' => 'Cleared to',
        'cleared_via' => 'Cleared via', 'deposit_bank_id' => 'Deposit bank', 'amount_min' => 'Amount from', 'amount_max' => 'Amount to',
    ];
    $chipValue = fn (string $key, $value) => isset($names[$key]) ? ($names[$key][$value] ?? $value) : $value;
    // Company, status and the cheque dates have their own controls, so they are not chips.
    $chips = \Illuminate\Support\Arr::except($filters, ['status', 'supplier_id', 'cheque_date_from', 'cheque_date_to']);
    $advancedKeys = ['employee_id', 'customer_id', 'bank_name', 'settlement_number', 'entry_date_from', 'entry_date_to', 'cleared_date_from', 'cleared_date_to', 'cleared_via', 'deposit_bank_id', 'amount_min', 'amount_max'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $companyLabel = match (true) {
        $selectedSupplier === 'all' => 'All companies',
        filled($selectedSupplier) => $suppliers->firstWhere('id', (int) $selectedSupplier)?->supplier_name ?? 'Company',
        default => null,
    };
    $periodLabel = match (true) {
        $dateFrom && $dateTo && $dateFrom === $dateTo => \Carbon\Carbon::parse($dateFrom)->format('d M Y'),
        $dateFrom && $dateTo => \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y'),
        (bool) $dateFrom => 'from '.\Carbon\Carbon::parse($dateFrom)->format('d M Y'),
        (bool) $dateTo => 'up to '.\Carbon\Carbon::parse($dateTo)->format('d M Y'),
        default => 'all cheque dates',
    };
    $statusTone = ['pending' => 'amber', 'cleared' => 'green', 'bounced' => 'red', 'cancelled' => 'grey'];
    $statusTabs = ['' => 'All', 'pending' => 'Pending', 'cleared' => 'Cleared', 'bounced' => 'Bounced', 'cancelled' => 'Cancelled'];
    $helpTopic = ! $isApplied ? 'start' : (['' => 'tabs', 'pending' => 'clear', 'cleared' => 'unclear', 'bounced' => 'bounce', 'cancelled' => 'tabs'][$tab] ?? 'tabs');
    $pageCheques = $cheques ? $cheques->getCollection() : collect();
    $pendingCheques = $pageCheques->where('status', 'pending')->mapWithKeys(fn ($cheque) => [$cheque->id => [
        'id' => $cheque->id,
        'number' => $cheque->cheque_number,
        'company' => $shortSupplier($cheque->supplier_short_name, $cheque->supplier_name),
        'supplierId' => (int) $cheque->supplier_id,
        'amount' => (float) $cheque->amount,
    ]]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cheque Register" :showSearch="true" :showRefresh="true" backRoute="reports.index">
            <x-slot name="afterFilter">
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: @js($helpTopic) })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How cheques are cleared, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
            <x-slot name="actions">
                <x-register-header-actions page="cheque-register" :help="false" :pdf-orientations="['landscape']"
                    :excel-url="request()->fullUrlWithQuery(['export' => 'xlsx', 'format' => null, 'page' => null])" />
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="cheque-register" />
    <x-apple-fields-style />
    @include('reports.cheque-register.partials.style')

    <div class="ak-page" x-data="chequeSelection()">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Cheque Register &middot; {{ $companyLabel ?? 'No company chosen' }} &middot; {{ $periodLabel }}</div>
        </div>

        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section id="cr-filter-panel" class="ak-card rg-gi-filters apl-fields" aria-label="Filters" x-data="{ open: {{ $isApplied ? 'false' : 'true' }} }" @cr-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $isApplied ? 'display:none' : '' }}">
            <form method="GET" action="{{ route('reports.cheque-register.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[status]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="gl-filter-row">
                    <div class="ak-field">
                        <label for="f_supplier">Company <span class="cr-required" aria-hidden="true">*</span></label>
                        <select id="f_supplier" class="gl-select" data-placeholder="Choose a company" name="filter[supplier_id]">
                            @if ($canViewAllSuppliers)
                                <option value=""></option>
                                <option value="all" @selected($selectedSupplier === 'all')>All companies</option>
                            @endif
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) $selectedSupplier === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_number">Cheque no.</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_number" type="search" name="filter[cheque_number]" value="{{ $filters['cheque_number'] ?? '' }}" autocomplete="off"
                                placeholder="Cheque number" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_from">Cheque date from</label>
                        <input id="f_from" type="date" name="filter[cheque_date_from]" value="{{ $dateFrom }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_to">Cheque date to</label>
                        <input id="f_to" type="date" name="filter[cheque_date_to]" value="{{ $dateTo }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows</label>
                        <select id="f_per_page" class="rg-select" name="per_page">
                            @foreach (\App\Http\Controllers\Reports\ChequeRegisterController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="cr-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>

                <div id="cr-advanced" class="gl-advanced" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_employee">Salesman</label>
                        <select id="f_employee" class="gl-select" data-placeholder="All salesmen" name="filter[employee_id]">
                            <option value=""></option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_customer">Customer</label>
                        <select id="f_customer" class="gl-select" data-placeholder="All customers" name="filter[customer_id]">
                            <option value=""></option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>{{ $customer->customer_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_bank">Cheque bank</label>
                        <select id="f_bank" class="gl-select" data-placeholder="Any bank" name="filter[bank_name]">
                            <option value=""></option>
                            @foreach ($bankNames as $bankName)
                                <option value="{{ $bankName }}" @selected(($filters['bank_name'] ?? '') === $bankName)>{{ $bankName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_settlement">Settlement no.</label>
                        <input id="f_settlement" type="search" name="filter[settlement_number]" value="{{ $filters['settlement_number'] ?? '' }}" autocomplete="off" placeholder="SS-…">
                    </div>
                    <div class="ak-field">
                        <label for="f_entry_from">Entry date from</label>
                        <input id="f_entry_from" type="date" name="filter[entry_date_from]" value="{{ $filters['entry_date_from'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_entry_to">Entry date to</label>
                        <input id="f_entry_to" type="date" name="filter[entry_date_to]" value="{{ $filters['entry_date_to'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_cleared_from">Cleared date from</label>
                        <input id="f_cleared_from" type="date" name="filter[cleared_date_from]" value="{{ $filters['cleared_date_from'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_cleared_to">Cleared date to</label>
                        <input id="f_cleared_to" type="date" name="filter[cleared_date_to]" value="{{ $filters['cleared_date_to'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_via">Cleared via</label>
                        <select id="f_via" class="rg-select" name="filter[cleared_via]">
                            <option value="">Cash or bank</option>
                            <option value="cash" @selected(($filters['cleared_via'] ?? '') === 'cash')>Cash back</option>
                            <option value="bank" @selected(($filters['cleared_via'] ?? '') === 'bank')>By bank</option>
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_deposit">Deposit bank</label>
                        <select id="f_deposit" class="gl-select" data-placeholder="Any deposit bank" name="filter[deposit_bank_id]">
                            <option value=""></option>
                            @foreach ($depositBanks as $bank)
                                <option value="{{ $bank->id }}" @selected((string) ($filters['deposit_bank_id'] ?? '') === (string) $bank->id)>{{ $bank->account_name }}{{ $bank->account_number ? ' · '.$bank->account_number : '' }}</option>
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
                @if ($isApplied)
                    <nav class="gl-ranges" aria-label="Quick cheque dates">
                        <span class="gl-ranges-label">Quick cheque dates</span>
                        @foreach ($ranges as $label => [$from, $to])
                            <a href="{{ $rangeUrl($from, $to) }}" @if ($dateFrom === $from && $dateTo === $to) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                        <a href="{{ route('reports.cheque-register.index') }}" class="gl-clear" title="Remove every filter and start again">&times; Clear</a>
                    </nav>
                @endif
            </form>
        </section>

        <section class="ak-card" aria-label="Cheques">
            @if (! $isApplied)
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" /></svg>
                    <h2>Choose a company and filters, then Apply</h2>
                    <p class="cr-empty-note">The register opens empty so it stays quick. Pick the company first{{ $canViewAllSuppliers ? ' (or All companies)' : '' }}, narrow it down if you like, and press Apply.</p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                        <button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('cr-toggle-filters')">Show filters</button>
                    </div>
                </div>
            @else
                <nav class="ak-tabs" aria-label="Cheque status">
                    @foreach ($statusTabs as $key => $label)
                        @php $tabStats = $stats[$key === '' ? 'all' : $key]; @endphp
                        <a href="{{ $tabUrl($key) }}" @if ($tab === $key) aria-current="page" @endif class="ak-tab {{ $tab === $key ? 'is-active' : '' }}{{ $key === 'pending' && $tabStats['count'] > 0 ? ' gl-tab-warn' : '' }}"
                            title="Rs {{ number_format($tabStats['amount'], 2) }}">
                            {{ $label }} <span class="ak-count">{{ number_format($tabStats['count']) }}</span>
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
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::only($filters, ['status', 'supplier_id', 'cheque_date_from', 'cheque_date_to'])) }}" class="ak-chips-clear">Clear all</a>
                    </div>
                @endif

                @if ($cheques->count() > 0)
                    <div class="ak-table-wrap" x-data="{
                            compact: (() => { try { return localStorage.getItem('cr-density') !== 'comfortable'; } catch (e) { return true; } })(),
                            setDensity(v) { this.compact = v; try { localStorage.setItem('cr-density', v ? 'compact' : 'comfortable'); } catch (e) {} }
                        }">
                        <div class="ak-dt-toolbar">
                            <p><b>{{ number_format($cheques->total()) }}</b> {{ \Illuminate\Support\Str::plural('cheque', $cheques->total()) }} &middot; Rs <b>{{ number_format($totalAmount, 2) }}</b> &middot; {{ $companyLabel }} &middot; {{ $periodLabel }}</p>
                            <div class="ak-seg ak-seg-sm" role="group" aria-label="Row density">
                                <button type="button" @click="setDensity(false)" :aria-pressed="!compact" :class="!compact && 'is-on'">Comfortable</button>
                                <button type="button" @click="setDensity(true)" :aria-pressed="compact" :class="compact && 'is-on'">Compact</button>
                            </div>
                        </div>
                        <div class="ak-dt-scroll" :class="compact && 'is-compact'" tabindex="0" aria-label="Cheques table">
                            <table class="ak-dt cr-table">
                                <caption class="sr-only">Cheques, {{ $cheques->total() }} results</caption>
                                <thead>
                                    <tr>
                                        <th scope="col" class="cr-c-no">#</th>
                                        @if ($canManage)
                                            <th scope="col" class="cr-c-check cr-check no-print">
                                                <input type="checkbox" aria-label="Select all pending cheques on this page" :checked="allPicked()" :indeterminate="picked.length > 0 && ! allPicked()" :disabled="pendingIds.length === 0" @change="toggleAll($event.target.checked)">
                                            </th>
                                        @endif
                                        <th scope="col" class="cr-c-cheque" aria-sort="{{ $ariaSort('cheque_date') }}">Cheque &middot; <a href="{{ $sortUrl('cheque_date') }}">Date <span aria-hidden="true">{{ $sortMark('cheque_date') }}</span></a></th>
                                        <th scope="col" class="cr-c-customer">Customer &middot; salesman</th>
                                        <th scope="col" class="cr-c-company">Company</th>
                                        <th scope="col" class="cr-c-bank">Bank &middot; settlement</th>
                                        <th scope="col" class="ak-num cr-c-amount" aria-sort="{{ $ariaSort('amount') }}"><a href="{{ $sortUrl('amount') }}">Amount (Rs) <span aria-hidden="true">{{ $sortMark('amount') }}</span></a></th>
                                        <th scope="col" class="cr-c-status" aria-sort="{{ $ariaSort('cleared_date') }}">Status &middot; <a href="{{ $sortUrl('cleared_date') }}">cleared <span aria-hidden="true">{{ $sortMark('cleared_date') }}</span></a></th>
                                        @if ($canManage)
                                            <th scope="col" class="ak-sticky-end no-print cr-c-act"><span class="sr-only">Action</span></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cheques as $cheque)
                                        @php
                                            $company = $shortSupplier($cheque->supplier_short_name, $cheque->supplier_name);
                                            $isCleared = $cheque->status === 'cleared';
                                            $clearedLine = $isCleared
                                                ? trim(($cheque->cleared_date?->format('d M Y') ?? '').' · '.match ($cheque->cleared_via) {
                                                    'bank' => 'By bank'.($cheque->deposit_bank_name ? ' · '.$cheque->deposit_bank_name : ''),
                                                    'cash' => 'Cash back',
                                                    default => 'Cleared',
                                                }, ' ·')
                                                : null;
                                            $partlyCleared = $isCleared && $cheque->cleared_amount !== null && (float) $cheque->cleared_amount !== (float) $cheque->amount;
                                        @endphp
                                        <tr class="{{ $cheque->status === 'pending' ? 'cr-pending' : '' }}" @if ($canManage && $cheque->status === 'pending') :class="isPicked({{ $cheque->id }}) && 'is-picked'" @endif>
                                            <td class="cr-no" data-label="#">{{ $cheques->firstItem() + $loop->index }}</td>
                                            @if ($canManage)
                                                <td class="cr-check no-print{{ $cheque->status === 'pending' ? '' : ' cr-check-none' }}" data-label="Select">
                                                    @if ($cheque->status === 'pending')
                                                        <input type="checkbox" value="{{ $cheque->id }}" x-model.number="picked" aria-label="Select cheque {{ $cheque->cheque_number }}">
                                                    @else
                                                        <span class="ak-muted" aria-hidden="true">—</span>
                                                    @endif
                                                </td>
                                            @endif
                                            <td data-label="Cheque">
                                                <span class="cr-line">
                                                    <a href="{{ route('sales-settlements.show', $cheque->sales_settlement_id) }}" target="_blank" class="ak-primary-link cr-num" title="Open settlement {{ $cheque->settlement_number }}">{{ $cheque->cheque_number }}</a>
                                                    <button type="button" class="cr-hist no-print" title="Status history" aria-label="Status history of cheque {{ $cheque->cheque_number }}"
                                                        data-history-url="{{ route('reports.cheque-register.history', $cheque->id) }}" @click="$dispatch('open-cheque-history-modal', { url: $el.dataset.historyUrl })">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    </button>
                                                </span>
                                                <div class="ak-muted cr-clamp" title="Entered {{ $cheque->created_at?->format('d M Y h:i A') }}">{{ $cheque->cheque_date?->format('d M Y') ?? '—' }}<span class="cr-hide-compact"> · in {{ $cheque->created_at?->format('d M') }}</span></div>
                                            </td>
                                            <td data-label="Customer · salesman">
                                                <span class="ak-strong cr-clamp" title="{{ $cheque->customer_name }}{{ $cheque->customer_address ? ' · '.$cheque->customer_address : '' }}">{{ $cheque->customer_name ?? '—' }}</span>
                                                <div class="ak-muted cr-clamp">{{ $displayName($cheque->employee_name) ?? '—' }}<span class="cr-hide-compact">{{ $cheque->customer_address ? ' · '.$cheque->customer_address : '' }}</span></div>
                                            </td>
                                            <td data-label="Company"><span class="cr-clamp" title="{{ $cheque->supplier_name }}">{{ $company }}</span></td>
                                            <td data-label="Bank · settlement">
                                                <span class="cr-clamp" title="{{ $cheque->bank_name }}">{{ $cheque->bank_name ?: '—' }}</span>
                                                <div class="ak-muted cr-clamp">{{ $cheque->settlement_number }}</div>
                                            </td>
                                            <td class="ak-num ak-strong" data-label="Amount (Rs)">{{ number_format((float) $cheque->amount, 2) }}</td>
                                            <td data-label="Status">
                                                <span class="gl-stage gl-stage-{{ $statusTone[$cheque->status] ?? 'grey' }}">{{ ucfirst($cheque->status) }}</span>
                                                @if ($clearedLine)
                                                    <span class="cr-sub" title="{{ $clearedLine }}">{{ $clearedLine }}</span>
                                                @endif
                                                @if ($partlyCleared)
                                                    <span class="cr-sub cr-sub-warn" title="Amount actually cleared">Rs {{ number_format((float) $cheque->cleared_amount, 2) }} cleared</span>
                                                @endif
                                            </td>
                                            @if ($canManage)
                                                <td class="ak-sticky-end no-print" data-label="">
                                                    <div class="gl-act">
                                                        <button type="button" class="ak-btn ak-btn-outline ak-btn-sm" title="Clear, bounce or change the status of {{ $cheque->cheque_number }}"
                                                            @click="$dispatch('open-cheque-status-modal', {
                                                                url: @js(route('reports.cheque-register.update-status', $cheque->id)),
                                                                status: @js($cheque->status),
                                                                number: @js($cheque->cheque_number),
                                                                company: @js($company),
                                                                clearedDate: @js($cheque->cleared_date?->format('Y-m-d')),
                                                                amount: @js((float) $cheque->amount),
                                                                via: @js($cheque->cleared_via ?? 'cash'),
                                                                bankId: @js((string) ($cheque->cleared_bank_account_id ?? '')),
                                                                chequeBankId: @js((string) ($cheque->bank_account_id ?? '')),
                                                            })">Change</button>
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="{{ $canManage ? 6 : 5 }}">Total &middot; {{ number_format($cheques->total()) }} {{ \Illuminate\Support\Str::plural('cheque', $cheques->total()) }}{{ $cheques->hasPages() ? ' (all pages)' : '' }}</td>
                                        <td class="ak-num">{{ number_format($totalAmount, 2) }}</td>
                                        <td colspan="{{ $canManage ? 2 : 1 }}"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="ak-pager">
                        <p>Showing <b>{{ number_format($cheques->firstItem()) }}–{{ number_format($cheques->lastItem()) }}</b> of <b>{{ number_format($cheques->total()) }}</b> cheques</p>
                        <div class="ak-pager-right"><div>{{ $cheques->onEachSide(1)->links() }}</div></div>
                    </div>
                @else
                    <div class="ak-empty">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                        <h2>No {{ $tab !== '' ? $tab.' ' : '' }}cheques for {{ $companyLabel }}{{ $chips || $dateFrom || $dateTo ? ' with these filters' : '' }}</h2>
                        <p>Pick another tab, a wider cheque date range (for example “All”), or remove a filter.</p>
                        <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap">
                            <a href="{{ $rangeUrl(null, null) }}" class="ak-btn ak-btn-outline">Show all cheque dates</a>
                        </div>
                    </div>
                @endif
            @endif
        </section>

        @if ($canManage && $isApplied)
            <div class="cr-bar no-print" x-show="picked.length > 0" x-cloak style="display:none"
                x-transition:enter="duration-200 [transition-timing-function:cubic-bezier(.23,1,.32,1)]" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" role="region" aria-label="Selected cheques">
                <span><b x-text="picked.length"></b> selected · Rs <b x-text="money(pickedTotal())"></b></span>
                <button type="button" class="cr-bar-link" @click="picked = []">Deselect</button>
                <button type="button" class="crx-btn crx-btn-primary" @click="$dispatch('open-cheque-status-modal', { bulk: true, cheques: pickedCheques(), url: @js(route('reports.cheque-register.bulk-clear')) })">Clear selected</button>
            </div>
        @endif
    </div>

    @if ($canManage && $isApplied)
        @include('reports.cheque-register.partials.status-modal')
    @endif
    @include('reports.cheque-register.partials.history-modal')

    @push('scripts')
        <script>
            // the header's filter button opens this page's own filter panel
            document.getElementById('toggle')?.addEventListener('click', (event) => { event.preventDefault(); window.dispatchEvent(new CustomEvent('cr-toggle-filters')); });

            function chequeSelection() {
                const pending = @js($pendingCheques);

                return {
                    picked: [],
                    pendingIds: Object.keys(pending).map(Number),
                    isPicked(id) { return this.picked.includes(id); },
                    allPicked() { return this.pendingIds.length > 0 && this.pendingIds.every((id) => this.picked.includes(id)); },
                    toggleAll(on) { this.picked = on ? [...this.pendingIds] : []; },
                    pickedCheques() { return this.picked.map((id) => pending[id]).filter(Boolean); },
                    pickedTotal() { return Math.round(this.picked.reduce((sum, id) => sum + (pending[id]?.amount || 0), 0) * 100) / 100; },
                    money(value) { return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                };
            }

            // Searchable dropdowns: type to find a company, salesman, customer or bank.
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
