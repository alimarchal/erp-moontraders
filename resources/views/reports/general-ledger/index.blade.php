<x-app-layout>
    <x-slot name="header">
        <x-page-header title="General Ledger" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true"
            backRoute="dashboard" />
    </x-slot>

    <x-filter-section :action="route('reports.general-ledger.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_journal_entry_id" value="Journal #" />
                <x-input id="filter_journal_entry_id" name="filter[journal_entry_id]" type="text"
                    class="mt-1 block w-full" :value="request('filter.journal_entry_id')"
                    placeholder="Exact journal #" />
            </div>

            <div>
                <x-label for="filter_entry_date_from" value="Entry Date (From)" />
                <x-input id="filter_entry_date_from" name="filter[entry_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.entry_date_from')" />
            </div>

            <div>
                <x-label for="filter_entry_date_to" value="Entry Date (To)" />
                <x-input id="filter_entry_date_to" name="filter[entry_date_to]" type="date" class="mt-1 block w-full"
                    :value="request('filter.entry_date_to')" />
            </div>

            <div>
                <x-label for="filter_account_code" value="Account Code" />
                <x-input id="filter_account_code" name="filter[account_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.account_code')"
                    placeholder="e.g., 4000" />
            </div>

            <div>
                <x-label for="filter_account_id" value="Account ID" />
                <x-input id="filter_account_id" name="filter[account_id]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_id')" placeholder="Exact account id" />
            </div>

            <div>
                <x-label for="filter_account_name" value="Account Name" />
                <x-input id="filter_account_name" name="filter[account_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_name')" placeholder="Search by account name" />
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
                <x-label for="filter_cost_center_code" value="Cost Center" />
                <x-input id="filter_cost_center_code" name="filter[cost_center_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.cost_center_code')"
                    placeholder="e.g., CC-01" />
            </div>

            <div>
                <x-label for="filter_cost_center_name" value="Cost Center Name" />
                <x-input id="filter_cost_center_name" name="filter[cost_center_name]" type="text"
                    class="mt-1 block w-full" :value="request('filter.cost_center_name')"
                    placeholder="Cost center name contains..." />
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
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.status')===$value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$entries" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Entry Date'],
        ['label' => 'Reference'],
        ['label' => 'Journal Description'],
        ['label' => 'Account'],
        ['label' => 'Debit', 'align' => 'text-right'],
        ['label' => 'Credit', 'align' => 'text-right'],
        ['label' => 'Cost Center'],
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
        <tr class="border-b border-gray-200 dark:border-gray-700 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $entries->firstItem() + $index }}
            </td>
            <td class="py-1 px-2 whitespace-nowrap">
                {{ optional($entry->entry_date)->format('d-m-Y') }}
            </td>
            <td class="py-1 px-2">
                {{ $entry->reference ?? '—' }}
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold "> {{ $entry->journal_description ?? '—' }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <abbr class="text-red-700" title="Line Description">LD:</abbr> {{ $entry->line_description
                    ??
                    $entry->journal_description ?? '—' }}
                </div>
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold uppercase">{{ $entry->account_code }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->account_name }} </div>
            </td>
            <td class="py-1 px-2 text-right font-mono">
                {{ number_format((float) $entry->debit, 2) }}
            </td>
            <td class="py-1 px-2 text-right font-mono">
                {{ number_format((float) $entry->credit, 2) }}
            </td>
            <td class="py-1 px-2">
                @if ($entry->cost_center_code)
                <div class="font-semibold uppercase">{{ $entry->cost_center_code }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->cost_center_name }}</div>
                @else
                <span class="text-gray-400">—</span>
                @endif
            </td>
            <td class="py-1 px-2 text-center">
                @php
                $status = $entry->status ?? 'draft';
                $badgeClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-600';
                @endphp
                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $badgeClass }}">
                    {{ ucfirst($status) }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-data-table>
</x-app-layout>