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
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <td colspan="8" class="px-2 py-2">
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
                                        <td></td>
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
                items: oldItems.length > 0 ? oldItems.map(item => ({
                    product_id: item.product_id || '',
                    uom_id: item.uom_id || defaultUomId || '',
                    quantity_received: parseFloat(item.quantity_received) || 0,
                    quantity_accepted: parseFloat(item.quantity_accepted) || 0,
                    unit_cost: parseFloat(item.unit_cost) || 0,
                    selling_price: parseFloat(item.selling_price) || 0,
                    total: parseFloat(item.quantity_accepted || 0) * parseFloat(item.unit_cost || 0)
                })) : [{
                    product_id: '',
                    uom_id: defaultUomId || '',
                    quantity_received: 0,
                    quantity_accepted: 0,
                    unit_cost: 0,
                    selling_price: 0,
                    total: 0
                }],

                addItem() {
                    const newIndex = this.items.length;
                    this.items.push({
                        product_id: '',
                        uom_id: defaultUomId || '',
                        quantity_received: 0,
                        quantity_accepted: 0,
                        unit_cost: 0,
                        selling_price: 0,
                        total: 0
                    });

                    // Initialize Select2 for new product dropdown after DOM update
                    this.$nextTick(() => {
                        initializeProductSelect2(newIndex);
                    });
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