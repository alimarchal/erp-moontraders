<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Advance Tax Sales Register" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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
                color: #000;
            }

            .print-only {
                display: none;
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
            }

            .page-footer {
                display: none;
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.advance-tax-sales-register.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">

            {{-- Supplier --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_supplier_id" value="Supplier" />
                    @if($selectedSupplierId)
                        <a href="{{ route('reports.advance-tax-sales-register.index', array_merge(request()->except(['filter.supplier_id', 'page']), ['filter[supplier_id]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">Clear</a>
                    @endif
                </div>
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $selectedSupplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Designation --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_designation" value="Designation" />
                    @if($selectedDesignation)
                        <a href="{{ route('reports.advance-tax-sales-register.index', array_merge(request()->except(['filter.designation', 'page']), ['filter[designation]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">Clear</a>
                    @endif
                </div>
                <select id="filter_designation" name="filter[designation]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Designations</option>
                    @foreach($designations as $d)
                        <option value="{{ $d }}" {{ $selectedDesignation == $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Salesman --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_employee_id" value="Salesman" />
                    @if($selectedEmployeeId)
                        <a href="{{ route('reports.advance-tax-sales-register.index', array_merge(request()->except(['filter.employee_id', 'page']), ['filter[employee_id]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">Clear</a>
                    @endif
                </div>
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Salesmen</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ $selectedEmployeeId == $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Vehicle --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_vehicle_id" value="Vehicle" />
                    @if($selectedVehicleId)
                        <a href="{{ route('reports.advance-tax-sales-register.index', array_merge(request()->except(['filter.vehicle_id', 'page']), ['filter[vehicle_id]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">Clear</a>
                    @endif
                </div>
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ $selectedVehicleId == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->registration_number }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Warehouse --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_warehouse_id" value="Warehouse" />
                    @if($selectedWarehouseId)
                        <a href="{{ route('reports.advance-tax-sales-register.index', array_merge(request()->except(['filter.warehouse_id', 'page']), ['filter[warehouse_id]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">Clear</a>
                    @endif
                </div>
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ $selectedWarehouseId == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Date From --}}
            <div>
                <x-label for="filter_start_date" value="Date From" />
                <x-input id="filter_start_date" name="filter[start_date]" type="date" class="mt-1 block w-full"
                    :value="$startDate" />
            </div>

            {{-- Date To --}}
            <div>
                <x-label for="filter_end_date" value="Date To" />
                <x-input id="filter_end_date" name="filter[end_date]" type="date" class="mt-1 block w-full"
                    :value="$endDate" />
            </div>

        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            @if(!$hasFilter)
                <div class="flex flex-col items-center justify-center py-16 text-gray-500">
                    <svg class="w-12 h-12 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
                    </svg>
                    <p class="text-lg font-medium">Please select a supplier to view the report.</p>
                    <p class="text-sm mt-1">Use the filter above to select a supplier and date range.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <p class="text-center font-extrabold mb-2">
                        Moon Traders<br>
                        Advance Tax Sales Register<br>
                        <span class="text-sm font-semibold">Supplier: {{ $selectedSupplier?->supplier_name ?? 'All' }}</span>
                        @if($selectedDesignation)
                            <br><span class="text-sm font-semibold">Designation: {{ $selectedDesignation }}</span>
                        @endif
                        @if($selectedEmployeeId)
                            <br><span class="text-sm font-semibold">Salesman: {{ $employees->firstWhere('id', $selectedEmployeeId)?->name }}</span>
                        @endif
                        @if($selectedVehicleId)
                            <br><span class="text-sm font-semibold">Vehicle: {{ $vehicles->firstWhere('id', $selectedVehicleId)?->registration_number }}</span>
                        @endif
                        @if($selectedWarehouseId)
                            <br><span class="text-sm font-semibold">Warehouse: {{ $warehouses->firstWhere('id', $selectedWarehouseId)?->name }}</span>
                        @endif
                        <br>For the period {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }} to
                        {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                        <br>
                        <span class="print-only print-info text-xs text-center">
                            Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                        </span>
                    </p>

                    <table class="report-table">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="w-10 text-center font-bold">#</th>
                                <th class="text-center font-bold px-2 whitespace-nowrap">Date</th>
                                <th class="text-center font-bold px-2">
                                    Sale
                                    <div class="text-xs font-normal text-gray-500">Total Sales</div>
                                </th>
                                <th class="text-center font-bold px-2 whitespace-nowrap">
                                    Advance Tax (2.5%)
                                    <div class="text-xs font-normal text-gray-500">Sale × 2.5%</div>
                                </th>
                                <th class="text-center font-bold px-2 whitespace-nowrap">
                                    Advance Tax Benefits<br>(NTN Customer)
                                    <div class="text-xs font-normal text-gray-500">Account 1161</div>
                                </th>
                                <th class="text-center font-bold px-2 whitespace-nowrap">
                                    Advance Tax<br>Received From Customer
                                    <div class="text-xs font-normal text-gray-500">Adv. Tax − Benefits</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $index => $row)
                                <tr>
                                    <td class="text-center text-black">{{ $index + 1 }}</td>
                                    <td class="text-center px-2 whitespace-nowrap text-black">
                                        {{ \Carbon\Carbon::parse($row['date'])->format('d-M-y') }}
                                    </td>
                                    <td class="text-right font-mono px-2 text-black">
                                        {{ number_format($row['sale'], 2) }}
                                    </td>
                                    <td class="text-right font-mono px-2 text-black">
                                        {{ number_format($row['advance_tax'], 2) }}
                                    </td>
                                    <td class="text-right font-mono px-2 text-black">
                                        {{ number_format($row['benefits'], 2) }}
                                    </td>
                                    <td class="text-right font-mono px-2 text-black">
                                        {{ number_format($row['received'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="2" class="text-right px-2 py-1 text-black">Total</td>
                                <td class="text-right font-mono px-2 py-1 text-black">
                                    {{ number_format($totalSale, 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1 text-black">
                                    {{ number_format($totalAdvanceTax, 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1 text-black">
                                    {{ number_format($totalBenefits, 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1 text-black">
                                    {{ number_format($totalReceived, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
