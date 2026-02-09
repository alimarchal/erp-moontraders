<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Percentage Expense Report" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
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

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;

                    @bottom-center {
                        content: "\"Page \" counter(page) \" of \" counter(pages)";
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

            .page-footer {
                display: none;
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.percentage-expense.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_supplier_id" value="Supplier" />
                    @if($selectedSupplierId)
                        <a href="{{ route('reports.percentage-expense.index', array_merge(request()->except(['filter.supplier_id', 'page']), ['filter[supplier_id]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">
                            Clear
                        </a>
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

            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_designation" value="Designation" />
                    @if($selectedDesignation)
                        <a href="{{ route('reports.percentage-expense.index', array_merge(request()->except(['filter.designation', 'page']), ['filter[designation]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">
                            Clear
                        </a>
                    @endif
                </div>
                <select id="filter_designation" name="filter[designation]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Designations</option>
                    @foreach($designations as $designation)
                        <option value="{{ $designation }}" {{ $selectedDesignation == $designation ? 'selected' : '' }}>
                            {{ $designation }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_salesman_ids" value="Salesman(s)" />
                <select id="filter_salesman_ids" name="filter[salesman_ids][]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($salesmen as $salesman)
                        <option value="{{ $salesman->id }}" {{ in_array($salesman->id, $selectedSalesmanIds) ? 'selected' : '' }}>
                            {{ $salesman->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_start_date" value="Start Date (From)" />
                <x-input id="filter_start_date" name="filter[start_date]" type="date" class="mt-1 block w-full"
                    :value="$startDate" />
            </div>

            <div>
                <x-label for="filter_end_date" value="End Date (To)" />
                <x-input id="filter_end_date" name="filter[end_date]" type="date" class="mt-1 block w-full"
                    :value="$endDate" />
            </div>

            <div>
                <x-label for="filter_sort_order" value="Sort Order" />
                <select id="filter_sort_order" name="filter[sort_order]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="name_asc" {{ $sortOrder == 'name_asc' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="high_to_low" {{ $sortOrder == 'high_to_low' ? 'selected' : '' }}>Highest Amount First
                    </option>
                    <option value="low_to_high" {{ $sortOrder == 'low_to_high' ? 'selected' : '' }}>Lowest Amount First
                    </option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Percentage Expense Report<br>
                    @if($selectedDesignation)
                        <span class="text-sm font-semibold">Designation: {{ $selectedDesignation }}</span><br>
                    @endif
                    <span class="text-sm font-semibold">Salesmen: {{ $selectedSalesmanNames }}</span><br>
                    For the period {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }} to
                    {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="w-10 text-center border font-bold">#</th>
                            <th class="text-left border font-bold px-2 whitespace-nowrap">Employee Name</th>
                            @foreach($dates as $date)
                                <th class="text-center w-10 border font-bold px-1">
                                    {{ \Carbon\Carbon::parse($date)->format('d') }}
                                </th>
                            @endforeach
                            <th class="w-20 text-center border font-bold px-2">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reportSalesmen as $salesman)
                            <tr>
                                <td class="text-center border">{{ $loop->iteration }}</td>
                                <td class="text-left font-semibold border px-2 whitespace-nowrap">
                                    {{ $salesman->name }}
                                </td>
                                @foreach($dates as $date)
                                    @php
                                        // Matrix is now [employee_id][date]
                                        $amount = $matrix[$salesman->id][$date] ?? 0;
                                    @endphp
                                    <td class="text-center font-mono border px-1 text-sm" style="vertical-align: middle;">
                                        {{ number_format($amount, 0) }}
                                    </td>
                                @endforeach
                                <td class="text-center font-mono font-bold bg-gray-50 border px-2">
                                    {{ number_format($salesmanTotals[$salesman->id] ?? 0, 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="2" class="text-right px-2 py-1">Grand Total
                            </td>
                            @foreach($dates as $date)
                                <td class="text-center font-mono px-2 py-1 border">
                                    {{ number_format($dateTotals[$date] ?? 0, 0) }}
                                </td>
                            @endforeach
                            <td class="text-center font-mono px-2 py-1 border">
                                {{ number_format($grandTotal, 0) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>