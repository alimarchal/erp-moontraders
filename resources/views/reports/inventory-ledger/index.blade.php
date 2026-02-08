<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Inventory Ledger Report" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
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
                    font-size: 11px !important;
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
        </style>
    @endpush

    <x-filter-section :action="route('reports.inventory-ledger.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
            <div>
                <x-label for="filter_start_date" value="Start Date" />
                <x-input id="filter_start_date" name="filter[start_date]" type="date" class="mt-1 block w-full"
                    :value="$filters['start_date'] ?? now()->startOfMonth()->toDateString()" />
            </div>

            <div>
                <x-label for="filter_end_date" value="End Date" />
                <x-input id="filter_end_date" name="filter[end_date]" type="date" class="mt-1 block w-full"
                    :value="$filters['end_date'] ?? now()->toDateString()" />
            </div>

            <div>
                <x-label for="filter_product_id" value="Product" />
                <select id="filter_product_id" name="filter[product_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ ($filters['product_id'] ?? '') == $product->id ? 'selected' : '' }}>
                            {{ $product->product_code }} - {{ $product->product_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ ($filters['warehouse_id'] ?? '') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->warehouse_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ ($filters['vehicle_id'] ?? '') == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->registration_number }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_transaction_type" value="Transaction Type" />
                <select id="filter_transaction_type" name="filter[transaction_type]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach($transactionTypes as $key => $label)
                        <option value="{{ $key }}" {{ ($filters['transaction_type'] ?? '') == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    @if(!empty($error))
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
            <strong>Error:</strong> {{ $error }}
        </div>
    </div>
    @endif

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 mt-4 no-print">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase">Opening Balance</p>
                <p class="text-xl font-bold text-gray-900">{{ number_format($openingBalance, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <p class="text-xs text-gray-500 uppercase">Total Debit (In)</p>
                <p class="text-xl font-bold text-green-600">{{ number_format($totalDebits, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                <p class="text-xs text-gray-500 uppercase">Total Credit (Out)</p>
                <p class="text-xl font-bold text-red-600">{{ number_format($totalCredits, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <p class="text-xs text-gray-500 uppercase">Closing Balance</p>
                <p class="text-xl font-bold text-gray-900">{{ number_format($closingBalance, 2) }}</p>
            </div>
        </div>

        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Inventory Ledger Report<br>
                    For the period {{ \Carbon\Carbon::parse($filters['start_date'] ?? now()->startOfMonth())->format('d-M-Y') }} to
                    {{ \Carbon\Carbon::parse($filters['end_date'] ?? now())->format('d-M-Y') }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="text-center border font-bold px-2">Date</th>
                            <th class="text-left border font-bold px-2">Type</th>
                            <th class="text-left border font-bold px-2">Product</th>
                            <th class="text-left border font-bold px-2">Batch</th>
                            <th class="text-left border font-bold px-2">Location</th>
                            <th class="text-left border font-bold px-2">Reference</th>
                            <th class="text-right border font-bold px-2">Debit (In)</th>
                            <th class="text-right border font-bold px-2">Credit (Out)</th>
                            <th class="text-right border font-bold px-2">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr class="bg-blue-50">
                            <td class="text-center border font-medium">{{ \Carbon\Carbon::parse($filters['start_date'] ?? now()->startOfMonth())->format('d M Y') }}</td>
                            <td class="border text-gray-500" colspan="5">Opening Balance (Brought Forward)</td>
                            <td class="text-right border text-gray-400">-</td>
                            <td class="text-right border text-gray-400">-</td>
                            <td class="text-right border font-bold">{{ number_format($openingBalance, 2) }}</td>
                        </tr>

                        @php $runningBalance = $openingBalance; @endphp
                        @forelse($entries as $entry)
                            @php
                                $runningBalance += (float)$entry->debit_qty - (float)$entry->credit_qty;
                            @endphp
                            <tr>
                                <td class="text-center border">{{ $entry->date->format('d M Y') }}</td>
                                <td class="border">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        @if($entry->transaction_type === 'purchase') bg-green-100 text-green-800
                                        @elseif($entry->transaction_type === 'sale') bg-blue-100 text-blue-800
                                        @elseif($entry->transaction_type === 'return') bg-yellow-100 text-yellow-800
                                        @elseif($entry->transaction_type === 'shortage') bg-red-100 text-red-800
                                        @elseif(str_contains($entry->transaction_type, 'transfer')) bg-purple-100 text-purple-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ $transactionTypes[$entry->transaction_type] ?? $entry->transaction_type }}
                                    </span>
                                </td>
                                <td class="border">
                                    {{ $entry->product?->product_code ?? '-' }}
                                    <span class="text-xs text-gray-500 block">{{ $entry->product?->product_name ?? '' }}</span>
                                </td>
                                <td class="border text-xs">
                                    {{ $entry->stockBatch?->batch_code ?? '-' }}
                                </td>
                                <td class="border text-xs">
                                    @if($entry->warehouse_id)
                                        🏭 {{ $entry->warehouse?->warehouse_name ?? 'Warehouse' }}
                                    @elseif($entry->vehicle_id)
                                        🚗 {{ $entry->vehicle?->registration_number ?? 'Van' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="border text-xs">
                                    @if($entry->goods_receipt_note_id)
                                        GRN #{{ $entry->goodsReceiptNote?->grn_number ?? $entry->goods_receipt_note_id }}
                                    @elseif($entry->goods_issue_id)
                                        GI #{{ $entry->goodsIssue?->issue_number ?? $entry->goods_issue_id }}
                                    @elseif($entry->sales_settlement_id)
                                        SS #{{ $entry->salesSettlement?->settlement_number ?? $entry->sales_settlement_id }}
                                    @else
                                        {{ $entry->notes ?? '-' }}
                                    @endif
                                </td>
                                <td class="text-right border {{ (float)$entry->debit_qty > 0 ? 'text-green-600 font-medium' : 'text-gray-400' }}">
                                    {{ (float)$entry->debit_qty > 0 ? number_format($entry->debit_qty, 2) : '-' }}
                                </td>
                                <td class="text-right border {{ (float)$entry->credit_qty > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                                    {{ (float)$entry->credit_qty > 0 ? number_format($entry->credit_qty, 2) : '-' }}
                                </td>
                                <td class="text-right border font-medium">
                                    {{ number_format($runningBalance, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500 border">
                                    No ledger entries found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <!-- Closing Balance Row -->
                    @if($entries->isNotEmpty())
                    <tfoot class="bg-purple-50 font-bold">
                        <tr>
                            <td class="text-center border">{{ \Carbon\Carbon::parse($filters['end_date'] ?? now())->format('d M Y') }}</td>
                            <td class="border text-gray-500" colspan="5">Closing Balance (Carried Forward)</td>
                            <td class="text-right border text-green-600">{{ number_format($totalDebits, 2) }}</td>
                            <td class="text-right border text-red-600">{{ number_format($totalCredits, 2) }}</td>
                            <td class="text-right border">{{ number_format($closingBalance, 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <!-- Formula Note -->
        <div class="text-center text-xs text-gray-500 no-print">
            <strong>Balance Formula:</strong> Opening Balance + Total Debit (In) - Total Credit (Out) = Closing Balance
            <br>
            {{ number_format($openingBalance, 2) }} + {{ number_format($totalDebits, 2) }} - {{ number_format($totalCredits, 2) }} = {{ number_format($closingBalance, 2) }}
        </div>
    </div>
</x-app-layout>
