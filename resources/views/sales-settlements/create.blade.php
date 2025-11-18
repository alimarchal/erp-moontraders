<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Create Sales Settlement
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            <a href="{{ route('sales-settlements.index') }}"
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
                    <form method="POST" action="{{ route('sales-settlements.store') }}" id="settlementForm">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <x-label for="settlement_date" value="Settlement Date" class="required" />
                                <x-input id="settlement_date" name="settlement_date" type="date"
                                    class="mt-1 block w-full" :value="old('settlement_date', date('Y-m-d'))" required />
                                <x-input-error for="settlement_date" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="goods_issue_id" value="Select Goods Issue" class="required" />
                                <select id="goods_issue_id" name="goods_issue_id"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    required>
                                    <option value="">Select Goods Issue</option>
                                    @foreach ($goodsIssues as $gi)
                                    <option value="{{ $gi->id }}" {{ old('goods_issue_id')==$gi->id ? 'selected' : '' }}
                                        data-items="{{ json_encode($gi->items) }}"
                                        data-employee="{{ $gi->employee->full_name }}"
                                        data-vehicle="{{ $gi->vehicle->vehicle_number }}">
                                        {{ $gi->issue_number }} - {{ $gi->employee->full_name }} ({{
                                        $gi->issue_date->format('d M Y') }})
                                    </option>
                                    @endforeach
                                </select>
                                <x-input-error for="goods_issue_id" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <x-label for="cash_sales_amount" value="Cash Sales Amount" />
                                <x-input id="cash_sales_amount" name="cash_sales_amount" type="number" step="0.01"
                                    min="0" class="mt-1 block w-full" :value="old('cash_sales_amount', 0)" />
                                <x-input-error for="cash_sales_amount" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="cheque_sales_amount" value="Cheque Sales Amount" />
                                <x-input id="cheque_sales_amount" name="cheque_sales_amount" type="number" step="0.01"
                                    min="0" class="mt-1 block w-full" :value="old('cheque_sales_amount', 0)" />
                                <x-input-error for="cheque_sales_amount" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="credit_sales_amount" value="Credit Sales Amount" />
                                <x-input id="credit_sales_amount" name="credit_sales_amount" type="number" step="0.01"
                                    min="0" class="mt-1 block w-full" :value="old('credit_sales_amount', 0)" />
                                <x-input-error for="credit_sales_amount" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="cash_collected" value="Cash Collected" />
                                <x-input id="cash_collected" name="cash_collected" type="number" step="0.01" min="0"
                                    class="mt-1 block w-full" :value="old('cash_collected', 0)" />
                                <x-input-error for="cash_collected" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="cheques_collected" value="Cheques Collected" />
                                <x-input id="cheques_collected" name="cheques_collected" type="number" step="0.01"
                                    min="0" class="mt-1 block w-full" :value="old('cheques_collected', 0)" />
                                <x-input-error for="cheques_collected" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="expenses_claimed" value="Expenses Claimed" />
                                <x-input id="expenses_claimed" name="expenses_claimed" type="number" step="0.01" min="0"
                                    class="mt-1 block w-full" :value="old('expenses_claimed', 0)" />
                                <x-input-error for="expenses_claimed" class="mt-2" />
                            </div>
                        </div>

                        <div class="mb-6">
                            <x-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="2"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('notes') }}</textarea>
                            <x-input-error for="notes" class="mt-2" />
                        </div>

                        <hr class="my-6 border-gray-200">

                        <h3 class="text-lg font-semibold mb-4">Product-wise Settlement</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border" id="itemsTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">#
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            Product</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                            Quantity Issued</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                                            UOM</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            Batch Breakdown</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                            Total Value</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="itemsBody">
                                    <!-- Items will be loaded from selected goods issue -->
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-300" id="itemsFooter"
                                    style="display: none;">
                                    <tr>
                                        <td colspan="5" class="px-3 py-2 text-right text-lg">Grand Total:</td>
                                        <td class="px-3 py-2 text-right text-lg text-emerald-600" id="grandTotal">₨ 0.00
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <p class="text-sm text-gray-500 mt-2" id="noItemsMessage">
                            Select a Goods Issue to load product details
                        </p>

                        <hr class="my-6 border-gray-200">

                        <h3 class="text-lg font-semibold mb-4">Batch-wise Settlement (Sold/Returned/Shortage)</h3>
                        <p class="text-sm text-gray-600 mb-3">Enter quantities for each batch separately. Returns will go back to the same batch.</p>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border" id="settlementItemsTable"
                                style="display: none;">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            Product / Batch</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                            Issued</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                            Price</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase bg-green-50">
                                            Sold</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase bg-blue-50">
                                            Returned</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase bg-red-50">
                                            Shortage</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="settlementItemsBody">
                                    <!-- Settlement items will be populated here -->
                                </tbody>
                            </table>
                        </div>

                        <hr class="my-6 border-gray-200">

                        <div class="mb-6" x-data="creditSalesManager()">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Credit Sales Breakdown</h3>
                                <button type="button" @click="addCreditSale()"
                                    class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Credit Sale
                                </button>
                            </div>

                            <div class="overflow-x-auto" x-show="creditSales.length > 0">
                                <table class="min-w-full divide-y divide-gray-200 border">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Customer</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                Previous Balance</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                New Credit</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                Payment Received</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                New Balance</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Notes</th>
                                            <th
                                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                                                Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <template x-for="(sale, index) in creditSales" :key="index">
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <select :name="`credit_sales[${index}][customer_id]`" required
                                                        x-model="sale.customer_id"
                                                        @change="updateCustomerBalance(index)"
                                                        class="border-gray-300 rounded-md shadow-sm w-full text-sm">
                                                        <option value="">Select Customer</option>
                                                        @foreach($customers as $customer)
                                                        <option value="{{ $customer->id }}"
                                                            data-balance="{{ $customer->receivable_balance ?? 0 }}">
                                                            {{ $customer->customer_name }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="text" :value="formatCurrency(sale.previous_balance)"
                                                        readonly
                                                        class="border-gray-200 bg-gray-50 rounded-md shadow-sm w-32 text-sm text-right" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="number" :name="`credit_sales[${index}][sale_amount]`"
                                                        x-model="sale.sale_amount"
                                                        @input="updateCreditTotal(); calculateNewBalance(index)"
                                                        step="0.01" min="0" required
                                                        class="border-gray-300 rounded-md shadow-sm w-32 text-sm text-right" />
                                                    <input type="hidden"
                                                        :name="`credit_sales[${index}][invoice_number]`"
                                                        :value="sale.invoice_number" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="number"
                                                        :name="`credit_sales[${index}][payment_received]`"
                                                        x-model="sale.payment_received"
                                                        @input="calculateNewBalance(index)" step="0.01" min="0"
                                                        class="border-gray-300 rounded-md shadow-sm w-32 text-sm text-right"
                                                        placeholder="0.00" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="text" :value="formatCurrency(sale.new_balance)"
                                                        readonly
                                                        class="border-gray-200 bg-blue-50 rounded-md shadow-sm w-32 text-sm text-right font-semibold"
                                                        :class="sale.new_balance > 0 ? 'text-red-600' : 'text-green-600'" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="text" :name="`credit_sales[${index}][notes]`"
                                                        x-model="sale.notes"
                                                        class="border-gray-300 rounded-md shadow-sm w-full text-sm"
                                                        placeholder="Optional notes" />
                                                </td>
                                                <td class="px-3 py-2 text-center">
                                                    <button type="button" @click="removeCreditSale(index)"
                                                        class="text-red-600 hover:text-red-800">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="2" class="px-3 py-2 text-right font-semibold">Total Credit
                                                Sales:</td>
                                            <td class="px-3 py-2 text-right font-bold text-orange-600"
                                                x-text="formatCurrency(creditTotal)"></td>
                                            <td colspan="4"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <p class="text-sm text-gray-500 mt-2" x-show="creditSales.length === 0">
                                No credit sales added. Click "Add Credit Sale" to add credit sale records.
                            </p>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button class="ml-4">
                                Create Settlement
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let invoiceCounter = 1;
        
        function creditSalesManager() {
            return {
                creditSales: [],
                creditTotal: 0,

                addCreditSale() {
                    const year = new Date().getFullYear();
                    const invoiceNumber = `INV-${year}-${String(invoiceCounter++).padStart(4, '0')}`;
                    
                    this.creditSales.push({
                        customer_id: '',
                        invoice_number: invoiceNumber,
                        sale_amount: 0,
                        payment_received: 0,
                        previous_balance: 0,
                        new_balance: 0,
                        notes: ''
                    });
                },

                removeCreditSale(index) {
                    this.creditSales.splice(index, 1);
                    this.updateCreditTotal();
                },

                updateCustomerBalance(index) {
                    const selectElement = event.target;
                    const selectedOption = selectElement.options[selectElement.selectedIndex];
                    const balance = parseFloat(selectedOption.dataset.balance || 0);
                    this.creditSales[index].previous_balance = balance;
                    this.calculateNewBalance(index);
                },

                calculateNewBalance(index) {
                    const sale = this.creditSales[index];
                    const previousBalance = parseFloat(sale.previous_balance || 0);
                    const newCredit = parseFloat(sale.sale_amount || 0);
                    const paymentReceived = parseFloat(sale.payment_received || 0);
                    
                    sale.new_balance = previousBalance + newCredit - paymentReceived;
                },

                updateCreditTotal() {
                    this.creditTotal = this.creditSales.reduce((sum, sale) => {
                        return sum + (parseFloat(sale.sale_amount) || 0);
                    }, 0);
                    
                    document.getElementById('credit_sales_amount').value = this.creditTotal.toFixed(2);
                },

                formatCurrency(value) {
                    return '₨ ' + parseFloat(value || 0).toLocaleString('en-PK', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
        }

        // Function to update product-level totals from batch inputs
        function updateProductTotals(itemIndex) {
            let soldTotal = 0;
            let returnedTotal = 0;
            let shortageTotal = 0;

            // Sum up all batch quantities for this item
            document.querySelectorAll(`.batch-input[data-item-index="${itemIndex}"]`).forEach(input => {
                const value = parseFloat(input.value) || 0;
                const type = input.dataset.type;

                if (type === 'sold') soldTotal += value;
                if (type === 'returned') returnedTotal += value;
                if (type === 'shortage') shortageTotal += value;
            });

            // Update hidden fields
            document.querySelector(`.item-${itemIndex}-qty-sold`).value = soldTotal.toFixed(3);
            document.querySelector(`.item-${itemIndex}-qty-returned`).value = returnedTotal.toFixed(3);
            document.querySelector(`.item-${itemIndex}-qty-shortage`).value = shortageTotal.toFixed(3);
        }

        document.getElementById('goods_issue_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (!selectedOption.value) {
                document.getElementById('itemsBody').innerHTML = '';
                document.getElementById('settlementItemsBody').innerHTML = '';
                document.getElementById('itemsFooter').style.display = 'none';
                document.getElementById('settlementItemsTable').style.display = 'none';
                document.getElementById('noItemsMessage').style.display = 'block';
                return;
            }

            const items = JSON.parse(selectedOption.dataset.items || '[]');
            const itemsBody = document.getElementById('itemsBody');
            const settlementItemsBody = document.getElementById('settlementItemsBody');
            itemsBody.innerHTML = '';
            settlementItemsBody.innerHTML = '';
            
            let grandTotal = 0;

            items.forEach((item, index) => {
                const batchBreakdown = item.batch_breakdown || [];
                const itemTotal = item.calculated_total || item.total_value;
                grandTotal += parseFloat(itemTotal);
                
                // Calculate weighted average selling price from batch breakdown
                let avgSellingPrice = 0;
                if (batchBreakdown.length > 0) {
                    const totalQty = batchBreakdown.reduce((sum, b) => sum + parseFloat(b.quantity), 0);
                    const totalValue = batchBreakdown.reduce((sum, b) => sum + parseFloat(b.value), 0);
                    avgSellingPrice = totalQty > 0 ? (totalValue / totalQty) : parseFloat(item.unit_cost);
                } else {
                    avgSellingPrice = parseFloat(item.unit_cost);
                }
                
                // Build batch breakdown HTML
                let batchHtml = '';
                if (batchBreakdown.length === 1) {
                    const b = batchBreakdown[0];
                    batchHtml = `
                        <div class="flex items-center space-x-1">
                            <span class="font-semibold text-green-600">
                                ${parseFloat(b.quantity).toLocaleString()} × ₨${parseFloat(b.selling_price).toFixed(2)}
                            </span>
                            ${b.is_promotional ? '<span class="px-2 py-1 ml-1 text-xs font-semibold rounded bg-orange-100 text-orange-800">Promotional</span>' : ''}
                        </div>
                    `;
                } else if (batchBreakdown.length > 1) {
                    batchHtml = '<div class="space-y-1">';
                    batchBreakdown.forEach(b => {
                        batchHtml += `
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">
                                    ${parseFloat(b.quantity).toLocaleString()} × ₨${parseFloat(b.selling_price).toFixed(2)}
                                    ${b.is_promotional ? '<span title="Promotional">🎁</span>' : ''}
                                </span>
                                <span class="font-semibold">= ₨${parseFloat(b.value).toLocaleString('en-PK', {minimumFractionDigits: 2})}</span>
                            </div>
                        `;
                    });
                    batchHtml += '</div>';
                } else {
                    batchHtml = `<span class="text-gray-400 text-xs">Avg: ₨${parseFloat(item.unit_cost).toFixed(2)}</span>`;
                }

                // Add row to items display table
                const displayRow = `
                    <tr class="border-b border-gray-200 text-sm">
                        <td class="py-1 px-2 text-center">${index + 1}</td>
                        <td class="py-1 px-2">
                            <div class="font-semibold text-gray-900">${item.product.product_code}</div>
                            <div class="text-xs text-gray-500">${item.product.product_name}</div>
                        </td>
                        <td class="py-1 px-2 text-right">${parseFloat(item.quantity_issued).toLocaleString('en-PK', {minimumFractionDigits: 2})}</td>
                        <td class="py-1 px-2 text-center">${item.uom?.uom_name || 'Piece'}</td>
                        <td class="py-1 px-2">${batchHtml}</td>
                        <td class="py-1 px-2 text-right font-bold text-emerald-600">₨ ${parseFloat(itemTotal).toLocaleString('en-PK', {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
                itemsBody.innerHTML += displayRow;

                // Add product-level aggregates as hidden fields
                let productAggregateInputs = `
                    <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}" />
                    <input type="hidden" name="items[${index}][quantity_issued]" value="${item.quantity_issued}" />
                    <input type="hidden" name="items[${index}][unit_cost]" value="${parseFloat(item.unit_cost).toFixed(2)}" />
                    <input type="hidden" name="items[${index}][selling_price]" value="${avgSellingPrice.toFixed(2)}" />
                    <input type="hidden" class="item-${index}-qty-sold" name="items[${index}][quantity_sold]" value="0" />
                    <input type="hidden" class="item-${index}-qty-returned" name="items[${index}][quantity_returned]" value="0" />
                    <input type="hidden" class="item-${index}-qty-shortage" name="items[${index}][quantity_shortage]" value="0" />
                `;

                // Add batch-wise settlement rows
                if (batchBreakdown.length > 0) {
                    batchBreakdown.forEach((b, bIndex) => {
                        const isFirst = bIndex === 0;
                        const rowClass = b.is_promotional ? 'bg-orange-50' : '';

                        const settlementRow = `
                            <tr class="${rowClass}">
                                <td class="px-3 py-2 text-sm">
                                    ${isFirst ? productAggregateInputs : ''}
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][stock_batch_id]" value="${b.stock_batch_id || ''}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][batch_code]" value="${b.batch_code || ''}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][quantity_issued]" value="${b.quantity || 0}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][unit_cost]" value="${b.unit_cost || item.unit_cost}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][selling_price]" value="${b.selling_price || 0}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][is_promotional]" value="${b.is_promotional ? 1 : 0}" />
                                    ${isFirst ? `<div class="font-semibold text-gray-900">${item.product.product_name}</div><div class="text-xs text-gray-500 mb-1">${item.product.product_code}</div>` : ''}
                                    <div class="text-xs ${b.is_promotional ? 'text-orange-700 font-medium' : 'text-gray-600'}">
                                        ${b.is_promotional ? '🎁 ' : ''}Batch: ${b.batch_code || 'N/A'}
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-sm text-right font-medium">${parseFloat(b.quantity).toLocaleString('en-PK', {minimumFractionDigits: 0})}</td>
                                <td class="px-3 py-2 text-sm text-right">₨${parseFloat(b.selling_price).toFixed(2)}</td>
                                <td class="px-3 py-2 bg-green-50">
                                    <input type="number"
                                        name="items[${index}][batches][${bIndex}][quantity_sold]"
                                        step="0.001" min="0" max="${b.quantity}"
                                        class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right batch-input"
                                        data-item-index="${index}" data-type="sold"
                                        onchange="updateProductTotals(${index})"
                                        value="0" />
                                </td>
                                <td class="px-3 py-2 bg-blue-50">
                                    <input type="number"
                                        name="items[${index}][batches][${bIndex}][quantity_returned]"
                                        step="0.001" min="0" max="${b.quantity}"
                                        class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right batch-input"
                                        data-item-index="${index}" data-type="returned"
                                        onchange="updateProductTotals(${index})"
                                        value="0" />
                                </td>
                                <td class="px-3 py-2 bg-red-50">
                                    <input type="number"
                                        name="items[${index}][batches][${bIndex}][quantity_shortage]"
                                        step="0.001" min="0" max="${b.quantity}"
                                        class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right batch-input"
                                        data-item-index="${index}" data-type="shortage"
                                        onchange="updateProductTotals(${index})"
                                        value="0" />
                                </td>
                            </tr>
                        `;
                        settlementItemsBody.innerHTML += settlementRow;
                    });
                } else {
                    // Fallback for items without batch breakdown
                    const settlementRow = `
                        <tr>
                            <td class="px-3 py-2 text-sm">
                                ${productAggregateInputs}
                                <div class="font-medium">${item.product.product_name}</div>
                                <div class="text-xs text-gray-500">${item.product.product_code}</div>
                                <div class="text-xs text-gray-400">No batch data</div>
                            </td>
                            <td class="px-3 py-2 text-sm text-right">${parseFloat(item.quantity_issued).toLocaleString('en-PK', {minimumFractionDigits: 2})}</td>
                            <td class="px-3 py-2 text-sm text-right">₨${parseFloat(item.unit_cost).toFixed(2)}</td>
                            <td class="px-3 py-2 bg-green-50">
                                <input type="number" name="items[${index}][quantity_sold]" step="0.001" min="0"
                                    max="${item.quantity_issued}"
                                    class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right" value="0" />
                            </td>
                            <td class="px-3 py-2 bg-blue-50">
                                <input type="number" name="items[${index}][quantity_returned]" step="0.001" min="0"
                                    max="${item.quantity_issued}"
                                    class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right" value="0" />
                            </td>
                            <td class="px-3 py-2 bg-red-50">
                                <input type="number" name="items[${index}][quantity_shortage]" step="0.001" min="0"
                                    max="${item.quantity_issued}"
                                    class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right" value="0" />
                            </td>
                        </tr>
                    `;
                    settlementItemsBody.innerHTML += settlementRow;
                }
            });

            // Show grand total
            document.getElementById('grandTotal').textContent = `₨ ${grandTotal.toLocaleString('en-PK', {minimumFractionDigits: 2})}`;
            document.getElementById('itemsFooter').style.display = 'table-row-group';
            document.getElementById('settlementItemsTable').style.display = 'table';
            document.getElementById('noItemsMessage').style.display = 'none';
        });
    </script>
</x-app-layout>