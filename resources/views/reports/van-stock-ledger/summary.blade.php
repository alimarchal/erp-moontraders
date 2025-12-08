<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Van Stock Summary" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.van-stock-ledger.index" />
    </x-slot>

    <x-filter-section :action="route('reports.van-stock-ledger.summary')">
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
                <div class="mt-1 text-2xl font-semibold text-blue-600">{{ number_format($totals['total_quantity']) }}
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
            $vehicleTotal = $vehicleStocks->sum(function($stock) {
            return $stock->quantity_on_hand * $stock->average_cost;
            });
            @endphp
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                <a href="{{ route('reports.van-stock-ledger.vehicle-ledger', $vehicle) }}"
                                    class="text-indigo-600 hover:text-indigo-900" target="_blank">
                                    {{ $vehicle->vehicle_number }}
                                </a>
                            </h3>
                            <p class="text-sm text-gray-500">
                                {{ $vehicle->model ?? '' }} {{ $vehicle->make ?? '' }}
                                @if($vehicle->driver)
                                | Driver: {{ $vehicle->driver->name ?? '-' }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-semibold text-gray-900">
                                PKR {{ number_format($vehicleTotal, 2) }}
                            </div>
                            <div class="text-sm text-gray-500">Total Stock Value</div>
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
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Opening
                                        Bal</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty on
                                        Hand</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Avg
                                        Cost</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total
                                        Value</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Last
                                        Updated</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($vehicleStocks as $stock)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        {{ $stock->product->product_name }}
                                        @if($stock->product->product_code)
                                        <span class="text-xs text-gray-500">({{ $stock->product->product_code }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600 text-right">
                                        {{ number_format($stock->opening_balance) }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-sm font-medium text-right {{ $stock->quantity_on_hand > 0 ? 'text-green-600' : 'text-gray-600' }}">
                                        {{ number_format($stock->quantity_on_hand) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 text-right">
                                        PKR {{ number_format($stock->average_cost, 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900 text-right">
                                        PKR {{ number_format($stock->quantity_on_hand * $stock->average_cost, 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500 text-center">
                                        {{ $stock->last_updated ? \Carbon\Carbon::parse($stock->last_updated)->format('M
                                        d, Y H:i') : '-' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-900">Vehicle Totals
                                    </th>
                                    <th class="px-4 py-2 text-right text-sm font-medium text-gray-900">
                                        {{ number_format($vehicleStocks->sum('opening_balance')) }}
                                    </th>
                                    <th class="px-4 py-2 text-right text-sm font-medium text-green-600">
                                        {{ number_format($vehicleStocks->sum('quantity_on_hand')) }}
                                    </th>
                                    <th class="px-4 py-2"></th>
                                    <th class="px-4 py-2 text-right text-sm font-medium text-gray-900">
                                        PKR {{ number_format($vehicleTotal, 2) }}
                                    </th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @empty
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8 text-center text-gray-500">
                    <div class="flex flex-col items-center">
                        <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p>No van stock balances found</p>
                        <p class="text-xs mt-1">Issue goods to vehicles to see stock here</p>
                    </div>
                </div>
            </div>
            @endforelse
        </div>
    </div>
</x-app-layout>