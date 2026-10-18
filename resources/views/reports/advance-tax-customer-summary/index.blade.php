<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Advance Tax Customer Summary" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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

    <x-filter-section :action="route('reports.advance-tax-customer-summary.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            {{-- Company --}}
            <div>
                <x-label for="filter_supplier_id" value="Company" />
                <select id="filter_supplier_id" name="filter[supplier_id]" data-placeholder="Select Company"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Select Company</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $selectedSupplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
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
            @if (! $hasFilter)
                <div class="flex flex-col items-center justify-center py-16 text-gray-500">
                    <p class="text-lg font-medium">Please select a company to view the report.</p>
                    <p class="text-sm mt-1">Use the filter above to select a company and date range.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <p class="text-center font-extrabold mb-2">
                        Moon Traders<br>
                        Advance Tax Customer Summary<br>
                        <span class="text-sm font-semibold">Company: {{ $selectedSupplier->supplier_name }}</span>
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
                                <th class="text-center font-bold px-2">Customer</th>
                                <th class="text-center font-bold px-2 whitespace-nowrap">Entries</th>
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
                            @forelse ($rows as $index => $row)
                                @php($customer = $row['customer'])
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="px-2">
                                        <span class="font-semibold">{{ $customer?->customer_code }}</span>
                                        <span class="px-1">–</span>{{ $customer?->business_name ?: $customer?->customer_name }}
                                    </td>
                                    <td class="text-center px-2">{{ $row['entries'] }}</td>
                                    <td class="text-right font-mono px-2">{{ number_format($row['sale'], 2) }}</td>
                                    <td class="text-right font-mono px-2">{{ number_format($row['advance_tax'], 2) }}</td>
                                    <td class="text-right font-mono px-2">{{ number_format($row['benefits'], 2) }}</td>
                                    <td class="text-right font-mono px-2">{{ number_format($row['received'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center italic text-gray-500 px-2 py-2">No advance tax entries found for this company and period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($rows->isNotEmpty())
                            <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td colspan="2" class="text-right px-2 py-1">Total ({{ $rows->count() }} customers)</td>
                                    <td class="text-center px-2 py-1">{{ $totalEntries }}</td>
                                    <td class="text-right font-mono px-2 py-1">{{ number_format($totalSale, 2) }}</td>
                                    <td class="text-right font-mono px-2 py-1">{{ number_format($totalAdvanceTax, 2) }}</td>
                                    <td class="text-right font-mono px-2 py-1">{{ number_format($totalBenefits, 2) }}</td>
                                    <td class="text-right font-mono px-2 py-1">{{ number_format($totalReceived, 2) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>

                    {{-- Cross-check against the 1161 amount on the settlements --}}
                    <table class="report-table mt-4" style="width: auto; min-width: 420px;">
                        <tbody>
                            <tr>
                                <td class="px-2">Benefits (1161) added up customer-wise</td>
                                <td class="text-right font-mono px-2">{{ number_format($totalBenefits, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="px-2">Advance Tax (1161) on the posted settlements</td>
                                <td class="text-right font-mono px-2">{{ number_format($benefitsPosted, 2) }}</td>
                            </tr>
                            <tr class="font-bold">
                                <td class="px-2">Difference</td>
                                <td class="text-right font-mono px-2">{{ number_format($difference, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
