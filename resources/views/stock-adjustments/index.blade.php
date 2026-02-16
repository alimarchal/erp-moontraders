<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Stock Adjustments" :createRoute="route('stock-adjustments.create')"
            createLabel="New Adjustment" createPermission="stock-adjustment-create" :showSearch="true"
            :showRefresh="true" />
    </x-slot>

    <x-filter-section :action="route('stock-adjustments.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_adjustment_number" value="Adjustment Number" />
                <x-input id="filter_adjustment_number" name="filter[adjustment_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.adjustment_number')" placeholder="SA-2026-0001" />
            </div>

            <div>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ request('filter.warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->warehouse_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_adjustment_type" value="Adjustment Type" />
                <select id="filter_adjustment_type" name="filter[adjustment_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    <option value="damage" {{ request('filter.adjustment_type') === 'damage' ? 'selected' : '' }}>Damage
                    </option>
                    <option value="theft" {{ request('filter.adjustment_type') === 'theft' ? 'selected' : '' }}>Theft
                    </option>
                    <option value="count_variance" {{ request('filter.adjustment_type') === 'count_variance' ? 'selected' : '' }}>Count Variance
                    </option>
                    <option value="expiry" {{ request('filter.adjustment_type') === 'expiry' ? 'selected' : '' }}>Expiry
                    </option>
                    <option value="other" {{ request('filter.adjustment_type') === 'other' ? 'selected' : '' }}>Other
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('filter.status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="posted" {{ request('filter.status') === 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="cancelled" {{ request('filter.status') === 'cancelled' ? 'selected' : '' }}>Cancelled
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_adjustment_date_from" value="Date From" />
                <x-input id="filter_adjustment_date_from" name="filter[adjustment_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.adjustment_date_from')" />
            </div>

            <div>
                <x-label for="filter_adjustment_date_to" value="Date To" />
                <x-input id="filter_adjustment_date_to" name="filter[adjustment_date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.adjustment_date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$adjustments" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Adjustment Number'],
        ['label' => 'Date', 'align' => 'text-center'],
        ['label' => 'Warehouse'],
        ['label' => 'Type', 'align' => 'text-center'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Created By'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No stock adjustments found."
        :emptyRoute="route('stock-adjustments.create')" emptyLinkText="Create a Stock Adjustment">

        @foreach ($adjustments as $index => $adjustment)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $adjustments->firstItem() + $index }}
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold text-gray-900">
                        {{ $adjustment->adjustment_number }}
                    </div>
                    @if ($adjustment->reason)
                        <div class="text-xs text-gray-500 truncate max-w-[200px]">
                            {{ $adjustment->reason }}
                        </div>
                    @endif
                </td>
                <td class="py-1 px-2 text-center">
                    {{ $adjustment->adjustment_date->format('d M Y') }}
                </td>
                <td class="py-1 px-2">
                    {{ $adjustment->warehouse->warehouse_name }}
                </td>
                <td class="py-1 px-2 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full
                                {{ $adjustment->adjustment_type === 'damage' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $adjustment->adjustment_type === 'theft' ? 'bg-purple-100 text-purple-700' : '' }}
                                {{ $adjustment->adjustment_type === 'count_variance' ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $adjustment->adjustment_type === 'expiry' ? 'bg-orange-100 text-orange-700' : '' }}
                                {{ $adjustment->adjustment_type === 'other' ? 'bg-gray-200 text-gray-700' : '' }}">
                        {{ ucfirst(str_replace('_', ' ', $adjustment->adjustment_type)) }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full
                                {{ $adjustment->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                {{ $adjustment->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                {{ $adjustment->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}">
                        {{ ucfirst($adjustment->status) }}
                    </span>
                </td>
                <td class="py-1 px-2">
                    {{ $adjustment->createdBy?->name }}
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('stock-adjustments.show', $adjustment->id) }}"
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
                        @if ($adjustment->isDraft())
                            @can('stock-adjustment-edit')
                                <a href="{{ route('stock-adjustments.edit', $adjustment->id) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                    title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                            @endcan
                            @can('stock-adjustment-delete')
                                <form action="{{ route('stock-adjustments.destroy', $adjustment->id) }}" method="POST"
                                    class="inline-block"
                                    onsubmit="return confirm('Are you sure you want to delete this stock adjustment?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                        title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>