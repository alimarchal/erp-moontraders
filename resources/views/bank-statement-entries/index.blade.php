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
    $statusOrder = ['unclassified', 'classified', 'pending_approval', 'rejected', 'posted', 'reconciled', 'reversed'];
    $totalCount = array_sum(array_column($summary, 'count'));

    $accountNames = $bankAccounts->pluck('account_name', 'id');
    $companyNames = $companies->pluck('supplier_name', 'id');
    $categoryNames = $categories->pluck('name', 'id');
    $userNames = $users->pluck('name', 'id');
    $chipLabels = [
        'q' => 'Search', 'bank_account_id' => 'Account', 'direction' => 'Money', 'company_id' => 'Company', 'category_id' => 'Category',
        'date_from' => 'From', 'date_to' => 'To', 'amount_min' => 'Amount from', 'amount_max' => 'Amount to', 'has_attachment' => 'Receipt',
        'needs_review' => 'Review', 'submitted_by' => 'Maker', 'receipt_no' => 'Receipt no.', 'reference' => 'Reference', 'import_id' => 'Import',
    ];
    $chipValue = fn (string $key, $value) => match ($key) {
        'bank_account_id' => $accountNames[$value] ?? $value,
        'company_id' => $value === 'none' ? 'No company' : ($companyNames[$value] ?? $value),
        'category_id' => $value === 'none' ? 'No category' : ($categoryNames[$value] ?? $value),
        'submitted_by' => $userNames[$value] ?? $value,
        'direction' => $value === 'credit' ? 'Received' : 'Paid out',
        'has_attachment' => $value === 'yes' ? 'Has attachment' : 'No attachment',
        'needs_review' => $value === 'yes' ? 'Needs review' : 'Not flagged',
        default => $value,
    };
    $chips = \Illuminate\Support\Arr::except($filters, 'status');
    $advancedKeys = ['company_id', 'category_id', 'date_from', 'date_to', 'amount_min', 'amount_max', 'has_attachment', 'needs_review', 'submitted_by', 'receipt_no', 'reference'];
    $advancedCount = collect($advancedKeys)->filter(fn ($k) => isset($filters[$k]))->count();
    $canBulk = auth()->user()->can('bank-statement-entry-classify') || auth()->user()->can('bank-statement-entry-submit');
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
            @can('bank-statement-entry-create')
                <a href="{{ route('bank-statement-entries.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add entry</a>
            @endcan
            @can('bank-statement-import-create')
                <a href="{{ route('bank-statement-imports.create') }}" class="ak-btn ak-btn-outline">Import statement</a>
            @endcan
            @can('bank-statement-entry-export')
                <a href="{{ route('bank-statement-entries.export', array_filter(['filter' => $filters])) }}" class="ak-btn ak-btn-outline">Export</a>
            @endcan
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16" x-data="bankRegister()">
        <x-bank.subnav inside />
        <x-bank.print-title title="Bank Register" :subtitle="$scope" />
        <x-status-message />
        @if ($errors->any())<div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>@endif

        {{-- KPI cards (also filters) --}}
        <section class="ak-kpis" aria-label="Work to do">
            @php
                $kpis = [
                    ['To classify', $tiles['unclassified'], 'Needs a company and a category', 'unclassified', 'ak-tone-slate', 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z'],
                    ['Waiting for approval', $tiles['pending'], 'With a checker or senior approver', 'pending_approval', 'ak-tone-amber', 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                    ['Posted', $tiles['unreconciled'], 'In the books, not yet reconciled', 'posted', 'ak-tone-green', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ];
            @endphp
            @foreach ($kpis as [$label, $tile, $hint, $status, $tone, $icon])
                <a href="{{ $tab === $status ? $tabUrl('') : $tabUrl($status) }}" class="ak-kpi{{ $tab === $status ? ' is-active' : '' }}">
                    <span class="ak-kpi-icon {{ $tone }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg></span>
                    <span class="ak-kpi-body">
                        <span class="ak-kpi-label">{{ $label }}</span>
                        <span class="ak-kpi-value">{{ number_format($tile['count']) }}</span>
                        <span class="ak-kpi-hint">PKR {{ number_format($tile['amount'], 2) }} · {{ $hint }}</span>
                    </span>
                </a>
            @endforeach
            <a href="{{ ($filters['has_attachment'] ?? '') === 'no' ? $withFilters(\Illuminate\Support\Arr::except($filters, 'has_attachment')) : $withFilters(array_merge($filters, ['has_attachment' => 'no'])) }}"
                class="ak-kpi{{ $withoutReceipt ? ' ak-kpi-action' : '' }}{{ ($filters['has_attachment'] ?? '') === 'no' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">No receipt</span>
                    <span class="ak-kpi-value">{{ number_format($withoutReceipt) }}</span>
                    <span class="ak-kpi-hint">{{ $withoutReceipt ? 'Entries still missing a receipt →' : 'Every entry has a receipt' }}</span>
                </span>
            </a>
        </section>

        <section class="ak-card" aria-label="Entries">
            <div class="ak-tabs" role="tablist">
                <a href="{{ $tabUrl('') }}" role="tab" aria-selected="{{ $tab === '' ? 'true' : 'false' }}" class="ak-tab {{ $tab === '' ? 'is-active' : '' }}">All <span class="ak-count">{{ number_format($totalCount) }}</span></a>
                @foreach ($statusOrder as $status)
                    @if (isset($summary[$status]))
                        <a href="{{ $tabUrl($status) }}" role="tab" aria-selected="{{ $tab === $status ? 'true' : 'false' }}" class="ak-tab {{ $tab === $status ? 'is-active' : '' }}">
                            {{ \App\Models\BankStatementEntry::STATUSES[$status] }} <span class="ak-count">{{ number_format($summary[$status]['count']) }}</span>
                        </a>
                    @endif
                @endforeach
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('bank-statement-entries.index') }}" class="ak-filters" x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '')<input type="hidden" name="filter[status]" value="{{ $tab }}">@endif
                @if (! empty($filters['import_id']))<input type="hidden" name="filter[import_id]" value="{{ $filters['import_id'] }}">@endif
                <div class="ak-filter-row">
                    <div class="ak-field">
                        <label for="f_q">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_q" type="search" name="filter[q]" value="{{ $filters['q'] ?? '' }}" dir="auto" autocomplete="off" placeholder="Party, reference, receipt no., narration, file name">
                        </div>
                    </div>
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
                <div id="bk-advanced" class="ak-filter-advanced" x-show="advanced" x-cloak x-transition style="margin-top:14px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr))">
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
                    <div class="ak-field"><label for="f_date_from">Date from</label><input id="f_date_from" type="date" name="filter[date_from]" value="{{ $filters['date_from'] ?? '' }}"></div>
                    <div class="ak-field"><label for="f_date_to">Date to</label><input id="f_date_to" type="date" name="filter[date_to]" value="{{ $filters['date_to'] ?? '' }}"></div>
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
                    <div class="ak-field"><label for="f_submitted_by">Maker</label>
                        <select data-placeholder="Anyone" id="f_submitted_by" name="filter[submitted_by]" class="select2 bank-select">
                            <option value="">Anyone</option>
                            @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['submitted_by'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach
                        </select></div>
                </div>
            </form>

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
                        <button type="button" class="ak-btn ak-btn-ghost ak-btn-sm" @click="selected = []">Clear selection</button>
                    </form>
                @endif

                <div class="ak-table-wrap">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($totals->total_count) }}</b> {{ \Illuminate\Support\Str::plural('entry', $totals->total_count) }}
                            &middot; received <b class="bk-in">{{ number_format($totals->total_in, 2) }}</b>
                            &middot; paid out <b class="bk-out">{{ number_format($totals->total_out, 2) }}</b>
                            &middot; net <b>{{ number_format($net, 2) }}</b>
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
                                    <th scope="col" class="ak-c ak-sticky-end bank-noprint"><span class="sr-only">Open</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $entry)
                                    <tr class="bk-link-row" data-entry="{{ route('bank-statement-entries.drawer', $entry) }}">
                                        @if ($canBulk)<td class="ak-c bank-noprint" data-label=""><input type="checkbox" name="ids[]" value="{{ $entry->id }}" form="bulk-form" x-model="selected" aria-label="Select entry {{ $entry->id }}"></td>@endif
                                        <td class="ak-mono" data-label="Date">{{ $entry->posting_date->format('d-m-Y') }}</td>
                                        <td data-label="Party">
                                            <a href="{{ route('bank-statement-entries.show', $entry) }}" data-entry="{{ route('bank-statement-entries.drawer', $entry) }}" class="ak-primary-link" dir="auto">{{ $entry->company?->supplier_name ?? $entry->party_name_in_statement ?? '—' }}</a>
                                            <div class="ak-muted" dir="auto">
                                                @if ($entry->company && $entry->party_name_in_statement && mb_strtolower($entry->company->supplier_name) !== mb_strtolower($entry->party_name_in_statement)){{ $entry->party_name_in_statement }} · @endif
                                                {{ $entry->reference }}@if ($entry->receipt_no) · <b>{{ $entry->receipt_no }}</b>@endif
                                            </div>
                                        </td>
                                        <td data-label="Category">{{ $entry->category?->name ?? '—' }}</td>
                                        <td class="ak-num bk-in" data-label="Received">@if ((float) $entry->credit > 0){{ number_format($entry->credit, 2) }}@endif</td>
                                        <td class="ak-num bk-out" data-label="Paid out">@if ((float) $entry->debit > 0){{ number_format($entry->debit, 2) }}@endif</td>
                                        <td data-label="Status">
                                            <x-bank.status-badge :status="$entry->status" />
                                            @if ($entry->needs_review)<div class="ak-muted"><b>Review</b></div>@endif
                                        </td>
                                        <td class="ak-c" data-label="Files">
                                            @if ($entry->attachments_count)<span title="{{ $entry->attachments_count }} file(s) attached">{{ $entry->attachments_count }}</span>
                                            @elseif (in_array($entry->status, ['classified', 'pending_approval', 'posted'], true))<span class="ak-muted" title="No receipt attached yet">none</span>@endif
                                        </td>
                                        <td class="ak-sticky-end bank-noprint" data-label="">
                                            <div class="ak-actions">
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
