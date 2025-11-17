<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Edit Goods Issue: {{ $goodsIssue->issue_number }}
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            <a href="{{ route('goods-issues.index') }}"
                class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
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
            <x-status-message class="mb-4 shadow-md" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('goods-issues.update', $goodsIssue) }}" id="goodsIssueForm">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <x-label for="issue_date" value="Issue Date" class="required" />
                                <x-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full"
                                    :value="old('issue_date', $goodsIssue->issue_date)" required />
                                <x-input-error for="issue_date" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="warehouse_id" value="Warehouse" class="required" />
                                <select id="warehouse_id" name="warehouse_id"
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    required>
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('warehouse_id', $goodsIssue->
                                        warehouse_id)==$warehouse->id ?
                                        'selected' : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                                    @endforeach
                                </select>
                                <x-input-error for="warehouse_id" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="vehicle_id" value="Vehicle" class="required" />
                                <select id="vehicle_id" name="vehicle_id"
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    required>
                                    <option value="">Select Vehicle</option>
                                    @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}" {{ old('vehicle_id', $goodsIssue->
                                        vehicle_id)==$vehicle->id ? 'selected' :
                                        '' }}>
                                        {{ $vehicle->vehicle_number }} ({{ $vehicle->vehicle_type }})
                                    </option>
                                    @endforeach
                                </select>
                                <x-input-error for="vehicle_id" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="employee_id" value="Salesman" class="required" />
                                <select id="employee_id" name="employee_id"
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    required>
                                    <option value="">Select Salesman</option>
                                    @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id', $goodsIssue->
                                        employee_id)==$employee->id ?
                                        'selected' : '' }}>
                                        {{ $employee->name }} ({{ $employee->employee_code }})
                                    </option>
                                    @endforeach
                                </select>
                                <x-input-error for="employee_id" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-label for="notes" value="Notes" />
                                <textarea id="notes" name="notes" rows="2"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('notes', $goodsIssue->notes) }}</textarea>
                                <x-input-error for="notes" class="mt-2" />
                            </div>
                        </div>

                        <hr class="my-6 border-gray-200">

                        <h3 class="text-lg font-semibold mb-4">Products to Issue</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="itemsTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            Product</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            Qty Available</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            Quantity</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">UOM
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            Selling
                                            Price</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            Total</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="itemsBody">
                                    <!-- Items will be added here -->
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            <button type="button" onclick="addItem()"
                                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                Add Product
                            </button>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button class="ml-4">
                                Create Goods Issue
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let itemIndex = 0;
        const products = @json($products);
        const uoms = @json($uoms);
        let productBatches = {}; // Store batch data for each product

        function addItem() {
            const warehouseId = document.getElementById('warehouse_id').value;
            if (!warehouseId) {
                alert('Please select a warehouse first');
                return;
            }

            const tbody = document.getElementById('itemsBody');
            const row = document.createElement('tr');
            row.setAttribute('data-index', itemIndex);
            row.setAttribute('data-product-id', ''); // Track product ID to prevent duplicates
            row.innerHTML = `
                <td class="px-3 py-2">
                    <select name="items[${itemIndex}][product_id]" 
                        id="product_${itemIndex}"
                        class="product-select border-gray-300 rounded-md shadow-sm w-full text-sm" 
                        required
                        onchange="onProductChange(${itemIndex})">
                        <option value="">Select Product</option>
                        ${products.map(p => `<option value="${p.id}" data-uom-id="${p.uom_id}">${p.product_code} - ${p.product_name}</option>`).join('')}
                    </select>
                    <div id="batch_info_${itemIndex}" class="text-xs mt-1 text-gray-600"></div>
                </td>
                <td class="px-3 py-2">
                    <input type="text" 
                        id="available_qty_${itemIndex}"
                        readonly 
                        class="border-gray-300 rounded-md shadow-sm w-full text-sm bg-gray-100 text-center font-semibold" 
                        value="0.00" />
                </td>
                <td class="px-3 py-2">
                    <input type="number" 
                        name="items[${itemIndex}][quantity_issued]" 
                        id="quantity_${itemIndex}"
                        step="0.001" 
                        min="0.001"
                        class="border-gray-300 rounded-md shadow-sm w-full text-sm" 
                        required
                        oninput="updatePriceBasedOnQuantity(${itemIndex})"
                        onchange="calculateTotal(${itemIndex})" />
                </td>
                <td class="px-3 py-2">
                    <select name="items[${itemIndex}][uom_id]" 
                        id="uom_${itemIndex}"
                        class="border-gray-300 rounded-md shadow-sm w-full text-sm" 
                        required>
                        <option value="">Select UOM</option>
                        ${uoms.map(u => `<option value="${u.id}">${u.uom_name}</option>`).join('')}
                    </select>
                </td>
                <td class="px-3 py-2">
                    <div id="price_breakdown_${itemIndex}" class="text-xs text-gray-700"></div>
                    <input type="hidden" 
                        name="items[${itemIndex}][unit_cost]" 
                        id="unit_cost_${itemIndex}"
                        value="0" />
                </td>
                <td class="px-3 py-2">
                    <input type="text" 
                        id="total_${itemIndex}"
                        readonly 
                        class="border-gray-300 rounded-md shadow-sm w-full text-sm bg-gray-100 text-right font-semibold" />
                </td>
                <td class="px-3 py-2 text-center">
                    <button type="button" onclick="removeItem(this)" class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150" title="Remove">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </td>
            `;
            tbody.appendChild(row);

            // Initialize Select2 for the product dropdown
            $(`#product_${itemIndex}`).select2({
                placeholder: 'Select Product',
                allowClear: true,
                width: '100%'
            });

            itemIndex++;
        }

        function removeItem(button) {
            const row = button.closest('tr');
            const productId = row.getAttribute('data-product-id');
            
            // Clear batch data
            if (productId && productBatches[productId]) {
                delete productBatches[productId];
            }
            
            row.remove();
        }

        async function onProductChange(index) {
            const productSelect = document.getElementById(`product_${index}`);
            const productId = productSelect.value;
            const warehouseId = document.getElementById('warehouse_id').value;
            const currentRow = document.querySelector(`tr[data-index="${index}"]`);

            if (!productId || !warehouseId) {
                return;
            }

            // Check if product already exists in other rows
            const existingRows = Array.from(document.querySelectorAll('#itemsBody tr'));
            const isDuplicate = existingRows.some(row => {
                return row !== currentRow && row.getAttribute('data-product-id') === productId;
            });

            if (isDuplicate) {
                alert('This product is already added. Please adjust the quantity in the existing row.');
                productSelect.value = '';
                $(`#product_${index}`).val('').trigger('change'); // Clear Select2
                return;
            }

            try {
                const response = await fetch(`/api/warehouses/${warehouseId}/products/${productId}/stock`);
                const data = await response.json();

                // Store batch data
                productBatches[productId] = data.batches || [];
                currentRow.setAttribute('data-product-id', productId);

                // Set available quantity
                document.getElementById(`available_qty_${index}`).value = parseFloat(data.available_quantity || 0).toFixed(2);

                // Set UOM from product's stock UOM
                if (data.stock_uom_id) {
                    document.getElementById(`uom_${index}`).value = data.stock_uom_id;
                }

                // Show batch breakdown if multiple prices exist
                displayBatchInfo(index, productId, data.batches, data.has_multiple_prices);

                // Clear quantity and price fields
                document.getElementById(`quantity_${index}`).value = '';
                document.getElementById(`total_${index}`).value = '';
                document.getElementById(`price_breakdown_${index}`).innerHTML = '';
                document.getElementById(`unit_cost_${index}`).value = '0';

            } catch (error) {
                console.error('Error fetching product stock:', error);
                alert('Error loading product stock data');
            }
        }

        function displayBatchInfo(index, productId, batches, hasMultiplePrices) {
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

        function updatePriceBasedOnQuantity(index) {
            const row = document.querySelector(`tr[data-index="${index}"]`);
            const productId = row.getAttribute('data-product-id');
            const quantity = parseFloat(document.getElementById(`quantity_${index}`).value) || 0;
            
            if (!productId || !productBatches[productId]) {
                document.getElementById(`price_breakdown_${index}`).innerHTML = '';
                document.getElementById(`total_${index}`).value = '';
                return;
            }

            if (quantity === 0) {
                document.getElementById(`price_breakdown_${index}`).innerHTML = '<span class="text-gray-400">Enter quantity</span>';
                document.getElementById(`total_${index}`).value = '';
                return;
            }

            const batches = productBatches[productId];
            let remainingQty = quantity;
            let totalValue = 0;
            let batchesUsed = [];

            // Calculate which batches will be used and total value
            for (const batch of batches) {
                if (remainingQty <= 0) break;

                const qtyFromBatch = Math.min(remainingQty, batch.quantity);
                const batchValue = qtyFromBatch * batch.selling_price;
                totalValue += batchValue;
                remainingQty -= qtyFromBatch;

                if (qtyFromBatch > 0) {
                    batchesUsed.push({
                        code: batch.batch_code,
                        qty: qtyFromBatch,
                        price: batch.selling_price,
                        value: batchValue,
                        is_promotional: batch.is_promotional
                    });
                }
            }

            // Check if requested quantity exceeds available stock
            if (remainingQty > 0) {
                document.getElementById(`price_breakdown_${index}`).innerHTML = `
                    <span class="text-red-600 font-semibold">⚠️ Insufficient stock!</span><br>
                    <span class="text-sm">Available: ${quantity - remainingQty}, Short: ${remainingQty.toFixed(0)}</span>
                `;
                document.getElementById(`total_${index}`).value = '';
                return;
            }
            
            // Display price breakdown for each batch
            const priceBreakdownDiv = document.getElementById(`price_breakdown_${index}`);
            if (batchesUsed.length === 1) {
                const b = batchesUsed[0];
                const promo = b.is_promotional ? ' 🎁' : '';
                priceBreakdownDiv.innerHTML = `
                    <div class="font-semibold text-green-600">${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)}${promo}</div>
                `;
            } else {
                let html = '<div class="space-y-1">';
                batchesUsed.forEach((b, idx) => {
                    const promo = b.is_promotional ? ' 🎁' : '';
                    html += `
                        <div class="flex justify-between">
                            <span class="text-gray-600">${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)}${promo}</span>
                            <span class="font-semibold">= ₨${b.value.toFixed(2)}</span>
                        </div>
                    `;
                });
                html += '</div>';
                priceBreakdownDiv.innerHTML = html;
            }
            
            // Update batch info to show which batches are being used
            const batchInfoDiv = document.getElementById(`batch_info_${index}`);
            if (batchesUsed.length > 1) {
                let info = '<div class="text-blue-600 font-semibold mt-1">📦 Using batches:</div>';
                batchesUsed.forEach(b => {
                    const promo = b.is_promotional ? ' 🎁' : '';
                    info += `<div class="ml-2 text-xs">${b.code}: ${b.qty.toFixed(0)} units${promo}</div>`;
                });
                batchInfoDiv.innerHTML = info;
            } else if (batchesUsed.length === 1) {
                const b = batchesUsed[0];
                const promo = b.is_promotional ? ' 🎁' : '';
                batchInfoDiv.innerHTML = `<div class="text-green-600 text-xs">✓ Batch: ${b.code}${promo}</div>`;
            }

            // Set total value (sum of all batch values)
            document.getElementById(`total_${index}`).value = totalValue.toFixed(2);
            
            // Store average price for backend (for record-keeping)
            const avgPrice = quantity > 0 ? totalValue / quantity : 0;
            document.getElementById(`unit_cost_${index}`).value = avgPrice.toFixed(2);
        }

        function calculateTotal(index) {
            // Total is now calculated in updatePriceBasedOnQuantity
            // This function kept for compatibility
            updatePriceBasedOnQuantity(index);
        }

        // Warehouse change handler - clear all items
        document.getElementById('warehouse_id').addEventListener('change', function() {
            const tbody = document.getElementById('itemsBody');
            if (tbody.children.length > 0) {
                if (confirm('Changing warehouse will clear all items. Continue?')) {
                    tbody.innerHTML = '';
                    itemIndex = 0;
                } else {
                    // Revert warehouse selection
                    this.value = this.getAttribute('data-previous-value') || '';
                }
            }
            this.setAttribute('data-previous-value', this.value);
        });

        // Add first item on page load if warehouse is selected
        document.addEventListener('DOMContentLoaded', function() {
            const warehouseId = document.getElementById('warehouse_id').value;
            
            @if(isset($goodsIssue) && $goodsIssue->items->count() > 0)
            // Load existing items for editing
            let loadDelay = 0;
            @foreach($goodsIssue->items as $item)
            setTimeout(() => {
                const savedIndex = itemIndex;
                addItem();
                
                // Wait for Select2 to initialize
                setTimeout(() => {
                    // Set product value and trigger change
                    const productSelect = $(`#product_${savedIndex}`);
                    productSelect.val({{ $item->product_id }}).trigger('change');
                    
                    // Listen for the AJAX call to complete by polling the available quantity field
                    const checkStockLoaded = setInterval(() => {
                        const availQty = document.getElementById(`available_qty_${savedIndex}`).value;
                        
                        // If stock data has loaded (value changed from initial "0.00")
                        if (availQty !== '0.00' || productBatches[{{ $item->product_id }}]) {
                            clearInterval(checkStockLoaded);
                            
                            // Now set quantity and UOM
                            document.getElementById(`quantity_${savedIndex}`).value = {{ $item->quantity_issued }};
                            document.getElementById(`uom_${savedIndex}`).value = {{ $item->uom_id }};
                            
                            // Trigger quantity change to recalculate price breakdown
                            setTimeout(() => {
                                updatePriceBasedOnQuantity(savedIndex);
                            }, 200);
                        }
                    }, 200); // Check every 200ms
                    
                    // Failsafe: force set after 3 seconds even if stock didn't load
                    setTimeout(() => {
                        clearInterval(checkStockLoaded);
                        if (!document.getElementById(`quantity_${savedIndex}`).value) {
                            document.getElementById(`quantity_${savedIndex}`).value = {{ $item->quantity_issued }};
                            document.getElementById(`uom_${savedIndex}`).value = {{ $item->uom_id }};
                            updatePriceBasedOnQuantity(savedIndex);
                        }
                    }, 3000);
                }, 300);
            }, loadDelay);
            loadDelay += 200;
            @endforeach
            @else
            // Add first empty item
            if (warehouseId) {
                addItem();
            }
            @endif
        });
    </script>
</x-app-layout>