<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Batch Transfer — GRN: {{ $grn->grn_number }}
            </h2>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    Super Admin Only
                </span>
                <a href="{{ route('goods-receipt-notes.show', $grn->id) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    ← Back to GRN
                </a>
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

            {{-- Info panel explaining full vs partial --}}
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-sm text-purple-900">
                <p class="font-semibold mb-1">What does Batch Transfer do?</p>
                <ul class="list-disc list-inside space-y-1 text-purple-800">
                    <li><strong>Full transfer</strong> — moves entire remaining quantity to another product. Updates <code>stock_batches</code>, <code>stock_valuation_layers</code>, <code>current_stock_by_batch</code>, <code>current_stock</code>, <code>stock_movements</code>, and <strong>draft GI items</strong> automatically.</li>
                    <li><strong>Partial transfer</strong> — moves a specific quantity. A new cloned batch is created for the target product. Draft GI items are NOT auto-updated (handle manually if needed).</li>
                    <li>All changes run in a single DB transaction — either everything succeeds or nothing changes.</li>
                </ul>
            </div>

            @if ($batches->isEmpty())
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center text-yellow-800">
                    <p class="font-semibold">No transferable batches found.</p>
                    <p class="text-sm mt-1">All batches from this GRN have zero remaining quantity, or the GRN has not been posted yet.</p>
                </div>
            @else

            <div class="bg-white shadow rounded-lg" x-data="batchTransfer()">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Transfer Form</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Select a batch, choose target product, and enter quantity.</p>
                </div>

                <form action="{{ route('goods-receipt-notes.batch-transfer.store', $grn->id) }}" method="POST" class="p-6 space-y-6">
                    @csrf

                    {{-- 1. Batch selection --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            1. Select Batch to Transfer <span class="text-red-500">*</span>
                        </label>
                        <select name="stock_batch_id" x-model="selectedBatchId" @change="onBatchChange()"
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 @error('stock_batch_id') border-red-500 @enderror">
                            <option value="">— Select batch —</option>
                            @foreach ($batches as $batch)
                                <option value="{{ $batch->id }}" {{ old('stock_batch_id') == $batch->id ? 'selected' : '' }}>
                                    {{ $batch->batch_code }} — {{ $batch->product->product_name }}
                                    ({{ number_format($batch->quantity_on_hand, 0) }} units available)
                                </option>
                            @endforeach
                        </select>
                        @error('stock_batch_id')
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Selected batch info card --}}
                    <template x-if="selectedBatch">
                        <div class="bg-gray-50 border border-gray-200 rounded-md p-4 text-sm grid grid-cols-3 gap-4">
                            <div>
                                <p class="text-gray-500 text-xs">Current Product</p>
                                <p class="font-semibold text-gray-800" x-text="selectedBatch.product"></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs">Batch Code</p>
                                <p class="font-semibold text-gray-800" x-text="selectedBatch.batchCode"></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs">Available Qty</p>
                                <p class="font-semibold text-emerald-700" x-text="selectedBatch.qty + ' units'"></p>
                            </div>
                        </div>
                    </template>

                    {{-- 2. Target product --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            2. Target Product <span class="text-red-500">*</span>
                        </label>
                        <select name="target_product_id"
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 @error('target_product_id') border-red-500 @enderror">
                            <option value="">— Select target product —</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ old('target_product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->product_code }} — {{ $product->product_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('target_product_id')
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- 3. Quantity --}}
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
                                <span x-show="isFullTransfer">✔ Full Transfer — batch reassigned in-place. Draft GI items will be auto-updated.</span>
                                <span x-show="!isFullTransfer">⚠ Partial Transfer — new batch created for target product. Draft GI items NOT auto-updated.</span>
                            </p>
                        </template>
                    </div>

                    {{-- 4. Reason --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            4. Reason for Transfer <span class="text-red-500">*</span>
                        </label>
                        <textarea name="reason" rows="3"
                                  placeholder="e.g. Wrong SKU received — batch should have been booked under CP variant"
                                  class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 @error('reason') border-red-500 @enderror">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Confirmation --}}
                    <div class="flex items-start gap-3 bg-red-50 border border-red-200 rounded-md p-4">
                        <input type="checkbox" id="confirm_transfer" x-model="confirmed"
                               class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <label for="confirm_transfer" class="text-sm text-red-800">
                            I understand this will update <strong>stock_batches, stock_valuation_layers, current_stock_by_batch, current_stock</strong>
                            (and draft GI items for full transfers). This action cannot be undone automatically.
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <a href="{{ route('goods-receipt-notes.show', $grn->id) }}"
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

            {{-- Summary table of available batches --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-800">Batches from this GRN with remaining stock</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Batch Code</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Current Product</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Qty on Hand</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Unit Cost</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Total Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($batches as $batch)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $batch->batch_code }}</td>
                                    <td class="px-4 py-3 text-gray-800">{{ $batch->product->product_name }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700">{{ number_format($batch->quantity_on_hand, 0) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">₨{{ number_format($batch->unit_cost, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-800">₨{{ number_format($batch->quantity_on_hand * $batch->unit_cost, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function batchTransfer() {
            return {
                selectedBatchId: '{{ old('stock_batch_id', '') }}',
                selectedBatch: null,
                quantity: {{ old('quantity', 0) }},
                confirmed: false,

                // Batch data keyed by id — avoids extra AJAX calls
                batches: @json(
                    $batches->mapWithKeys(fn ($b) => [
                        $b->id => [
                            'qty'       => (float) $b->quantity_on_hand,
                            'product'   => $b->product->product_name,
                            'productId' => $b->product_id,
                            'batchCode' => $b->batch_code,
                        ]
                    ])
                ),

                get isFullTransfer() {
                    if (!this.selectedBatch) return false;
                    return Math.abs(parseFloat(this.quantity) - this.selectedBatch.qty) < 0.001;
                },

                onBatchChange() {
                    const id = parseInt(this.selectedBatchId);
                    this.selectedBatch = this.batches[id] ?? null;
                    this.quantity = 0;
                },

                setFullTransfer() {
                    if (this.selectedBatch) {
                        this.quantity = this.selectedBatch.qty;
                    }
                },

                init() {
                    // Restore selected batch info on validation error (old input restored)
                    if (this.selectedBatchId) {
                        this.selectedBatch = this.batches[parseInt(this.selectedBatchId)] ?? null;
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
