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

    @if ($hasFilter)
        @php
            $query = array_filter([
                'filter' => [
                    'supplier_id' => $selectedSupplierId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ]);
        @endphp
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 no-print">
            <div class="flex justify-end gap-2">
                @foreach (['portrait' => 'PDF Portrait', 'landscape' => 'PDF Landscape'] as $orientation => $label)
                    <a href="{{ route('reports.advance-tax-customer-summary.export.pdf', $query + ['orientation' => $orientation]) }}"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold rounded-md shadow transition-colors duration-150"
                        title="Download as an A4 {{ $orientation }} PDF">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

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

                    @include('reports.advance-tax-customer-summary._table')

                    <p class="text-xs mt-1">
                        Sale is the Total Sales of the settlements the customer has an advance tax entry on. A settlement with several customers shows its sale against each of them, and counts once in the Total row.
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
