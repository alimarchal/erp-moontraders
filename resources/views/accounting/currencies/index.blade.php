<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Currencies" :createRoute="route('currencies.create')" createLabel="Add Currency"
            :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('currencies.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_currency_code" value="Currency Code" />
                <x-input id="filter_currency_code" name="filter[currency_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.currency_code')"
                    placeholder="e.g., USD" />
            </div>

            <div>
                <x-label for="filter_currency_name" value="Currency Name" />
                <x-input id="filter_currency_name" name="filter[currency_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.currency_name')" placeholder="Search by name" />
            </div>

            <div>
                <x-label for="filter_is_base_currency" value="Currency Type" />
                <select id="filter_is_base_currency" name="filter[is_base_currency]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($baseOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.is_base_currency')===(string) $value ? 'selected'
                        : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.is_active')===(string) $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$currencies" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Code'],
        ['label' => 'Name'],
        ['label' => 'Symbol', 'align' => 'text-center'],
        ['label' => 'Exchange Rate', 'align' => 'text-center'],
        ['label' => 'Type', 'align' => 'text-center'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No currencies found." :emptyRoute="route('currencies.create')" emptyLinkText="Add a currency">
        @foreach ($currencies as $index => $currency)
        <tr class="border-b border-gray-200 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $currencies->firstItem() + $index }}
            </td>
            <td class="py-1 px-2 font-semibold uppercase">
                {{ $currency->currency_code }}
            </td>
            <td class="py-1 px-2">
                {{ $currency->currency_name }}
            </td>
            <td class="py-1 px-2 text-center">
                {{ $currency->currency_symbol }}
            </td>
            <td class="py-1 px-2 text-center">
                {{ number_format($currency->exchange_rate, 6) }}
            </td>
            <td class="py-1 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $currency->is_base_currency ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $currency->is_base_currency ? 'Base' : 'Secondary' }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $currency->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    {{ $currency->is_active ? 'Active' : 'Inactive' }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('currencies.show', $currency) }}"
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
                    <a href="{{ route('currencies.edit', $currency) }}"
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