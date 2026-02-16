<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Stock Adjustment Details
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold">{{ $stockAdjustment->adjustment_number }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Status: {{ ucfirst($stockAdjustment->status) }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm font-semibold">Date:</p>
                        <p>{{ $stockAdjustment->adjustment_date->format('Y-m-d') }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Type:</p>
                        <p>{{ ucfirst(str_replace('_', ' ', $stockAdjustment->adjustment_type)) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Warehouse:</p>
                        <p>{{ $stockAdjustment->warehouse->warehouse_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Reason:</p>
                        <p>{{ $stockAdjustment->reason }}</p>
                    </div>
                </div>

                <h4 class="text-md font-semibold mb-4">Items</h4>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Product</th>
                            <th class="px-4 py-2 text-right">System Qty</th>
                            <th class="px-4 py-2 text-right">Actual Qty</th>
                            <th class="px-4 py-2 text-right">Adjustment</th>
                            <th class="px-4 py-2 text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stockAdjustment->items as $item)
                            <tr>
                                <td class="px-4 py-2">{{ $item->product->product_name }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($item->system_quantity, 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($item->actual_quantity, 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($item->adjustment_quantity, 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($item->adjustment_value, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($stockAdjustment->isDraft())
                    <div class="mt-6">
                        <form method="POST" action="{{ route('stock-adjustments.post', $stockAdjustment) }}">
                            @csrf
                            <x-label for="password" value="Password Confirmation" />
                            <x-input id="password" type="password" name="password" required />
                            <x-button class="mt-2">Post Adjustment</x-button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>