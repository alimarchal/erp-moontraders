{{--
    Bank Register (/bank-statement-entries). Same layout as Settings > Users: KPI cards that double as filters,
    status tabs, one search box with "More filters", removable chips, a dense table, bulk actions and a pager.
    A transaction opens in a pop-up (data-entry); everything prints in black and white.
--}}
@php
    $filters = array_filter((array) $filters, fn ($v) => $v !== null && $v !== '' && $v !== []);
    $tab = $filters['status'] ?? '';
    $withFilters = fn (array $f) => route('bank-statement-entries.index', ['filter' => $f ?: null]);
    $tabUrl = fn (string $key) => $withFilters($key === '' ? \Illuminate\Support\Arr::except($filters, 'status') : array_merge($filters, ['status' => $key]));
    $onlyUrl = fn (array $f) => $withFilters(array_merge(\Illuminate\Support\Arr::except($filters, ['status', 'has_attachment']), $f));
    $tabLabels = [
        'unclassified' => 'To classify', 'classified' => 'Ready to post', 'pending_approval' => 'Waiting for approval',
        'rejected' => 'Rejected', 'posted' => 'Posted', 'reconciled' => 'Reconciled', 'reversed' => 'Reversed',
    ];
    $totalCount = array_sum(array_column($summary, 'count'));

    $accountNames = $bankAccounts->pluck('account_name', 'id');
    $companyNames = $companies->pluck('supplier_name', 'id');
    $categoryNames = $categories->pluck('name', 'id');
    $userNames = $users->pluck('name', 'id');
    $chipLabels = [
        'q' => 'Search', 'bank_account_id' => 'Account', 'direction' => 'Money', 'company_id' => 'Company', 'category_id' => 'Category',
        'date_from' => 'From', 'date_to' => 'To', 'amount_min' => 'Amount from', 'amount_max' => 'Amount to', 'has_attachment' => 'Receipt',
        'needs_review' => 'Review', 'submitted_by' => 'Maker', 'receipt_no' => 'Receipt no.', 'reference' => 'Reference', 'import_id' => 'Statement file',
    ];
    $chipValue = fn (string $key, $value) => match ($key) {
        'bank_account_id' => $accountNames[$value] ?? $value,
        'import_id' => $importNames[$value] ?? 'file #'.$value,
        'company_id' => $value === 'none' ? 'No company' : ($companyNames[$value] ?? $value),
        'category_id' => $value === 'none' ? 'No category' : ($categoryNames[$value] ?? $value),
        'submitted_by' => $userNames[$value] ?? $value,
        'direction' => $value === 'credit' ? 'Received' : 'Paid out',
        'has_attachment' => $value === 'yes' ? 'Has attachment' : 'No attachment',
        'needs_review' => $value === 'yes' ? 'Needs review' : 'Not flagged',
        default => $value,
    };
    $chips = \Illuminate\Support\Arr::except($filters, 'status');
    $advancedKeys = ['import_id', 'company_id', 'category_id', 'amount_min', 'amount_max', 'has_attachment', 'needs_review', 'submitted_by', 'receipt_no', 'reference'];
    $activeFilterCount = count($chips);
    $today = now();
    $ranges = [
        'Today' => [$today->toDateString(), $today->toDateString()],
        'Yesterday' => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
        'This week' => [$today->copy()->startOfWeek()->toDateString(), $today->toDateString()],
        'This month' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
        'Last month' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
    ];
    $rangeUrl = fn (string $from, string $to) => $withFilters(array_merge($filters, ['date_from' => $from, 'date_to' => $to]));
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $canApprove = auth()->user()->can('bank-statement-entry-approve');
    $canBulk = auth()->user()->can('bank-statement-entry-classify') || auth()->user()->can('bank-statement-entry-submit') || $canApprove;
    $importNames = $imports->pluck('file_name', 'id');
    $limits = config('bank.maker_checker');
    $canSubmit = auth()->user()->can('bank-statement-entry-submit');
    $canEditRows = auth()->user()->can('bank-statement-entry-edit');
    $pageIds = $canBulk ? $entries->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->values() : [];
    $net = $totals->total_in - $totals->total_out;
    $scope = $filters ? collect($filters)->map(fn ($v, $k) => ($chipLabels[$k] ?? \Illuminate\Support\Str::headline($k)).': '.$chipValue($k, $v))->implode(' · ') : 'All entries';
    if ($tab !== '') {
        $scope = (\App\Models\BankStatementEntry::STATUSES[$tab] ?? $tab).($filters && count($filters) > 1 ? ' · '.$scope : '');
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Bank Register" sub="Every rupee in and out of the bank, cash and wallet accounts, with its receipt.">
            <button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('bk-toggle-filters')" aria-controls="bk-filter-panel" title="Show or hide the filters">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                Filters @if ($activeFilterCount)<span class="ak-count ak-count-dark">{{ $activeFilterCount }}</span>@endif
            </button>
            @can('bank-statement-entry-create')
                <a href="{{ route('bank-statement-entries.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add entry</a>
            @endcan
            @can('bank-statement-import-create')
                <a href="{{ route('bank-statement-imports.create') }}" class="ak-btn ak-btn-outline">Import statement</a>
            @endcan
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16" x-data="bankRegister()">
        <x-bank.print-title title="Bank Register" :subtitle="$scope" />
        <x-status-message />
        @if ($errors->any())<div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>@endif

        <section class="apl-summary bank-noprint" aria-label="Totals for the entries listed">
            <div class="apl-stat"><p class="apl-stat-label"><i aria-hidden="true"></i>Entries</p><p class="apl-stat-value">{{ number_format($totals->total_count) }}</p></div>
            <div class="apl-stat is-in"><p class="apl-stat-label"><i aria-hidden="true"></i>Received</p><p class="apl-stat-value"><small>PKR</small>{{ number_format($totals->total_in, 2) }}</p></div>
            <div class="apl-stat is-out"><p class="apl-stat-label"><i aria-hidden="true"></i>Paid out</p><p class="apl-stat-value"><small>PKR</small>{{ number_format($totals->total_out, 2) }}</p></div>
            <div class="apl-stat is-net"><p class="apl-stat-label"><i aria-hidden="true"></i>Net</p><p class="apl-stat-value"><small>PKR</small>{{ $net < 0 ? '−' : '' }}{{ number_format(abs($net), 2) }}</p></div>
        </section>

        {{-- Filters: their own panel, opened with the Filters button, as on Expense Vouchers --}}
        <section id="bk-filter-panel" class="ak-card bank-noprint" aria-label="Filters" x-data="{ open: {{ $activeFilterCount ? 'true' : 'false' }} }" @bk-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $activeFilterCount ? '' : 'display:none' }}">
            <form method="GET" action="{{ route('bank-statement-entries.index') }}" class="ak-filters" x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '')<input type="hidden" name="filter[status]" value="{{ $tab }}">@endif
                <div class="bk-filter-row">
                    <div class="ak-field">
                        <label for="f_q">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_q" type="search" name="filter[q]" value="{{ $filters['q'] ?? '' }}" dir="auto" autocomplete="off" placeholder="Party, reference, receipt no., narration, file name">
                        </div>
                    </div>
                    <div class="ak-field"><label for="f_date_from">From</label><input id="f_date_from" type="date" name="filter[date_from]" value="{{ $filters['date_from'] ?? '' }}"></div>
                    <div class="ak-field"><label for="f_date_to">To</label><input id="f_date_to" type="date" name="filter[date_to]" value="{{ $filters['date_to'] ?? '' }}"></div>
                    <div class="ak-field">
                        <label for="f_bank_account_id">Account</label>
                        <select data-placeholder="All accounts" id="f_bank_account_id" name="filter[bank_account_id]" class="select2 bank-select">
                            <option value="">All accounts</option>
                            @foreach ($bankAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) ($filters['bank_account_id'] ?? '') === (string) $account->id)>{{ $account->account_name }}@if ($account->account_number) ({{ $account->account_number }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_direction">Money</label>
                        <select data-placeholder="In and out" id="f_direction" name="filter[direction]" class="select2 bank-select">
                            <option value="">In and out</option>
                            <option value="credit" @selected(($filters['direction'] ?? '') === 'credit')>Received</option>
                            <option value="debit" @selected(($filters['direction'] ?? '') === 'debit')>Paid out</option>
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="bk-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy"><span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span></button>
                    </div>
                </div>
                <div id="bk-advanced" class="bk-advanced" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field"><label for="f_company_id">Company / principal</label>
                        <select data-placeholder="All" id="f_company_id" name="filter[company_id]" class="select2 bank-select">
                            <option value="">All</option>
                            <option value="none" @selected(($filters['company_id'] ?? '') === 'none')>— no company —</option>
                            @foreach ($companies as $company)<option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->supplier_name }}</option>@endforeach
                        </select></div>
                    <div class="ak-field"><label for="f_category_id">Category</label>
                        <select data-placeholder="All" id="f_category_id" name="filter[category_id]" class="select2 bank-select">
                            <option value="">All</option>
                            <option value="none" @selected(($filters['category_id'] ?? '') === 'none')>— no category —</option>
                            @foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach
                        </select></div>
                    <div class="ak-field"><label for="f_amount_min">Amount from</label><input id="f_amount_min" type="number" step="0.01" min="0" name="filter[amount_min]" value="{{ $filters['amount_min'] ?? '' }}"></div>
                    <div class="ak-field"><label for="f_amount_max">Amount to</label><input id="f_amount_max" type="number" step="0.01" min="0" name="filter[amount_max]" value="{{ $filters['amount_max'] ?? '' }}"></div>
                    <div class="ak-field"><label for="f_reference">Reference</label><input id="f_reference" type="text" name="filter[reference]" value="{{ $filters['reference'] ?? '' }}"></div>
                    <div class="ak-field"><label for="f_receipt_no">Receipt no.</label><input id="f_receipt_no" type="text" name="filter[receipt_no]" value="{{ $filters['receipt_no'] ?? '' }}" placeholder="BR-2026-27-…"></div>
                    <div class="ak-field"><label for="f_has_attachment">Receipt file</label>
                        <select data-placeholder="Any" id="f_has_attachment" name="filter[has_attachment]" class="select2 bank-select">
                            <option value="">Any</option>
                            <option value="yes" @selected(($filters['has_attachment'] ?? '') === 'yes')>Has attachment</option>
                            <option value="no" @selected(($filters['has_attachment'] ?? '') === 'no')>No attachment</option>
                        </select></div>
                    <div class="ak-field"><label for="f_needs_review">Review flag</label>
                        <select data-placeholder="Any" id="f_needs_review" name="filter[needs_review]" class="select2 bank-select">
                            <option value="">Any</option>
                            <option value="yes" @selected(($filters['needs_review'] ?? '') === 'yes')>Needs review</option>
                            <option value="no" @selected(($filters['needs_review'] ?? '') === 'no')>Not flagged</option>
                        </select></div>
                    @if ($imports->isNotEmpty())
                        <div class="ak-field"><label for="f_import_id">Statement file</label>
                            <select data-placeholder="All files" id="f_import_id" name="filter[import_id]" class="select2 bank-select">
                                <option value="">All files</option>
                                @foreach ($imports as $import)<option value="{{ $import->id }}" @selected((string) ($filters['import_id'] ?? '') === (string) $import->id)>{{ $import->file_name }} · {{ $import->created_at->format('d-m-Y') }}</option>@endforeach
                            </select></div>
                    @endif
                    <div class="ak-field"><label for="f_submitted_by">Maker</label>
                        <select data-placeholder="Anyone" id="f_submitted_by" name="filter[submitted_by]" class="select2 bank-select">
                            <option value="">Anyone</option>
                            @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['submitted_by'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach
                        </select></div>
                </div>
                <nav class="bk-ranges" aria-label="Quick dates">
                    <span class="bk-ranges-label">Quick dates</span>
                    @foreach ($ranges as $label => [$from, $to])
                        <a href="{{ $rangeUrl($from, $to) }}" @if (($filters['date_from'] ?? '') === $from && ($filters['date_to'] ?? '') === $to) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                    <a href="{{ $withFilters($tab !== '' ? ['status' => $tab] : []) }}" class="bk-clear" title="Remove every filter">&times; Clear</a>
                </nav>
            </form>
        </section>

        <section class="ak-card" aria-label="Entries">
            <div class="ak-tabs">
                <div class="apl-seg" role="tablist">
                <a href="{{ $tabUrl('') }}" role="tab" aria-selected="{{ $tab === '' ? 'true' : 'false' }}" class="ak-tab {{ $tab === '' ? 'is-active' : '' }}">All <span class="ak-count">{{ number_format($totalCount) }}</span></a>
                {{-- Every step is always shown, in the order an entry moves through them, so the tabs never jump around. --}}
                @foreach ($tabLabels as $status => $label)
                    @php $tabCount = $summary[$status]['count'] ?? 0; @endphp
                    <a href="{{ $tabUrl($status) }}" role="tab" aria-selected="{{ $tab === $status ? 'true' : 'false' }}" class="ak-tab {{ $tab === $status ? 'is-active' : '' }} {{ $tabCount ? '' : 'is-empty' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($tabCount) }}</span>
                    </a>
                @endforeach
                </div>
                @if ($withoutReceipt || ($filters['has_attachment'] ?? '') === 'no')
                    <a href="{{ ($filters['has_attachment'] ?? '') === 'no' ? $withFilters(\Illuminate\Support\Arr::except($filters, 'has_attachment')) : $withFilters(array_merge($filters, ['has_attachment' => 'no'])) }}"
                        class="bk-tab-extra {{ ($filters['has_attachment'] ?? '') === 'no' ? 'is-active' : '' }}" title="Show only the entries that still have no receipt attached">No receipt {{ number_format($withoutReceipt) }}</a>
                @endif
            </div>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}"><b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span></a>
                    @endforeach
                    <a href="{{ $withFilters($tab !== '' ? ['status' => $tab] : []) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($entries->count() > 0)
                {{-- Bulk actions: the tick boxes point at this form with form="bulk-form" (no nested forms). --}}
                @if ($canBulk)
                    <form id="bulk-form" method="POST" action="{{ route('bank-statement-entries.bulk-classify') }}" class="ak-bulk bank-noprint" x-show="selected.length" x-cloak>
                        @csrf
                        <b x-text="selected.length + ' selected'"></b>
                        @can('bank-statement-entry-classify')
                            <select data-placeholder="Company…" name="company_id" class="select2 bank-select" data-width="220px" aria-label="Company for the selected entries">
                                <option value="">Company…</option>
                                @foreach ($companies as $company)<option value="{{ $company->id }}">{{ $company->supplier_name }}</option>@endforeach
                            </select>
                            <select data-placeholder="Category…" name="bank_entry_category_id" class="select2 bank-select" data-width="200px" aria-label="Category for the selected entries">
                                <option value="">Category…</option>
                                @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                            </select>
                            <label><input type="checkbox" name="remember_alias" value="1"> Remember this party next time</label>
                            <button type="submit" class="ak-btn ak-btn-primary ak-btn-sm">Apply</button>
                        @endcan
                        @can('bank-statement-entry-submit')
                            <button type="submit" formaction="{{ route('bank-statement-entries.bulk-submit') }}" class="ak-btn ak-btn-success ak-btn-sm">Submit selected</button>
                        @endcan
                        @if ($canApprove && $tab === 'pending_approval')
                            <button type="submit" formaction="{{ route('bank-approvals.bulk-approve') }}" class="ak-btn ak-btn-success ak-btn-sm">Approve selected</button>
                        @endif
                        <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="selected = []">Clear selection</button>
                    </form>
                @endif

                @if ($tab === 'pending_approval')
                    <div class="ak-alert bank-noprint" role="note" style="margin:12px 16px 0; background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a">
                        <b>Approval limits:</b>
                        @if ($limits['enabled'])
                            from PKR {{ number_format($limits['level1_threshold']) }} a checker must approve; from PKR {{ number_format($limits['level2_threshold']) }} a senior approver must approve as well.
                            @unless ($limits['allow_self_approval']) You cannot approve an entry you submitted yourself. @endunless Open the receipt before you approve.
                        @else
                            Maker-checker is switched off: entries are posted by the maker.
                        @endif
                    </div>
                @endif
                @if (! empty($filters['import_id']) && $imports->isNotEmpty())
                    <p class="ak-muted bank-noprint" style="margin:12px 16px 0">Showing the lines of one statement file. <a href="{{ route('bank-statement-imports.show', $filters['import_id']) }}" class="ak-primary-link">Open its import summary</a></p>
                @endif

                <div class="ak-table-wrap">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($totals->total_count) }}</b> {{ \Illuminate\Support\Str::plural('entry', $totals->total_count) }}
                            <span class="apl-dup">
                            &middot; received <b class="bk-in">{{ number_format($totals->total_in, 2) }}</b>
                            &middot; paid out <b class="bk-out">{{ number_format($totals->total_out, 2) }}</b>
                            &middot; net <b>{{ $net < 0 ? '−' : '' }}{{ number_format(abs($net), 2) }}</b>
                            </span>
                        </p>
                        @can('bank-statement-entry-classify')
                            <form method="POST" action="{{ route('bank-statement-entries.auto-classify') }}" class="bank-noprint">@csrf
                                <button class="ak-btn ak-btn-ghost ak-btn-sm" title="Run the aliases, rules and the cheque register over every unclassified entry">Auto-classify</button>
                            </form>
                        @endcan
                    </div>
                    <div class="ak-dt-scroll" tabindex="0" aria-label="Bank register table">
                        <table class="ak-dt">
                            <caption class="sr-only">Bank register, {{ $totals->total_count }} entries</caption>
                            <thead>
                                <tr>
                                    @if ($canBulk)<th scope="col" class="ak-c bank-noprint"><input type="checkbox" aria-label="Select all entries on this page" :checked="allSelected()" @change="toggleAll($event.target.checked)"></th>@endif
                                    <th scope="col">Date</th>
                                    <th scope="col">Party / company</th>
                                    <th scope="col">Category</th>
                                    <th scope="col" class="ak-num">Received</th>
                                    <th scope="col" class="ak-num">Paid out</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-c" title="Attachments">Files</th>
                                    <th scope="col" class="ak-c ak-sticky-end bank-noprint"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $entry)
                                    <tr class="bk-link-row" data-entry="{{ route('bank-statement-entries.drawer', $entry) }}">
                                        @if ($canBulk)<td class="ak-c bank-noprint" data-label=""><input type="checkbox" name="ids[]" value="{{ $entry->id }}" form="bulk-form" x-model="selected" aria-label="Select entry {{ $entry->id }}"></td>@endif
                                        <td class="ak-mono" data-label="Date" style="white-space:nowrap">{{ $entry->posting_date->format('d-m-Y') }}</td>
                                        <td data-label="Party">
                                            <a href="{{ route('bank-statement-entries.show', $entry) }}" data-entry="{{ route('bank-statement-entries.drawer', $entry) }}" class="ak-primary-link" dir="auto">{{ $entry->company?->supplier_name ?? $entry->party_name_in_statement ?? '—' }}</a>
                                            @php
                                                $subline = array_filter([
                                                    $entry->company && $entry->party_name_in_statement && mb_strtolower($entry->company->supplier_name) !== mb_strtolower($entry->party_name_in_statement) ? $entry->party_name_in_statement : null,
                                                    $entry->reference,
                                                ]);
                                            @endphp
                                            @if ($subline || $entry->receipt_no)
                                                <div class="ak-muted" dir="auto">{{ implode(' · ', $subline) }}@if ($entry->receipt_no)@if ($subline) · @endif<b>{{ $entry->receipt_no }}</b>@endif</div>
                                            @endif
                                        </td>
                                        <td data-label="Category">{{ $entry->category?->name ?? '—' }}</td>
                                        <td class="ak-num bk-in" data-label="Received">@if ((float) $entry->credit > 0){{ number_format($entry->credit, 2) }}@endif</td>
                                        <td class="ak-num bk-out" data-label="Paid out">@if ((float) $entry->debit > 0){{ number_format($entry->debit, 2) }}@endif</td>
                                        <td data-label="Status">
                                            <x-bank.status-badge :status="$entry->status" />
                                            @if ($entry->needs_review)<div class="ak-muted"><b>Review</b></div>@endif
                                            @if (isset($approvals[$entry->id]))
                                                @php($given = $approvals[$entry->id]['approved'])
                                                <div class="ak-muted">Checker: {{ $given->count() >= 1 ? '✓ '.$given->first()->user?->name : 'pending' }}@if ((int) $entry->approval_level_required === 2) · Senior: {{ $given->count() >= 2 ? '✓' : 'pending' }}@endif</div>
                                                <div class="ak-muted">by {{ $entry->submitter?->name }} {{ $entry->submitted_at?->diffForHumans() }}</div>
                                            @endif
                                        </td>
                                        <td class="ak-c" data-label="Files">
                                            @if ($entry->attachments_count)
                                                {{-- one file opens straight away in a new tab; several open the transaction, where each has its own view icon --}}
                                                @php($firstFile = $entry->attachments->first())
                                                <a class="bk-clip bank-noprint"
                                                    @if ($entry->attachments_count === 1 && $firstFile)
                                                        href="{{ route('bank-attachments.download', ['attachment' => $firstFile, 'inline' => 1]) }}" target="_blank" rel="noopener"
                                                        title="View {{ $firstFile->file_name }}" aria-label="View the attached file {{ $firstFile->file_name }}"
                                                    @else
                                                        href="{{ route('bank-statement-entries.show', $entry) }}" data-entry="{{ route('bank-statement-entries.drawer', $entry) }}"
                                                        title="{{ $entry->attachments_count }} files attached. Open the transaction to view them." aria-label="{{ $entry->attachments_count }} files attached to entry {{ $entry->id }}"
                                                    @endif>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32a1.5 1.5 0 0 1-2.122-2.122l7.693-7.693" /></svg>
                                                    <span>{{ $entry->attachments_count }}</span>
                                                </a>
                                                <span class="bank-printonly">{{ $entry->attachments_count }}</span>
                                            @elseif ($entry->status === 'pending_approval')
                                                <span class="ak-status ak-status-amber"><i aria-hidden="true"></i>No receipt</span>
                                            @elseif (in_array($entry->status, ['classified', 'posted'], true))
                                                <span class="ak-muted" title="No receipt attached yet">none</span>
                                            @endif
                                        </td>
                                        <td class="ak-sticky-end bank-noprint" data-label="">
                                            <div class="bk-row-actions">
                                                @if ($canSubmit && $entry->bank_entry_category_id && in_array($entry->status, ['classified', 'rejected', 'reversed'], true))
                                                    <form method="POST" action="{{ route('bank-statement-entries.submit', $entry) }}">@csrf
                                                        <button class="ak-btn ak-btn-success ak-btn-sm" title="Post this entry, or send it to a checker when it is above the approval limit">Post</button>
                                                    </form>
                                                @endif
                                                @if ($approvals[$entry->id]['can_approve'] ?? false)
                                                    <form method="POST" action="{{ route('bank-approvals.approve', $entry) }}">@csrf
                                                        <button class="ak-btn ak-btn-success ak-btn-sm" title="Approve this entry. The last approval posts it.">Approve</button>
                                                    </form>
                                                @endif
                                                @if ($canEditRows && ! $entry->isLocked())
                                                    <a href="{{ route('bank-statement-entries.edit', $entry) }}" class="ak-btn ak-btn-outline ak-btn-sm">{{ $entry->bank_entry_category_id ? 'Edit' : 'Classify' }}</a>
                                                @endif
                                                <a href="{{ route('bank-statement-entries.show', $entry) }}" data-entry="{{ route('bank-statement-entries.drawer', $entry) }}" class="ak-icon ak-icon-view" title="View transaction" aria-label="View transaction {{ $entry->id }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 14.75a2.75 2.75 0 1 0 0-5.5 2.75 2.75 0 0 0 0 5.5Z" /></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($entries->firstItem()) }}–{{ number_format($entries->lastItem()) }}</b> of <b>{{ number_format($entries->total()) }}</b> entries</p>
                    <div class="ak-pager-right"><div>{{ $entries->onEachSide(1)->links() }}</div></div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    @if ($filters)
                        <h2>No entries match these filters</h2>
                        <p>Remove a filter or search with part of a name or reference.</p>
                        <a href="{{ route('bank-statement-entries.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>
                    @else
                        <h2>No entries yet</h2>
                        <p>Add today's first entry, import a statement, or scan a receipt.</p>
                        @can('bank-statement-entry-create')<a href="{{ route('bank-statement-entries.create') }}" class="ak-btn ak-btn-primary">＋ Add entry</a>@endcan
                    @endif
                </div>
            @endif
        </section>
    </div>

    @push('scripts')
        <script>
            function bankRegister() {
                return {
                    selected: [],
                    pageIds: {{ \Illuminate\Support\Js::from($pageIds) }},
                    allSelected() { return this.pageIds.length > 0 && this.pageIds.every((id) => this.selected.includes(id)); },
                    toggleAll(on) { this.selected = on ? [...this.pageIds] : []; },
                };
            }
        </script>
    @endpush
</x-app-layout>
