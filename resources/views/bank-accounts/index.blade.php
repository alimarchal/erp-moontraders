<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bank Accounts" :createRoute="route('bank-accounts.create')" createLabel="Add Bank Account"
            :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('bank-accounts.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_account_name" value="Account Name" />
                <x-input id="filter_account_name" name="filter[account_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_name')" placeholder="HBL Main Account" />
            </div>

            <div>
                <x-label for="filter_account_number" value="Account Number" />
                <x-input id="filter_account_number" name="filter[account_number]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_number')" placeholder="24997000284199" />
            </div>

            <div>
                <x-label for="filter_bank_name" value="Bank Name" />
                <x-input id="filter_bank_name" name="filter[bank_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.bank_name')" placeholder="HBL" />
            </div>

            <div>
                <x-label for="filter_iban" value="IBAN" />
                <x-input id="filter_iban" name="filter[iban]" type="text" class="mt-1 block w-full uppercase"
                    :value="request('filter.iban')" placeholder="Search IBAN" />
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.is_active')===$value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$accounts" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Account Number', 'align' => 'text-left'],
        ['label' => 'Account Name', 'align' => 'text-left'],
        ['label' => 'Bank', 'align' => 'text-left'],
        ['label' => 'Chart of Account', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No bank accounts found." :emptyRoute="route('bank-accounts.create')"
        emptyLinkText="Add a bank account">
        @foreach ($accounts as $index => $account)
        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
            <td class="py-2 px-2 text-center">
                {{ $accounts->firstItem() + $index }}
            </td>
            <td class="py-2 px-2 font-semibold">
                <div>{{ $account->account_number }}</div>
                <div class="text-xs text-gray-500">{{ $account->iban ?? '—' }}</div>
            </td>
            <td class="py-2 px-2">
                <div class="font-semibold">{{ $account->account_name }}</div>
                <div class="text-xs text-gray-500 line-clamp-2">{{ $account->description ?? '—' }}</div>
            </td>
            <td class="py-2 px-2">
                <div>{{ $account->bank_name ?? '—' }}</div>
                <div class="text-xs text-gray-500">{{ $account->branch ?? '—' }}</div>
            </td>
            <td class="py-2 px-2 text-sm">
                {{ $account->chartOfAccount ? $account->chartOfAccount->account_code . ' - ' .
                $account->chartOfAccount->account_name : '—' }}
            </td>
            <td class="py-2 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $account->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                </span>
            </td>
            <td class="py-2 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('bank-accounts.show', $account) }}"
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
                    <a href="{{ route('bank-accounts.edit', $account) }}"
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