<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Create Product Recall
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('product-recalls.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
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
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />

                    <form method="POST" action="{{ route('product-recalls.store') }}" id="productRecallForm"
                        x-data="productRecallForm()">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <x-label for="recall_date" value="Recall Date *" />
                                <x-input id="recall_date" name="recall_date" type="date" class="mt-1 block w-full"
                                    :value="old('recall_date', date('Y-m-d'))" required />
                            </div>

                            <div>
                                <x-label for="supplier_id" value="Supplier *" />
                                <select id="supplier_id" name="supplier_id" required x-model="supplierId"
                                    @change="onSupplierChange()"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="warehouse_id" value="Warehouse *" />
                                <select id="warehouse_id" name="warehouse_id" required x-model="warehouseId"
                                    @change="onWarehouseChange()"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->warehouse_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="recall_type" value="Recall Type *" />
                                <select id="recall_type" name="recall_type" required
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Type</option>
                                    <option value="supplier_initiated" {{ old('recall_type') == 'supplier_initiated' ? 'selected' : '' }}>Supplier Initiated</option>
                                    <option value="quality_issue" {{ old('recall_type') == 'quality_issue' ? 'selected' : '' }}>Quality Issue</option>
                                    <option value="expiry" {{ old('recall_type') == 'expiry' ? 'selected' : '' }}>Expiry
                                    </option>
                                    <option value="other" {{ old('recall_type') == 'other' ? 'selected' : '' }}>Other
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mb-6">
                            <div>
                                <x-label for="reason" value="Reason for Recall *" />
                                <textarea id="reason" name="reason" rows="2" required
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('reason') }}</textarea>
                            </div>
                        </div>

                        <hr class="my-6 border-gray-200">

                        {{-- Batch Search Panel --}}
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6"
                            x-show="supplierId && warehouseId">
                            <h4 class="text-sm font-semibold text-blue-800 mb-3">Search Available Batches</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-blue-700 mb-1">Batch Code</label>
                                    <input type="text" x-model="searchFilters.batch_code"
                                        @input.debounce.500ms="searchBatches()"
                                        class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full"
                                        placeholder="Search by batch code...">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-blue-700 mb-1">Expiry From</label>
                                    <input type="date" x-model="searchFilters.expiry_from" @change="searchBatches()"
                                        class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-blue-700 mb-1">Expiry To</label>
                                    <input type="date" x-model="searchFilters.expiry_to" @change="searchBatches()"
                                        class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                </div>
                                <div class="flex items-end">
                                    <button type="button" @click="searchBatches()"
                                        class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 w-full justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        Search Batches
                                    </button>
                                </div>
                            </div>

                            {{-- Available Batches List --}}
                            <div x-show="availableBatches.length > 0" class="mt-4">
                                <p class="text-xs text-blue-600 mb-2"
                                    x-text="`${availableBatches.length} batch(es) found. Click to add:`"></p>
                                <div class="max-h-48 overflow-y-auto border border-blue-100 rounded-md bg-white">
                                    <table class="w-full text-sm">
                                        <thead class="bg-blue-100 sticky top-0">
                                            <tr>
                                                <th class="px-2 py-1 text-left text-xs">Product</th>
                                                <th class="px-2 py-1 text-left text-xs">Batch</th>
                                                <th class="px-2 py-1 text-center text-xs">Expiry</th>
                                                <th class="px-2 py-1 text-right text-xs">Stock Qty</th>
                                                <th class="px-2 py-1 text-right text-xs">Unit Cost</th>
                                                <th class="px-2 py-1 text-center text-xs">Add</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="batch in availableBatches" :key="batch.id">
                                                <tr class="hover:bg-blue-50 border-b border-blue-50">
                                                    <td class="px-2 py-1 text-xs" x-text="batch.product_name"></td>
                                                    <td class="px-2 py-1 text-xs" x-text="batch.batch_code"></td>
                                                    <td class="px-2 py-1 text-center text-xs"
                                                        x-text="batch.expiry_date || '-'"></td>
                                                    <td class="px-2 py-1 text-right text-xs" x-text="batch.stock_qty">
                                                    </td>
                                                    <td class="px-2 py-1 text-right text-xs"
                                                        x-text="formatNumber(batch.unit_cost)"></td>
                                                    <td class="px-2 py-1 text-center">
                                                        <button type="button" @click="addBatchToItems(batch)"
                                                            class="text-green-600 hover:text-green-800"
                                                            :disabled="isBatchAlreadyAdded(batch.id)">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                                :class="isBatchAlreadyAdded(batch.id) ? 'opacity-30' : ''">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <p x-show="searchLoading" class="text-xs text-blue-500 mt-2">Loading batches...</p>
                            <p x-show="!searchLoading && searchPerformed && availableBatches.length === 0"
                                class="text-xs text-orange-600 mt-2">No batches found matching your criteria.</p>
                        </div>

                        <p x-show="!supplierId || !warehouseId"
                            class="text-sm text-gray-500 mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            Please select both a <strong>Supplier</strong> and a <strong>Warehouse</strong> to search
                            for available batches.
                        </p>

                        <x-form-table title="Recall Items" :headers="[
        ['label' => '#', 'align' => 'text-center', 'width' => '50px'],
        ['label' => 'Product', 'align' => 'text-left', 'width' => '250px'],
        ['label' => 'Batch', 'align' => 'text-left', 'width' => '200px'],
        ['label' => 'Available<br>Qty', 'align' => 'text-center', 'width' => '120px'],
        ['label' => 'Qty to<br>Recall', 'align' => 'text-center', 'width' => '120px'],
        ['label' => 'Unit<br>Cost', 'align' => 'text-center', 'width' => '120px'],
        ['label' => 'Total<br>Value', 'align' => 'text-right', 'width' => '130px'],
        ['label' => 'Action', 'align' => 'text-center', 'width' => '80px'],
    ]">
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr>
                                        <td class="px-2 py-2 text-center text-sm text-gray-500" x-text="index + 1"></td>

                                        <td class="px-2 py-2">
                                            <input type="text" :value="item.product_name" readonly
                                                class="border-gray-300 bg-gray-50 rounded-md shadow-sm text-sm w-full">
                                            <input type="hidden" :name="`items[${index}][product_id]`"
                                                x-model="item.product_id">
                                        </td>

                                        <td class="px-2 py-2">
                                            <input type="text" :value="item.batch_code" readonly
                                                class="border-gray-300 bg-gray-50 rounded-md shadow-sm text-sm w-full">
                                            <input type="hidden" :name="`items[${index}][stock_batch_id]`"
                                                x-model="item.stock_batch_id">
                                        </td>

                                        <td class="px-2 py-2">
                                            <input type="text" :value="item.available_qty" readonly
                                                class="border-gray-300 bg-gray-50 rounded-md shadow-sm text-sm w-full text-right font-semibold">
                                        </td>

                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][quantity_recalled]`"
                                                x-model="item.quantity_recalled" @input="calculateRecallValue(index)"
                                                step="0.001" min="0.001" :max="item.available_qty" required
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full text-right"
                                                placeholder="0.000">
                                        </td>

                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][unit_cost]`"
                                                x-model="item.unit_cost" @input="calculateRecallValue(index)"
                                                step="0.01" min="0" required
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full text-right"
                                                placeholder="0.00">
                                        </td>

                                        <td class="px-2 py-2 text-right text-sm font-semibold text-red-600"
                                            x-text="formatNumber(item.total_value)">
                                        </td>

                                        <td class="px-2 py-2 text-center">
                                            <button type="button" @click="removeItem(index)"
                                                class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                                :class="items.length === 0 ? 'opacity-40 cursor-not-allowed' : ''">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="items.length === 0">
                                    <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
                                        No items added yet. Use the batch search above to find and add batches to
                                        recall.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50" x-show="items.length > 0">
                                <tr class="font-semibold bg-gray-100">
                                    <td class="px-2 py-2" colspan="4">Totals:</td>
                                    <td class="px-2 py-2 text-right"
                                        x-text="formatNumber(items.reduce((sum, item) => sum + (parseFloat(item.quantity_recalled) || 0), 0))">
                                    </td>
                                    <td class="px-2 py-2"></td>
                                    <td class="px-2 py-2 text-right font-bold text-lg text-red-600"
                                        x-text="formatNumber(items.reduce((sum, item) => sum + (parseFloat(item.total_value) || 0), 0))">
                                    </td>
                                    <td class="px-2 py-2"></td>
                                </tr>
                            </tfoot>
                        </x-form-table>

                        <hr class="my-6 border-gray-200">

                        <div class="mt-4">
                            <x-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="3"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button>
                                Save Draft
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function productRecallForm() {
                return {
                    supplierId: '{{ old("supplier_id", "") }}',
                    warehouseId: '{{ old("warehouse_id", "") }}',
                    items: [],
                    availableBatches: [],
                    searchLoading: false,
                    searchPerformed: false,
                    searchFilters: {
                        batch_code: '',
                        expiry_from: '',
                        expiry_to: '',
                    },

                    onSupplierChange() {
                        this.items = [];
                        this.availableBatches = [];
                        this.searchPerformed = false;
                        if (this.supplierId && this.warehouseId) {
                            this.searchBatches();
                        }
                    },

                    onWarehouseChange() {
                        this.items = [];
                        this.availableBatches = [];
                        this.searchPerformed = false;
                        if (this.supplierId && this.warehouseId) {
                            this.searchBatches();
                        }
                    },

                    async searchBatches() {
                        if (!this.supplierId || !this.warehouseId) return;

                        this.searchLoading = true;
                        this.searchPerformed = true;

                        try {
                            const params = new URLSearchParams();
                            params.append('warehouse_id', this.warehouseId);
                            if (this.searchFilters.batch_code) params.append('batch_code', this.searchFilters.batch_code);
                            if (this.searchFilters.expiry_from) params.append('expiry_from', this.searchFilters.expiry_from);
                            if (this.searchFilters.expiry_to) params.append('expiry_to', this.searchFilters.expiry_to);

                            const response = await fetch(`/api/suppliers/${this.supplierId}/batches?${params.toString()}`);
                            const batches = await response.json();

                            this.availableBatches = batches.map(batch => ({
                                id: batch.id,
                                product_id: batch.product_id,
                                product_name: batch.product ? batch.product.product_name : 'Unknown',
                                batch_code: batch.batch_code,
                                expiry_date: batch.expiry_date,
                                unit_cost: parseFloat(batch.unit_cost) || 0,
                                stock_qty: batch.current_stock_by_batch
                                    ? batch.current_stock_by_batch.reduce((sum, s) => sum + parseFloat(s.quantity_on_hand || 0), 0)
                                    : 0,
                            }));
                        } catch (error) {
                            console.error('Failed to search batches:', error);
                            this.availableBatches = [];
                        } finally {
                            this.searchLoading = false;
                        }
                    },

                    isBatchAlreadyAdded(batchId) {
                        return this.items.some(item => item.stock_batch_id == batchId);
                    },

                    addBatchToItems(batch) {
                        if (this.isBatchAlreadyAdded(batch.id)) return;

                        this.items.push({
                            product_id: batch.product_id,
                            product_name: batch.product_name,
                            stock_batch_id: batch.id,
                            batch_code: batch.batch_code,
                            available_qty: batch.stock_qty,
                            quantity_recalled: batch.stock_qty,
                            unit_cost: batch.unit_cost,
                            total_value: batch.stock_qty * batch.unit_cost,
                        });
                    },

                    removeItem(index) {
                        this.items.splice(index, 1);
                    },

                    calculateRecallValue(index) {
                        const item = this.items[index];
                        const qty = parseFloat(item.quantity_recalled) || 0;
                        const cost = parseFloat(item.unit_cost) || 0;
                        item.total_value = qty * cost;
                    },

                    formatNumber(value) {
                        const num = parseFloat(value) || 0;
                        return num.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>