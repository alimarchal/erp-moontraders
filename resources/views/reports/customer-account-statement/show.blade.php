<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Statement - {{ $account->account_number }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.customer-account-statement.index" />
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

                .page-footer {
                    display: none;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.customer-account-statement.show', $account)" class="no-print">
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
                <x-label for="filter_transaction_type" value="Transaction Type" />
                <select id="filter_transaction_type" name="filter[transaction_type]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach($transactionTypes as $type)
                        <option value="{{ $type }}" {{ request('filter.transaction_type') === $type ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $type)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_payment_method" value="Payment Method" />
                <select id="filter_payment_method" name="filter[payment_method]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Methods</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method }}" {{ request('filter.payment_method') === $method ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $method)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_reference_number" value="Reference Number" />
                <x-input id="filter_reference_number" name="filter[reference_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.reference_number')" placeholder="Reference..." />
            </div>

            <div>
                <x-label for="filter_invoice_number" value="Invoice Number" />
                <x-input id="filter_invoice_number" name="filter[invoice_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.invoice_number')" placeholder="Invoice..." />
            </div>

            <div>
                <x-label for="filter_description" value="Description" />
                <x-input id="filter_description" name="filter[description]" type="text" class="mt-1 block w-full"
                    :value="request('filter.description')" placeholder="Description..." />
            </div>

            <div>
                <x-label for="filter_amount_min" value="Amount Min" />
                <x-input id="filter_amount_min" name="filter[amount_min]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.amount_min')" placeholder="0.00" />
            </div>

            <div>
                <x-label for="filter_amount_max" value="Amount Max" />
                <x-input id="filter_amount_max" name="filter[amount_max]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.amount_max')" placeholder="Any" />
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach([10, 25, 50, 100, 250] as $pageSize)
                        <option value="{{ $pageSize }}" {{ request('per_page', '100') == (string) $pageSize ? 'selected' : '' }}>
                            {{ $pageSize }}
                        </option>
                    @endforeach
                    <option value="all" {{ request('per_page') === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Account No:</span>
                    <span class="font-semibold ml-1 font-mono">{{ $account->account_number }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Customer:</span>
                    <span class="font-semibold ml-1">{{ $account->customer?->customer_name }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Salesman:</span>
                    <span class="font-semibold ml-1">{{ $account->employee?->name }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Supplier:</span>
                    <span class="font-semibold ml-1">{{ $account->employee?->supplier?->supplier_name }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Customer Code:</span>
                    <span class="font-semibold ml-1">{{ $account->customer?->customer_code }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Business:</span>
                    <span class="font-semibold ml-1">{{ $account->customer?->business_name ?: '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Phone:</span>
                    <span class="font-semibold ml-1">{{ $account->customer?->phone ?: '-' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Status:</span>
                    <span class="font-semibold ml-1">{{ ucfirst($account->status) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 no-print">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
                <div class="text-sm text-gray-500">Opening Balance</div>
                <div class="text-xl font-bold text-gray-700">{{ number_format($summary['opening_balance'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Debit</div>
                <div class="text-xl font-bold text-blue-700">{{ number_format($summary['total_debits'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Credit</div>
                <div class="text-xl font-bold text-green-700">{{ number_format($summary['total_credits'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="text-sm text-gray-500">Closing Balance</div>
                <div class="text-xl font-bold text-orange-700">{{ number_format($summary['closing_balance'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg print:shadow-none">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    SHAHZAIN TRADERS - MUZAFFRABAD<br>
                    CUSTOMER ACCOUNT STATEMENT<br>
                    <span class="text-sm font-semibold">
                        {{ $account->customer?->customer_name }} | {{ $account->account_number }}
                    </span><br>
                    <span class="text-sm font-semibold">
                        Salesman: {{ $account->employee?->name }} | Supplier: {{ $account->employee?->supplier?->supplier_name }}
                    </span><br>
                    <span class="text-sm font-semibold">
                        Period: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') : 'Start' }}
                        to {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d.m.Y') : 'End' }}
                    </span><br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table tabular-nums">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="text-center w-10">#</th>
                            <th class="text-center">Date</th>
                            <th class="text-left">Type</th>
                            <th class="text-left">Reference</th>
                            <th class="text-left">Invoice</th>
                            <th class="text-left">Description</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($entries->currentPage() === 1)
                            <tr class="bg-gray-50 font-bold">
                                <td colspan="8" class="text-right">Opening Balance</td>
                                <td class="text-right font-mono">{{ number_format($summary['opening_balance'], 2) }}</td>
                            </tr>
                        @endif

                        @forelse($entries as $entry)
                            <tr>
                                <td class="text-center">{{ $entries->firstItem() + $loop->index }}</td>
                                <td class="text-center">{{ optional($entry->transaction_date)->format('d.m.Y') }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $entry->transaction_type)) }}</td>
                                <td>{{ $entry->reference_number ?: $entry->salesSettlement?->settlement_number }}</td>
                                <td>{{ $entry->invoice_number ?: '-' }}</td>
                                <td>{{ $entry->description }}</td>
                                <td class="text-right font-mono">{{ number_format((float) $entry->debit, 2) }}</td>
                                <td class="text-right font-mono">{{ number_format((float) $entry->credit, 2) }}</td>
                                <td class="text-right font-bold font-mono">{{ number_format((float) $entry->running_balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center italic text-gray-500">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold">
                        <tr>
                            <td colspan="6" class="text-right">Period Totals:</td>
                            <td class="text-right font-mono">{{ number_format($summary['total_debits'], 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($summary['total_credits'], 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($summary['closing_balance'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-4 no-print">
                {{ $entries->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
