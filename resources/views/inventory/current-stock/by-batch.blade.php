<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Stock by Batch - {{ $product->product_code }}
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            <a href="{{ route('inventory.current-stock.index') }}"
                class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Product</h3>
                            <p class="text-lg font-bold text-gray-900">{{ $product->product_code }}
                            </p>
                            <p class="text-sm text-gray-600">{{ $product->product_name }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Warehouse</h3>
                            <p class="text-lg text-gray-900">{{ $warehouse->warehouse_name }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Total Stock
                            </h3>
                            <p class="text-lg font-bold text-gray-900">
                                {{ number_format($currentStock->quantity_on_hand ?? 0, 2) }}
                            </p>
                        </div>
                    </div>

                    @if($currentStock)
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 uppercase">Qty Available
                            </h4>
                            <p class="text-base font-semibold text-gray-900">
                                {{ number_format($currentStock->quantity_available, 2) }}
                            </p>
                        </div>
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 uppercase">Avg Cost</h4>
                            <p class="text-base text-gray-900">
                                ₨ {{ number_format($currentStock->average_cost, 2) }}
                            </p>
                        </div>
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 uppercase">Total Value
                            </h4>
                            <p class="text-base font-semibold text-gray-900">
                                ₨ {{ number_format($currentStock->total_value, 2) }}
                            </p>
                        </div>
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 uppercase">Batches</h4>
                            <p class="text-base text-gray-900">
                                {{ $currentStock->total_batches }}
                                @if($currentStock->promotional_batches > 0)
                                <span class="text-xs text-orange-600">({{ $currentStock->promotional_batches }}
                                    promotional)</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    @endif

                    <hr class="my-6 border-gray-200">

                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Stock Batches</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Batch
                                        Code
                                    </th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                                        Receipt
                                        Date</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                        Quantity
                                    </th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit
                                        Cost
                                    </th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Selling
                                        Price
                                    </th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total
                                        Value
                                    </th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                                        Priority
                                    </th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Expiry
                                    </th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($batches as $batch)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm">
                                        <div class="font-semibold text-gray-900">
                                            {{ $batch->stockBatch->batch_code }}
                                        </div>
                                        @if($batch->is_promotional)
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 mt-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-700">
                                            Promotional
                                        </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center text-sm text-gray-700">
                                        {{ \Carbon\Carbon::parse($batch->stockBatch->receipt_date)->format('d M Y') }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm font-semibold text-gray-900">
                                        {{ number_format($batch->quantity_on_hand, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm text-gray-700">
                                        ₨ {{ number_format($batch->unit_cost, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm text-gray-700">
                                        @if($batch->stockBatch->selling_price)
                                        ₨ {{ number_format($batch->stockBatch->selling_price, 2) }}
                                        @else
                                        <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm font-semibold text-gray-900">
                                        ₨ {{ number_format($batch->total_value, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-center text-sm">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            {{ $batch->priority_order < 50 ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $batch->priority_order }}
                                        </span>
                                        @if($batch->must_sell_before)
                                        <div class="text-xs text-red-600 mt-1">
                                            Sell by: {{ \Carbon\Carbon::parse($batch->must_sell_before)->format('d M Y')
                                            }}
                                        </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center text-sm">
                                        @if($batch->expiry_date)
                                        <span class="text-gray-700">
                                            {{ \Carbon\Carbon::parse($batch->expiry_date)->format('d M Y') }}
                                        </span>
                                        @else
                                        <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center text-sm">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            {{ $batch->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst($batch->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @if($batch->is_promotional && $batch->promotional_price)
                                <tr class="bg-orange-50/10">
                                    <td colspan="9" class="px-3 py-2 text-sm">
                                        <div class="flex items-center justify-between">
                                            <span class="text-orange-700 font-medium">
                                                Promotional Price: ₨ {{ number_format($batch->promotional_price, 2) }}
                                            </span>
                                            @if($batch->stockBatch->promotional_discount_percent)
                                            <span class="text-orange-600">
                                                {{ number_format($batch->stockBatch->promotional_discount_percent, 2)
                                                }}%
                                                discount
                                            </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endif
                                @empty
                                <tr>
                                    <td colspan="9" class="px-3 py-8 text-center text-gray-500">
                                        No stock batches found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>