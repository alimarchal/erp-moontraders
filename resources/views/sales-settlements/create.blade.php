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
                                <x-input id="settlement_date" name="settlement_date" type="date" class="mt-1 block w-full"
                                    :value="old('settlement_date', date('Y-m-d'))" required />
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
                                        {{ $gi->issue_number }} - {{ $gi->employee->full_name }} ({{ $gi->issue_date->format('d M Y') }})
                                    </option>
                                    @endforeach
                                </select>
                                <x-input-error for="goods_issue_id" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <x-label for="cash_sales_amount" value="Cash Sales Amount" />
                                <x-input id="cash_sales_amount" name="cash_sales_amount" type="number" step="0.01" min="0"
                                    class="mt-1 block w-full" :value="old('cash_sales_amount', 0)" />
                                <x-input-error for="cash_sales_amount" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="cheque_sales_amount" value="Cheque Sales Amount" />
                                <x-input id="cheque_sales_amount" name="cheque_sales_amount" type="number" step="0.01" min="0"
                                    class="mt-1 block w-full" :value="old('cheque_sales_amount', 0)" />
                                <x-input-error for="cheque_sales_amount" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="credit_sales_amount" value="Credit Sales Amount" />
                                <x-input id="credit_sales_amount" name="credit_sales_amount" type="number" step="0.01" min="0"
                                    class="mt-1 block w-full" :value="old('credit_sales_amount', 0)" />
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
                                <x-input id="cheques_collected" name="cheques_collected" type="number" step="0.01" min="0"
                                    class="mt-1 block w-full" :value="old('cheques_collected', 0)" />
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
                            <table class="min-w-full divide-y divide-gray-200" id="itemsTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Issued</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sold</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Returned</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Shortage</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Selling Price</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="itemsBody">
                                    <!-- Items will be loaded from selected goods issue -->
                                </tbody>
                            </table>
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
        document.getElementById('goods_issue_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (!selectedOption.value) return;

            const items = JSON.parse(selectedOption.dataset.items || '[]');
            const tbody = document.getElementById('itemsBody');
            tbody.innerHTML = '';

            items.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="px-3 py-2 text-sm">
                        <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}" />
                        ${item.product.product_name}
                        <div class="text-xs text-gray-500">${item.product.product_code}</div>
                    </td>
                    <td class="px-3 py-2 text-sm text-right">
                        <input type="hidden" name="items[${index}][quantity_issued]" value="${item.quantity_issued}" />
                        ${parseFloat(item.quantity_issued).toFixed(3)}
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="items[${index}][quantity_sold]" step="0.001" min="0"
                            max="${item.quantity_issued}"
                            class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right" required />
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="items[${index}][quantity_returned]" step="0.001" min="0"
                            max="${item.quantity_issued}"
                            class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right" value="0" />
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="items[${index}][quantity_shortage]" step="0.001" min="0"
                            max="${item.quantity_issued}"
                            class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right" value="0" />
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="items[${index}][unit_cost]" step="0.01" min="0"
                            value="${item.unit_cost}"
                            class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right" required />
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="items[${index}][selling_price]" step="0.01" min="0"
                            class="border-gray-300 rounded-md shadow-sm w-24 text-sm text-right" required />
                    </td>
                `;
                tbody.appendChild(row);
            });
        });
    </script>
</x-app-layout>
