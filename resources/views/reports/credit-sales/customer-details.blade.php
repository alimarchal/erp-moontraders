<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Credit Sales Details - {{ $customer->customer_name }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.credit-sales.customer-history" />
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

                .text-green-700,
                .text-blue-700,
                .text-orange-700 {
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

    <x-filter-section :action="route('reports.credit-sales.customer-details', $customer)" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_date_from" value="Date From" />
                <x-input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full"
                    :value="request('filter.date_from')" />
            </div>

            <div>
                <x-label for="filter_date_to" value="Date To" />
                <x-input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full"
                    :value="request('filter.date_to')" />
            </div>

            <div>
                <x-label for="filter_employee_id" value="Salesman" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Salesmen</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ request('filter.employee_id')==(string)$employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="25" {{ request('per_page')==25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page', 50)==50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page')==100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page')==250 ? 'selected' : '' }}>250</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 no-print">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Credit Sales</div>
                <div class="text-xl font-bold text-blue-700">{{ number_format($summary['total_credit_sales'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Recoveries</div>
                <div class="text-xl font-bold text-green-700">{{ number_format($summary['total_recoveries'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="text-sm text-gray-500">Outstanding Balance</div>
                <div class="text-xl font-bold {{ $summary['balance'] > 0 ? 'text-orange-700' : 'text-green-700' }}">
                    {{ number_format($summary['balance'], 2) }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Total Sales Count</div>
                <div class="text-xl font-bold text-purple-700">{{ $creditSales->total() }}</div>
            </div>
        </div>
    </div>

    @if($salesmenBreakdown->count() > 0)
        {{-- Salesman Breakdown Table --}}
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-4">
            <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg print:shadow-none">
                <div class="overflow-x-auto">
                    <p class="text-center font-extrabold mb-2">
                        Credit Sales by Salesman
                    </p>
                    <table class="report-table">
                        <thead>
                            <tr class="bg-gray-50">
                                <th style="width: 40px;">Sr#</th>
                                <th style="width: 150px;">Salesman</th>
                                <th style="width: 120px;">Supplier</th>
                                <th style="width: 100px;">Credit Sales</th>
                                <th style="width: 100px;">Recoveries</th>
                                <th style="width: 100px;">Balance</th>
                                <th style="width: 60px;">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($salesmenBreakdown as $index => $breakdown)
                                @php
                                    $salesmanBalance = ($breakdown->total_credit_sales ?? 0) - ($breakdown->total_recoveries ?? 0);
                                @endphp
                                <tr>
                                    <td class="text-center" style="vertical-align: middle;">{{ $index + 1 }}</td>
                                    <td style="vertical-align: middle;">
                                        {{ $breakdown->employee_name }}
                                        <div class="text-xs text-gray-500">{{ $breakdown->employee_code }}</div>
                                    </td>
                                    <td style="vertical-align: middle;">
                                        {{ $breakdown->supplier_name ?? 'N/A' }}
                                        @if($breakdown->short_name)
                                            <div class="text-xs text-gray-500">{{ $breakdown->short_name }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right font-mono text-blue-700" style="vertical-align: middle;">
                                        {{ number_format($breakdown->total_credit_sales ?? 0, 2) }}
                                    </td>
                                    <td class="text-right font-mono text-green-700" style="vertical-align: middle;">
                                        {{ number_format($breakdown->total_recoveries ?? 0, 2) }}
                                    </td>
                                    <td class="text-right font-mono font-bold {{ $salesmanBalance > 0 ? 'text-orange-700' : 'text-green-700' }}" style="vertical-align: middle;">
                                        {{ number_format($salesmanBalance, 2) }}
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        {{ $breakdown->sales_count }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="3" class="text-center px-2 py-1">Total ({{ $salesmenBreakdown->count() }} salesmen)</td>
                                <td class="text-right font-mono px-2 py-1 text-blue-700">
                                    {{ number_format($salesmenBreakdown->sum('total_credit_sales'), 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1 text-green-700">
                                    {{ number_format($salesmenBreakdown->sum('total_recoveries'), 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1 text-orange-700">
                                    {{ number_format($salesmenBreakdown->sum('total_credit_sales') - $salesmenBreakdown->sum('total_recoveries'), 2) }}
                                </td>
                                <td class="text-center px-2 py-1">
                                    {{ $salesmenBreakdown->sum('sales_count') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Credit Sales Details - {{ $customer->customer_name }} ({{ $customer->customer_code }})<br>
                    @if(request('filter.date_from') && request('filter.date_to'))
                        For the period {{ \Carbon\Carbon::parse(request('filter.date_from'))->format('d-M-Y') }} to {{ \Carbon\Carbon::parse(request('filter.date_to'))->format('d-M-Y') }}
                    @else
                        All Transactions
                    @endif
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;">Sr#</th>
                            <th style="width: 90px;">Date</th>
                            <th style="width: 150px;">Salesman</th>
                            <th style="width: 120px;">Supplier</th>
                            <th style="width: 100px;">Settlement</th>
                            <th style="width: 100px;">Invoice #</th>
                            <th style="width: 100px;">Amount</th>
                            <th style="width: 150px;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($creditSales as $index => $sale)
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">{{ $creditSales->firstItem() + $index }}</td>
                                <td style="vertical-align: middle;">{{ \Carbon\Carbon::parse($sale->transaction_date)->format('d-m-Y') }}</td>
                                <td style="vertical-align: middle;">
                                    {{ $sale->account->employee->full_name ?? 'N/A' }}
                                    @if($sale->account->employee->employee_code ?? null)
                                        <div class="text-xs text-gray-500">{{ $sale->account->employee->employee_code }}</div>
                                    @endif
                                </td>
                                <td style="vertical-align: middle;">
                                    {{ $sale->account->employee->supplier->supplier_name ?? 'N/A' }}
                                </td>
                                <td style="vertical-align: middle;">
                                    @if($sale->salesSettlement)
                                        <a href="{{ route('sales-settlements.show', $sale->salesSettlement) }}"
                                            class="text-blue-600 hover:text-blue-800 font-semibold no-print" target="_blank">
                                            {{ $sale->salesSettlement->settlement_number }}
                                        </a>
                                        <span class="print-only">{{ $sale->salesSettlement->settlement_number }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="font-mono" style="vertical-align: middle;">{{ $sale->invoice_number ?? '-' }}</td>
                                <td class="text-right font-mono font-bold text-blue-700" style="vertical-align: middle;">
                                    {{ number_format($sale->debit, 2) }}
                                </td>
                                <td style="vertical-align: middle;">{{ $sale->notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-gray-500">No credit sales found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="6" class="text-center px-2 py-1">Page Total ({{ $creditSales->count() }} entries)</td>
                            <td class="text-right font-mono px-2 py-1 text-blue-700">
                                {{ number_format($creditSales->sum('debit'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                @if ($creditSales->hasPages())
                    <div class="mt-4 no-print">
                        {{ $creditSales->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
