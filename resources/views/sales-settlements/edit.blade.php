<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Edit Sales Settlement: {{ $settlement->settlement_number }}
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            <a href="{{ route('sales-settlements.show', $settlement) }}"
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
            <x-validation-errors class="mb-4" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <form method="POST" action="{{ route('sales-settlements.update', $settlement) }}" id="settlementForm">
                    @csrf
                    @method('PUT')
                    <div class="pt-6 pl-6 pr-6">
                        {{-- Section 1: Date & Goods Issue Selection --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-2" x-data="goodsIssueSelector()">
                            <div>
                                <x-label for="settlement_date" value="Settlement Date" class="required" />
                                <x-input id="settlement_date" name="settlement_date" type="date"
                                    class="mt-1 block w-full"
                                    :value="old('settlement_date', $settlement->settlement_date->format('Y-m-d'))"
                                    required />
                                <x-input-error for="settlement_date" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="goods_issue_id" value="Goods Issue" class="required" />
                                <select id="goods_issue_id" name="goods_issue_id"
                                    class="select2-goods-issue border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    required disabled>
                                    <option value="{{ $settlement->goods_issue_id }}" selected>
                                        {{ $settlement->goodsIssue->issue_number }} - {{
                                        $settlement->goodsIssue->employee->full_name }} ({{
                                        $settlement->goodsIssue->issue_date->format('d M Y') }})
                                    </option>
                                </select>
                                <input type="hidden" name="goods_issue_id" value="{{ $settlement->goods_issue_id }}" />
                                <x-input-error for="goods_issue_id" class="mt-2" />
                            </div>

                        </div>
                        <p class="text-sm text-blue-600" id="noItemsMessage" style="display: none;">
                            Loading settlement items...
                        </p>
                    </div>

                    <div class="mb-2">

                        {{-- Section 2: Combined Batch-wise Settlement Table --}}
                        <div id="settlementTableContainer"
                            style="display: none; text-align: center; margin: 0px; padding: 0px">
                            <x-detail-table
                                title="Batch-wise Settlement (B/F + Issued = Total Available | Sold + Returned + Shortage + Balance = Total)"
                                :headers="[
                                ['label' => 'Product / Batch', 'align' => 'text-left'],
                                ['label' => 'Batch Breakdown', 'align' => 'text-left'],
                                ['label' => 'B/F (In)', 'align' => 'text-right'],
                                ['label' => 'Qty Issued', 'align' => 'text-right'],
                                ['label' => 'Price', 'align' => 'text-right'],
                                ['label' => 'Value', 'align' => 'text-right'],
                                ['label' => 'Sold', 'align' => 'text-right'],
                                ['label' => 'Returned', 'align' => 'text-right'],
                                ['label' => 'Shortage', 'align' => 'text-right'],
                                ['label' => 'B/F (Out)', 'align' => 'text-right'],
                            ]">
                                <tbody id="settlementItemsBody">
                                    <!-- Settlement items will be populated here -->
                                </tbody>
                                <x-slot name="footer">
                                    <tr class="border-t-2 border-gray-300 bg-gray-100">
                                        <td colspan="2" class="py-1 px-1 text-right font-bold text-sm">Grand Totals:
                                        </td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-purple-700"
                                            id="grandTotalBFIn">-</td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-purple-700"
                                            id="grandTotalIssued">0</td>
                                        <td colspan="2" class="py-1 px-1"></td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-green-700"
                                            id="grandTotalSold">0</td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-blue-700"
                                            id="grandTotalReturned">0</td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-red-700"
                                            id="grandTotalShortage">0</td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-orange-700"
                                            id="grandTotalBalance">0</td>
                                    </tr>
                                    <tr class="border-t border-gray-300 bg-blue-50">
                                        <td colspan="2" class="py-1 px-1 text-right font-bold text-sm">Value Totals:
                                        </td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-purple-700"
                                            id="grandTotalBFInValue">-</td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-purple-700"
                                            id="grandTotalIssuedValue">₨ 0.00</td>
                                        <td colspan="2" class="py-1 px-1"></td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-green-700"
                                            id="grandTotalSoldValue">₨ 0.00</td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-blue-700"
                                            id="grandTotalReturnValue">₨ 0.00</td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-red-700"
                                            id="grandTotalShortageValue">₨ 0.00</td>
                                        <td class="py-1 px-1 text-right font-bold text-sm text-orange-700"
                                            id="grandTotalBalanceValue">₨ 0.00</td>
                                    </tr>
                                </x-slot>
                            </x-detail-table>
                        </div>
                        <p class="text-sm text-blue-600 mt-2 p-2" style="display: none;" id="settlementHelpText">
                            💡 Tip: When you enter Sold quantity, the remaining will auto-calculate. You can then adjust
                            Returned and Shortage as needed.
                        </p>
                    </div>


                    <div class="pr-6 pl-6 pt-2">
                        {{-- Section 3: Side-by-Side Cash Detail, Expense Detail, and Sales Summary --}}
                        <div id="expenseAndSalesSummarySection" style="display: none;" class="mb-4">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                                <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Cash Reconciliation & Settlement
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                {{-- LEFT SIDE: Cash Detail (Denomination Breakdown) --}}
                                <div class="bg-white rounded-lg border border-gray-300 overflow-hidden">
                                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-3 py-2">
                                        <h4 class="text-sm font-bold text-white">Cash Detail (Denomination Breakdown)
                                        </h4>
                                    </div>
                                    <div class="p-3">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1.5 px-2 text-left text-gray-700">Denomination</th>
                                                    <th class="py-1.5 px-2 text-right text-gray-700">Qty</th>
                                                    <th class="py-1.5 px-2 text-right text-gray-700">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr>
                                                    <td class="py-1 px-2">₨ 5,000 Notes</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="denom_5000" name="denom_5000" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td class="py-1 px-2 text-right font-semibold"
                                                        id="denom_5000_total">₨
                                                        0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">₨ 1,000 Notes</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="denom_1000" name="denom_1000" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td class="py-1 px-2 text-right font-semibold"
                                                        id="denom_1000_total">₨
                                                        0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">₨ 500 Notes</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="denom_500" name="denom_500" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td class="py-1 px-2 text-right font-semibold" id="denom_500_total">
                                                        ₨
                                                        0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">₨ 100 Notes</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="denom_100" name="denom_100" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td class="py-1 px-2 text-right font-semibold" id="denom_100_total">
                                                        ₨
                                                        0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">₨ 50 Notes</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="denom_50" name="denom_50" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td class="py-1 px-2 text-right font-semibold" id="denom_50_total">₨
                                                        0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">₨ 20 Notes</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="denom_20" name="denom_20" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td class="py-1 px-2 text-right font-semibold" id="denom_20_total">₨
                                                        0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">₨ 10 Notes</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="denom_10" name="denom_10" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td class="py-1 px-2 text-right font-semibold" id="denom_10_total">₨
                                                        0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">Loose Cash/Coins</td>
                                                    <td class="py-1 px-2 text-right">-</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="denom_coins" name="denom_coins" min="0"
                                                            step="0.01"
                                                            class="w-20 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                </tr>
                                                <tr class="bg-green-100 border-t-2 border-green-300">
                                                    <td colspan="2" class="py-1.5 px-2 font-bold text-green-900">Total
                                                        Physical Cash</td>
                                                    <td class="py-1.5 px-2 text-right font-bold text-green-900"
                                                        id="totalCashDisplay">₨ 0.00</td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        {{-- Credit Sales, Bank Transfer and Cheque Payment Links with Totals --}}
                                        <div class="mt-3 space-y-3">
                                            {{-- Credit Sales --}}
                                            <div
                                                class="border border-orange-300 rounded-lg overflow-hidden bg-orange-50">
                                                <button type="button"
                                                    class="w-full bg-gradient-to-r from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 text-white px-4 py-2.5 font-semibold text-sm shadow-md transition flex items-center justify-center gap-2"
                                                    onclick="window.dispatchEvent(new CustomEvent('open-credit-sales-modal'))">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                                    </svg>
                                                    Creditors / Credit Sales Breakdown
                                                </button>
                                                <div class="px-4 py-2 bg-white border-t border-orange-200">
                                                    <div class="flex justify-between items-center mb-1">
                                                        <span class="text-xs font-semibold text-orange-900">Total Credit
                                                            Sales:</span>
                                                        <span class="text-sm font-bold text-orange-700"
                                                            id="creditSalesTotalDisplay">₨ 0.00</span>
                                                    </div>
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-xs font-semibold text-green-900">Total
                                                            Recovery:</span>
                                                        <span class="text-sm font-bold text-green-700"
                                                            id="creditRecoveryTotalDisplay">₨ 0.00</span>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Bank Transfer --}}
                                            <div class="border border-blue-300 rounded-lg overflow-hidden bg-blue-50">
                                                <button type="button"
                                                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-4 py-2.5 font-semibold text-sm shadow-md transition flex items-center justify-center gap-2"
                                                    onclick="window.dispatchEvent(new CustomEvent('open-bank-transfer-modal'))">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                    </svg>
                                                    Bank Transfer / Online Payment
                                                </button>
                                                <div
                                                    class="px-4 py-2 bg-white border-t border-blue-200 flex justify-between items-center">
                                                    <span class="text-xs font-semibold text-blue-900">Total Bank
                                                        Transfers:</span>
                                                    <span class="text-sm font-bold text-blue-700"
                                                        id="bankTransferTotalDisplay">₨ 0.00</span>
                                                </div>
                                            </div>

                                            {{-- Cheque Payment --}}
                                            <div
                                                class="border border-purple-300 rounded-lg overflow-hidden bg-purple-50">
                                                <button type="button"
                                                    class="w-full bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-4 py-2.5 font-semibold text-sm shadow-md transition flex items-center justify-center gap-2"
                                                    onclick="window.dispatchEvent(new CustomEvent('open-cheque-payment-modal'))">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    Cheque Payments
                                                </button>
                                                <div
                                                    class="px-4 py-2 bg-white border-t border-purple-200 flex justify-between items-center">
                                                    <span class="text-xs font-semibold text-purple-900">Total
                                                        Cheques:</span>
                                                    <span class="text-sm font-bold text-purple-700"
                                                        id="chequeTotalDisplay">₨ 0.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Hidden inputs for totals (will be populated by modals) --}}
                                        <input type="hidden" id="credit_sales_amount" name="credit_sales_amount"
                                            value="0.00" />
                                        <input type="hidden" id="credit_recoveries_total" name="credit_recoveries_total"
                                            value="0.00" />
                                        <input type="hidden" id="total_bank_transfers" name="total_bank_transfers"
                                            value="0.00" />
                                        <input type="hidden" id="total_cheques" name="total_cheques" value="0.00" />

                                        {{-- Grand Total --}}
                                        <div
                                            class="mt-3 p-2 bg-gradient-to-r from-emerald-100 to-emerald-50 border-2 border-emerald-400 rounded">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-bold text-emerald-900">💰 Total Cash
                                                    Received</span>
                                                <span class="text-lg font-bold text-emerald-900"
                                                    id="grandTotalCashDisplay">₨
                                                    0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- RIGHT SIDE: Expense Detail Table --}}
                                <div class="bg-white rounded-lg border border-gray-300 overflow-hidden">
                                    <div class="bg-gradient-to-r from-red-500 to-red-600 px-3 py-2">
                                        <h4 class="text-sm font-bold text-white">Expense Detail</h4>
                                    </div>
                                    <div class="p-3">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1.5 px-2 text-left text-gray-700">Description</th>
                                                    <th class="py-1.5 px-2 text-right text-gray-700">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr>
                                                    <td class="py-1 px-2">Toll Tax (52250)</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="expense_toll_tax"
                                                            name="expense_toll_tax" step="0.01" min="0"
                                                            class="w-24 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateExpensesTotal()" value="0.00" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">AMR Powder (52230)</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="expense_amr_powder_claim"
                                                            name="expense_amr_powder_claim" step="0.01" min="0"
                                                            class="w-24 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateExpensesTotal()" value="0.00" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">AMR Liquid (52240)</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="expense_amr_liquid_claim"
                                                            name="expense_amr_liquid_claim" step="0.01" min="0"
                                                            class="w-24 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateExpensesTotal()" value="0.00" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">Scheme Discount Expense (52270)</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="expense_scheme" name="expense_scheme"
                                                            step="0.01" min="0"
                                                            class="w-24 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateExpensesTotal()" value="0.00" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">
                                                        <button type="button"
                                                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 underline"
                                                            onclick="window.dispatchEvent(new CustomEvent('open-advance-tax-modal'))">
                                                            Advance Tax (1171)
                                                        </button>
                                                    </td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="expense_advance_tax"
                                                            name="expense_advance_tax" step="0.01" min="0"
                                                            class="w-24 text-right border-gray-300 rounded text-xs px-1 py-0.5 bg-gray-100"
                                                            value="0.00" readonly />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">Food/Salesman/Loader Charges (52260)</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="expense_food_charges"
                                                            name="expense_food_charges" step="0.01" min="0"
                                                            class="w-24 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateExpensesTotal()" value="0.00" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">Percentage Expense (52280)</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="expense_percentage"
                                                            name="expense_percentage" step="0.01" min="0"
                                                            class="w-24 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateExpensesTotal()" value="0.00" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">Miscellaneous Expenses (52110)</td>
                                                    <td class="py-1 px-2 text-right">
                                                        <input type="number" id="expense_miscellaneous_amount"
                                                            name="expense_miscellaneous_amount" step="0.01" min="0"
                                                            class="w-24 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateExpensesTotal()" value="0.00" />
                                                    </td>
                                                </tr>
                                                <tr class="bg-red-100 border-t-2 border-red-300">
                                                    <td class="py-1.5 px-2 font-bold text-red-900">Total Expenses</td>
                                                    <td class="py-1.5 px-2 text-right font-bold text-red-900"
                                                        id="totalExpensesDisplay">₨ 0.00</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- THIRD COLUMN: Sales Summary (Auto-Calculating) --}}
                                <div class="bg-white rounded-lg border border-gray-300 overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2">
                                        <h4 class="text-sm font-bold text-white">Sales Summary</h4>
                                    </div>
                                    <div class="p-3">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1.5 px-2 text-left text-gray-700">Description</th>
                                                    <th class="py-1.5 px-2 text-right text-gray-700">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr>
                                                    <td class="py-1 px-2">Net Sale (Sold Items)</td>
                                                    <td class="py-1 px-2 text-right font-semibold text-green-700"
                                                        id="summary_net_sale_display">₨ 0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">Recovery (from customers)</td>
                                                    <td class="py-1 px-2 text-right font-semibold text-teal-700"
                                                        id="summary_recovery_display">₨ 0.00</td>
                                                </tr>
                                                <tr class="bg-blue-100 border-t-2 border-blue-300">
                                                    <td class="py-1.5 px-2 font-bold text-blue-900">Total Sale</td>
                                                    <td class="py-1.5 px-2 text-right font-bold text-blue-900"
                                                        id="summary_total_sale_display">₨ 0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2">Credit (to customers)</td>
                                                    <td class="py-1 px-2 text-right font-semibold text-orange-700"
                                                        id="summary_credit_display">₨ 0.00</td>
                                                </tr>
                                                <tr class="bg-gray-50">
                                                    <td class="py-1 px-2 font-semibold">Balance</td>
                                                    <td class="py-1 px-2 text-right font-semibold"
                                                        id="summary_balance_display">
                                                        ₨ 0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-2 text-red-700">Expenses</td>
                                                    <td class="py-1 px-2 text-right font-semibold text-red-700"
                                                        id="summary_expenses_display">₨ 0.00</td>
                                                </tr>
                                                <tr class="bg-indigo-50">
                                                    <td class="py-1.5 px-2 font-bold text-indigo-900">Net Balance</td>
                                                    <td class="py-1.5 px-2 text-right font-bold text-indigo-900"
                                                        id="summary_net_balance_display">₨ 0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-2">
                                                        <div>Cash Received (counted)</div>
                                                        <div class="text-xs text-gray-500 italic">Physical Cash + Bank
                                                            Transfer + Cheques</div>
                                                    </td>
                                                    <td class="py-1 px-2 text-right font-semibold text-green-700"
                                                        id="summary_cash_received_display">₨ 0.00</td>
                                                </tr>
                                                <tr
                                                    class="bg-gradient-to-r from-purple-100 to-purple-50 border-t-2 border-purple-300">
                                                    <td class="py-2 px-2 font-bold text-purple-900">Short/Excess</td>
                                                    <td class="py-2 px-2 text-right font-bold text-purple-900 text-base"
                                                        id="summary_short_excess_display">₨ 0.00</td>
                                                </tr>
                                                {{-- Profit Summary Section --}}
                                                <tr class="bg-gray-100 border-t-2 border-gray-400">
                                                    <td colspan="2"
                                                        class="py-1.5 px-2 text-center font-bold text-gray-700 text-xs uppercase tracking-wide">
                                                        Profit Analysis
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2 text-gray-700">Total COGS</td>
                                                    <td class="py-1 px-2 text-right font-semibold text-gray-700"
                                                        id="summary_cogs_display">₨ 0.00</td>
                                                </tr>
                                                <tr class="bg-green-50">
                                                    <td class="py-1 px-2 font-semibold text-green-800">Gross Profit
                                                        (Sales -
                                                        COGS)</td>
                                                    <td class="py-1 px-2 text-right font-bold text-green-700"
                                                        id="summary_gross_profit_display">₨ 0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2 text-xs text-gray-500 pl-4">Gross Margin</td>
                                                    <td class="py-1 px-2 text-right text-xs font-semibold"
                                                        id="summary_gross_margin_display">0.00%</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2 text-red-700">Less: Expenses</td>
                                                    <td class="py-1 px-2 text-right font-semibold text-red-700"
                                                        id="summary_profit_expenses_display">₨ 0.00</td>
                                                </tr>
                                                <tr
                                                    class="bg-gradient-to-r from-emerald-100 to-teal-100 border-t-2 border-emerald-400">
                                                    <td class="py-2 px-2 font-bold text-emerald-900">Net Profit (After
                                                        Expenses)</td>
                                                    <td class="py-2 px-2 text-right font-bold text-emerald-900 text-base"
                                                        id="summary_net_profit_display">₨ 0.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 px-2 text-xs text-gray-500 pl-4">Net Margin</td>
                                                    <td class="py-1 px-2 text-right text-xs font-semibold"
                                                        id="summary_net_margin_display">0.00%</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <div
                                            class="mt-2 p-3 bg-gradient-to-r from-gray-50 to-blue-50 rounded-lg border border-gray-200 text-xs text-gray-700 leading-relaxed">
                                            <strong
                                                class="block mb-2 text-sm text-gray-900 border-b border-gray-300 pb-1">Settlement
                                                Calculation Formula:</strong>
                                            <div class="space-y-1">
                                                <div><span class="font-semibold text-gray-800">Net Sale:</span> Value of
                                                    sold items</div>
                                                <div><span class="font-semibold text-gray-800">Recovery:</span> Payments
                                                    received from customers</div>
                                                <div class="pl-2"><span class="font-bold text-blue-700">Total
                                                        Sale</span> =
                                                    Net Sale + Recovery</div>
                                                <div class="border-t border-gray-200 pt-1 mt-1"><span
                                                        class="font-semibold text-gray-800">Credit:</span> Grand total
                                                    of
                                                    credit extended to customers</div>
                                                <div class="pl-2"><span class="font-bold text-gray-700">Balance</span> =
                                                    Total Sale - Credit</div>
                                                <div class="border-t border-gray-200 pt-1 mt-1"><span
                                                        class="font-semibold text-gray-800">Expenses:</span> Sum of all
                                                    expenses incurred</div>
                                                <div class="pl-2"><span class="font-bold text-indigo-700">Net
                                                        Balance</span>
                                                    = Balance - Expenses</div>
                                                <div class="border-t border-gray-200 pt-1 mt-1"><span
                                                        class="font-semibold text-gray-800">Cash Received:</span> Total
                                                    from
                                                    cash denomination breakdown</div>
                                                <div class="pl-2"><span
                                                        class="font-bold text-purple-700">Short/Excess</span> = Cash
                                                    Received - Net Balance</div>
                                            </div>
                                        </div>

                                        {{-- Hidden input fields for calculations --}}
                                        <input type="hidden" id="summary_net_sale" name="summary_net_sale"
                                            value="0.00" />
                                        <input type="hidden" id="summary_recovery" name="summary_recovery"
                                            value="0.00" />
                                        <input type="hidden" id="summary_total_sale" value="0.00" />
                                        <input type="hidden" id="summary_credit" name="summary_credit" value="0.00" />
                                        <input type="hidden" id="summary_balance" value="0.00" />
                                        <input type="hidden" id="summary_expenses" name="summary_expenses"
                                            value="0.00" />
                                        <input type="hidden" id="summary_net_balance" value="0.00" />
                                        <input type="hidden" id="summary_cash_received" name="summary_cash_received"
                                            value="0.00" />
                                        <input type="hidden" id="summary_short_excess" value="0.00" />

                                        <x-advance-tax-modal
                                            :customers="\App\Models\Customer::orderBy('customer_name')->get(['id', 'customer_name'])"
                                            entriesInputId="advance_taxes" />
                                        <x-bank-transfer-modal
                                            :customers="\App\Models\Customer::orderBy('customer_name')->get(['id', 'customer_name'])"
                                            entriesInputId="bank_transfers" />
                                        <x-cheque-payment-modal
                                            :customers="\App\Models\Customer::orderBy('customer_name')->get(['id', 'customer_name'])"
                                            entriesInputId="cheques" />
                                        <x-credit-sales-modal
                                            :customers="\App\Models\Customer::orderBy('customer_name')->get(['id', 'customer_name'])"
                                            entriesInputId="credit_sales" />
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Section 8: Notes (MOVED TO BOTTOM) --}}
                    <div class="mb-6 p-6">
                        <x-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            placeholder="Add any additional notes or remarks here...">{{ old('notes', $settlement->notes) }}</textarea>
                        <x-input-error for="notes" class="mt-2" />
                    </div>

                    <div class="flex justify-end space-x-3 mb-4 mr-4">
                        <a href="{{ route('sales-settlements.show', $settlement) }}"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            Cancel
                        </a>
                        <x-button type="submit">
                            Update Settlement
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
                selectedGoodsIssue: {{ $settlement->goods_issue_id }},
                loading: false,
            }
        }

        // Pre-loaded settlement data for edit mode
        @php
            $settlementDataArray = [
                'settlement' => $settlement,
                'items' => $settlement->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product' => $item->product,
                        'quantity_issued' => $item->quantity_issued,
                        'quantity_sold' => $item->quantity_sold,
                        'quantity_returned' => $item->quantity_returned,
                        'quantity_shortage' => $item->quantity_shortage,
                        'unit_cost' => $item->unit_cost,
                        'unit_selling_price' => $item->unit_selling_price,
                        'batches' => $item->batches->map(function($batch) {
                            return [
                                'stock_batch_id' => $batch->stock_batch_id,
                                'batch_code' => $batch->batch_code,
                                'quantity_issued' => $batch->quantity_issued,
                                'quantity_sold' => $batch->quantity_sold,
                                'quantity_returned' => $batch->quantity_returned,
                                'quantity_shortage' => $batch->quantity_shortage,
                                'unit_cost' => $batch->unit_cost,
                                'selling_price' => $batch->selling_price,
                                'is_promotional' => $batch->is_promotional,
                                'bf_quantity' => 0,
                            ];
                        }),
                    ];
                }),
                'expenses' => [
                    'toll_tax' => $settlement->expense_toll_tax ?? 0,
                    'amr_powder_claim' => $settlement->expense_amr_powder_claim ?? 0,
                    'amr_liquid_claim' => $settlement->expense_amr_liquid_claim ?? 0,
                    'scheme' => $settlement->expense_scheme ?? 0,
                    'advance_tax' => $settlement->expense_advance_tax ?? 0,
                    'food_charges' => $settlement->expense_food_charges ?? 0,
                    'salesman_charges' => $settlement->expense_salesman_charges ?? 0,
                    'loader_charges' => $settlement->expense_loader_charges ?? 0,
                    'percentage' => $settlement->expense_percentage ?? 0,
                    'miscellaneous_amount' => $settlement->expense_miscellaneous_amount ?? 0,
                ],
                'denominations' => [
                    'denom_5000' => $settlement->denom_5000 ?? 0,
                    'denom_1000' => $settlement->denom_1000 ?? 0,
                    'denom_500' => $settlement->denom_500 ?? 0,
                    'denom_100' => $settlement->denom_100 ?? 0,
                    'denom_50' => $settlement->denom_50 ?? 0,
                    'denom_20' => $settlement->denom_20 ?? 0,
                    'denom_10' => $settlement->denom_10 ?? 0,
                    'denom_coins' => $settlement->denom_coins ?? 0,
                ],
                'credit_sales' => $settlement->creditSales ?? [],
                'bank_transfers' => $settlement->bank_transfers ?? [],
                'cheque_payments' => $settlement->cheque_details ?? [],
                'advance_taxes' => $settlement->advanceTaxes ?? [],
            ];
        @endphp
        const settlementData = @json($settlementDataArray);

        // Initialize on page load
        $(document).ready(function() {
            console.log('Edit mode - Loading settlement data');
            loadSettlementItems();
            loadExpenseData();
            loadCashDenominations();
        });

        // Load settlement items into the form
        function loadSettlementItems() {
            const items = settlementData.items;
            const settlementItemsBody = document.getElementById('settlementItemsBody');
            settlementItemsBody.innerHTML = '';

            items.forEach((item, index) => {
                const batches = item.batches || [];

                if (batches.length > 0) {
                    batches.forEach((batch, batchIdx) => {
                        const batchValue = parseFloat(batch.quantity_issued) * parseFloat(batch.selling_price);
                        const batchBfQuantity = parseFloat(batch.bf_quantity) || 0;

                        const productName = item.product?.name || 'Unknown Product';
                        const productCode = item.product?.product_code || 'N/A';

                        const settlementRow = `
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-1 px-1" style="max-width: 250px; min-width: 180px;">
                                    <div class="font-semibold text-gray-900 break-words">${productName}</div>
                                    <div class="text-xs text-gray-500 break-words">
                                        ${productCode}<br>Batch: ${batch.batch_code}
                                        ${batch.is_promotional ? '<span class="ml-1 px-1.5 py-0.5 bg-purple-100 text-purple-800 text-xs font-bold rounded">PROMO</span>' : ''}
                                    </div>
                                </td>
                                <td class="py-1 px-1" style="max-width: 120px; min-width: 90px;">
                                    <div class="text-xs text-gray-600">
                                        ${parseFloat(batch.quantity_issued).toLocaleString()} × ${parseFloat(batch.selling_price).toFixed(2)}
                                    </div>
                                </td>
                                <td class="py-1 px-1 text-right">
                                    <span id="bf-in-${index}-${batchIdx}" class="font-semibold text-purple-600">${batchBfQuantity > 0 ? batchBfQuantity : '-'}</span>
                                    <input type="hidden" name="items[${index}][batches][${batchIdx}][bf_quantity]" value="${batchBfQuantity}">
                                </td>
                                <td class="py-1 px-1 text-right">
                                    <div class="font-semibold text-gray-900">${parseFloat(batch.quantity_issued).toFixed(0)}</div>
                                </td>
                                <td class="py-1 px-1 text-right text-sm">${parseFloat(batch.selling_price).toFixed(2)}</td>
                                <td class="py-1 px-1 text-right font-bold text-green-700">${batchValue.toLocaleString('en-PK', {minimumFractionDigits: 2})}</td>
                                <td class="py-1 px-1 text-right">
                                    <input type="number"
                                        name="items[${index}][batches][${batchIdx}][quantity_sold]"
                                        class="batch-input w-full text-right border-gray-300 rounded text-sm px-2 py-1"
                                        data-item-index="${index}"
                                        data-batch-index="${batchIdx}"
                                        data-type="sold"
                                        data-bf-quantity="${batchBfQuantity}"
                                        min="0"
                                        max="${parseFloat(batch.quantity_issued) + batchBfQuantity}"
                                        step="1"
                                        value="${batch.quantity_sold}"
                                        oninput="autoFillShortage(${index}, ${batchIdx})">
                                </td>
                                <td class="py-1 px-1 text-right">
                                    <input type="number"
                                        name="items[${index}][batches][${batchIdx}][quantity_returned]"
                                        class="batch-input w-full text-right border-gray-300 rounded text-sm px-2 py-1"
                                        data-item-index="${index}"
                                        data-batch-index="${batchIdx}"
                                        data-type="returned"
                                        min="0"
                                        max="${parseFloat(batch.quantity_issued) + batchBfQuantity}"
                                        step="1"
                                        value="${batch.quantity_returned}"
                                        oninput="autoFillShortage(${index}, ${batchIdx})">
                                </td>
                                <td class="py-1 px-1 text-right">
                                    <input type="number"
                                        name="items[${index}][batches][${batchIdx}][quantity_shortage]"
                                        class="batch-input w-full text-right border-gray-300 rounded text-sm px-2 py-1"
                                        data-item-index="${index}"
                                        data-batch-index="${batchIdx}"
                                        data-type="shortage"
                                        min="0"
                                        max="${parseFloat(batch.quantity_issued) + batchBfQuantity}"
                                        step="1"
                                        value="${batch.quantity_shortage}"
                                        oninput="autoFillShortage(${index}, ${batchIdx}, 'shortage')">
                                </td>
                                <td class="py-1 px-1 text-right">
                                    <span id="bf-out-${index}-${batchIdx}" class="font-bold text-orange-600">0</span>
                                </td>
                            </tr>
                        `;
                        settlementItemsBody.innerHTML += settlementRow;

                        // Create batch-level hidden fields
                        const batchHiddenFields = document.createElement('div');
                        batchHiddenFields.className = 'batch-hidden-fields';
                        batchHiddenFields.innerHTML = `
                            <input type="hidden" name="items[${index}][batches][${batchIdx}][stock_batch_id]" value="${batch.stock_batch_id}">
                            <input type="hidden" name="items[${index}][batches][${batchIdx}][batch_code]" value="${batch.batch_code}">
                            <input type="hidden" name="items[${index}][batches][${batchIdx}][quantity_issued]" value="${batch.quantity_issued}">
                            <input type="hidden" name="items[${index}][batches][${batchIdx}][unit_cost]" value="${batch.unit_cost}">
                            <input type="hidden" name="items[${index}][batches][${batchIdx}][selling_price]" value="${batch.selling_price}">
                            <input type="hidden" name="items[${index}][batches][${batchIdx}][is_promotional]" value="${batch.is_promotional ? 1 : 0}">
                        `;
                        document.getElementById('settlementForm').appendChild(batchHiddenFields);

                        // Add product-level hidden fields only once (on first batch)
                        if (batchIdx === 0) {
                            const hiddenFieldsContainer = document.createElement('div');
                            hiddenFieldsContainer.className = 'hidden-fields-container';
                            hiddenFieldsContainer.innerHTML = `
                                <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                                <input type="hidden" name="items[${index}][quantity_issued]" value="${item.quantity_issued}">
                                <input type="hidden" name="items[${index}][unit_cost]" value="${item.unit_cost}">
                                <input type="hidden" name="items[${index}][selling_price]" value="${item.unit_selling_price}">
                                <input type="hidden" name="items[${index}][quantity_sold]" class="item-${index}-qty-sold" value="${item.quantity_sold}">
                                <input type="hidden" name="items[${index}][quantity_returned]" class="item-${index}-qty-returned" value="${item.quantity_returned}">
                                <input type="hidden" name="items[${index}][quantity_shortage]" class="item-${index}-qty-shortage" value="${item.quantity_shortage}">
                            `;
                            document.getElementById('settlementForm').appendChild(hiddenFieldsContainer);
                        }
                    });
                }
            });

            // Show relevant sections
            document.getElementById('noItemsMessage').style.display = 'none';
            document.getElementById('settlementTableContainer').style.display = 'block';
            document.getElementById('settlementHelpText').style.display = 'block';
            document.getElementById('expenseAndSalesSummarySection').style.display = 'block';

            // Calculate balances for all batch rows
            items.forEach((item, index) => {
                const batches = item.batches || [];
                batches.forEach((batch, batchIdx) => {
                    calculateBatchBalance(index, batchIdx);
                });
            });

            updateGrandTotals();
        }

        // Load expense data
        function loadExpenseData() {
            const expenses = settlementData.expenses;
            document.getElementById('expense_toll_tax').value = expenses.toll_tax;
            document.getElementById('expense_amr_powder_claim').value = expenses.amr_powder_claim;
            document.getElementById('expense_amr_liquid_claim').value = expenses.amr_liquid_claim;
            document.getElementById('expense_scheme').value = expenses.scheme;
            document.getElementById('expense_advance_tax').value = expenses.advance_tax;
            document.getElementById('expense_food_charges').value = expenses.food_charges;
            document.getElementById('expense_percentage').value = expenses.percentage;
            document.getElementById('expense_miscellaneous_amount').value = expenses.miscellaneous_amount;
            updateExpensesTotal();
        }

        // Load cash denominations
        function loadCashDenominations() {
            const denoms = settlementData.denominations;
            document.getElementById('denom_5000').value = denoms.denom_5000;
            document.getElementById('denom_1000').value = denoms.denom_1000;
            document.getElementById('denom_500').value = denoms.denom_500;
            document.getElementById('denom_100').value = denoms.denom_100;
            document.getElementById('denom_50').value = denoms.denom_50;
            document.getElementById('denom_20').value = denoms.denom_20;
            document.getElementById('denom_10').value = denoms.denom_10;
            document.getElementById('denom_coins').value = denoms.denom_coins;
            updateCashTotal();
        }


        // Function to calculate balance for a batch row
        // B/F (Out) = B/F (In) + Qty Issued - Sold - Returned - Shortage
        function calculateBatchBalance(itemIndex, batchIndex) {
            const issuedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_issued]"]`);
            const bfInInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][bf_quantity]"]`);
            const soldInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_sold]"]`);
            const returnedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_returned]"]`);
            const shortageInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_shortage]"]`);

            if (!issuedInput || !soldInput || !returnedInput || !shortageInput) return;

            const issued = Math.round(parseFloat(issuedInput.value) || 0);
            const bfIn = Math.round(parseFloat(bfInInput?.value) || 0);
            const sold = Math.round(parseFloat(soldInput.value) || 0);
            const returned = Math.round(parseFloat(returnedInput.value) || 0);
            const shortage = Math.round(parseFloat(shortageInput.value) || 0);

            // Total available = B/F (In) + Issued
            const totalAvailable = bfIn + issued;
            // B/F (Out) = Total Available - Sold - Returned - Shortage
            const bfOut = totalAvailable - sold - returned - shortage;
            
            const bfOutSpan = document.getElementById(`bf-out-${itemIndex}-${batchIndex}`);

            if (bfOutSpan) {
                bfOutSpan.textContent = bfOut;

                // Color coding: green if zero (fully settled), orange if positive (stock remaining on van), red if negative (error)
                if (bfOut === 0) {
                    bfOutSpan.className = 'font-bold text-green-600';
                } else if (bfOut > 0) {
                    bfOutSpan.className = 'font-bold text-orange-600';
                } else {
                    bfOutSpan.className = 'font-bold text-red-600';
                }
            }

            // Update product totals
            updateProductTotals(itemIndex);
        }

        // Auto-fill B/F (Out) when sold + returned is entered (no longer auto-fill shortage)
        function autoFillShortage(itemIndex, batchIndex, skipField = null) {
            const issuedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_issued]"]`);
            const bfInInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][bf_quantity]"]`);
            const soldInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_sold]"]`);
            const returnedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_returned]"]`);
            const shortageInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_shortage]"]`);

            if (!issuedInput || !soldInput || !returnedInput || !shortageInput) return;

            const issued = Math.round(parseFloat(issuedInput.value) || 0);
            const bfIn = Math.round(parseFloat(bfInInput?.value) || 0);
            const sold = Math.round(parseFloat(soldInput.value) || 0);
            const returned = Math.round(parseFloat(returnedInput.value) || 0);
            const shortage = Math.round(parseFloat(shortageInput.value) || 0);

            // Total available = B/F (In) + Issued
            const totalAvailable = bfIn + issued;

            // No longer auto-fill shortage - user must enter it manually
            // B/F (Out) is calculated automatically
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

        // Function to calculate total COGS (Cost of Goods Sold) based on sold quantities
        function calculateTotalCOGS() {
            let totalCOGS = 0;

            // Sum up COGS for all sold items (quantity_sold * unit_cost)
            document.querySelectorAll('.batch-input[data-type="sold"]').forEach(input => {
                const qty = Math.round(parseFloat(input.value) || 0);
                const itemIdx = input.dataset.itemIndex;
                const batchIdx = input.dataset.batchIndex;

                // Get the unit cost for this batch
                const costInput = document.querySelector(`input[name="items[${itemIdx}][batches][${batchIdx}][unit_cost]"]`);
                const unitCost = costInput ? parseFloat(costInput.value) || 0 : 0;

                totalCOGS += qty * unitCost;
            });

            return totalCOGS;
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

            // Get total issued and B/F (In) from hidden inputs
            let grandBfIn = 0;
            let grandBfInValue = 0;

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

                    // Also get B/F (In) for this batch
                    const bfInput = document.querySelector(`input[name="items[${itemIdx}][batches][${batchIdx}][bf_quantity]"]`);
                    const bfQty = Math.round(parseFloat(bfInput?.value) || 0);
                    grandBfIn += bfQty;
                    grandBfInValue += bfQty * price;
                }
            });

            // B/F (Out) = B/F (In) + Issued - Sold - Returned - Shortage
            const grandTotalAvailable = grandBfIn + grandIssued;
            const grandBfOut = grandTotalAvailable - grandSold - grandReturned - grandShortage;
            const grandBfOutValue = (grandBfInValue + grandIssuedValue) - grandSoldValue - grandReturnValue - grandShortageValue;

            // Update quantity displays with null checks
            const soldEl = document.getElementById('grandTotalSold');
            const returnedEl = document.getElementById('grandTotalReturned');
            const shortageEl = document.getElementById('grandTotalShortage');
            const balanceElement = document.getElementById('grandTotalBalance');
            const bfInEl = document.getElementById('grandTotalBFIn');
            const issuedEl = document.getElementById('grandTotalIssued');

            if (soldEl) soldEl.textContent = grandSold;
            if (returnedEl) returnedEl.textContent = grandReturned;
            if (shortageEl) shortageEl.textContent = grandShortage;
            if (issuedEl) issuedEl.textContent = grandIssued;
            if (bfInEl) bfInEl.textContent = grandBfIn > 0 ? grandBfIn : '-';

            // Update B/F (Out) display
            if (balanceElement) {
                balanceElement.textContent = grandBfOut;
                // Color code: green if zero, orange if positive (stock left on van), red if negative
                if (grandBfOut === 0) {
                    balanceElement.className = 'py-1 px-1 text-right font-bold text-sm text-green-600';
                } else if (grandBfOut > 0) {
                    balanceElement.className = 'py-1 px-1 text-right font-bold text-sm text-orange-600';
                } else {
                    balanceElement.className = 'py-1 px-1 text-right font-bold text-sm text-red-600';
                }
            }

            // Update value displays with null checks
            const formatPKR = (val) => '₨ ' + val.toLocaleString('en-PK', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            const soldValueEl = document.getElementById('grandTotalSoldValue');
            const returnValueEl = document.getElementById('grandTotalReturnValue');
            const shortageValueEl = document.getElementById('grandTotalShortageValue');
            const balanceValueEl = document.getElementById('grandTotalBalanceValue');
            const issuedValueEl = document.getElementById('grandTotalIssuedValue');
            const bfInValueEl = document.getElementById('grandTotalBFInValue');

            if (soldValueEl) soldValueEl.textContent = formatPKR(grandSoldValue);
            if (returnValueEl) returnValueEl.textContent = formatPKR(grandReturnValue);
            if (shortageValueEl) shortageValueEl.textContent = formatPKR(grandShortageValue);
            if (balanceValueEl) balanceValueEl.textContent = formatPKR(grandBfOutValue);
            if (issuedValueEl) issuedValueEl.textContent = formatPKR(grandIssuedValue);
            if (bfInValueEl) bfInValueEl.textContent = grandBfIn > 0 ? formatPKR(grandBfInValue) : '-';

            // Update Sales Summary with sold value (Net Sale = Value of SOLD Items)
            const netSaleEl = document.getElementById('summary_net_sale');
            if (netSaleEl) {
                netSaleEl.value = grandSoldValue.toFixed(2);
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
            const shortExcess = cashReceived - netBalance; // CORRECTED: Cash Received - Net Balance

            document.getElementById('summary_total_sale').value = totalSale.toFixed(2);
            document.getElementById('summary_balance').value = balance.toFixed(2);
            document.getElementById('summary_net_balance').value = netBalance.toFixed(2);
            document.getElementById('summary_short_excess').value = shortExcess.toFixed(2);

            // Calculate and populate sales breakdown for controller
            // Credit sales and cheques are captured separately via modals
            const creditSalesAmount = parseFloat(document.getElementById('credit_sales_amount').value) || 0;
            const chequeSalesAmount = parseFloat(document.getElementById('total_cheques').value) || 0;

            // Cash sales = Net Sale - Credit Sales - Cheque Sales
            const cashSalesAmount = Math.max(0, netSale - creditSalesAmount - chequeSalesAmount);

            // Create or update hidden input for cash_sales_amount if it doesn't exist
            let cashSalesInput = document.getElementById('cash_sales_amount');
            if (!cashSalesInput) {
                cashSalesInput = document.createElement('input');
                cashSalesInput.type = 'hidden';
                cashSalesInput.id = 'cash_sales_amount';
                cashSalesInput.name = 'cash_sales_amount';
                document.getElementById('settlementForm').appendChild(cashSalesInput);
            }
            cashSalesInput.value = cashSalesAmount.toFixed(2);

            // Also update cheque_sales_amount in case it's not set
            let chequeSalesInput = document.getElementById('cheque_sales_amount');
            if (!chequeSalesInput) {
                chequeSalesInput = document.createElement('input');
                chequeSalesInput.type = 'hidden';
                chequeSalesInput.id = 'cheque_sales_amount';
                chequeSalesInput.name = 'cheque_sales_amount';
                document.getElementById('settlementForm').appendChild(chequeSalesInput);
            }
            chequeSalesInput.value = chequeSalesAmount.toFixed(2);

            // Format currency for display
            const formatPKR = (val) => '₨ ' + val.toLocaleString('en-PK', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Update display fields in the Sales Summary table (3rd column)
            const netSaleDisplay = document.getElementById('summary_net_sale_display');
            const recoveryDisplay = document.getElementById('summary_recovery_display');
            const totalSaleDisplay = document.getElementById('summary_total_sale_display');
            const creditDisplay = document.getElementById('summary_credit_display');
            const balanceDisplay = document.getElementById('summary_balance_display');
            const expensesDisplay = document.getElementById('summary_expenses_display');
            const netBalanceDisplay = document.getElementById('summary_net_balance_display');
            const cashReceivedDisplay = document.getElementById('summary_cash_received_display');
            const shortExcessDisplay = document.getElementById('summary_short_excess_display');

            if (netSaleDisplay) netSaleDisplay.textContent = formatPKR(netSale);
            if (recoveryDisplay) recoveryDisplay.textContent = formatPKR(recovery);
            if (totalSaleDisplay) totalSaleDisplay.textContent = formatPKR(totalSale);
            if (creditDisplay) creditDisplay.textContent = formatPKR(credit);
            if (balanceDisplay) balanceDisplay.textContent = formatPKR(balance);
            if (expensesDisplay) expensesDisplay.textContent = formatPKR(expenses);
            if (netBalanceDisplay) netBalanceDisplay.textContent = formatPKR(netBalance);
            if (cashReceivedDisplay) cashReceivedDisplay.textContent = formatPKR(cashReceived);
            if (shortExcessDisplay) shortExcessDisplay.textContent = formatPKR(shortExcess);

            // Calculate and display Profit Analysis
            const totalCOGS = calculateTotalCOGS();
            const grossProfit = netSale - totalCOGS;
            const grossMargin = netSale > 0 ? (grossProfit / netSale) * 100 : 0;
            const netProfit = grossProfit - expenses;
            const netMargin = netSale > 0 ? (netProfit / netSale) * 100 : 0;

            // Update profit display fields
            const cogsDisplay = document.getElementById('summary_cogs_display');
            const grossProfitDisplay = document.getElementById('summary_gross_profit_display');
            const grossMarginDisplay = document.getElementById('summary_gross_margin_display');
            const profitExpensesDisplay = document.getElementById('summary_profit_expenses_display');
            const netProfitDisplay = document.getElementById('summary_net_profit_display');
            const netMarginDisplay = document.getElementById('summary_net_margin_display');

            if (cogsDisplay) cogsDisplay.textContent = formatPKR(totalCOGS);
            if (grossProfitDisplay) {
                grossProfitDisplay.textContent = formatPKR(grossProfit);
                grossProfitDisplay.className = grossProfit >= 0 
                    ? 'py-1 px-2 text-right font-bold text-green-700' 
                    : 'py-1 px-2 text-right font-bold text-red-700';
            }
            if (grossMarginDisplay) {
                grossMarginDisplay.textContent = grossMargin.toFixed(2) + '%';
                grossMarginDisplay.className = grossMargin >= 0 
                    ? 'py-1 px-2 text-right text-xs font-semibold text-green-600' 
                    : 'py-1 px-2 text-right text-xs font-semibold text-red-600';
            }
            if (profitExpensesDisplay) profitExpensesDisplay.textContent = formatPKR(expenses);
            if (netProfitDisplay) {
                netProfitDisplay.textContent = formatPKR(netProfit);
                netProfitDisplay.className = netProfit >= 0 
                    ? 'py-2 px-2 text-right font-bold text-emerald-900 text-base' 
                    : 'py-2 px-2 text-right font-bold text-red-900 text-base';
            }
            if (netMarginDisplay) {
                netMarginDisplay.textContent = netMargin.toFixed(2) + '%';
                netMarginDisplay.className = netMargin >= 0 
                    ? 'py-1 px-2 text-right text-xs font-semibold text-emerald-600' 
                    : 'py-1 px-2 text-right text-xs font-semibold text-red-600';
            }

            // Color code short/excess in the old input field
            const shortExcessEl = document.getElementById('summary_short_excess');
            if (shortExcessEl) {
                if (Math.abs(shortExcess) < 0.01) {
                    shortExcessEl.className = 'mt-1 block w-full text-right font-bold bg-green-100 border-green-300 rounded-md text-sm px-2 py-1';
                } else if (shortExcess > 0) {
                    shortExcessEl.className = 'mt-1 block w-full text-right font-bold bg-red-100 border-red-300 rounded-md text-sm px-2 py-1 text-red-700';
                } else {
                    shortExcessEl.className = 'mt-1 block w-full text-right font-bold bg-blue-100 border-blue-300 rounded-md text-sm px-2 py-1 text-blue-700';
                }
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
            const percentage = parseFloat(document.getElementById('expense_percentage').value) || 0;
            const miscellaneousAmount = parseFloat(document.getElementById('expense_miscellaneous_amount').value) || 0;

            const totalExpenses = tollTax + amrPowder + amrLiquid + scheme + advanceTax +
                                 foodCharges + percentage + miscellaneousAmount;

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
            document.getElementById('expenseAndSalesSummarySection').style.display = 'none';
            const salesSummarySection = document.getElementById('salesSummarySection');
            if (salesSummarySection) salesSummarySection.style.display = 'none';
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
            const bankTransferTotal = parseFloat(document.getElementById('total_bank_transfers')?.value) || 0;
            const chequesTotal = parseFloat(document.getElementById('total_cheques')?.value) || 0;

            // Update individual denomination totals
            document.getElementById('denom_5000_total').textContent = '₨ ' + denom5000.toLocaleString('en-PK');
            document.getElementById('denom_1000_total').textContent = '₨ ' + denom1000.toLocaleString('en-PK');
            document.getElementById('denom_500_total').textContent = '₨ ' + denom500.toLocaleString('en-PK');
            document.getElementById('denom_100_total').textContent = '₨ ' + denom100.toLocaleString('en-PK');
            document.getElementById('denom_50_total').textContent = '₨ ' + denom50.toLocaleString('en-PK');
            document.getElementById('denom_20_total').textContent = '₨ ' + denom20.toLocaleString('en-PK');
            document.getElementById('denom_10_total').textContent = '₨ ' + denom10.toLocaleString('en-PK');

            const physicalCashTotal = denom5000 + denom1000 + denom500 + denom100 + denom50 + denom20 + denom10 + coins;
            const totalCash = physicalCashTotal + bankTransferTotal + chequesTotal;

            // Update physical cash display
            document.getElementById('totalCashDisplay').textContent = '₨ ' + physicalCashTotal.toLocaleString('en-PK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // Update grand total cash display
            const grandTotalDisplay = document.getElementById('grandTotalCashDisplay');
            if (grandTotalDisplay) {
                grandTotalDisplay.textContent = '₨ ' + totalCash.toLocaleString('en-PK', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // Update cash received in sales summary
            document.getElementById('summary_cash_received').value = totalCash.toFixed(2);
            updateSalesSummary();
        }

        // Edit mode - no need for Select2 event handlers since goods issue is fixed
    </script>
    @endpush
</x-app-layout>