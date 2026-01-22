<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Van Stock Ledger" :createRoute="null" createLabel="" :showSearch="true"
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

    <x-filter-section :action="route('reports.van-stock-ledger.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ $selectedVehicle == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->vehicle_number }} - {{ $vehicle->model ?? 'N/A' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_product_id" value="Product" />
                <select id="filter_product_id" name="filter[product_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ $selectedProduct == $product->id ? 'selected' : '' }}>
                            {{ $product->product_name }} ({{ $product->product_code ?? 'N/A' }})
                        </option>
                    @endforeach
                </select>
            </div>

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
                <x-label for="filter_movement_type" value="Movement Type" />
                <select id="filter_movement_type" name="filter[movement_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach($movementTypes as $type)
                        <option value="{{ $type }}" {{ request('filter.movement_type') === $type ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $type)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="date_asc" {{ request('sort', 'date_asc') === 'date_asc' ? 'selected' : '' }}>Date
                        (Oldest First)</option>
                    <option value="date_desc" {{ request('sort') === 'date_desc' ? 'selected' : '' }}>Date (Newest First)
                    </option>
                    <option value="product" {{ request('sort') === 'product' ? 'selected' : '' }}>Product Name</option>
                    <option value="vehicle" {{ request('sort') === 'vehicle' ? 'selected' : '' }}>Vehicle</option>
                    <option value="-quantity" {{ request('sort') === '-quantity' ? 'selected' : '' }}>Quantity (High to
                        Low)</option>
                    <option value="quantity" {{ request('sort') === 'quantity' ? 'selected' : '' }}>Quantity (Low to High)
                    </option>
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page', 100) == 100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page') == 250 ? 'selected' : '' }}>250</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Quick Links --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4 no-print">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.van-stock-ledger.summary') }}"
                class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200 transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Current Stock Summary
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 no-print">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Movements</div>
                <div class="text-xl font-bold text-blue-700">{{ number_format($movements->total()) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Inward</div>
                <div class="text-xl font-bold text-green-700">{{ number_format($totals['total_inward'] ?? 0) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                <div class="text-sm text-gray-500">Total Outward</div>
                <div class="text-xl font-bold text-red-700">{{ number_format($totals['total_outward'] ?? 0) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Total Value</div>
                <div class="text-xl font-bold text-purple-700">{{ number_format($totals['total_value'] ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Van Stock Ledger<br>
                    @if($dateFrom && $dateTo)
                        For the period {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to
                        {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}
                    @else
                        All Movements
                    @endif
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;">Sr#</th>
                            <th style="width: 90px;">Date</th>
                            <th style="width: 100px;">Vehicle</th>
                            <th style="width: 150px;">Product</th>
                            <th style="width: 100px;">Type</th>
                            <th style="width: 80px;">Qty</th>
                            <th style="width: 90px;">Unit Cost</th>
                            <th style="width: 100px;">Total Value</th>
                            <th style="width: 120px;">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $index => $movement)
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">
                                    {{ $movements->firstItem() + $index }}
                                </td>
                                <td style="vertical-align: middle;">
                                    {{ \Carbon\Carbon::parse($movement->movement_date)->format('d-m-Y') }}
                                </td>
                                <td style="vertical-align: middle;">
                                    @if($movement->vehicle)
                                        <a href="{{ route('reports.van-stock-ledger.vehicle-ledger', $movement->vehicle) }}"
                                            class="text-indigo-600 hover:text-indigo-900 no-print" target="_blank">
                                            {{ $movement->vehicle->vehicle_number }}
                                        </a>
                                        <span class="print-only">{{ $movement->vehicle->vehicle_number }}</span>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td style="vertical-align: middle;">
                                    {{ $movement->product?->product_name ?? 'N/A' }}
                                </td>
                                <td style="vertical-align: middle;">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                                @if(in_array($movement->movement_type, ['issue', 'goods_issue']))
                                                    bg-blue-100 text-blue-800
                                                @elseif(in_array($movement->movement_type, ['sale', 'settlement_sale']))
                                                    bg-green-100 text-green-800
                                                @elseif(in_array($movement->movement_type, ['return', 'settlement_return']))
                                                    bg-yellow-100 text-yellow-800
                                                @elseif(in_array($movement->movement_type, ['shortage', 'settlement_shortage']))
                                                    bg-red-100 text-red-800
                                                @else
                                                    bg-gray-100 text-gray-800
                                                @endif">
                                        {{ ucwords(str_replace('_', ' ', $movement->movement_type)) }}
                                    </span>
                                </td>
                                <td class="text-right font-mono {{ $movement->quantity > 0 ? 'text-green-600 font-bold' : 'text-red-600 font-bold' }}"
                                    style="vertical-align: middle;">
                                    {{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity) }}
                                </td>
                                <td class="text-right font-mono" style="vertical-align: middle;">
                                    {{ number_format($movement->unit_cost, 2) }}
                                </td>
                                <td class="text-right font-mono" style="vertical-align: middle;">
                                    {{ number_format(abs($movement->quantity * $movement->unit_cost), 2) }}
                                </td>
                                <td style="vertical-align: middle;">
                                    @if($movement->reference)
                                        @php
                                            $refClass = class_basename($movement->reference_type);
                                        @endphp
                                        {{ $refClass }}:
                                        {{ $movement->reference->reference_number ?? $movement->reference->id ?? '-' }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-gray-500">No stock movements found. Try
                                    adjusting your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="5" class="text-center px-2 py-1">Page Total ({{ $movements->count() }}
                                movements)</td>
                            <td class="text-right font-mono px-2 py-1">
                                {{ number_format($movements->sum('quantity')) }}
                            </td>
                            <td class="px-2 py-1"></td>
                            <td class="text-right font-mono px-2 py-1">
                                {{ number_format($movements->sum(fn($m) => abs($m->quantity * $m->unit_cost)), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                @if ($movements->hasPages())
                    <div class="mt-4 no-print">
                        {{ $movements->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>