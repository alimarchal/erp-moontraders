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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-500">Vehicles with Stock</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $stocks->count() }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-500">Total Qty on Hand</div>
                <div class="mt-1 text-2xl font-semibold text-blue-600">{{ number_format($totals['total_quantity'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-500">Total Value (Cost)</div>
                <div class="mt-1 text-xl font-semibold text-gray-600">PKR {{ number_format($totals['total_value_cost'], 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm font-medium text-gray-500">Total Value (Selling)</div>
                <div class="mt-1 text-2xl font-semibold text-green-600">PKR {{ number_format($totals['total_value_selling'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="py-2">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @forelse($stocks as $vehicleId => $vehicleStocks)
            @php
            $vehicle = $vehicleStocks->first()->vehicle;
            $vehicleSellingValue = $vehicleStocks->sum(function($stock) {
                return $stock->quantity_on_hand * $stock->calculated_selling_price;
            });
            @endphp
            
            <div class="mb-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">
                            {{ $vehicle->vehicle_number }} - {{ $vehicle->make_model ?? '' }}
                        </h3>
                        <p class="text-sm text-gray-600">Driver: {{ $vehicle->driver_name ?? 'N/A' }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle Stock Value (Selling)</span>
                        <div class="text-xl font-bold text-green-600">PKR {{ number_format($vehicleSellingValue, 2) }}</div>
                    </div>
                </div>

                <div class="p-0">
                    <x-data-table :items="$vehicleStocks" :headers="[
                        ['label' => 'Code'],
                        ['label' => 'Product'],
                        ['label' => 'Batch'],
                        ['label' => 'Qty', 'align' => 'text-right'],
                        ['label' => 'Unit Cost', 'align' => 'text-right'],
                        ['label' => 'Selling Price', 'align' => 'text-right'],
                        ['label' => 'Total (Selling)', 'align' => 'text-right'],
                        ['label' => 'Expiry', 'align' => 'text-center'],
                    ]" emptyMessage="No stock found for this vehicle.">
                        @foreach($vehicleStocks as $stock)
                        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
                            <td class="py-2 px-4 text-gray-600">
                                {{ $stock->product->product_code }}
                            </td>
                            <td class="py-2 px-4 font-medium text-gray-900">
                                {{ $stock->product->product_name }}
                            </td>
                            <td class="py-2 px-4">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                                    {{ $stock->stockBatch->batch_code ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="py-2 px-4 text-right font-bold text-gray-900">
                                {{ number_format($stock->quantity_on_hand, 2) }}
                            </td>
                            <td class="py-2 px-4 text-right text-gray-500">
                                {{ number_format($stock->calculated_unit_cost, 2) }}
                            </td>
                            <td class="py-2 px-4 text-right text-indigo-600 font-medium">
                                {{ number_format($stock->calculated_selling_price, 2) }}
                            </td>
                            <td class="py-2 px-4 text-right font-bold text-green-600">
                                {{ number_format($stock->quantity_on_hand * $stock->calculated_selling_price, 2) }}
                            </td>
                            <td class="py-2 px-4 text-center">
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
                    </x-data-table>
                </div>
            </div>
            @empty
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-12 text-center">
                <div class="text-gray-400 text-5xl mb-4">🚚</div>
                <h3 class="text-lg font-medium text-gray-900">No Van Stock Found</h3>
                <p class="text-gray-500">There is currently no batch-level stock recorded in any vans matching your filters.</p>
            </div>
            @endforelse
        </div>
    </div>
</x-app-layout>