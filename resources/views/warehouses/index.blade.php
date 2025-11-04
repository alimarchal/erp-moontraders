<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Warehouses" :createRoute="route('warehouses.create')" createLabel="Add Warehouse"
            :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('warehouses.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_warehouse_name" value="Warehouse Name" />
                <x-input id="filter_warehouse_name" name="filter[warehouse_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.warehouse_name')" placeholder="Central Warehouse" />
            </div>

            <div>
                <x-label for="filter_company_id" value="Company" />
                <select id="filter_company_id" name="filter[company_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Companies</option>
                    @foreach ($companyOptions as $company)
                        <option value="{{ $company->id }}"
                            {{ (string) request('filter.company_id') === (string) $company->id ? 'selected' : '' }}>
                            {{ $company->company_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_warehouse_type_id" value="Warehouse Type" />
                <select id="filter_warehouse_type_id" name="filter[warehouse_type_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach ($warehouseTypeOptions as $type)
                        <option value="{{ $type->id }}"
                            {{ (string) request('filter.warehouse_type_id') === (string) $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_group" value="Group Warehouse" />
                <select id="filter_is_group" name="filter[is_group]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_group') === '1' ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ request('filter.is_group') === '0' ? 'selected' : '' }}>No</option>
                </select>
            </div>

            <div>
                <x-label for="filter_disabled" value="Disabled Status" />
                <select id="filter_disabled" name="filter[disabled]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="0" {{ request('filter.disabled') === '0' ? 'selected' : '' }}>Active</option>
                    <option value="1" {{ request('filter.disabled') === '1' ? 'selected' : '' }}>Disabled</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$warehouses" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Name'],
        ['label' => 'Company'],
        ['label' => 'Type'],
        ['label' => 'Group', 'align' => 'text-center'],
        ['label' => 'Disabled', 'align' => 'text-center'],
        ['label' => 'Rejected', 'align' => 'text-center'],
        ['label' => 'Account'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No warehouses found." :emptyRoute="route('warehouses.create')" emptyLinkText="Add a warehouse">
        @foreach ($warehouses as $index => $warehouse)
        <tr class="border-b border-gray-200 dark:border-gray-700 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $warehouses->firstItem() + $index }}
            </td>
            <td class="py-1 px-2 font-semibold">
                {{ $warehouse->warehouse_name }}
            </td>
            <td class="py-1 px-2">
                {{ $warehouse->company?->company_name ?? '-' }}
            </td>
            <td class="py-1 px-2">
                {{ $warehouse->warehouseType?->name ?? '-' }}
            </td>
            <td class="py-1 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $warehouse->is_group ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $warehouse->is_group ? 'Yes' : 'No' }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $warehouse->disabled ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                    {{ $warehouse->disabled ? 'Yes' : 'No' }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $warehouse->is_rejected_warehouse ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $warehouse->is_rejected_warehouse ? 'Yes' : 'No' }}
                </span>
            </td>
            <td class="py-1 px-2">
                @php($account = $warehouse->account)
                {{ $account ? ($account->account_code . ' - ' . $account->account_name) : '-' }}
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('warehouses.show', $warehouse) }}"
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
                    <a href="{{ route('warehouses.edit', $warehouse) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                        title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </a>
                    <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}"
                        onsubmit="return confirm('Are you sure you want to delete this warehouse?');">
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
