<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Product Recall Details
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4" />
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold">{{ $productRecall->recall_number }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Status: {{ ucfirst($productRecall->status) }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm font-semibold">Recall Date:</p>
                        <p>{{ $productRecall->recall_date->format('Y-m-d') }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Supplier:</p>
                        <p>{{ $productRecall->supplier->supplier_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Warehouse:</p>
                        <p>{{ $productRecall->warehouse->warehouse_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Recall Type:</p>
                        <p>{{ ucfirst(str_replace('_', ' ', $productRecall->recall_type)) }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-sm font-semibold">Reason:</p>
                        <p>{{ $productRecall->reason }}</p>
                    </div>
                </div>

                <h4 class="text-md font-semibold mb-4">Recalled Items</h4>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mb-6">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Product</th>
                            <th class="px-4 py-2 text-left">Batch Code</th>
                            <th class="px-4 py-2 text-right">Qty Recalled</th>
                            <th class="px-4 py-2 text-right">Unit Cost</th>
                            <th class="px-4 py-2 text-right">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($productRecall->items as $item)
                            <tr>
                                <td class="px-4 py-2">{{ $item->product->product_name }}</td>
                                <td class="px-4 py-2">{{ $item->stockBatch->batch_code }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($item->quantity_recalled, 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($item->unit_cost, 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($item->total_value, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td colspan="2" class="px-4 py-2 text-right">Total:</td>
                            <td class="px-4 py-2 text-right">
                                {{ number_format($productRecall->total_quantity_recalled, 2) }}</td>
                            <td></td>
                            <td class="px-4 py-2 text-right">{{ number_format($productRecall->total_value, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                @if($productRecall->isPosted() && $productRecall->stockAdjustment)
                    <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-md mb-6">
                        <p class="text-sm"><strong>Stock Adjustment:</strong>
                            {{ $productRecall->stockAdjustment->adjustment_number }}</p>
                        @if($productRecall->stockAdjustment->journalEntry)
                            <p class="text-sm"><strong>Journal Entry:</strong>
                                {{ $productRecall->stockAdjustment->journalEntry->reference }}</p>
                        @endif
                    </div>
                @endif

                @if($productRecall->isDraft())
                    <div class="mt-6 flex gap-4">
                        <form method="POST" action="{{ route('product-recalls.post', $productRecall) }}">
                            @csrf
                            <div class="mb-4">
                                <x-label for="password" value="Password Confirmation" />
                                <x-input id="password" type="password" name="password" required />
                            </div>
                            <x-button>Post Recall</x-button>
                        </form>
                        <form method="POST" action="{{ route('product-recalls.cancel', $productRecall) }}">
                            @csrf
                            <x-button class="bg-red-600 hover:bg-red-700">Cancel Recall</x-button>
                        </form>
                    </div>
                @endif

                @if($productRecall->isPosted() && !$productRecall->claim_register_id)
                    <div class="mt-6">
                        <form method="POST" action="{{ route('product-recalls.create-claim', $productRecall) }}">
                            @csrf
                            <x-button>Create Claim Register</x-button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>