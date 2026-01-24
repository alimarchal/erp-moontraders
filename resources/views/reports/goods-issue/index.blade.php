<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Goods Issue Report" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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
                padding: 3px 4px;
                word-wrap: break-word;
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
                    font-size: 10px !important;
                    width: 100% !important;
                    table-layout: auto;
                }

                .report-table tr {
                    page-break-inside: avoid;
                }

                .report-table .text-right {
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }

                .text-green-600,
                .text-red-600 {
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
        </style>
    @endpush

    <x-filter-section :action="route('reports.goods-issue.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Dates -->
            <div>
                <x-label for="filter_start_date" value="Start Date" />
                <x-input id="filter_start_date" name="filter[start_date]" type="date" class="mt-1 block w-full"
                    :value="$startDate" />
            </div>
            <div>
                <x-label for="filter_end_date" value="End Date" />
                <x-input id="filter_end_date" name="filter[end_date]" type="date" class="mt-1 block w-full"
                    :value="$endDate" />
            </div>

            <!-- Salesman (Employee) -->
            <div>
                <x-label for="filter_employee_id" value="Salesman / Employee" />
                <select id="filter_employee_id" name="filter[employee_id][]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ in_array($employee->id, $filters['employee_id'] ?? []) ? 'selected' : '' }}>
                            {{ $employee->name }} ({{ $employee->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Vehicle -->
            <div>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ ($filters['vehicle_id'] ?? '') == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->registration_number }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Warehouse -->
            <div>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ ($filters['warehouse_id'] ?? '') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Status -->
            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    <option value="issued" {{ ($filters['status'] ?? '') == 'issued' ? 'selected' : '' }}>Issued</option>
                    <option value="draft" {{ ($filters['status'] ?? '') == 'draft' ? 'selected' : '' }}>Draft</option>
                </select>
            </div>

            <!-- Issue Number -->
            <div>
                <x-label for="filter_issue_number" value="Issue #" />
                <x-input id="filter_issue_number" name="filter[issue_number]" type="text" class="mt-1 block w-full"
                    placeholder="Search Issue #..." value="{{ $filters['issue_number'] ?? '' }}" />
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2 text-xl">
                    Moon Traders<br>
                    <span class="text-lg">Goods Issue Report</span><br>
                    <span class="text-xs font-normal">
                        Period: {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }} to
                        {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                        @if($filterSummary)
                            <br>{{ $filterSummary }}
                        @endif
                    </span>
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="w-10">S.No</th>
                            <th class="w-20">SKU Code</th>
                            <th class="w-40">SKU</th>
                            <th class="w-24">Category</th>
                            @foreach($matrixData['dates'] as $date)
                                <th class="w-8 text-center">{{ \Carbon\Carbon::parse($date)->format('j') }}</th>
                            @endforeach
                            <th class="bg-gray-200">Total</th>
                            <th class="bg-yellow-50">G.I Total</th>
                            <th class="bg-orange-50">Avg Cost</th>
                            <th class="bg-indigo-50">Sale</th>
                            <th class="bg-red-50">COGS</th>
                            <th class="bg-green-50">Net Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matrixData['products'] as $product)
                            <tr>
                                <td class="text-center font-mono">{{ $loop->iteration }}</td>
                                <td class="text-xs">{{ $product['product_code'] }}</td>
                                <td class="font-bold">{{ $product['product_name'] }}</td>
                                <td class="text-xs text-gray-600">{{ $product['category_name'] }}</td>

                                @foreach($matrixData['dates'] as $date)
                                    @php
                                        $count = $product['daily_data'][$date]['count'] ?? 0;
                                    @endphp
                                    <td class="text-center font-mono text-black">
                                        @if($count > 0)
                                            <a href="{{ route('goods-issues.index', ['filter[issue_date]' => $date, 'filter[status]' => 'issued', 'filter[product_id]' => $product['product_id']]) }}"
                                                class="hover:underline cursor-pointer text-black" target="_blank">
                                                {{ $count }}
                                            </a>
                                        @else
                                            <span>0</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="text-center font-bold bg-gray-100 font-mono text-black">
                                    <a href="{{ route('goods-issues.index', ['filter[issue_date_from]' => $startDate, 'filter[issue_date_to]' => $endDate, 'filter[status]' => 'issued', 'filter[product_id]' => $product['product_id']]) }}"
                                        class="hover:underline cursor-pointer text-black" target="_blank">
                                        {{ $product['totals']['total_issued_qty'] + 0 }}
                                    </a>
                                </td>
                                <td class="text-right font-mono bg-yellow-50 text-black">
                                    <a href="{{ route('goods-issues.index', ['filter[issue_date_from]' => $startDate, 'filter[issue_date_to]' => $endDate, 'filter[status]' => 'issued', 'filter[product_id]' => $product['product_id']]) }}"
                                        class="hover:underline cursor-pointer text-black" target="_blank">
                                        {{ number_format($product['totals']['total_issued_value'], 2) }}
                                    </a>
                                </td>
                                <td class="text-right font-mono bg-orange-50 text-black">
                                    {{ number_format($product['totals']['avg_unit_cost'], 2) }}
                                </td>
                                <td class="text-right font-mono bg-indigo-50 text-black">
                                    <a href="{{ route('sales-settlements.index', ['filter[settlement_date_from]' => $startDate, 'filter[settlement_date_to]' => $endDate]) }}"
                                        class="hover:underline cursor-pointer text-black" target="_blank">
                                        {{ number_format($product['totals']['total_sale'], 2) }}
                                    </a>
                                </td>
                                <td class="text-right font-mono bg-red-50 text-black">
                                    <a href="{{ route('sales-settlements.index', ['filter[settlement_date_from]' => $startDate, 'filter[settlement_date_to]' => $endDate]) }}"
                                        class="hover:underline cursor-pointer text-black" target="_blank">
                                        {{ number_format($product['totals']['total_cogs'], 2) }}
                                    </a>
                                </td>
                                <td class="text-right font-mono bg-green-50 text-black">
                                    <a href="{{ route('sales-settlements.index', ['filter[settlement_date_from]' => $startDate, 'filter[settlement_date_to]' => $endDate]) }}"
                                        class="hover:underline cursor-pointer text-black" target="_blank">
                                        {{ number_format($product['totals']['total_profit'], 2) }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($matrixData['dates']) + 10 }}" class="text-center py-4 text-gray-500">
                                    No data found for the selected criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold sticky bottom-0">
                        <tr>
                            <td colspan="4" class="text-right px-2">Grand Totals:</td>

                            {{-- Daily Totals (Optional? Leaving blank for layout clarity or calculate if needed) --}}
                            @foreach($matrixData['dates'] as $date)
                                <td></td>
                            @endforeach

                            <td class="text-center font-mono">{{ $matrixData['grand_totals']['issued_qty'] + 0 }}</td>
                            <td class="text-right font-mono">
                                {{ number_format($matrixData['grand_totals']['issued_value'], 2) }}
                            </td>
                            <td class="text-right font-mono text-gray-400">-</td>
                            <td class="text-right font-mono">
                                {{ number_format($matrixData['grand_totals']['sale_amount'], 2) }}
                            </td>
                            <td class="text-right font-mono">{{ number_format($matrixData['grand_totals']['cogs'], 2) }}
                            </td>
                            <td class="text-right font-mono text-blue-700">GP:
                                {{ number_format($matrixData['grand_totals']['profit'], 2) }}</td>
                        </tr>
                        <tr class="bg-gray-200 text-sm">
                            <td colspan="{{ count($matrixData['dates']) + 10 }}"
                                class="text-right font-bold pr-4 py-2 border-t border-gray-300">
                                <span class="mr-4 text-orange-700 font-mono">
                                    Total Expenses:
                                    <a href="{{ route('sales-settlements.index', ['filter[settlement_date_from]' => $startDate, 'filter[settlement_date_to]' => $endDate]) }}"
                                        class="hover:underline cursor-pointer" target="_blank">
                                        {{ number_format($matrixData['grand_totals']['expenses'] ?? 0, 2) }}
                                    </a>
                                </span>
                                <span class="text-green-800 font-mono text-lg">
                                    Net Profit:
                                    <a href="{{ route('sales-settlements.index', ['filter[settlement_date_from]' => $startDate, 'filter[settlement_date_to]' => $endDate]) }}"
                                        class="hover:underline cursor-pointer" target="_blank">
                                        {{ number_format($matrixData['grand_totals']['net_profit'] ?? 0, 2) }}
                                    </a>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>