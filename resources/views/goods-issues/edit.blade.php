<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Edit Goods Issue: {{ $goodsIssue->issue_number }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('goods-issues.show', $goodsIssue) }}"
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

                    <form method="POST" action="{{ route('goods-issues.update', $goodsIssue) }}" id="goodsIssueForm"
                        x-data="goodsIssueForm()">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <x-label for="issue_date" value="Issue Date *" />
                                <x-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full"
                                    :value="old('issue_date', $goodsIssue->issue_date ? $goodsIssue->issue_date->format('Y-m-d') : '')"
                                    required />
                            </div>

                            <div>
                                <x-label for="warehouse_id" value="Warehouse *" />
                                <select id="warehouse_id" name="warehouse_id" required
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('warehouse_id', $goodsIssue->
                                        warehouse_id)==$warehouse->id ?
                                        'selected' : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="vehicle_id" value="Vehicle *" />
                                <select id="vehicle_id" name="vehicle_id" required
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Vehicle</option>
                                    @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}" {{ old('vehicle_id', $goodsIssue->
                                        vehicle_id)==$vehicle->id ? 'selected' :
                                        '' }}>
                                        {{ $vehicle->vehicle_number }} ({{ $vehicle->vehicle_type }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="employee_id" value="Salesman *" />
                                <select id="employee_id" name="employee_id" required
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Salesman</option>
                                    @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id', $goodsIssue->
                                        employee_id)==$employee->id ?
                                        'selected' : '' }}>
                                        {{ $employee->name }} ({{ $employee->employee_code }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="md:col-span-4">
                                <x-label for="notes" value="Notes" />
                                <textarea id="notes" name="notes" rows="2"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('notes', $goodsIssue->notes) }}</textarea>
                            </div>
                        </div>

                        <hr class="my-6 border-gray-200">

                        <x-form-table title="Products to Issue" :headers="[
                            ['label' => 'Product', 'align' => 'text-left', 'width' => '350px'],
                            ['label' => 'Qty<br>Available', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Quantity<br>Issued', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'UOM', 'align' => 'text-center', 'width' => '120px'],
                            ['label' => 'Price<br>Breakdown', 'align' => 'text-left', 'width' => '250px'],
                            ['label' => 'Total<br>Value', 'align' => 'text-right', 'width' => '140px'],
                            ['label' => 'Action', 'align' => 'text-center', 'width' => '100px'],
                        ]">
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="align-top">
                                        <td class="px-2 py-2 align-middle">
                                            <select :id="`product_${index}`" :name="`items[${index}][product_id]`"
                                                required
                                                class="product-select select2 border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                <option value="">Select Product</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <input type="text" :id="`available_qty_${index}`" readonly
                                                x-model="item.available_qty"
                                                :class="parseFloat(item.available_qty) <= 0 ? 'border-red-300 bg-red-50' : 'border-gray-300 bg-gray-100'"
                                                class="rounded-md shadow-sm text-sm w-full text-center font-semibold">
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <input type="number" :name="`items[${index}][quantity_issued]`"
                                                x-model="item.quantity_issued"
                                                @input="updatePriceBasedOnQuantity(index)" step="0.001"
                                                :max="item.available_qty" min="0.001"
                                                :disabled="parseFloat(item.available_qty) <= 0"
                                                :required="parseFloat(item.available_qty) > 0"
                                                :class="parseFloat(item.available_qty) <= 0 ? 'bg-gray-200 cursor-not-allowed' : 'bg-white'"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <select :name="`items[${index}][uom_id]`" x-model="item.uom_id"
                                                :disabled="parseFloat(item.available_qty) <= 0"
                                                :required="parseFloat(item.available_qty) > 0"
                                                :class="parseFloat(item.available_qty) <= 0 ? 'bg-gray-200 cursor-not-allowed' : 'bg-white'"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                <option value="">Select UOM</option>
                                                @foreach ($uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->uom_name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <div :id="`batch_info_${index}`" class="text-xs text-gray-600 max-w-xs">
                                            </div>
                                            <div :id="`price_breakdown_${index}`"
                                                class="text-xs text-gray-700 max-w-xs"></div>
                                            <input type="hidden" :name="`items[${index}][unit_cost]`"
                                                x-model="item.unit_cost">
                                            <input type="hidden" :name="`items[${index}][selling_price]`"
                                                x-model="item.selling_price">
                                        </td>
                                        <td class="px-2 py-2 text-right text-sm font-semibold align-middle"
                                            x-text="formatNumber(item.total_value)"></td>
                                        <td class="px-2 py-2 text-center align-middle">
                                            <button type="button" @click="removeItem(index)"
                                                class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                                :class="items.length === 1 ? 'opacity-40 cursor-not-allowed hover:bg-transparent hover:text-red-600 pointer-events-none' : ''"
                                                :disabled="items.length === 1" title="Remove Line">
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
                                    <td class="px-2 py-2 text-right" colspan="2">Totals:</td>
                                    <td class="px-2 py-2 text-right"
                                        x-text="formatNumber(items.reduce((sum, item) => sum + (parseFloat(item.quantity_issued) || 0), 0))">
                                    </td>
                                    <td class="px-2 py-2"></td>
                                    <td class="px-2 py-2"></td>
                                    <td class="px-2 py-2 text-right font-bold text-lg"
                                        x-text="formatNumber(grandTotal)">
                                    </td>
                                    <td class="px-2 py-2"></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="px-2 py-2">
                                        <button type="button" @click="addItem()"
                                            class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Product
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </x-form-table>

                        <div class="flex items-center justify-end mt-6">
                            <x-button type="button" @click="validateAndSubmit()"
                                class="!bg-green-600 hover:!bg-green-700 focus:!bg-green-700 active:!bg-green-800 focus:!ring-green-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                Update Goods Issue
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let allProducts = []; // All active products
        let productBatches = {}; // Batch data per product
        const oldItems = @json(old('items', []));
        const existingItems = @json($goodsIssue->items);

        function goodsIssueForm() {
            return {
                items: oldItems.length > 0 ? oldItems.map(item => ({
                    product_id: item.product_id || '',
                    uom_id: item.uom_id || '',
                    quantity_issued: parseFloat(item.quantity_issued) || 0,
                    unit_cost: parseFloat(item.unit_cost) || 0,
                    selling_price: parseFloat(item.selling_price) || 0,
                    total_value: parseFloat(item.total_value) || 0,
                    available_qty: 0,
                })) : existingItems.map(item => ({
                    product_id: item.product_id || '',
                    uom_id: item.uom_id || '',
                    quantity_issued: parseFloat(item.quantity_issued) || 0,
                    unit_cost: parseFloat(item.unit_cost) || 0,
                    selling_price: parseFloat(item.selling_price) || 0,
                    total_value: parseFloat(item.total_value) || 0,
                    available_qty: 0,
                })),

                validateAndSubmit() {
                    // Filter out items with 0 or invalid quantity before submitting
                    const validItems = this.items.filter(item => {
                        const qty = parseFloat(item.quantity_issued) || 0;
                        return qty > 0 && item.product_id;
                    });

                    if (validItems.length === 0) {
                        alert('⚠️ Cannot update!\n\nNo valid items to update. Please add at least one product with a valid quantity.');
                        return false;
                    }

                    // Update items to only include valid ones
                    this.items = validItems;

                    // Submit the form after a short delay to allow Alpine to update
                    this.$nextTick(() => {
                        document.getElementById('goodsIssueForm').submit();
                    });
                },

                addItem() {
                    const warehouseId = document.getElementById('warehouse_id').value;
                    if (!warehouseId) {
                        alert('Please select a warehouse first');
                        return;
                    }

                    const newIndex = this.items.length;
                    this.items.push({
                        product_id: '',
                        uom_id: '',
                        quantity_issued: 0,
                        unit_cost: 0,
                        selling_price: 0,
                        total_value: 0,
                        available_qty: 0,
                    });

                    // Initialize Select2 for new product dropdown
                    this.$nextTick(() => {
                        initializeProductSelect2(newIndex);
                    });
                },

                removeItem(index) {
                    if (this.items.length > 1) {
                        const productId = this.items[index].product_id;
                        
                        // Clear batch data
                        if (productId && productBatches[productId]) {
                            delete productBatches[productId];
                        }
                        
                        this.items.splice(index, 1);
                        
                        // Reinitialize all Select2 after removal
                        this.$nextTick(() => {
                            $('.product-select').each(function() {
                                if ($(this).data('select2')) {
                                    $(this).select2('destroy');
                                }
                            });
                            
                            $('.product-select').each(function(idx) {
                                initializeProductSelect2(idx);
                            });
                        });
                    }
                },

                updatePriceBasedOnQuantity(index) {
                    const item = this.items[index];
                    const productId = item.product_id;
                    const quantity = parseFloat(item.quantity_issued) || 0;
                    const availableQty = parseFloat(item.available_qty) || 0;
                    
                    if (!productId || !productBatches[productId]) {
                        document.getElementById(`price_breakdown_${index}`).innerHTML = '';
                        document.getElementById(`batch_info_${index}`).innerHTML = '';
                        item.total_value = 0;
                        return;
                    }

                    if (quantity === 0) {
                        document.getElementById(`price_breakdown_${index}`).innerHTML = '<span class="text-gray-400">Enter quantity</span>';
                        document.getElementById(`batch_info_${index}`).innerHTML = '';
                        item.total_value = 0;
                        return;
                    }

                    // Validate quantity against available stock
                    if (quantity > availableQty) {
                        document.getElementById(`batch_info_${index}`).innerHTML = `
                            <div class="text-red-600 font-bold">⚠️ ERROR: Quantity exceeds available stock!</div>
                        `;
                        document.getElementById(`price_breakdown_${index}`).innerHTML = `
                            <div class="text-red-600 font-semibold">Entered: ${quantity.toFixed(0)} units</div>
                            <div class="text-green-600 font-semibold">Available: ${availableQty.toFixed(0)} units</div>
                            <div class="text-red-600 font-bold border-t border-red-300 pt-1 mt-1">Excess: ${(quantity - availableQty).toFixed(0)} units</div>
                        `;
                        item.total_value = 0;
                        item.unit_cost = 0;
                        
                        // Reset quantity to available max
                        setTimeout(() => {
                            alert(`⚠️ INVALID QUANTITY!\n\nYou entered: ${quantity.toFixed(0)} units\nAvailable stock: ${availableQty.toFixed(0)} units\n\nQuantity has been reset to maximum available.`);
                            item.quantity_issued = availableQty;
                            this.updatePriceBasedOnQuantity(index);
                        }, 100);
                        return;
                    }

                    const batches = productBatches[productId];
                    let remainingQty = quantity;
                    let totalValue = 0;
                    let totalCost = 0;
                    let batchesUsed = [];

                    // Calculate which batches will be used
                    for (const batch of batches) {
                        if (remainingQty <= 0) break;

                        const qtyFromBatch = Math.min(remainingQty, batch.quantity);
                        const batchValue = qtyFromBatch * batch.selling_price;
                        const batchCost = qtyFromBatch * batch.unit_cost;
                        totalValue += batchValue;
                        totalCost += batchCost;
                        remainingQty -= qtyFromBatch;

                        if (qtyFromBatch > 0) {
                            batchesUsed.push({
                                code: batch.batch_code,
                                qty: qtyFromBatch,
                                price: batch.selling_price,
                                cost: batch.unit_cost,
                                value: batchValue,
                                is_promotional: batch.is_promotional
                            });
                        }
                    }

                    // Check if insufficient stock (shouldn't happen due to above check, but keep as fallback)
                    if (remainingQty > 0) {
                        document.getElementById(`batch_info_${index}`).innerHTML = `
                            <div class="text-red-600 font-bold">⚠️ Insufficient stock!</div>
                        `;
                        document.getElementById(`price_breakdown_${index}`).innerHTML = `
                            <div class="text-sm">Available: ${(quantity - remainingQty).toFixed(0)}</div>
                            <div class="text-sm text-red-600">Short: ${remainingQty.toFixed(0)}</div>
                        `;
                        item.total_value = 0;
                        return;
                    }
                    
                    // Update batch info - show detailed breakdown at the top
                    const batchInfoDiv = document.getElementById(`batch_info_${index}`);
                    if (batchesUsed.length > 0) {
                        let info = '<div class="text-blue-600 font-semibold mb-1">📦 Issuing from batches:</div>';
                        batchesUsed.forEach((b, bIndex) => {
                            const promo = b.is_promotional ? ' 🎁' : '';
                            info += `<div>Batch ${bIndex + 1}: ${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)}${promo}</div>`;
                        });
                        batchInfoDiv.innerHTML = info;
                    }
                    
                    // Display price breakdown below batch info
                    const priceBreakdownDiv = document.getElementById(`price_breakdown_${index}`);
                    if (batchesUsed.length === 1) {
                        const b = batchesUsed[0];
                        priceBreakdownDiv.innerHTML = `
                            <div class="text-sm font-semibold text-green-700 mt-1">
                                ${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)} = ₨${b.value.toFixed(2)}
                            </div>
                        `;
                    } else {
                        let html = '<div class="mt-1 border-t border-gray-200 pt-1">';
                        batchesUsed.forEach((b, bIndex) => {
                            const promo = b.is_promotional ? ' 🎁' : '';
                            html += `<div class="text-sm">Batch ${bIndex + 1}: ${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)} = ₨${b.value.toFixed(2)}${promo}</div>`;
                        });
                        html += `<div class="font-bold text-green-700 border-t border-gray-300 pt-1 mt-1">Total: ₨${totalValue.toFixed(2)}</div>`;
                        html += '</div>';
                        priceBreakdownDiv.innerHTML = html;
                    }

                    // Set total value (selling) and unit_cost (purchase cost)
                    item.total_value = totalValue;
                    item.unit_cost = quantity > 0 ? totalCost / quantity : 0;
                },

                get grandTotal() {
                    return this.items.reduce((sum, item) => sum + (parseFloat(item.total_value) || 0), 0);
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
            const $select = $(`#product_${index}`);
            const alpineComponent = Alpine.$data($select.closest('form')[0]);

            // Initialize Select2
            $select.select2({
                placeholder: 'Select Product',
                allowClear: false,
                width: '100%',
                data: allProducts.map(p => ({
                    id: p.id,
                    text: `${p.product_code} - ${p.product_name}`
                }))
            });
            
            // Set initial value from Alpine.js data
            if (alpineComponent && alpineComponent.items && alpineComponent.items[index] && alpineComponent.items[index].product_id) {
                const savedQuantity = alpineComponent.items[index].quantity_issued;
                
                $select.val(alpineComponent.items[index].product_id).trigger('change.select2');
                
                // Load stock data for existing product
                const warehouseId = $('#warehouse_id').val();
                if (warehouseId && alpineComponent.items[index].product_id) {
                    await onProductChange(index, alpineComponent.items[index].product_id, warehouseId);
                    
                    // Restore the quantity after stock data loads and trigger calculation
                    if (savedQuantity > 0) {
                        alpineComponent.items[index].quantity_issued = savedQuantity;
                        // Trigger price calculation after a short delay to ensure batch data is loaded
                        setTimeout(() => {
                            alpineComponent.updatePriceBasedOnQuantity(index);
                        }, 100);
                    }
                }
            }

            // Sync with Alpine.js when product changes
            $select.on('change', async function() {
                const productId = $(this).val();
                const warehouseId = $('#warehouse_id').val();
                
                if (alpineComponent && alpineComponent.items && alpineComponent.items[index]) {
                    alpineComponent.items[index].product_id = productId;
                    
                    if (productId && warehouseId) {
                        await onProductChange(index, productId, warehouseId);
                    }
                }
            });
        }

        async function onProductChange(index, productId, warehouseId) {
            if (!productId || !warehouseId) {
                return;
            }

            const alpineComponent = Alpine.$data(document.querySelector('[x-data="goodsIssueForm()"]'));

            // Check for duplicates (ensure type consistency)
            const isDuplicate = alpineComponent.items.some((item, idx) => {
                return idx !== index && String(item.product_id) === String(productId);
            });

            if (isDuplicate) {
                alert('⚠️ DUPLICATE PRODUCT!\n\nThis product is already added to the list.\n\nPlease adjust the quantity in the existing row instead of adding it again.');
                $(`#product_${index}`).val('').trigger('change');
                alpineComponent.items[index].product_id = '';
                return;
            }

            try {
                const response = await fetch(`/api/warehouses/${warehouseId}/products/${productId}/stock`);
                const data = await response.json();

                // Store batch data
                productBatches[productId] = data.batches || [];

                // Update Alpine.js data
                alpineComponent.items[index].available_qty = parseFloat(data.available_quantity || 0).toFixed(2);
                alpineComponent.items[index].uom_id = data.stock_uom_id || '';
                
                // Set selling_price from first batch's selling_price
                if (data.batches && data.batches.length > 0) {
                    alpineComponent.items[index].selling_price = parseFloat(data.batches[0].selling_price || 0);
                } else {
                    alpineComponent.items[index].selling_price = 0;
                }

                // Show batch info
                displayBatchInfo(index, data.batches, data.has_multiple_prices);

                // Only clear quantity and price if this is a new product selection (not loading existing item)
                // If quantity_issued already has a value, preserve it (this happens when loading existing items)
                if (alpineComponent.items[index].quantity_issued === 0 || alpineComponent.items[index].quantity_issued === null || alpineComponent.items[index].quantity_issued === undefined) {
                    alpineComponent.items[index].total_value = 0;
                    alpineComponent.items[index].unit_cost = 0;
                    document.getElementById(`price_breakdown_${index}`).innerHTML = '';
                }

            } catch (error) {
                console.error('Error fetching product stock:', error);
                alert('Error loading product stock data');
            }
        }

        function displayBatchInfo(index, batches, hasMultiplePrices) {
            const batchInfoDiv = document.getElementById(`batch_info_${index}`);
            
            if (!batches || batches.length === 0) {
                batchInfoDiv.innerHTML = '';
                return;
            }

            if (hasMultiplePrices) {
                let batchHtml = '<div class="text-orange-600 font-semibold mt-1">⚠️ Multiple batch prices:</div>';
                batches.forEach((batch, idx) => {
                    const promo = batch.is_promotional ? ' 🎁' : '';
                    batchHtml += `<div class="ml-2">Batch ${idx + 1}: ${batch.quantity.toFixed(0)} @ ₨${batch.selling_price.toFixed(2)}${promo}</div>`;
                });
                batchInfoDiv.innerHTML = batchHtml;
            } else {
                batchInfoDiv.innerHTML = `<div class="text-green-600">✓ Single price: ₨${batches[0].selling_price.toFixed(2)}</div>`;
            }
        }

        // Initialize when DOM is ready
        function initializeGoodsIssueForm() {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                setTimeout(initializeGoodsIssueForm, 100);
                return;
            }

            $(document).ready(function() {
                // Load all products
                allProducts = @json($products);

                // Initialize standard Select2 dropdowns
                $('#warehouse_id, #vehicle_id, #employee_id').select2({
                    placeholder: 'Select an option',
                    allowClear: false,
                    width: '100%'
                });

                // Initialize product selects for existing items
                $('.product-select').each(function(index) {
                    initializeProductSelect2(index);
                });
            });
        }

        // Start initialization
        initializeGoodsIssueForm();
    </script>
    @endpush
</x-app-layout>