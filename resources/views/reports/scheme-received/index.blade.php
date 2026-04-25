<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Scheme Received" :createRoute="null" createLabel="" :showSearch="true"
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

                .print-only {
                    display: block !important;
                }
            }

            /* Select2 Styling */
            .select2-container .select2-selection--single {
                height: 38px !important;
                border-color: #d1d5db !important;
                border-radius: 0.375rem !important;
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 38px !important;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 36px !important;
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
    <x-filter-section :action="route('reports.scheme-received.index')" class="no-print">
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

            {{-- Date From --}}
            <div>
                <x-label for="date_from" value="Date From" />
                <x-input id="date_from" name="date_from" type="date" class="mt-1 block w-full"
                    :value="$dateFrom" />
            </div>

            {{-- Date To --}}
            <div>
                <x-label for="date_to" value="Date To" />
                <x-input id="date_to" name="date_to" type="date" class="mt-1 block w-full"
                    :value="$dateTo" />
            </div>

            {{-- Category --}}
            <div>
                <x-label for="category" value="Category" />
                <select id="filter_category" name="category"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Categories</option>
                    @foreach ($categoryOptions as $value => $label)
                        <option value="{{ $value }}" {{ $category === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Active Status --}}
            <div>
                <x-label for="active_status" value="Status" />
                <select id="active_status" name="active_status"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="active" {{ ($activeStatus ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ ($activeStatus ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
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

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Opening Balance</div>
                <div class="text-2xl font-bold text-blue-700">{{ number_format($openingBalance, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-emerald-500">
                <div class="text-sm text-gray-500">Period Received</div>
                <div class="text-2xl font-bold text-emerald-700">{{ number_format($totalAmount, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
                <div class="text-sm text-gray-500">Closing Balance</div>
                <div class="text-2xl font-bold text-indigo-700">{{ number_format($closingBalance, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
                <div class="text-sm text-gray-500">Total Entries</div>
                <div class="text-2xl font-bold text-gray-700">{{ $records->total() }}</div>
            </div>
        </div>

        {{-- Category Totals --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            @foreach ($categoryOptions as $catKey => $catLabel)
                <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $catKey === 'tts_received' ? 'border-sky-500' : 'border-orange-500' }}">
                    <div class="text-sm text-gray-500">{{ $catLabel }}</div>
                    <div class="text-xl font-bold {{ $catKey === 'tts_received' ? 'text-sky-700' : 'text-orange-700' }}">
                        {{ number_format($categoryTotals[$catKey] ?? 0, 2) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Main Table --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header --}}
                <div class="mb-4">
                    <p class="text-center font-extrabold mb-2">
                        Moon Traders<br>
                        Scheme Received Register<br>
                        @if ($dateFrom && $dateTo)
                            <span class="text-sm font-semibold">
                                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to
                                {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}
                            </span>
                        @endif
                        @if ($selectedSupplier)
                            <br><span class="text-sm font-semibold">{{ $selectedSupplier->supplier_name }}</span>
                        @endif
                    </p>
                    <span class="print-only text-xs text-center block mt-1">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </div>

                <div x-data="{ showAddRow: false, editId: null, editData: {} }">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th style="width: 35px;">#</th>
                                <th style="width: 90px;">Date</th>
                                <th style="width: 120px;">Supplier</th>
                                <th style="width: 110px;">Category</th>
                                <th>Description</th>
                                <th style="width: 110px;">Amount</th>
                                <th style="width: 120px;">Closing Balance</th>
                                <th style="width: 60px;">Status</th>
                                <th style="width: 70px;" class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $runningBalance = $openingBalance; @endphp

                            @if ($openingBalance != 0)
                                <tr class="bg-yellow-50">
                                    <td class="text-center" colspan="5">
                                        <strong>Opening Balance
                                            @if ($dateFrom)
                                                (Before {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }})
                                            @endif
                                        </strong>
                                    </td>
                                    <td class="amount-cell">—</td>
                                    <td class="amount-cell font-bold text-blue-700">{{ number_format($openingBalance, 2) }}</td>
                                    <td></td>
                                    <td class="no-print"></td>
                                </tr>
                            @endif

                            @forelse ($records as $index => $record)
                                @php
                                    $amount = (float) $record->amount;
                                    $runningBalance += $amount;
                                @endphp
                                <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                                    <td class="text-center" style="vertical-align: middle;">
                                        {{ $records->firstItem() + $index }}
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        {{ $record->transaction_date->format('d.m.Y') }}
                                    </td>
                                    <td class="text-center text-xs" style="vertical-align: middle;">
                                        {{ $record->supplier?->short_name ?? $record->supplier?->supplier_name ?? '—' }}
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-xs font-semibold
                                            {{ $record->category === 'tts_received' ? 'bg-sky-100 text-sky-800' : 'bg-orange-100 text-orange-800' }}">
                                            {{ $categoryOptions[$record->category] ?? $record->category }}
                                        </span>
                                    </td>
                                    <td class="text-xs" style="vertical-align: middle;">
                                        {{ $record->description ?? '—' }}
                                        @if ($record->notes)
                                            <br><span class="text-gray-400 text-xs">{{ $record->notes }}</span>
                                        @endif
                                    </td>
                                    <td class="amount-cell text-emerald-700" style="vertical-align: middle;">
                                        {{ number_format($amount, 2) }}
                                    </td>
                                    <td class="amount-cell font-bold" style="vertical-align: middle;">
                                        {{ number_format($runningBalance, 2) }}
                                    </td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        @if ($record->is_active)
                                            <span class="inline-block px-1.5 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">Active</span>
                                        @else
                                            <span class="inline-block px-1.5 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">Inactive</span>
                                        @endif
                                    </td>
                                    @canany(['scheme-received-edit', 'scheme-received-delete'])
                                        <td class="text-center no-print" style="vertical-align: middle;">
                                            <div class="flex justify-center gap-1">
                                                @can('scheme-received-edit')
                                                    <button type="button"
                                                        @click="editId = {{ $record->id }}; editData = {{ json_encode([
                                                            'id' => $record->id,
                                                            'supplier_id' => $record->supplier_id,
                                                            'category' => $record->category,
                                                            'transaction_date' => $record->transaction_date->format('Y-m-d'),
                                                            'description' => $record->description,
                                                            'amount' => (float) $record->amount,
                                                            'notes' => $record->notes,
                                                            'is_active' => $record->is_active,
                                                        ]) }}"
                                                        x-on:click="$dispatch('open-scheme-edit-modal', editData)"
                                                        class="inline-flex items-center px-1.5 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                                                        title="Edit">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                @endcan
                                                @can('scheme-received-delete')
                                                    <form action="{{ route('reports.scheme-received.destroy', $record) }}" method="POST"
                                                        onsubmit="return confirm('Delete this entry?');">
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
                                        </td>
                                    @endcanany
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-gray-500">No entries found for the selected filters.</td>
                                </tr>
                            @endforelse

                            {{-- Inline Add Row --}}
                            @can('scheme-received-create')
                                <tr x-show="showAddRow" x-cloak class="bg-indigo-50 no-print">
                                    <td colspan="9" class="p-0">
                                        <form action="{{ route('reports.scheme-received.store') }}" method="POST">
                                            @csrf
                                            <table class="w-full border-collapse">
                                                <tr>
                                                    <td style="width:35px; text-align:center; padding:4px;">
                                                        <span class="text-indigo-600 font-bold">+</span>
                                                    </td>
                                                    <td style="width:90px; padding:4px;">
                                                        <input type="date" name="transaction_date"
                                                            value="{{ old('transaction_date', now()->format('Y-m-d')) }}"
                                                            max="{{ now()->format('Y-m-d') }}"
                                                            class="inline-input" required>
                                                    </td>
                                                    <td style="width:120px; padding:4px;">
                                                        <select name="supplier_id" class="inline-select" required>
                                                            <option value="">Supplier</option>
                                                            @foreach ($suppliers as $supplier)
                                                                <option value="{{ $supplier->id }}"
                                                                    {{ old('supplier_id', $supplierId) == $supplier->id ? 'selected' : '' }}>
                                                                    {{ $supplier->short_name ?? $supplier->supplier_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td style="width:110px; padding:4px;">
                                                        <select name="category" class="inline-select" required>
                                                            <option value="">Category</option>
                                                            @foreach ($categoryOptions as $value => $label)
                                                                <option value="{{ $value }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td style="padding:4px;">
                                                        <input type="text" name="description"
                                                            value="{{ old('description') }}"
                                                            class="inline-input" placeholder="Description">
                                                    </td>
                                                    <td style="width:110px; padding:4px;">
                                                        <input type="number" name="amount" step="0.01" min="0.01"
                                                            value="{{ old('amount') }}"
                                                            class="inline-input" placeholder="Amount" required>
                                                    </td>
                                                    <td style="width:120px; padding:4px; text-align:center;">
                                                        <span class="text-xs text-gray-500">Auto</span>
                                                    </td>
                                                    <td style="width:60px; padding:4px; text-align:center;">
                                                        <label class="flex items-center justify-center gap-1 text-xs">
                                                            <input type="checkbox" name="is_active" value="1" checked
                                                                class="rounded border-gray-300 text-indigo-600"> Active
                                                        </label>
                                                    </td>
                                                    <td style="width:70px; padding:4px; text-align:center;">
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
                        <tfoot>
                            <tr class="font-bold bg-gray-100">
                                <td colspan="5" class="text-right">Period Total:</td>
                                <td class="amount-cell text-emerald-700">{{ number_format($totalAmount, 2) }}</td>
                                <td class="amount-cell">{{ number_format($closingBalance, 2) }}</td>
                                <td colspan="2" class="no-print"></td>
                            </tr>
                        </tfoot>
                    </table>

                    {{-- Add Row Toggle --}}
                    @can('scheme-received-create')
                        <div class="mt-3 no-print">
                            <button type="button" @click="showAddRow = !showAddRow"
                                class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 font-medium gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span x-text="showAddRow ? 'Cancel' : 'Add Entry'"></span>
                            </button>
                        </div>
                    @endcan
                </div>

                {{-- Pagination --}}
                @if ($records->hasPages())
                    <div class="mt-4 no-print">
                        {{ $records->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    @can('scheme-received-edit')
        <div x-data="{ open: false, record: {} }"
            x-on:open-scheme-edit-modal.window="open = true; record = $event.detail"
            x-show="open" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 no-print">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4" @click.outside="open = false">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Scheme Received Entry</h3>
                </div>
                <form :action="`{{ route('reports.scheme-received.index') }}/../` + record.id" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="px-6 py-4 grid grid-cols-2 gap-4">
                        <div>
                            <x-label for="edit_transaction_date" value="Date" />
                            <x-input id="edit_transaction_date" name="transaction_date" type="date"
                                class="mt-1 block w-full" x-bind:value="record.transaction_date" required />
                        </div>
                        <div>
                            <x-label for="edit_supplier_id" value="Supplier" />
                            <select id="edit_supplier_id" name="supplier_id"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                                x-bind:value="record.supplier_id" required>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}"
                                        x-bind:selected="record.supplier_id == {{ $supplier->id }}">
                                        {{ $supplier->short_name ?? $supplier->supplier_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-label for="edit_category" value="Category" />
                            <select id="edit_category" name="category"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                                required>
                                @foreach ($categoryOptions as $value => $label)
                                    <option value="{{ $value }}"
                                        x-bind:selected="record.category === '{{ $value }}'">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-label for="edit_amount" value="Amount" />
                            <x-input id="edit_amount" name="amount" type="number" step="0.01" min="0.01"
                                class="mt-1 block w-full" x-bind:value="record.amount" required />
                        </div>
                        <div class="col-span-2">
                            <x-label for="edit_description" value="Description" />
                            <x-input id="edit_description" name="description" type="text"
                                class="mt-1 block w-full" x-bind:value="record.description" />
                        </div>
                        <div class="col-span-2">
                            <x-label for="edit_notes" value="Notes" />
                            <x-input id="edit_notes" name="notes" type="text"
                                class="mt-1 block w-full" x-bind:value="record.notes" />
                        </div>
                        <div class="col-span-2 flex items-center gap-2">
                            <input type="checkbox" id="edit_is_active" name="is_active" value="1"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                x-bind:checked="record.is_active">
                            <label for="edit_is_active" class="text-sm text-gray-700">Active</label>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t flex justify-end gap-3">
                        <button type="button" @click="open = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

</x-app-layout>
