<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Van Stock Report
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <!-- Filters -->
                <form method="GET" class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Vehicle</label>
                        <select name="vehicle_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">All Vehicles</option>
                            @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" {{ $vehicleId==$vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->vehicle_number }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Filter
                        </button>
                    </div>
                </form>

                <!-- Results -->
                @if($groupedStock->count() > 0)
                @foreach($groupedStock as $vehicleId => $stocks)
                @php
                $firstStock = $stocks->first();
                $totalValue = $stocks->sum('total_value');
                @endphp
                <div class="mb-8">
                    <h3 class="text-lg font-bold mb-4 bg-gray-100 p-3 rounded">
                        Vehicle: {{ $firstStock->vehicle_number }}
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product
                                        Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product
                                        Name</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Opening
                                        Balance</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty on
                                        Hand</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg
                                        Cost</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total
                                        Value</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($stocks as $stock)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $stock->product_code }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $stock->product_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                        number_format($stock->opening_balance, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                        number_format($stock->quantity_on_hand, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                        number_format($stock->average_cost, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                        number_format($stock->total_value, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100 font-bold">
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-sm text-right">Vehicle Total:</td>
                                    <td class="px-6 py-4 text-sm text-right">{{ number_format($totalValue, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @endforeach

                <!-- Grand Total -->
                @php
                $grandTotal = $groupedStock->flatten(1)->sum('total_value');
                @endphp
                <div class="mt-6 p-4 bg-blue-50 rounded">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold">Grand Total:</span>
                        <span class="text-xl font-bold text-blue-600">{{ number_format($grandTotal, 2) }}</span>
                    </div>
                </div>
                @else
                <div class="text-center py-8 text-gray-500">
                    No stock found for the selected criteria
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>