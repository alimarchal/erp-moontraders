<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            @if($product)
                Batch Transfer &mdash; {{ $product->product_name }}
            @else
                Batch Transfer
            @endif
        </h2>
        <div class="flex justify-center items-center float-right gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                Super Admin Only
            </span>
            @if($product && $warehouse)
                <a href="{{ route('inventory.current-stock.by-batch', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id]) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
            @else
                <a href="{{ route('inventory.current-stock.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />

                    @if(! $product)
                        {{-- STEP 1: Supplier + Product + Warehouse picker --}}
                        <div class="mb-4">
                            <h3 class="text-base font-semibold text-gray-800">Select Source Product &amp; Warehouse</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Choose the supplier, product and warehouse whose batch you want to transfer.</p>
                        </div>

                        <form method="GET" action="{{ route('inventory.current-stock.batch-transfer') }}">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <div>
                                    <x-label for="supplier_id" value="Supplier" />
                                    <select id="supplier_id" name="supplier_id"
                                        class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                        <option value="">All Suppliers</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                                {{ $supplier->supplier_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <x-label for="warehouse_id" value="Warehouse *" />
                                    <select id="warehouse_id" name="warehouse_id" required
                                        class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                        <option value="">Select Warehouse</option>
                                        @foreach($warehouses as $w)
                                            <option value="{{ $w->id }}" {{ request('warehouse_id') == $w->id ? 'selected' : '' }}>
                                                {{ $w->warehouse_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <x-label for="product_id" value="Product *" />
                                    <select id="product_id" name="product_id" required
                                        class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                        <option value="">Select Product</option>
                                        @foreach($allProducts as $p)
                                            <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>
                                                {{ $p->product_code }} &mdash; {{ $p->product_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                    class="inline-flex items-center px-6 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 transition">
                                    Continue &rarr;
                                </button>
                            </div>
                        </form>

                    @else
                        {{-- STEP 2: Transfer Form --}}
                        <div class="grid grid-cols-2 gap-4 bg-gray-50 border border-gray-200 rounded-md p-4 mb-6 text-sm">
                            <div>
                                <p class="text-gray-500 text-xs">Source Product</p>
                                <p class="font-semibold text-gray-800">{{ $product->product_code }} &mdash; {{ $product->product_name }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs">Warehouse</p>
                                <p class="font-semibold text-gray-800">{{ $warehouse->warehouse_name }}</p>
                            </div>
                        </div>

                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-sm text-purple-900 mb-6">
                            <p class="font-semibold mb-1">What does Batch Transfer do?</p>
                            <ul class="list-disc list-inside space-y-1 text-purple-800">
                                <li><strong>Full transfer</strong> &mdash; moves entire remaining quantity to another product. Updates stock_batches, stock_valuation_layers, current_stock_by_batch, current_stock, stock_movements, and draft GI items automatically.</li>
                                <li><strong>Partial transfer</strong> &mdash; moves a specific quantity. A new cloned batch is created for the target product. Draft GI items are NOT auto-updated.</li>
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

                            <div x-data="batchTransfer({{ json_encode($batchesData) }})">
                                <form action="{{ route('inventory.current-stock.batch-transfer.store') }}" method="POST" id="batchTransferForm">
                                    @csrf
                                    <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                        <div>
                                            <x-label for="stock_batch_id" value="1. Select Batch to Transfer *" />
                                            <select id="stock_batch_id" name="stock_batch_id"
                                                x-model="selectedBatchId" @change="onBatchChange()"
                                                class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full @error('stock_batch_id') border-red-500 @enderror">
                                                <option value="">Select Batch</option>
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

                                        <div>
                                            <x-label for="target_product_id" value="2. Target Product *" />
                                            <select id="target_product_id" name="target_product_id"
                                                class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full @error('target_product_id') border-red-500 @enderror">
                                                <option value="">Select Target Product</option>
                                                @foreach($products as $p)
                                                    <option value="{{ $p->id }}"
                                                        {{ old('target_product_id') == $p->id ? 'selected' : '' }}
                                                        {{ $p->id === $product->id ? 'disabled' : '' }}>
                                                        {{ $p->product_code }} &mdash; {{ $p->product_name }}
                                                        {{ $p->id === $product->id ? '(current)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('target_product_id')
                                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <template x-if="selectedBatch">
                                        <div class="grid grid-cols-3 gap-4 bg-gray-50 border border-gray-200 rounded-md p-4 mb-6 text-sm">
                                            <div>
                                                <p class="text-gray-500 text-xs">Batch Code</p>
                                                <p class="font-semibold font-mono text-gray-800" x-text="selectedBatch.batchCode"></p>
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

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                        <div>
                                            <x-label for="quantity" value="3. Quantity to Transfer *" />
                                            <div class="flex gap-2 mt-1">
                                                <x-input id="quantity" name="quantity" type="number" step="0.001" min="0.001"
                                                    x-model="quantity"
                                                    :value="old('quantity')"
                                                    placeholder="Enter quantity"
                                                    class="block w-full @error('quantity') border-red-500 @enderror" />
                                                <template x-if="selectedBatch">
                                                    <button type="button" @click="setFullTransfer()"
                                                        class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 whitespace-nowrap">
                                                        Full Qty (<span x-text="selectedBatch.qty"></span>)
                                                    </button>
                                                </template>
                                            </div>
                                            @error('quantity')
                                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                            <template x-if="selectedBatch && quantity > 0">
                                                <p class="text-xs mt-1.5 font-semibold"
                                                    :class="isFullTransfer ? 'text-blue-700' : 'text-amber-700'">
                                                    <span x-show="isFullTransfer">&#x2713; Full Transfer &mdash; batch reassigned in-place. Draft GI items will be auto-updated.</span>
                                                    <span x-show="!isFullTransfer">&#x26A0; Partial Transfer &mdash; new cloned batch created for target product.</span>
                                                </p>
                                            </template>
                                        </div>

                                        <div>
                                            <x-label for="reason" value="4. Reason for Transfer *" />
                                            <textarea id="reason" name="reason" rows="3"
                                                placeholder="e.g. Wrong SKU received — batch should have been booked under CP variant"
                                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full text-sm @error('reason') border-red-500 @enderror">{{ old('reason') }}</textarea>
                                            @error('reason')
                                                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3 bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                                        <input type="checkbox" id="confirm_transfer" x-model="confirmed"
                                            class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                        <label for="confirm_transfer" class="text-sm text-red-800">
                                            I understand this will permanently update stock_batches, stock_valuation_layers, current_stock_by_batch, current_stock
                                            (and draft GI items for full transfers). This action cannot be undone automatically.
                                        </label>
                                    </div>

                                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                        <a href="{{ route('inventory.current-stock.by-batch', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id]) }}"
                                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                            Cancel
                                        </a>
                                        <button type="submit"
                                            :disabled="!confirmed || !selectedBatchId || quantity <= 0"
                                            :class="(!confirmed || !selectedBatchId || quantity <= 0) ? 'bg-purple-300 cursor-not-allowed' : 'bg-purple-600 hover:bg-purple-700'"
                                            class="inline-flex items-center px-6 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition">
                                            Execute Transfer
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="mt-8">
                                <h3 class="text-base font-semibold text-gray-800 mb-3">
                                    All Batches &mdash; {{ $product->product_name }} in {{ $warehouse->warehouse_name }}
                                </h3>
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
                                        <tbody class="bg-white divide-y divide-gray-100">
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
        </div>
    </div>

    @push('scripts')
    <script>
        @if(! $product)
        var supplierWarehouseMap = @json($supplierWarehouseMap);

        function initializePicker() {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                setTimeout(initializePicker, 100);
                return;
            }
            $(document).ready(function () {
                $('#supplier_id').select2({ placeholder: 'All Suppliers', allowClear: true, width: '100%' });
                $('#warehouse_id').select2({ placeholder: 'Select Warehouse', allowClear: false, width: '100%' });
                $('#product_id').select2({ placeholder: 'Select Product', allowClear: false, width: '100%' });

                $('#supplier_id').on('change', function () {
                    var supplierId = $(this).val();
                    var $wh = $('#warehouse_id');
                    var currentVal = $wh.val();

                    $wh.find('option').each(function () {
                        var optVal = $(this).val();
                        if (!optVal) { return; }
                        if (!supplierId || (supplierWarehouseMap[supplierId] && supplierWarehouseMap[supplierId].indexOf(parseInt(optVal)) !== -1)) {
                            $(this).show().prop('disabled', false);
                        } else {
                            $(this).hide().prop('disabled', true);
                        }
                    });

                    if (currentVal && supplierId && supplierWarehouseMap[supplierId] && supplierWarehouseMap[supplierId].indexOf(parseInt(currentVal)) === -1) {
                        $wh.val('').trigger('change.select2');
                    }

                    $wh.select2({ placeholder: 'Select Warehouse', allowClear: false, width: '100%' });
                });

                @if(request('supplier_id'))
                    $('#supplier_id').trigger('change');
                @endif
            });
        }
        initializePicker();
        @endif

        @if($product && $batches->isNotEmpty())
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

                    this.$nextTick(function () {
                        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                            $('#target_product_id').select2({ placeholder: 'Select Target Product', allowClear: false, width: '100%' });
                        }
                    });
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
                    var self = this;
                    function initFormSelect2() {
                        if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                            setTimeout(initFormSelect2, 100);
                            return;
                        }
                        $('#stock_batch_id').select2({ placeholder: 'Select Batch', allowClear: false, width: '100%' });
                        $('#target_product_id').select2({ placeholder: 'Select Target Product', allowClear: false, width: '100%' });

                        $('#stock_batch_id').on('change', function () {
                            self.selectedBatchId = $(this).val();
                            self.onBatchChange();
                        });

                        @if(old('stock_batch_id'))
                            $('#stock_batch_id').val('{{ old("stock_batch_id") }}').trigger('change.select2');
                        @endif
                        @if(old('target_product_id'))
                            $('#target_product_id').val('{{ old("target_product_id") }}').trigger('change.select2');
                        @endif
                    }
                    initFormSelect2();
                },
            };
        }
        @endif
    </script>
    @endpush
</x-app-layout>
