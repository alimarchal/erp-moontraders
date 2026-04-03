<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Investment Opening Balances"
            :createRoute="route('investment-opening-balances.create')"
            createLabel="Add Balance"
            createPermission="investment-opening-balance-create"
            :showSearch="true"
            :showRefresh="true"
            backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('investment-opening-balances.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ request('filter.supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_description" value="Description" />
                <x-input id="filter_description" name="filter[description]" type="text"
                    class="mt-1 block w-full" :value="request('filter.description')"
                    placeholder="BANK_OPENING_AMOUNT" />
            </div>

            <div>
                <x-label for="filter_date_from" value="Date (From)" />
                <x-input id="filter_date_from" name="filter[date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.date_from')" />
            </div>

            <div>
                <x-label for="filter_date_to" value="Date (To)" />
                <x-input id="filter_date_to" name="filter[date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$balances" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Supplier'],
        ['label' => 'Date', 'align' => 'text-center'],
        ['label' => 'Description'],
        ['label' => 'Amount', 'align' => 'text-right'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No investment opening balances found."
        :emptyRoute="route('investment-opening-balances.create')" emptyLinkText="Add a balance">
        @foreach ($balances as $index => $balance)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $balances->firstItem() + $index }}
                </td>
                <td class="py-1 px-2 font-semibold">
                    {{ $balance->supplier?->supplier_name ?? '-' }}
                </td>
                <td class="py-1 px-2 text-center">
                    {{ $balance->date->format('d-m-Y') }}
                </td>
                <td class="py-1 px-2">
                    {{ $balance->description }}
                </td>
                <td class="py-1 px-2 text-right font-mono">
                    {{ number_format($balance->amount, 2) }}
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('investment-opening-balances.show', $balance) }}"
                            class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                            title="View">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        @can('investment-opening-balance-edit')
                            <a href="{{ route('investment-opening-balances.edit', $balance) }}"
                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        @endcan
                        @can('investment-opening-balance-delete')
                            <form method="POST"
                                action="{{ route('investment-opening-balances.destroy', $balance) }}"
                                onsubmit="return confirm('Are you sure you want to delete this record?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                    title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
