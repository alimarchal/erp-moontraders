<x-app-layout>
    <x-slot name="header">
        <x-page-header title="General Ledger" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table,
            table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 14px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td,
            table th,
            table td {
                border: 1px solid black;
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;

                    @bottom-center {
                        content: "Page " counter(page) " of " counter(pages);
                    }
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    counter-reset: page 1;
                }

                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 10px !important;
                    box-shadow: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table,
                table {
                    font-size: 11px !important;
                    width: 100% !important;
                    table-layout: auto !important;
                }

                .report-table th,
                .report-table td,
                table th,
                table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                    word-wrap: break-word !important;
                    white-space: normal !important;
                    font-size: 11px !important;
                }

                table th *,
                table td *,
                table th span,
                table td span,
                table td div,
                table td abbr {
                    font-size: 11px !important;
                    color: #000 !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 8px !important;
                }

                .print-info {
                    font-size: 9px !important;
                    margin-top: 5px !important;
                    margin-bottom: 10px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.general-ledger.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="lg:col-span-2">
                <x-label for="accounting_period_id" value="Accounting Period" />
                <select id="accounting_period_id" name="accounting_period_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                    onchange="this.form.submit()">
                    <option value="">All Time (Custom Dates)</option>
                    @foreach($accountingPeriods as $period)
                                    <option value="{{ $period->id }}" {{ $periodId == $period->id ? 'selected' : '' }}>
                                        {{ $period->name }} ({{ \Carbon\Carbon::parse($period->start_date)->format('M d, Y') }} - {{
                        \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_entry_date_from" value="Entry Date (From)" />
                <x-input id="filter_entry_date_from" name="filter[entry_date_from]" type="date"
                    class="mt-1 block w-full" :value="$entryDateFrom" />
            </div>

            <div>
                <x-label for="filter_entry_date_to" value="Entry Date (To)" />
                <x-input id="filter_entry_date_to" name="filter[entry_date_to]" type="date" class="mt-1 block w-full"
                    :value="$entryDateTo" />
            </div>

            <div>
                <x-label for="filter_journal_entry_id" value="Journal #" />
                <x-input id="filter_journal_entry_id" name="filter[journal_entry_id]" type="text"
                    class="mt-1 block w-full" :value="request('filter.journal_entry_id')"
                    placeholder="Exact journal #" />
            </div>

            <div>
                <x-label for="filter_account_code" value="Account Code" />
                <select id="filter_account_code" name="filter[account_code]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $account)
                                    <option value="{{ $account->account_code }}" {{ request('filter.account_code') === $account->
                        account_code ? 'selected' : '' }}>
                                        {{ $account->account_code }} - {{ $account->account_name }}
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_account_id" value="Account ID" />
                <x-input id="filter_account_id" name="filter[account_id]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_id')" placeholder="Exact account id" />
            </div>

            <div>
                <x-label for="filter_account_name" value="Account Name" />
                <select id="filter_account_name" name="filter[account_name]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $account)
                                    <option value="{{ $account->account_name }}" {{ request('filter.account_name') === $account->
                        account_name ? 'selected' : '' }}>
                                        {{ $account->account_name }} ({{ $account->account_code }})
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_reference" value="Reference" />
                <x-input id="filter_reference" name="filter[reference]" type="text" class="mt-1 block w-full"
                    :value="request('filter.reference')" placeholder="Reference contains..." />
            </div>

            <div>
                <x-label for="filter_journal_description" value="Journal Description" />
                <x-input id="filter_journal_description" name="filter[journal_description]" type="text"
                    class="mt-1 block w-full" :value="request('filter.journal_description')"
                    placeholder="Journal description contains..." />
            </div>

            <div>
                <x-label for="filter_line_description" value="Line Description" />
                <x-input id="filter_line_description" name="filter[line_description]" type="text"
                    class="mt-1 block w-full" :value="request('filter.line_description')"
                    placeholder="Line description contains..." />
            </div>

            <div>
                <x-label for="filter_line_no" value="Line #" />
                <x-input id="filter_line_no" name="filter[line_no]" type="text" class="mt-1 block w-full"
                    :value="request('filter.line_no')" placeholder="Exact line #" />
            </div>

            <div>
                <x-label for="filter_cost_center_code" value="Cost Center Code" />
                <select id="filter_cost_center_code" name="filter[cost_center_code]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Cost Centers</option>
                    @foreach($costCenters as $cc)
                        <option value="{{ $cc->code }}" {{ request('filter.cost_center_code') === $cc->code ? 'selected' : ''
                                                                                                                                                                                }}>
                            {{ $cc->code }} - {{ $cc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_cost_center_name" value="Cost Center Name" />
                <select id="filter_cost_center_name" name="filter[cost_center_name]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Cost Centers</option>
                    @foreach($costCenters as $cc)
                        <option value="{{ $cc->name }}" {{ request('filter.cost_center_name') === $cc->name ? 'selected' : ''
                                                                                                                                                                                }}>
                            {{ $cc->name }} ({{ $cc->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_currency_code" value="Currency" />
                <x-input id="filter_currency_code" name="filter[currency_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.currency_code')"
                    placeholder="e.g., USD" />
            </div>

            <div>
                <x-label for="filter_debit_min" value="Debit (Min)" />
                <x-input id="filter_debit_min" name="filter[debit_min]" type="number" step="0.01" min="0"
                    class="mt-1 block w-full" :value="request('filter.debit_min')" placeholder="0.00" />
            </div>

            <div>
                <x-label for="filter_debit_max" value="Debit (Max)" />
                <x-input id="filter_debit_max" name="filter[debit_max]" type="number" step="0.01" min="0"
                    class="mt-1 block w-full" :value="request('filter.debit_max')" placeholder="Any" />
            </div>

            <div>
                <x-label for="filter_credit_min" value="Credit (Min)" />
                <x-input id="filter_credit_min" name="filter[credit_min]" type="number" step="0.01" min="0"
                    class="mt-1 block w-full" :value="request('filter.credit_min')" placeholder="0.00" />
            </div>

            <div>
                <x-label for="filter_credit_max" value="Credit (Max)" />
                <x-input id="filter_credit_max" name="filter[credit_max]" type="number" step="0.01" min="0"
                    class="mt-1 block w-full" :value="request('filter.credit_max')" placeholder="Any" />
            </div>

            <div>
                <x-label for="filter_fx_rate_min" value="FX Rate to Base (Min)" />
                <x-input id="filter_fx_rate_min" name="filter[fx_rate_min]" type="number" step="0.000001" min="0"
                    class="mt-1 block w-full" :value="request('filter.fx_rate_min')" placeholder="0.000000" />
            </div>

            <div>
                <x-label for="filter_fx_rate_max" value="FX Rate to Base (Max)" />
                <x-input id="filter_fx_rate_max" name="filter[fx_rate_max]" type="number" step="0.000001" min="0"
                    class="mt-1 block w-full" :value="request('filter.fx_rate_max')" placeholder="Any" />
            </div>

            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.status') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="-entry_date" {{ request('sort') == '-entry_date' || !request('sort') ? 'selected' : ''
                        }}>Entry Date (Newest)</option>
                    <option value="entry_date" {{ request('sort') == 'entry_date' ? 'selected' : '' }}>Entry Date (Oldest)
                    </option>
                    <option value="journal_entry_id" {{ request('sort') == 'journal_entry_id' ? 'selected' : '' }}>Journal
                        Entry ID (Asc)</option>
                    <option value="-journal_entry_id" {{ request('sort') == '-journal_entry_id' ? 'selected' : '' }}>
                        Journal Entry ID (Desc)</option>
                    <option value="account_code" {{ request('sort') == 'account_code' ? 'selected' : '' }}>Account Code
                        (A-Z)</option>
                    <option value="-account_code" {{ request('sort') == '-account_code' ? 'selected' : '' }}>Account Code
                        (Z-A)</option>
                    <option value="account_name" {{ request('sort') == 'account_name' ? 'selected' : '' }}>Account Name
                        (A-Z)</option>
                    <option value="-account_name" {{ request('sort') == '-account_name' ? 'selected' : '' }}>Account Name
                        (Z-A)</option>
                    <option value="-debit" {{ request('sort') == '-debit' ? 'selected' : '' }}>Debit (High-Low)</option>
                    <option value="debit" {{ request('sort') == 'debit' ? 'selected' : '' }}>Debit (Low-High)</option>
                    <option value="-credit" {{ request('sort') == '-credit' ? 'selected' : '' }}>Credit (High-Low)
                    </option>
                    <option value="credit" {{ request('sort') == 'credit' ? 'selected' : '' }}>Credit (Low-High)</option>
                    <option value="status" {{ request('sort') == 'status' ? 'selected' : '' }}>Status (A-Z)</option>
                    <option value="-status" {{ request('sort') == '-status' ? 'selected' : '' }}>Status (Z-A)</option>
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Show Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page') == 250 ? 'selected' : '' }}>250</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$entries" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Date / Ref / CS'],
        ['label' => 'Journal Description'],
        ['label' => 'Account'],
        ['label' => 'Debit', 'align' => 'text-right'],
        ['label' => 'Credit', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
    ]" emptyMessage="No general ledger entries found.">
        @php
            $statusClasses = [
                'posted' => 'bg-emerald-100 text-emerald-700',
                'draft' => 'bg-yellow-100 text-yellow-700',
                'void' => 'bg-red-100 text-red-700',
            ];
        @endphp
        @foreach ($entries as $index => $entry)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-1 text-center">
                    {{ $entries->firstItem() + $index }}
                </td>
                <td class="py-1 px-1 break-words max-w-xs">
                    {{ optional($entry->entry_date)->format('d-m-Y') }}
                    <div class="break-words max-w-[8rem]">{{ $entry->reference ?? '—' }}</div>
                    @if ($entry->cost_center_code)
                        <div class="font-semibold uppercase font-mono">{{ $entry->cost_center_code }}</div>
                        <div class="text-xs text-gray-600">{{ $entry->cost_center_name }}</div>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="py-1 px-1">
                    <div class="font-semibold break-words max-w-xs">{{ $entry->journal_description ?? '—' }}</div>
                    <div class="text-xs text-gray-600 break-words max-w-xs">
                        <abbr class="text-red-700" title="Line Description">LD:</abbr> {{ $entry->line_description ??
            $entry->journal_description ?? '—' }}
                    </div>
                </td>
                <td class="py-1 px-1">
                    <div class="font-semibold uppercase font-mono">{{ $entry->account_code }}</div>
                    <div class="text-xs text-gray-600">{{ $entry->account_name }}</div>
                </td>
                <td class="py-1 px-1 text-right font-mono">
                    {{ number_format((float) $entry->debit, 2) }}
                </td>
                <td class="py-1 px-1 text-right font-mono">
                    {{ number_format((float) $entry->credit, 2) }}
                </td>

                <td class="py-1 px-1 text-center">
                    @php
                        $status = $entry->status ?? 'draft';
                        $badgeClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-600';
                    @endphp
                    <span class="inline-flex items-center px-1 py-1 text-xs font-semibold rounded-full {{ $badgeClass }}">
                        {{ ucfirst($status) }}
                    </span>
                </td>
            </tr>
        @endforeach
        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
            <td colspan="4" class="py-1 px-1 text-right">
                Page Total ({{ $entries->count() }} rows):
            </td>
            <td class="py-1 px-1 text-right font-mono">
                {{ number_format($entries->sum('debit'), 2) }}
            </td>
            <td class="py-1 px-1 text-right font-mono">
                {{ number_format($entries->sum('credit'), 2) }}
            </td>
            <td></td>
        </tr>
    </x-data-table>
</x-app-layout>