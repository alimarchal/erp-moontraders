<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Units of Measure" :createRoute="route('uoms.create')" createLabel="Add Unit"
            createPermission="uom-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('uoms.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_uom_name" value="Unit Name" />
                <x-input id="filter_uom_name" name="filter[uom_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.uom_name')" placeholder="e.g., Kilogram" />
            </div>

            <div>
                <x-label for="filter_symbol" value="Symbol" />
                <x-input id="filter_symbol" name="filter[symbol]" type="text" class="mt-1 block w-full"
                    :value="request('filter.symbol')" placeholder="e.g., kg" />
            </div>

            <div>
                <x-label for="filter_description" value="Description Contains" />
                <x-input id="filter_description" name="filter[description]" type="text" class="mt-1 block w-full"
                    :value="request('filter.description')" placeholder="Search description" />
            </div>

            <div>
                <x-label for="filter_must_be_whole_number" value="Quantity Type" />
                <select id="filter_must_be_whole_number" name="filter[must_be_whole_number]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($quantityOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.must_be_whole_number') === (string) $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_enabled" value="Status" />
                <select id="filter_enabled" name="filter[enabled]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.enabled') === (string) $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$uoms" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Unit Name'],
        ['label' => 'Symbol'],
        ['label' => 'Description'],
        ['label' => 'Quantity Type', 'align' => 'text-center'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No units found." :emptyRoute="route('uoms.create')" emptyLinkText="Add a unit">
        @foreach ($uoms as $index => $uom)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $uoms->firstItem() + $index }}
                </td>
                <td class="py-1 px-2 font-semibold">
                    {{ $uom->uom_name }}
                </td>
                <td class="py-1 px-2 uppercase">
                    {{ $uom->symbol ?? '—' }}
                </td>
                <td class="py-1 px-2">
                    {{ $uom->description ?? '—' }}
                </td>
                <td class="py-1 px-2 text-center">
                    <span
                        class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $uom->must_be_whole_number ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ $uom->must_be_whole_number ? 'Whole Numbers' : 'Any Quantity' }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    <span
                        class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $uom->enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        {{ $uom->enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('uoms.show', $uom) }}"
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
                        @can('uom-edit')
                            <a href="{{ route('uoms.edit', $uom) }}"
                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>