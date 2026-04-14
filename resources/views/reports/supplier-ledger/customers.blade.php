<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Supplier Ledger — {{ $employee->name }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true"
            :backRoute="route('reports.supplier-ledger.salesmen', $supplier) . '?filter%5Bdate_from%5D=' . $dateFrom . '&filter%5Bdate_to%5D=' . $dateTo" />
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

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 11px !important;
                    width: 100% !important;
                }

                .print-only {
                    display: block !important;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.supplier-ledger.customers', [$supplier, $employee])" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_date_from" value="Date From" />
                <x-input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full"
                    :value="$dateFrom" />
            </div>
            <div>
                <x-label for="filter_date_to" value="Date To" />
                <x-input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full"
                    :value="$dateTo" />
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none">
            <div class="overflow-x-auto">

                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Supplier Ledger — Customer Wise<br>
                    <span class="font-normal text-base">
                        {{ $supplier->supplier_name }} &mdash; {{ $employee->name }}
                    </span><br>
                    @if ($dateFrom && $dateTo)
                        For the period {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to
                        {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}<br>
                    @endif
                    <span class="print-only text-xs font-normal">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;">#</th>
                            <th style="width: 100px;">Code</th>
                            <th>Customer Name</th>
                            <th style="width: 90px;">City</th>
                            <th style="width: 115px;">Opening Bal.</th>
                            <th style="width: 115px;">Credit Sales</th>
                            <th style="width: 115px;">Recoveries</th>
                            <th style="width: 115px;">Closing Bal.</th>
                            <th style="width: 55px;" class="no-print">Ledger</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customerRows as $index => $row)
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">{{ $index + 1 }}</td>
                                <td class="font-mono text-xs" style="vertical-align: middle;">
                                    {{ $row->customer_code }}
                                </td>
                                <td style="vertical-align: middle;">{{ $row->customer_name }}</td>
                                <td style="vertical-align: middle;">{{ $row->city ?? '-' }}</td>
                                <td class="text-right font-mono" style="vertical-align: middle;">
                                    {{ number_format($row->opening_balance, 2) }}
                                </td>
                                <td class="text-right font-mono" style="vertical-align: middle;">
                                    {{ number_format($row->period_debit, 2) }}
                                </td>
                                <td class="text-right font-mono" style="vertical-align: middle;">
                                    {{ number_format($row->period_credit, 2) }}
                                </td>
                                <td class="text-right font-mono font-bold"
                                    style="vertical-align: middle;">
                                    {{ number_format($row->closing_balance, 2) }}
                                </td>
                                <td class="text-center no-print" style="vertical-align: middle;">
                                    <a href="{{ route('reports.creditors-ledger.customer-ledger', $row->id) }}?filter[date_from]={{ $dateFrom }}&filter[date_to]={{ $dateTo }}&filter[employee_id]={{ $employee->id }}"
                                        class="inline-flex items-center px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                                        title="View Full Ledger" target="_blank">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-gray-500">No customers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="4" class="text-center px-2 py-1">
                                Grand Total ({{ $customerRows->count() }} customers)
                            </td>
                            <td class="text-right font-mono px-2 py-1">
                                {{ number_format($totals['opening_balance'], 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1">
                                {{ number_format($totals['period_debit'], 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1">
                                {{ number_format($totals['period_credit'], 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1">
                                {{ number_format($totals['closing_balance'], 2) }}
                            </td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>

            </div>
        </div>
    </div>
</x-app-layout>
