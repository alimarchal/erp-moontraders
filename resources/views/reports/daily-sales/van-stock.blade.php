<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Van Stock Report" :showSearch="true" :showRefresh="true" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 12px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 4px 6px;
                white-space: nowrap;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 15mm 5mm 20mm 5mm;

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
                    background-color: white !important;
                }

                .max-w-7xl,
                .max-w-8xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    background-color: white !important;
                    margin: 0 !important;
                    padding: 10px !important;
                    border: none !important;
                    box-shadow: none !important;
                }

                .shadow-xl,
                .shadow-lg {
                    box-shadow: none !important;
                }

                .rounded-lg,
                .sm\:rounded-lg {
                    border-radius: 0 !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 12px !important;
                    width: 100% !important;
                    table-layout: auto;
                }

                .report-table tr {
                    page-break-inside: avoid;
                }

                .report-table th,
                .report-table td {
                    padding: 1px 2px !important;
                    color: #000 !important;
                    background-color: white !important;
                    white-space: normal !important;
                    overflow-wrap: break-word;
                }

                /* Ensure specific background colors are removed in print */
                .bg-gray-100,
                .bg-gray-50,
                .bg-blue-50,
                .bg-red-50,
                .bg-indigo-50,
                .bg-green-50,
                .bg-orange-50,
                .bg-red-100,
                .bg-red-200,
                .bg-green-100,
                .bg-emerald-100 {
                    background-color: white !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 4px !important;
                }

                .print-info {
                    font-size: 8px !important;
                    margin-top: 2px !important;
                    margin-bottom: 5px !important;
                    color: #000 !important;
                }

                /* Header visibility in print */
                .report-header {
                    display: block !important;
                }

                .print-only {
                    display: block !important;
                }

                .page-footer {
                    display: none;
                }
            }

            /* Screen styles for header */
            .report-header {
                /* Visible on screen by default now */
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.daily-sales.van-stock')" class="no-print" maxWidth="max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="vehicle_id" value="Vehicle" />
                <select id="vehicle_id" name="vehicle_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ request('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->vehicle_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="employee_id" value="Salesman" />
                <select id="employee_id" name="employee_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Salesmen</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="settlement_number" value="Settlement #" />
                <x-input id="settlement_number" name="settlement_number" type="text" class="mt-1 block w-full"
                    :value="request('settlement_number')" placeholder="Search by partial #" />
            </div>
            <div>
                <x-label for="goods_issue_number" value="Goods Issue #" />
                <x-input id="goods_issue_number" name="goods_issue_number" type="text" class="mt-1 block w-full"
                    :value="request('goods_issue_number')" placeholder="Search by partial #" />
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header --}}
                <p class="text-center font-extrabold mb-2 text-xl report-header">
                    Moon Traders<br>
                    <span class="text-lg">Van Stock Report</span><br>
                    <span class="text-xs font-normal">
                        Date: {{ now()->format('d-M-Y') }}
                    </span>
                    @php
                        $filtersText = [];
                        if (request('vehicle_id'))
                            $filtersText[] = 'Vehicle: ' . ($vehicles->firstWhere('id', request('vehicle_id'))->vehicle_number ?? '');
                        if (request('employee_id'))
                            $filtersText[] = 'Salesman: ' . ($employees->firstWhere('id', request('employee_id'))->name ?? '');
                        if (request('settlement_number'))
                            $filtersText[] = 'Settlement #: ' . request('settlement_number');
                        if (request('goods_issue_number'))
                            $filtersText[] = 'GI #: ' . request('goods_issue_number');
                    @endphp
                    @if(count($filtersText) > 0)
                        <br>
                        <span class="text-xs font-normal">
                            {!! implode(' | ', $filtersText) !!}
                        </span>
                    @endif
                    <br>
                    <span class="print-only text-xs text-center hidden">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                @if($groupedStock->count() > 0)
                    <table class="report-table">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="text-center w-12">Sr.#</th>
                                <th class="text-center">CMK</th>
                                <th class="text-center">Member</th>
                                <th class="text-center">GI No</th>
                                <th class="text-left w-24">SKU Code</th>
                                <th class="text-left">SKU</th>
                                <th class="text-right">Cost Unit</th>
                                <th class="text-right">Selling Price</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $globalIndex = 1; @endphp
                            @foreach($groupedStock as $vehicleId => $stocks)
                                @foreach($stocks as $stock)
                                    <tr>
                                        <td class="text-center text-black">{{ $globalIndex++ }}</td>
                                        <td class="text-center font-bold text-black">{{ $stock->vehicle_number }}</td>
                                        <td class="text-center text-black">{{ $stock->employee_name ?? 'N/A' }}</td>
                                        <td class="text-center font-mono text-black">{{ $stock->latest_issue_number ?? '-' }}</td>
                                        <td class="text-left font-mono text-black">{{ $stock->product_code }}</td>
                                        <td class="text-left text-black">{{ $stock->product_name }}</td>
                                        <td class="text-right tabular-nums font-mono text-black">{{ number_format($stock->issue_unit_cost ?? $stock->average_cost, 2) }}</td>
                                        <td class="text-right tabular-nums font-mono text-black">{{ number_format($stock->issue_selling_price ?? 0, 2) }}</td>
                                        <td class="text-right tabular-nums font-mono text-black">{{ number_format($stock->quantity_on_hand, 2) }}</td>
                                        <td class="text-right tabular-nums font-mono text-black">{{ number_format($stock->total_value, 2) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-extrabold sticky bottom-0 border-t-2 border-black">
                            @php
                                $grandTotal = $groupedStock->flatten(1)->sum('total_value');
                            @endphp
                            <tr>
                                <td colspan="9" class="py-2 px-2 text-right">Grand Total:</td>
                                <td class="py-2 px-2 text-right tabular-nums font-mono">{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>

                @else
                    <div class="p-6 text-center text-gray-500">
                        No stock found for the selected criteria.
                    </div>
                @endif
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            $(document).ready(function () {
                $('#vehicle_id').select2({
                    width: '100%',
                    placeholder: "All Vehicles",
                    allowClear: true
                });
                $('#employee_id').select2({
                    width: '100%',
                    placeholder: "All Salesmen",
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>