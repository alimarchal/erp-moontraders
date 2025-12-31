<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Van Stock by Batch" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.van-stock-batch.index')">
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
                            {{ $product->product_name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <!-- Summary Cards -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-500">Total Vehicles with Stock</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $stocks->count() }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-500">Total Quantity on Hand</div>
                <div class="mt-1 text-2xl font-semibold text-blue-600">{{ number_format($totals['total_quantity'], 2) }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-500">Total Stock Value</div>
                <div class="mt-1 text-2xl font-semibold text-green-600">PKR {{ number_format($totals['total_value'], 2)
                    }}</div>
            </div>
        </div>
    </div>

    <div class="py-2">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @forelse($stocks as $vehicleId => $vehicleStocks)
                @php
                    $vehicle = $vehicleStocks->first()->vehicle;
                    $vehicleTotalValue = $vehicleStocks->sum(function ($stock) {
                        return $stock->quantity_on_hand * $stock->last_unit_cost;
                    });
                @endphp
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">
                                    {{ $vehicle->vehicle_number }} - {{ $vehicle->make_model ?? '' }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Driver: {{ $vehicle->driver_name ?? 'N/A' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-semibold text-gray-900">
                                    PKR {{ number_format($vehicleTotalValue, 2) }}
                                </div>
                                <div class="text-sm text-gray-500">Total Batch Value</div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Batch
                                        </th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty on
                                            Hand</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit
                                            Cost</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total
                                            Value</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expiry
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($vehicleStocks as $stock)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-900">
                                                {{ $stock->product->product_name }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-900">
                                                {{ $stock->stockBatch->batch_number ?? 'N/A' }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900 font-semibold">
                                                {{ number_format($stock->quantity_on_hand, 2) }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-500">
                                                {{ number_format($stock->last_unit_cost, 2) }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">
                                                {{ number_format($stock->quantity_on_hand * $stock->last_unit_cost, 2) }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-center text-gray-500">
                                                {{ $stock->stockBatch->expiry_date ? $stock->stockBatch->expiry_date->format('d-M-Y') : 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                    No batch-level stock found in vans matching the criteria.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>