<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                @if($product)
                    Batch Transfer — {{ $product->product_name }}
                @else
                    Batch Transfer
                @endif
            </h2>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    Super Admin Only
                </span>
                @if($product && $warehouse)
                    <a href="{{ route('inventory.current-stock.by-batch', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id]) }}"
                        class="inline-flex items-center px-3 py-1.5 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                        &larr; Back
                    </a>
                @else
                    <a href="{{ route('inventory.current-stock.index') }}"
                        class="inline-flex items-center px-3 py-1.5 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                        &larr; Back
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-md px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-md px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif

            @if(! $product)
                {{-- STEP 1: Product + Warehouse picker --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Select Source Product &amp; Warehouse</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Choose the product and warehouse whose batch you want to transfer.</p>
                    </div>
                    <form method="GET" action="{{ route('inventory.current-stock.batch-transfer') }}" class="p-6 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product <span class="text-red-500">*</span></label>
                            <select name="product_id" required
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
                                <option value="">-- Select product --</option>
                                @foreach($allProducts as $p)
                                    <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>
                                        {{ $p->product_code }} -- {{ $p->product_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse <span class="text-red-500">*</span></label>
                            <select name="warehouse_id" required
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
                                <option value="">-- Select warehouse --</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ request('warehouse_id') == $w->id ? 'selected' : '' }}>
                                        {{ $w->warehouse_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                class="px-6 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 transition">
                                Continue
                            </button>
                        </div>
                    </form>
                </div>

            @else
                {{-- STEP 2: Transfer Form --}}

                <div class="bg-gray-50 border border-gray-200 rounded-md p-4 text-sm grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-500 text-xs">Source Product</p>
                        <p class="font-semibold text-gray-800">{{ $product->product_code }} -- {{ $product->product_name }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs">Warehouse</p>
                        <p class="font-semibold text-gray-800">{{ $warehouse->warehouse_name }}</p>
                    </div>
                </div>

                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-sm text-purple-900">
                    <p class="font-semibold mb-1">What does Batch Transfer do?</p>
                    <ul class="list-disc list-inside space-y-1 text-purple-800">
                        <li><strong>Full transfer</strong> -- moves entire remaining quantity to another product. Updates stock_batches, stock_valuation_layers, current_stock_by_batch, current_stock, stock_movements, and draft GI items automatically.</li>
                        <li><strong>Partial transfer</strong> -- moves a specific quantity. A new cloned batch is created for the target product. Draft GI items are NOT auto-updated.</li>
                        <li>All changes run in a single DB transaction.</li>
                    </ul>
                </div>

                @if($batches->isEmpty())
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center text-yellow-800">
                        <p class="font-semibold">No transferable batches found.</p>
                        <p class="text-sm mt-1">All batches for this product in this warehouse have zero remaining quantity.</p>
                        <a href="{{ route('inventory.current-stock.batch-transfer') }}"
                            class="inline-block mt-3 text-sm text-purple-700 underline">&larr; Choose a different product</a>
                    </div>
                @else
                    @php
                        $batchesData = [];
                        foreach ($batches as $b) {
                            $batchesData[$b->id] = [
                                'qty'       => (float) $b->quantity_on_hand,
                                'batchCode' => $b->batch_code,
                                'unitCost'  => number_format((float) $b->unit_cost, 2),
                            ];
                        }
                    @endphp

                    <div class="bg-white shadow rounded-lg" x-data="batchTransfer({{ json_encode($batchesData) }})">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Transfer Form</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Select a batch, choose target product, and enter quantity.</p>
                        </div>

                        <form action="{{ route('inventory.current-stock.batch-transfer.store') }}" method="POST" class="p-6 space-y-6">
                            @csrf
                            <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    1. Select Batch to Transfer <span class="text-red-500">*</span>
                                </label>
                                <select name="stock_batch_id" x-model="selectedBatchId" @change="onBatchChange()"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 @error('stock_batch_id') border-red-500 @enderror">
                                    <option value="">-- Select batch --</option>
                                    @foreach($batches as $batch)
                                        <option value="{{ $batch->id }}" {{ old('stock_batch_id') == $batch->id ? 'selected' : '' }}>
                                            {{ $batch->batch_code }} ({{ number_format($batch->quantity_on_hand, 0) }} units available)
                                        </option>
                                    @endforeach
                                </select>
                                @error('stock_batch_id')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <template x-if="selectedBatch">
                                <div class="bg-gray-50 border border-gray-200 rounded-md p-4 text-sm grid grid-cols-3 gap-4">
                                    <div>
                                        <p class="text-gray-500 text-xs">Batch Code</p>
                                        <p class="font-semibold text-gray-800" x-text="selectedBatch.batchCode"></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs">Unit Cost</p>
                                        <p class="font-semibold text-gray-800" x-text="'Rs.' + selectedBatch.unitCost"></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs">Available Qty</p>
                                        <p class="font-semibold text-emerald-700" x-text="selectedBatch.qty + ' units'"></p>
                                    </div>
                                </div>
                            </template>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    2. Target Product <span class="text-red-500">*</span>
                                </label>
                                <select name="target_product_id"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 @error('target_product_id') border-red-500 @enderror">
                                    <option value="">-- Select target product --</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}"
                                            {{ old('target_product_id') == $p->id ? 'selected' : '' }}
                                            {{ $p->id === $product->id ? 'disabled' : '' }}>
                                            {{ $p->product_code }} -- {{ $p->product_name }}
                                            {{ $p->id === $product->id ? '(current product)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('target_product_id')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    3. Quantity to Transfer <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-3 items-start">
                                    <div class="flex-1">
                                        <input type="number" name="quantity" step="0.001" min="0.001"
                                            x-model="quantity"
                                            value="{{ old('quantity') }}"
                                            placeholder="Enter units to transfer"
                                            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 @error('quantity') border-red-500 @enderror">
                                        @error('quantity')
                                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <template x-if="selectedBatch">
                                        <button type="button" @click="setFullTransfer()"
                                            class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 whitespace-nowrap">
                                            Use Full Qty (<span x-text="selectedBatch.qty"></span>)
                                        </button>
                                    </template>
                                </div>
                                <template x-if="selectedBatch && quantity > 0">
                                    <p class="text-xs mt-1.5"
                                        :class="isFullTransfer ? 'text-blue-700 font-semibold' : 'text-amber-700 font-semibold'">
                                        <span x-show="isFullTransfer">Full Transfer -- batch reassigned in-place. Draft GI items will be auto-updated.</span>
                                        <span x-show="!isFullTransfer">Partial Transfer -- new batch created for target product. Draft GI items NOT auto-updated.</span>
                                    </p>
                                </template>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    4. Reason for Transfer <span class="text-red-500">*</span>
                                </label>
                                <textarea name="reason" rows="3"
                                    placeholder="e.g. Wrong SKU received -- batch should have been booked under CP variant"
                                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 @error('reason') border-red-500 @enderror">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-start gap-3 bg-red-50 border border-red-200 rounded-md p-4">
                                <input type="checkbox" id="confirm_transfer" x-model="confirmed"
                                    class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <label for="confirm_transfer" class="text-sm text-red-800">
                                    I understand this will update stock_batches, stock_valuation_layers, current_stock_by_batch, current_stock
                                    (and draft GI items for full transfers). This action cannot be undone automatically.
                                </label>
                            </div>

                            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                                <a href="{{ route('inventory.current-stock.by-batch', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id]) }}"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                    Cancel
                                </a>
                                <button type="submit"
                                    :disabled="!confirmed || !selectedBatchId || quantity <= 0"
                                    :class="(!confirmed || !selectedBatchId || quantity <= 0) ? 'bg-purple-300 cursor-not-allowed' : 'bg-purple-600 hover:bg-purple-700'"
                                    class="px-6 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition">
                                    Execute Transfer
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-base font-semibold text-gray-800">All batches -- {{ $product->product_name }} in {{ $warehouse->warehouse_name }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-600">Batch Code</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-600">Qty on Hand</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-600">Unit Cost</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-600">Total Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($batches as $batch)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $batch->batch_code }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-emerald-700">{{ number_format($batch->quantity_on_hand, 0) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600">Rs.{{ number_format($batch->unit_cost, 2) }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-800">Rs.{{ number_format($batch->quantity_on_hand * $batch->unit_cost, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif

        </div>
    </div>

    @if($product && $batches->isNotEmpty())
    @push('scripts')
    <script>
        function batchTransfer(batches) {
            return {
                selectedBatchId: '{{ old("stock_batch_id", "") }}',
                selectedBatch: null,
                quantity: {{ (float) old('quantity', 0) }},
                confirmed: false,
                batches: batches,

                get isFullTransfer() {
                    if (!this.selectedBatch) { return false; }
                    return Math.abs(parseFloat(this.quantity) - this.selectedBatch.qty) < 0.001;
                },

                onBatchChange() {
                    var id = parseInt(this.selectedBatchId);
                    this.selectedBatch = this.batches[id] || null;
                    this.quantity = 0;
                },

                setFullTransfer() {
                    if (this.selectedBatch) {
                        this.quantity = this.selectedBatch.qty;
                    }
                },

                init() {
                    if (this.selectedBatchId) {
                        this.selectedBatch = this.batches[parseInt(this.selectedBatchId)] || null;
                    }
                },
            };
        }
    </script>
    @endpush
    @endif
</x-app-layout>
