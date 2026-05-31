<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Revenue Detail Report" :createRoute="null" createLabel="" :showSearch="true"
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

    <x-filter-section :action="route('reports.revenue-detail.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

            <div>
                <x-label for="date_from" value="Date From" />
                <x-input id="date_from" name="date_from" type="date" class="mt-1 block w-full"
                    :value="$dateFrom" />
            </div>

            <div>
                <x-label for="date_to" value="Date To" />
                <x-input id="date_to" name="date_to" type="date" class="mt-1 block w-full"
                    :value="$dateTo" />
            </div>

            <div>
                <x-label for="revenue_category_id" value="Category" />
                <select id="revenue_category_id" name="revenue_category_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Categories</option>
                    @foreach ($categoryOptions as $categoryOption)
                        <option value="{{ $categoryOption->id }}" {{ (string) $revenueCategoryId === (string) $categoryOption->id ? 'selected' : '' }}>
                            {{ $categoryOption->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="posted_status" value="Posted Status" />
                <select id="posted_status" name="posted_status"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="posted" {{ ($postedStatus ?? '') === 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="unposted" {{ ($postedStatus ?? '') === 'unposted' ? 'selected' : '' }}>Unposted</option>
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ([10, 25, 50, 100, 250, 'all'] as $option)
                        <option value="{{ $option }}" {{ (string) $perPage === (string) $option ? 'selected' : '' }}>
                            {{ strtoupper((string) $option) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Opening Balance</div>
                <div class="text-2xl font-bold text-blue-700">{{ number_format($openingBalance, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
                <div class="text-sm text-gray-500">Period Revenue</div>
                <div class="text-2xl font-bold text-emerald-700">{{ number_format($totalAmount, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $closingBalance >= 0 ? 'border-emerald-500' : 'border-red-500' }}">
                <div class="text-sm text-gray-500">Closing Balance</div>
                <div class="text-2xl font-bold {{ $closingBalance >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                    {{ number_format($closingBalance, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
                <div class="text-sm text-gray-500">Total Entries</div>
                <div class="text-2xl font-bold text-gray-700">{{ $revenues->total() }}</div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <div class="mb-4">
                    <p class="text-center font-extrabold mb-2">
                        Moon Traders<br>
                        Revenue Detail Report<br>
                        @if ($dateFrom && $dateTo)
                            <span class="text-sm font-semibold">
                                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to
                                {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}
                            </span>
                        @elseif ($dateTo)
                            <span class="text-sm font-semibold">
                                As of: {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}
                            </span>
                        @endif
                        @if ($selectedSupplier)
                            <br><span class="text-sm font-semibold">{{ $selectedSupplier->supplier_name }}</span>
                        @endif
                        @if ($revenueCategoryId)
                            <br><span class="text-sm font-semibold">Category: {{ $categoryOptions->firstWhere('id', (int) $revenueCategoryId)?->name }}</span>
                        @endif
                    </p>
                    <span class="print-only print-info text-xs text-center block mt-1">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </div>

                <div x-data="revenueReport()">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th style="width: 35px;">Sr#</th>
                                <th style="width: 90px;">Txn Date</th>
                                <th style="width: 120px;">Supplier</th>
                                <th style="width: 110px;">Category</th>
                                <th style="width: 170px;">Description</th>
                                <th style="width: 110px;">Amount</th>
                                <th style="width: 120px;">Closing Balance</th>
                                <th style="width: 70px;" class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $runningBalance = $openingBalance;
                            @endphp

                            @if ($openingBalance != 0)
                                <tr class="bg-yellow-50">
                                    <td class="text-center" colspan="5">
                                        <strong>Opening Balance
                                            @if ($dateFrom)
                                                (Before {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }})
                                            @endif
                                        </strong>
                                    </td>
                                    <td class="amount-cell">-</td>
                                    <td class="amount-cell font-bold {{ $openingBalance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ number_format($openingBalance, 2) }}
                                    </td>
                                    <td class="no-print"></td>
                                </tr>
                            @endif

                            @forelse ($revenues as $index => $revenue)
                                @php
                                    $amount = (float) $revenue->amount;
                                    $runningBalance += $amount;
                                @endphp
                                <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                                    <td class="text-center" style="vertical-align: middle;">{{ $revenues->firstItem() + $index }}</td>
                                    <td class="text-center" style="vertical-align: middle;">{{ $revenue->transaction_date->format('d.m.Y') }}</td>
                                    <td class="text-center text-xs" style="vertical-align: middle;">
                                        {{ $revenue->supplier?->short_name ?? $revenue->supplier?->supplier_name ?? '-' }}
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                            {{ $revenue->revenueCategory?->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="text-xs text-center" style="vertical-align: middle;">
                                        {{ $revenue->description ?? '-' }}
                                        @if ($revenue->notes)
                                            <br><span class="text-gray-500">{{ $revenue->notes }}</span>
                                        @endif
                                    </td>
                                    <td class="amount-cell text-emerald-700" style="vertical-align: middle;">
                                        {{ number_format($amount, 2) }}
                                    </td>
                                    <td class="amount-cell font-bold" style="vertical-align: middle;">
                                        {{ number_format($runningBalance, 2) }}
                                    </td>
                                    @canany(['revenue-detail-edit', 'revenue-detail-post', 'revenue-detail-delete'])
                                        <td class="text-center no-print" style="vertical-align: middle;">
                                            @if (! $revenue->isPosted())
                                                <div class="flex justify-center gap-1">
                                                    @can('revenue-detail-post')
                                                        <button type="button" x-data
                                                            x-on:click="$dispatch('open-revenue-post-modal', { url: '{{ route('reports.revenue-detail.post', $revenue) }}' })"
                                                            class="inline-flex items-center px-1.5 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700"
                                                            title="Post">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        </button>
                                                    @endcan
                                                    @can('revenue-detail-edit')
                                                        <button type="button"
                                                            @click="openEditModal({{ json_encode([
                                                                'id' => $revenue->id,
                                                                'supplier_id' => $revenue->supplier_id,
                                                                'revenue_category_id' => $revenue->revenue_category_id,
                                                                'transaction_date' => $revenue->transaction_date->format('Y-m-d'),
                                                                'amount' => (float) $revenue->amount,
                                                                'description' => $revenue->description,
                                                                'notes' => $revenue->notes,
                                                            ]) }})"
                                                            class="inline-flex items-center px-1.5 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                                                            title="Edit">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </button>
                                                    @endcan
                                                    @can('revenue-detail-delete')
                                                        <form action="{{ route('reports.revenue-detail.destroy', $revenue) }}" method="POST"
                                                            onsubmit="return confirm('Are you sure you want to delete this revenue?');">
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
                                                    @endcan
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-400" title="Posted">
                                                    <svg class="w-4 h-4 inline text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </span>
                                            @endif
                                        </td>
                                    @endcanany
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-gray-500">No revenue entries found for the selected filters.</td>
                                </tr>
                            @endforelse

                            @can('revenue-detail-create')
                                <tr x-show="showAddRow" x-cloak class="bg-indigo-50 no-print">
                                    <td colspan="8" class="p-0">
                                        <form action="{{ route('reports.revenue-detail.store') }}" method="POST" id="addRevenueForm">
                                            @csrf
                                            <table class="w-full border-collapse">
                                                <tr>
                                                    <td style="width: 35px; text-align: center; padding: 4px;">
                                                        <span class="text-indigo-600 font-bold">+</span>
                                                    </td>
                                                    <td style="width: 90px; padding: 4px;">
                                                        <input type="date" name="transaction_date"
                                                            value="{{ old('transaction_date', now()->format('Y-m-d')) }}"
                                                            max="{{ now()->format('Y-m-d') }}"
                                                            class="inline-input" required>
                                                    </td>
                                                    <td style="width: 120px; padding: 4px;">
                                                        <select name="supplier_id" id="add_supplier_id" class="select2-inline inline-select" required>
                                                            <option value="">Supplier</option>
                                                            @foreach ($suppliers as $supplier)
                                                                <option value="{{ $supplier->id }}"
                                                                    {{ old('supplier_id', $supplierId) == $supplier->id ? 'selected' : '' }}>
                                                                    {{ $supplier->short_name ?? $supplier->supplier_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td style="width: 110px; padding: 4px;">
                                                        <select name="revenue_category_id" id="add_revenue_category_id" class="select2-inline inline-select" required>
                                                            <option value="">Category</option>
                                                            @foreach ($categoryOptions as $categoryOption)
                                                                <option value="{{ $categoryOption->id }}" {{ old('revenue_category_id') == $categoryOption->id ? 'selected' : '' }}>
                                                                    {{ $categoryOption->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td style="width: 170px; padding: 4px;">
                                                        <input type="text" name="description"
                                                            value="{{ old('description') }}" class="inline-input"
                                                            placeholder="Description">
                                                    </td>
                                                    <td style="padding: 4px;">
                                                        <input type="number" name="amount" step="0.01"
                                                            min="0" value="{{ old('amount') }}"
                                                            class="inline-input" placeholder="Amount" required>
                                                    </td>
                                                    <td style="width: 120px; padding: 4px; text-align: center;">
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

                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="5" class="text-center px-2 py-1">
                                    Period Totals ({{ $revenues->total() }} entries)
                                </td>
                                <td class="amount-cell px-2 py-1 text-emerald-700">{{ number_format($totalAmount, 2) }}</td>
                                <td class="amount-cell px-2 py-1">{{ number_format($closingBalance, 2) }}</td>
                                <td class="no-print"></td>
                            </tr>
                        </tfoot>
                    </table>

                    @can('revenue-detail-create')
                        <div class="mt-3 no-print">
                            <button type="button" @click="toggleAddRow()"
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

                @if ($selectedSupplier)
                    <div class="mt-4 flex justify-end">
                        <div class="border border-black rounded-lg overflow-hidden" style="min-width: 280px;">
                            <div class="bg-gray-800 text-white text-center py-1.5 font-bold text-sm">
                                Revenue Summary - {{ $selectedSupplier->short_name ?? $selectedSupplier->supplier_name }}
                            </div>
                            <table class="w-full text-sm">
                                @foreach ($categoryOptions as $categoryOption)
                                    <tr class="border-b border-gray-300">
                                        <td class="px-3 py-1.5 font-semibold">{{ $categoryOption->name }}:-</td>
                                        <td class="px-3 py-1.5 text-right font-mono font-bold text-emerald-700">
                                            {{ number_format($categoryTotals[$categoryOption->id] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50">
                                    <td class="px-3 py-1.5 font-semibold">Total:-</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-extrabold text-emerald-700">
                                        {{ number_format($totalAmount, 2) }}</td>
                                </tr>
                                <tr class="{{ $closingBalance >= 0 ? 'bg-green-50' : 'bg-red-50' }}">
                                    <td class="px-3 py-1.5 font-semibold">Closing Balance:-</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-extrabold {{ $closingBalance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ number_format($closingBalance, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($revenues->hasPages())
                    <div class="mt-4 no-print">
                        {{ $revenues->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @can('revenue-detail-edit')
        <div x-data="revenueEditModal()" x-show="open" x-cloak
            class="fixed inset-0 z-50 overflow-y-auto no-print" style="display: none;"
            aria-labelledby="edit-modal-title" role="dialog" aria-modal="true">
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
                        <h3 class="text-lg font-bold" id="edit-modal-title">Edit Revenue Entry</h3>
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
                                <x-label value="Supplier" />
                                <select name="supplier_id" x-model="entry.supplier_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label value="Category" />
                                <select name="revenue_category_id" x-model="entry.revenue_category_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required>
                                    @foreach ($categoryOptions as $categoryOption)
                                        <option value="{{ $categoryOption->id }}">{{ $categoryOption->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label value="Transaction Date" />
                                <input type="date" name="transaction_date" x-model="entry.transaction_date"
                                    max="{{ now()->format('Y-m-d') }}"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required>
                            </div>
                            <div>
                                <x-label value="Amount" />
                                <x-input type="number" name="amount" step="0.01" min="0"
                                    x-model="entry.amount" class="mt-1 block w-full" required />
                            </div>
                            <div class="col-span-2">
                                <x-label value="Description" />
                                <x-input type="text" name="description" x-model="entry.description"
                                    class="mt-1 block w-full" placeholder="Description" />
                            </div>
                            <div class="col-span-2">
                                <x-label value="Notes" />
                                <textarea name="notes" x-model="entry.notes" rows="2"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    placeholder="Additional remarks..."></textarea>
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

    @can('revenue-detail-post')
        <x-alpine-confirmation-modal eventName="open-revenue-post-modal" title="Post Revenue"
            confirmButtonText="Post" confirmButtonClass="bg-green-600 hover:bg-green-700"
            iconBgClass="bg-green-100" iconColorClass="text-green-600"
            iconPath="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z">
            <p class="text-sm text-gray-600">
                Are you sure you want to post this revenue? This will mark the entry as posted.
            </p>
        </x-alpine-confirmation-modal>
    @endcan

    @push('scripts')
        <script>
            function revenueReport() {
                return {
                    showAddRow: false,
                    toggleAddRow() {
                        this.showAddRow = !this.showAddRow;
                        if (this.showAddRow) {
                            this.$nextTick(() => {
                                if (typeof $ !== 'undefined' && $.fn.select2) {
                                    $('#add_supplier_id').select2({ width: '100%', dropdownAutoWidth: true, minimumResultsForSearch: 5 });
                                    $('#add_revenue_category_id').select2({ width: '100%', dropdownAutoWidth: true, minimumResultsForSearch: 5 });
                                }
                            });
                        }
                    },
                    openEditModal(data) {
                        window.dispatchEvent(new CustomEvent('open-revenue-edit-modal', {
                            detail: data
                        }));
                    }
                }
            }

            function revenueEditModal() {
                return {
                    open: false,
                    entry: {},
                    formAction: '',
                    init() {
                        window.addEventListener('open-revenue-edit-modal', (e) => {
                            this.entry = e.detail;
                            this.formAction = '{{ url("reports/revenue-detail") }}/' + this.entry.id;
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
