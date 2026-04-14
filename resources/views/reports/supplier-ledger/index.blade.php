<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Supplier Ledger (Accounts Receivable)" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.supplier-ledger.index')" class="no-print">
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

            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliersList as $supplier)
                        <option value="{{ $supplier->id }}" {{ $supplierId === $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">

                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Supplier Ledger (Accounts Receivable — Supplier Wise)<br>
                    @if ($dateFrom && $dateTo)
                        For the period {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to
                        {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}<br>
                    @elseif ($dateTo)
                        Up to {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}<br>
                    @endif
                    <span class="print-only text-xs font-normal">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                @if ($supplierRows->isEmpty())
                    <div class="text-center py-8 text-gray-500">No data found for the selected filters.</div>
                @else
                    <table class="report-table">
                        <thead>
                            <tr class="bg-gray-50">
                                <th style="width: 40px;">#</th>
                                <th>Supplier</th>
                                <th style="width: 60px;">Customers</th>
                                <th style="width: 60px;">Salesmen</th>
                                <th style="width: 110px;">Opening Bal.</th>
                                <th style="width: 110px;">Credit Sales</th>
                                <th style="width: 110px;">Recoveries</th>
                                <th style="width: 110px;">Closing Bal.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($supplierRows as $index => $row)
                                <tr>
                                    <td class="text-center" style="vertical-align: middle;">{{ $index + 1 }}</td>
                                    <td style="vertical-align: middle;">
                                        <a href="{{ route('reports.supplier-ledger.salesmen', $row->id) }}?filter[date_from]={{ $dateFrom }}&filter[date_to]={{ $dateTo }}"
                                            class="text-blue-700 hover:underline font-medium no-print">
                                            {{ $row->supplier_name }}
                                        </a>
                                        <span class="print-only">{{ $row->supplier_name }}</span>
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        @if ($row->customer_count > 0)
                                            <a href="{{ route('reports.supplier-ledger.salesmen', $row->id) }}?filter[date_from]={{ $dateFrom }}&filter[date_to]={{ $dateTo }}"
                                                class="inline-flex items-center justify-center px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-semibold text-xs hover:bg-blue-200 no-print">
                                                {{ $row->customer_count }}
                                            </a>
                                            <span class="print-only">{{ $row->customer_count }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        @if ($row->employee_count > 0)
                                            <a href="{{ route('reports.supplier-ledger.salesmen', $row->id) }}?filter[date_from]={{ $dateFrom }}&filter[date_to]={{ $dateTo }}"
                                                class="inline-flex items-center justify-center px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded font-semibold text-xs hover:bg-indigo-200 no-print">
                                                {{ $row->employee_count }}
                                            </a>
                                            <span class="print-only">{{ $row->employee_count }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-mono"
                                        style="vertical-align: middle;">
                                        {{ number_format($row->opening_balance, 2) }}
                                    </td>
                                    <td class="text-right font-mono"
                                        style="vertical-align: middle;">
                                        {{ number_format($row->period_debit, 2) }}
                                    </td>
                                    <td class="text-right font-mono"
                                        style="vertical-align: middle;">
                                        {{ number_format($row->period_credit, 2) }}
                                    </td>
                                    <td class="text-right font-mono font-bold"
                                        style="vertical-align: middle;">
                                        {{ number_format($row->closing_balance, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="2" class="text-center px-2 py-1">
                                    Grand Total ({{ $supplierRows->count() }} supplier(s))
                                </td>
                                <td class="text-center px-2 py-1">{{ number_format($totals['customer_count']) }}</td>
                                <td></td>
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
                            </tr>
                        </tfoot>
                    </table>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
