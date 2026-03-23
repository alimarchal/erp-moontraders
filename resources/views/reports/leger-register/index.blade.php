<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Supplier Ledger Register" :createRoute="null" createLabel="" :showSearch="true"
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

            .doc-badge {
                display: inline-block;
                padding: 1px 6px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
            }

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;
                    size: landscape;

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
    @if (session('success'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ session('error') }}
            </div>
        </div>
    @endif

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
    <x-filter-section :action="route('reports.leger-register.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ (string) request('filter.supplier_id', $selectedSupplier?->id) === (string) $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                            @if ($supplier->short_name)
                                ({{ $supplier->short_name }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_date_from" value="Date From" />
                <x-input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full"
                    :value="request('filter.date_from', $dateFrom)" />
            </div>

            <div>
                <x-label for="filter_date_to" value="Date To" />
                <x-input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full"
                    :value="request('filter.date_to', $dateTo)" />
            </div>

            <div>
                <x-label for="filter_document_type" value="Document Type" />
                <select id="filter_document_type" name="filter[document_type][]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($documentTypes as $type)
                        <option value="{{ $type->value }}"
                            {{ in_array($type->value, (array) request('filter.document_type', [])) ? 'selected' : '' }}>
                            {{ $type->value }} - {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_document_number" value="Document Number" />
                <x-input id="filter_document_number" name="filter[document_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.document_number')" placeholder="Search doc #..." />
            </div>

            <div>
                <x-label for="filter_sap_code" value="SAP Code" />
                <x-input id="filter_sap_code" name="filter[sap_code]" type="text" class="mt-1 block w-full"
                    :value="request('filter.sap_code')" placeholder="Search SAP code..." />
            </div>

            <div>
                <x-label for="sort" value="Sort Order" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="asc" {{ request('sort', 'asc') === 'asc' ? 'selected' : '' }}>Date (Oldest First)
                    </option>
                    <option value="desc" {{ request('sort') === 'desc' ? 'selected' : '' }}>Date (Newest First)</option>
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page') == 250 ? 'selected' : '' }}>250</option>
                    <option value="all" {{ request('per_page') === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Online Amount</div>
                <div class="text-2xl font-bold text-green-700">
                    {{ number_format($totals->total_online ?? 0, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                <div class="text-sm text-gray-500">Total Invoice Amount</div>
                <div class="text-2xl font-bold text-red-700">
                    {{ number_format($totals->total_invoice ?? 0, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="text-sm text-gray-500">Total Expenses</div>
                <div class="text-2xl font-bold text-orange-700">
                    {{ number_format($totals->total_expenses ?? 0, 2) }}</div>
            </div>
            <div
                class="bg-white rounded-lg shadow p-4 border-l-4 {{ $currentBalance >= 0 ? 'border-blue-500' : 'border-red-500' }}">
                <div class="text-sm text-gray-500">Current Balance</div>
                <div class="text-2xl font-bold {{ $currentBalance >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                    {{ number_format($currentBalance, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Main Report --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header (Excel-style) --}}
                <div class="mb-4">
                    <table class="w-full border-collapse border border-black text-sm">
                        <tr>
                            <td class="border border-black px-3 py-1 font-extrabold" style="width: 35%;">LEDGER TO THE
                                COMPANY</td>
                            <td class="border border-black px-3 py-1 font-bold text-center" style="width: 15%;">
                                {{ \Carbon\Carbon::parse($dateFrom)->format('Y') }}</td>
                            <td class="border border-black px-3 py-1 font-bold text-center" style="width: 15%;">SAP CODE
                            </td>
                            <td class="border border-black px-3 py-1 font-bold text-center" style="width: 15%;">MONTH
                            </td>
                            <td class="border border-black px-3 py-1 text-center" style="width: 20%;" rowspan="2">
                                @if ($selectedSupplier)
                                    <div class="font-bold text-lg">{{ $selectedSupplier->short_name ?? $selectedSupplier->supplier_name }}</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black px-3 py-1 font-extrabold">
                                {{ $selectedSupplier?->supplier_name ?? 'All Suppliers' }}</td>
                            <td class="border border-black px-3 py-1 text-center">
                                {{ $entries->first()?->sap_code ?? '-' }}</td>
                            <td class="border border-black px-3 py-1 text-center">-</td>
                            <td class="border border-black px-3 py-1 text-center font-bold">
                                {{ \Carbon\Carbon::parse($dateFrom)->format('F') }}</td>
                        </tr>
                        <tr>
                            <td class="border border-black px-3 py-1"></td>
                            <td class="border border-black px-3 py-1 text-right font-mono font-bold">
                                {{ number_format($totals->total_online ?? 0, 2) }}</td>
                            <td class="border border-black px-3 py-1 text-right font-mono font-bold">
                                {{ number_format($totals->total_invoice ?? 0, 2) }}</td>
                            <td class="border border-black px-3 py-1 text-right font-mono font-bold">
                                {{ number_format($totals->total_expenses ?? 0, 2) }}</td>
                            <td class="border border-black px-3 py-1 text-right font-mono font-bold">
                                {{ number_format($totals->total_za ?? 0, 2) }}</td>
                        </tr>
                    </table>
                    <span class="print-only print-info text-xs text-center block mt-1">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </div>

                {{-- Data Table --}}
                <div x-data="legerRegister()">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 35px;">Sr#</th>
                            <th style="width: 90px;">Date</th>
                            <th style="width: 55px;">Type</th>
                            <th style="width: 100px;">Document #</th>
                            <th style="width: 100px;">Online Amount</th>
                            <th style="width: 110px;">Invoice Amount</th>
                            <th style="width: 90px;">Expenses</th>
                            <th style="width: 90px;">ZA(0.5%)</th>
                            <th style="width: 90px;">Claim Adjust</th>
                            <th style="width: 80px;">Adv.Tax</th>
                            <th style="width: 110px;">Balance</th>
                            <th style="width: 70px;" class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Opening Balance Row --}}
                        @if ($openingBalance != 0)
                            <tr class="bg-yellow-50">
                                <td class="text-center" colspan="4">
                                    <strong>Opening Balance (Before {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }})</strong>
                                </td>
                                <td class="amount-cell">-</td>
                                <td class="amount-cell">-</td>
                                <td class="amount-cell">-</td>
                                <td class="amount-cell">-</td>
                                <td class="amount-cell">-</td>
                                <td class="amount-cell">-</td>
                                <td class="amount-cell font-bold {{ $openingBalance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ number_format($openingBalance, 2) }}
                                </td>
                                <td class="no-print"></td>
                            </tr>
                        @endif

                        @forelse ($entries as $index => $entry)
                            <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                                <td class="text-center" style="vertical-align: middle;">
                                    {{ $entries->firstItem() + $index }}</td>
                                <td style="vertical-align: middle;">
                                    {{ $entry->transaction_date->format('d.m.Y') }}</td>
                                <td class="text-center" style="vertical-align: middle;">
                                    @if ($entry->document_type)
                                        <span class="doc-badge {{ $entry->document_type->badgeColor() }}">
                                            {{ $entry->document_type->value }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="font-mono text-xs" style="vertical-align: middle;">
                                    {{ $entry->document_number ?? '-' }}</td>
                                <td class="amount-cell {{ $entry->online_amount > 0 ? 'text-green-700' : '' }}"
                                    style="vertical-align: middle;">
                                    {{ $entry->online_amount > 0 ? number_format($entry->online_amount, 2) : '-' }}
                                </td>
                                <td class="amount-cell {{ $entry->invoice_amount > 0 ? 'text-blue-700' : '' }}"
                                    style="vertical-align: middle;">
                                    {{ $entry->invoice_amount > 0 ? number_format($entry->invoice_amount, 2) : '-' }}
                                </td>
                                <td class="amount-cell" style="vertical-align: middle;">
                                    {{ $entry->expenses_amount > 0 ? number_format($entry->expenses_amount, 2) : '-' }}
                                </td>
                                <td class="amount-cell" style="vertical-align: middle;">
                                    {{ $entry->za_point_five_percent_amount > 0 ? number_format($entry->za_point_five_percent_amount, 2) : '-' }}
                                </td>
                                <td class="amount-cell" style="vertical-align: middle;">
                                    {{ $entry->claim_adjust_amount > 0 ? number_format($entry->claim_adjust_amount, 2) : '-' }}
                                </td>
                                <td class="amount-cell" style="vertical-align: middle;">
                                    {{ $entry->advance_tax_amount > 0 ? number_format($entry->advance_tax_amount, 2) : '-' }}
                                </td>
                                <td class="amount-cell font-bold {{ $entry->running_balance >= 0 ? 'text-green-700' : 'text-red-700' }}"
                                    style="vertical-align: middle;">
                                    {{ number_format($entry->running_balance, 2) }}
                                </td>
                                <td class="text-center no-print" style="vertical-align: middle;">
                                    @can('report-audit-leger-register-manage')
                                        <div class="flex justify-center gap-1">
                                            <button type="button"
                                                @click="openEditModal({{ json_encode([
                                                    'id' => $entry->id,
                                                    'supplier_id' => $entry->supplier_id,
                                                    'transaction_date' => $entry->transaction_date->format('Y-m-d'),
                                                    'document_type' => $entry->document_type?->value,
                                                    'document_number' => $entry->document_number,
                                                    'sap_code' => $entry->sap_code,
                                                    'online_amount' => $entry->online_amount,
                                                    'invoice_amount' => $entry->invoice_amount,
                                                    'expenses_amount' => $entry->expenses_amount,
                                                    'za_point_five_percent_amount' => $entry->za_point_five_percent_amount,
                                                    'claim_adjust_amount' => $entry->claim_adjust_amount,
                                                    'advance_tax_amount' => $entry->advance_tax_amount,
                                                    'remarks' => $entry->remarks,
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
                                            <form
                                                action="{{ route('reports.leger-register.destroy', $entry) }}"
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this entry?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="inline-flex items-center px-1.5 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700"
                                                    title="Delete">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-4 text-gray-500">No ledger entries found for the
                                    selected filters.</td>
                            </tr>
                        @endforelse

                        {{-- Inline Add Form Row --}}
                        @can('report-audit-leger-register-manage')
                            <tr x-show="showAddRow" x-cloak class="bg-indigo-50 no-print">
                                <td colspan="12" class="p-0">
                                    <form action="{{ route('reports.leger-register.store') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="supplier_id"
                                            value="{{ $selectedSupplier?->id ?? '' }}">
                                        <table class="w-full border-collapse">
                                            <tr>
                                                <td style="width: 35px; text-align: center; padding: 4px;">
                                                    <span class="text-indigo-600 font-bold">+</span>
                                                </td>
                                                <td style="width: 90px; padding: 4px;">
                                                    <input type="date" name="transaction_date"
                                                        value="{{ old('transaction_date', now()->format('Y-m-d')) }}"
                                                        class="inline-input" required>
                                                </td>
                                                <td style="width: 55px; padding: 4px;">
                                                    <select name="document_type" class="inline-select">
                                                        <option value="">-</option>
                                                        @foreach ($documentTypes as $type)
                                                            <option value="{{ $type->value }}"
                                                                {{ old('document_type') === $type->value ? 'selected' : '' }}>
                                                                {{ $type->value }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td style="width: 100px; padding: 4px;">
                                                    <input type="text" name="document_number"
                                                        value="{{ old('document_number') }}" class="inline-input"
                                                        placeholder="Doc #">
                                                </td>
                                                <td style="width: 100px; padding: 4px;">
                                                    <input type="number" name="online_amount" step="0.01"
                                                        min="0" value="{{ old('online_amount') }}"
                                                        class="inline-input" placeholder="0.00">
                                                </td>
                                                <td style="width: 110px; padding: 4px;">
                                                    <input type="number" name="invoice_amount" step="0.01"
                                                        min="0" value="{{ old('invoice_amount') }}"
                                                        class="inline-input" placeholder="0.00">
                                                </td>
                                                <td style="width: 90px; padding: 4px;">
                                                    <input type="number" name="expenses_amount" step="0.01"
                                                        min="0" value="{{ old('expenses_amount') }}"
                                                        class="inline-input" placeholder="0.00">
                                                </td>
                                                <td style="width: 90px; padding: 4px;">
                                                    <input type="number" name="za_point_five_percent_amount"
                                                        step="0.01" min="0"
                                                        value="{{ old('za_point_five_percent_amount') }}"
                                                        class="inline-input" placeholder="0.00">
                                                </td>
                                                <td style="width: 90px; padding: 4px;">
                                                    <input type="number" name="claim_adjust_amount" step="0.01"
                                                        min="0" value="{{ old('claim_adjust_amount') }}"
                                                        class="inline-input" placeholder="0.00">
                                                </td>
                                                <td style="width: 80px; padding: 4px;">
                                                    <input type="number" name="advance_tax_amount" step="0.01"
                                                        min="0" value="{{ old('advance_tax_amount') }}"
                                                        class="inline-input" placeholder="0.00">
                                                </td>
                                                <td style="width: 110px; padding: 4px; text-align: center;">
                                                    <span class="text-xs text-gray-500">Auto</span>
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
                                Page Total ({{ $entries->count() }} entries)
                            </td>
                            <td class="amount-cell px-2 py-1 text-green-700">
                                {{ number_format($entries->sum('online_amount'), 2) }}
                            </td>
                            <td class="amount-cell px-2 py-1 text-blue-700">
                                {{ number_format($entries->sum('invoice_amount'), 2) }}
                            </td>
                            <td class="amount-cell px-2 py-1">
                                {{ number_format($entries->sum('expenses_amount'), 2) }}
                            </td>
                            <td class="amount-cell px-2 py-1">
                                {{ number_format($entries->sum('za_point_five_percent_amount'), 2) }}
                            </td>
                            <td class="amount-cell px-2 py-1">
                                {{ number_format($entries->sum('claim_adjust_amount'), 2) }}
                            </td>
                            <td class="amount-cell px-2 py-1">
                                {{ number_format($entries->sum('advance_tax_amount'), 2) }}
                            </td>
                            <td class="amount-cell px-2 py-1">
                                {{ $entries->count() > 0 ? number_format($entries->last()->running_balance, 2) : '0.00' }}
                            </td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>

                {{-- Add Entry Toggle Button --}}
                @can('report-audit-leger-register-manage')
                    <div class="mt-3 no-print">
                        <button type="button" @click="showAddRow = !showAddRow"
                            class="inline-flex items-center px-3 py-1.5 text-white text-sm rounded-md transition-colors"
                            :class="showAddRow ? 'bg-gray-500 hover:bg-gray-600' : 'bg-indigo-600 hover:bg-indigo-700'">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span x-text="showAddRow ? 'Cancel' : 'Add Entry'"></span>
                        </button>
                    </div>
                @endcan
                </div>

                {{-- Company Ledger Detail Summary --}}
                @if ($selectedSupplier)
                    <div class="mt-4 flex justify-end">
                        <div class="border border-black rounded-lg overflow-hidden" style="min-width: 280px;">
                            <div class="bg-gray-800 text-white text-center py-1.5 font-bold text-sm">
                                Company Ledger Detail
                            </div>
                            <table class="w-full text-sm">
                                <tr class="border-b border-gray-300">
                                    <td class="px-3 py-1.5 font-semibold">Online:-</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-bold text-green-700">
                                        {{ number_format($totals->total_online ?? 0, 2) }}</td>
                                </tr>
                                <tr class="border-b border-gray-300">
                                    <td class="px-3 py-1.5 font-semibold">Invoicing:-</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-bold text-blue-700">
                                        {{ number_format($totals->total_invoice ?? 0, 2) }}</td>
                                </tr>
                                <tr
                                    class="{{ $currentBalance >= 0 ? 'bg-green-50' : 'bg-red-50' }}">
                                    <td class="px-3 py-1.5 font-semibold">Balance:-</td>
                                    <td
                                        class="px-3 py-1.5 text-right font-mono font-extrabold {{ $currentBalance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ number_format($currentBalance, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Document Type Legend --}}
                <div class="mt-4 no-print">
                    <div class="text-xs font-semibold text-gray-600 mb-1">Document Types:</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($documentTypes as $type)
                            <span class="doc-badge {{ $type->badgeColor() }}">
                                {{ $type->value }} = {{ $type->label() }}
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- Pagination --}}
                @if ($entries->hasPages())
                    <div class="mt-4 no-print">
                        {{ $entries->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Edit Modal with Backdrop Blur --}}
    @can('report-audit-leger-register-manage')
        <div x-data="editModal()" x-show="open" x-cloak
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
                    class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:w-full sm:max-w-2xl"
                    @click.outside="close()">

                    <div class="bg-gray-800 text-white px-6 py-3 rounded-t-xl flex justify-between items-center">
                        <h3 class="text-lg font-bold" id="edit-modal-title">Edit Ledger Entry</h3>
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
                        <input type="hidden" name="supplier_id" :value="entry.supplier_id">

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-label value="Transaction Date" />
                                <input type="date" name="transaction_date" x-model="entry.transaction_date"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required>
                            </div>
                            <div>
                                <x-label value="Document Type" />
                                <select name="document_type" x-model="entry.document_type"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">-</option>
                                    @foreach ($documentTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->value }} -
                                            {{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label value="Document Number" />
                                <x-input type="text" name="document_number" x-model="entry.document_number"
                                    class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="SAP Code" />
                                <x-input type="text" name="sap_code" x-model="entry.sap_code"
                                    class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Online Amount" />
                                <x-input type="number" name="online_amount" step="0.01" min="0"
                                    x-model="entry.online_amount" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Invoice Amount" />
                                <x-input type="number" name="invoice_amount" step="0.01" min="0"
                                    x-model="entry.invoice_amount" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Expenses" />
                                <x-input type="number" name="expenses_amount" step="0.01" min="0"
                                    x-model="entry.expenses_amount" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="ZA (0.5%)" />
                                <x-input type="number" name="za_point_five_percent_amount" step="0.01" min="0"
                                    x-model="entry.za_point_five_percent_amount" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Claim Adjust" />
                                <x-input type="number" name="claim_adjust_amount" step="0.01" min="0"
                                    x-model="entry.claim_adjust_amount" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Advance Tax" />
                                <x-input type="number" name="advance_tax_amount" step="0.01" min="0"
                                    x-model="entry.advance_tax_amount" class="mt-1 block w-full" />
                            </div>
                            <div class="col-span-2">
                                <x-label value="Remarks" />
                                <textarea name="remarks" x-model="entry.remarks" rows="2"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    placeholder="Optional notes..."></textarea>
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


    @push('scripts')
        <script>
            function legerRegister() {
                return {
                    showAddRow: false,
                    openEditModal(data) {
                        window.dispatchEvent(new CustomEvent('open-edit-modal', {
                            detail: data
                        }));
                    }
                }
            }

            function editModal() {
                return {
                    open: false,
                    entry: {},
                    formAction: '',
                    init() {
                        window.addEventListener('open-edit-modal', (e) => {
                            this.entry = e.detail;
                            this.formAction = '{{ url("reports/leger-register") }}/' + this.entry.id;
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
