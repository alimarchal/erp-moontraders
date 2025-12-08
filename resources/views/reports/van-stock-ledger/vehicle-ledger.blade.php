<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Vehicle Stock Ledger - {{ $vehicle->vehicle_number }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.van-stock-ledger.index" />
    </x-slot>

    <x-filter-section :action="route('reports.van-stock-ledger.vehicle-ledger', $vehicle)">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

            <div>
                <x-label for="filter_date_from" value="Date (From)" />
                <x-input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full"
                    :value="$dateFrom" />
            </div>

            <div>
                <x-label for="filter_date_to" value="Date (To)" />
                <x-input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full"
                    :value="$dateTo" />
            </div>

            <div>
                <x-label for="per_page" value="Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach([10, 25, 50, 100, 250] as $option)
                    <option value="{{ $option }}" {{ request('per_page', 100)==$option ? 'selected' : '' }}>
                        {{ $option }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <!-- Vehicle Info Card -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <div class="text-sm font-medium text-gray-500">Vehicle</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $vehicle->vehicle_number }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">Make / Model</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $vehicle->make ?? '-' }} {{ $vehicle->model
                        ?? '' }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">Driver</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $vehicle->driver->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">Status</div>
                    <div class="mt-1">
                        <span
                            class="px-2 py-1 text-sm rounded-full {{ $vehicle->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ ucfirst($vehicle->status ?? 'Unknown') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Stock Summary -->
    @if($currentStock->count() > 0)
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
        <div class="bg-blue-50 rounded-lg shadow p-4">
            <h3 class="text-sm font-medium text-blue-800 mb-3">Current Stock Balance</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach($currentStock as $stock)
                <div class="bg-white rounded p-3">
                    <div class="text-sm text-gray-600">{{ $stock->product->product_name }}</div>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-lg font-semibold text-gray-900">{{ number_format($stock->quantity_on_hand)
                            }}</span>
                        <span class="text-sm text-gray-500">@ PKR {{ number_format($stock->average_cost, 2) }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="py-2">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <!-- Results Count -->
                    <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                        <span class="text-sm text-gray-700">
                            Showing {{ $movements->firstItem() ?? 0 }} to {{ $movements->lastItem() ?? 0 }}
                            of {{ number_format($movements->total()) }} movements
                        </span>
                    </div>

                    <!-- Ledger Table -->
                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Date</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Product</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Type</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Reference</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        In (+)</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Out (-)</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Balance</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Unit Cost</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($movements as $movement)
                                <tr
                                    class="hover:bg-gray-50 {{ $movement->quantity > 0 ? 'bg-green-50' : 'bg-red-50' }}">
                                    <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($movement->movement_date)->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">
                                        {{ $movement->product?->product_name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm whitespace-nowrap">
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
                                    <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                                        @if($movement->reference)
                                        @php
                                        $refClass = class_basename($movement->reference_type);
                                        @endphp
                                        {{ $refClass }}: {{ $movement->reference->reference_number ??
                                        $movement->reference->id ?? '-' }}
                                        @else
                                        -
                                        @endif
                                    </td>
                                    <td
                                        class="px-4 py-3 text-sm text-green-600 font-medium text-right whitespace-nowrap">
                                        {{ $movement->quantity > 0 ? number_format($movement->quantity) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-red-600 font-medium text-right whitespace-nowrap">
                                        {{ $movement->quantity < 0 ? number_format(abs($movement->quantity)) : '-' }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-sm font-semibold text-gray-900 text-right whitespace-nowrap">
                                        {{ number_format($movement->running_balance ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right whitespace-nowrap">
                                        PKR {{ number_format($movement->unit_cost, 2) }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                            </svg>
                                            <p>No stock movements found for this vehicle</p>
                                            <p class="text-xs mt-1">Try adjusting your date filters</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($movements->hasPages())
                    <div class="mt-4">
                        {{ $movements->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>