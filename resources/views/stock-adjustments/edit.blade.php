<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Edit Stock Adjustment - {{ $adjustment->adjustment_number }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('stock-adjustments.show', $adjustment) }}"
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

                    <form method="POST" action="{{ route('stock-adjustments.update', $adjustment) }}" id="stockAdjustmentEditForm"
                        x-data="stockAdjustmentEditForm()">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <x-label for="adjustment_date" value="Adjustment Date *" />
                                <x-input id="adjustment_date" name="adjustment_date" type="date"
                                    class="mt-1 block w-full" :value="old('adjustment_date', $adjustment->adjustment_date->format('Y-m-d'))" required />
                            </div>

                            <div>
                                <x-label for="warehouse_id" value="Warehouse *" />
                                <select id="warehouse_id" name="warehouse_id" required x-model="warehouseId"
                                    @change="onWarehouseChange()"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ $adjustment->warehouse_id == $warehouse->id ? 'selected' : '' }}>
                                            {{ $warehouse->warehouse_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="adjustment_type" value="Adjustment Type *" />
                                <select id="adjustment_type" name="adjustment_type" required
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Type</option>
                                    @foreach (['damage', 'theft', 'count_variance', 'expiry', 'other'] as $type)
                                        <option value="{{ $type }}" {{ $adjustment->adjustment_type == $type ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="reason" value="Reason *" />
                                <textarea id="reason" name="reason" rows="1" required
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('reason', $adjustment->reason) }}</textarea>
                            </div>
                        </div>

                        <hr class="my-6 border-gray-200">

                        <x-form-table title="Adjustment Items" :headers="[
                            ['label' => '#', 'align' => 'text-center', 'width' => '50px'],
                            ['label' => 'Product', 'align' => 'text-left', 'width' => '280px'],
                            ['label' => 'Batch', 'align' => 'text-left', 'width' => '220px'],
                            ['label' => 'UOM', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'System<br>Qty', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Actual<br>Qty', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Adj<br>Qty', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Unit<br>Cost', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Adj<br>Value', 'align' => 'text-right', 'width' => '130px'],
                            ['label' => 'Action', 'align' => 'text-center', 'width' => '80px'],
                        ]">
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr>
                                        <td class="px-2 py-2 text-center text-sm text-gray-500" x-text="index + 1"></td>

                                        <td class="px-2 py-2">
                                            <select :name="`items[${index}][product_id]`" x-model="item.product_id"
                                                @change="onProductChange(index)" required
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                <option value="">Select Product</option>
                                                @foreach ($products as $product)
                                                    <option value="{{ $product->id }}"
                                                        data-uom-id="{{ $product->uom_id }}">
                                                        {{ $product->product_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>

                                        <td class="px-2 py-2">
                                            <select :name="`items[${index}][stock_batch_id]`"
                                                x-model="item.stock_batch_id" @change="onBatchChange(index)" required
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                <option value="">Select Batch</option>
                                                <template x-for="batch in item.availableBatches" :key="batch.id">
                                                    <option :value="batch.id"
                                                        x-text="`${batch.batch_code} (Qty: ${batch.stock_qty}, Cost: ${batch.unit_cost})`">
                                                    </option>
                                                </template>
                                            </select>
                                            <p x-show="item.loadingBatches" class="text-xs text-blue-500 mt-1">Loading batches...</p>
                                        </td>

                                        <td class="px-2 py-2">
                                            <select :name="`items[${index}][uom_id]`" x-model="item.uom_id" required
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                <option value="">UOM</option>
                                                @foreach ($uoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->uom_name }}</option>
                                                @endforeach
                                            </select>
                                        </td>

                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][system_quantity]`"
                                                x-model="item.system_quantity" step="0.001" min="0" readonly
                                                class="border-gray-300 bg-gray-50 rounded-md shadow-sm text-sm w-full text-right font-semibold">
                                        </td>

                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][actual_quantity]`"
                                                x-model="item.actual_quantity" @input="calculateAdjustment(index)"
                                                step="0.001" min="0" required
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full text-right">
                                        </td>

                                        <td class="px-2 py-2">
                                            <input type="text" :value="formatNumber(item.adjustment_quantity)" readonly
                                                class="border-gray-300 bg-gray-50 rounded-md shadow-sm text-sm w-full text-right font-semibold"
                                                :class="item.adjustment_quantity < 0 ? 'text-red-600' : (item.adjustment_quantity > 0 ? 'text-green-600' : '')">
                                        </td>

                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][unit_cost]`"
                                                x-model="item.unit_cost" @input="calculateAdjustment(index)"
                                                step="0.01" min="0" required
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full text-right">
                                        </td>

                                        <td class="px-2 py-2 text-right text-sm font-semibold"
                                            :class="item.adjustment_value < 0 ? 'text-red-600' : (item.adjustment_value > 0 ? 'text-green-600' : '')"
                                            x-text="formatNumber(item.adjustment_value)">
                                        </td>

                                        <td class="px-2 py-2 text-center">
                                            <button type="button" @click="removeItem(index)"
                                                class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                                :class="items.length === 1 ? 'opacity-40 cursor-not-allowed' : ''"
                                                :disabled="items.length === 1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr class="font-semibold bg-gray-100">
                                    <td class="px-2 py-2" colspan="4">Totals:</td>
                                    <td class="px-2 py-2 text-right" x-text="formatNumber(items.reduce((s, i) => s + (parseFloat(i.system_quantity) || 0), 0))"></td>
                                    <td class="px-2 py-2 text-right" x-text="formatNumber(items.reduce((s, i) => s + (parseFloat(i.actual_quantity) || 0), 0))"></td>
                                    <td class="px-2 py-2 text-right" x-text="formatNumber(items.reduce((s, i) => s + (parseFloat(i.adjustment_quantity) || 0), 0))"></td>
                                    <td class="px-2 py-2"></td>
                                    <td class="px-2 py-2 text-right font-bold text-lg" x-text="formatNumber(items.reduce((s, i) => s + (parseFloat(i.adjustment_value) || 0), 0))"></td>
                                    <td class="px-2 py-2"></td>
                                </tr>
                                <tr>
                                    <td colspan="10" class="px-2 py-2">
                                        <button type="button" @click="addItem()"
                                            class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Line
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </x-form-table>

                        <hr class="my-6 border-gray-200">

                        <div class="mt-4">
                            <x-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="3"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('notes', $adjustment->notes) }}</textarea>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button>Update Adjustment</x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function stockAdjustmentEditForm() {
            return {
                warehouseId: '{{ old("warehouse_id", $adjustment->warehouse_id) }}',
                items: @json($adjustment->items->map(fn($item) => [
                    'product_id' => (string) $item->product_id,
                    'stock_batch_id' => (string) $item->stock_batch_id,
                    'uom_id' => (string) $item->uom_id,
                    'system_quantity' => (float) $item->system_quantity,
                    'actual_quantity' => (float) $item->actual_quantity,
                    'adjustment_quantity' => (float) $item->adjustment_quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'adjustment_value' => (float) $item->adjustment_value,
                    'availableBatches' => [],
                    'loadingBatches' => false,
                ])->values()),

                init() {
                    this.items.forEach((item, index) => {
                        if (item.product_id) {
                            this.loadBatches(index);
                        }
                    });
                },

                addItem() {
                    this.items.push({
                        product_id: '',
                        stock_batch_id: '',
                        uom_id: '',
                        system_quantity: 0,
                        actual_quantity: '',
                        adjustment_quantity: 0,
                        unit_cost: '',
                        adjustment_value: 0,
                        availableBatches: [],
                        loadingBatches: false,
                    });
                },

                removeItem(index) {
                    if (this.items.length > 1) { this.items.splice(index, 1); }
                },

                onWarehouseChange() {
                    this.items.forEach((item, index) => {
                        if (item.product_id) { this.loadBatches(index); }
                    });
                },

                async onProductChange(index) {
                    const item = this.items[index];
                    item.stock_batch_id = '';
                    item.system_quantity = 0;
                    item.unit_cost = '';
                    item.adjustment_quantity = 0;
                    item.adjustment_value = 0;
                    item.availableBatches = [];

                    const selectEl = document.querySelector(`[name="items[${index}][product_id]"]`);
                    if (selectEl && selectEl.selectedIndex > 0) {
                        const uomId = selectEl.options[selectEl.selectedIndex].getAttribute('data-uom-id');
                        if (uomId) { item.uom_id = uomId; }
                    }

                    if (item.product_id && this.warehouseId) {
                        await this.loadBatches(index);
                    }
                },

                async loadBatches(index) {
                    const item = this.items[index];
                    if (!item.product_id || !this.warehouseId) return;

                    const savedBatchId = item.stock_batch_id;
                    item.loadingBatches = true;
                    item.availableBatches = [];

                    try {
                        const response = await fetch(`/api/products/${item.product_id}/batches/${this.warehouseId}`);
                        const batches = await response.json();
                        item.availableBatches = batches.map(batch => ({
                            id: batch.id,
                            batch_code: batch.batch_code,
                            unit_cost: parseFloat(batch.unit_cost) || 0,
                            stock_qty: batch.current_stock_by_batch
                                ? batch.current_stock_by_batch.reduce((sum, s) => sum + parseFloat(s.quantity_on_hand || 0), 0)
                                : 0,
                        }));
                        if (savedBatchId) {
                            item.stock_batch_id = savedBatchId;
                        }
                    } catch (error) {
                        console.error('Failed to load batches:', error);
                    } finally {
                        item.loadingBatches = false;
                    }
                },

                onBatchChange(index) {
                    const item = this.items[index];
                    const batch = item.availableBatches.find(b => b.id == item.stock_batch_id);
                    if (batch) {
                        item.system_quantity = batch.stock_qty;
                        item.unit_cost = batch.unit_cost;
                        this.calculateAdjustment(index);
                    }
                },

                calculateAdjustment(index) {
                    const item = this.items[index];
                    item.adjustment_quantity = (parseFloat(item.actual_quantity) || 0) - (parseFloat(item.system_quantity) || 0);
                    item.adjustment_value = item.adjustment_quantity * (parseFloat(item.unit_cost) || 0);
                },

                formatNumber(value) {
                    return (parseFloat(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
