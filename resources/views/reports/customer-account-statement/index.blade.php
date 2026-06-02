<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Customer Account Statement" :createRoute="null" createLabel="" :showSearch="true"
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

    <x-filter-section :action="route('reports.customer-account-statement.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_search" value="Search" />
                <x-input id="filter_search" name="filter[search]" type="text" class="mt-1 block w-full"
                    :value="request('filter.search')" placeholder="Customer, account, phone, salesman..." />
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
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @if ($canViewAllSuppliers)
                        <option value="">All Suppliers</option>
                    @endif
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ (string) $supplierIdFilter === (string) $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_employee_id" value="Salesman" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Salesmen</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ request('filter.employee_id') == (string) $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>

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
                <x-label for="filter_status" value="Account Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    @foreach(['active', 'closed', 'suspended'] as $status)
                        <option value="{{ $status }}" {{ request('filter.status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_balance_min" value="Balance Min" />
                <x-input id="filter_balance_min" name="filter[balance_min]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_min')" placeholder="0.00" />
            </div>

            <div>
                <x-label for="filter_balance_max" value="Balance Max" />
                <x-input id="filter_balance_max" name="filter[balance_max]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_max')" placeholder="Any" />
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach([10, 25, 50, 100, 250] as $pageSize)
                        <option value="{{ $pageSize }}" {{ request('per_page', '50') == (string) $pageSize ? 'selected' : '' }}>
                            {{ $pageSize }}
                        </option>
                    @endforeach
                    <option value="all" {{ request('per_page') === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg print:shadow-none">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    SHAHZAIN TRADERS - MUZAFFRABAD<br>
                    CUSTOMER ACCOUNT STATEMENT<br>
                    <span class="text-sm font-semibold">Account Search</span><br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table tabular-nums">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="text-center w-10">#</th>
                            <th class="text-left">Account No.</th>
                            <th class="text-left">Customer</th>
                            <th class="text-left">Salesman</th>
                            <th class="text-left">Supplier</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Transactions</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Balance</th>
                            <th class="text-center no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                            @php
                                $debit = (float) ($account->total_debits ?? 0);
                                $credit = (float) ($account->total_credits ?? 0);
                                $balance = $debit - $credit;
                            @endphp
                            <tr>
                                <td class="text-center">{{ $accounts->firstItem() + $loop->index }}</td>
                                <td class="font-mono">{{ $account->account_number }}</td>
                                <td>
                                    <div class="font-semibold">{{ $account->customer?->customer_name }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $account->customer?->customer_code }}
                                        @if($account->customer?->business_name)
                                            | {{ $account->customer->business_name }}
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $account->employee?->name }}</td>
                                <td>{{ $account->employee?->supplier?->supplier_name }}</td>
                                <td class="text-center">{{ ucfirst($account->status) }}</td>
                                <td class="text-right font-mono">{{ number_format($account->transactions_count) }}</td>
                                <td class="text-right font-mono">{{ number_format($debit, 2) }}</td>
                                <td class="text-right font-mono">{{ number_format($credit, 2) }}</td>
                                <td class="text-right font-bold font-mono">{{ number_format($balance, 2) }}</td>
                                <td class="text-center no-print">
                                    <a href="{{ route('reports.customer-account-statement.show', $account) }}"
                                        class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                                        Statement
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center italic text-gray-500">No customer accounts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 no-print">
                {{ $accounts->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
