<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Summary ROI Report" />
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
                    
                    margin: 10mm 8mm 12mm 8mm;

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
                }

                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 6px !important;
                    box-shadow: none !important;
                }

                .report-table {
                    font-size: 9px !important;
                    width: 100% !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }

                /* Force 2-column grid in print */
                .print-grid-2col {
                    display: grid !important;
                    grid-template-columns: 1fr 1fr !important;
                    gap: 12px !important;
                }

                .print-grid-2col>* {
                    min-width: 0 !important;
                }

                /* Reduce spacing in print */
                .space-y-6>*+* {
                    margin-top: 8px !important;
                }

                h2.font-bold {
                    font-size: 10px !important;
                    margin-bottom: 3px !important;
                    padding-bottom: 2px !important;
                }

                /* Charts in print */
                .print-chart {
                    height: 200px !important;
                    overflow: visible !important;
                }

                .print-chart svg {
                    width: 100% !important;
                    height: 100% !important;
                }
            }

            /* Select2 Styling to match Tailwind Inputs */
            .select2-container .select2-selection--multiple {
                min-height: 42px !important;
                border-color: #d1d5db !important;
                border-radius: 0.375rem !important;
                padding-top: 4px !important;
                padding-bottom: 4px !important;
            }

            .select2-container--default.select2-container--focus .select2-selection--multiple {
                border-color: #6366f1 !important;
                box-shadow: 0 0 0 1px #6366f1 !important;
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.summary-roi.index')" class="no-print" :maxWidth="'max-w-7xl'">
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
                        <option value="{{ $employee->id }}" {{ in_array($employee->id, (array) ($filters['employee_id'] ?? [])) ? 'selected' : '' }}>
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
                    <option value="posted" {{ ($filters['status'] ?? 'posted') === 'posted' ? 'selected' : '' }}>Posted
                    </option>
                    <option value="draft" {{ ($filters['status'] ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="" {{ ($filters['status'] ?? '') === '' ? 'selected' : '' }}>All Statuses</option>
                </select>
            </div>

            <!-- Supplier -->
            <div class="md:col-span-2 lg:col-span-2">
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg print:shadow-none">

            {{-- Report header --}}
            <div class="text-center py-4 no-print">
                <p class="font-bold text-lg text-gray-800">Summary ROI Report</p>
                <p class="text-sm text-gray-600">
                    For the Period of
                    {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }}
                    to
                    {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                </p>
                @if(count($filterBadges))
                    <div class="flex flex-wrap justify-center gap-1 mt-2">
                        @foreach($filterBadges as $badge)
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 border border-gray-300 text-gray-700">
                                {{ $badge }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Print-only header --}}
            <div class="hidden print:block text-center mb-4">
                <p class="font-bold text-lg">Summary ROI Report</p>
                <p class="text-sm">
                    For the Period of
                    {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }}
                    to
                    {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                </p>
                @if(count($filterBadges))
                    <div class="flex flex-wrap justify-center gap-1 mt-1">
                        @foreach($filterBadges as $badge)
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border border-gray-400 text-gray-700 mr-1">
                                {{ $badge }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            @php
                $grossInflow = (float) ($grandTotals['sale'] + $grandTotals['schema_received'] + $grandTotals['fmr_received'] + $grandTotals['cash_discount']);
                $totalExpensesShown = (float) ($distributionExpensesTotal + $otherOperatingExpensesTotal);
                $netAfterAllExpenses = $grossInflow - $totalExpensesShown;
            @endphp

            <div class="no-print mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">Gross Inflow</p>
                        <p class="mt-2 text-2xl font-bold text-blue-900">{{ number_format($grossInflow, 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-rose-700">Total Expenses (Shown)
                        </p>
                        <p class="mt-2 text-2xl font-bold text-rose-900">{{ number_format($totalExpensesShown, 2) }}</p>
                    </div>
                    <div
                        class="rounded-xl border {{ $netAfterAllExpenses >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-orange-200 bg-orange-50' }} p-4">
                        <p
                            class="text-xs font-semibold uppercase tracking-wider {{ $netAfterAllExpenses >= 0 ? 'text-emerald-700' : 'text-orange-700' }}">
                            Net Position</p>
                        <p
                            class="mt-2 text-2xl font-bold {{ $netAfterAllExpenses >= 0 ? 'text-emerald-900' : 'text-orange-900' }}">
                            {{ number_format($netAfterAllExpenses, 2) }}
                        </p>
                    </div>
                </div>

            </div>

            {{-- Charts: visible on screen and in print --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 print-grid-2col">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Inflow Composition</h3>
                    <div id="summary-roi-inflow-chart" class="h-[300px] print-chart"></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Expense Comparison</h3>
                    <div id="summary-roi-expense-chart" class="h-[300px] print-chart"></div>
                </div>
            </div>

            {{-- 3-column layout --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 print-grid-2col">

                <div class="space-y-6">
                    {{-- ── Table 1: Category Wise Sale ── --}}
                    <div class="print:break-inside-avoid">
                        <h2 class="font-bold text-base mb-2 text-center border-b pb-2 text-black">Category Wise Sale
                        </h2>
                        <table class="report-table w-full">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-right">Sale (Rs.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categoryRows as $row)
                                    <tr>
                                        <td>{{ $row['category_name'] }}</td>
                                        <td class="text-right">{{ number_format($row['sale'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-gray-500">No data for this period</td>
                                    </tr>
                                @endforelse

                                {{-- Summary rows below categories --}}
                                <tr class="font-semibold border-t-2 border-gray-400">
                                    <td>Total Sale</td>
                                    <td class="text-right">{{ number_format($grandTotals['sale'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Schema Received</td>
                                    <td class="text-right">{{ number_format($grandTotals['schema_received'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>
                                        FMR Received
                                        {{-- <div class="text-xs text-gray-400 font-normal leading-tight mt-0.5">
                                            Σ grni.fmr_allowance (Posted GRNs, supplier-filtered, date range)
                                        </div> --}}
                                    </td>
                                    <td class="text-right">{{ number_format($grandTotals['fmr_received'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Cash Discount from Invoices (0.5%)</td>
                                    <td class="text-right">{{ number_format($grandTotals['cash_discount'], 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-bold">
                                    <td>Grand Total</td>
                                    <td class="text-right">
                                        {{ number_format($grandTotals['sale'] + $grandTotals['schema_received'] + $grandTotals['fmr_received'] + $grandTotals['cash_discount'], 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="print:break-inside-avoid">
                        <h2 class="font-bold text-base mb-2 text-center border-b pb-2 text-black">Other Operating
                            Expenses</h2>
                        <table class="report-table w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Account</th>
                                    <th>Code</th>
                                    <th class="text-right">Amount (Rs.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($expenseBreakdown as $expense)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $expense->account_name }}</td>
                                        <td class="text-center">{{ $expense->account_code }}</td>
                                        <td class="text-right">
                                            {{ $expense->total_amount > 0 ? number_format($expense->total_amount, 2) : '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-gray-500">No expense records</td>
                                    </tr>
                                @endforelse
                                <tr>
                                    <td>{{ $expenseBreakdown->count() + 1 }}</td>
                                    <td>Distribution &amp; Selling Expenses</td>
                                    <td class="text-center">—</td>
                                    <td class="text-right">
                                        {{ $distributionExpensesTotal > 0 ? number_format($distributionExpensesTotal, 2) : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-bold">
                                    <td colspan="3">Total</td>
                                    <td class="text-right">
                                        {{ number_format($otherOperatingExpensesTotal + $distributionExpensesTotal, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                </div>

                {{-- ── Right Column Tables ── --}}
                <div class="space-y-6">
                    <div class="print:break-inside-avoid">
                        <h2 class="font-bold text-base mb-2 text-center border-b pb-2 text-black">Distribution &amp;
                            Selling
                            Expenses</h2>
                        <table class="report-table w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th class="text-right">Amount (Rs.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($distributionExpenses as $i => $expense)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $expense['category'] }}</td>
                                        <td>{{ $expense['description'] }}</td>
                                        <td class="text-right">
                                            {{ $expense['amount'] > 0 ? number_format($expense['amount'], 2) : '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-gray-500">No expense records</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="font-bold">
                                    <td colspan="3">Total</td>
                                    <td class="text-right">{{ number_format($distributionExpensesTotal, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- ── Summary Table ── --}}
                    <div class="print:break-inside-avoid">
                        <h2 class="font-bold text-base mb-2 text-center border-b pb-2 text-black">Summary</h2>
                        <table class="report-table w-full">
                            <tbody>
                                <tr>
                                    <td class="font-semibold">Total Gross Inflow</td>
                                    <td class="text-right font-semibold">{{ number_format($grossInflow, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="font-semibold">Total Expenses (Shown)</td>
                                    <td class="text-right font-semibold">
                                        {{ number_format($otherOperatingExpensesTotal + $distributionExpensesTotal, 2) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr
                                    class="font-bold {{ ($grossInflow - ($otherOperatingExpensesTotal + $distributionExpensesTotal)) >= 0 ? 'bg-emerald-50 text-emerald-900' : 'bg-rose-50 text-rose-900' }}">
                                    <td>Net Position</td>
                                    <td class="text-right">
                                        {{ number_format($grossInflow - ($otherOperatingExpensesTotal + $distributionExpensesTotal), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                </div>


            </div>{{-- end 3-col grid --}}

        </div>{{-- end white card --}}
    </div>

    @push('scripts')
        <script>
            (function () {
                if (typeof ApexCharts === 'undefined') {
                    return;
                }

                const inflowSeries = [
                                {{ (float) $grandTotals['sale'] }},
                                {{ (float) $grandTotals['schema_received'] }},
                                {{ (float) $grandTotals['fmr_received'] }},
                                {{ (float) $grandTotals['cash_discount'] }},
                ];

                const inflowChart = document.querySelector('#summary-roi-inflow-chart');
                if (inflowChart) {
                    new ApexCharts(inflowChart, {
                        chart: {
                            type: 'donut',
                            height: 300,
                            toolbar: {
                                show: false
                            }
                        },
                        labels: ['Sale', 'Scheme Received', 'FMR Received', 'Cash Discount'],
                        series: inflowSeries,
                        colors: ['#2563eb', '#16a34a', '#9333ea', '#d97706'],
                        legend: {
                            position: 'bottom'
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function (value) {
                                return value.toFixed(1) + '%';
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (value) {
                                    return Number(value).toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    }).render();
                }

                const expenseChart = document.querySelector('#summary-roi-expense-chart');
                if (expenseChart) {
                    new ApexCharts(expenseChart, {
                        chart: {
                            type: 'bar',
                            height: 300,
                            toolbar: {
                                show: false
                            }
                        },
                        series: [{
                            name: 'Amount',
                            data: [{{ (float) $distributionExpensesTotal }}, {{ (float) $otherOperatingExpensesTotal }}]
                        }],
                        xaxis: {
                            categories: ['Distribution & Selling', 'Other Operating']
                        },
                        colors: ['#dc2626'],
                        plotOptions: {
                            bar: {
                                borderRadius: 4,
                                columnWidth: '55%'
                            }
                        },
                        dataLabels: {
                            enabled: false
                        },
                        yaxis: {
                            labels: {
                                formatter: function (value) {
                                    return Number(value).toLocaleString();
                                }
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (value) {
                                    return Number(value).toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    }).render();
                }
            })();
        </script>
    @endpush
</x-app-layout>