@php
use Illuminate\Support\Str;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Chart of Accounts" :createRoute="route('chart-of-accounts.create')" createLabel="Add Account"
            :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('chart-of-accounts.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_account_code" value="Account Code" />
                <x-input id="filter_account_code" name="filter[account_code]" type="text"
                    class="mt-1 block w-full" :value="request('filter.account_code')" placeholder="e.g., 1100" />
            </div>

            <div>
                <x-label for="filter_account_name" value="Account Name" />
                <x-input id="filter_account_name" name="filter[account_name]" type="text"
                    class="mt-1 block w-full" :value="request('filter.account_name')" placeholder="Search by name" />
            </div>

            <div>
                <x-label for="filter_account_type_id" value="Account Type" />
                <select id="filter_account_type_id" name="filter[account_type_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Account Types</option>
                    @foreach ($accountTypes as $typeId => $typeName)
                        <option value="{{ $typeId }}" {{ request('filter.account_type_id') == (string) $typeId ? 'selected' : '' }}>
                            {{ $typeName }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_currency_id" value="Currency" />
                <select id="filter_currency_id" name="filter[currency_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Currencies</option>
                    @foreach ($currencies as $currencyId => $currencyCode)
                        <option value="{{ $currencyId }}" {{ request('filter.currency_id') == (string) $currencyId ? 'selected' : '' }}>
                            {{ $currencyCode }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_normal_balance" value="Normal Balance" />
                <select id="filter_normal_balance" name="filter[normal_balance]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($normalBalances as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.normal_balance') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_group" value="Account Kind" />
                <select id="filter_is_group" name="filter[is_group]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($groupOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.is_group') === (string) $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.is_active') === (string) $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$chartOfAccounts" :headers="[
            ['label' => '#', 'align' => 'text-center'],
            ['label' => 'Account Code', 'align' => 'text-left'],
            ['label' => 'Account Name', 'align' => 'text-left'],
            ['label' => 'Type', 'align' => 'text-left'],
            ['label' => 'Currency', 'align' => 'text-center'],
            ['label' => 'Normal Balance', 'align' => 'text-center'],
            ['label' => 'Kind', 'align' => 'text-center'],
            ['label' => 'Status', 'align' => 'text-center'],
            ['label' => 'Parent', 'align' => 'text-left'],
            ['label' => 'Actions', 'align' => 'text-center print:hidden'],
        ]" emptyMessage="No chart of account records found." :emptyRoute="route('chart-of-accounts.create')"
        emptyLinkText="Create an account">

        @foreach ($chartOfAccounts as $index => $account)
            <tr class="border-b border-gray-200 hover:bg-gray-100">
                <td class="py-1 px-2 text-center">
                    {{ $chartOfAccounts->firstItem() + $index }}
                </td>
                <td class="py-1 px-2 text-left font-semibold">
                    {{ $account->account_code }}
                </td>
                <td class="py-1 px-2 text-left">
                    {{ Str::limit($account->account_name, 80) }}
                </td>
                <td class="py-1 px-2 text-left">
                    {{ $account->accountType->type_name ?? '-' }}
                </td>
                <td class="py-1 px-2 text-center">
                    {{ $account->currency->currency_code ?? '-' }}
                </td>
                <td class="py-1 px-2 text-center capitalize">
                    {{ $account->normal_balance }}
                </td>
                <td class="py-1 px-2 text-center">
                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $account->is_group ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                        {{ $account->is_group ? 'Group' : 'Posting' }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $account->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        {{ $account->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="py-1 px-2 text-left">
                    @if ($account->parent)
                        {{ $account->parent->account_code }} · {{ Str::limit($account->parent->account_name, 40) }}
                    @else
                        -
                    @endif
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('chart-of-accounts.show', $account) }}"
                            class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                            title="View">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        <a href="{{ route('chart-of-accounts.edit', $account) }}"
                            class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                            title="Edit">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
