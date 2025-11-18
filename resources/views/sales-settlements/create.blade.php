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

                        <div class="mb-6">
                            <x-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="2"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('notes') }}</textarea>
                            <x-input-error for="notes" class="mt-2" />
                        </div>

                        <hr class="my-6 border-gray-200">

                        <div id="itemsTableContainer" style="display: none;">
                            <x-detail-table title="Items Issued" :headers="[
                                ['label' => '#', 'align' => 'text-center'],
                                ['label' => 'Product', 'align' => 'text-left'],
                                ['label' => 'Quantity Issued', 'align' => 'text-right'],
                                ['label' => 'UOM', 'align' => 'text-center'],
                                ['label' => 'Batch Breakdown', 'align' => 'text-left'],
                                ['label' => 'Total Value', 'align' => 'text-right'],
                            ]">
                                <tbody id="itemsBody">
                                    <!-- Items will be loaded from selected goods issue -->
                                </tbody>
                                <x-slot name="footer">
                                    <tr class="border-t-2 border-gray-300" id="itemsFooter" style="display: none;">
                                        <td colspan="5" class="py-1 px-2 text-right font-bold text-lg">Grand Total:</td>
                                        <td class="py-1 px-2 text-right font-bold text-lg text-emerald-600"
                                            id="grandTotal">₨ 0.00</td>
                                    </tr>
                                </x-slot>
                            </x-detail-table>
                        </div>

                        <p class="text-sm text-gray-500 mt-2" id="noItemsMessage">
                            Select a Goods Issue to load product details
                        </p>

                        <hr class="my-6 border-gray-200">

                        <div id="settlementTableContainer" style="display: none;">
                            <x-detail-table title="Batch-wise Settlement (Sold/Returned/Shortage)" :headers="[
                                ['label' => 'Product / Batch', 'align' => 'text-left'],
                                ['label' => 'Issued', 'align' => 'text-right'],
                                ['label' => 'Price', 'align' => 'text-right'],
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
                                        <td colspan="3" class="py-2 px-2 text-right font-bold text-base">Grand Totals:
                                        </td>
                                        <td class="py-2 px-2 text-right font-bold text-base text-green-700"
                                            id="grandTotalSold">0</td>
                                        <td class="py-2 px-2 text-right font-bold text-base text-blue-700"
                                            id="grandTotalReturned">0</td>
                                        <td class="py-2 px-2 text-right font-bold text-base text-red-700"
                                            id="grandTotalShortage">0</td>
                                        <td class="py-2 px-2 text-right font-bold text-base" id="grandTotalBalance">
                                            0</td>
                                    </tr>
                                </x-slot>
                            </x-detail-table>
                        </div>
                        <p class="text-sm text-blue-600 mt-2" style="display: none;" id="settlementHelpText">
                            💡 Tip: When you enter Sold quantity, the remaining will auto-calculate. You can then adjust
                            Returned and Shortage as needed.
                        </p>

                        <hr class="my-6 border-gray-200">

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
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Net Sale</label>
                                        <input type="number" id="summary_net_sale" name="summary_net_sale" readonly
                                            class="mt-1 block w-full text-right font-bold text-green-700 bg-green-50 border-green-200 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Recovery</label>
                                        <input type="number" id="summary_recovery" name="summary_recovery" step="0.01"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateSalesSummary()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Credit Recovery</label>
                                        <input type="number" id="summary_credit_recovery" name="summary_credit_recovery" step="0.01"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateSalesSummary()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Total Sale</label>
                                        <input type="number" id="summary_total_sale" readonly
                                            class="mt-1 block w-full text-right font-bold text-blue-700 bg-blue-50 border-blue-200 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Credit</label>
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
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Expenses</label>
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
                                        <input type="number" id="summary_cash_received" name="summary_cash_received"
                                            step="0.01"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateSalesSummary()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label
                                            class="text-xs font-semibold text-gray-600 block mb-1">Short/Excess</label>
                                        <input type="number" id="summary_short_excess" readonly
                                            class="mt-1 block w-full text-right font-bold bg-purple-50 border-purple-200 rounded-md text-sm px-2 py-1"
                                            value="0.00" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="expensesSection" style="display: none;" class="mb-6">
                            <div
                                class="bg-gradient-to-br from-red-50 to-orange-50 p-6 rounded-lg border-2 border-red-200">
                                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Expenses Detail
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
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">DA</label>
                                        <input type="number" id="expense_da" name="expense_da" step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Claim
                                            Amount</label>
                                        <input type="number" id="expense_claim" name="expense_claim" step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">Scheme</label>
                                        <input type="number" id="expense_scheme" name="expense_scheme" step="0.01"
                                            min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                    <div class="bg-white p-3 rounded-md shadow-sm border">
                                        <label class="text-xs font-semibold text-gray-600 block mb-1">%age</label>
                                        <input type="number" id="expense_percentage" name="expense_percentage"
                                            step="0.01" min="0"
                                            class="mt-1 block w-full text-right font-bold border-gray-300 rounded-md text-sm px-2 py-1"
                                            oninput="updateExpensesTotal()" value="0.00" />
                                    </div>
                                </div>
                                <div class="mt-4 bg-white p-4 rounded-md shadow-sm border-2 border-red-300">
                                    <div class="flex justify-between items-center">
                                        <span class="text-base font-bold text-gray-700">Total Expenses:</span>
                                        <span class="text-2xl font-bold text-red-700" id="totalExpensesDisplay">₨
                                            0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

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

        // Function to calculate financial balance
        function calculateFinancialBalance() {
            const cashSales = parseFloat(document.getElementById('cash_sales_amount').value) || 0;
            const chequeSales = parseFloat(document.getElementById('cheque_sales_amount').value) || 0;
            const creditSales = parseFloat(document.getElementById('credit_sales_amount').value) || 0;
            const cashCollected = parseFloat(document.getElementById('cash_collected').value) || 0;
            const chequesCollected = parseFloat(document.getElementById('cheques_collected').value) || 0;
            const expensesClaimed = parseFloat(document.getElementById('expenses_claimed').value) || 0;

            const totalSales = cashSales + chequeSales + creditSales;
            const totalCollection = cashCollected + chequesCollected - expensesClaimed;
            const financialBalance = totalSales - totalCollection;

            // Update displays
            document.getElementById('totalSalesDisplay').textContent = 'Rs ' + totalSales.toLocaleString('en-PK', {minimumFractionDigits: 2});
            document.getElementById('totalCollectionDisplay').textContent = 'Rs ' + totalCollection.toLocaleString('en-PK', {minimumFractionDigits: 2});

            const balanceDisplay = document.getElementById('financialBalanceDisplay');
            balanceDisplay.textContent = 'Rs ' + financialBalance.toLocaleString('en-PK', {minimumFractionDigits: 2});

            // Color code the balance
            if (Math.abs(financialBalance) < 0.01) {
                balanceDisplay.className = 'font-bold text-lg ml-2 text-green-600';
            } else if (financialBalance > 0) {
                balanceDisplay.className = 'font-bold text-lg ml-2 text-orange-600';
            } else {
                balanceDisplay.className = 'font-bold text-lg ml-2 text-red-600';
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

            // Update quantity displays
            document.getElementById('grandTotalSold').textContent = grandSold;
            document.getElementById('grandTotalReturned').textContent = grandReturned;
            document.getElementById('grandTotalShortage').textContent = grandShortage;

            const balanceElement = document.getElementById('grandTotalBalance');
            balanceElement.textContent = grandBalance;

            // Color code quantity balance
            if (grandBalance === 0) {
                balanceElement.className = 'py-2 px-2 text-right font-bold text-base text-green-600';
            } else {
                balanceElement.className = 'py-2 px-2 text-right font-bold text-base text-red-600';
            }

            // Update value displays
            const formatPKR = (val) => '₨ ' + val.toLocaleString('en-PK', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            document.getElementById('grandTotalSoldValue').textContent = formatPKR(grandSoldValue);
            document.getElementById('grandTotalReturnValue').textContent = formatPKR(grandReturnValue);
            document.getElementById('grandTotalShortageValue').textContent = formatPKR(grandShortageValue);
            document.getElementById('grandTotalBalanceValue').textContent = formatPKR(grandBalanceValue);
            document.getElementById('grandTotalIssuedValue').textContent = formatPKR(grandIssuedValue);

            const valueCheckElement = document.getElementById('valueBalanceCheck');
            valueCheckElement.textContent = formatPKR(grandBalanceValue);

            // Color code value balance - must be zero
            if (Math.abs(grandBalanceValue) < 0.01) {
                valueCheckElement.className = 'py-3 px-2 text-right font-bold text-2xl text-green-700';
            } else {
                valueCheckElement.className = 'py-3 px-2 text-right font-bold text-2xl text-red-700';
            }

            // Update Sales Summary with sold value
            document.getElementById('summary_net_sale').value = grandSoldValue.toFixed(2);
            document.getElementById('summary_credit').value = grandSoldValue.toFixed(2);
            updateSalesSummary();
        }

        // Sales Summary calculations
        function updateSalesSummary() {
            const netSale = parseFloat(document.getElementById('summary_net_sale').value) || 0;
            const recovery = parseFloat(document.getElementById('summary_recovery').value) || 0;
            const creditRecovery = parseFloat(document.getElementById('summary_credit_recovery').value) || 0;
            const credit = parseFloat(document.getElementById('summary_credit').value) || 0;
            const expenses = parseFloat(document.getElementById('summary_expenses').value) || 0;
            const cashReceived = parseFloat(document.getElementById('summary_cash_received').value) || 0;

            const totalSale = netSale + recovery + creditRecovery;
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

        // Expenses Detail calculations
        function updateExpensesTotal() {
            const tollTax = parseFloat(document.getElementById('expense_toll_tax').value) || 0;
            const da = parseFloat(document.getElementById('expense_da').value) || 0;
            const claim = parseFloat(document.getElementById('expense_claim').value) || 0;
            const scheme = parseFloat(document.getElementById('expense_scheme').value) || 0;
            const percentage = parseFloat(document.getElementById('expense_percentage').value) || 0;

            const totalExpenses = tollTax + da + claim + scheme + percentage;

            document.getElementById('totalExpensesDisplay').textContent = '₨ ' + totalExpenses.toLocaleString('en-PK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            document.getElementById('summary_expenses').value = totalExpenses.toFixed(2);
            updateSalesSummary();
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

        document.getElementById('goods_issue_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (!selectedOption.value) {
                document.getElementById('itemsBody').innerHTML = '';
                document.getElementById('settlementItemsBody').innerHTML = '';
                document.getElementById('itemsFooter').style.display = 'none';
                document.getElementById('itemsTableContainer').style.display = 'none';
                document.getElementById('settlementTableContainer').style.display = 'none';
                document.getElementById('settlementHelpText').style.display = 'none';
                document.getElementById('noItemsMessage').style.display = 'block';
                document.getElementById('salesSummarySection').style.display = 'none';
                document.getElementById('expensesSection').style.display = 'none';
                document.getElementById('cashDetailSection').style.display = 'none';
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
                            <tr class="border-b border-gray-200 text-sm ${rowClass}">
                                <td class="py-1 px-2">
                                    ${isFirst ? productAggregateInputs : ''}
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][stock_batch_id]" value="${b.stock_batch_id || ''}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][batch_code]" value="${b.batch_code || ''}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][quantity_issued]" value="${b.quantity || 0}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][unit_cost]" value="${b.unit_cost || item.unit_cost}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][selling_price]" value="${b.selling_price || 0}" />
                                    <input type="hidden" name="items[${index}][batches][${bIndex}][is_promotional]" value="${b.is_promotional ? 1 : 0}" />
                                    ${isFirst ? `<div class="font-semibold text-gray-900">${item.product.product_code}</div><div class="text-xs text-gray-500 mb-1">${item.product.product_name}</div>` : ''}
                                    <div class="text-xs ${b.is_promotional ? 'text-orange-700 font-bold' : 'text-gray-600'}">
                                        ${b.is_promotional ? '🎁 ' : ''}${b.batch_code || 'N/A'}
                                    </div>
                                </td>
                                <td class="py-1 px-2 text-right font-medium">${parseFloat(b.quantity).toLocaleString('en-PK', {minimumFractionDigits: 0})}</td>
                                <td class="py-1 px-2 text-right">₨${parseFloat(b.selling_price).toFixed(2)}</td>
                                <td class="py-1 px-2">
                                    <input type="number"
                                        name="items[${index}][batches][${bIndex}][quantity_sold]"
                                        step="0.001" min="0" max="${b.quantity}"
                                        class="border-gray-300 rounded-md shadow-sm w-20 text-sm text-right batch-input"
                                        data-item-index="${index}" data-batch-index="${bIndex}" data-type="sold"
                                        oninput="autoFillShortage(${index}, ${bIndex}, 'sold')"
                                        value="0" />
                                </td>
                                <td class="py-1 px-2">
                                    <input type="number"
                                        name="items[${index}][batches][${bIndex}][quantity_returned]"
                                        step="0.001" min="0" max="${b.quantity}"
                                        class="border-gray-300 rounded-md shadow-sm w-20 text-sm text-right batch-input"
                                        data-item-index="${index}" data-batch-index="${bIndex}" data-type="returned"
                                        oninput="autoFillShortage(${index}, ${bIndex}, 'returned')"
                                        value="0" />
                                </td>
                                <td class="py-1 px-2">
                                    <input type="number"
                                        name="items[${index}][batches][${bIndex}][quantity_shortage]"
                                        step="0.001" min="0" max="${b.quantity}"
                                        class="border-gray-300 rounded-md shadow-sm w-20 text-sm text-right batch-input"
                                        data-item-index="${index}" data-batch-index="${bIndex}" data-type="shortage"
                                        oninput="calculateBatchBalance(${index}, ${bIndex})"
                                        value="0" />
                                </td>
                                <td class="py-1 px-2 text-right">
                                    <span id="balance-${index}-${bIndex}" class="font-bold text-red-600">${parseFloat(b.quantity).toFixed(3)}</span>
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

            // Show grand total and tables
            document.getElementById('grandTotal').textContent = `₨ ${grandTotal.toLocaleString('en-PK', {minimumFractionDigits: 2})}`;
            document.getElementById('itemsFooter').style.display = 'table-row';
            document.getElementById('itemsTableContainer').style.display = 'block';
            document.getElementById('settlementTableContainer').style.display = 'block';
            document.getElementById('settlementHelpText').style.display = 'block';
            document.getElementById('noItemsMessage').style.display = 'none';
            
            // Show the new sections
            document.getElementById('salesSummarySection').style.display = 'block';
            document.getElementById('expensesSection').style.display = 'block';
            document.getElementById('cashDetailSection').style.display = 'block';
        });
    </script>
</x-app-layout>