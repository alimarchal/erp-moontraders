<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Vehicle Report" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 14px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .print-only {
                display: none;
            }

            .vehicle-link {
                color: inherit;
                text-decoration: none;
            }

            .vehicle-link:hover {
                text-decoration: underline;
            }

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;

                    @bottom-center {
                        content: "Page " counter(page) " of " counter(pages);
                    }
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    counter-reset: page 1;
                }

                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 10px !important;
                    box-shadow: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 11px !important;
                    width: 100% !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 8px !important;
                }

                .print-info {
                    font-size: 9px !important;
                    margin-top: 5px !important;
                    margin-bottom: 10px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }

                .page-footer {
                    display: none;
                }

                .vehicle-link,
                .vehicle-link:visited,
                .vehicle-link:hover {
                    color: #000 !important;
                    text-decoration: none !important;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.vehicle.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_vehicle_number" value="Vehicle Number" />
                <x-input id="filter_vehicle_number" name="filter[vehicle_number]" type="text" class="mt-1 block w-full"
                    :value="request('filter.vehicle_number')" placeholder="Search by vehicle number" />
            </div>

            <div>
                <x-label for="filter_registration_number" value="Registration Number" />
                <x-input id="filter_registration_number" name="filter[registration_number]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.registration_number')"
                    placeholder="ABC-123" />
            </div>

            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($supplierOptions as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('filter.supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_employee_id" value="Salesman (Assigned)" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Salesmen</option>
                    @foreach ($employeeOptions as $employee)
                        <option value="{{ $employee->id }}" {{ request('filter.employee_id') == $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_vehicle_type" value="Vehicle Type" />
                <select id="filter_vehicle_type" name="filter[vehicle_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach ($typeOptions as $type)
                        <option value="{{ $type }}" {{ request('filter.vehicle_type') == $type ? 'selected' : '' }}>
                            {{ $type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.is_active') === (string) $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" {{ $currentPerPage === $option ? 'selected' : '' }}>
                            {{ number_format($option) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Vehicle Report<br>
                    Total Records: {{ number_format($vehicles->total()) }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;" class="text-center">Sr#</th>
                            <th style="width: 200px;" class="text-center">Supplier</th>
                            <th style="width: 130px;" class="text-center">Van #</th>
                            <th style="width: 130px;" class="text-center">Vehicle Type</th>
                            <th style="width: 180px;">Salesman</th>
                            <th style="width: 150px;">Driver</th>
                            <th style="width: 90px;" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $groupedVehicles = $vehicles->groupBy(fn($v) => $v->supplier_id ?? 0);
                        @endphp
                        @forelse ($groupedVehicles as $supplierId => $supplierVehicles)
                            @foreach ($supplierVehicles as $idx => $vehicle)
                                <tr>
                                    <td class="text-center" style="vertical-align: middle;">
                                        {{ $vehicles->firstItem() + $vehicles->search(fn($v) => $v->id === $vehicle->id) }}
                                    </td>
                                    @if ($idx === 0)
                                        <td rowspan="{{ $supplierVehicles->count() }}"
                                            class="text-center"
                                            style="vertical-align: middle; font-weight: 600; background-color: #f9fafb;">
                                            {{ $vehicle->supplier?->supplier_name ?? '-' }}
                                        </td>
                                    @endif
                                    <td class="text-center" style="vertical-align: middle;">
                                        <a href="{{ route('vehicles.edit', $vehicle) }}" class="vehicle-link">
                                            {{ $vehicle->registration_number }}
                                        </a>
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">{{ $vehicle->vehicle_type ?? '-' }}</td>
                                    <td style="vertical-align: middle;">{{ $vehicle->employee?->name ?? '-' }}</td>
                                    <td style="vertical-align: middle;">
                                        {{ $vehicle->driver_name ?? '-' }}<br>
                                        <span class="text-xs text-gray-500">{{ $vehicle->driver_phone ?? '-' }}</span>
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        @if($vehicle->is_active)
                                            <span class="text-green-600 font-semibold">Active</span>
                                        @else
                                            <span class="text-red-600 font-semibold">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-gray-500">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($vehicles->hasPages())
                    <div class="mt-4 no-print">
                        {{ $vehicles->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
