<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Invoice Summary" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 12px;
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
                font-size: 11px;
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
                font-size: 11px;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 10mm 5mm 15mm 5mm;
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
                    padding: 5px !important;
                    box-shadow: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 9px !important;
                    width: 100% !important;
                }

                .report-table th,
                .report-table td {
                    padding: 1px 2px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
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
    <x-filter-section :action="route('reports.invoice-summary.index')" class="no-print">
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
                <x-label for="filter_invoice_number" value="Invoice Number" />
                <x-input id="filter_invoice_number" name="filter[invoice_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.invoice_number')" placeholder="Search invoice #..." />
            </div>

            <div>
                <x-label for="sort" value="Sort Order" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="asc" {{ request('sort', 'asc') === 'asc' ? 'selected' : '' }}>Date (Oldest First)</option>
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
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Invoice Value</div>
                <div class="text-xl font-bold text-blue-700">
                    {{ number_format($totals->total_invoice_value ?? 0, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Cartons</div>
                <div class="text-xl font-bold text-green-700">
                    {{ number_format($totals->total_cartons ?? 0) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="text-sm text-gray-500">Total Sales Tax</div>
                <div class="text-xl font-bold text-orange-700">
                    {{ number_format($totals->total_sales_tax ?? 0, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Total with Tax</div>
                <div class="text-xl font-bold text-purple-700">
                    {{ number_format($totals->total_with_tax ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Main Report --}}
    <div class="max-w-full mx-auto sm:px-4 lg:px-6 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header --}}
                <div class="mb-4">
                    <p class="text-center font-extrabold mb-1">
                        INVOICE SUMMARY -
                        {{ strtoupper(\Carbon\Carbon::parse($dateFrom)->format('F Y')) }}
                    </p>
                    @if ($selectedSupplier)
                        <p class="text-center text-sm font-semibold">{{ $selectedSupplier->supplier_name }}</p>
                    @endif
                    <span class="print-only print-info text-xs text-center block mt-1">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </div>

                {{-- Data Table --}}
                <div x-data="invoiceSummary()">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;">Sr#</th>
                                <th style="width: 80px;">Date</th>
                                <th style="width: 95px;">Invoice Number</th>
                                <th style="width: 55px;">Cartons</th>
                                <th style="width: 100px;">Invoice Value</th>
                                <th style="width: 90px;">0.5% On Inv.</th>
                                <th style="width: 90px;">Discount</th>
                                <th style="width: 80px;">FMR Allow.</th>
                                <th style="width: 100px;">Disc. Before ST</th>
                                <th style="width: 80px;">Excise Duty</th>
                                <th style="width: 95px;">Sales Tax</th>
                                <th style="width: 75px;">Adv. Tax</th>
                                <th style="width: 100px;">Total with Tax</th>
                                <th style="width: 60px;" class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entries as $index => $entry)
                                <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                                    <td class="text-center">{{ $entries->firstItem() + $index }}</td>
                                    <td>{{ $entry->invoice_date->format('d.m.Y') }}</td>
                                    <td class="font-mono text-xs">{{ $entry->invoice_number }}</td>
                                    <td class="text-center">{{ number_format($entry->cartons) }}</td>
                                    <td class="amount-cell text-blue-700">{{ number_format($entry->invoice_value, 2) }}</td>
                                    <td class="amount-cell">{{ $entry->za_on_invoices > 0 ? number_format($entry->za_on_invoices, 2) : '-' }}</td>
                                    <td class="amount-cell">{{ $entry->discount_value > 0 ? number_format($entry->discount_value, 2) : '-' }}</td>
                                    <td class="amount-cell">{{ $entry->fmr_allowance > 0 ? number_format($entry->fmr_allowance, 2) : '-' }}</td>
                                    <td class="amount-cell">{{ $entry->discount_before_sales_tax > 0 ? number_format($entry->discount_before_sales_tax, 2) : '-' }}</td>
                                    <td class="amount-cell">{{ $entry->excise_duty > 0 ? number_format($entry->excise_duty, 2) : '-' }}</td>
                                    <td class="amount-cell">{{ $entry->sales_tax_value > 0 ? number_format($entry->sales_tax_value, 2) : '-' }}</td>
                                    <td class="amount-cell">{{ $entry->advance_tax > 0 ? number_format($entry->advance_tax, 2) : '-' }}</td>
                                    <td class="amount-cell font-bold">{{ number_format($entry->total_value_with_tax, 2) }}</td>
                                    <td class="text-center no-print">
                                        @can('report-audit-invoice-summary-manage')
                                            <div class="flex justify-center gap-1">
                                                <button type="button"
                                                    @click="openEditModal({{ json_encode([
                                                        'id' => $entry->id,
                                                        'supplier_id' => $entry->supplier_id,
                                                        'invoice_date' => $entry->invoice_date->format('Y-m-d'),
                                                        'invoice_number' => $entry->invoice_number,
                                                        'cartons' => $entry->cartons,
                                                        'invoice_value' => $entry->invoice_value,
                                                        'za_on_invoices' => $entry->za_on_invoices,
                                                        'discount_value' => $entry->discount_value,
                                                        'fmr_allowance' => $entry->fmr_allowance,
                                                        'discount_before_sales_tax' => $entry->discount_before_sales_tax,
                                                        'excise_duty' => $entry->excise_duty,
                                                        'sales_tax_value' => $entry->sales_tax_value,
                                                        'advance_tax' => $entry->advance_tax,
                                                        'total_value_with_tax' => $entry->total_value_with_tax,
                                                        'remarks' => $entry->remarks,
                                                    ]) }})"
                                                    class="inline-flex items-center px-1.5 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                                                    title="Edit">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <form action="{{ route('reports.invoice-summary.destroy', $entry) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this invoice?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="inline-flex items-center px-1.5 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700"
                                                        title="Delete">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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
                                    <td colspan="14" class="text-center py-4 text-gray-500">No invoice entries found for the selected filters.</td>
                                </tr>
                            @endforelse

                            {{-- Inline Add Form Row --}}
                            @can('report-audit-invoice-summary-manage')
                                <tr x-show="showAddRow" x-cloak class="bg-indigo-50 no-print">
                                    <td colspan="14" class="p-0">
                                        <form action="{{ route('reports.invoice-summary.store') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="supplier_id" value="{{ $selectedSupplier?->id ?? '' }}">
                                            <table class="w-full border-collapse">
                                                <tr>
                                                    <td style="width: 30px; text-align: center; padding: 3px;">
                                                        <span class="text-indigo-600 font-bold">+</span>
                                                    </td>
                                                    <td style="width: 80px; padding: 3px;">
                                                        <input type="date" name="invoice_date"
                                                            value="{{ old('invoice_date', now()->format('Y-m-d')) }}"
                                                            class="inline-input" required>
                                                    </td>
                                                    <td style="width: 95px; padding: 3px;">
                                                        <input type="text" name="invoice_number"
                                                            value="{{ old('invoice_number') }}" class="inline-input"
                                                            placeholder="Inv #" required>
                                                    </td>
                                                    <td style="width: 55px; padding: 3px;">
                                                        <input type="number" name="cartons" min="0"
                                                            value="{{ old('cartons') }}" class="inline-input" placeholder="0">
                                                    </td>
                                                    <td style="width: 100px; padding: 3px;">
                                                        <input type="number" name="invoice_value" step="0.01" min="0"
                                                            value="{{ old('invoice_value') }}" class="inline-input" placeholder="0.00">
                                                    </td>
                                                    <td style="width: 90px; padding: 3px;">
                                                        <input type="number" name="za_on_invoices" step="0.01" min="0"
                                                            value="{{ old('za_on_invoices') }}" class="inline-input" placeholder="0.00">
                                                    </td>
                                                    <td style="width: 90px; padding: 3px;">
                                                        <input type="number" name="discount_value" step="0.01" min="0"
                                                            value="{{ old('discount_value') }}" class="inline-input" placeholder="0.00">
                                                    </td>
                                                    <td style="width: 80px; padding: 3px;">
                                                        <input type="number" name="fmr_allowance" step="0.01" min="0"
                                                            value="{{ old('fmr_allowance') }}" class="inline-input" placeholder="0.00">
                                                    </td>
                                                    <td style="width: 100px; padding: 3px;">
                                                        <input type="number" name="discount_before_sales_tax" step="0.01" min="0"
                                                            value="{{ old('discount_before_sales_tax') }}" class="inline-input" placeholder="0.00">
                                                    </td>
                                                    <td style="width: 80px; padding: 3px;">
                                                        <input type="number" name="excise_duty" step="0.01" min="0"
                                                            value="{{ old('excise_duty') }}" class="inline-input" placeholder="0.00">
                                                    </td>
                                                    <td style="width: 95px; padding: 3px;">
                                                        <input type="number" name="sales_tax_value" step="0.01" min="0"
                                                            value="{{ old('sales_tax_value') }}" class="inline-input" placeholder="0.00">
                                                    </td>
                                                    <td style="width: 75px; padding: 3px;">
                                                        <input type="number" name="advance_tax" step="0.01" min="0"
                                                            value="{{ old('advance_tax') }}" class="inline-input" placeholder="0.00">
                                                    </td>
                                                    <td style="width: 100px; padding: 3px;">
                                                        <input type="number" name="total_value_with_tax" step="0.01" min="0"
                                                            value="{{ old('total_value_with_tax') }}" class="inline-input" placeholder="Auto">
                                                    </td>
                                                    <td style="width: 60px; padding: 3px; text-align: center;">
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
                                <td colspan="3" class="text-center px-2 py-1">
                                    TOTAL ({{ $totals->total_entries ?? 0 }} invoices)
                                </td>
                                <td class="text-center px-2 py-1">{{ number_format($totals->total_cartons ?? 0) }}</td>
                                <td class="amount-cell px-2 py-1 text-blue-700">{{ number_format($totals->total_invoice_value ?? 0, 2) }}</td>
                                <td class="amount-cell px-2 py-1">{{ number_format($totals->total_za ?? 0, 2) }}</td>
                                <td class="amount-cell px-2 py-1">{{ number_format($totals->total_discount ?? 0, 2) }}</td>
                                <td class="amount-cell px-2 py-1">{{ number_format($totals->total_fmr ?? 0, 2) }}</td>
                                <td class="amount-cell px-2 py-1">{{ number_format($totals->total_disc_before_st ?? 0, 2) }}</td>
                                <td class="amount-cell px-2 py-1">{{ number_format($totals->total_excise ?? 0, 2) }}</td>
                                <td class="amount-cell px-2 py-1">{{ number_format($totals->total_sales_tax ?? 0, 2) }}</td>
                                <td class="amount-cell px-2 py-1">{{ number_format($totals->total_advance_tax ?? 0, 2) }}</td>
                                <td class="amount-cell px-2 py-1">{{ number_format($totals->total_with_tax ?? 0, 2) }}</td>
                                <td class="no-print"></td>
                            </tr>
                        </tfoot>
                    </table>

                    {{-- Add Entry Toggle Button --}}
                    @can('report-audit-invoice-summary-manage')
                        <div class="mt-3 no-print">
                            <button type="button" @click="showAddRow = !showAddRow"
                                class="inline-flex items-center px-3 py-1.5 text-white text-sm rounded-md transition-colors"
                                :class="showAddRow ? 'bg-gray-500 hover:bg-gray-600' : 'bg-indigo-600 hover:bg-indigo-700'">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span x-text="showAddRow ? 'Cancel' : 'Add Invoice'"></span>
                            </button>
                        </div>
                    @endcan
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
    @can('report-audit-invoice-summary-manage')
        <div x-data="invoiceEditModal()" x-show="open" x-cloak
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
                    class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:w-full sm:max-w-3xl"
                    @click.outside="close()">

                    <div class="bg-gray-800 text-white px-6 py-3 rounded-t-xl flex justify-between items-center">
                        <h3 class="text-lg font-bold" id="edit-modal-title">Edit Invoice Entry</h3>
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

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <x-label value="Invoice Date" />
                                <input type="date" name="invoice_date" x-model="entry.invoice_date"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required>
                            </div>
                            <div>
                                <x-label value="Invoice Number" />
                                <x-input type="text" name="invoice_number" x-model="entry.invoice_number"
                                    class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-label value="Cartons" />
                                <x-input type="number" name="cartons" min="0" x-model="entry.cartons"
                                    class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Invoice Value" />
                                <x-input type="number" name="invoice_value" step="0.01" min="0"
                                    x-model="entry.invoice_value" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="0.5% On Invoices" />
                                <x-input type="number" name="za_on_invoices" step="0.01" min="0"
                                    x-model="entry.za_on_invoices" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Discount Value" />
                                <x-input type="number" name="discount_value" step="0.01" min="0"
                                    x-model="entry.discount_value" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="FMR Allowance" />
                                <x-input type="number" name="fmr_allowance" step="0.01" min="0"
                                    x-model="entry.fmr_allowance" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Disc. Before Sales Tax" />
                                <x-input type="number" name="discount_before_sales_tax" step="0.01" min="0"
                                    x-model="entry.discount_before_sales_tax" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Excise Duty" />
                                <x-input type="number" name="excise_duty" step="0.01" min="0"
                                    x-model="entry.excise_duty" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Sales Tax Value" />
                                <x-input type="number" name="sales_tax_value" step="0.01" min="0"
                                    x-model="entry.sales_tax_value" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Advance Tax" />
                                <x-input type="number" name="advance_tax" step="0.01" min="0"
                                    x-model="entry.advance_tax" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-label value="Total Value with Tax" />
                                <x-input type="number" name="total_value_with_tax" step="0.01" min="0"
                                    x-model="entry.total_value_with_tax" class="mt-1 block w-full" />
                            </div>
                            <div class="col-span-3">
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
            function invoiceSummary() {
                return {
                    showAddRow: false,
                    openEditModal(data) {
                        window.dispatchEvent(new CustomEvent('open-invoice-edit-modal', {
                            detail: data
                        }));
                    }
                }
            }

            function invoiceEditModal() {
                return {
                    open: false,
                    entry: {},
                    formAction: '',
                    init() {
                        window.addEventListener('open-invoice-edit-modal', (e) => {
                            this.entry = e.detail;
                            this.formAction = '{{ url("reports/invoice-summary") }}/' + this.entry.id;
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
