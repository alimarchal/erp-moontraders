<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Edit Goods Receipt Note: {{ $grn->grn_number }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('goods-receipt-notes.show', $grn->id) }}"
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

                    <form method="POST" action="{{ route('goods-receipt-notes.update', $grn->id) }}"
                        x-data="grnForm({{ json_encode($products->toArray()) }}, {{ json_encode($uoms->toArray()) }}, {{ json_encode($grn->items->toArray()) }})">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <x-label for="receipt_date" value="Receipt Date *" />
                                <x-input id="receipt_date" name="receipt_date" type="date" class="mt-1 block w-full"
                                    :value="old('receipt_date', $grn->receipt_date)" required />
                            </div>

                            <div>
                                <x-label for="supplier_id" value="Supplier *" />
                                <select id="supplier_id" name="supplier_id" required
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ old('supplier_id', $grn->supplier_id) ==
                                        $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->supplier_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="warehouse_id" value="Warehouse *" />
                                <select id="warehouse_id" name="warehouse_id" required
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('warehouse_id', $grn->warehouse_id) ==
                                        $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="supplier_invoice_number" value="Supplier Invoice Number" />
                                <x-input id="supplier_invoice_number" name="supplier_invoice_number" type="text"
                                    class="mt-1 block w-full"
                                    :value="old('supplier_invoice_number', $grn->supplier_invoice_number)" />
                            </div>

                            <div>
                                <x-label for="supplier_invoice_date" value="Supplier Invoice Date" />
                                <x-input id="supplier_invoice_date" name="supplier_invoice_date" type="date"
                                    class="mt-1 block w-full"
                                    :value="old('supplier_invoice_date', $grn->supplier_invoice_date)" />
                            </div>
                        </div>

                        <hr class="my-6 border-gray-200 dark:border-gray-700">

                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Line Items</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Product</th>
                                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">UOM
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
                                                <select :name="`items[${index}][product_id]`" required
                                                    x-model="item.product_id" @change="updateProduct(index)"
                                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                    <option value="">Select</option>
                                                    @foreach ($products as $product)
                                                    <option value="{{ $product->id }}">
                                                        {{ $product->product_code }} - {{ $product->product_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-2 py-2">
                                                <select :name="`items[${index}][uom_id]`" required x-model="item.uom_id"
                                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                    <option value="">Select</option>
                                                    @foreach ($uoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->uom_name }}</option>
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
                                    class="mt-1 block w-full" :value="old('tax_amount', $grn->tax_amount)" />
                            </div>
                            <div>
                                <x-label for="freight_charges" value="Freight Charges" />
                                <x-input id="freight_charges" name="freight_charges" type="number" step="0.01"
                                    class="mt-1 block w-full" :value="old('freight_charges', $grn->freight_charges)" />
                            </div>
                            <div>
                                <x-label for="other_charges" value="Other Charges" />
                                <x-input id="other_charges" name="other_charges" type="number" step="0.01"
                                    class="mt-1 block w-full" :value="old('other_charges', $grn->other_charges)" />
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="3"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">{{ old('notes', $grn->notes) }}</textarea>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button>
                                Update GRN
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function grnForm(productsData, uomsData, existingItems) {
            return {
                items: existingItems.map(item => ({
                    product_id: item.product_id,
                    uom_id: item.uom_id,
                    quantity_received: parseFloat(item.quantity_received),
                    quantity_accepted: parseFloat(item.quantity_accepted),
                    unit_cost: parseFloat(item.unit_cost),
                    selling_price: parseFloat(item.selling_price || 0),
                    total: parseFloat(item.total_cost)
                })),
                products: productsData,
                uoms: uomsData,

                addItem() {
                    this.items.push({
                        product_id: '',
                        uom_id: '',
                        quantity_received: 0,
                        quantity_accepted: 0,
                        unit_cost: 0,
                        selling_price: 0,
                        total: 0
                    });
                },

                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    }
                },

                updateProduct(index) {
                    const product = this.products.find(p => p.id == this.items[index].product_id);
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
    </script>
</x-app-layout>