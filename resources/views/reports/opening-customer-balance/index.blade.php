<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Opening Customer Balance Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 13px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .report-table th {
                background-color: #f3f4f6;
                font-weight: 600;
                text-align: center;
            }

            .amount-cell {
                text-align: right;
                font-family: ui-monospace, monospace;
                white-space: nowrap;
            }

            .inline-input {
                width: 100%;
                padding: 2px 4px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 12px;
                font-family: ui-monospace, monospace;
            }

            .inline-input:focus {
                outline: none;
                border-color: #6366f1;
                box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            }

            .inline-select {
                width: 100%;
                padding: 2px 4px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 12px;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    /* margin: 15mm 10mm 20mm 10mm;
                    size: landscape; */

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
                    font-size: 10px !important;
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
        </style>
    @endpush

    {{-- Status Messages --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <x-status-message />
    </div>

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Filter Section --}}
    <x-filter-section :action="route('reports.opening-customer-balance.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Supplier --}}
            <div>
                <x-label for="supplier_id" value="Supplier" />
                <select id="supplier_id" name="supplier_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ (string) $supplierId === (string) $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                            @if ($supplier->short_name)
                                ({{ $supplier->short_name }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Employee --}}
            <div>
                <x-label for="employee_id" value="Employee / Salesman" />
                <select id="employee_id" name="employee_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}"
                            {{ (string) $employeeId === (string) $employee->id ? 'selected' : '' }}>
                            {{ $employee->employee_code }} — {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Customer --}}
            <div>
                <x-label for="customer_id" value="Customer" />
                <select id="customer_id" name="customer_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Customers</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}"
                            {{ (string) $customerId === (string) $customer->id ? 'selected' : '' }}>
                            {{ $customer->customer_code }} — {{ $customer->customer_name }}
                            @if ($customer->address)
                                — {{ $customer->address }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Reference Number --}}
            <div>
                <x-label for="reference_number" value="Reference Number" />
                <x-input id="reference_number" name="reference_number" type="text" class="mt-1 block w-full"
                    :value="$referenceNumber ?? ''" placeholder="OCB-M-..." />
            </div>

            {{-- Posted Status --}}
            <div>
                <x-label for="posted_status" value="Posted Status" />
                <select id="posted_status" name="posted_status"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="posted" {{ ($postedStatus ?? '') === 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="unposted" {{ ($postedStatus ?? '') === 'unposted' ? 'selected' : '' }}>Unposted</option>
                </select>
            </div>

            {{-- Per Page --}}
            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ $perPage == 250 ? 'selected' : '' }}>250</option>
                    <option value="all" {{ $perPage === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Summary Card --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Opening Balances</div>
                <div class="text-2xl font-bold text-blue-700">
                    {{ number_format($totalDebit, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
                <div class="text-sm text-gray-500">Total Entries</div>
                <div class="text-2xl font-bold text-emerald-700">
                    {{ $transactions->total() }}</div>
            </div>
            @if ($selectedSupplier)
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-teal-500">
                    <div class="text-sm text-gray-500">Supplier</div>
                    <div class="text-2xl font-bold text-teal-700">
                        {{ $selectedSupplier->short_name ?? $selectedSupplier->supplier_name }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Main Report --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header --}}
                <div class="mb-4">
                    <p class="text-center font-extrabold mb-2">
                        Moon Traders<br>
                        Opening Customer Balance Report
                        @if ($selectedSupplier)
                            <br><span class="text-sm font-semibold">{{ $selectedSupplier->supplier_name }}</span>
                        @endif
                    </p>
                    <span class="print-only print-info text-xs text-center block mt-1">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </div>

                {{-- Data Table --}}
                <div x-data="ocbReport()">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th style="width: 35px;">Sr#</th>
                                <th style="width: 90px;">Date</th>
                                <th>Salesman</th>
                                <th>Customer</th>
                                <th style="width: 110px;">Opening Balance</th>
                                <th style="width: 60px;">Status</th>
                                @canany(['opening-customer-balance-edit', 'opening-customer-balance-delete', 'opening-customer-balance-post'])
                                    <th style="width: 70px;" class="no-print">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transactions as $index => $transaction)
                                <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                                    <td class="text-center" style="vertical-align: middle;">
                                        {{ $transactions->firstItem() + $index }}</td>
                                    <td style="vertical-align: middle;">
                                        {{ $transaction->transaction_date->format('d.m.Y') }}</td>
                                    <td style="vertical-align: middle;">
                                        {{ $transaction->account->employee->name ?? '-' }}
                                        <span class="text-xs text-black">({{ $transaction->account->employee->employee_code ?? '' }})</span>
                                    </td>
                                    <td style="vertical-align: middle;">
                                        {{ $transaction->account->customer->customer_name ?? '-' }}
                                        <span class="text-xs text-black">({{ $transaction->account->account_number ?? '-' }})</span>
                                        @if ($transaction->account->customer->address)
                                            — <span class="text-xs text-black">{{ $transaction->account->customer->address }}</span>
                                        @endif
                                    </td>
                                    <td class="amount-cell text-green-700" style="vertical-align: middle;">
                                        {{ number_format($transaction->debit, 2) }}
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        @if ($transaction->isPosted())
                                            <span class="text-xs text-gray-400" title="Posted {{ $transaction->posted_at->format('d-M-Y H:i') }}">
                                                <svg class="w-4 h-4 inline text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </span>
                                        @else
                                            <span class="inline-block px-1.5 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                                                Draft
                                            </span>
                                        @endif
                                    </td>
                                    @canany(['opening-customer-balance-edit', 'opening-customer-balance-delete', 'opening-customer-balance-post'])
                                    <td class="text-center no-print" style="vertical-align: middle;">
                                        @if (!$transaction->isPosted())
                                            <div class="flex justify-center gap-1">
                                                @can('opening-customer-balance-edit')
                                                    <button type="button"
                                                        @click="openEditModal({{ json_encode([
                                                            'id' => $transaction->id,
                                                            'balance_date' => $transaction->transaction_date->format('Y-m-d'),
                                                            'opening_balance' => (float) $transaction->debit,
                                                            'description' => $transaction->description,
                                                            'employee_name' => ($transaction->account->employee->employee_code ?? '') . ' — ' . ($transaction->account->employee->name ?? ''),
                                                            'customer_name' => ($transaction->account->customer->customer_code ?? '') . ' — ' . ($transaction->account->customer->customer_name ?? ''),
                                                        ]) }})"
                                                        class="inline-flex items-center px-1.5 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                                                        title="Edit">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                @endcan
                                                @can('opening-customer-balance-post')
                                                    <button type="button" x-data
                                                        x-on:click="$dispatch('open-ocb-post-modal', { url: '{{ route('reports.opening-customer-balance.post', $transaction) }}' })"
                                                        class="inline-flex items-center px-1.5 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700"
                                                        title="Post to GL">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </button>
                                                @endcan
                                                @can('opening-customer-balance-delete')
                                                    <button type="button" x-data
                                                        x-on:click="$dispatch('open-ocb-delete-modal', { url: '{{ route('reports.opening-customer-balance.destroy', $transaction) }}' })"
                                                        class="inline-flex items-center px-1.5 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700"
                                                        title="Delete">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                @endcan
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400" title="Posted">✓</span>
                                        @endif
                                    </td>
                                @endcanany
                                </tr>
                            @empty
                                @if (isset($hasFilters) && !$hasFilters)
                                    <tr>
                                        <td colspan="7" class="text-center py-8 text-gray-500">
                                            <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                            </svg>
                                            <p class="font-semibold text-gray-600">Please select filters to view data</p>
                                            <p class="text-xs mt-1">Choose a supplier, salesman, or customer from the filters above.</p>
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-gray-500">No opening customer balance entries found for the selected filters.</td>
                                    </tr>
                                @endif
                            @endforelse

                            {{-- Inline Add Form Row --}}
                            @can('opening-customer-balance-create')
                                <tr x-show="showAddRow" x-cloak class="bg-indigo-50 no-print">
                                    <td colspan="7" class="p-0">
                                        <form action="{{ route('reports.opening-customer-balance.store') }}" method="POST">
                                            @csrf
                                            <table class="w-full border-collapse">
                                                <tr>
                                                    <td style="width: 35px; text-align: center; padding: 4px;">
                                                        <span class="text-indigo-600 font-bold">+</span>
                                                    </td>
                                                    <td style="width: 90px; padding: 4px;">
                                                        <input type="date" name="balance_date"
                                                            value="{{ old('balance_date', now()->format('Y-m-d')) }}"
                                                            class="inline-input" required>
                                                    </td>
                                                    <td style="width: 130px; padding: 4px;">
                                                        <select name="supplier_id" id="add_supplier_id" class="select2" required
                                                            style="width: 100%;">
                                                            <option value="">Select Supplier</option>
                                                            @foreach ($suppliers as $supplier)
                                                                <option value="{{ $supplier->id }}"
                                                                    {{ old('supplier_id', $supplierId) == $supplier->id ? 'selected' : '' }}>
                                                                    {{ $supplier->short_name ?? Str::limit($supplier->supplier_name, 15) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td style="width: 180px; padding: 4px;">
                                                        <select name="employee_id" id="add_employee_id" class="select2" required
                                                            style="width: 100%;" disabled>
                                                            <option value="">Select Salesman</option>
                                                        </select>
                                                    </td>
                                                    <td colspan="4" style="padding: 4px;">
                                                        <select name="customer_id" class="select2" required
                                                            style="width: 100%;">
                                                            <option value="">Select Customer</option>
                                                            @foreach ($customers as $customer)
                                                                <option value="{{ $customer->id }}"
                                                                    {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                                                    {{ $customer->customer_code }} — {{ $customer->customer_name }}{{ $customer->address ? ' — ' . Str::limit($customer->address, 25) : '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td style="width: 120px; padding: 4px;">
                                                        <input type="number" name="opening_balance" step="0.01"
                                                            min="0.01" value="{{ old('opening_balance') }}"
                                                            class="inline-input" placeholder="Amount" required
                                                            style="height: 36px; padding: 4px 6px; box-sizing: border-box;">
                                                    </td>
                                                    <td style="width: 70px; padding: 4px; text-align: center;">
                                                        <button type="submit"
                                                            class="inline-flex items-center px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 font-semibold">
                                                            Save
                                                        </button>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </td>
                                </tr>
                            @endcan
                        </tbody>

                        {{-- Footer Totals --}}
                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="4" class="text-center px-2 py-1">
                                    Total ({{ $transactions->total() }} entries)
                                </td>
                                <td class="amount-cell px-2 py-1 text-green-700">
                                    {{ number_format($totalDebit, 2) }}
                                </td>
                                <td colspan="2" class="no-print"></td>
                            </tr>
                        </tfoot>
                    </table>

                    {{-- Add Entry Toggle Button & Export --}}
                    @if (isset($hasFilters) && $hasFilters)
                        <div class="mt-3 no-print flex items-center gap-2">
                            @can('opening-customer-balance-create')
                                <button type="button" @click="showAddRow = !showAddRow"
                                    class="inline-flex items-center px-3 py-1.5 text-white text-sm rounded-md transition-colors"
                                    :class="showAddRow ? 'bg-gray-500 hover:bg-gray-600' : 'bg-indigo-600 hover:bg-indigo-700'">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    <span x-text="showAddRow ? 'Cancel' : 'Add Entry'"></span>
                                </button>
                            @endcan
                            <a href="{{ route('reports.opening-customer-balance.export.excel', request()->query()) }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold rounded-md shadow transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Export to Excel
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Pagination --}}
                @if ($transactions->hasPages())
                    <div class="mt-4 no-print">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Edit Modal with Backdrop Blur --}}
    @can('opening-customer-balance-edit')
        <div x-data="ocbEditModal()" x-show="open" x-cloak
            class="fixed inset-0 z-50 overflow-y-auto no-print" style="display: none;"
            aria-labelledby="edit-modal-title" role="dialog" aria-modal="true">

            {{-- Backdrop with blur --}}
            <div x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all"
                @click="close()">
            </div>

            {{-- Modal Panel --}}
            <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
                <div x-show="open"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:w-full sm:max-w-lg"
                    @click.outside="close()">

                    <div class="bg-gray-800 text-white px-6 py-3 rounded-t-xl flex justify-between items-center">
                        <h3 class="text-lg font-bold" id="edit-modal-title">Edit Opening Balance</h3>
                        <button @click="close()" class="text-gray-300 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form :action="formAction" method="POST" class="p-6">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-label value="Employee / Salesman" />
                                <x-input type="text" x-model="entry.employee_name"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Customer" />
                                <x-input type="text" x-model="entry.customer_name"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100" disabled readonly />
                            </div>
                            <div>
                                <x-label value="Balance Date" />
                                <input type="date" name="balance_date" x-model="entry.balance_date"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required>
                            </div>
                            <div>
                                <x-label value="Opening Balance" />
                                <x-input type="number" name="opening_balance" step="0.01" min="0.01"
                                    x-model="entry.opening_balance" class="mt-1 block w-full" required />
                            </div>
                            <div class="col-span-2">
                                <x-label value="Description" />
                                <textarea name="description" x-model="entry.description" rows="2"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    placeholder="Optional description"></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="close()"
                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm font-medium">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                                Update Entry
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    {{-- Delete Confirmation Modal --}}
    @can('opening-customer-balance-delete')
        <x-alpine-confirmation-modal eventName="open-ocb-delete-modal" title="Delete Opening Balance"
            confirmButtonText="Delete" confirmButtonClass="bg-red-600 hover:bg-red-700" csrfMethod="DELETE">
            <p class="text-sm text-gray-600">
                Are you sure you want to delete this opening balance? This action cannot be undone.
            </p>
        </x-alpine-confirmation-modal>
    @endcan

    {{-- Post Confirmation Modal --}}
    @can('opening-customer-balance-post')
        <x-alpine-confirmation-modal eventName="open-ocb-post-modal" title="Post to General Ledger"
            confirmButtonText="Post to GL" confirmButtonClass="bg-green-600 hover:bg-green-700"
            iconBgClass="bg-green-100" iconColorClass="text-green-600"
            iconPath="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z">
            <p class="text-sm text-gray-600">
                Are you sure you want to post this opening balance to the General Ledger? This action cannot be reversed.
            </p>
        </x-alpine-confirmation-modal>
    @endcan

    @push('scripts')
        <script>
            function ocbReport() {
                return {
                    showAddRow: {{ $errors->any() || session('error') ? 'true' : 'false' }},
                    openEditModal(data) {
                        window.dispatchEvent(new CustomEvent('open-ocb-edit-modal', {
                            detail: data
                        }));
                    }
                }
            }

            function loadEmployeesForSupplier(supplierId, preselectEmployeeId) {
                const $employee = $('#add_employee_id');

                if ($employee.data('select2')) {
                    $employee.select2('destroy');
                }
                $employee.empty().append('<option value="">Select Salesman</option>');
                $employee.val('').prop('disabled', true);

                if (!supplierId) {
                    $employee.select2({ placeholder: 'Select Salesman', allowClear: true, width: '100%' });
                    return;
                }

                const params = new URLSearchParams();
                params.append('supplier_ids[]', supplierId);

                fetch(`/api/employees/by-suppliers?${params.toString()}`)
                    .then(response => response.json())
                    .then(employees => {
                        $employee.empty().append('<option value="">Select Salesman</option>');
                        employees.forEach(emp => {
                            if (emp.supplier_id) {
                                $employee.append(
                                    `<option value="${emp.id}">${emp.employee_code} — ${emp.name}</option>`
                                );
                            }
                        });
                        $employee.prop('disabled', false);
                        if (preselectEmployeeId) {
                            $employee.val(preselectEmployeeId);
                        }
                        $employee.select2({ placeholder: 'Select Salesman', allowClear: true, width: '100%' });
                    })
                    .catch(error => console.error('Error loading employees:', error));
            }

            $(document).ready(function () {
                $('#add_supplier_id').on('change', function () {
                    loadEmployeesForSupplier($(this).val());
                });

                const initialSupplier = $('#add_supplier_id').val();
                const initialEmployee = '{{ $employeeId }}';
                if (initialSupplier) {
                    loadEmployeesForSupplier(initialSupplier, initialEmployee || null);
                }
            });

            function ocbEditModal() {
                return {
                    open: false,
                    entry: {},
                    formAction: '',
                    init() {
                        window.addEventListener('open-ocb-edit-modal', (e) => {
                            this.entry = e.detail;
                            this.formAction = '{{ url("reports/opening-customer-balance") }}/' + this.entry.id;
                            this.open = true;
                        });
                    },
                    close() {
                        this.open = false;
                        this.entry = {};
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>
