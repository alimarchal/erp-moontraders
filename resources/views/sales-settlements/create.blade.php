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
            <x-validation-errors class="mb-4" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <form method="POST" action="{{ route('sales-settlements.store') }}" id="settlementForm">
                    @csrf
                    {{-- Hidden input to store current employee ID for credit sales modal --}}
                    <input type="hidden" id="current_settlement_employee_id" value="">

                    <div class="pt-6 pl-6 pr-6">
                        {{-- Section 1: Date & Goods Issue Selection --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-2" x-data="goodsIssueSelector()">
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
                        <p class="text-sm text-gray-500 " id="noItemsMessage">
                            Select a Goods Issue to load product details
                        </p>
                    </div>

                    <div class="mb-2">

                        {{-- Section 2: Combined Batch-wise Settlement Table --}}
                        <div id="settlementTableContainer"
                            style="display: none; text-align: center; margin: 10px; padding: 0px">
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

                            {{-- ROW 1: Credit Sales Detail | Recoveries Detail | Cheque Payments | Bank Transfers --}}
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6 items-start">
                                {{-- Credit Sales Detail Card --}}
                                <div class="bg-white rounded-lg border border-orange-300 overflow-hidden"
                                    x-data="creditSalesDisplay()">
                                    <div
                                        class="bg-gradient-to-r from-orange-500 to-orange-600 px-3 py-2 flex justify-between items-center">
                                        <h4 class="text-sm font-bold text-white">Credit Sales Detail</h4>
                                        <button type="button" @click="openModal()"
                                            class="text-xs bg-white text-orange-600 px-2 py-0.5 rounded font-semibold hover:bg-orange-50">
                                            + Add More
                                        </button>
                                    </div>
                                    <div class="p-0">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1 px-1 text-left text-black">Customer</th>
                                                    <th class="py-1 px-1 text-right text-black">Sale</th>
                                                    <th class="py-1 px-1 text-right text-black"
                                                        title="Balance with this Salesman">BAL</th>
                                                </tr>
                                            </thead>
                                            <tbody id="creditSalesTableBody">
                                                <tr>
                                                    <td colspan="3"
                                                        class="py-2 px-1 text-center text-black text-xs italic">
                                                        No credit sales entries
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="border-t-2 border-gray-300">
                                                <tr class="bg-orange-50">
                                                    <td
                                                        class="py-1.5 px-1 text-right font-semibold text-orange-900 text-xs">
                                                        Total:</td>
                                                    <td class="py-1.5 px-1 text-right font-bold text-orange-700 text-xs"
                                                        id="creditSalesTotalDisplay">0</td>
                                                    <td class="py-1.5 px-1 text-right font-bold text-blue-700 text-xs"
                                                        id="creditBalanceTotalDisplay">0</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                {{-- Recoveries Detail Card --}}
                                <div class="bg-white rounded-lg border border-green-300 overflow-hidden"
                                    x-data="recoveriesDisplay()">
                                    <div
                                        class="bg-gradient-to-r from-green-500 to-green-600 px-3 py-2 flex justify-between items-center">
                                        <h4 class="text-sm font-bold text-white">Recoveries Detail</h4>
                                        <button type="button" @click="openModal()"
                                            class="text-xs bg-white text-green-600 px-2 py-0.5 rounded font-semibold hover:bg-green-50">
                                            + Add More
                                        </button>
                                    </div>
                                    <div class="p-0">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1 px-1 text-left text-black">Customer</th>
                                                    <th class="py-1 px-1 text-center text-black">Method</th>
                                                    <th class="py-1 px-1 text-left text-black">Bank Account</th>
                                                    <th class="py-1 px-1 text-right text-black">Recovery</th>
                                                </tr>
                                            </thead>
                                            <tbody id="recoveriesTableBody">
                                                <tr>
                                                    <td colspan="4"
                                                        class="py-2 px-1 text-center text-black text-xs italic">
                                                        No recovery entries
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="border-t-2 border-gray-300">
                                                <tr class="bg-green-50">
                                                    <td colspan="3"
                                                        class="py-1.5 px-1 text-right font-semibold text-green-900 text-xs">
                                                        Total:</td>
                                                    <td class="py-1.5 px-1 text-right font-bold text-green-700 text-xs"
                                                        id="creditRecoveryTotalDisplay">0</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                {{-- Cheque Payments Card --}}
                                <div class="bg-white rounded-lg border border-purple-300 overflow-hidden"
                                    x-data="chequePaymentDisplay()">
                                    <div
                                        class="bg-gradient-to-r from-purple-500 to-purple-600 px-3 py-2 flex justify-between items-center">
                                        <h4 class="text-sm font-bold text-white">Cheque Payments</h4>
                                        <button type="button" @click="openModal()"
                                            class="text-xs bg-white text-purple-600 px-2 py-0.5 rounded font-semibold hover:bg-purple-50">
                                            + Add More
                                        </button>
                                    </div>
                                    <div class="p-0">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1 px-1 text-left text-black">Customer</th>
                                                    <th class="py-1 px-1 text-left text-black">Cheque #</th>
                                                    <th class="py-1 px-1 text-left text-black">Bank</th>
                                                    <th class="py-1 px-1 text-left text-black">Deposit Bank</th>
                                                    <th class="py-1 px-1 text-left text-black">Cheque Date</th>
                                                    <th class="py-1 px-1 text-right text-black">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody id="chequePaymentTableBody">
                                                <tr>
                                                    <td colspan="6"
                                                        class="py-2 px-1 text-center text-black text-xs italic">
                                                        No cheque payments
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="border-t-2 border-gray-300">
                                                <tr class="bg-purple-50">
                                                    <td colspan="5"
                                                        class="py-1.5 px-1 text-right font-semibold text-purple-900 text-xs">
                                                        Total:</td>
                                                    <td class="py-1.5 px-1 text-right font-bold text-purple-700 text-xs"
                                                        id="chequeTotalDisplay">0</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                {{-- Bank Transfers Card --}}
                                <div class="bg-white rounded-lg border border-blue-300 overflow-hidden"
                                    x-data="bankTransferDisplay()">
                                    <div
                                        class="bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 flex justify-between items-center">
                                        <h4 class="text-sm font-bold text-white">Bank Transfers</h4>
                                        <button type="button" @click="openModal()"
                                            class="text-xs bg-white text-blue-600 px-2 py-0.5 rounded font-semibold hover:bg-blue-50">
                                            + Add More
                                        </button>
                                    </div>
                                    <div class="p-0">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1 px-1 text-left text-black">Customer</th>
                                                    <th class="py-1 px-1 text-left text-black">Bank</th>
                                                    <th class="py-1 px-1 text-left text-black">Ref #</th>
                                                    <th class="py-1 px-1 text-left text-black">Transfer Date</th>
                                                    <th class="py-1 px-1 text-right text-black">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bankTransferTableBody">
                                                <tr>
                                                    <td colspan="5"
                                                        class="py-2 px-1 text-center text-black text-xs italic">
                                                        No bank transfers
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="border-t-2 border-gray-300">
                                                <tr class="bg-blue-50">
                                                    <td colspan="4"
                                                        class="py-1.5 px-1 text-right font-semibold text-blue-900 text-xs">
                                                        Total:</td>
                                                    <td class="py-1.5 px-1 text-right font-bold text-blue-700 text-xs"
                                                        id="bankTransferTotalDisplay">0</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {{-- ROW 2: Cash Detail | Expense Detail | Sales Summary --}}
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                                {{-- Cash Detail (Denomination Breakdown) Card --}}
                                <div class="bg-white rounded-lg border border-orange-300 overflow-hidden">
                                    <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-3 py-2">
                                        <h4 class="text-sm font-bold text-white text-center">Cash Detail (Denomination
                                            Breakdown)</h4>
                                    </div>
                                    <div class="p-0">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1 px-1 text-left text-black">Denomination</th>
                                                    <th class="py-1 px-1 text-right text-black">Quantity</th>
                                                    <th class="py-1 px-1 text-right text-black">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr style="border-top: 1px solid #000;">
                                                    <td style="padding: 3px 6px; border: none;">5,000 Notes</td>
                                                    <td style="padding: 3px 6px; text-align: right; border: none;">
                                                        <input type="number" id="denom_5000" name="denom_5000" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td style="padding: 3px 6px; text-align: right; font-weight: 600; border: none;"
                                                        id="denom_5000_total">0.00</td>
                                                </tr>
                                                <tr style="border-top: 1px solid #000;">
                                                    <td style="padding: 3px 6px; border: none;">1,000 Notes</td>
                                                    <td style="padding: 3px 6px; text-align: right; border: none;">
                                                        <input type="number" id="denom_1000" name="denom_1000" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td style="padding: 3px 6px; text-align: right; font-weight: 600; border: none;"
                                                        id="denom_1000_total">0.00</td>
                                                </tr>
                                                <tr style="border-top: 1px solid #000;">
                                                    <td style="padding: 3px 6px; border: none;">500 Notes</td>
                                                    <td style="padding: 3px 6px; text-align: right; border: none;">
                                                        <input type="number" id="denom_500" name="denom_500" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td style="padding: 3px 6px; text-align: right; font-weight: 600; border: none;"
                                                        id="denom_500_total">0.00</td>
                                                </tr>
                                                <tr style="border-top: 1px solid #000;">
                                                    <td style="padding: 3px 6px; border: none;">100 Notes</td>
                                                    <td style="padding: 3px 6px; text-align: right; border: none;">
                                                        <input type="number" id="denom_100" name="denom_100" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td style="padding: 3px 6px; text-align: right; font-weight: 600; border: none;"
                                                        id="denom_100_total">0.00</td>
                                                </tr>
                                                <tr style="border-top: 1px solid #000;">
                                                    <td style="padding: 3px 6px; border: none;">50 Notes</td>
                                                    <td style="padding: 3px 6px; text-align: right; border: none;">
                                                        <input type="number" id="denom_50" name="denom_50" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td style="padding: 3px 6px; text-align: right; font-weight: 600; border: none;"
                                                        id="denom_50_total">0.00</td>
                                                </tr>
                                                <tr style="border-top: 1px solid #000;">
                                                    <td style="padding: 3px 6px; border: none;">20 Notes</td>
                                                    <td style="padding: 3px 6px; text-align: right; border: none;">
                                                        <input type="number" id="denom_20" name="denom_20" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td style="padding: 3px 6px; text-align: right; font-weight: 600; border: none;"
                                                        id="denom_20_total">0.00</td>
                                                </tr>
                                                <tr style="border-top: 1px solid #000;">
                                                    <td style="padding: 3px 6px; border: none;">10 Notes</td>
                                                    <td style="padding: 3px 6px; text-align: right; border: none;">
                                                        <input type="number" id="denom_10" name="denom_10" min="0"
                                                            step="1"
                                                            class="w-16 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                    <td style="padding: 3px 6px; text-align: right; font-weight: 600; border: none;"
                                                        id="denom_10_total">0.00</td>
                                                </tr>
                                                <tr style="border-top: 1px solid #000;">
                                                    <td style="padding: 3px 6px; border: none;">Loose Cash/Coins</td>
                                                    <td style="padding: 3px 6px; text-align: right; border: none;">-
                                                    </td>
                                                    <td style="padding: 3px 6px; text-align: right; border: none;">
                                                        <input type="number" id="denom_coins" name="denom_coins" min="0"
                                                            step="0.01"
                                                            class="w-20 text-right border-gray-300 rounded text-xs px-1 py-0.5"
                                                            oninput="updateCashTotal()" value="0" />
                                                    </td>
                                                </tr>
                                                <tr style="background-color: #f0fdf4; border-top: 2px solid #059669;">
                                                    <td colspan="2"
                                                        style="padding: 4px 6px; font-weight: bold; color: #047857;">
                                                        Total Physical Cash
                                                    </td>
                                                    <td style="padding: 4px 6px; text-align: right; font-weight: bold; color: #047857;"
                                                        id="totalCashDisplay">0.00</td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        {{-- Hidden inputs for totals (will be populated by modals) --}}
                                        <input type="hidden" id="credit_sales_amount" name="credit_sales_amount"
                                            value="0.00" />
                                        <input type="hidden" id="credit_recoveries_total" name="credit_recoveries_total"
                                            value="0.00" />
                                        <input type="hidden" id="recoveries_entries" name="recoveries_entries"
                                            value="[]" />
                                        <input type="hidden" id="total_bank_transfers" name="total_bank_transfers"
                                            value="0.00" />
                                        <input type="hidden" id="total_cheques" name="total_cheques" value="0.00" />
                                    </div>
                                </div>

                                {{-- Expense Detail Card --}}
                                <div class="bg-white rounded-lg border border-orange-300 overflow-hidden"
                                    x-data="expenseManager(@js($expenseAccounts))">
                                    <div
                                        class="bg-gradient-to-r from-orange-500 to-orange-600 px-3 py-2 flex justify-between items-center">
                                        <h4 class="text-sm font-bold text-white">Expense Detail</h4>
                                        <button type="button" @click="addExpense()"
                                            class="text-xs bg-white text-orange-600 px-2 py-0.5 rounded font-semibold hover:bg-orange-50">
                                            + Add More
                                        </button>
                                    </div>
                                    <div class="p-0">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1 px-1 text-left text-black">Expense Account</th>
                                                    <th class="py-1 px-1 text-right text-black">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(expense, index) in expenses" :key="expense.id">
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="py-1 px-1 text-xs">
                                                            <template
                                                                x-if="expense.is_predefined && ![20, 70, 71].includes(expense.expense_account_id)">
                                                                <span
                                                                    x-text="expense.label + ' (' + expense.account_code + ')'"></span>
                                                            </template>
                                                            <template
                                                                x-if="expense.is_predefined && expense.expense_account_id === 20">
                                                                <button type="button"
                                                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 underline"
                                                                    @click="window.dispatchEvent(new CustomEvent('open-advance-tax-modal'))">
                                                                    <span
                                                                        x-text="expense.label + ' (' + expense.account_code + ')'"></span>
                                                                </button>
                                                            </template>
                                                            <template
                                                                x-if="expense.is_predefined && expense.expense_account_id === 70">
                                                                <button type="button"
                                                                    class="text-xs font-semibold text-blue-600 hover:text-blue-700 underline"
                                                                    @click="window.dispatchEvent(new CustomEvent('open-amr-powder-modal'))">
                                                                    <span
                                                                        x-text="expense.label + ' (' + expense.account_code + ')'"></span>
                                                                </button>
                                                            </template>
                                                            <template
                                                                x-if="expense.is_predefined && expense.expense_account_id === 71">
                                                                <button type="button"
                                                                    class="text-xs font-semibold text-blue-600 hover:text-blue-700 underline"
                                                                    @click="window.dispatchEvent(new CustomEvent('open-amr-liquid-modal'))">
                                                                    <span
                                                                        x-text="expense.label + ' (' + expense.account_code + ')'"></span>
                                                                </button>
                                                            </template>
                                                            <template x-if="!expense.is_predefined">
                                                                <div class="flex items-center gap-0.5">
                                                                    <select @change="onAccountSelect($event, index)"
                                                                        class="text-[10px] border-gray-300 rounded flex-1 py-0 px-1 h-6">
                                                                        <option value="">Select...</option>
                                                                        <template x-for="account in availableAccounts"
                                                                            :key="account.id">
                                                                            <option :value="account.id"
                                                                                :selected="expense.expense_account_id == account.id"
                                                                                x-text="account.account_code + '-' + account.account_name">
                                                                            </option>
                                                                        </template>
                                                                    </select>
                                                                    <button type="button" @click="removeExpense(index)"
                                                                        class="text-red-500 hover:text-red-700 text-sm leading-none px-0.5"
                                                                        title="Remove">×</button>
                                                                </div>
                                                            </template>
                                                            {{-- Hidden inputs for form submission --}}
                                                            <input type="hidden"
                                                                :name="'expenses[' + index + '][expense_account_id]'"
                                                                :value="expense.expense_account_id" />
                                                            <input type="hidden"
                                                                :name="'expenses[' + index + '][description]'"
                                                                :value="expense.description || expense.label" />
                                                        </td>
                                                        <td class="py-1 px-1 text-right">
                                                            <input type="number" :id="'expense_amount_' + expense.id"
                                                                :name="'expenses[' + index + '][amount]'" step="0.01"
                                                                min="0" x-model.number="expense.amount"
                                                                @input="calculateTotal()"
                                                                :readonly="[20, 70, 71].includes(expense.expense_account_id)"
                                                                :class="[20, 70, 71].includes(expense.expense_account_id) ? 'bg-gray-100' : ''"
                                                                class="w-24 text-right border-gray-300 rounded text-xs px-1 py-0.5" />
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                            <tfoot class="border-t-2 border-gray-300">
                                                <tr class="bg-orange-50">
                                                    <td
                                                        class="py-1.5 px-1 text-right font-semibold text-orange-900 text-xs">
                                                        Total:</td>
                                                    <td class="py-1.5 px-1 text-right font-bold text-orange-700 text-xs"
                                                        id="totalExpensesDisplay"
                                                        x-text="totalExpenses.toLocaleString('en-PK', {minimumFractionDigits: 2, maximumFractionDigits: 2})">
                                                        0.00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                {{-- Sales Summary Card --}}
                                <div class="bg-white rounded-lg border border-orange-300 overflow-hidden">
                                    <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-3 py-2">
                                        <h4 class="text-sm font-bold text-white">Sales Summary</h4>
                                    </div>
                                    <div class="p-0">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-b-2 border-gray-300">
                                                    <th class="py-1 px-1 text-left text-black">Description</th>
                                                    <th class="py-1 px-1 text-right text-black">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-black">Credit Sale Amount</td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-orange-700"
                                                        id="summary_credit_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-black">Cheque Sale Amount</td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-blue-700"
                                                        id="summary_cheque_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-black">Bank Transfer Amount</td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-indigo-700"
                                                        id="summary_bank_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-black">Cash Sale Amount</td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-emerald-700"
                                                        id="summary_cash_display">0.00</td>
                                                </tr>

                                                <tr class="border-t border-gray-200 bg-gray-50">
                                                    <td class="py-1 px-1 text-xs text-black">Net Sale (Sold Items Value)
                                                    </td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-black"
                                                        id="summary_net_sale_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-black">Return Value</td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-blue-700"
                                                        id="summary_return_value_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-black">Shortage Value</td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-red-700"
                                                        id="summary_shortage_value_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-black">Recovery (From Customers)
                                                    </td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-teal-700"
                                                        id="summary_recovery_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-black">Bank Online Recoveries</td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-blue-700"
                                                        id="summary_bank_recovery_display">0.00</td>
                                                </tr>
                                                <tr class="bg-blue-50 border-y-2 border-blue-200">
                                                    <td class="py-1 px-1 text-xs font-semibold text-blue-900">Total Sale
                                                        Amount</td>
                                                    <td class="py-1 px-1 text-right font-bold text-xs text-blue-800"
                                                        id="summary_total_sale_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200 bg-gray-50">
                                                    <td class="py-1 px-1 text-xs text-black">Balance</td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-black"
                                                        id="summary_balance_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-red-700">Less: Expenses</td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-red-700"
                                                        id="summary_expenses_display">0.00</td>
                                                </tr>
                                                <tr class="bg-indigo-50 border-y-2 border-indigo-200">
                                                    <td class="py-1 px-1 text-xs font-semibold text-indigo-900">Net
                                                        Balance</td>
                                                    <td class="py-1 px-1 text-right font-bold text-xs text-indigo-900"
                                                        id="summary_net_balance_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-1 px-1 text-xs text-black">
                                                        Cash Received (denomination + bank + cheques)
                                                        <div class="text-[10px] text-gray-600 italic">
                                                            Physical + bank transfers + cheques (sales only)
                                                        </div>
                                                    </td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs text-emerald-700"
                                                        id="summary_cash_received_display">0.00</td>
                                                </tr>
                                                <tr class="bg-purple-50 border-y-2 border-purple-200">
                                                    <td class="py-1 px-1 text-xs font-semibold text-purple-900">
                                                        Short/Excess</td>
                                                    <td class="py-1 px-1 text-right font-bold text-xs text-purple-900"
                                                        id="summary_short_excess_display">0.00</td>
                                                </tr>
                                                {{-- Profit Analysis Section --}}
                                                <tr class="bg-gray-100 border-t-2 border-gray-300">
                                                    <td colspan="2"
                                                        class="py-1 px-1 text-center font-bold text-black text-xs uppercase tracking-wide">
                                                        Profit Analysis
                                                    </td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-0.5 px-1 text-xs text-gray-700">Total COGS</td>
                                                    <td class="py-0.5 px-1 text-right font-semibold text-xs text-black"
                                                        id="summary_cogs_display">0.00</td>
                                                </tr>
                                                <tr class="bg-green-50 border-t border-green-200">
                                                    <td class="py-0.5 px-1 font-semibold text-xs text-green-800">Gross
                                                        Profit (Sales - COGS)</td>
                                                    <td class="py-0.5 px-1 text-right font-bold text-xs text-green-700"
                                                        id="summary_gross_profit_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-0.5 px-1 text-xs text-gray-500 pl-2">Gross Margin</td>
                                                    <td class="py-0.5 px-1 text-right text-xs font-semibold"
                                                        id="summary_gross_margin_display">0.00%</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-0.5 px-1 text-xs text-red-700">Less: Expenses</td>
                                                    <td class="py-0.5 px-1 text-right font-semibold text-xs text-red-700"
                                                        id="summary_profit_expenses_display">0.00</td>
                                                </tr>
                                                <tr
                                                    class="bg-gradient-to-r from-emerald-100 to-teal-100 border-t-2 border-emerald-400">
                                                    <td class="py-1 px-1 font-bold text-xs text-emerald-900">Net Profit
                                                        (After Expenses)</td>
                                                    <td class="py-1 px-1 text-right font-bold text-xs text-emerald-900"
                                                        id="summary_net_profit_display">0.00</td>
                                                </tr>
                                                <tr class="border-t border-gray-200">
                                                    <td class="py-0.5 px-1 text-xs text-gray-500 pl-2">Net Margin</td>
                                                    <td class="py-0.5 px-1 text-right text-xs font-semibold"
                                                        id="summary_net_margin_display">0.00%</td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        {{-- Hidden input fields for calculations --}}
                                        <input type="hidden" id="summary_net_sale" name="summary_net_sale"
                                            value="0.00" />
                                        <input type="hidden" id="summary_recovery" name="summary_recovery"
                                            value="0.00" />
                                        <input type="hidden" id="summary_return_value" name="summary_return_value"
                                            value="0.00" />
                                        <input type="hidden" id="summary_shortage_value" name="summary_shortage_value"
                                            value="0.00" />
                                        <input type="hidden" id="summary_total_sale" value="0.00" />
                                        <input type="hidden" id="summary_credit" name="summary_credit" value="0.00" />
                                        <input type="hidden" id="summary_balance" name="summary_balance" value="0.00" />
                                        <input type="hidden" id="summary_expenses" name="summary_expenses"
                                            value="0.00" />
                                        <input type="hidden" id="summary_net_balance" value="0.00" />
                                        <input type="hidden" id="summary_cash_received" name="summary_cash_received"
                                            value="0.00" />
                                        <input type="hidden" id="summary_short_excess" value="0.00" />

                                        <x-advance-tax-modal
                                            :customers="\App\Models\Customer::orderBy('customer_name')->get(['id', 'customer_name'])" entriesInputId="advance_taxes" />
                                        <x-amr-expense-modal :products="$powderProducts" title="AMR Powder"
                                            accountCode="5252" triggerEvent="open-amr-powder-modal"
                                            entriesInputId="amr_powders" updatedEvent="amr-powder-updated" />
                                        <x-amr-expense-modal :products="$liquidProducts" title="AMR Liquid"
                                            accountCode="5262" triggerEvent="open-amr-liquid-modal"
                                            entriesInputId="amr_liquids" updatedEvent="amr-liquid-updated" />
                                        <x-bank-transfer-modal :customers="$customers" :bankAccounts="$bankAccounts"
                                            entriesInputId="bank_transfers" />
                                        <x-cheque-payment-modal :customers="$customers" :bankAccounts="$bankAccounts"
                                            entriesInputId="cheques" />
                                        <x-credit-sales-modal :customers="$customers" entriesInputId="credit_sales" />
                                        <x-recoveries-modal :customers="$customers" :bankAccounts="$bankAccounts"
                                            entriesInputId="recoveries_entries" />
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Section 8: Notes (MOVED TO BOTTOM) --}}
                    <div class="mb-6 p-6 bg-white rounded-lg border border-gray-300">
                        <x-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            placeholder="Add any additional notes or remarks here...">{{ old('notes') }}</textarea>
                        <x-input-error for="notes" class="mt-2" />
                    </div>

                    <div class="flex justify-end space-x-3 mb-4 mr-4">
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

            // Alpine.js component for dynamic expense management
            function expenseManager(expenseAccounts) {
                return {
                    // Available expense accounts from server
                    availableAccounts: expenseAccounts || [],
                    // Predefined expense accounts shown by default
                    predefinedExpenses: [
                        { id: 1, label: 'Toll Tax', account_code: '5272', expense_account_id: 72, is_predefined: true, amount: 0 },
                        { id: 2, label: 'AMR Powder', account_code: '5252', expense_account_id: 70, is_predefined: true, amount: 0 },
                        { id: 3, label: 'AMR Liquid', account_code: '5262', expense_account_id: 71, is_predefined: true, amount: 0 },
                        { id: 4, label: 'Scheme Discount Expense', account_code: '5292', expense_account_id: 74, is_predefined: true, amount: 0 },
                        { id: 5, label: 'Advance Tax', account_code: '1161', expense_account_id: 20, is_predefined: true, amount: 0 },
                        { id: 6, label: 'Food/Salesman/Loader Charges', account_code: '5282', expense_account_id: 73, is_predefined: true, amount: 0 },
                        { id: 7, label: 'Percentage Expense', account_code: '5223', expense_account_id: 76, is_predefined: true, amount: 0 },
                        { id: 8, label: 'Miscellaneous Expenses', account_code: '5221', expense_account_id: 58, is_predefined: true, amount: 0 },
                    ],
                    expenses: [],
                    totalExpenses: 0,
                    nextId: 100, // For dynamically added expenses
                    expenseDescription: '',

                    init() {
                        // Initialize with predefined expenses
                        this.expenses = JSON.parse(JSON.stringify(this.predefinedExpenses));

                        // Listen for advance tax updates from modal
                        window.addEventListener('advance-tax-updated', (e) => {
                            const advanceTaxExpense = this.expenses.find(exp => exp.expense_account_id === 20);
                            if (advanceTaxExpense) {
                                advanceTaxExpense.amount = e.detail.total || 0;
                                this.calculateTotal();
                            }
                        });

                        // Listen for AMR Powder updates from modal
                        window.addEventListener('amr-powder-updated', (e) => {
                            const amrPowderExpense = this.expenses.find(exp => exp.expense_account_id === 70);
                            if (amrPowderExpense) {
                                amrPowderExpense.amount = e.detail.total || 0;
                                this.calculateTotal();
                            }
                        });

                        // Listen for AMR Liquid updates from modal
                        window.addEventListener('amr-liquid-updated', (e) => {
                            const amrLiquidExpense = this.expenses.find(exp => exp.expense_account_id === 71);
                            if (amrLiquidExpense) {
                                amrLiquidExpense.amount = e.detail.total || 0;
                                this.calculateTotal();
                            }
                        });
                    },

                    addExpense() {
                        const newId = this.nextId++;
                        this.expenses.push({
                            id: newId,
                            label: '',
                            account_code: '',
                            expense_account_id: null,
                            is_predefined: false,
                            amount: 0,
                            description: ''
                        });
                    },

                    onAccountSelect(event, index) {
                        const selectedId = parseInt(event.target.value);
                        if (selectedId) {
                            const account = this.availableAccounts.find(a => a.id === selectedId);
                            if (account) {
                                this.expenses[index].expense_account_id = account.id;
                                this.expenses[index].account_code = account.account_code;
                                this.expenses[index].label = account.account_name;
                            }
                        } else {
                            this.expenses[index].expense_account_id = null;
                            this.expenses[index].account_code = '';
                            this.expenses[index].label = '';
                        }
                    },

                    removeExpense(index) {
                        if (!this.expenses[index].is_predefined) {
                            this.expenses.splice(index, 1);
                            this.calculateTotal();
                        }
                    },

                    calculateTotal() {
                        this.totalExpenses = this.expenses.reduce((sum, exp) => sum + (parseFloat(exp.amount) || 0), 0);

                        // Update the hidden summary_expenses field for sales summary calculation
                        const summaryExpensesEl = document.getElementById('summary_expenses');
                        if (summaryExpensesEl) {
                            summaryExpensesEl.value = this.totalExpenses.toFixed(2);
                        }

                        // Trigger sales summary update
                        if (typeof updateSalesSummary === 'function') {
                            updateSalesSummary();
                        }
                    }
                }
            }

            // Initialize Select2 with AJAX on-demand loading
            $(document).ready(function () {
                $('.select2-goods-issue').select2({
                    width: '100%',
                    placeholder: 'Select a Goods Issue',
                    allowClear: true,
                    ajax: {
                        url: '{{ route('api.sales-settlements.goods-issues') }}',
                        dataType: 'json',
                        delay: 250,
                        processResults: function (data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    }
                });

                // Restore old value if validation failed and redirected back
                @if(old('goods_issue_id'))
                    const oldGoodsIssueId = '{{ old('goods_issue_id') }}';

                    // Fetch the goods issue details to populate the Select2
                    fetch('{{ url('api/sales-settlements/goods-issues') }}/' + oldGoodsIssueId + '/items')
                        .then(response => response.json())
                        .then(data => {
                            // Create a new option and append to the Select2
                            const option = new Option(
                                data.issue_number + ' - ' + data.employee + ' (' + data.issue_date + ')',
                                oldGoodsIssueId,
                                true,
                                true
                            );
                            $('#goods_issue_id').append(option).trigger('change');

                            // Trigger the select2:select event to load the items
                            $('#goods_issue_id').trigger({
                                type: 'select2:select',
                                params: {
                                    data: {
                                        id: oldGoodsIssueId,
                                        text: data.issue_number + ' - ' + data.employee + ' (' + data.issue_date + ')'
                                    }
                                }
                            });
                        })
                        .catch(() => {
                            // Silently handle error
                        });
                @endif
                                                                                            });


            // Track which field was last changed for smart auto-adjustment
            let lastChangedField = {};

            // Function to calculate balance for a batch row
            // B/F (Out) = B/F (In) + Qty Issued - Sold - Returned - Shortage
            function calculateBatchBalance(itemIndex, batchIndex, changedField = null) {
                const issuedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_issued]"]`);
                const bfInInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][bf_quantity]"]`);
                const soldInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_sold]"]`);
                const returnedInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_returned]"]`);
                const shortageInput = document.querySelector(`input[name="items[${itemIndex}][batches][${batchIndex}][quantity_shortage]"]`);

                if (!issuedInput || !soldInput || !returnedInput || !shortageInput) return;

                // Track which field was changed
                if (changedField) {
                    lastChangedField[`${itemIndex}-${batchIndex}`] = changedField;
                }

                const issued = Math.round(parseFloat(issuedInput.value) || 0);
                const bfIn = Math.round(parseFloat(bfInInput?.value) || 0);
                let sold = Math.round(parseFloat(soldInput.value) || 0);
                let returned = Math.round(parseFloat(returnedInput.value) || 0);
                let shortage = Math.round(parseFloat(shortageInput.value) || 0);

                // Total available = B/F (In) + Issued
                const totalAvailable = bfIn + issued;

                // Auto-adjust: Sold + Returned + Shortage cannot exceed Total Available
                const totalUsed = sold + returned + shortage;
                if (totalUsed > totalAvailable) {
                    const excess = totalUsed - totalAvailable;
                    const lastField = lastChangedField[`${itemIndex}-${batchIndex}`] || 'shortage';

                    // Auto-adjust the LAST changed field to fit within available
                    if (lastField === 'sold') {
                        // User changed sold, so adjust sold
                        const maxSold = totalAvailable - returned - shortage;
                        sold = Math.max(0, maxSold);
                        soldInput.value = sold;
                    } else if (lastField === 'returned') {
                        // User changed returned, so adjust returned
                        const maxReturned = totalAvailable - sold - shortage;
                        returned = Math.max(0, maxReturned);
                        returnedInput.value = returned;
                    } else {
                        // User changed shortage (or default), so adjust shortage
                        const maxShortage = totalAvailable - sold - returned;
                        shortage = Math.max(0, maxShortage);
                        shortageInput.value = shortage;
                    }

                    // Show max available hint
                    showQuantityWarning(itemIndex, batchIndex, totalAvailable, sold, returned, shortage);
                } else {
                    hideQuantityWarning(itemIndex, batchIndex);
                }

                // B/F (Out) = Total Available - Sold - Returned - Shortage (should always be >= 0 now)
                const bfOut = totalAvailable - sold - returned - shortage;

                const bfOutSpan = document.getElementById(`bf-out-${itemIndex}-${batchIndex}`);

                if (bfOutSpan) {
                    bfOutSpan.textContent = bfOut;

                    // Color coding: green if zero (fully settled), orange if positive (stock remaining on van)
                    if (bfOut === 0) {
                        bfOutSpan.className = 'font-bold text-green-600';
                    } else if (bfOut > 0) {
                        bfOutSpan.className = 'font-bold text-orange-600';
                    } else {
                        // This should not happen anymore, but keep for safety
                        bfOutSpan.className = 'font-bold text-red-600';
                    }
                }

                // Update product totals
                updateProductTotals(itemIndex);
            }

            // Show warning when quantity was auto-adjusted
            function showQuantityWarning(itemIndex, batchIndex, maxAvailable, sold, returned, shortage) {
                let warningEl = document.getElementById(`qty-warning-${itemIndex}-${batchIndex}`);
                if (!warningEl) {
                    const row = document.querySelector(`#bf-out-${itemIndex}-${batchIndex}`)?.closest('tr');
                    if (row) {
                        warningEl = document.createElement('div');
                        warningEl.id = `qty-warning-${itemIndex}-${batchIndex}`;
                        warningEl.className = 'text-xs text-orange-600 font-semibold mt-1';

                        const shortageCell = row.querySelector('td:nth-last-child(2)');
                        if (shortageCell) {
                            shortageCell.appendChild(warningEl);
                        }
                    }
                }
                if (warningEl) {
                    warningEl.innerHTML = `<i class="fas fa-info-circle mr-1"></i>Auto-adjusted (Max: ${maxAvailable})`;
                    warningEl.style.display = 'block';
                }
            }

            // Hide quantity warning
            function hideQuantityWarning(itemIndex, batchIndex) {
                const warningEl = document.getElementById(`qty-warning-${itemIndex}-${batchIndex}`);
                if (warningEl) {
                    warningEl.style.display = 'none';
                }
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
                const formatPKR = (val) => '₨ ' + val.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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

                const returnValueInput = document.getElementById('summary_return_value');
                if (returnValueInput) {
                    returnValueInput.value = grandReturnValue.toFixed(2);
                }
                const shortageValueInput = document.getElementById('summary_shortage_value');
                if (shortageValueInput) {
                    shortageValueInput.value = grandShortageValue.toFixed(2);
                }

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
                const returnValue = parseFloat(document.getElementById('summary_return_value').value) || 0;
                const shortageValue = parseFloat(document.getElementById('summary_shortage_value').value) || 0;
                const credit = parseFloat(document.getElementById('summary_credit').value) || 0;
                const expenses = parseFloat(document.getElementById('summary_expenses').value) || 0;

                // Get breakdown amounts
                const creditSalesAmount = parseFloat(document.getElementById('credit_sales_amount').value) || 0;
                const chequeSalesAmount = parseFloat(document.getElementById('total_cheques').value) || 0;
                const bankSalesAmount = parseFloat(document.getElementById('total_bank_transfers').value) || 0;

                // Cash sales is now strictly physical cash from denominations
                const denom5000 = (parseFloat(document.getElementById('denom_5000').value) || 0) * 5000;
                const denom1000 = (parseFloat(document.getElementById('denom_1000').value) || 0) * 1000;
                const denom500 = (parseFloat(document.getElementById('denom_500').value) || 0) * 500;
                const denom100 = (parseFloat(document.getElementById('denom_100').value) || 0) * 100;
                const denom50 = (parseFloat(document.getElementById('denom_50').value) || 0) * 50;
                const denom20 = (parseFloat(document.getElementById('denom_20').value) || 0) * 20;
                const denom10 = (parseFloat(document.getElementById('denom_10').value) || 0) * 10;
                const coins = parseFloat(document.getElementById('denom_coins').value) || 0;
                const cashSalesAmount = denom5000 + denom1000 + denom500 + denom100 + denom50 + denom20 + denom10 + coins;

                // Total Sale Amount = Credit + Cheque + Bank + Cash
                const totalSaleAmount = creditSalesAmount + chequeSalesAmount + bankSalesAmount + cashSalesAmount;

                let recoveryCash = 0;
                let recoveryBank = 0;
                const recoveriesEntriesInput = document.getElementById('recoveries_entries');
                if (recoveriesEntriesInput && recoveriesEntriesInput.value) {
                    try {
                        const entries = JSON.parse(recoveriesEntriesInput.value);
                        if (Array.isArray(entries)) {
                            recoveryCash = entries.reduce((sum, entry) => {
                                if (entry.payment_method === 'cash') {
                                    return sum + (parseFloat(entry.amount) || 0);
                                }
                                return sum;
                            }, 0);
                            recoveryBank = entries.reduce((sum, entry) => {
                                if (entry.payment_method === 'bank_transfer') {
                                    return sum + (parseFloat(entry.amount) || 0);
                                }
                                return sum;
                            }, 0);
                        }
                    } catch (e) {
                        recoveryCash = 0;
                        recoveryBank = 0;
                    }
                }

                // Expected Cash Calculation (Professional Accounting)
                // Only Cash Sales and Cash Recoveries should be in the salesman's physical wallet
                // Note: In create mode, cashSalesAmount is derived from denominations, but for "Expected" 
                // we need the theoretical cash sales (Total Sale - Credit - Cheque - Bank)
                const theoreticalCashSales = netSale - creditSalesAmount - chequeSalesAmount - bankSalesAmount;

                // Advance Tax (if any)
                let advanceTaxTotal = 0;
                const advanceTaxEntriesInput = document.getElementById('advance_tax_entries');
                if (advanceTaxEntriesInput && advanceTaxEntriesInput.value) {
                    try {
                        const entries = JSON.parse(advanceTaxEntriesInput.value);
                        if (Array.isArray(entries)) {
                            advanceTaxTotal = entries.reduce((sum, entry) => sum + (parseFloat(entry.tax_amount) || 0), 0);
                        }
                    } catch (e) { }
                }

                const expectedCashGross = theoreticalCashSales + recoveryCash;
                const totalDeductions = expenses + advanceTaxTotal;
                const expectedCashNet = expectedCashGross - totalDeductions;

                // Actual Physical Cash Collected (from denominations)
                const actualPhysicalCash = cashSalesAmount;

                // Shortage/Excess (Physical Cash vs Expected Cash)
                const shortExcess = actualPhysicalCash - expectedCashNet;

                document.getElementById('summary_total_sale').value = totalSale.toFixed(2);
                document.getElementById('summary_balance').value = expectedCashGross.toFixed(2);
                document.getElementById('summary_net_balance').value = expectedCashNet.toFixed(2);
                document.getElementById('summary_short_excess').value = shortExcess.toFixed(2);

                // Create or update hidden input for cash_sales_amount
                let cashSalesInput = document.getElementById('cash_sales_amount');
                if (!cashSalesInput) {
                    cashSalesInput = document.createElement('input');
                    cashSalesInput.type = 'hidden';
                    cashSalesInput.id = 'cash_sales_amount';
                    cashSalesInput.name = 'cash_sales_amount';
                    document.getElementById('settlementForm').appendChild(cashSalesInput);
                }
                cashSalesInput.value = cashSalesAmount.toFixed(2);

                // Create or update hidden input for bank_transfer_amount
                let bankSalesInput = document.getElementById('bank_transfer_amount');
                if (!bankSalesInput) {
                    bankSalesInput = document.createElement('input');
                    bankSalesInput.type = 'hidden';
                    bankSalesInput.id = 'bank_transfer_amount';
                    bankSalesInput.name = 'bank_transfer_amount';
                    document.getElementById('settlementForm').appendChild(bankSalesInput);
                }
                bankSalesInput.value = bankSalesAmount.toFixed(2);

                // Update cheque_sales_amount
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
                const formatPKR = (val) => '₨ ' + val.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Update display fields
                const totalSaleDisplay = document.getElementById('summary_total_sale_display');
                const creditDisplay = document.getElementById('summary_credit_display');
                const chequeDisplay = document.getElementById('summary_cheque_display');
                const bankDisplay = document.getElementById('summary_bank_display');
                const cashDisplay = document.getElementById('summary_cash_display');

                const netSaleDisplay = document.getElementById('summary_net_sale_display');
                const recoveryDisplay = document.getElementById('summary_recovery_display');
                const returnValueDisplay = document.getElementById('summary_return_value_display');
                const shortageValueDisplay = document.getElementById('summary_shortage_value_display');
                const balanceDisplay = document.getElementById('summary_balance_display');
                const expensesDisplay = document.getElementById('summary_expenses_display');
                const netBalanceDisplay = document.getElementById('summary_net_balance_display');
                const cashReceivedDisplay = document.getElementById('summary_cash_received_display');
                const shortExcessDisplay = document.getElementById('summary_short_excess_display');

                if (totalSaleDisplay) totalSaleDisplay.textContent = formatPKR(totalSale);
                if (creditDisplay) creditDisplay.textContent = formatPKR(creditSalesAmount);
                if (chequeDisplay) chequeDisplay.textContent = formatPKR(chequeSalesAmount);
                if (bankDisplay) bankDisplay.textContent = formatPKR(bankSalesAmount);
                if (cashDisplay) cashDisplay.textContent = formatPKR(cashSalesAmount);

                if (netSaleDisplay) netSaleDisplay.textContent = formatPKR(netSale);
                if (recoveryDisplay) recoveryDisplay.textContent = formatPKR(recovery);
                const bankRecoveryDisplay = document.getElementById('summary_bank_recovery_display');
                if (bankRecoveryDisplay) bankRecoveryDisplay.textContent = formatPKR(recoveryBank);
                if (returnValueDisplay) returnValueDisplay.textContent = formatPKR(returnValue);
                if (shortageValueDisplay) shortageValueDisplay.textContent = formatPKR(shortageValue);
                if (balanceDisplay) balanceDisplay.textContent = formatPKR(expectedCashGross);
                if (expensesDisplay) expensesDisplay.textContent = formatPKR(totalDeductions);
                if (netBalanceDisplay) netBalanceDisplay.textContent = formatPKR(expectedCashNet);
                if (cashReceivedDisplay) cashReceivedDisplay.textContent = formatPKR(actualPhysicalCash);
                if (shortExcessDisplay) shortExcessDisplay.textContent = formatPKR(shortExcess);

                // Update summary_cash_received hidden input
                document.getElementById('summary_cash_received').value = actualPhysicalCash.toFixed(2);

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

            // Expenses Detail calculations - Now handled by Alpine.js expenseManager
            // This function is kept for backward compatibility but no longer directly used
            function updateExpensesTotal() {
                // Alpine.js now manages expenses dynamically
                // This function can be called but won't affect the new dynamic expense system
                if (typeof updateSalesSummary === 'function') {
                    updateSalesSummary();
                }
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
                const totalCash = physicalCashTotal;

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

            // Handle Goods Issue selection with AJAX data loading
            // Use Select2 specific event instead of standard change
            $('#goods_issue_id').on('select2:select', function (e) {
                const goodsIssueId = e.params.data.id;

                if (!goodsIssueId) {
                    clearSettlementForm();
                    return;
                }

                // Show loading state
                document.getElementById('noItemsMessage').innerHTML = '<span class="text-blue-600"><i class="fas fa-spinner fa-spin"></i> Loading goods issue data...</span>';
                document.getElementById('noItemsMessage').style.display = 'block';

                // Fetch goods issue items via AJAX
                const apiUrl = `{{ url('api/sales-settlements/goods-issues') }}/${goodsIssueId}/items`;

                fetch(apiUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        const items = data.items || [];

                        // Load customers for this employee
                        if (data.employee_id) {
                            loadCustomersForEmployee(data.employee_id);
                        }

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


                            // Container div for product-level hidden fields (will be placed outside the table)
                            let hiddenFieldsContainer = document.createElement('div');
                            hiddenFieldsContainer.className = 'hidden-fields-container';
                            hiddenFieldsContainer.innerHTML = `
                                                                                                        <input type="hidden" name="items[${index}][goods_issue_item_id]" value="${item.id}">
                                                                                                        <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                                                                                                        <input type="hidden" name="items[${index}][quantity_issued]" value="${item.quantity_issued}">
                                                                                                        <input type="hidden" name="items[${index}][bf_quantity]" value="${item.bf_quantity || 0}">
                                                                                                        <input type="hidden" name="items[${index}][unit_cost]" value="${item.unit_cost}">
                                                                                                        <input type="hidden" name="items[${index}][selling_price]" value="${avgSellingPrice}">
                                                                                                        <input type="hidden" name="items[${index}][quantity_sold]" class="item-${index}-qty-sold" value="0">
                                                                                                        <input type="hidden" name="items[${index}][quantity_returned]" class="item-${index}-qty-returned" value="0">
                                                                                                        <input type="hidden" name="items[${index}][quantity_shortage]" class="item-${index}-qty-shortage" value="0">
                                                                                                    `;

                            // Get B/F quantity for this item (from van stock)
                            const itemBfQuantity = parseFloat(item.bf_quantity) || 0;

                            // Settlement rows (one per batch)
                            if (batchBreakdown.length > 0) {
                                batchBreakdown.forEach((batch, batchIdx) => {
                                    const batchValue = parseFloat(batch.quantity) * parseFloat(batch.selling_price);
                                    // For B/F, distribute proportionally across batches or show on first batch only
                                    const batchBfQuantity = batchIdx === 0 ? itemBfQuantity : 0;

                                    const productName = (item.product && item.product.name) ? item.product.name : 'Unknown Product';
                                    const productCode = (item.product && item.product.product_code) ? item.product.product_code : 'N/A';
                                    const uomSymbol = (item.uom && item.uom.symbol) ? item.uom.symbol : 'N/A';

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
                                                                                                                            ${parseFloat(batch.quantity).toLocaleString()} × ${parseFloat(batch.selling_price).toFixed(2)} (${uomSymbol})
                                                                                                                        </div>
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-1 text-right">
                                                                                                                        <span id="bf-in-${index}-${batchIdx}" class="font-semibold text-purple-600">${batchBfQuantity > 0 ? batchBfQuantity : '-'}</span>
                                                                                                                        <input type="hidden" name="items[${index}][batches][${batchIdx}][bf_quantity]" value="${batchBfQuantity}">
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-1 text-right">
                                                                                                                        <div class="font-semibold text-gray-900">${parseFloat(batch.quantity).toFixed(0)}</div>
                                                                                                                        <div class="text-xs text-gray-500">${data.issue_date || 'N/A'}</div>
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-1 text-right text-sm">${parseFloat(batch.selling_price).toFixed(2)}</td>
                                                                                                                    <td class="py-1 px-1 text-right font-bold text-green-700">${batchValue.toLocaleString('en-PK', { minimumFractionDigits: 2 })}</td>
                                                                                                                    <td class="py-1 px-1 text-right">
                                                                                                                        <input type="number"
                                                                                                                            name="items[${index}][batches][${batchIdx}][quantity_sold]"
                                                                                                                            class="batch-input w-full text-right border-gray-300 rounded text-sm px-2 py-1"
                                                                                                                            data-item-index="${index}"
                                                                                                                            data-batch-index="${batchIdx}"
                                                                                                                            data-type="sold"
                                                                                                                            data-bf-quantity="${batchBfQuantity}"
                                                                                                                            min="0"
                                                                                                                            step="1"
                                                                                                                            value="0"
                                                                                                                            oninput="calculateBatchBalance(${index}, ${batchIdx}, 'sold')">
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-1 text-right">
                                                                                                                        <input type="number"
                                                                                                                            name="items[${index}][batches][${batchIdx}][quantity_returned]"
                                                                                                                            class="batch-input w-full text-right border-gray-300 rounded text-sm px-2 py-1"
                                                                                                                            data-item-index="${index}"
                                                                                                                            data-batch-index="${batchIdx}"
                                                                                                                            data-type="returned"
                                                                                                                            min="0"
                                                                                                                            step="1"
                                                                                                                            value="0"
                                                                                                                            oninput="calculateBatchBalance(${index}, ${batchIdx}, 'returned')">
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-1 text-right">
                                                                                                                        <input type="number"
                                                                                                                            name="items[${index}][batches][${batchIdx}][quantity_shortage]"
                                                                                                                            class="batch-input w-full text-right border-gray-300 rounded text-sm px-2 py-1"
                                                                                                                            data-item-index="${index}"
                                                                                                                            data-batch-index="${batchIdx}"
                                                                                                                            data-type="shortage"
                                                                                                                            min="0"
                                                                                                                            step="1"
                                                                                                                            value="0"
                                                                                                                            oninput="calculateBatchBalance(${index}, ${batchIdx}, 'shortage')">
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-1 text-right">
                                                                                                                        <span id="bf-out-${index}-${batchIdx}" class="font-bold text-orange-600">${parseFloat(batch.quantity) + batchBfQuantity}</span>
                                                                                                                    </td>
                                                                                                                </tr>
                                                                                                            `;
                                    settlementItemsBody.innerHTML += settlementRow;

                                    // Create batch-level hidden fields container (outside table)
                                    const batchHiddenFields = document.createElement('div');
                                    batchHiddenFields.className = 'batch-hidden-fields';
                                    batchHiddenFields.innerHTML = `
                                                                                                                <input type="hidden" name="items[${index}][batches][${batchIdx}][stock_batch_id]" value="${batch.stock_batch_id}">
                                                                                                                <input type="hidden" name="items[${index}][batches][${batchIdx}][batch_code]" value="${batch.batch_code}">
                                                                                                                <input type="hidden" name="items[${index}][batches][${batchIdx}][quantity_issued]" value="${batch.quantity}">
                                                                                                                <input type="hidden" name="items[${index}][batches][${batchIdx}][unit_cost]" value="${batch.unit_cost}">
                                                                                                                <input type="hidden" name="items[${index}][batches][${batchIdx}][selling_price]" value="${batch.selling_price}">
                                                                                                                <input type="hidden" name="items[${index}][batches][${batchIdx}][is_promotional]" value="${batch.is_promotional ? 1 : 0}">
                                                                                                            `;
                                    document.getElementById('settlementForm').appendChild(batchHiddenFields);

                                    // Add product-level hidden fields only once (on first batch)
                                    if (batchIdx === 0) {
                                        // Append hidden fields container to the form (outside the table)
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
                        const salesSummarySection = document.getElementById('salesSummarySection');
                        if (salesSummarySection) salesSummarySection.style.display = 'block';

                        // Initialize balances for all batch rows
                        items.forEach((item, index) => {
                            const batchBreakdown = item.batch_breakdown || [];
                            batchBreakdown.forEach((batch, batchIdx) => {
                                calculateBatchBalance(index, batchIdx);
                            });
                        });
                    })
                    .catch(() => {
                        document.getElementById('noItemsMessage').innerHTML = '<span class="text-red-600">Error loading goods issue data. Please try again.</span>';
                        document.getElementById('noItemsMessage').style.display = 'block';
                        document.getElementById('settlementTableContainer').style.display = 'none';
                    });
            });

            // Handle clearing the select2 dropdown
            $('#goods_issue_id').on('select2:clear', function () {
                clearSettlementForm();
                // Clear customer lists in modals
                loadCustomersForEmployee(null);
            });

            // Global variable to store current employee ID for settlement
            window.currentSettlementEmployeeId = null;

            // Function to load customers for a specific employee
            function loadCustomersForEmployee(employeeId) {
                // Store globally for modals to access
                window.currentSettlementEmployeeId = employeeId;

                // Also store in hidden input for reliable access
                const hiddenInput = document.getElementById('current_settlement_employee_id');
                if (hiddenInput) {
                    hiddenInput.value = employeeId || '';
                }

                if (!employeeId) {
                    // Clear all customer dropdowns
                    updateModalCustomers([]);
                    return;
                }

                // Fetch customers for this employee via API (new endpoint)
                fetch(`{{ url('api/v1/customer-employee-accounts/by-employee') }}/${employeeId}`)
                    .then(response => response.json())
                    .then(customers => {
                        // Pass both customers AND employeeId in the same event
                        updateModalCustomers(customers, employeeId);
                    })
                    .catch(() => {
                        updateModalCustomers([]);
                    });
            }

            // Function to update customer lists in all modals
            function updateModalCustomers(customers, employeeId = null) {
                // Update Alpine.js components with new customer data AND employeeId
                window.dispatchEvent(new CustomEvent('update-modal-customers', {
                    detail: { customers: customers, employeeId: employeeId }
                }));
            }

            // Alpine.js component for Credit Sales Display
            function creditSalesDisplay() {
                return {
                    entries: [],

                    openModal() {
                        window.dispatchEvent(new CustomEvent('open-credit-sales-modal'));
                    },

                    updateDisplay() {
                        const tbody = document.getElementById('creditSalesTableBody');
                        if (!tbody) return;

                        // Get entries from hidden input
                        const entriesInput = document.getElementById('credit_sales');

                        if (entriesInput && entriesInput.value) {
                            try {
                                this.entries = JSON.parse(entriesInput.value);
                            } catch (e) {
                                this.entries = [];
                            }
                        } else {
                            this.entries = [];
                        }

                        // Clear and rebuild table
                        tbody.innerHTML = '';

                        if (this.entries.length === 0) {
                            tbody.innerHTML = `
                                                                                                                <tr>
                                                                                                                    <td colspan="2" class="py-2 px-2 text-center text-gray-500 text-xs italic">
                                                                                                                        No credit sales entries added yet
                                                                                                                    </td>
                                                                                                                </tr>
                                                                                                            `;
                        } else {
                            this.entries.forEach((entry, index) => {
                                const row = document.createElement('tr');
                                row.className = 'border-b border-gray-200';
                                row.innerHTML = `
                                                                                                                    <td class="py-1 px-2 text-xs">
                                                                                                                        <div class="font-semibold text-gray-800">${entry.customer_name}</div>
                                                                                                                        ${entry.notes ? `<div class="text-xs text-gray-500">${entry.notes}</div>` : ''}
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-2 text-right text-xs font-semibold text-orange-700">
                                                                                                                        ₨ ${parseFloat(entry.sale_amount).toLocaleString('en-PK', { minimumFractionDigits: 2 })}
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-2 text-right text-xs font-semibold text-blue-700">
                                                                                                                        ₨ ${parseFloat(entry.new_balance).toLocaleString('en-PK', { minimumFractionDigits: 2 })}
                                                                                                                    </td>
                                                                                                                `;
                                tbody.appendChild(row);
                            });
                        }
                    },

                    init() {
                        // Listen for updates from the modal
                        window.addEventListener('credit-sales-updated', () => {
                            this.updateDisplay();
                        });

                        // Initial load
                        this.updateDisplay();
                    }
                }
            }

            // Alpine.js component for Recoveries Display
            function recoveriesDisplay() {
                return {
                    entries: [],

                    openModal() {
                        window.dispatchEvent(new CustomEvent('open-recoveries-modal'));
                    },

                    updateDisplay() {
                        const tbody = document.getElementById('recoveriesTableBody');
                        if (!tbody) return;

                        // Get entries from hidden input
                        const entriesInput = document.getElementById('recoveries_entries');

                        if (entriesInput && entriesInput.value) {
                            try {
                                this.entries = JSON.parse(entriesInput.value);
                            } catch (e) {
                                this.entries = [];
                            }
                        } else {
                            this.entries = [];
                        }

                        // Clear and rebuild table
                        tbody.innerHTML = '';

                        if (this.entries.length === 0) {
                            tbody.innerHTML = `
                                                                                                                <tr>
                                                                                                                    <td colspan="4" class="py-2 px-2 text-center text-gray-500 text-xs italic">
                                                                                                                        No recovery entries added yet
                                                                                                                    </td>
                                                                                                                </tr>
                                                                                                            `;
                        } else {
                            this.entries.forEach((entry, index) => {
                                const row = document.createElement('tr');
                                row.className = 'border-b border-gray-200';
                                row.innerHTML = `
                                                                                                                    <td class="py-1 px-2 text-xs">
                                                                                                                        <div class="font-semibold text-gray-800">${entry.customer_name}</div>
                                                                                                                        ${entry.notes ? `<div class="text-xs text-gray-500">${entry.notes}</div>` : ''}
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-2 text-center text-xs">
                                                                                                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${entry.payment_method === 'cash' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'}">
                                                                                                                            ${entry.payment_method === 'cash' ? 'Cash' : 'Bank'}
                                                                                                                        </span>
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-2 text-xs text-gray-800">${entry.payment_method === 'bank_transfer' ? (entry.bank_account_name || '—') : '—'}</td>
                                                                                                                    <td class="py-1 px-2 text-right text-xs font-semibold text-green-700">
                                                                                                                        ₨ ${parseFloat(entry.amount).toLocaleString('en-PK', { minimumFractionDigits: 2 })}
                                                                                                                    </td>
                                                                                                                `;
                                tbody.appendChild(row);
                            });
                        }
                    },

                    init() {
                        // Listen for updates from the modal
                        window.addEventListener('recoveries-updated', () => {
                            this.updateDisplay();
                        });

                        // Initial load
                        this.updateDisplay();
                    }
                }
            }

            // Alpine.js component for Bank Transfer Display
            function bankTransferDisplay() {
                return {
                    entries: [],

                    openModal() {
                        window.dispatchEvent(new CustomEvent('open-bank-transfer-modal'));
                    },

                    updateDisplay() {
                        const tbody = document.getElementById('bankTransferTableBody');
                        if (!tbody) return;

                        // Get entries from hidden input
                        const entriesInput = document.getElementById('bank_transfers');

                        if (entriesInput && entriesInput.value) {
                            try {
                                this.entries = JSON.parse(entriesInput.value);
                            } catch (e) {
                                this.entries = [];
                            }
                        } else {
                            this.entries = [];
                        }

                        // Clear and rebuild table
                        tbody.innerHTML = '';

                        if (this.entries.length === 0) {
                            tbody.innerHTML = `
                                                                                                                <tr>
                                                                                                                    <td colspan="5" class="py-2 px-2 text-center text-gray-500 text-xs italic">
                                                                                                                        No bank transfer entries added yet
                                                                                                                    </td>
                                                                                                                </tr>
                                                                                                            `;
                        } else {
                            this.entries.forEach((entry, index) => {
                                const row = document.createElement('tr');
                                row.className = 'border-b border-gray-200';
                                row.innerHTML = `
                                                                                                                    <td class="py-1 px-2 text-xs text-gray-800">${entry.customer_name || 'N/A'}</td>
                                                                                                                    <td class="py-1 px-2 text-xs text-gray-800">${entry.bank_account_name || 'Unknown Account'}</td>
                                                                                                                    <td class="py-1 px-2 text-xs text-gray-700">${entry.reference_number || 'No ref'}</td>
                                                                                                                    <td class="py-1 px-2 text-xs text-gray-600">${entry.transfer_date || 'N/A'}</td>
                                                                                                                    <td class="py-1 px-2 text-right text-xs font-semibold text-blue-700">
                                                                                                                        ₨ ${parseFloat(entry.amount).toLocaleString('en-PK', { minimumFractionDigits: 2 })}
                                                                                                                    </td>
                                                                                                                `;
                                tbody.appendChild(row);
                            });
                        }
                    },

                    init() {
                        // Listen for updates from the modal
                        window.addEventListener('bank-transfers-updated', () => {
                            this.updateDisplay();
                            if (typeof updateCashTotal === 'function') {
                                updateCashTotal();
                            }
                        });

                        // Initial load
                        this.updateDisplay();
                    }
                }
            }

            // Alpine.js component for Cheque Payment Display
            function chequePaymentDisplay() {
                return {
                    entries: [],

                    openModal() {
                        window.dispatchEvent(new CustomEvent('open-cheque-payment-modal'));
                    },

                    updateDisplay() {
                        const tbody = document.getElementById('chequePaymentTableBody');
                        if (!tbody) return;

                        // Get entries from hidden input
                        const entriesInput = document.getElementById('cheques');

                        if (entriesInput && entriesInput.value) {
                            try {
                                this.entries = JSON.parse(entriesInput.value);
                            } catch (e) {
                                this.entries = [];
                            }
                        } else {
                            this.entries = [];
                        }

                        // Clear and rebuild table
                        tbody.innerHTML = '';

                        if (this.entries.length === 0) {
                            tbody.innerHTML = `
                                                                                                                <tr>
                                                                                                                    <td colspan="6" class="py-2 px-2 text-center text-gray-500 text-xs italic">
                                                                                                                        No cheque payment entries added yet
                                                                                                                    </td>
                                                                                                                </tr>
                                                                                                            `;
                        } else {
                            this.entries.forEach((entry, index) => {
                                const row = document.createElement('tr');
                                row.className = 'border-b border-gray-200';
                                row.innerHTML = `
                                                                                                                    <td class="py-1 px-2 text-xs text-gray-800">${entry.customer_name || 'N/A'}</td>
                                                                                                                    <td class="py-1 px-2 text-xs">
                                                                                                                        <div class="font-semibold text-gray-800">${entry.cheque_number || 'N/A'}</div>
                                                                                                                        ${entry.notes ? `<div class="text-[11px] text-gray-500">${entry.notes}</div>` : ''}
                                                                                                                    </td>
                                                                                                                    <td class="py-1 px-2 text-xs text-gray-800">${entry.bank_name || 'N/A'}</td>
                                                                                                                    <td class="py-1 px-2 text-xs text-gray-800">${entry.bank_account_name || 'N/A'}</td>
                                                                                                                    <td class="py-1 px-2 text-xs text-gray-600">${entry.cheque_date || 'N/A'}</td>
                                                                                                                    <td class="py-1 px-2 text-right text-xs font-semibold text-purple-700">
                                                                                                                        ₨ ${parseFloat(entry.amount).toLocaleString('en-PK', { minimumFractionDigits: 2 })}
                                                                                                                    </td>
                                                                                                                `;
                                tbody.appendChild(row);
                            });
                        }
                    },

                    init() {
                        // Listen for updates from the modal
                        window.addEventListener('cheque-payments-updated', () => {
                            this.updateDisplay();
                            if (typeof updateCashTotal === 'function') {
                                updateCashTotal();
                            }
                        });

                        // Initial load
                        this.updateDisplay();
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>