<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Van Stock by Batch" :createRoute="null" createLabel="" :showSearch="true"
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

                .text-green-700,
                .text-blue-700,
                .text-orange-700,
                .text-green-600,
                .text-indigo-600 {
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

    <x-filter-section :action="route('reports.van-stock-batch.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ $selectedVehicle == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->vehicle_number }} - {{ $vehicle->make_model ?? 'N/A' }}
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
                <x-label for="filter_expiry_status" value="Expiry Status" />
                <select id="filter_expiry_status" name="filter[expiry_status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="expired" {{ request('filter.expiry_status')==='expired' ? 'selected' : '' }}>Expired</option>
                    <option value="expiring_soon" {{ request('filter.expiry_status')==='expiring_soon' ? 'selected' : '' }}>Expiring Soon (30 days)</option>
                    <option value="valid" {{ request('filter.expiry_status')==='valid' ? 'selected' : '' }}>Valid</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Vehicles with Stock</div>
                <div class="text-2xl font-bold text-purple-700">{{ $stocks->count() }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Qty on Hand</div>
                <div class="text-2xl font-bold text-blue-700">{{ number_format($totals['total_quantity'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
                <div class="text-sm text-gray-500">Total Value (Cost)</div>
                <div class="text-xl font-bold text-gray-700">PKR {{ number_format($totals['total_value_cost'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Value (Selling)</div>
                <div class="text-2xl font-bold text-green-700">PKR {{ number_format($totals['total_value_selling'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Van Stock by Batch<br>
                    Total Vehicles: {{ $stocks->count() }} | Total Qty: {{ number_format($totals['total_quantity'], 2) }} | Total Value: PKR {{ number_format($totals['total_value_selling'], 2) }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                @forelse($stocks as $vehicleId => $vehicleStocks)
                    @php
                        $vehicle = $vehicleStocks->first()->vehicle;
                        $vehicleSellingValue = $vehicleStocks->sum(function($stock) {
                            return $stock->quantity_on_hand * $stock->calculated_selling_price;
                        });
                    @endphp

                    <div class="vehicle-section mb-6">
                        <div class="bg-gray-100 px-3 py-2 rounded-t border border-gray-300 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-gray-900">{{ $vehicle->vehicle_number }}</span>
                                <span class="text-sm text-gray-600 ml-2">{{ $vehicle->make_model ?? '' }}</span>
                                <span class="text-sm text-gray-500 ml-2">| Driver: {{ $vehicle->driver_name ?? 'N/A' }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-500">Stock Value (Selling)</span>
                                <div class="font-bold text-green-700">PKR {{ number_format($vehicleSellingValue, 2) }}</div>
                            </div>
                        </div>

                        <table class="report-table">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th style="width: 40px;">Sr#</th>
                                    <th style="width: 80px;">Code</th>
                                    <th style="width: 150px;">Product</th>
                                    <th style="width: 90px;">Batch</th>
                                    <th style="width: 70px;">Qty</th>
                                    <th style="width: 80px;">Unit Cost</th>
                                    <th style="width: 90px;">Selling Price</th>
                                    <th style="width: 100px;">Total (Selling)</th>
                                    <th style="width: 90px;">Expiry</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vehicleStocks as $index => $stock)
                                    <tr>
                                        <td class="text-center" style="vertical-align: middle;">{{ $index + 1 }}</td>
                                        <td class="font-mono" style="vertical-align: middle;">{{ $stock->product->product_code ?? '-' }}</td>
                                        <td style="vertical-align: middle;">{{ $stock->product->product_name }}</td>
                                        <td style="vertical-align: middle;">
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                                                {{ $stock->stockBatch->batch_code ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="text-right font-mono font-bold" style="vertical-align: middle;">{{ number_format($stock->quantity_on_hand, 2) }}</td>
                                        <td class="text-right font-mono" style="vertical-align: middle;">{{ number_format($stock->calculated_unit_cost, 2) }}</td>
                                        <td class="text-right font-mono text-indigo-600 font-bold" style="vertical-align: middle;">{{ number_format($stock->calculated_selling_price, 2) }}</td>
                                        <td class="text-right font-mono font-bold text-green-700" style="vertical-align: middle;">{{ number_format($stock->quantity_on_hand * $stock->calculated_selling_price, 2) }}</td>
                                        <td class="text-center" style="vertical-align: middle;">
                                            @if($stock->stockBatch->expiry_date)
                                                <span @class([
                                                    'px-2 py-0.5 rounded text-xs font-medium',
                                                    'bg-red-100 text-red-700' => $stock->stockBatch->expiry_date->isPast(),
                                                    'bg-orange-100 text-orange-700' => !$stock->stockBatch->expiry_date->isPast() && $stock->stockBatch->expiry_date->diffInDays(now()) < 30,
                                                    'bg-gray-100 text-gray-600' => $stock->stockBatch->expiry_date->isFuture() && $stock->stockBatch->expiry_date->diffInDays(now()) >= 30,
                                                ])>
                                                    {{ $stock->stockBatch->expiry_date->format('d-m-Y') }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100 font-extrabold">
                                <tr>
                                    <td colspan="4" class="text-center px-2 py-1">Vehicle Total ({{ $vehicleStocks->count() }} items)</td>
                                    <td class="text-right font-mono px-2 py-1">{{ number_format($vehicleStocks->sum('quantity_on_hand'), 2) }}</td>
                                    <td class="px-2 py-1"></td>
                                    <td class="px-2 py-1"></td>
                                    <td class="text-right font-mono px-2 py-1 text-green-700">{{ number_format($vehicleSellingValue, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500">
                        <div class="text-5xl mb-4">No Van Stock Found</div>
                        <p>There is currently no batch-level stock recorded in any vans matching your filters.</p>
                    </div>
                @endforelse

                @if($stocks->count() > 0)
                    <div class="mt-4 pt-4 border-t-2 border-gray-300">
                        <table class="report-table">
                            <tfoot class="bg-gray-200 font-extrabold">
                                <tr>
                                    <td colspan="4" class="text-center px-2 py-2">Grand Total ({{ $stocks->count() }} vehicles)</td>
                                    <td class="text-right font-mono px-2 py-2 text-blue-700">{{ number_format($totals['total_quantity'], 2) }}</td>
                                    <td class="text-right font-mono px-2 py-2">{{ number_format($totals['total_value_cost'], 2) }}</td>
                                    <td class="px-2 py-2"></td>
                                    <td class="text-right font-mono px-2 py-2 text-green-700">{{ number_format($totals['total_value_selling'], 2) }}</td>
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
