@props([
'customers' => collect(),
'bankAccounts' => collect(),
'triggerEvent' => 'open-cheque-payment-modal',
'inputId' => 'total_cheques',
'entriesInputId' => 'cheque_payment_entries',
])

@php
$customers = $customers instanceof \Illuminate\Support\Collection ? $customers : collect($customers);
$bankAccounts = $bankAccounts instanceof \Illuminate\Support\Collection ? $bankAccounts : collect($bankAccounts);
@endphp

<div x-data="chequePaymentModal({
        customers: @js($customers->map(fn ($customer) => [
            'id' => $customer->id,
            'name' => $customer->customer_name,
        ])->values()),
        bankAccounts: @js($bankAccounts->map(fn ($bank) => [
            'id' => $bank->id,
            'name' => $bank->bank_name . ' - ' . $bank->account_name . ' (' . $bank->account_number . ')',
        ])->values()),
        inputId: '{{ $inputId }}',
        entriesInputId: '{{ $entriesInputId }}',
    })" x-on:{{ $triggerEvent }}.window="openModal()" x-cloak>
    <input type="hidden" name="{{ $entriesInputId }}" id="{{ $entriesInputId }}" :value="JSON.stringify(entries)">

    <div x-show="show" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-0">
        <div class="absolute inset-0 bg-gray-900 bg-opacity-70" @click="closeModal()"></div>

        <div class="relative w-full max-w-5xl bg-white rounded-lg shadow-xl overflow-hidden transform transition-all flex flex-col max-h-[90vh]"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" @click.stop>
            <div
                class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-purple-600 to-purple-700 flex-shrink-0">
                <div>
                    <h3 class="text-lg font-semibold text-white">Cheque Payments</h3>
                    <p class="text-xs text-purple-100">Record cheque payments received from customers.</p>
                </div>
                <button type="button" @click="closeModal()" class="text-white hover:text-purple-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto flex-grow">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Cheque Date</label>
                        <input type="date" x-model="form.cheque_date"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500"
                            @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Cheque Number</label>
                        <input type="text" x-model="form.cheque_number"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500"
                            placeholder="e.g., 123456" @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Customer Name</label>
                        <select id="cheque_payment_customer_select"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500">
                            <option value="">Select Customer</option>
                            <template x-for="customer in customers" :key="customer.id">
                                <option :value="customer.id" x-text="customer.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Bank Name (Cheque's Bank)</label>
                        <select x-model="form.bank_name"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500">
                            <option value="">Select Bank</option>
                            <option value="Allied Bank Limited (ABL)">Allied Bank Limited (ABL)</option>
                            <option value="Askari Bank Limited">Askari Bank Limited</option>
                            <option value="Bank Alfalah Limited">Bank Alfalah Limited</option>
                            <option value="Bank Al-Habib Limited">Bank Al-Habib Limited</option>
                            <option value="BankIslami Pakistan Limited">BankIslami Pakistan Limited</option>
                            <option value="Dubai Islamic Bank Pakistan Limited">Dubai Islamic Bank Pakistan Limited
                            </option>
                            <option value="Faysal Bank Limited">Faysal Bank Limited</option>
                            <option value="First Women Bank Limited">First Women Bank Limited</option>
                            <option value="Habib Bank Limited (HBL)">Habib Bank Limited (HBL)</option>
                            <option value="Habib Metropolitan Bank Limited">Habib Metropolitan Bank Limited</option>
                            <option value="Industrial Development Bank of Pakistan">Industrial Development Bank of
                                Pakistan</option>
                            <option value="JS Bank Limited">JS Bank Limited</option>
                            <option value="MCB Bank Limited">MCB Bank Limited</option>
                            <option value="Meezan Bank Limited">Meezan Bank Limited</option>
                            <option value="National Bank of Pakistan (NBP)">National Bank of Pakistan (NBP)</option>
                            <option value="Punjab Provincial Cooperative Bank Limited">Punjab Provincial Cooperative
                                Bank Limited</option>
                            <option value="Samba Bank Limited">Samba Bank Limited</option>
                            <option value="Silk Bank Limited">Silk Bank Limited</option>
                            <option value="Standard Chartered Bank (Pakistan) Limited">Standard Chartered Bank
                                (Pakistan) Limited</option>
                            <option value="Summit Bank Limited">Summit Bank Limited</option>
                            <option value="Sindh Bank Limited">Sindh Bank Limited</option>
                            <option value="The Bank of Khyber">The Bank of Khyber</option>
                            <option value="The Bank of Punjab">The Bank of Punjab</option>
                            <option value="United Bank Limited (UBL)">United Bank Limited (UBL)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Deposit Bank (Our Bank)</label>
                        <select x-model="form.bank_account_id"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500">
                            <option value="">Select Deposit Bank</option>
                            <template x-for="bank in bankAccounts" :key="bank.id">
                                <option :value="bank.id" x-text="bank.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-grow">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Amount (₨)</label>
                            <input type="number" min="0" step="0.01" x-model="form.amount"
                                class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500"
                                placeholder="0.00" @keydown.enter.prevent="addEntry()" />
                        </div>
                        <div class="flex items-end">
                            <button type="button" @click="addEntry()"
                                class="inline-flex items-center justify-center px-4 py-2 bg-purple-600 text-white text-sm font-semibold rounded-md hover:bg-purple-700 shadow-sm">
                                Add
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left text-gray-700">S.No</th>
                                <th class="px-3 py-2 text-left text-gray-700">Customer Name</th>
                                <th class="px-3 py-2 text-left text-gray-700">Cheque Number</th>
                                <th class="px-3 py-2 text-left text-gray-700">Bank Name</th>
                                <th class="px-3 py-2 text-left text-gray-700">Deposit Bank</th>
                                <th class="px-3 py-2 text-left text-gray-700">Cheque Date</th>
                                <th class="px-3 py-2 text-right text-gray-700">Amount (₨)</th>
                                <th class="px-3 py-2 text-center text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="entries.length === 0">
                                <tr>
                                    <td colspan="8" class="px-3 py-4 text-center text-gray-500 italic">
                                        No cheque payment entries added yet.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(entry, index) in entries" :key="index">
                                <tr class="border-t border-gray-200 hover:bg-gray-50 transition-colors">
                                    <td class="px-3 py-2 font-semibold text-gray-700" x-text="index + 1"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.customer_name"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.cheque_number"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.bank_name"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.bank_account_name"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="formatDate(entry.cheque_date)"></td>
                                    <td class="px-3 py-2 text-right font-semibold text-purple-700"
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
                        <tfoot class="bg-purple-50 border-t-2 border-purple-200">
                            <tr>
                                <td colspan="6" class="px-3 py-2 text-right font-bold text-purple-900">Grand Total</td>
                                <td class="px-3 py-2 text-right font-bold text-purple-900"
                                    x-text="formatCurrency(total)"></td>
                                <td class="px-3 py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 border-t border-gray-200 flex-shrink-0">
                <button type="button" @click="closeModal()"
                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100">
                    Cancel
                </button>
                <button type="button" @click="saveEntries()"
                    class="px-4 py-2 text-sm font-semibold text-white bg-purple-600 rounded-md hover:bg-purple-700 shadow-sm">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    function chequePaymentModal({ customers, bankAccounts, inputId, entriesInputId }) {
        return {
            show: false,
            customers,
            bankAccounts,
            form: {
                customer_id: '',
                bank_account_id: '',
                cheque_number: '',
                bank_name: '',
                cheque_date: new Date().toISOString().split('T')[0],
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
                $('#cheque_payment_customer_select').select2({
                    width: '100%',
                    placeholder: 'Select Customer',
                    allowClear: true,
                    dropdownParent: $('#cheque_payment_customer_select').parent()
                });

                // Handle select2 change event
                $('#cheque_payment_customer_select').on('change', function() {
                    self.form.customer_id = $(this).val();
                });
            },

            addEntry() {
                const customerId = this.form.customer_id;
                const bankAccountId = this.form.bank_account_id;
                const chequeNumber = this.form.cheque_number.trim();
                const bankName = this.form.bank_name.trim();
                const chequeDate = this.form.cheque_date;
                const amount = parseFloat(this.form.amount);

                if (!customerId) {
                    alert('Please select a customer.');
                    return;
                }

                if (!bankAccountId) {
                    alert('Please select a deposit bank.');
                    return;
                }

                if (!chequeNumber) {
                    alert('Please enter a cheque number.');
                    return;
                }

                if (!bankName) {
                    alert('Please enter a bank name.');
                    return;
                }

                if (!chequeDate) {
                    alert('Please select a cheque date.');
                    return;
                }

                if (isNaN(amount) || amount <= 0) {
                    alert('Please enter a valid amount greater than zero.');
                    return;
                }

                const customerName = this.customerName(customerId);
                const bankAccountName = this.bankAccountName(bankAccountId);

                this.entries.push({
                    customer_id: customerId,
                    customer_name: customerName,
                    bank_account_id: bankAccountId,
                    bank_account_name: bankAccountName,
                    cheque_number: chequeNumber,
                    bank_name: bankName,
                    cheque_date: chequeDate,
                    amount: parseFloat(amount.toFixed(2)),
                });

                // Reset form
                this.form.customer_id = '';
                this.form.bank_account_id = '';
                this.form.cheque_number = '';
                this.form.bank_name = '';
                this.form.cheque_date = new Date().toISOString().split('T')[0];
                this.form.amount = '';

                // Reset select2 dropdown
                $('#cheque_payment_customer_select').val(null).trigger('change');
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
                const displayElement = document.getElementById('chequeTotalDisplay');
                if (displayElement) {
                    displayElement.textContent = this.formatCurrency(total);
                }

                if (typeof updateCashTotal === 'function') {
                    updateCashTotal();
                }

                // Dispatch update event for the display table
                window.dispatchEvent(new CustomEvent('cheque-payments-updated'));
            },

            customerName(id) {
                const found = this.customers.find(customer => Number(customer.id) === Number(id));
                return found ? found.name : 'Unknown Customer';
            },

            bankAccountName(id) {
                const found = this.bankAccounts.find(bank => Number(bank.id) === Number(id));
                return found ? found.name : 'Unknown Bank';
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

            init() {
                // Listen for customer updates from goods issue selection
                window.addEventListener('update-modal-customers', (event) => {
                    this.customers = event.detail.customers || [];
                    
                    // Rebuild select2 options if initialized
                    if (this.select2Initialized) {
                        const select = $('#cheque_payment_customer_select');
                        select.empty();
                        select.append('<option value="">Select Customer</option>');
                        
                        this.customers.forEach(customer => {
                            select.append(`<option value="${customer.id}">${customer.name}</option>`);
                        });
                        
                        select.trigger('change');
                    }
                });
            },
        };
    }
</script>
@endpush
@endonce