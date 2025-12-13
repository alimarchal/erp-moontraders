@props([
'customers' => collect(),
'bankAccounts' => null,
'triggerEvent' => 'open-bank-transfer-modal',
'inputId' => 'total_bank_transfers',
'entriesInputId' => 'bank_transfer_entries',
])

@php
$bankAccounts = $bankAccounts ?? \App\Models\BankAccount::where('is_active', true)->orderBy('bank_name')->get();
@endphp

<div x-data="bankTransferModal({
        customers: @js($customers->map(fn ($customer) => [
            'id' => $customer->id,
            'name' => $customer->customer_name,
        ])->values()),
        bankAccounts: @js($bankAccounts->map(fn ($account) => [
            'id' => $account->id,
            'name' => $account->bank_name . ' - ' . $account->account_name . ' (' . $account->account_number . ')',
            'bank_name' => $account->bank_name,
        ])->values()),
        inputId: '{{ $inputId }}',
        entriesInputId: '{{ $entriesInputId }}',
    })" x-on:{{ $triggerEvent }}.window="openModal()" x-cloak>
    <input type="hidden" name="{{ $entriesInputId }}" id="{{ $entriesInputId }}" :value="JSON.stringify(entries)">

    <div x-show="show" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-0">
        <div class="absolute inset-0 bg-gray-900 bg-opacity-70" @click="closeModal()"></div>

        <div class="relative w-full max-w-4xl bg-white rounded-lg shadow-xl overflow-hidden transform transition-all"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" @click.stop>
            <div
                class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-blue-600 to-blue-700">
                <div>
                    <h3 class="text-lg font-semibold text-white">Bank Transfer / Online Payment</h3>
                    <p class="text-xs text-blue-100">Record bank transfers and online payments from customers.</p>
                </div>
                <button type="button" @click="closeModal()" class="text-white hover:text-blue-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Bank Account *</label>
                        <select id="bank_transfer_account_select"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Bank Account</option>
                            <template x-for="account in bankAccounts" :key="account.id">
                                <option :value="account.id" x-text="account.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Customer Name (Optional)</label>
                        <select id="bank_transfer_customer_select"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Customer</option>
                            <template x-for="customer in customers" :key="customer.id">
                                <option :value="customer.id" x-text="customer.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Transfer Date</label>
                        <input type="date" x-model="form.transfer_date"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                            @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Amount (₨) *</label>
                        <input type="number" min="0" step="0.01" x-model="form.amount"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                            placeholder="0.00" @keydown.enter.prevent="addEntry()" />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-11">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Reference / Transaction ID</label>
                        <input type="text" x-model="form.reference_number"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                            placeholder="e.g., TXN123456789" @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="button" @click="addEntry()"
                            class="w-full md:w-auto inline-flex items-center justify-center px-3 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 shadow-sm">
                            Add
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left text-gray-700">S.No</th>
                                <th class="px-3 py-2 text-left text-gray-700">Bank Account</th>
                                <th class="px-3 py-2 text-left text-gray-700">Customer</th>
                                <th class="px-3 py-2 text-left text-gray-700">Transfer Date</th>
                                <th class="px-3 py-2 text-left text-gray-700">Reference</th>
                                <th class="px-3 py-2 text-right text-gray-700">Amount (₨)</th>
                                <th class="px-3 py-2 text-center text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="entries.length === 0">
                                <tr>
                                    <td colspan="7" class="px-3 py-4 text-center text-gray-500 italic">
                                        No bank transfer entries added yet.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(entry, index) in entries" :key="index">
                                <tr class="border-t border-gray-200">
                                    <td class="px-3 py-2 font-semibold text-gray-700" x-text="index + 1"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.bank_account_name"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.customer_name || 'N/A'"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="formatDate(entry.transfer_date)"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.reference_number || 'N/A'"></td>
                                    <td class="px-3 py-2 text-right font-semibold text-blue-700"
                                        x-text="formatCurrency(entry.amount)"></td>
                                    <td class="px-3 py-2 text-center">
                                        <button type="button" @click="removeEntry(index)"
                                            class="text-red-600 hover:text-red-800 text-xs font-semibold">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-blue-50 border-t-2 border-blue-200">
                            <tr>
                                <td colspan="5" class="px-3 py-2 text-right font-bold text-blue-900">Grand Total</td>
                                <td class="px-3 py-2 text-right font-bold text-blue-900" x-text="formatCurrency(total)">
                                </td>
                                <td class="px-3 py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 border-t border-gray-200">
                <button type="button" @click="closeModal()"
                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100">
                    Cancel
                </button>
                <button type="button" @click="saveEntries()"
                    class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md hover:bg-blue-700 shadow-sm">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    function bankTransferModal({ customers, bankAccounts, inputId, entriesInputId }) {
        return {
            show: false,
            customers,
            bankAccounts,
            form: {
                bank_account_id: '',
                customer_id: '',
                transfer_date: new Date().toISOString().split('T')[0],
                reference_number: '',
                amount: '',
            },
            entries: [],
            select2Initialized: false,

            openModal() {
                this.show = true;

                // Initialize select2 after the modal is fully rendered
                this.$nextTick(() => {
                    if (!this.select2Initialized) {
                        this.initializeSelect2();
                        this.select2Initialized = true;
                    }
                });
            },

            closeModal() {
                this.show = false;
            },

            initializeSelect2() {
                const self = this;
                
                // Bank Account Select2
                $('#bank_transfer_account_select').select2({
                    width: '100%',
                    placeholder: 'Select Bank Account',
                    allowClear: true,
                    dropdownParent: $('#bank_transfer_account_select').parent()
                });

                $('#bank_transfer_account_select').on('change', function() {
                    self.form.bank_account_id = $(this).val();
                });
                
                // Customer Select2
                $('#bank_transfer_customer_select').select2({
                    width: '100%',
                    placeholder: 'Select Customer',
                    allowClear: true,
                    dropdownParent: $('#bank_transfer_customer_select').parent()
                });

                $('#bank_transfer_customer_select').on('change', function() {
                    self.form.customer_id = $(this).val();
                });
            },

            addEntry() {
                const bankAccountId = this.form.bank_account_id;
                const customerId = this.form.customer_id;
                const amount = parseFloat(this.form.amount);
                const transferDate = this.form.transfer_date;

                if (!bankAccountId) {
                    alert('Please select a bank account.');
                    return;
                }

                if (!transferDate) {
                    alert('Please select a transfer date.');
                    return;
                }

                if (isNaN(amount) || amount <= 0) {
                    alert('Please enter a valid amount greater than zero.');
                    return;
                }

                const bankAccountName = this.bankAccountName(bankAccountId);
                const customerName = customerId ? this.customerName(customerId) : null;

                this.entries.push({
                    bank_account_id: bankAccountId,
                    bank_account_name: bankAccountName,
                    customer_id: customerId || null,
                    customer_name: customerName,
                    transfer_date: transferDate,
                    reference_number: this.form.reference_number.trim(),
                    amount: parseFloat(amount.toFixed(2)),
                });

                // Reset form
                this.form.bank_account_id = '';
                this.form.customer_id = '';
                this.form.transfer_date = new Date().toISOString().split('T')[0];
                this.form.reference_number = '';
                this.form.amount = '';

                // Reset select2 dropdowns
                $('#bank_transfer_account_select').val(null).trigger('change');
                $('#bank_transfer_customer_select').val(null).trigger('change');
            },

            removeEntry(index) {
                this.entries.splice(index, 1);
            },

            saveEntries() {
                this.syncTotals();
                this.closeModal();
            },

            syncTotals() {
                const total = this.total;
                const amountInput = document.getElementById(inputId);
                if (amountInput) {
                    amountInput.value = total.toFixed(2);
                }

                const entriesInput = document.getElementById(entriesInputId);
                if (entriesInput) {
                    entriesInput.value = JSON.stringify(this.entries);
                }

                // Update the display total
                const displayElement = document.getElementById('bankTransferTotalDisplay');
                if (displayElement) {
                    displayElement.textContent = this.formatCurrency(total);
                }

                if (typeof updateCashTotal === 'function') {
                    updateCashTotal();
                }

                // Dispatch update event for the display table
                window.dispatchEvent(new CustomEvent('bank-transfers-updated'));
            },

            customerName(id) {
                const found = this.customers.find(customer => Number(customer.id) === Number(id));
                return found ? found.name : 'Unknown Customer';
            },

            bankAccountName(id) {
                const found = this.bankAccounts.find(account => Number(account.id) === Number(id));
                return found ? found.name : 'Unknown Bank Account';
            },

            formatCurrency(value) {
                const numericValue = parseFloat(value) || 0;
                return '₨ ' + numericValue.toLocaleString('en-PK', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            },

            formatDate(dateString) {
                if (!dateString) {
                    return 'N/A';
                }
                const date = new Date(dateString);
                return date.toLocaleDateString('en-PK', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            },

            get total() {
                return this.entries.reduce((sum, entry) => {
                    const amount = parseFloat(entry.amount);
                    return sum + (isNaN(amount) ? 0 : amount);
                }, 0);
            },
        };
    }
</script>
@endpush
@endonce