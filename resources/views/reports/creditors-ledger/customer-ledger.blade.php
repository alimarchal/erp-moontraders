<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Customer Ledger - {{ $customer->customer_name }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.creditors-ledger.index" />
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


    <x-filter-section :action="route('reports.creditors-ledger.customer-ledger', $customer)" class="no-print">
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
                <x-label for="filter_employee_id" value="Salesman" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Salesmen</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ request('filter.employee_id')==(string)$employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_transaction_type" value="Transaction Type" />
                <select id="filter_transaction_type" name="filter[transaction_type]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach($transactionTypes as $type)
                        <option value="{{ $type }}" {{ request('filter.transaction_type')===$type ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $type)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_reference_number" value="Reference Number" />
                <x-input id="filter_reference_number" name="filter[reference_number]" type="text" class="mt-1 block w-full"
                    :value="request('filter.reference_number')" placeholder="Search reference..." />
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
                <x-label for="filter_payment_method" value="Payment Method" />
                <select id="filter_payment_method" name="filter[payment_method]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Methods</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method }}" {{ request('filter.payment_method')===$method ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $method)) }}
                        </option>
                    @endforeach
                </select>
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
                    <option value="25" {{ request('per_page')=='25' ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page')=='50' ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page', '100')=='100' ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page')=='250' ? 'selected' : '' }}>250</option>
                    <option value="all" {{ request('per_page')==='all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Customer Info Card --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Customer Code:</span>
                    <span class="font-semibold ml-1">{{ $customer->customer_code }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Customer Name:</span>
                    <span class="font-semibold ml-1">{{ $customer->customer_name }}</span>
                </div>
                @if($customer->business_name)
                    <div>
                        <span class="text-gray-500">Business:</span>
                        <span class="font-semibold ml-1">{{ $customer->business_name }}</span>
                    </div>
                @endif
                @if($customer->city)
                    <div>
                        <span class="text-gray-500">City:</span>
                        <span class="font-semibold ml-1">{{ $customer->city }}</span>
                    </div>
                @endif
                @if($customer->phone)
                    <div>
                        <span class="text-gray-500">Phone:</span>
                        <span class="font-semibold ml-1">{{ $customer->phone }}</span>
                    </div>
                @endif
                @if($customer->channel_type)
                    <div>
                        <span class="text-gray-500">Channel:</span>
                        <span class="font-semibold ml-1">{{ $customer->channel_type }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 no-print">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
                <div class="text-sm text-gray-500">Opening Bal.</div>
                <div class="text-xl font-bold text-gray-700">{{ number_format($summary['opening_balance'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Credit Sales</div>
                <div class="text-xl font-bold text-blue-700">{{ number_format($summary['total_debits'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Recoveries</div>
                <div class="text-xl font-bold text-green-700">{{ number_format($summary['total_credits'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="text-sm text-gray-500">Closing Bal.</div>
                <div class="text-xl font-bold {{ $summary['closing_balance'] > 0 ? 'text-orange-700' : 'text-green-700' }}">
                    {{ number_format($summary['closing_balance'], 2) }}
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-1">
                    Moon Traders<br>
                    Customer Ledger
                </p>
                <div class="text-center text-sm mb-1">
                    <span class="font-bold">{{ $customer->customer_name }}</span>
                    <span class="text-gray-600">({{ $customer->customer_code }})</span>
                    @if($customer->business_name)
                        <span class="text-gray-500">- {{ $customer->business_name }}</span>
                    @endif
                </div>
                <div class="text-center text-xs text-gray-600 mb-1">
                    @if($dateFrom && $dateTo)
                        Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}
                    @else
                        All Transactions
                    @endif
                    |
                    Opening Bal.: {{ number_format($summary['opening_balance'], 2) }} |
                    Closing Bal.: {{ number_format($summary['closing_balance'], 2) }}
                </div>
                <div class="print-only print-info text-xs text-center mb-2">
                    Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                </div>

                <table class="report-table" style="table-layout: auto;">
                    <thead>
                        <tr class="bg-gray-50 text-center">
                            <th>Sr#</th>
                            <th>Date</th>
                            <th>Salesman</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th>Type</th>
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
                                <td colspan="5">Opening Balance</td>
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
                                <td class="text-center">{{ $entries->firstItem() + $index }}</td>
                                <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($entry->transaction_date)->format('d-m-Y') }}</td>
                                <td class="text-center">{{ $entry->employee_name ?? '-' }}</td>
                                <td class="text-center">
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
                                <td>{{ $entry->description ?? '-' }}</td>
                                <td class="text-center whitespace-nowrap">{{ ucwords(str_replace('_', ' ', $entry->transaction_type)) }}</td>
                                <td class="text-right font-mono">
                                    {{ number_format($entry->row_opening_balance, 2) }}
                                </td>
                                <td class="text-right font-mono {{ $entry->debit > 0 ? 'text-blue-700' : '' }}">
                                    {{ number_format($entry->debit ?? 0, 2) }}
                                </td>
                                <td class="text-right font-mono {{ $entry->credit > 0 ? 'text-green-700' : '' }}">
                                    {{ number_format($entry->credit ?? 0, 2) }}
                                </td>
                                <td class="text-right font-mono font-bold {{ $entry->balance > 0 ? 'text-orange-700' : 'text-green-700' }}">
                                    {{ number_format($entry->balance, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-gray-500">No ledger entries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="6" class="text-center px-2 py-1">
                                Page Total ({{ $entries->count() }} entries)
                            </td>
                            <td class="text-right font-mono px-2 py-1">
                                -
                            </td>
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
                            <td colspan="10" class="text-center px-2 py-1 text-xs">
                                Opening Bal.: {{ number_format($summary['opening_balance'], 2) }} |
                                Credit Sales: {{ number_format($summary['total_debits'], 2) }} |
                                Recoveries: {{ number_format($summary['total_credits'], 2) }} |
                                Closing Bal.: {{ number_format($summary['closing_balance'], 2) }}
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
</x-app-layout>
