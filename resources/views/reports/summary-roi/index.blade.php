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

                .report-table {
                    font-size: 10px !important;
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

            {{-- 3-column layout --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <div class="space-y-6">
                    {{-- ── Table 1: Category Wise Sale ── --}}
                    <div class="print:break-inside-avoid">
                        <h2 class="font-bold text-base mb-2 text-center border-b pb-2 text-black">Category Wise Sale</h2>
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
                            </tbody>
                            <tfoot>
                                <tr class="font-bold">
                                    <td colspan="3">Total</td>
                                    <td class="text-right">{{ number_format($otherOperatingExpensesTotal, 2) }}</td>
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

                </div>


            </div>{{-- end 3-col grid --}}

        </div>{{-- end white card --}}
    </div>
</x-app-layout>