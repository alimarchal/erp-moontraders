<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Suppliers" :createRoute="route('suppliers.create')" createLabel="Add Supplier"
            :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-status-message class="mb-4 mt-4 shadow-md" />

    <x-filter-section :action="route('suppliers.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_supplier_name" value="Supplier Name" />
                <x-input id="filter_supplier_name" name="filter[supplier_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.supplier_name')" placeholder="Nestlé Pakistan" />
            </div>

            <div>
                <x-label for="filter_short_name" value="Short Name" />
                <x-input id="filter_short_name" name="filter[short_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.short_name')" placeholder="NESTLE" />
            </div>

            <div>
                <x-label for="filter_supplier_group" value="Supplier Group" />
                <x-input id="filter_supplier_group" name="filter[supplier_group]" type="text"
                    class="mt-1 block w-full" :value="request('filter.supplier_group')" placeholder="Local" />
            </div>

            <div>
                <x-label for="filter_supplier_type" value="Supplier Type" />
                <x-input id="filter_supplier_type" name="filter[supplier_type]" type="text"
                    class="mt-1 block w-full" :value="request('filter.supplier_type')" placeholder="Food & Beverage" />
            </div>

            <div>
                <x-label for="filter_country" value="Country" />
                <x-input id="filter_country" name="filter[country]" type="text" class="mt-1 block w-full"
                    :value="request('filter.country')" placeholder="Pakistan" />
            </div>

            <div>
                <x-label for="filter_is_transporter" value="Provides Transport" />
                <select id="filter_is_transporter" name="filter[is_transporter]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_transporter') === '1' ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ request('filter.is_transporter') === '0' ? 'selected' : '' }}>No</option>
                </select>
            </div>

            <div>
                <x-label for="filter_is_internal_supplier" value="Internal Supplier" />
                <select id="filter_is_internal_supplier" name="filter[is_internal_supplier]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_internal_supplier') === '1' ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ request('filter.is_internal_supplier') === '0' ? 'selected' : '' }}>No</option>
                </select>
            </div>

            <div>
                <x-label for="filter_disabled" value="Status" />
                <select id="filter_disabled" name="filter[disabled]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="0" {{ request('filter.disabled') === '0' ? 'selected' : '' }}>Active</option>
                    <option value="1" {{ request('filter.disabled') === '1' ? 'selected' : '' }}>Disabled</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$suppliers" :headers="[
            ['label' => '#', 'align' => 'text-center'],
            ['label' => 'Supplier'],
            ['label' => 'Group / Type'],
            ['label' => 'Country', 'align' => 'text-center'],
            ['label' => 'Defaults'],
            ['label' => 'Flags', 'align' => 'text-center'],
            ['label' => 'Actions', 'align' => 'text-center'],
        ]" emptyMessage="No suppliers found." :emptyRoute="route('suppliers.create')" emptyLinkText="Create a supplier">

        @foreach ($suppliers as $index => $supplier)
            <tr class="border-b border-gray-200 dark:border-gray-700 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $suppliers->firstItem() + $index }}
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ $supplier->supplier_name }}
                    </div>
                    <div class="text-xs text-gray-500 uppercase">
                        {{ $supplier->short_name ?: '—' }}
                    </div>
                </td>
                <td class="py-1 px-2">
                    <div class="text-sm text-gray-800 dark:text-gray-200">
                        {{ $supplier->supplier_group ?: '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $supplier->supplier_type ?: '—' }}
                    </div>
                </td>
                <td class="py-1 px-2 text-center">
                    {{ $supplier->country ?: '—' }}
                </td>
                <td class="py-1 px-2 text-sm">
                    <div>
                        <strong class="text-xs text-gray-500">Currency:</strong>
                        {{ optional($supplier->defaultCurrency)->currency_code ?: '—' }}
                    </div>
                    <div>
                        <strong class="text-xs text-gray-500">Bank:</strong>
                        {{ optional($supplier->defaultBankAccount)->account_code ? $supplier->defaultBankAccount->account_code . ' · ' . $supplier->defaultBankAccount->account_name : '—' }}
                    </div>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex flex-col items-center space-y-1">
                        <span
                            class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full {{ $supplier->disabled ? 'bg-gray-200 text-gray-700' : 'bg-emerald-100 text-emerald-700' }}">
                            {{ $supplier->disabled ? 'Disabled' : 'Active' }}
                        </span>
                        <span
                            class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full {{ $supplier->is_transporter ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                            Transporter: {{ $supplier->is_transporter ? 'Yes' : 'No' }}
                        </span>
                        <span
                            class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full {{ $supplier->is_internal_supplier ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600' }}">
                            Internal: {{ $supplier->is_internal_supplier ? 'Yes' : 'No' }}
                        </span>
                    </div>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('suppliers.show', $supplier->id) }}"
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
                        <a href="{{ route('suppliers.edit', $supplier->id) }}"
                            class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                            title="Edit">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('suppliers.destroy', $supplier->id) }}"
                            onsubmit="return confirm('Delete this supplier?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
