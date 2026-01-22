<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Van Stock Summary" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.van-stock-ledger.index" />
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
                .text-orange-700,
                .text-green-600,
                .text-blue-600 {
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

                .vehicle-section {
                    page-break-inside: avoid;
                    margin-bottom: 15px !important;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.van-stock-ledger.summary')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" {{ $selectedVehicle==$vehicle->id ? 'selected' : '' }}>
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
                    <option value="{{ $product->id }}" {{ $selectedProduct==$product->id ? 'selected' : '' }}>
                        {{ $product->product_name }} ({{ $product->product_code ?? 'N/A' }})
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Total Vehicles with Stock</div>
                <div class="text-2xl font-bold text-purple-700">{{ $totals['total_vehicles'] }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Quantity on Hand</div>
                <div class="text-2xl font-bold text-blue-700">{{ number_format($totals['total_quantity']) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Stock Value</div>
                <div class="text-2xl font-bold text-green-700">PKR {{ number_format($totals['total_value'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Van Stock Summary<br>
                    Total Vehicles: {{ $totals['total_vehicles'] }} | Total Qty: {{ number_format($totals['total_quantity']) }} | Total Value: PKR {{ number_format($totals['total_value'], 2) }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                @forelse($stocks as $vehicleId => $vehicleStocks)
                    @php
                        $vehicle = $vehicleStocks->first()->vehicle;
                        $vehicleTotal = $vehicleStocks->sum(function($stock) {
                            return $stock->quantity_on_hand * $stock->average_cost;
                        });
                    @endphp

                    <div class="vehicle-section mb-6">
                        <div class="bg-gray-100 px-3 py-2 rounded-t border border-gray-300 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-gray-900">
                                    <a href="{{ route('reports.van-stock-ledger.vehicle-ledger', $vehicle) }}"
                                        class="text-indigo-600 hover:text-indigo-900 no-print" target="_blank">
                                        {{ $vehicle->vehicle_number }}
                                    </a>
                                    <span class="print-only">{{ $vehicle->vehicle_number }}</span>
                                </span>
                                <span class="text-sm text-gray-600 ml-2">
                                    {{ $vehicle->model ?? '' }} {{ $vehicle->make ?? '' }}
                                    @if($vehicle->driver)
                                        | Driver: {{ $vehicle->driver->name ?? '-' }}
                                    @endif
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-green-700">PKR {{ number_format($vehicleTotal, 2) }}</span>
                            </div>
                        </div>

                        <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th style="width: 40px;">Sr#</th>
                                    <th style="width: 100px;">Code</th>
                                    <th style="width: 200px;">Product</th>
                                    <th style="width: 90px;">Opening Bal</th>
                                    <th style="width: 90px;">Qty on Hand</th>
                                    <th style="width: 100px;">Avg Cost</th>
                                    <th style="width: 120px;">Total Value</th>
                                    <th style="width: 100px;">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vehicleStocks as $index => $stock)
                                    <tr>
                                        <td class="text-center" style="vertical-align: middle;">{{ $index + 1 }}</td>
                                        <td class="font-mono" style="vertical-align: middle;">{{ $stock->product->product_code ?? '-' }}</td>
                                        <td style="vertical-align: middle;">{{ $stock->product->product_name }}</td>
                                        <td class="text-right font-mono" style="vertical-align: middle;">{{ number_format($stock->opening_balance) }}</td>
                                        <td class="text-right font-mono font-bold {{ $stock->quantity_on_hand > 0 ? 'text-green-600' : 'text-gray-600' }}" style="vertical-align: middle;">
                                            {{ number_format($stock->quantity_on_hand) }}
                                        </td>
                                        <td class="text-right font-mono" style="vertical-align: middle;">{{ number_format($stock->average_cost, 2) }}</td>
                                        <td class="text-right font-mono font-bold" style="vertical-align: middle;">{{ number_format($stock->quantity_on_hand * $stock->average_cost, 2) }}</td>
                                        <td class="text-center text-sm" style="vertical-align: middle;">
                                            {{ $stock->last_updated ? \Carbon\Carbon::parse($stock->last_updated)->format('d-m-Y') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td colspan="3" class="text-center px-2 py-1">Vehicle Total ({{ $vehicleStocks->count() }} products)</td>
                                    <td class="text-right font-mono px-2 py-1">{{ number_format($vehicleStocks->sum('opening_balance')) }}</td>
                                    <td class="text-right font-mono px-2 py-1 text-green-700">{{ number_format($vehicleStocks->sum('quantity_on_hand')) }}</td>
                                    <td class="px-2 py-1"></td>
                                    <td class="text-right font-mono px-2 py-1">{{ number_format($vehicleTotal, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 text-gray-300 mb-2 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p>No van stock balances found</p>
                        <p class="text-xs mt-1">Issue goods to vehicles to see stock here</p>
                    </div>
                @endforelse

                @if($stocks->count() > 0)
                    <div class="mt-4 pt-4 border-t-2 border-gray-300">
                        <table class="report-table">
                            <tfoot class="bg-gray-200 font-extrabold">
                                <tr>
                                    <td colspan="4" class="text-center px-2 py-2">Grand Total ({{ $totals['total_vehicles'] }} vehicles)</td>
                                    <td class="text-right font-mono px-2 py-2 text-blue-700">{{ number_format($totals['total_quantity']) }}</td>
                                    <td class="px-2 py-2"></td>
                                    <td class="text-right font-mono px-2 py-2 text-green-700">PKR {{ number_format($totals['total_value'], 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
