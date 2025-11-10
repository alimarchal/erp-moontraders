<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Create Goods Receipt Note
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('goods-receipt-notes.index') }}"
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
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />

                    <form method="POST" action="{{ route('goods-receipt-notes.store') }}" id="grnForm"
                        x-data="grnForm()">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <x-label for="receipt_date" value="Receipt Date *" />
                                <x-input id="receipt_date" name="receipt_date" type="date" class="mt-1 block w-full"
                                    :value="old('receipt_date', date('Y-m-d'))" required />
                            </div>

                            <div>
                                <x-label for="supplier_id" value="Supplier *" />
                                <select id="supplier_id" name="supplier_id" required
                                    class="select2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ old('supplier_id')==$supplier->id ?
                                        'selected' : '' }}>
                                        {{ $supplier->supplier_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="warehouse_id" value="Warehouse *" />
                                <select id="warehouse_id" name="warehouse_id" required
                                    class="select2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('warehouse_id')==$warehouse->id ?
                                        'selected' : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="supplier_invoice_number" value="Supplier Invoice Number" />
                                <x-input id="supplier_invoice_number" name="supplier_invoice_number" type="text"
                                    class="mt-1 block w-full" :value="old('supplier_invoice_number')" />
                            </div>

                            <div>
                                <x-label for="supplier_invoice_date" value="Supplier Invoice Date" />
                                <x-input id="supplier_invoice_date" name="supplier_invoice_date" type="date"
                                    class="mt-1 block w-full" :value="old('supplier_invoice_date')" />
                            </div>
                        </div>

                        <hr class="my-6 border-gray-200 dark:border-gray-700">

                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Line Items</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                                            style="min-width: 350px;">
                                            Product</th>
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                                            style="min-width: 150px;">UOM
                                        </th>
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty
                                            Received</th>
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty
                                            Accepted</th>
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit
                                            Cost</th>
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Selling Price</th>
                                        <th class="px-2 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                            Total</th>
                                        <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                            Promo</th>
                                        <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <template x-for="(item, index) in items" :key="index">
                                        <tr>
                                            <td class="px-2 py-2">
                                                <select :id="`product_${index}`" :name="`items[${index}][product_id]`"
                                                    required
                                                    class="product-select select2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                    <option value="">Select Product</option>
                                                </select>
                                            </td>
                                            <td class="px-2 py-2">
                                                <select :id="`uom_${index}`" :name="`items[${index}][uom_id]`" required
                                                    class="uom-select select2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                    <option value="">Select UOM</option>
                                                    @foreach ($uoms as $uom)
                                                    <option value="{{ $uom->id }}" {{ $uom->id == 24 ? 'selected' : ''
                                                        }}>{{ $uom->uom_name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="number" :name="`items[${index}][quantity_received]`"
                                                    x-model="item.quantity_received" @input="updateTotal(index)"
                                                    step="0.01" min="0" required
                                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="number" :name="`items[${index}][quantity_accepted]`"
                                                    x-model="item.quantity_accepted" @input="updateTotal(index)"
                                                    step="0.01" min="0" required
                                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="number" :name="`items[${index}][unit_cost]`"
                                                    x-model="item.unit_cost" @input="updateTotal(index)" step="0.01"
                                                    min="0" required
                                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="number" :name="`items[${index}][selling_price]`"
                                                    x-model="item.selling_price" step="0.01" min="0"
                                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                            </td>
                                            <td class="px-2 py-2 text-right text-sm font-semibold"
                                                x-text="formatCurrency(item.total)"></td>
                                            <td class="px-2 py-2 text-center">
                                                <button type="button" @click="openPromoModal(index)"
                                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                                    title="Promotional & Batch Details">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor"
                                                        :class="{'text-orange-600': item.promotional_campaign_id}">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                                    </svg>
                                                </button>
                                            </td>
                                            <td class="px-2 py-2 text-center">
                                                <button type="button" @click="removeItem(index)"
                                                    class="text-red-600 hover:text-red-800"
                                                    :disabled="items.length === 1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </td>

                                            <!-- Hidden inputs for promotional fields -->
                                            <input type="hidden" :name="`items[${index}][promotional_campaign_id]`"
                                                x-model="item.promotional_campaign_id">
                                            <input type="hidden" :name="`items[${index}][promotional_price]`"
                                                x-model="item.promotional_price">
                                            <input type="hidden" :name="`items[${index}][promotional_discount_percent]`"
                                                x-model="item.promotional_discount_percent">
                                            <input type="hidden" :name="`items[${index}][priority_order]`"
                                                x-model="item.priority_order">
                                            <input type="hidden" :name="`items[${index}][must_sell_before]`"
                                                x-model="item.must_sell_before">
                                            <input type="hidden" :name="`items[${index}][manufacturing_date]`"
                                                x-model="item.manufacturing_date">
                                            <input type="hidden" :name="`items[${index}][expiry_date]`"
                                                x-model="item.expiry_date">
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <td colspan="9" class="px-2 py-2">
                                            <button type="button" @click="addItem()"
                                                class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                Add Line
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="6" class="px-2 py-2 text-right font-semibold">Grand Total:</td>
                                        <td class="px-2 py-2 text-right font-bold text-lg"
                                            x-text="formatCurrency(grandTotal)"></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <hr class="my-6 border-gray-200 dark:border-gray-700">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-label for="tax_amount" value="Tax Amount" />
                                <x-input id="tax_amount" name="tax_amount" type="number" step="0.01"
                                    class="mt-1 block w-full" :value="old('tax_amount', 0)" />
                            </div>
                            <div>
                                <x-label for="freight_charges" value="Freight Charges" />
                                <x-input id="freight_charges" name="freight_charges" type="number" step="0.01"
                                    class="mt-1 block w-full" :value="old('freight_charges', 0)" />
                            </div>
                            <div>
                                <x-label for="other_charges" value="Other Charges" />
                                <x-input id="other_charges" name="other_charges" type="number" step="0.01"
                                    class="mt-1 block w-full" :value="old('other_charges', 0)" />
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="3"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button>
                                Create GRN
                            </x-button>
                        </div>

                        <!-- Promotional Details Modal -->
                        <div x-show="showPromoModal" x-cloak @keydown.escape.window="showPromoModal = false"
                            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
                            aria-labelledby="modal-title" role="dialog" aria-modal="true">

                            <!-- Background Overlay -->
                            <div @click="showPromoModal = false"
                                class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" aria-hidden="true">
                            </div>

                            <!-- Modal Content -->
                            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
                                @click.stop>

                                <!-- Header with Close Button -->
                                <div
                                    class="bg-white dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            Promotional & Batch Details
                                        </h3>
                                        <button type="button" @click="showPromoModal = false"
                                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Body -->
                                <div class="px-6 py-4 overflow-y-auto max-h-[calc(90vh-140px)]">
                                    <template x-if="currentEditIndex !== null">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Campaign
                                                </label>
                                                <select x-model="items[currentEditIndex].promotional_campaign_id"
                                                    class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                                    <option value="">None</option>
                                                    @foreach ($campaigns as $campaign)
                                                    <option value="{{ $campaign->id }}">
                                                        {{ $campaign->campaign_code }} - {{ $campaign->campaign_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Promotional Price
                                                </label>
                                                <input type="number" x-model="items[currentEditIndex].promotional_price"
                                                    step="0.01" min="0"
                                                    class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                                    :disabled="!items[currentEditIndex].promotional_campaign_id">
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Discount %
                                                </label>
                                                <input type="number"
                                                    x-model="items[currentEditIndex].promotional_discount_percent"
                                                    step="0.01" min="0" max="100"
                                                    class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Priority (1-10)
                                                </label>
                                                <input type="number" x-model="items[currentEditIndex].priority_order"
                                                    step="1" min="1" max="10"
                                                    class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                                    placeholder="Lower = Higher Priority">
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Must Sell Before
                                                </label>
                                                <input type="date" x-model="items[currentEditIndex].must_sell_before"
                                                    class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                                    :disabled="!items[currentEditIndex].promotional_campaign_id">
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Manufacturing Date
                                                </label>
                                                <input type="date" x-model="items[currentEditIndex].manufacturing_date"
                                                    class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                    Expiry Date
                                                </label>
                                                <input type="date" x-model="items[currentEditIndex].expiry_date"
                                                    class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Footer -->
                                <div
                                    class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                                    <button type="button"
                                        @click="clearPromoFields(currentEditIndex); showPromoModal = false"
                                        class="inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Clear All
                                    </button>
                                    <button type="button" @click="showPromoModal = false"
                                        class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Save & Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const allProducts = @json($products);
        const oldItems = @json(old('items', []));
        const defaultUomId = 24; // Piece UOM

        function grnForm() {
            return {
                showPromoModal: false,
                currentEditIndex: null,

                items: oldItems.length > 0 ? oldItems.map(item => ({
                    product_id: item.product_id || '',
                    uom_id: item.uom_id || defaultUomId || '',
                    manufacturing_date: item.manufacturing_date || '',
                    expiry_date: item.expiry_date || '',
                    quantity_received: parseFloat(item.quantity_received) || 0,
                    quantity_accepted: parseFloat(item.quantity_accepted) || 0,
                    unit_cost: parseFloat(item.unit_cost) || 0,
                    selling_price: parseFloat(item.selling_price) || 0,
                    promotional_campaign_id: item.promotional_campaign_id || '',
                    promotional_price: parseFloat(item.promotional_price) || 0,
                    promotional_discount_percent: parseFloat(item.promotional_discount_percent) || 0,
                    priority_order: parseInt(item.priority_order) || null,
                    must_sell_before: item.must_sell_before || '',
                    total: parseFloat(item.quantity_accepted || 0) * parseFloat(item.unit_cost || 0)
                })) : [{
                    product_id: '',
                    uom_id: defaultUomId || '',
                    manufacturing_date: '',
                    expiry_date: '',
                    quantity_received: 0,
                    quantity_accepted: 0,
                    unit_cost: 0,
                    selling_price: 0,
                    promotional_campaign_id: '',
                    promotional_price: 0,
                    promotional_discount_percent: 0,
                    priority_order: null,
                    must_sell_before: '',
                    total: 0
                }],

                addItem() {
                    const newIndex = this.items.length;
                    this.items.push({
                        product_id: '',
                        uom_id: defaultUomId || '',
                        manufacturing_date: '',
                        expiry_date: '',
                        quantity_received: 0,
                        quantity_accepted: 0,
                        unit_cost: 0,
                        selling_price: 0,
                        promotional_campaign_id: '',
                        promotional_price: 0,
                        promotional_discount_percent: 0,
                        priority_order: null,
                        must_sell_before: '',
                        total: 0
                    });

                    // Initialize Select2 for new product dropdown after DOM update
                    this.$nextTick(() => {
                        initializeProductSelect2(newIndex);
                    });
                },

                openPromoModal(index) {
                    this.currentEditIndex = index;
                    this.showPromoModal = true;
                },

                clearPromoFields(index) {
                    this.items[index].promotional_campaign_id = '';
                    this.items[index].promotional_price = 0;
                    this.items[index].promotional_discount_percent = 0;
                    this.items[index].priority_order = null;
                    this.items[index].must_sell_before = '';
                    this.items[index].manufacturing_date = '';
                    this.items[index].expiry_date = '';
                },

                removeItem(index) {
                    if (this.items.length > 1) {
                        // Destroy Select2 before removing
                        if ($(`#product_${index}`).data('select2')) {
                            $(`#product_${index}`).select2('destroy');
                        }
                        if ($(`#uom_${index}`).data('select2')) {
                            $(`#uom_${index}`).select2('destroy');
                        }
                        this.items.splice(index, 1);
                    }
                },

                updateProduct(index) {
                    const productId = this.items[index].product_id;
                    const product = allProducts.find(p => p.id == productId);
                    if (product) {
                        this.items[index].unit_cost = product.unit_price || 0;
                        this.items[index].selling_price = product.unit_price || 0;
                        this.updateTotal(index);
                    }
                },

                updateTotal(index) {
                    const item = this.items[index];
                    const qty = parseFloat(item.quantity_accepted) || 0;
                    const cost = parseFloat(item.unit_cost) || 0;
                    item.total = qty * cost;
                },

                get grandTotal() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0);
                },

                formatCurrency(value) {
                    return '₨ ' + parseFloat(value || 0).toLocaleString('en-PK', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
        }

        function initializeProductSelect2(index) {
            const supplierId = $('#supplier_id').val();
            const $select = $(`#product_${index}`);

            $select.select2({
                placeholder: 'Select Product',
                allowClear: true,
                width: '100%',
                data: getFilteredProducts(supplierId)
            });

            // Sync with Alpine.js
            $select.on('change', function() {
                const event = new Event('change', { bubbles: true });
                this.dispatchEvent(event);
            });

            // Initialize UOM Select2 for this row
            initializeUomSelect2(index);
        }

        function initializeUomSelect2(index) {
            const $select = $(`#uom_${index}`);

            $select.select2({
                placeholder: 'Select UOM',
                allowClear: true,
                width: '100%'
            });

            // Sync with Alpine.js
            $select.on('change', function() {
                const event = new Event('change', { bubbles: true });
                this.dispatchEvent(event);
            });
        }

        function getFilteredProducts(supplierId) {
            let filteredProducts = allProducts;

            if (supplierId) {
                filteredProducts = allProducts.filter(p => p.supplier_id == supplierId);
            }

            return filteredProducts.map(p => ({
                id: p.id,
                text: `${p.product_code} - ${p.product_name}`
            }));
        }

        function refreshAllProductSelects() {
            const supplierId = $('#supplier_id').val();
            const productData = getFilteredProducts(supplierId);

            $('.product-select').each(function(index) {
                const $this = $(this);
                const currentValue = $this.val();

                // Unbind events before destroying
                $this.off('change');

                // Destroy and reinitialize
                if ($this.data('select2')) {
                    $this.select2('destroy');
                }

                $this.empty();
                $this.append('<option value="">Select Product</option>');

                productData.forEach(item => {
                    $this.append(new Option(item.text, item.id, false, false));
                });

                $this.select2({
                    placeholder: 'Select Product',
                    allowClear: true,
                    width: '100%'
                });

                // Restore previous value if it exists in new filtered list
                if (currentValue && productData.find(p => p.id == currentValue)) {
                    $this.val(currentValue).trigger('change.select2');
                }

                // Re-bind change event with Alpine.js sync
                $this.on('change', function() {
                    const event = new Event('change', { bubbles: true });
                    this.dispatchEvent(event);
                });
            });
        }

        // Wait for Select2 to be fully loaded
        function initializeGRNForm() {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                setTimeout(initializeGRNForm, 100);
                return;
            }

            $(document).ready(function() {
                // Initialize Select2 for supplier
                $('#supplier_id').select2({
                    placeholder: 'Select Supplier',
                    allowClear: true,
                    width: '100%'
                });

                // Initialize Select2 for warehouse
                $('#warehouse_id').select2({
                    placeholder: 'Select Warehouse',
                    allowClear: true,
                    width: '100%'
                });

                // Initialize product selects for existing items (including old input)
                $('.product-select').each(function(index) {
                    initializeProductSelect2(index);
                });

                // When supplier changes, refresh all product dropdowns
                $('#supplier_id').on('change', function() {
                    refreshAllProductSelects();
                });

                // If there's old supplier value, trigger product refresh
                @if(old('supplier_id'))
                refreshAllProductSelects();
                @endif
            });
        }

        // Start initialization
        initializeGRNForm();
    </script>
    @endpush
</x-app-layout>