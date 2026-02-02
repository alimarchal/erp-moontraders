<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Salesman Wise Sales Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
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
                .bg-gray-100 {
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
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.daily-sales.salesman-wise')" class="no-print" maxWidth="max-w-8xl">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="start_date" value="Start Date" />
                <x-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="$startDate" />
            </div>
            <div>
                <x-label for="end_date" value="End Date" />
                <x-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="$endDate" />
            </div>
            <div>
                <x-label for="employee_id" value="Salesman" />
                <select id="employee_id" name="employee_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Salesmen</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ $employeeId == $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="vehicle_id" value="Vehicle" />
                <select id="vehicle_id" name="vehicle_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ $vehicleId == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->vehicle_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="warehouse_id" value="Warehouse" />
                <select id="warehouse_id" name="warehouse_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ $warehouseId == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->warehouse_name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
             <div>
                <x-label for="sort_by" value="Sort By" />
                <select id="sort_by" name="sort_by" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="total_sales" {{ $sortBy == 'total_sales' ? 'selected' : '' }}>Total Sales (High to Low)</option>
                    <option value="net_profit" {{ $sortBy == 'net_profit' ? 'selected' : '' }}>Net Profit (High to Low)</option>
                    <option value="gross_profit_margin" {{ $sortBy == 'gross_profit_margin' ? 'selected' : '' }}>GP Margin % (High to Low)</option>
                    <option value="employee_name" {{ $sortBy == 'employee_name' ? 'selected' : '' }}>Salesman Name (A-Z)</option>
                    <option value="settlement_count" {{ $sortBy == 'settlement_count' ? 'selected' : '' }}>Settlement Count (High to Low)</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header --}}
                <p class="text-center font-extrabold mb-2 text-xl report-header">
                    Moon Traders<br>
                    <span class="text-lg">Salesman Wise Sales Report</span><br>
                    <span class="text-xs font-normal">
                        Period: {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }} to
                        {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                    </span>
                    @php
                        $filters = [];
                        if ($employeeId)
                            $filters[] = 'Salesman: ' . ($employees->firstWhere('id', $employeeId)->name ?? '');
                        if ($vehicleId)
                            $filters[] = 'Vehicle: ' . ($vehicles->firstWhere('id', $vehicleId)->vehicle_number ?? '');
                        if ($warehouseId)
                            $filters[] = 'Warehouse: ' . ($warehouses->firstWhere('id', $warehouseId)->warehouse_name ?? '');
                    @endphp
                    @if(count($filters) > 0)
                        <br>
                        <span class="text-xs font-normal">
                            {!! implode(' | ', $filters) !!}
                        </span>
                    @endif
                    <br>
                    <span class="print-only text-xs text-center hidden">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="text-center w-12">Sr#</th>
                            <th class="text-left w-1/4">Salesman</th>
                            <th class="text-left w-1/5">Vehicle</th>
                            <th class="text-center">Sets</th>
                            <th class="text-right">Sold Qty</th>
                            <th class="text-right">Rtn Qty</th>
                            <th class="text-right">Short Qty</th>
                            <th class="text-right">Net Sales</th>
                            <th class="text-right">Expense</th>
                            <th class="text-right">Net Profit</th>
                            <th class="text-right">NP %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($salesmanPerformance as $salesman)
                            <tr>
                                <td class="text-center text-black">
                                    {{ $loop->iteration }}
                                </td>
                                <td class="text-left text-black font-semibold">
                                    {{ $salesman->employee_name }}
                                </td>
                                <td class="text-left text-black">
                                    {{ $salesman->vehicle_number }}
                                </td>
                                <td class="text-center text-black font-mono">
                                    {{ $salesman->settlement_count }}
                                </td>
                                <td class="text-right font-mono font-bold">
                                    {{ number_format($salesman->total_quantity_sold, 0) }}
                                </td>
                                <td class="text-right font-mono text-red-600 print:text-black">
                                    {{ number_format($salesman->total_returned, 0) }}
                                </td>
                                <td class="text-right font-mono text-red-600 print:text-black">
                                    {{ number_format($salesman->total_shortage, 0) }}
                                </td>
                                <td class="text-right font-mono font-bold">
                                    {{ number_format($salesman->total_sales, 2) }}
                                </td>
                                <td class="text-right font-mono text-red-600 print:text-black">
                                    {{ number_format($salesman->expenses_claimed, 2) }}
                                </td>
                                <td class="text-right font-mono font-bold text-green-700 print:text-black">
                                    {{ number_format($salesman->net_profit, 2) }}
                                </td>
                                <td class="text-right font-mono">
                                    @php
                                        $npMargin = $salesman->total_sales > 0 ? ($salesman->net_profit / $salesman->total_sales) * 100 : 0;
                                    @endphp
                                    {{ number_format($npMargin, 2) }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold sticky bottom-0">
                        @if($salesmanPerformance->count() > 0)
                            <tr>
                                <td colspan="3" class="py-2 px-2 text-right">
                                    Total ({{ $salesmanPerformance->count() }} Rows):
                                </td>
                                <td class="py-2 px-2 text-center font-mono">
                                    {{ $totals['settlement_count'] }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals['total_quantity_sold'], 0) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals['total_returned'], 0) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals['total_shortage'], 0) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals['total_sales'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals['expenses_claimed'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals['net_profit'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    @php
                                        $totalNpMargin = $totals['total_sales'] > 0 ? ($totals['net_profit'] / $totals['total_sales']) * 100 : 0;
                                    @endphp
                                    {{ number_format($totalNpMargin, 2) }}%
                                </td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            $(document).ready(function () {
                $('#employee_id').select2({
                    width: '100%',
                    placeholder: "All Salesmen",
                    allowClear: true
                });
                $('#vehicle_id').select2({
                    width: '100%',
                    placeholder: "All Vehicles",
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>