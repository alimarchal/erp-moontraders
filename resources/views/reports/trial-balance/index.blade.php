<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Trial Balance" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.trial-balance.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_account_code" value="Account Code" />
                <x-input id="filter_account_code" name="filter[account_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.account_code')"
                    placeholder="e.g., 1000" />
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
        </div>
    </x-filter-section>

    <!-- Trial Balance Summary -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 mb-4">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200">Trial Balance Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Debits</div>
                    <div class="text-2xl font-bold font-mono text-blue-700 dark:text-blue-300">
                        {{ number_format((float) $trialBalance->total_debits, 2) }}
                    </div>
                </div>
                <div class="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Credits</div>
                    <div class="text-2xl font-bold font-mono text-green-700 dark:text-green-300">
                        {{ number_format((float) $trialBalance->total_credits, 2) }}
                    </div>
                </div>
                <div
                    class="p-4 rounded-lg {{ abs($trialBalance->difference) < 0.01 ? 'bg-emerald-50 dark:bg-emerald-900' : 'bg-red-50 dark:bg-red-900' }}">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Difference</div>
                    <div
                        class="text-2xl font-bold font-mono {{ abs($trialBalance->difference) < 0.01 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">
                        {{ number_format((float) $trialBalance->difference, 2) }}
                    </div>
                    @if(abs($trialBalance->difference) < 0.01) <div
                        class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">✓ Balanced
                </div>
                @else
                <div class="text-xs text-red-600 dark:text-red-400 mt-1">⚠️ Out of Balance</div>
                @endif
            </div>
        </div>
    </div>
    </div>

    <!-- Detailed Account Balances -->
    <x-data-table :items="$accounts" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Account Code'],
        ['label' => 'Account Name'],
        ['label' => 'Account Type'],
        ['label' => 'Debits', 'align' => 'text-right'],
        ['label' => 'Credits', 'align' => 'text-right'],
        ['label' => 'Balance', 'align' => 'text-right'],
    ]" emptyMessage="No account balances found.">
        @foreach ($accounts as $index => $account)
        <tr class="border-b border-gray-200 dark:border-gray-700 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $accounts->firstItem() + $index }}
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold uppercase font-mono">{{ $account->account_code }}</div>
            </td>
            <td class="py-1 px-2">
                {{ $account->account_name }}
            </td>
            <td class="py-1 px-2">
                <div class="text-xs text-gray-600 dark:text-gray-400">{{ $account->account_type }}</div>
            </td>
            <td class="py-1 px-2 text-right font-mono">
                {{ number_format((float) $account->total_debits, 2) }}
            </td>
            <td class="py-1 px-2 text-right font-mono">
                {{ number_format((float) $account->total_credits, 2) }}
            </td>
            <td class="py-1 px-2 text-right font-mono font-semibold">
                {{ number_format((float) $account->balance, 2) }}
            </td>
        </tr>
        @endforeach
        <tr class="border-t-2 border-gray-400 dark:border-gray-500 bg-gray-100 dark:bg-gray-800 font-bold">
            <td colspan="4" class="py-2 px-2 text-right">
                Page Total ({{ $accounts->count() }} accounts):
            </td>
            <td class="py-2 px-2 text-right font-mono">
                {{ number_format($accounts->sum('total_debits'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono">
                {{ number_format($accounts->sum('total_credits'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono">
                {{ number_format($accounts->sum('balance'), 2) }}
            </td>
        </tr>
    </x-data-table>
</x-app-layout>