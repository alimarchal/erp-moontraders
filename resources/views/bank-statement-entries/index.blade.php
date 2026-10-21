@php
    $query = fn (array $extra = []) => array_filter(['filter' => array_merge($filters, $extra)], fn ($v) => $v !== []);
    $activeStatus = $filters['status'] ?? null;
    $statusOrder = ['unclassified', 'classified', 'pending_approval', 'rejected', 'posted', 'reconciled', 'reversed'];
    $totalCount = array_sum(array_column($summary, 'count'));
    $totalAmount = array_sum(array_column($summary, 'amount'));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bank Statement Register</h2>
            <div class="flex flex-wrap items-center gap-2">
                @can('bank-statement-entry-create')
                    <a href="{{ route('bank-statement-entries.create') }}" class="inline-flex items-center px-3 py-2 bg-green-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-900">＋ Add entry</a>
                @endcan
                @can('bank-statement-import-create')
                    <a href="{{ route('bank-statement-imports.create') }}" class="inline-flex items-center px-3 py-2 bg-blue-950 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-900">Import statement</a>
                @endcan
                @can('bank-statement-entry-export')
                    <a href="{{ route('bank-statement-entries.export', $query()) }}" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Export</a>
                @endcan
                <button id="toggle" type="button" class="inline-flex items-center px-3 py-2 bg-blue-950 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-900">Filters</button>
            </div>
        </div>
    </x-slot>

    <x-bank.print-styles />
    <x-bank.subnav />
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4"><x-bank.print-title title="Bank Statement Register" /></div>

    <x-filter-section :action="route('bank-statement-entries.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="lg:col-span-2">
                <x-label for="f_q" value="Search (party, narration, reference, receipt, note, file name)" />
                <x-input id="f_q" name="filter[q]" type="text" dir="auto" class="mt-1 block w-full" :value="$filters['q'] ?? ''" placeholder="e.g. Unilever, CHQ 00000264, BR-2024-25-000001" />
            </div>
            <div>
                <x-label for="f_date_from" value="Date from" />
                <x-input id="f_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full" :value="$filters['date_from'] ?? ''" />
            </div>
            <div>
                <x-label for="f_date_to" value="Date to" />
                <x-input id="f_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full" :value="$filters['date_to'] ?? ''" />
            </div>
            <div>
                <x-label for="f_bank_account_id" value="Bank account" />
                <select id="f_bank_account_id" name="filter[bank_account_id]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['bank_account_id'] ?? '') === (string) $account->id)>{{ $account->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="f_company_id" value="Company / principal" />
                <select id="f_company_id" name="filter[company_id]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="none" @selected(($filters['company_id'] ?? '') === 'none')>— no company —</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->supplier_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="f_category_id" value="Category" />
                <select id="f_category_id" name="filter[category_id]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="none" @selected(($filters['category_id'] ?? '') === 'none')>— no category —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="f_direction" value="Money" />
                <select id="f_direction" name="filter[direction]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">In and out</option>
                    <option value="credit" @selected(($filters['direction'] ?? '') === 'credit')>Received (credit)</option>
                    <option value="debit" @selected(($filters['direction'] ?? '') === 'debit')>Paid (debit)</option>
                </select>
            </div>
            <div>
                <x-label for="f_amount_min" value="Amount from" />
                <x-input id="f_amount_min" name="filter[amount_min]" type="number" step="0.01" class="mt-1 block w-full" :value="$filters['amount_min'] ?? ''" />
            </div>
            <div>
                <x-label for="f_amount_max" value="Amount to" />
                <x-input id="f_amount_max" name="filter[amount_max]" type="number" step="0.01" class="mt-1 block w-full" :value="$filters['amount_max'] ?? ''" />
            </div>
            <div>
                <x-label for="f_reference" value="Reference / cheque no." />
                <x-input id="f_reference" name="filter[reference]" type="text" class="mt-1 block w-full" :value="$filters['reference'] ?? ''" />
            </div>
            <div>
                <x-label for="f_receipt_no" value="Receipt no." />
                <x-input id="f_receipt_no" name="filter[receipt_no]" type="text" class="mt-1 block w-full" :value="$filters['receipt_no'] ?? ''" />
            </div>
            <div>
                <x-label for="f_has_attachment" value="Attachment" />
                <select id="f_has_attachment" name="filter[has_attachment]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Any</option>
                    <option value="yes" @selected(($filters['has_attachment'] ?? '') === 'yes')>Has attachment</option>
                    <option value="no" @selected(($filters['has_attachment'] ?? '') === 'no')>No attachment</option>
                </select>
            </div>
            <div>
                <x-label for="f_needs_review" value="Review flag" />
                <select id="f_needs_review" name="filter[needs_review]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Any</option>
                    <option value="yes" @selected(($filters['needs_review'] ?? '') === 'yes')>Needs review</option>
                    <option value="no" @selected(($filters['needs_review'] ?? '') === 'no')>Not flagged</option>
                </select>
            </div>
            <div>
                <x-label for="f_submitted_by" value="Maker" />
                <select id="f_submitted_by" name="filter[submitted_by]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Anyone</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['submitted_by'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            @if (! empty($filters['status']))<input type="hidden" name="filter[status]" value="{{ $filters['status'] }}">@endif
            @if (! empty($filters['import_id']))<input type="hidden" name="filter[import_id]" value="{{ $filters['import_id'] }}">@endif
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 pb-16" x-data="{
        ids: [],
        all: false,
        drawer: false, loading: false, html: '',
        async open(url) {
            this.drawer = true; this.loading = true; this.html = '';
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            this.html = await response.text(); this.loading = false;
            this.$nextTick(() => window.initSelect2 && window.initSelect2(this.$root));
        },
        toggleAll(checked) { this.ids = checked ? [...document.querySelectorAll('input[name=\'ids[]\']')].map(e => e.value) : []; }
    }" @keydown.escape.window="drawer = false">

        <x-status-message class="mb-3" />

        {{-- Work pipeline: where the money is in the daily flow --}}
        <div class="bank-noprint mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['1', 'To classify', 'Waiting for a company / category', $tiles['unclassified'], 'unclassified', 'slate'],
                ['2', 'Waiting for approval', 'With a checker or senior approver', $tiles['pending'], 'pending_approval', 'amber'],
                ['3', 'Posted', 'In the books, not yet reconciled', $tiles['unreconciled'], 'posted', 'emerald'],
                ['4', 'Reconciled', 'Matched with the bank statement', $tiles['reconciled'], 'reconciled', 'teal'],
            ] as [$step, $label, $hint, $tile, $status, $tone])
                @php($active = ($filters['status'] ?? '') === $status)
                <a href="{{ route('bank-statement-entries.index', $query(['status' => $active ? null : $status])) }}"
                    class="group relative overflow-hidden rounded-xl bg-white p-4 shadow-sm ring-1 transition hover:-translate-y-0.5 hover:shadow-md {{ $active ? 'ring-2 ring-blue-900' : 'ring-gray-200' }}">
                    <span class="absolute inset-x-0 top-0 h-1 {{ ['slate' => 'bg-slate-400', 'amber' => 'bg-amber-500', 'emerald' => 'bg-emerald-500', 'teal' => 'bg-teal-500'][$tone] }}"></span>
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">{{ $label }}</p>
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500">{{ $step }}</span>
                    </div>
                    <p class="mt-1 text-3xl font-bold leading-none tabular-nums text-gray-900">{{ number_format($tile['count']) }}</p>
                    <p class="mt-1.5 text-xs font-medium tabular-nums text-gray-700">PKR {{ number_format($tile['amount'], 2) }}</p>
                    <p class="mt-0.5 text-[11px] text-gray-400">{{ $hint }}</p>
                </a>
            @endforeach
        </div>

        {{-- Account switcher (only when there is more than one account) --}}
        @if ($bankAccounts->count() > 1)
            <div class="bank-noprint flex flex-wrap items-center gap-2 mb-2 text-sm">
                <span class="text-gray-500">Account:</span>
                <a href="{{ route('bank-statement-entries.index', $query(['bank_account_id' => null])) }}" class="rounded-md px-2.5 py-0.5 border {{ empty($filters['bank_account_id']) ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">All accounts</a>
                @foreach ($bankAccounts as $account)
                    <a href="{{ route('bank-statement-entries.index', $query(['bank_account_id' => $account->id])) }}" class="rounded-md px-2.5 py-0.5 border {{ (string) ($filters['bank_account_id'] ?? '') === (string) $account->id ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">{{ $account->account_name }}</a>
                @endforeach
            </div>
        @endif

        {{-- Status chips --}}
        <div class="bank-noprint flex flex-wrap items-center gap-2 mb-3">
            <a href="{{ route('bank-statement-entries.index', $query(['status' => null])) }}" class="rounded-full px-3 py-1 text-sm border {{ $activeStatus ? 'bg-white text-gray-700 border-gray-300' : 'bg-blue-950 text-white border-blue-950' }}">All <b class="tabular-nums">{{ number_format($totalCount) }}</b></a>
            @foreach ($statusOrder as $status)
                @if (isset($summary[$status]))
                    <a href="{{ route('bank-statement-entries.index', $query(['status' => $status])) }}" class="rounded-full px-3 py-1 text-sm border {{ $activeStatus === $status ? 'bg-blue-950 text-white border-blue-950' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                        {{ \App\Models\BankStatementEntry::STATUSES[$status] }} <b class="tabular-nums">{{ number_format($summary[$status]['count']) }}</b>
                        <span class="text-xs opacity-70 tabular-nums">· {{ number_format($summary[$status]['amount'] / 1000000, 2) }}M</span>
                    </a>
                @endif
            @endforeach
            <a href="{{ route('bank-statement-entries.index', $query(['has_attachment' => ($filters['has_attachment'] ?? '') === 'no' ? null : 'no'])) }}" class="rounded-full px-3 py-1 text-sm border {{ ($filters['has_attachment'] ?? '') === 'no' ? 'bg-amber-600 text-white border-amber-600' : 'bg-amber-50 text-amber-900 border-amber-300 hover:bg-amber-100' }}" title="Entries that have no receipt or file yet">📎 No receipt <b class="tabular-nums">{{ number_format($withoutReceipt) }}</b></a>
            @can('bank-statement-entry-classify')
                <form method="POST" action="{{ route('bank-statement-entries.auto-classify') }}" class="ms-auto">
                    @csrf
                    <button class="text-sm text-blue-800 hover:underline" title="Run aliases, rules and the cheque register over every unclassified entry">⚡ Auto-classify unclassified</button>
                </form>
            @endcan
        </div>

        <form method="POST" id="bulk-form">
            @csrf
            {{-- Bulk toolbar --}}
            <div x-show="ids.length" x-cloak class="bank-noprint sticky top-0 z-20 mb-3 rounded-lg border border-blue-200 bg-blue-50 p-3 shadow">
                <div class="flex flex-wrap items-end gap-3">
                    <p class="text-sm font-semibold text-blue-900"><span x-text="ids.length"></span> selected</p>
                    @can('bank-statement-entry-classify')
                        <div>
                            <label class="block text-xs text-blue-900">Company</label>
                            <select data-width="230px" name="company_id" class="select2 bank-select border-gray-300 rounded-md text-sm py-1.5">
                                <option value="">—</option>
                                @foreach ($companies as $company)<option value="{{ $company->id }}">{{ $company->supplier_name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-blue-900">Category</label>
                            <select data-width="230px" name="bank_entry_category_id" class="select2 bank-select border-gray-300 rounded-md text-sm py-1.5">
                                <option value="">—</option>
                                @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                            </select>
                        </div>
                        <label class="inline-flex items-center gap-1.5 text-sm text-blue-900"><input type="checkbox" name="remember_alias" value="1" class="rounded border-gray-300"> Remember for next time</label>
                        <button type="submit" formaction="{{ route('bank-statement-entries.bulk-classify') }}" class="rounded-md bg-blue-950 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900">Apply</button>
                    @endcan
                    @can('bank-statement-entry-submit')
                        <button type="submit" formaction="{{ route('bank-statement-entries.bulk-submit') }}" class="rounded-md bg-green-800 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900">Submit selected</button>
                    @endcan
                    <button type="button" @click="ids = []; all = false" class="text-sm text-gray-600 hover:underline">Clear</button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                {{-- Card toolbar: what is shown, the totals of it, and a quick search --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50/70 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Entries</p>
                            <p class="text-lg font-bold leading-tight tabular-nums text-gray-900">{{ number_format($totals->total_count) }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Received</p>
                            <p class="text-lg font-bold leading-tight tabular-nums text-emerald-700">{{ number_format($totals->total_in, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Paid out</p>
                            <p class="text-lg font-bold leading-tight tabular-nums text-red-700">{{ number_format($totals->total_out, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Net</p>
                            <p class="text-lg font-bold leading-tight tabular-nums {{ $totals->total_in - $totals->total_out < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ number_format($totals->total_in - $totals->total_out, 2) }}</p>
                        </div>
                    </div>
                    <form method="GET" action="{{ route('bank-statement-entries.index') }}" class="bank-noprint flex items-center gap-2" role="search">
                        @foreach (collect($filters)->except('q') as $key => $value)
                            @if (is_array($value))
                                @foreach ($value as $item)
                                    @if (is_scalar($item) && $item !== '')<input type="hidden" name="filter[{{ $key }}][]" value="{{ $item }}">@endif
                                @endforeach
                            @elseif (is_scalar($value) && $value !== '')
                                <input type="hidden" name="filter[{{ $key }}]" value="{{ $value }}">
                            @endif
                        @endforeach
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z"/></svg>
                            <input type="search" name="filter[q]" value="{{ $filters['q'] ?? '' }}" dir="auto" placeholder="Search party, reference, receipt…" class="w-72 rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-900 focus:ring-blue-900">
                        </div>
                        <button class="rounded-lg bg-blue-950 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-white hover:bg-blue-900">Search</button>
                    </form>
                </div>
                @if ($entries->count())
                    <div class="bank-scroll overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="sticky top-0 z-10">
                                <tr class="sticky top-0 z-10 bg-slate-800 text-[11px] font-semibold uppercase tracking-wider text-slate-100">
                                    <th class="bank-noprint py-2 px-2 w-8"><input type="checkbox" x-model="all" @change="toggleAll($event.target.checked)" class="rounded border-gray-300"></th>
                                    <th class="py-2.5 px-3 text-left">Date</th>
                                    <th class="py-2.5 px-3 text-left">Party / company</th>
                                    <th class="py-2.5 px-3 text-left">Reference</th>
                                    <th class="py-2.5 px-3 text-left">Category</th>
                                    <th class="py-2.5 px-3 text-right">Paid out</th>
                                    <th class="py-2.5 px-3 text-right">Received</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-center" title="Attachments (a dot means no receipt yet)">Files</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $index => $entry)
                                    <tr class="border-t border-gray-100 cursor-pointer transition hover:bg-blue-50/70 {{ $entry->needs_review ? 'bg-amber-50/80' : 'bg-white' }}"
                                        @click="if (! $event.target.closest('input, a, button')) open('{{ route('bank-statement-entries.drawer', $entry) }}')">
                                        <td class="bank-noprint py-2.5 px-3"><input type="checkbox" name="ids[]" value="{{ $entry->id }}" x-model="ids" class="rounded border-gray-300"></td>
                                        <td class="py-2.5 px-3 whitespace-nowrap tabular-nums text-gray-600">{{ $entry->posting_date->format('d-m-Y') }}</td>
                                        <td class="py-2.5 px-3 max-w-xs">
                                            <div class="font-semibold truncate" dir="auto">{{ $entry->company?->supplier_name ?? $entry->party_name_in_statement ?? '—' }}</div>
                                            @if ($entry->company && $entry->party_name_in_statement && mb_strtolower($entry->company->supplier_name) !== mb_strtolower($entry->party_name_in_statement))<div class="text-xs text-gray-500 truncate" dir="auto">{{ $entry->party_name_in_statement }}</div>@endif
                                        </td>
                                        <td class="py-2.5 px-3 text-xs text-gray-600 whitespace-nowrap">{{ $entry->reference }}@if ($entry->receipt_no)<div class="text-emerald-700 font-semibold">{{ $entry->receipt_no }}</div>@endif</td>
                                        <td class="py-2.5 px-3">{{ $entry->category?->name ?? '—' }}</td>
                                        <td class="py-2.5 px-3 text-right font-medium tabular-nums text-red-700">@if ((float) $entry->debit > 0)<x-bank.money :value="$entry->debit" />@endif</td>
                                        <td class="py-2.5 px-3 text-right font-medium tabular-nums text-emerald-700">@if ((float) $entry->credit > 0)<x-bank.money :value="$entry->credit" />@endif</td>
                                        <td class="py-2.5 px-3 text-center"><x-bank.status-badge :status="$entry->status" />@if ($entry->needs_review)<div class="text-[10px] text-orange-700 font-semibold">REVIEW</div>@endif</td>
                                        <td class="py-2.5 px-3 text-center text-xs text-gray-500">
                                            @if ($entry->attachments_count)
                                                <span class="inline-flex items-center gap-0.5" title="{{ $entry->attachments_count }} file(s) attached"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.4 11.1l-7.2 7.2a4.5 4.5 0 01-6.4-6.4l7.2-7.2a3 3 0 014.2 4.2l-7.2 7.2a1.5 1.5 0 01-2.1-2.1l6.7-6.7"/></svg>{{ $entry->attachments_count }}</span>
                                            @elseif (in_array($entry->status, ['classified', 'pending_approval', 'posted'], true))
                                                <span class="inline-block h-2 w-2 rounded-full bg-amber-400" title="No receipt attached yet"></span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($entries->hasPages())<div class="px-2 py-2">{{ $entries->links() }}</div>@endif
                @else
                    <p class="text-center text-gray-700 py-10">No entries match. @can('bank-statement-entry-create')<a href="{{ route('bank-statement-entries.create') }}" class="text-blue-700 hover:underline">Add an entry</a> or @endcan @can('bank-statement-import-create')<a href="{{ route('bank-statement-imports.create') }}" class="text-blue-700 hover:underline">Import a statement</a>@endcan</p>
                @endif
            </div>
        </form>

        {{-- Side drawer --}}
        <div x-show="drawer" x-cloak class="bank-noprint fixed inset-0 z-40 flex justify-end" aria-modal="true">
            <div class="absolute inset-0 bg-black/40" @click="drawer = false"></div>
            <aside class="relative w-full max-w-xl bg-white shadow-2xl overflow-y-auto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
                <button type="button" @click="drawer = false" class="absolute end-3 top-3 z-10 rounded-full bg-gray-100 px-2.5 py-1 text-sm hover:bg-gray-200" aria-label="Close">✕</button>
                <div x-show="loading" class="p-10 text-center text-gray-500">Loading…</div>
                <div x-html="html"></div>
            </aside>
        </div>
    </div>
</x-app-layout>
