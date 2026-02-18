<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Goods Issues" :createRoute="route('goods-issues.create')" createLabel=""
            createPermission="goods-issue-create" :showSearch="true" :showRefresh="true" />
    </x-slot>

    <x-filter-section :action="route('goods-issues.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_issue_number" value="Issue Number" />
                <x-input id="filter_issue_number" name="filter[issue_number]" type="text" class="mt-1 block w-full"
                    :value="request('filter.issue_number')" placeholder="GI-2025-0001" />
            </div>

            <div>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ request('filter.warehouse_id') == $warehouse->id ? 'selected'
                        : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ request('filter.vehicle_id') == $vehicle->id ? 'selected' : ''
                                                                                                                }}>
                            {{ $vehicle->vehicle_number }} ({{ $vehicle->vehicle_type }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_employee_id" value="Salesman" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Salesmen</option>
                    @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ request('filter.employee_id') == $employee->id ? 'selected' :
                        '' }}>
                                        {{ $employee->full_name }}
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('filter.status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="issued" {{ request('filter.status') === 'issued' ? 'selected' : '' }}>Issued</option>
                </select>
            </div>

            <div>
                <x-label for="filter_issue_date_from" value="Issue Date From" />
                <x-input id="filter_issue_date_from" name="filter[issue_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.issue_date_from')" />
            </div>

            <div>
                <x-label for="filter_issue_date_to" value="Issue Date To" />
                <x-input id="filter_issue_date_to" name="filter[issue_date_to]" type="date" class="mt-1 block w-full"
                    :value="request('filter.issue_date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$goodsIssues" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Issue Number'],
        ['label' => 'Issue Date', 'align' => 'text-center'],
        ['label' => 'Warehouse'],
        ['label' => 'Vehicle'],
        ['label' => 'Salesman'],
        ['label' => 'Total Value', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No goods issues found."
        :emptyRoute="route('goods-issues.create')" emptyLinkText="Create a Goods Issue">

        @foreach ($goodsIssues as $index => $gi)
            <tr class="border-b border-gray-200 text-sm tabular-nums">
                <td class="py-0 px-2 text-center">
                    {{ $goodsIssues->firstItem() + $index }}
                </td>
                <td class="py-0 px-2">
                    <div class="font-semibold text-gray-900">
                        {{ $gi->issue_number }}
                    </div>
                </td>
                <td class="py-0 px-2 text-center">
                    {{ \Carbon\Carbon::parse($gi->issue_date)->format('d M Y') }}
                </td>
                <td class="py-0 px-2">
                    {{ $gi->warehouse->warehouse_name }}
                </td>
                <td class="py-0 px-2">
                    {{ $gi->vehicle->vehicle_number }}
                </td>
                <td class="py-0 px-2">
                    <div>
                        @if($gi->supplier && $gi->supplier->supplier_name)
                            <span class="font-semibold text-gray-900">{{ $gi->supplier->supplier_name }}</span>
                            <span class="text-gray-600"> - {{ $gi->employee->name }}</span>
                        @else
                            {{ $gi->employee->name }}
                        @endif
                    </div>
                </td>
                <td class="py-0 px-2 text-right">
                    <div class="font-semibold text-gray-900">
                        Rs {{ number_format($gi->total_value, 2) }}
                    </div>
                </td>
                <td class="py-0 px-2 text-center">
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full
                                                                                                                {{ $gi->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                                                                                                {{ $gi->status === 'issued' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                        {{ ucfirst($gi->status) }}
                    </span>
                </td>
                <td class="py-0 px-2 text-center">
                    <div class="flex justify-center flex-wrap gap-2">
                        <a href="{{ route('goods-issues.show', $gi) }}"
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

                        @if ($gi->status === 'draft')
                            @can('goods-issue-edit')
                                <a href="{{ route('goods-issues.edit', $gi) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                    title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                            @endcan

                            @can('goods-issue-delete')
                                <button type="button" x-data
                                    @click="$dispatch('open-delete-gi-modal', { url: '{{ route('goods-issues.destroy', $gi) }}' })"
                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                    title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            @endcan
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot name="footer">
            <tr class="text-gray-900 border-t-2 border-gray-300">
                <td class="py-1 px-1 text-right" colspan="6">Total:</td>
                <td class="py-1 px-1 text-right font-bold text-lg">Rs {{ number_format($totalValue, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </x-slot>
    </x-data-table>

    <x-alpine-confirmation-modal
        event-name="open-delete-gi-modal"
        title="Delete Goods Issue"
        message="Are you sure you want to delete this draft goods issue? This action cannot be undone."
        confirm-button-text="Delete"
        csrf-method="DELETE"
    />
</x-app-layout>