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

                        {{-- Section 1: Date & Goods Issue Selection --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6" x-data="goodsIssueSelector()">
                            <div>
                                <x-label for="settlement_date" value="Settlement Date" class="required" />
                                <x-input id="settlement_date" name="settlement_date" type="date"
                                    class="mt-1 block w-full" :value="old('settlement_date', date('Y-m-d'))" required />
                                <x-input-error for="settlement_date" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="goods_issue_id" value="Select Goods Issue" class="required" />
                                <select id="goods_issue_id" name="goods_issue_id"
                                    class="select2-goods-issue border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    required>
                                    <option value="">Select Goods Issue</option>
                                </select>
                                <x-input-error for="goods_issue_id" class="mt-2" />
                            </div>
                        </div>

                        <p class="text-sm text-gray-500 mt-2" id="noItemsMessage">
                            Select a Goods Issue to load product details
                        </p>

                        {{-- Section 2: Combined Batch-wise Settlement Table --}}
                        <div id="settlementTableContainer" style="display: none;">
                            <x-detail-table title="Batch-wise Settlement (Issue - Sold - Return - Shortage = Balance)" :headers="[
                                ['label' => 'Product / Batch', 'align' => 'text-left'],
                                ['label' => 'UOM', 'align' => 'text-center'],
                                ['label' => 'Batch Breakdown', 'align' => 'text-left'],
                                ['label' => 'Qty Issued', 'align' => 'text-right'],
                                ['label' => 'Price', 'align' => 'text-right'],
                                ['label' => 'Value', 'align' => 'text-right'],
                                ['label' => 'Sold', 'align' => 'text-right'],
                                ['label' => 'Returned', 'align' => 'text-right'],
                                ['label' => 'Shortage', 'align' => 'text-right'],
                                ['label' => 'Balance', 'align' => 'text-right'],
                            ]">
                                <tbody id="settlementItemsBody">
                                    <!-- Settlement items will be populated here -->
                                </tbody>
                                <x-slot name="footer">
                                    <tr class="border-t-2 border-gray-300 bg-gray-100">
                                        <td colspan="6" class="py-2 px-2 text-right font-bold text-base">Grand Totals:</td>
                                        <td class="py-2 px-2 text-right font-bold text-base text-green-700" id="grandTotalSold">0</td>
                                        <td class="py-2 px-2 text-right font-bold text-base text-blue-700" id="grandTotalReturned">0</td>
                                        <td class="py-2 px-2 text-right font-bold text-base text-red-700" id="grandTotalShortage">0</td>
                                        <td class="py-2 px-2 text-right font-bold text-base" id="grandTotalBalance">0</td>
                                    </tr>
                                    <tr class="border-t border-gray-300 bg-blue-50">
                                        <td colspan="6" class="py-3 px-2 text-right font-bold text-lg">Value Totals:</td>
                                        <td class="py-3 px-2 text-right font-bold text-lg text-green-700" id="grandTotalSoldValue">₨ 0.00</td>
                                        <td class="py-3 px-2 text-right font-bold text-lg text-blue-700" id="grandTotalReturnValue">₨ 0.00</td>
                                        <td class="py-3 px-2 text-right font-bold text-lg text-red-700" id="grandTotalShortageValue">₨ 0.00</td>
                                        <td class="py-3 px-2 text-right font-bold text-2xl" id="valueBalanceCheck">₨ 0.00</td>
                                    </tr>
                                    <tr class="border-t-2 border-gray-400 bg-gray-200">
                                        <td colspan="9" class="py-2 px-2 text-right font-bold text-base">Total Issued Value:</td>
                                        <td class="py-2 px-2 text-right font-bold text-xl text-emerald-700" id="grandTotalIssuedValue">₨ 0.00</td>
                                    </tr>
                                </x-slot>
                            </x-detail-table>
                        </div>
                        <p class="text-sm text-blue-600 mt-2" style="display: none;" id="settlementHelpText">
                            💡 Tip: When you enter Sold quantity, the remaining will auto-calculate. You can then adjust
                            Returned and Shortage as needed.
                        </p>

                        <hr class="my-6 border-gray-200">

                        {{-- Section 4: Creditors/Credit Sales Breakdown (MOVED BEFORE EXPENSES) --}}
                        <div class="mb-6" x-data="creditSalesManager()">
                            <div class="flex justify-between items-center mb-4">
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

                            <div x-show="creditSales.length > 0">
                                <x-detail-table title="Creditors / Credit Sales Breakdown" :headers="[
                                    ['label' => 'Customer', 'align' => 'text-left'],
                                    ['label' => 'Previous Balance', 'align' => 'text-right'],
                                    ['label' => 'New Credit', 'align' => 'text-right'],
                                    ['label' => 'Payment Received', 'align' => 'text-right'],
                                    ['label' => 'New Balance', 'align' => 'text-right'],
                                    ['label' => 'Notes', 'align' => 'text-left'],
                                    ['label' => 'Action', 'align' => 'text-center'],
                                ]">
                                    <tbody>
                                        <template x-for="(sale, index) in creditSales" :key="index">
                                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                                <td class="py-2 px-2">
                                                    <select
                                                        :name="'credit_sales[' + index + '][customer_id]'"
                                                        x-model="sale.customer_id"
                                                        @change="updateCustomerBalance(index)"
                                                        class="border-gray-300 rounded-md text-sm w-full"
                                                        required>
                                                        <option value="">Select Customer</option>
                                                        @foreach($customers ?? [] as $customer)
                                                            <option value="{{ $customer->id }}"
                                                                data-balance="{{ $customer->receivable_balance ?? 0 }}">
                                                                {{ $customer->customer_name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="py-2 px-2 text-right">
                                                    <input type="number"
                                                        :name="'credit_sales[' + index + '][previous_balance]'"
                                                        x-model="sale.previous_balance"
                                                        readonly
                                                        class="border-gray-300 rounded-md text-sm w-24 text-right bg-gray-100"
                                                        step="0.01" />
                                                </td>
                                                <td class="py-2 px-2 text-right">
                                                    <input type="number"
                                                        :name="'credit_sales[' + index + '][sale_amount]'"
                                                        x-model="sale.sale_amount"
                                                        @input="calculateNewBalance(index); updateCreditTotal()"
                                                        class="border-gray-300 rounded-md text-sm w-24 text-right"
                                                        step="0.01"
                                                        min="0" />
                                                </td>
                                                <td class="py-2 px-2 text-right">
                                                    <input type="number"
                                                        :name="'credit_sales[' + index + '][payment_received]'"
                                                        x-model="sale.payment_received"
                                                        @input="calculateNewBalance(index); updateRecoveryTotal()"
                                                        class="border-gray-300 rounded-md text-sm w-24 text-right"
                                                        step="0.01"
                                                        min="0" />
                                                </td>
                                                <td class="py-2 px-2 text-right">
                                                    <span class="font-semibold text-gray-700"
                                                        x-text="formatCurrency(sale.new_balance)"></span>
                                                    <input type="hidden"
                                                        :name="'credit_sales[' + index + '][new_balance]'"
                                                        x-model="sale.new_balance" />
                                                </td>
                                                <td class="py-2 px-2">
                                                    <input type="text"
                                                        :name="'credit_sales[' + index + '][notes]'"
                                                        x-model="sale.notes"
                                                        class="border-gray-300 rounded-md text-sm w-full"
                                                        placeholder="Optional notes" />
                                                </td>
                                                <td class="py-2 px-2 text-center">
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
                                    <x-slot name="footer">
                                        <tr class="border-t-2 border-gray-300 bg-gray-100">
                                            <td colspan="2" class="py-2 px-2 text-right font-bold">Total Credit Sales:</td>
                                            <td class="py-2 px-2 text-right font-bold text-orange-700">
                                                <span x-text="formatCurrency(creditTotal)"></span>
                                                <input type="hidden" id="credit_sales_amount" name="credit_sales_amount" :value="creditTotal" />
                                            </td>
                                            <td class="py-2 px-2 text-right font-bold text-green-700">
                                                <span x-text="formatCurrency(recoveryTotal)"></span>
                                                <input type="hidden" id="credit_recoveries_total" name="credit_recoveries_total" :value="recoveryTotal" />
                                            </td>
                                            <td colspan="3"></td>
                                        </tr>
                                    </x-slot>
                                </x-detail-table>
                            </div>

                            <p class="text-sm text-gray-500 mt-2" x-show="creditSales.length === 0">
                                No credit sales added yet. Click "Add Credit Sale" to add one.
                            </p>
                        </div>

                        <hr class="my-6 border-gray-200">

                        {{-- Section 5: ENHANCED Expense Detail --}}
                        <div id="expensesSection" style="display: none;" class="mb-6">
                            <div
                                class="bg-gradient-to-br from-red-50 to-orange-50 p-6 rounded-lg border-2 border-red-200">
                                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Expense Detail
                                </h3>
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Toll Tax</label>
                                        <input type="number" id="expense_toll_tax" name="expense_toll_tax" step="0.01"
                                            min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">AMR Powder Claim</label>
                                        <input type="number" id="expense_amr_powder_claim" name="expense_amr_powder_claim" step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">AMR Liquid Claim</label>
                                        <input type="number" id="expense_amr_liquid_claim" name="expense_amr_liquid_claim" step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Scheme Amount</label>
                                        <input type="number" id="expense_scheme" name="expense_scheme" step="0.01"
                                            min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Advance Tax</label>
                                        <input type="number" id="expense_advance_tax" name="expense_advance_tax" step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Food Charges</label>
                                        <input type="number" id="expense_food_charges" name="expense_food_charges" step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Salesman Charges</label>
                                        <input type="number" id="expense_salesman_charges" name="expense_salesman_charges" step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Loader Charges</label>
                                        <input type="number" id="expense_loader_charges" name="expense_loader_charges" step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Percentage Amount</label>
                                        <input type="number" id="expense_percentage" name="expense_percentage"
                                            step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Miscellaneous Amount</label>
                                        <input type="number" id="expense_miscellaneous_amount" name="expense_miscellaneous_amount" step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                </div>
                                <div class="mt-4 bg-white p-4 rounded-md shadow-sm border-2 border-red-300">
                                    <div class="flex justify-between items-center">
                                        <span class="text-base font-bold text-gray-700">Grand Total Expense:</span>
                                        <span class="text-2xl font-bold text-red-700" id="totalExpensesDisplay">₨
                                            0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section 6: Sales Summary --}}
                        <div id="salesSummarySection" style="display: none;" class="mb-6">
                            <div
                                class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-lg border-2 border-blue-200">
                                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    Sales Summary
                                </h3>
                                <p class="text-xs text-gray-600 mb-4 italic">Formula: Net Sales + Recoveries = Total Sales | Total Sales - Credit = Balance | Balance - Grand Total Expense = Net Balance | Net Balance - Cash Received = Short/Excess</p>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Net Sale (= Item Issue Value)</label>
                                        <input type="number" id="summary_net_sale" name="summary_net_sale" readonly
                                            class="mt-1 block w-full text-right font-bold text-green-700 bg-green-50 border-green-200 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Recoveries (from Credit Sales)</label>
                                        <input type="number" id="summary_recovery" name="summary_recovery" readonly
                                            class="mt-1 block w-full text-right font-bold bg-gray-100 border-gray-300 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Total Sale</label>
                                        <input type="number" id="summary_total_sale" readonly
                                            class="mt-1 block w-full text-right font-bold text-blue-700 bg-blue-50 border-blue-200 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Credit (from Credit Sales)</label>
                                        <input type="number" id="summary_credit" name="summary_credit" readonly
                                            class="mt-1 block w-full text-right font-bold text-orange-700 bg-orange-50 border-orange-200 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Balance</label>
                                        <input type="number" id="summary_balance" readonly
                                            class="mt-1 block w-full text-right font-bold bg-gray-100 border-gray-300 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Grand Total Expenses</label>
                                        <input type="number" id="summary_expenses" name="summary_expenses" readonly
                                            class="mt-1 block w-full text-right font-bold text-red-700 bg-red-50 border-red-200 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Net
                                            Balance</label>
                                        <input type="number" id="summary_net_balance" readonly
                                            class="mt-1 block w-full text-right font-bold bg-gray-100 border-gray-300 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Cash
                                            Received</label>
                                        <input type="number" id="summary_cash_received" name="summary_cash_received" readonly
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1 bg-gray-100"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border col-span-2">
                                        <label
                                            class="text-xs font-semibold text-gray-600 block mb-1">Short/Excess</label>
                                        <input type="number" id="summary_short_excess" readonly
                                            class="mt-1 block w-full text-right font-bold bg-purple-50 border-purple-200 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section 7: Cash Detail --}}
                        <div id="cashDetailSection" style="display: none;" class="mb-6">
                            <div
                                class="bg-gradient-to-br from-green-50 to-emerald-50 p-6 rounded-lg border-2 border-green-200">
                                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Cash Detail (Denomination Breakdown)
                                </h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">₨ 5000
                                            Notes</label>
                                        <input type="number" id="denom_5000" name="denom_5000" min="0" step="1"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                        <div class="text-xs text-gray-500 mt-1">= <span id="denom_5000_total">₨ 0</span>
                                        </div>
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">₨ 1000
                                            Notes</label>
                                        <input type="number" id="denom_1000" name="denom_1000" min="0" step="1"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                        <div class="text-xs text-gray-500 mt-1">= <span id="denom_1000_total">₨ 0</span>
                                        </div>
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">₨ 500
                                            Notes</label>
                                        <input type="number" id="denom_500" name="denom_500" min="0" step="1"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                        <div class="text-xs text-gray-500 mt-1">= <span id="denom_500_total">₨ 0</span>
                                        </div>
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">₨ 100
                                            Notes</label>
                                        <input type="number" id="denom_100" name="denom_100" min="0" step="1"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                        <div class="text-xs text-gray-500 mt-1">= <span id="denom_100_total">₨ 0</span>
                                        </div>
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">₨ 50 Notes</label>
                                        <input type="number" id="denom_50" name="denom_50" min="0" step="1"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                        <div class="text-xs text-gray-500 mt-1">= <span id="denom_50_total">₨ 0</span>
                                        </div>
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">₨ 20 Notes</label>
                                        <input type="number" id="denom_20" name="denom_20" min="0" step="1"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                        <div class="text-xs text-gray-500 mt-1">= <span id="denom_20_total">₨ 0</span>
                                        </div>
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">₨ 10 Notes</label>
                                        <input type="number" id="denom_10" name="denom_10" min="0" step="1"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                        <div class="text-xs text-gray-500 mt-1">= <span id="denom_10_total">₨ 0</span>
                                        </div>
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Loose Cash</label>
                                        <input type="number" id="denom_coins" name="denom_coins" min="0" step="0.01"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Bank 1</label>
                                        <input type="number" id="bank_1" name="bank_1" min="0" step="0.01"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Bank 2</label>
                                        <input type="number" id="bank_2" name="bank_2" min="0" step="0.01"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateCashTotal()" value="0" />
                                    </div>
                                </div>
                                <div class="mt-4 bg-white p-4 rounded-md shadow-sm border-2 border-green-300">
                                    <div class="flex justify-between items-center">
                                        <span class="text-base font-bold text-gray-700">Total Cash:</span>
                                        <span class="text-2xl font-bold text-green-700" id="totalCashDisplay">₨
                                            0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-6 border-gray-200">

                        {{-- Section 8: Notes (MOVED TO BOTTOM) --}}
                        <div class="mb-6">
                            <x-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="3"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                placeholder="Add any additional notes or remarks here...">{{ old('notes') }}</textarea>
                            <x-input-error for="notes" class="mt-2" />
                        </div>

                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('sales-settlements.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                Cancel
                            </a>
                            <x-button type="submit">
                                Create Settlement
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let invoiceCounter = 1;

        // Alpine.js component for Goods Issue selector
        function goodsIssueSelector() {
            return {
                selectedGoodsIssue: null,
                loading: false,
            }
        }

        // Initialize Select2 with AJAX on-demand loading
        $(document).ready(function() {
            console.log('Initializing Select2 for goods issue dropdown');

            $('.select2-goods-issue').select2({
                width: '100%',
                placeholder: 'Select a Goods Issue',
                allowClear: true,
                ajax: {
                    url: '{{ route('api.sales-settlements.goods-issues') }}',
                    dataType: 'json',
                    delay: 250,
                    processResults: function (data) {
                        console.log('Select2 data loaded:', data);
                        return {
                            results: data
                        };
                    },
                    cache: true
                }
            });

            console.log('Select2 initialized successfully');
        });

        function creditSalesManager() {
            return {
                creditSales: [],
                creditTotal: 0,
                recoveryTotal: 0,

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
                    this.updateRecoveryTotal();
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
                    document.getElementById('summary_credit').value = this.creditTotal.toFixed(2);
                    updateSalesSummary();
                },

                updateRecoveryTotal() {
                    this.recoveryTotal = this.creditSales.reduce((sum, sale) => {
                        return sum + (parseFloat(sale.payment_received) || 0);
                    }, 0);

                    document.getElementById('credit_recoveries_total').value = this.recoveryTotal.toFixed(2);
                    document.getElementById('summary_recovery').value = this.recoveryTotal.toFixed(2);
                    updateSalesSummary();
                },

                formatCurrency(value) {
                    return '₨ ' + parseFloat(value || 0).toLocaleString('en-PK', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
        }

        // Function to calculate balance for a batch row
        function calculateBatchBalance(itemIndex, batchIndex) {
            const issuedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_issued]"]`);
            const soldInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_sold]"]`);
            const returnedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_returned]"]`);
            const shortageInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_shortage]"]`);

            if (!issuedInput || !soldInput || !returnedInput || !shortageInput) return;

            const issued = Math.round(parseFloat(issuedInput.value) || 0);
            const sold = Math.round(parseFloat(soldInput.value) || 0);
            const returned = Math.round(parseFloat(returnedInput.value) || 0);
            const shortage = Math.round(parseFloat(shortageInput.value) || 0);

            const balance = issued - sold - returned - shortage;
            const balanceSpan = document.getElementById(`balance-${itemIndex}-${batchIndex}`);

            if (balanceSpan) {
                balanceSpan.textContent = balance;

                // Color coding: green if balanced, red if not
                if (balance === 0) {
                    balanceSpan.className = 'font-bold text-green-600';
                } else {
                    balanceSpan.className = 'font-bold text-red-600';
                }
            }

            // Update product totals
            updateProductTotals(itemIndex);
        }

        // Auto-fill shortage when sold + returned is entered
        function autoFillShortage(itemIndex, batchIndex, skipField = null) {
            const issuedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_issued]"]`);
            const soldInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_sold]"]`);
            const returnedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_returned]"]`);
            const shortageInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_shortage]"]`);

            if (!issuedInput || !soldInput || !returnedInput || !shortageInput) return;

            const issued = Math.round(parseFloat(issuedInput.value) || 0);
            const sold = Math.round(parseFloat(soldInput.value) || 0);
            const returned = Math.round(parseFloat(returnedInput.value) || 0);

            // Auto-fill shortage when entering sold or returned (but not when editing shortage itself)
            if (skipField !== 'shortage') {
                const autoShortage = Math.max(0, issued - sold - returned);
                shortageInput.value = autoShortage;
            }

            calculateBatchBalance(itemIndex, batchIndex);
        }

        // Function to update product-level totals from batch inputs
        function updateProductTotals(itemIndex) {
            let soldTotal = 0;
            let returnedTotal = 0;
            let shortageTotal = 0;

            // Sum up all batch quantities for this item
            document.querySelectorAll(`.batch-input[data-item-index="${itemIndex}"]`).forEach(input => {
                const value = Math.round(parseFloat(input.value) || 0);
                const type = input.dataset.type;

                if (type === 'sold') soldTotal += value;
                if (type === 'returned') returnedTotal += value;
                if (type === 'shortage') shortageTotal += value;
            });

            // Update hidden fields
            const soldField = document.querySelector(`.item-${itemIndex}-qty-sold`);
            const returnedField = document.querySelector(`.item-${itemIndex}-qty-returned`);
            const shortageField = document.querySelector(`.item-${itemIndex}-qty-shortage`);

            if (soldField) soldField.value = soldTotal;
            if (returnedField) returnedField.value = returnedTotal;
            if (shortageField) shortageField.value = shortageTotal;

            // Update grand totals
            updateGrandTotals();
        }

        // Function to update grand totals across all items with VALUE calculations
        function updateGrandTotals() {
            let grandSold = 0;
            let grandReturned = 0;
            let grandShortage = 0;
            let grandIssued = 0;

            let grandSoldValue = 0;
            let grandReturnValue = 0;
            let grandShortageValue = 0;
            let grandIssuedValue = 0;

            // Sum all batch inputs with their prices
            document.querySelectorAll('.batch-input').forEach(input => {
                const qty = Math.round(parseFloat(input.value) || 0);
                const type = input.dataset.type;
                const itemIdx = input.dataset.itemIndex;
                const batchIdx = input.dataset.batchIndex;

                // Get the selling price for this batch
                const priceInput = document.querySelector(`input[name="items[${itemIdx}][batches][${batchIdx}][selling_price]"]`);
                const price = priceInput ? parseFloat(priceInput.value) || 0 : 0;

                if (type === 'sold') {
                    grandSold += qty;
                    grandSoldValue += qty * price;
                }
                if (type === 'returned') {
                    grandReturned += qty;
                    grandReturnValue += qty * price;
                }
                if (type === 'shortage') {
                    grandShortage += qty;
                    grandShortageValue += qty * price;
                }
            });

            // Get total issued from hidden inputs
            document.querySelectorAll('input[name*="[batches]"][name*="[quantity_issued]"]').forEach(input => {
                const qty = Math.round(parseFloat(input.value) || 0);
                grandIssued += qty;

                // Extract indices from name attribute
                const matches = input.name.match(/items\[(\d+)\]\[batches\]\[(\d+)\]/);
                if (matches) {
                    const itemIdx = matches[1];
                    const batchIdx = matches[2];
                    const priceInput = document.querySelector(`input[name="items[${itemIdx}][batches][${batchIdx}][selling_price]"]`);
                    const price = priceInput ? parseFloat(priceInput.value) || 0 : 0;
                    grandIssuedValue += qty * price;
                }
            });

            const grandBalance = grandIssued - grandSold - grandReturned - grandShortage;
            const grandBalanceValue = grandIssuedValue - grandSoldValue - grandReturnValue - grandShortageValue;

            // Update quantity displays with null checks
            const soldEl = document.getElementById('grandTotalSold');
            const returnedEl = document.getElementById('grandTotalReturned');
            const shortageEl = document.getElementById('grandTotalShortage');
            const balanceElement = document.getElementById('grandTotalBalance');

            if (soldEl) soldEl.textContent = grandSold;
            if (returnedEl) returnedEl.textContent = grandReturned;
            if (shortageEl) shortageEl.textContent = grandShortage;

            if (balanceElement) {
                balanceElement.textContent = grandBalance;
                // Color code quantity balance
                if (grandBalance === 0) {
                    balanceElement.className = 'py-2 px-2 text-right font-bold text-base text-green-600';
                } else {
                    balanceElement.className = 'py-2 px-2 text-right font-bold text-base text-red-600';
                }
            }

            // Update value displays with null checks
            const formatPKR = (val) => '₨ ' + val.toLocaleString('en-PK', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            const soldValueEl = document.getElementById('grandTotalSoldValue');
            const returnValueEl = document.getElementById('grandTotalReturnValue');
            const shortageValueEl = document.getElementById('grandTotalShortageValue');
            const balanceValueEl = document.getElementById('grandTotalBalanceValue');
            const issuedValueEl = document.getElementById('grandTotalIssuedValue');
            const valueCheckElement = document.getElementById('valueBalanceCheck');

            if (soldValueEl) soldValueEl.textContent = formatPKR(grandSoldValue);
            if (returnValueEl) returnValueEl.textContent = formatPKR(grandReturnValue);
            if (shortageValueEl) shortageValueEl.textContent = formatPKR(grandShortageValue);
            if (balanceValueEl) balanceValueEl.textContent = formatPKR(grandBalanceValue);
            if (issuedValueEl) issuedValueEl.textContent = formatPKR(grandIssuedValue);

            if (valueCheckElement) {
                valueCheckElement.textContent = formatPKR(grandBalanceValue);
                // Color code value balance - must be zero
                if (Math.abs(grandBalanceValue) < 0.01) {
                    valueCheckElement.className = 'py-3 px-2 text-right font-bold text-2xl text-green-700';
                } else {
                    valueCheckElement.className = 'py-3 px-2 text-right font-bold text-2xl text-red-700';
                }
            }

            // Update Sales Summary with sold value (Net Sale = Item Issue Value)
            const netSaleEl = document.getElementById('summary_net_sale');
            if (netSaleEl) {
                netSaleEl.value = grandIssuedValue.toFixed(2);
            }
            updateSalesSummary();
        }

        // Sales Summary calculations
        function updateSalesSummary() {
            const netSale = parseFloat(document.getElementById('summary_net_sale').value) || 0;
            const recovery = parseFloat(document.getElementById('summary_recovery').value) || 0;
            const credit = parseFloat(document.getElementById('summary_credit').value) || 0;
            const expenses = parseFloat(document.getElementById('summary_expenses').value) || 0;
            const cashReceived = parseFloat(document.getElementById('summary_cash_received').value) || 0;

            const totalSale = netSale + recovery;
            const balance = totalSale - credit;
            const netBalance = balance - expenses;
            const shortExcess = netBalance - cashReceived;

            document.getElementById('summary_total_sale').value = totalSale.toFixed(2);
            document.getElementById('summary_balance').value = balance.toFixed(2);
            document.getElementById('summary_net_balance').value = netBalance.toFixed(2);
            document.getElementById('summary_short_excess').value = shortExcess.toFixed(2);

            // Color code short/excess
            const shortExcessEl = document.getElementById('summary_short_excess');
            if (Math.abs(shortExcess) < 0.01) {
                shortExcessEl.className = 'mt-1 block w-full text-right font-bold bg-green-100 border-green-300 rounded-md text-sm px-2 py-1';
            } else if (shortExcess > 0) {
                shortExcessEl.className = 'mt-1 block w-full text-right font-bold bg-red-100 border-red-300 rounded-md text-sm px-2 py-1 text-red-700';
            } else {
                shortExcessEl.className = 'mt-1 block w-full text-right font-bold bg-blue-100 border-blue-300 rounded-md text-sm px-2 py-1 text-blue-700';
            }
        }

        // Expenses Detail calculations (UPDATED with new fields)
        function updateExpensesTotal() {
            const tollTax = parseFloat(document.getElementById('expense_toll_tax').value) || 0;
            const amrPowder = parseFloat(document.getElementById('expense_amr_powder_claim').value) || 0;
            const amrLiquid = parseFloat(document.getElementById('expense_amr_liquid_claim').value) || 0;
            const scheme = parseFloat(document.getElementById('expense_scheme').value) || 0;
            const advanceTax = parseFloat(document.getElementById('expense_advance_tax').value) || 0;
            const foodCharges = parseFloat(document.getElementById('expense_food_charges').value) || 0;
            const salesmanCharges = parseFloat(document.getElementById('expense_salesman_charges').value) || 0;
            const loaderCharges = parseFloat(document.getElementById('expense_loader_charges').value) || 0;
            const percentage = parseFloat(document.getElementById('expense_percentage').value) || 0;
            const miscellaneousAmount = parseFloat(document.getElementById('expense_miscellaneous_amount').value) || 0;

            const totalExpenses = tollTax + amrPowder + amrLiquid + scheme + advanceTax +
                                 foodCharges + salesmanCharges + loaderCharges + percentage + miscellaneousAmount;

            document.getElementById('totalExpensesDisplay').textContent = '₨ ' + totalExpenses.toLocaleString('en-PK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            document.getElementById('summary_expenses').value = totalExpenses.toFixed(2);
            updateSalesSummary();
        }

        // Helper function to clear settlement form
        function clearSettlementForm() {
            document.getElementById('settlementItemsBody').innerHTML = '';
            document.getElementById('settlementTableContainer').style.display = 'none';
            document.getElementById('settlementHelpText').style.display = 'none';
            document.getElementById('noItemsMessage').style.display = 'block';
            document.getElementById('noItemsMessage').innerHTML = 'Select a Goods Issue to load product details';
            document.getElementById('salesSummarySection').style.display = 'none';
            document.getElementById('expensesSection').style.display = 'none';
            document.getElementById('cashDetailSection').style.display = 'none';
        }

        // Cash Detail denomination breakdown
        function updateCashTotal() {
            const denom5000 = (parseFloat(document.getElementById('denom_5000').value) || 0) * 5000;
            const denom1000 = (parseFloat(document.getElementById('denom_1000').value) || 0) * 1000;
            const denom500 = (parseFloat(document.getElementById('denom_500').value) || 0) * 500;
            const denom100 = (parseFloat(document.getElementById('denom_100').value) || 0) * 100;
            const denom50 = (parseFloat(document.getElementById('denom_50').value) || 0) * 50;
            const denom20 = (parseFloat(document.getElementById('denom_20').value) || 0) * 20;
            const denom10 = (parseFloat(document.getElementById('denom_10').value) || 0) * 10;
            const coins = parseFloat(document.getElementById('denom_coins').value) || 0;
            const bank1 = parseFloat(document.getElementById('bank_1').value) || 0;
            const bank2 = parseFloat(document.getElementById('bank_2').value) || 0;

            // Update individual denomination totals
            document.getElementById('denom_5000_total').textContent = '₨ ' + denom5000.toLocaleString('en-PK');
            document.getElementById('denom_1000_total').textContent = '₨ ' + denom1000.toLocaleString('en-PK');
            document.getElementById('denom_500_total').textContent = '₨ ' + denom500.toLocaleString('en-PK');
            document.getElementById('denom_100_total').textContent = '₨ ' + denom100.toLocaleString('en-PK');
            document.getElementById('denom_50_total').textContent = '₨ ' + denom50.toLocaleString('en-PK');
            document.getElementById('denom_20_total').textContent = '₨ ' + denom20.toLocaleString('en-PK');
            document.getElementById('denom_10_total').textContent = '₨ ' + denom10.toLocaleString('en-PK');

            const totalCash = denom5000 + denom1000 + denom500 + denom100 + denom50 + denom20 + denom10 + coins + bank1 + bank2;

            document.getElementById('totalCashDisplay').textContent = '₨ ' + totalCash.toLocaleString('en-PK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // Update cash received in sales summary
            document.getElementById('summary_cash_received').value = totalCash.toFixed(2);
            updateSalesSummary();
        }

        // Handle Goods Issue selection with AJAX data loading
        // Use Select2 specific event instead of standard change
        $('#goods_issue_id').on('select2:select', function(e) {
            const goodsIssueId = e.params.data.id;
            console.log('Selected Goods Issue ID:', goodsIssueId);

            if (!goodsIssueId) {
                clearSettlementForm();
                return;
            }

            // Show loading state
            document.getElementById('noItemsMessage').innerHTML = '<span class="text-blue-600"><i class="fas fa-spinner fa-spin"></i> Loading goods issue data...</span>';
            document.getElementById('noItemsMessage').style.display = 'block';

            // Fetch goods issue items via AJAX
            const apiUrl = `{{ url('api/sales-settlements/goods-issues') }}/${goodsIssueId}/items`;
            console.log('Fetching from:', apiUrl);

            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Received data:', data);
                    const items = data.items || [];
                    console.log('Number of items:', items.length);

                    const settlementItemsBody = document.getElementById('settlementItemsBody');
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


                // Hidden fields for product-level data
                let hiddenFields = `
                    <input type="hidden" name="items[${index}][goods_issue_item_id]" value="${item.id}">
                    <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                    <input type="hidden" name="items[${index}][quantity_issued]" value="${item.quantity_issued}">
                    <input type="hidden" name="items[${index}][unit_cost]" value="${item.unit_cost}">
                    <input type="hidden" name="items[${index}][quantity_sold]" class="item-${index}-qty-sold" value="0">
                    <input type="hidden" name="items[${index}][quantity_returned]" class="item-${index}-qty-returned" value="0">
                    <input type="hidden" name="items[${index}][quantity_shortage]" class="item-${index}-qty-shortage" value="0">
                `;

                // Settlement rows (one per batch)
                if (batchBreakdown.length > 0) {
                    batchBreakdown.forEach((batch, batchIdx) => {
                        const batchValue = parseFloat(batch.quantity) * parseFloat(batch.selling_price);

                        // Debug: log the item data
                        console.log('Item data:', item);
                        console.log('Product:', item.product);
                        console.log('Product name:', item.product?.name);

                        const productName = (item.product && item.product.name) ? item.product.name : 'Unknown Product';
                        const productCode = (item.product && item.product.product_code) ? item.product.product_code : 'N/A';
                        const uomSymbol = (item.uom && item.uom.symbol) ? item.uom.symbol : 'N/A';

                        const settlementRow = `
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-2 px-2" style="max-width: 250px; min-width: 180px;">
                                    <div class="font-semibold text-gray-900 break-words">${productName}</div>
                                    <div class="text-xs text-gray-500 break-words">
                                        ${productCode}<br>Batch: ${batch.batch_code}
                                        ${batch.is_promotional ? '<span class="ml-1 px-1.5 py-0.5 bg-purple-100 text-purple-800 text-xs font-bold rounded">PROMO</span>' : ''}
                                    </div>
                                </td>
                                <td class="py-2 px-2 text-center text-sm font-medium">${uomSymbol}</td>
                                <td class="py-2 px-2">
                                    <div class="text-xs text-gray-600">
                                        ${parseFloat(batch.quantity).toLocaleString()} × ₨${parseFloat(batch.selling_price).toFixed(2)}
                                    </div>
                                </td>
                                <td class="py-2 px-2 text-right font-semibold">${parseFloat(batch.quantity).toFixed(0)}</td>
                                <td class="py-2 px-2 text-right text-sm">₨${parseFloat(batch.selling_price).toFixed(2)}</td>
                                <td class="py-2 px-2 text-right font-bold text-green-700">₨${batchValue.toLocaleString('en-PK', {minimumFractionDigits: 2})}</td>
                                <td class="py-2 px-2 text-right">
                                    <input type="number"
                                        name="items[${index}][batches][${batchIdx}][quantity_sold]"
                                        class="batch-input w-full text-right border-gray-300 rounded text-sm px-2 py-1"
                                        data-item-index="${index}"
                                        data-batch-index="${batchIdx}"
                                        data-type="sold"
                                        min="0"
                                        max="${batch.quantity}"
                                        step="1"
                                        value="0"
                                        oninput="autoFillShortage(${index}, ${batchIdx})">
                                </td>
                                <td class="py-2 px-2 text-right">
                                    <input type="number"
                                        name="items[${index}][batches][${batchIdx}][quantity_returned]"
                                        class="batch-input w-full text-right border-gray-300 rounded text-sm px-2 py-1"
                                        data-item-index="${index}"
                                        data-batch-index="${batchIdx}"
                                        data-type="returned"
                                        min="0"
                                        max="${batch.quantity}"
                                        step="1"
                                        value="0"
                                        oninput="autoFillShortage(${index}, ${batchIdx})">
                                </td>
                                <td class="py-2 px-2 text-right">
                                    <input type="number"
                                        name="items[${index}][batches][${batchIdx}][quantity_shortage]"
                                        class="batch-input w-full text-right border-gray-300 rounded text-sm px-2 py-1"
                                        data-item-index="${index}"
                                        data-batch-index="${batchIdx}"
                                        data-type="shortage"
                                        min="0"
                                        max="${batch.quantity}"
                                        step="1"
                                        value="${batch.quantity}"
                                        oninput="autoFillShortage(${index}, ${batchIdx}, 'shortage')">
                                </td>
                                <td class="py-2 px-2 text-right">
                                    <span id="balance-${index}-${batchIdx}" class="font-bold text-red-600">0</span>
                                </td>
                                <input type="hidden" name="items[${index}][batches][${batchIdx}][stock_batch_id]" value="${batch.stock_batch_id}">
                                <input type="hidden" name="items[${index}][batches][${batchIdx}][batch_code]" value="${batch.batch_code}">
                                <input type="hidden" name="items[${index}][batches][${batchIdx}][quantity_issued]" value="${batch.quantity}">
                                <input type="hidden" name="items[${index}][batches][${batchIdx}][unit_cost]" value="${batch.unit_cost}">
                                <input type="hidden" name="items[${index}][batches][${batchIdx}][selling_price]" value="${batch.selling_price}">
                                <input type="hidden" name="items[${index}][batches][${batchIdx}][is_promotional]" value="${batch.is_promotional ? 1 : 0}">
                            </tr>
                        `;
                        settlementItemsBody.innerHTML += settlementRow + hiddenFields;
                        hiddenFields = ''; // Only add once
                    });
                }
            });

                    // Show relevant sections
                    console.log('Displaying settlement table and sections');
                    document.getElementById('noItemsMessage').style.display = 'none';
                    document.getElementById('settlementTableContainer').style.display = 'block';
                    document.getElementById('settlementHelpText').style.display = 'block';
                    document.getElementById('salesSummarySection').style.display = 'block';
                    document.getElementById('expensesSection').style.display = 'block';
                    document.getElementById('cashDetailSection').style.display = 'block';

                    // Initialize balances for all batch rows
                    items.forEach((item, index) => {
                        const batchBreakdown = item.batch_breakdown || [];
                        batchBreakdown.forEach((batch, batchIdx) => {
                            calculateBatchBalance(index, batchIdx);
                        });
                    });
                    console.log('Settlement form loaded successfully');
                })
                .catch(error => {
                    console.error('Error fetching goods issue items:', error);
                    document.getElementById('noItemsMessage').innerHTML = '<span class="text-red-600">Error loading goods issue data. Please try again.</span>';
                    document.getElementById('noItemsMessage').style.display = 'block';
                    document.getElementById('settlementTableContainer').style.display = 'none';
                });
        });

        // Handle clearing the select2 dropdown
        $('#goods_issue_id').on('select2:clear', function() {
            console.log('Goods Issue selection cleared');
            clearSettlementForm();
        });
    </script>
    @endpush
</x-app-layout>
