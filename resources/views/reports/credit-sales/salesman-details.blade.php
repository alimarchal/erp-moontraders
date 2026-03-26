<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Salesman Ledger - {{ $employee->full_name }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.credit-sales.salesman-history" />
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
                .text-orange-700,
                .text-gray-700,
                .text-purple-700 {
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

    <x-filter-section :action="route('reports.credit-sales.salesman-details', $employee)" class="no-print">
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
                <x-label for="filter_customer_id" value="Customer" />
                <select id="filter_customer_id" name="filter[customer_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Customers</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ request('filter.customer_id') == (string) $customer->id ? 'selected' : '' }}>
                            {{ $customer->customer_name }} ({{ $customer->customer_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_invoice_number" value="Invoice Number" />
                <x-input id="filter_invoice_number" name="filter[invoice_number]" type="text" class="mt-1 block w-full"
                    :value="request('filter.invoice_number')" placeholder="Search invoice..." />
            </div>

            <div>
                <x-label for="filter_description" value="Description" />
                <x-input id="filter_description" name="filter[description]" type="text" class="mt-1 block w-full"
                    :value="request('filter.description')" placeholder="Search description..." />
            </div>

            <div>
                <x-label for="filter_amount_min" value="Amount (Min)" />
                <x-input id="filter_amount_min" name="filter[amount_min]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.amount_min')" placeholder="0.00" />
            </div>

            <div>
                <x-label for="filter_amount_max" value="Amount (Max)" />
                <x-input id="filter_amount_max" name="filter[amount_max]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.amount_max')" placeholder="Any" />
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="25" {{ request('per_page') == '25' ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page', '100') == '100' ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page') == '250' ? 'selected' : '' }}>250</option>
                    <option value="all" {{ request('per_page') === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Employee Info Card --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Employee Code:</span>
                    <span class="font-semibold ml-1">{{ $employee->employee_code }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Name:</span>
                    <span class="font-semibold ml-1">{{ $employee->full_name }}</span>
                </div>
                @if($employee->supplier)
                    <div>
                        <span class="text-gray-500">Supplier:</span>
                        <span class="font-semibold ml-1">{{ $employee->supplier->supplier_name }}</span>
                    </div>
                @endif
                @if($employee->designation)
                    <div>
                        <span class="text-gray-500">Designation:</span>
                        <span class="font-semibold ml-1">{{ $employee->designation }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 no-print">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
                <div class="text-sm text-gray-500">Opening Balance</div>
                <div class="text-xl font-bold text-gray-700">{{ number_format($summary['opening_balance'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Credit Sales</div>
                <div class="text-xl font-bold text-blue-700">{{ number_format($summary['total_credit_sales'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Recoveries</div>
                <div class="text-xl font-bold text-green-700">{{ number_format($summary['total_recoveries'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="text-sm text-gray-500">Closing Balance</div>
                <div class="text-xl font-bold {{ $summary['closing_balance'] > 0 ? 'text-orange-700' : 'text-green-700' }}">
                    {{ number_format($summary['closing_balance'], 2) }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Total Customers</div>
                <div class="text-xl font-bold text-purple-700">{{ $customerSummaries->count() }}</div>
            </div>
        </div>
    </div>

    @if($customerSummaries->count() > 0)
        {{-- Customer Summary Table --}}
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-4">
            <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg print:shadow-none">
                <div class="overflow-x-auto">
                    <p class="text-center font-extrabold mb-2">
                        Customer-wise Summary
                    </p>
                    <table class="report-table" style="table-layout: auto;">
                        <thead>
                            <tr class="bg-gray-50 text-center">
                                <th>Sr#</th>
                                <th>Code</th>
                                <th>Customer Name</th>
                                <th>Opening Balance</th>
                                <th>Credit Sales</th>
                                <th>Recoveries</th>
                                <th>Closing Balance</th>
                                <th>Txns</th>
                                <th class="no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customerSummaries as $index => $custSummary)
                                @php
                                    $custOpening = $custSummary->opening_balance ?? 0;
                                    $custClosing = $custOpening + $custSummary->credit_sales - $custSummary->recoveries;
                                @endphp
                                <tr>
                                    <td class="text-center" style="vertical-align: middle;">{{ $index + 1 }}</td>
                                    <td class="font-mono" style="vertical-align: middle;">{{ $custSummary->customer_code }}</td>
                                    <td style="vertical-align: middle;">{{ $custSummary->customer_name }}</td>
                                    <td class="text-right font-mono text-gray-700" style="vertical-align: middle;">
                                        {{ number_format($custOpening, 2) }}
                                    </td>
                                    <td class="text-right font-mono text-blue-700" style="vertical-align: middle;">
                                        {{ number_format($custSummary->credit_sales, 2) }}
                                    </td>
                                    <td class="text-right font-mono text-green-700" style="vertical-align: middle;">
                                        {{ number_format($custSummary->recoveries, 2) }}
                                    </td>
                                    <td class="text-right font-mono font-bold {{ $custClosing > 0 ? 'text-orange-700' : 'text-green-700' }}" style="vertical-align: middle;">
                                        {{ number_format($custClosing, 2) }}
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">{{ $custSummary->txn_count }}</td>
                                    <td class="text-center no-print" style="vertical-align: middle;">
                                        <a href="{{ route('reports.credit-sales.salesman-details', $employee) }}?filter[customer_id]={{ $custSummary->customer_id }}{{ $dateFrom ? '&filter[date_from]='.$dateFrom : '' }}{{ $dateTo ? '&filter[date_to]='.$dateTo : '' }}"
                                            class="text-blue-600 hover:text-blue-800 font-semibold text-xs">
                                            Filter
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="3" class="text-center px-2 py-1">Total ({{ $customerSummaries->count() }} customers)</td>
                                <td class="text-right font-mono px-2 py-1 text-gray-700">
                                    {{ number_format($customerSummaries->sum('opening_balance'), 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1 text-blue-700">
                                    {{ number_format($customerSummaries->sum('credit_sales'), 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1 text-green-700">
                                    {{ number_format($customerSummaries->sum('recoveries'), 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1 text-orange-700">
                                    {{ number_format($customerSummaries->sum('opening_balance') + $customerSummaries->sum('credit_sales') - $customerSummaries->sum('recoveries'), 2) }}
                                </td>
                                <td class="text-center px-2 py-1">{{ $customerSummaries->sum('txn_count') }}</td>
                                <td class="no-print"></td>
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
                <p class="text-center font-extrabold mb-1">
                    Moon Traders<br>
                    Salesman Ledger
                </p>
                <div class="text-center text-sm mb-1">
                    <span class="font-bold">{{ $employee->full_name }}</span>
                    <span class="text-gray-600">({{ $employee->employee_code }})</span>
                    @if($employee->supplier)
                        <span class="text-gray-500">- {{ $employee->supplier->supplier_name }}</span>
                    @endif
                </div>
                <div class="text-center text-xs text-gray-600 mb-1">
                    @if($dateFrom && $dateTo)
                        Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}
                    @elseif($dateFrom)
                        From {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }}
                    @elseif($dateTo)
                        Up to {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}
                    @else
                        All Transactions
                    @endif
                    |
                    Opening Balance: {{ number_format($summary['opening_balance'], 2) }} |
                    Closing Balance: {{ number_format($summary['closing_balance'], 2) }}
                </div>
                <div class="print-only print-info text-xs text-center mb-2">
                    Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                </div>

                <table class="report-table" style="table-layout: auto;">
                    <thead>
                        <tr class="bg-gray-50 text-center">
                            <th>Sr#</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Settlement</th>
                            <th>Description</th>
                            <th>Opening Balance</th>
                            <th>Credit Sales</th>
                            <th>Recoveries</th>
                            <th>Closing Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($summary['opening_balance'] != 0)
                            <tr class="bg-gray-50 font-semibold">
                                <td class="text-center">-</td>
                                <td colspan="4">Opening Balance</td>
                                <td class="text-right font-mono">{{ number_format($summary['opening_balance'], 2) }}</td>
                                <td class="text-right font-mono">0.00</td>
                                <td class="text-right font-mono">0.00</td>
                                <td class="text-right font-mono font-bold {{ $summary['opening_balance'] > 0 ? 'text-orange-700' : 'text-green-700' }}">
                                    {{ number_format($summary['opening_balance'], 2) }}
                                </td>
                            </tr>
                        @endif
                        @forelse ($entries as $index => $entry)
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">
                                    {{ $entries->firstItem() + $index }}
                                </td>
                                <td class="whitespace-nowrap" style="vertical-align: middle;">
                                    {{ \Carbon\Carbon::parse($entry->transaction_date)->format('d-m-Y') }}
                                </td>
                                <td style="vertical-align: middle;">
                                    {{ $entry->customer_name ?? 'N/A' }}
                                    <div class="text-xs text-gray-500">{{ $entry->customer_code }}</div>
                                </td>
                                <td style="vertical-align: middle;">
                                    @if($entry->sales_settlement_id)
                                        <a href="{{ route('sales-settlements.show', $entry->sales_settlement_id) }}"
                                            class="text-blue-600 hover:text-blue-800 font-semibold no-print" target="_blank">
                                            {{ $entry->settlement_number ?? $entry->reference_number }}
                                        </a>
                                        <span class="print-only">{{ $entry->settlement_number ?? $entry->reference_number }}</span>
                                    @else
                                        {{ $entry->reference_number ?? '-' }}
                                    @endif
                                </td>
                                <td style="vertical-align: middle;">
                                    {{ $entry->description ?? ($entry->invoice_number ? 'Inv: '.$entry->invoice_number : '-') }}
                                </td>
                                <td class="text-right font-mono text-gray-700" style="vertical-align: middle;">
                                    {{ number_format($entry->row_opening_balance, 2) }}
                                </td>
                                <td class="text-right font-mono {{ $entry->debit > 0 ? 'text-blue-700' : '' }}" style="vertical-align: middle;">
                                    {{ number_format($entry->debit ?? 0, 2) }}
                                </td>
                                <td class="text-right font-mono {{ $entry->credit > 0 ? 'text-green-700' : '' }}" style="vertical-align: middle;">
                                    {{ number_format($entry->credit ?? 0, 2) }}
                                </td>
                                <td class="text-right font-mono font-bold {{ $entry->balance > 0 ? 'text-orange-700' : 'text-green-700' }}" style="vertical-align: middle;">
                                    {{ number_format($entry->balance, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-gray-500">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="5" class="text-center px-2 py-1">
                                Page Total ({{ $entries->count() }} entries)
                            </td>
                            <td class="text-right font-mono px-2 py-1">-</td>
                            <td class="text-right font-mono px-2 py-1 text-blue-700">
                                {{ number_format($entries->sum('debit'), 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1 text-green-700">
                                {{ number_format($entries->sum('credit'), 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1 text-orange-700">
                                {{ number_format($summary['closing_balance'], 2) }}
                            </td>
                        </tr>
                        <tr class="bg-gray-200">
                            <td colspan="9" class="text-center px-2 py-1 text-xs">
                                Opening Balance: {{ number_format($summary['opening_balance'], 2) }} |
                                Credit Sales: {{ number_format($summary['total_credit_sales'], 2) }} |
                                Recoveries: {{ number_format($summary['total_recoveries'], 2) }} |
                                Closing Balance: {{ number_format($summary['closing_balance'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>

                @if ($entries->hasPages())
                    <div class="mt-4 no-print">
                        {{ $entries->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function () {
                $('#filter_customer_id').select2({
                    width: '100%',
                    placeholder: 'All Customers',
                    allowClear: true
                });

            });
        </script>
    @endpush
</x-app-layout>
