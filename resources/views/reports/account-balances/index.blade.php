<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Account Balances" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.account-balances.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_account_code" value="Account Code" />
                <x-input id="filter_account_code" name="filter[account_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.account_code')"
                    placeholder="e.g., 4000" />
            </div>

            <div>
                <x-label for="filter_account_name" value="Account Name" />
                <x-input id="filter_account_name" name="filter[account_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_name')" placeholder="Search by account name" />
            </div>

            <div>
                <x-label for="filter_account_type" value="Account Type" />
                <x-input id="filter_account_type" name="filter[account_type]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_type')" placeholder="e.g., Asset" />
            </div>

            <div>
                <x-label for="filter_report_group" value="Report Group" />
                <select id="filter_report_group" name="filter[report_group]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="BalanceSheet" {{ request('filter.report_group')==='BalanceSheet' ? 'selected' : ''
                        }}>
                        Balance Sheet
                    </option>
                    <option value="IncomeStatement" {{ request('filter.report_group')==='IncomeStatement' ? 'selected'
                        : '' }}>
                        Income Statement
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_normal_balance" value="Normal Balance" />
                <select id="filter_normal_balance" name="filter[normal_balance]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="debit" {{ request('filter.normal_balance')==='debit' ? 'selected' : '' }}>
                        Debit
                    </option>
                    <option value="credit" {{ request('filter.normal_balance')==='credit' ? 'selected' : '' }}>
                        Credit
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Active Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="true" {{ request('filter.is_active')==='true' ? 'selected' : '' }}>
                        Active
                    </option>
                    <option value="false" {{ request('filter.is_active')==='false' ? 'selected' : '' }}>
                        Inactive
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_balance_min" value="Balance (Min)" />
                <x-input id="filter_balance_min" name="filter[balance_min]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_min')" placeholder="0.00" />
            </div>

            <div>
                <x-label for="filter_balance_max" value="Balance (Max)" />
                <x-input id="filter_balance_max" name="filter[balance_max]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_max')" placeholder="Any" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$balances" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Account Code'],
        ['label' => 'Account Name'],
        ['label' => 'Account Type'],
        ['label' => 'Report Group'],
        ['label' => 'Total Debits', 'align' => 'text-right'],
        ['label' => 'Total Credits', 'align' => 'text-right'],
        ['label' => 'Balance', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
    ]" emptyMessage="No account balances found.">
        @foreach ($balances as $index => $balance)
        <tr class="border-b border-gray-200 dark:border-gray-700 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $balances->firstItem() + $index }}
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold uppercase">{{ $balance->account_code }}</div>
            </td>
            <td class="py-1 px-2">
                {{ $balance->account_name }}
            </td>
            <td class="py-1 px-2">
                <div class="text-xs">{{ $balance->account_type }}</div>
            </td>
            <td class="py-1 px-2">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full 
                    {{ $balance->report_group === 'BalanceSheet' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                    {{ $balance->report_group === 'BalanceSheet' ? 'Balance Sheet' : 'Income Statement' }}
                </span>
            </td>
            <td class="py-1 px-2 text-right font-mono">
                {{ number_format((float) $balance->total_debits, 2) }}
            </td>
            <td class="py-1 px-2 text-right font-mono">
                {{ number_format((float) $balance->total_credits, 2) }}
            </td>
            <td
                class="py-1 px-2 text-right font-mono font-bold {{ $balance->balance < 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ number_format((float) $balance->balance, 2) }}
            </td>
            <td class="py-1 px-2 text-center">
                @if ($balance->is_active)
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700">
                    Active
                </span>
                @else
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                    Inactive
                </span>
                @endif
            </td>
        </tr>
        @endforeach
        <tr class="border-t-2 border-gray-400 dark:border-gray-500 bg-gray-100 dark:bg-gray-800 font-bold">
            <td colspan="5" class="py-2 px-2 text-right">
                Grand Total ({{ $balances->total() }} accounts):
            </td>
            <td class="py-2 px-2 text-right font-mono">
                {{ number_format($balances->sum('total_debits'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono">
                {{ number_format($balances->sum('total_credits'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono">
                {{ number_format($balances->sum('balance'), 2) }}
            </td>
            <td></td>
        </tr>
    </x-data-table>
</x-app-layout>