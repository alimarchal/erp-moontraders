<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
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
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
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
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
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
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
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

                        <hr class="my-6 border-gray-200">

                        <x-form-table title="Line Items" :headers="[
                            ['label' => 'Product', 'align' => 'text-left', 'width' => '350px'],
                            ['label' => 'Qty<br>Cases', 'align' => 'text-center', 'width' => '100px'],
                            ['label' => 'Unit<br>Price', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Extended<br>Value', 'align' => 'text-center', 'width' => '130px'],
                            ['label' => 'Discount<br>Value', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'FMR<br>Allowance', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Discounted Value<br>Before Sales Tax', 'align' => 'text-center', 'width' => '140px'],
                            ['label' => 'Excise<br>Duty', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Sales Tax<br>Value', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Advance<br>Income Tax', 'align' => 'text-center', 'width' => '130px'],
                            ['label' => 'Qty<br>Received', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Unit<br>Cost', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Selling<br>Price', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Total Value<br>with Taxes', 'align' => 'text-right', 'width' => '140px'],
                            ['label' => 'Promo', 'align' => 'text-center'],
                            ['label' => 'Action', 'align' => 'text-center'],
                        ]">
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr>
                                        <td class="px-2 py-2">
                                            <select :id="`product_${index}`" :name="`items[${index}][product_id]`"
                                                required
                                                class="product-select select2 border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                <option value="">Select Product</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][qty_cases]`"
                                                x-model="item.qty_cases" @input="calculateFromCases(index)" step="0.01"
                                                min="0"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full"
                                                placeholder="Cases">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][unit_price_per_case]`"
                                                x-model="item.unit_price_per_case" @input="calculateFromCases(index)"
                                                step="0.01" min="0"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full"
                                                placeholder="Price/Case">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="text" :value="formatNumber(item.extended_value)" readonly
                                                class="border-gray-300 bg-gray-50 rounded-md shadow-sm text-sm w-full text-right font-semibold"
                                                placeholder="0.00">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][discount_value]`"
                                                x-model="item.discount_value" @input="calculateTaxes(index)" step="0.01"
                                                min="0"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full"
                                                placeholder="0.00">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][fmr_allowance]`"
                                                x-model="item.fmr_allowance" @input="calculateTaxes(index)" step="0.01"
                                                min="0"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full"
                                                placeholder="0.00">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="text" :value="formatNumber(item.discounted_value_before_tax)"
                                                readonly
                                                class="border-gray-300 bg-gray-50 rounded-md shadow-sm text-sm w-full text-right font-semibold"
                                                placeholder="0.00">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][excise_duty]`"
                                                x-model="item.excise_duty" @input="calculateTaxes(index)" step="0.01"
                                                min="0"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full"
                                                placeholder="0.00">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][sales_tax_value]`"
                                                x-model="item.sales_tax_value" @input="onSalesTaxManualEdit(index)"
                                                step="0.01" min="0"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm w-full"
                                                placeholder="Auto-calculated">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][advance_income_tax]`"
                                                x-model="item.advance_income_tax" @input="calculateTaxes(index)"
                                                step="0.01" min="0"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full"
                                                placeholder="0.00">
                                        </td>
                                        <!-- Hidden UOM field - defaults to Piece (ID: 24) -->
                                        <input type="hidden" :name="`items[${index}][uom_id]`" x-model="item.uom_id">
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][quantity_received]`"
                                                x-model="item.quantity_received" step="0.01" min="0" required readonly
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full bg-gray-50">
                                            <!-- Hidden quantity_accepted field that mirrors quantity_received -->
                                            <input type="hidden" :name="`items[${index}][quantity_accepted]`"
                                                x-model="item.quantity_received">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="text" :value="formatNumber(item.unit_cost)" readonly
                                                class="border-gray-300 bg-gray-50 rounded-md shadow-sm text-sm w-full text-right">
                                            <!-- Hidden input to submit unit_cost value -->
                                            <input type="hidden" :name="`items[${index}][unit_cost]`"
                                                x-model="item.unit_cost">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" :name="`items[${index}][selling_price]`"
                                                x-model="item.selling_price" step="0.01" min="0"
                                                @input="validateSellingPrice(index)"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                        </td>
                                        <td class="px-2 py-2 text-right text-sm font-semibold"
                                            x-text="formatCurrency(item.total_value_with_taxes)"></td>
                                        <td class="px-2 py-2 text-center">
                                            <button type="button" @click="openPromoModal(index)"
                                                class="text-blue-600 hover:text-blue-800"
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
                                                class="text-red-600 hover:text-red-800" :disabled="items.length === 1">
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
                                        <input type="hidden" :name="`items[${index}][selling_strategy]`"
                                            x-model="item.selling_strategy">
                                        <input type="hidden" :name="`items[${index}][priority_order]`"
                                            x-model="item.priority_order">
                                        <input type="hidden" :name="`items[${index}][must_sell_before]`"
                                            x-model="item.must_sell_before">
                                        <input type="hidden" :name="`items[${index}][manufacturing_date]`"
                                            x-model="item.manufacturing_date">
                                        <input type="hidden" :name="`items[${index}][expiry_date]`"
                                            x-model="item.expiry_date">
                                        <input type="hidden" :name="`items[${index}][batch_number]`"
                                            x-model="item.batch_number">
                                        <input type="hidden" :name="`items[${index}][lot_number]`"
                                            x-model="item.lot_number">
                                        <input type="hidden" :name="`items[${index}][storage_location]`"
                                            x-model="item.storage_location">
                                        <input type="hidden" :name="`items[${index}][quality_status]`"
                                            x-model="item.quality_status">

                                        <!-- Hidden calculated fields -->
                                        <input type="hidden" :name="`items[${index}][extended_value]`"
                                            x-model="item.extended_value">
                                        <input type="hidden" :name="`items[${index}][discounted_value_before_tax]`"
                                            x-model="item.discounted_value_before_tax">
                                        <input type="hidden" :name="`items[${index}][total_value_with_taxes]`"
                                            x-model="item.total_value_with_taxes">
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="18" class="px-2 py-2">
                                        <button type="button" @click="addItem()"
                                            class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Line
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="13" class="px-2 py-2 text-right font-semibold">Grand Total:</td>
                                    <td class="px-2 py-2 text-right font-bold text-lg"
                                        x-text="formatCurrency(grandTotal)"></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </x-form-table>

                        <hr class="my-6 border-gray-200">

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
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('notes') }}</textarea>
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
                            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
                                @click.stop>

                                <!-- Header with Close Button -->
                                <div class="bg-white px-6 py-4 border-b border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            Promotional & Batch Details
                                        </h3>
                                        <button type="button" @click="showPromoModal = false"
                                            class="text-gray-400 hover:text-gray-500 focus:outline-none">
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
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Campaign
                                                </label>
                                                <select x-model="items[currentEditIndex].promotional_campaign_id"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                                    <option value="">None</option>
                                                    @foreach ($campaigns as $campaign)
                                                    <option value="{{ $campaign->id }}">
                                                        {{ $campaign->campaign_code }} - {{ $campaign->campaign_name }}
                                                        @if ($campaign->discount_type === 'buy_x_get_y')
                                                        ({{ $campaign->buy_quantity }}+{{ $campaign->get_quantity }})
                                                        @endif
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Promotional Price
                                                </label>
                                                <input type="number" x-model="items[currentEditIndex].promotional_price"
                                                    step="0.01" min="0"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                                    :disabled="!items[currentEditIndex].promotional_campaign_id">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Discount %
                                                </label>
                                                <input type="number"
                                                    x-model="items[currentEditIndex].promotional_discount_percent"
                                                    step="0.01" min="0" max="100"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Selling Strategy
                                                </label>
                                                <select x-model="items[currentEditIndex].selling_strategy"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                                    <option value="fifo">FIFO (First In, First Out)</option>
                                                    <option value="lifo">LIFO (Last In, First Out)</option>
                                                    <option value="priority">Priority Based (Use priority number below)
                                                    </option>
                                                    <option value="expiry_first">Expiry Date First</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Priority (1-10)
                                                </label>
                                                <select x-model="items[currentEditIndex].priority_order"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                                    <option value="">None (Normal FIFO)</option>
                                                    <option value="1">1 - Highest Priority (Sell First)</option>
                                                    <option value="2">2</option>
                                                    <option value="3">3</option>
                                                    <option value="4">4</option>
                                                    <option value="5">5</option>
                                                    <option value="6">6</option>
                                                    <option value="7">7</option>
                                                    <option value="8">8</option>
                                                    <option value="9">9</option>
                                                    <option value="10">10 - Lowest Priority</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Must Sell Before
                                                </label>
                                                <input type="date" x-model="items[currentEditIndex].must_sell_before"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                                    :disabled="!items[currentEditIndex].promotional_campaign_id">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Manufacturing Date
                                                </label>
                                                <input type="date" x-model="items[currentEditIndex].manufacturing_date"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Expiry Date
                                                </label>
                                                <input type="date" x-model="items[currentEditIndex].expiry_date"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                            </div>

                                            <!-- Batch Tracking Fields (Optional - Auto-generated if not entered) -->
                                            <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-200">
                                                <h4 class="text-sm font-semibold text-gray-700 mb-3">
                                                    Batch Tracking (Optional - Auto-generated if empty)
                                                </h4>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Batch Number
                                                </label>
                                                <input type="text" x-model="items[currentEditIndex].batch_number"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                                    placeholder="Leave empty for auto-generation">
                                                <p class="text-xs text-gray-500 mt-1">Supplier's batch code (optional)
                                                </p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Lot Number
                                                </label>
                                                <input type="text" x-model="items[currentEditIndex].lot_number"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                                    placeholder="Leave empty for auto-generation">
                                                <p class="text-xs text-gray-500 mt-1">Internal lot code (optional)</p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Storage Location
                                                </label>
                                                <input type="text" x-model="items[currentEditIndex].storage_location"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                                    placeholder="e.g., Rack A-1, Bin 5">
                                                <p class="text-xs text-gray-500 mt-1">Warehouse location (optional)</p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Quality Status
                                                </label>
                                                <select x-model="items[currentEditIndex].quality_status"
                                                    class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                                    <option value="approved">Approved (Ready to sell)</option>
                                                    <option value="pending">Pending QC</option>
                                                    <option value="rejected">Rejected</option>
                                                </select>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Footer -->
                                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                                    <button type="button"
                                        @click="clearPromoFields(currentEditIndex); showPromoModal = false"
                                        class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
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
        let allProducts = []; // Will be loaded via AJAX when supplier is selected
        const allSuppliers = @json($suppliers); // All suppliers with sales_tax
        const oldItems = @json(old('items', []));
        const defaultUomId = 24; // Piece UOM

        function grnForm() {
            return {
                showPromoModal: false,
                currentEditIndex: null,
                supplierSalesTaxRate: 18.00, // Default sales tax rate

                items: oldItems.length > 0 ? oldItems.map(item => ({
                    product_id: item.product_id || '',
                    uom_id: item.uom_id || defaultUomId || '',
                    qty_cases: parseFloat(item.qty_cases) || 0,
                    unit_price_per_case: parseFloat(item.unit_price_per_case) || 0,
                    extended_value: parseFloat(item.extended_value) || (parseFloat(item.qty_cases) || 0) * (parseFloat(item.unit_price_per_case) || 0),
                    discount_value: parseFloat(item.discount_value) || 0,
                    fmr_allowance: parseFloat(item.fmr_allowance) || 0,
                    discounted_value_before_tax: parseFloat(item.discounted_value_before_tax) || 0,
                    excise_duty: parseFloat(item.excise_duty) || 0,
                    sales_tax_value: parseFloat(item.sales_tax_value) || 0,
                    sales_tax_manually_edited: !!(item.sales_tax_value && parseFloat(item.sales_tax_value) > 0),
                    advance_income_tax: parseFloat(item.advance_income_tax) || 0,
                    total_value_with_taxes: parseFloat(item.total_value_with_taxes) || 0,
                    manufacturing_date: item.manufacturing_date || '',
                    expiry_date: item.expiry_date || '',
                    quantity_received: parseFloat(item.quantity_received) || 0,
                    quantity_accepted: parseFloat(item.quantity_accepted) || 0,
                    unit_cost: parseFloat(item.unit_cost) || 0,
                    selling_price: parseFloat(item.selling_price) || 0,
                    max_selling_price: parseFloat(item.max_selling_price) || parseFloat(item.selling_price) || 0,
                    promotional_campaign_id: item.promotional_campaign_id || '',
                    promotional_price: parseFloat(item.promotional_price) || 0,
                    promotional_discount_percent: parseFloat(item.promotional_discount_percent) || 0,
                    selling_strategy: item.selling_strategy || 'fifo',
                    priority_order: parseInt(item.priority_order) || null,
                    must_sell_before: item.must_sell_before || '',
                    batch_number: item.batch_number || '',
                    lot_number: item.lot_number || '',
                    storage_location: item.storage_location || '',
                    quality_status: item.quality_status || 'approved',
                    total: parseFloat(item.quantity_accepted || 0) * parseFloat(item.unit_cost || 0)
                })) : [{
                    product_id: '',
                    uom_id: defaultUomId || '',
                    qty_cases: 0,
                    unit_price_per_case: 0,
                    extended_value: 0,
                    discount_value: 0,
                    fmr_allowance: 0,
                    discounted_value_before_tax: 0,
                    excise_duty: 0,
                    sales_tax_value: 0,
                    sales_tax_manually_edited: false,
                    advance_income_tax: 0,
                    total_value_with_taxes: 0,
                    manufacturing_date: '',
                    expiry_date: '',
                    quantity_received: 0,
                    quantity_accepted: 0,
                    unit_cost: 0,
                    selling_price: 0,
                    max_selling_price: 0,
                    promotional_campaign_id: '',
                    promotional_price: 0,
                    promotional_discount_percent: 0,
                    selling_strategy: 'fifo',
                    priority_order: null,
                    must_sell_before: '',
                    batch_number: '',
                    lot_number: '',
                    storage_location: '',
                    quality_status: 'approved',
                    total: 0
                }],

                addItem() {
                    const newIndex = this.items.length;
                    this.items.push({
                        product_id: '',
                        uom_id: defaultUomId || '',
                        qty_cases: 0,
                        unit_price_per_case: 0,
                        extended_value: 0,
                        discount_value: 0,
                        fmr_allowance: 0,
                        discounted_value_before_tax: 0,
                        excise_duty: 0,
                        sales_tax_value: 0,
                        sales_tax_manually_edited: false,
                        advance_income_tax: 0,
                        total_value_with_taxes: 0,
                        manufacturing_date: '',
                        expiry_date: '',
                        quantity_received: 0,
                        quantity_accepted: 0,
                        unit_cost: 0,
                        selling_price: 0,
                        max_selling_price: 0,
                        promotional_campaign_id: '',
                        promotional_price: 0,
                        promotional_discount_percent: 0,
                        selling_strategy: 'fifo',
                        priority_order: null,
                        must_sell_before: '',
                        batch_number: '',
                        lot_number: '',
                        storage_location: '',
                        quality_status: 'approved',
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
                    this.items[index].selling_strategy = 'fifo';
                    this.items[index].priority_order = null;
                    this.items[index].must_sell_before = '';
                    this.items[index].manufacturing_date = '';
                    this.items[index].expiry_date = '';
                    this.items[index].batch_number = '';
                    this.items[index].lot_number = '';
                    this.items[index].storage_location = '';
                    this.items[index].quality_status = 'approved';
                },

                calculateFromCases(index) {
                    const item = this.items[index];
                    const productId = item.product_id;
                    const qtyCases = parseFloat(item.qty_cases) || 0;
                    const unitPrice = parseFloat(item.unit_price_per_case) || 0;
                    
                    // Calculate Extended Value (Qty Cases × Unit Price per Case)
                    item.extended_value = qtyCases * unitPrice;
                    
                    // Trigger tax calculations
                    this.calculateTaxes(index);
                    
                    if (!productId || qtyCases <= 0) {
                        item.quantity_received = 0;
                        this.updateTotal(index);
                        return;
                    }

                    // Find the product in allProducts array
                    const product = allProducts.find(p => p.id == productId);
                    if (product && product.uom_conversion_factor) {
                        const conversionFactor = parseFloat(product.uom_conversion_factor) || 1;
                        item.quantity_received = qtyCases * conversionFactor;
                        
                        // Qty Accepted always equals Qty Received
                        item.quantity_accepted = item.quantity_received;
                    }
                },

                calculateTaxes(index) {
                    const item = this.items[index];
                    const extendedValue = parseFloat(item.extended_value) || 0;
                    const discountValue = parseFloat(item.discount_value) || 0;
                    const fmrAllowance = parseFloat(item.fmr_allowance) || 0;
                    const exciseDuty = parseFloat(item.excise_duty) || 0;
                    let salesTaxValue = parseFloat(item.sales_tax_value) || 0;
                    const advanceIncomeTax = parseFloat(item.advance_income_tax) || 0;
                    
                    // Discounted Value Before Sales Tax = Extended Value - Discount Value - FMR Allowance
                    item.discounted_value_before_tax = parseFloat((extendedValue - discountValue - fmrAllowance).toFixed(2));
                    
                    // Auto-calculate Sales Tax Value based on supplier's tax rate
                    // Only auto-calculate if sales_tax_value is 0 or not manually edited
                    if (item.discounted_value_before_tax > 0 && !item.sales_tax_manually_edited) {
                        salesTaxValue = parseFloat(((item.discounted_value_before_tax * this.supplierSalesTaxRate) / 100).toFixed(2));
                        item.sales_tax_value = salesTaxValue;
                    }
                    
                    // Total Value with Taxes = Discounted Value Before Tax + Excise Duty + Sales Tax + Advance Income Tax
                    item.total_value_with_taxes = parseFloat((item.discounted_value_before_tax + exciseDuty + salesTaxValue + advanceIncomeTax).toFixed(2));
                    
                    // Calculate Unit Cost: (Total Value with Taxes + FMR Allowance) / Qty Received
                    const qtyReceived = parseFloat(item.quantity_received) || 0;
                    if (qtyReceived > 0) {
                        item.unit_cost = parseFloat(((item.total_value_with_taxes + fmrAllowance) / qtyReceived).toFixed(2));
                    }
                    
                    // Qty Accepted always equals Qty Received
                    item.quantity_accepted = item.quantity_received;
                },

                removeItem(index) {
                    if (this.items.length > 1) {
                        // Destroy Select2 before removing
                        if ($(`#product_${index}`).data('select2')) {
                            $(`#product_${index}`).select2('destroy');
                        }
                        this.items.splice(index, 1);
                    }
                },

                updateProduct(index) {
                    const productId = this.items[index].product_id;
                    const product = allProducts.find(p => p.id == productId);
                    if (product) {
                        this.items[index].selling_price = product.unit_sell_price || 0;
                        this.items[index].max_selling_price = product.unit_sell_price || 0;
                        
                        // If qty_cases is already entered, recalculate quantity_received
                        if (this.items[index].qty_cases > 0) {
                            this.calculateFromCases(index);
                        }
                    }
                },

                onSalesTaxManualEdit(index) {
                    // Mark that sales tax was manually edited
                    this.items[index].sales_tax_manually_edited = true;
                    // Recalculate total with taxes
                    this.calculateTaxes(index);
                },

                validateSellingPrice(index) {
                    const item = this.items[index];
                    const sellingPrice = parseFloat(item.selling_price) || 0;
                    const maxPrice = parseFloat(item.max_selling_price) || 0;
                    
                    // If selling price exceeds maximum, show alert and cap it at maximum
                    if (maxPrice > 0 && sellingPrice > maxPrice) {
                        alert(`Selling price cannot exceed the maximum price of ${this.formatCurrency(maxPrice)}\n\nThe selling price has been set to the maximum allowed value.`);
                        item.selling_price = maxPrice;
                    }
                },

                get grandTotal() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.total_value_with_taxes) || 0), 0);
                },

                formatCurrency(value) {
                    return '₨ ' + parseFloat(value || 0).toLocaleString('en-PK', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                },

                formatNumber(value) {
                    return parseFloat(value || 0).toLocaleString('en-PK', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
        }

        async function initializeProductSelect2(index) {
            const supplierId = $('#supplier_id').val();
            const $select = $(`#product_${index}`);

            // If supplier selected and products not loaded, load them first
            if (supplierId && allProducts.length === 0) {
                await loadProductsBySupplier(supplierId);
            }

            $select.select2({
                placeholder: 'Select Product',
                allowClear: false,
                width: '100%',
                data: getFilteredProducts(supplierId)
            });
            
            // Set initial value from Alpine.js data if it exists
            const alpineComponent = Alpine.$data($select.closest('form')[0]);
            if (alpineComponent && alpineComponent.items && alpineComponent.items[index] && alpineComponent.items[index].product_id) {
                $select.val(alpineComponent.items[index].product_id).trigger('change.select2');
            }

            // Sync with Alpine.js and trigger calculation when product changes
            $select.on('change', function() {
                const productId = $(this).val();
                const alpineComponent = Alpine.$data(this.closest('form'));
                if (alpineComponent && alpineComponent.items && alpineComponent.items[index]) {
                    alpineComponent.items[index].product_id = productId;
                    alpineComponent.updateProduct(index);
                }
            });
        }

        function getFilteredProducts(supplierId) {
            // Products are already filtered by supplier from AJAX call
            return allProducts.map(p => ({
                id: p.id,
                text: `${p.product_code} - ${p.product_name}`
            }));
        }

        async function loadProductsBySupplier(supplierId) {
            if (!supplierId) {
                allProducts = [];
                return;
            }

            try {
                const response = await fetch(`/api/suppliers/${supplierId}/products`);
                if (!response.ok) {
                    throw new Error('Failed to load products');
                }
                allProducts = await response.json();
            } catch (error) {
                console.error('Error loading products:', error);
                alert('Failed to load products. Please try again.');
                allProducts = [];
            }
        }

        async function refreshAllProductSelects() {
            const supplierId = $('#supplier_id').val();
            
            // Show loading indicator
            $('.product-select').prop('disabled', true);
            
            // Load products from server
            await loadProductsBySupplier(supplierId);
            
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
                    allowClear: false,
                    width: '100%'
                });

                // Restore previous value if it exists in new filtered list
                if (currentValue && productData.find(p => p.id == currentValue)) {
                    $this.val(currentValue).trigger('change.select2');
                }

                // Re-bind change event with Alpine.js sync
                $this.on('change', function() {
                    const productId = $(this).val();
                    const alpineComponent = Alpine.$data(this.closest('form'));
                    const itemIndex = parseInt($(this).attr('id').split('_')[1]);
                    if (alpineComponent && alpineComponent.items && alpineComponent.items[itemIndex]) {
                        alpineComponent.items[itemIndex].product_id = productId;
                        alpineComponent.updateProduct(itemIndex);
                    }
                });
            });
            
            // Re-enable product selects after loading
            $('.product-select').prop('disabled', false);
        }

        // Wait for Select2 to be fully loaded
        function initializeGRNForm() {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                setTimeout(initializeGRNForm, 100);
                return;
            }

            $(document).ready(function() {


                // Initialize product selects for existing items (including old input)
                $('.product-select').each(function(index) {
                    initializeProductSelect2(index);
                });

                // When supplier changes, refresh all product dropdowns
                $('#supplier_id').on('change', function() {
                    const supplierId = $(this).val();
                    const supplier = allSuppliers.find(s => s.id == supplierId);
                    
                    // Update Alpine.js component's sales tax rate
                    if (supplier && Alpine && Alpine.$data) {
                        const grnFormData = Alpine.$data(document.querySelector('[x-data="grnForm()"]'));
                        if (grnFormData) {
                            grnFormData.supplierSalesTaxRate = parseFloat(supplier.sales_tax) || 18.00;
                            
                            // Recalculate sales tax for all items that weren't manually edited
                            grnFormData.items.forEach((item, index) => {
                                if (!item.sales_tax_manually_edited && item.discounted_value_before_tax > 0) {
                                    grnFormData.calculateTaxes(index);
                                }
                            });
                        }
                    }
                    
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