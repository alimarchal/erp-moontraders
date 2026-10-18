<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Company Target Summary" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
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

                .overflow-x-auto {
                    overflow: visible !important;
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

                .print-info {
                    font-size: 9px !important;
                    margin-top: 5px !important;
                    margin-bottom: 10px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }
            }
        </style>
    @endpush

    @if ($summary)
        @php
            $query = array_filter([
                'supplier_id' => $supplier->id,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'designation' => $designation,
            ], fn ($value) => $value !== null && $value !== '');
        @endphp
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 no-print">
            <div class="flex justify-end gap-2">
                <a href="{{ route('reports.company-target-summary.export.excel', $query) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold rounded-md shadow transition-colors duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export to Excel
                </a>
                <a href="{{ route('reports.company-target-summary.export.pdf', $query) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold rounded-md shadow transition-colors duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export to PDF
                </a>
            </div>
        </div>
    @endif

    <div>
        <x-filter-section :action="route('reports.company-target-summary.index')" class="no-print">
            <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4">
                {{-- Company --}}
                <div>
                    <x-label for="supplier_id" value="{{ __('Company') }}" />
                    <select id="supplier_id" name="supplier_id" data-placeholder="Select Company"
                        class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                        @if (auth()->user()->hasAllSupplierAccess())
                            <option value="">Select Company</option>
                        @endif
                        @foreach ($suppliers as $option)
                            <option value="{{ $option->id }}" {{ $supplier?->id === $option->id ? 'selected' : '' }}>
                                {{ $option->supplier_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Start date --}}
                <div>
                    <x-label for="from" value="{{ __('Start Date') }}" />
                    <x-input id="from" class="block mt-1 w-full" type="date" name="from" :value="$from->toDateString()" required />
                </div>

                {{-- End date --}}
                <div>
                    <x-label for="to" value="{{ __('End Date') }}" />
                    <x-input id="to" class="block mt-1 w-full" type="date" name="to" :value="$to->toDateString()" required />
                </div>

                {{-- Designation --}}
                <div>
                    <x-label for="designation" value="{{ __('Designation') }}" />
                    <select id="designation" name="designation" data-placeholder="All Designations"
                        class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                        <option value="">All Designations</option>
                        @foreach ($designations as $option)
                            <option value="{{ $option }}" {{ $designation === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filter-section>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
            <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
                <div class="overflow-x-auto">
                    @if ($errors->any())
                        <div class="no-print mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @if (! $summary)
                        <div
                            class="no-print mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Select a company from the filters to view the target summary.
                        </div>
                    @endif

                    {{-- Report Header --}}
                    <p class="text-center font-extrabold mb-2">
                        SHAHZAIN TRADERS - MUZAFFRABAD<br>
                        COMPANY TARGET SUMMARY<br>
                        @if ($supplier)
                            <span class="text-sm font-semibold">
                                DISTRIBUTOR: {{ $supplier->supplier_name }}
                            </span><br>
                        @endif
                        <span class="text-sm font-semibold">
                            Period: {{ $from->format('d.m.Y') }} to {{ $to->format('d.m.Y') }}
                            @if ($summary)
                                | Days left: {{ $summary['days_left'] }}
                            @endif
                        </span><br>
                        <span class="text-xs font-normal">System generated by MOON TRADERS ERP</span><br>
                        <span class="print-only print-info text-xs text-center">
                            Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                        </span>
                    </p>

                    @if ($summary)
                        @include('reports.company-target-summary._table', ['summary' => $summary])
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
