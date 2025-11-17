<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Goods Issues" :createRoute="route('goods-issues.create')" createLabel="New Goods Issue"
            :showSearch="true" :showRefresh="true" />
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
                    <option value="{{ $warehouse->id }}" {{ request('filter.warehouse_id')==$warehouse->id ? 'selected'
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
                    <option value="{{ $vehicle->id }}" {{ request('filter.vehicle_id')==$vehicle->id ? 'selected' : ''
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
                    <option value="{{ $employee->id }}" {{ request('filter.employee_id')==$employee->id ? 'selected' :
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
                    <option value="draft" {{ request('filter.status')==='draft' ? 'selected' : '' }}>Draft</option>
                    <option value="issued" {{ request('filter.status')==='issued' ? 'selected' : '' }}>Issued</option>
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
            ['label' => 'Supplier'],
            ['label' => 'Total Value', 'align' => 'text-right'],
            ['label' => 'Status', 'align' => 'text-center'],
            ['label' => 'Actions', 'align' => 'text-center'],
        ]" emptyMessage="No goods issues found." :emptyRoute="route('goods-issues.create')"
        emptyLinkText="Create a Goods Issue">

        @foreach ($goodsIssues as $index => $gi)
        <tr class="border-b border-gray-200 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $goodsIssues->firstItem() + $index }}
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold text-gray-900">
                    {{ $gi->issue_number }}
                </div>
            </td>
            <td class="py-1 px-2 text-center">
                {{ \Carbon\Carbon::parse($gi->issue_date)->format('d M Y') }}
            </td>
            <td class="py-1 px-2">
                {{ $gi->warehouse->warehouse_name }}
            </td>
            <td class="py-1 px-2">
                {{ $gi->vehicle->vehicle_number }}
            </td>
            <td class="py-1 px-2">
                {{ $gi->employee->name }}
            </td>
            <td class="py-1 px-2">
                <span class="text-xs text-gray-600">{{ $gi->employee->supplier->company_name ?? 'N/A' }}</span>
            </td>
            <td class="py-1 px-2 text-right">
                <div class="font-semibold text-gray-900">
                    Rs {{ number_format($gi->total_value, 2) }}
                </div>
            </td>
            <td class="py-1 px-2 text-center">
                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full
                        {{ $gi->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                        {{ $gi->status === 'issued' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                    {{ ucfirst($gi->status) }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <a href="{{ route('goods-issues.show', $gi) }}" class="text-blue-600 hover:text-blue-900 mr-2">View</a>
                @if ($gi->status === 'draft')
                <a href="{{ route('goods-issues.edit', $gi) }}"
                    class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</a>
                @endif
            </td>
        </tr>
        @endforeach

    </x-data-table>
</x-app-layout>