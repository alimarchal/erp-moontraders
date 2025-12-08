<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Van Stock Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.daily-sales.van-stock')">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-label for="vehicle_id" value="Vehicle" />
                <select id="vehicle_id" name="vehicle_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" {{ $vehicleId==$vehicle->id ? 'selected' : '' }}>
                        {{ $vehicle->vehicle_number }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    @if($groupedStock->count() > 0)
    @foreach($groupedStock as $vehicleId => $stocks)
    @php
    $firstStock = $stocks->first();
    $totalValue = $stocks->sum('total_value');
    @endphp
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 mb-4">
        <div class="bg-green-50 p-3 rounded-t-lg border-l-4 border-green-600">
            <h3 class="text-lg font-bold text-green-800">Vehicle: {{ $firstStock->vehicle_number }}</h3>
        </div>
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-b-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-green-800 text-white uppercase text-sm">
                            <th class="py-2 px-2 text-left">Product Code</th>
                            <th class="py-2 px-2 text-left">Product Name</th>
                            <th class="py-2 px-2 text-right">Opening Balance</th>
                            <th class="py-2 px-2 text-right">Qty on Hand</th>
                            <th class="py-2 px-2 text-right">Avg Cost</th>
                            <th class="py-2 px-2 text-right">Total Value</th>
                        </tr>
                    </thead>
                    <tbody class="text-black font-extrabold">
                        @foreach($stocks as $stock)
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-1 px-2 font-mono">{{ $stock->product_code }}</td>
                            <td class="py-1 px-2">{{ $stock->product_name }}</td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($stock->opening_balance, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($stock->quantity_on_hand, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($stock->average_cost, 2) }}</td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($stock->total_value, 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
                            <td colspan="5" class="py-2 px-2 text-right">Vehicle Total:</td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totalValue, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach

    <!-- Grand Total -->
    @php
    $grandTotal = $groupedStock->flatten(1)->sum('total_value');
    @endphp
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-6">
        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
            <div class="flex justify-between items-center">
                <span class="text-lg font-bold text-blue-800">Grand Total:</span>
                <span class="text-xl font-bold text-blue-600 font-mono">{{ number_format($grandTotal, 2) }}</span>
            </div>
        </div>
    </div>
    @else
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <p class="text-gray-700 text-center py-8">No stock found for the selected criteria</p>
        </div>
    </div>
    @endif
</x-app-layout>