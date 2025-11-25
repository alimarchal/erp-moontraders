@props([
'customers' => collect(),
'triggerEvent' => 'open-credit-sales-modal',
'creditInputId' => 'credit_sales_amount',
'recoveryInputId' => 'credit_recoveries_total',
'entriesInputId' => 'credit_sales_entries',
])

<div x-data="creditSalesModal({
        customers: @js($customers->map(fn ($customer) => [
            'id' => $customer->id,
            'name' => $customer->customer_name,
        ])->values()),
        creditInputId: '{{ $creditInputId }}',
        recoveryInputId: '{{ $recoveryInputId }}',
        entriesInputId: '{{ $entriesInputId }}',
    })" x-on:{{ $triggerEvent }}.window="openModal()" x-cloak>
    <input type="hidden" name="{{ $entriesInputId }}" id="{{ $entriesInputId }}" :value="JSON.stringify(entries)">

    <div x-show="show" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-0">
        <div class="absolute inset-0 bg-gray-900 bg-opacity-70" @click="closeModal()"></div>

        <div class="relative w-full max-w-6xl bg-white rounded-lg shadow-xl overflow-hidden transform transition-all"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" @click.stop>
            <div
                class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-orange-600 to-orange-700">
                <div>
                    <h3 class="text-lg font-semibold text-white">Creditors / Credit Sales Breakdown</h3>
                    <p class="text-xs text-orange-100">Record credit sales and customer payments.</p>
                </div>
                <button type="button" @click="closeModal()" class="text-white hover:text-orange-100">
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
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Customer Name</label>
                        <select id="credit_sales_customer_select"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-orange-500 focus:ring-orange-500"
                            @change="onCustomerChange()">
                            <option value="">Select Customer</option>
                            <template x-for="customer in customers" :key="customer.id">
                                <option :value="customer.id" x-text="customer.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Previous Balance (₨)</label>
                        <input type="number" x-model="form.previous_balance" readonly
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 bg-gray-100 text-right font-semibold"
                            placeholder="0.00" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Credit Sale (₨)</label>
                        <input type="number" min="0" step="0.01" x-model="form.sale_amount"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-orange-500 focus:ring-orange-500 text-right"
                            placeholder="0.00" @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Recovery (₨)</label>
                        <input type="number" min="0" step="0.01" x-model="form.payment_received"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-orange-500 focus:ring-orange-500 text-right"
                            placeholder="0.00" @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">New Balance (₨)</label>
                        <input type="number" :value="calculateCurrentBalance()" readonly
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 bg-blue-50 text-right font-bold text-blue-700"
                            placeholder="0.00" />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-11">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Notes (Optional)</label>
                        <input type="text" x-model="form.notes"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-orange-500 focus:ring-orange-500"
                            placeholder="Add any notes..." @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="button" @click="addEntry()"
                            class="w-full md:w-auto inline-flex items-center justify-center px-3 py-2 bg-orange-600 text-white text-sm font-semibold rounded-md hover:bg-orange-700 shadow-sm">
                            Add
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left text-gray-700">S.No</th>
                                <th class="px-3 py-2 text-left text-gray-700">Customer Name</th>
                                <th class="px-3 py-2 text-right text-gray-700">Previous Balance (₨)</th>
                                <th class="px-3 py-2 text-right text-gray-700">Credit Sale (₨)</th>
                                <th class="px-3 py-2 text-right text-gray-700">Recovery (₨)</th>
                                <th class="px-3 py-2 text-right text-gray-700">New Balance (₨)</th>
                                <th class="px-3 py-2 text-left text-gray-700">Notes</th>
                                <th class="px-3 py-2 text-center text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="entries.length === 0">
                                <tr>
                                    <td colspan="8" class="px-3 py-4 text-center text-gray-500 italic">
                                        No credit sales entries added yet.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(entry, index) in entries" :key="index">
                                <tr class="border-t border-gray-200">
                                    <td class="px-3 py-2 font-semibold text-gray-700" x-text="index + 1"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.customer_name"></td>
                                    <td class="px-3 py-2 text-right font-semibold text-gray-600"
                                        x-text="formatCurrency(entry.previous_balance)"></td>
                                    <td class="px-3 py-2 text-right font-semibold text-orange-700"
                                        x-text="formatCurrency(entry.sale_amount)"></td>
                                    <td class="px-3 py-2 text-right font-semibold text-green-700"
                                        x-text="formatCurrency(entry.payment_received)"></td>
                                    <td class="px-3 py-2 text-right font-bold text-blue-700"
                                        x-text="formatCurrency(entry.new_balance)"></td>
                                    <td class="px-3 py-2 text-gray-600 text-xs" x-text="entry.notes || '-'"></td>
                                    <td class="px-3 py-2 text-center">
                                        <button type="button" @click="removeEntry(index)"
                                            class="text-red-600 hover:text-red-800 text-xs font-semibold">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-orange-50 border-t-2 border-orange-200">
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-right font-bold text-orange-900">Total Credit Sales:</td>
                                <td class="px-3 py-2 text-right font-bold text-orange-900"
                                    x-text="formatCurrency(creditTotal)"></td>
                                <td colspan="1" class="px-3 py-2 text-right font-bold text-green-900">Total Recovery:</td>
                                <td class="px-3 py-2 text-right font-bold text-green-900"
                                    x-text="formatCurrency(recoveryTotal)"></td>
                                <td colspan="2"></td>
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
                    class="px-4 py-2 text-sm font-semibold text-white bg-orange-600 rounded-md hover:bg-orange-700 shadow-sm">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    function creditSalesModal({ customers, creditInputId, recoveryInputId, entriesInputId }) {
        return {
            show: false,
            customers,
            form: {
                customer_id: '',
                previous_balance: 0,
                sale_amount: '',
                payment_received: '',
                notes: '',
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
                $('#credit_sales_customer_select').select2({
                    width: '100%',
                    placeholder: 'Select Customer',
                    allowClear: true,
                    dropdownParent: $('#credit_sales_customer_select').parent()
                });

                // Handle select2 change event
                $('#credit_sales_customer_select').on('change', function() {
                    self.form.customer_id = $(this).val();
                    self.onCustomerChange();
                });
            },

            async onCustomerChange() {
                if (!this.form.customer_id) {
                    this.form.previous_balance = 0;
                    return;
                }

                try {
                    const response = await fetch(`/api/customers/${this.form.customer_id}/balance`);
                    if (response.ok) {
                        const data = await response.json();
                        this.form.previous_balance = parseFloat(data.balance || 0);
                    } else {
                        console.error('Failed to fetch customer balance');
                        this.form.previous_balance = 0;
                    }
                } catch (error) {
                    console.error('Error fetching customer balance:', error);
                    this.form.previous_balance = 0;
                }
            },

            calculateCurrentBalance() {
                const previous = parseFloat(this.form.previous_balance) || 0;
                const credit = parseFloat(this.form.sale_amount) || 0;
                const recovery = parseFloat(this.form.payment_received) || 0;
                return (previous + credit - recovery).toFixed(2);
            },

            addEntry() {
                const customerId = this.form.customer_id;
                const saleAmount = parseFloat(this.form.sale_amount) || 0;
                const paymentReceived = parseFloat(this.form.payment_received) || 0;

                if (!customerId) {
                    alert('Please select a customer.');
                    return;
                }

                if (saleAmount === 0 && paymentReceived === 0) {
                    alert('Please enter either a credit sale amount or recovery amount.');
                    return;
                }

                const customerName = this.customerName(customerId);
                const previousBalance = parseFloat(this.form.previous_balance) || 0;
                const newBalance = previousBalance + saleAmount - paymentReceived;

                this.entries.push({
                    customer_id: customerId,
                    customer_name: customerName,
                    previous_balance: parseFloat(previousBalance.toFixed(2)),
                    sale_amount: parseFloat(saleAmount.toFixed(2)),
                    payment_received: parseFloat(paymentReceived.toFixed(2)),
                    new_balance: parseFloat(newBalance.toFixed(2)),
                    notes: this.form.notes.trim(),
                });

                // Reset form
                this.form.customer_id = '';
                this.form.previous_balance = 0;
                this.form.sale_amount = '';
                this.form.payment_received = '';
                this.form.notes = '';

                // Reset select2 dropdown
                $('#credit_sales_customer_select').val(null).trigger('change');
            },

            removeEntry(index) {
                this.entries.splice(index, 1);
            },

            saveEntries() {
                this.syncTotals();
                this.closeModal();
            },

            syncTotals() {
                const creditTotal = this.creditTotal;
                const recoveryTotal = this.recoveryTotal;

                const creditInput = document.getElementById(creditInputId);
                if (creditInput) {
                    creditInput.value = creditTotal.toFixed(2);
                }

                const recoveryInput = document.getElementById(recoveryInputId);
                if (recoveryInput) {
                    recoveryInput.value = recoveryTotal.toFixed(2);
                }

                const entriesInput = document.getElementById(entriesInputId);
                if (entriesInput) {
                    entriesInput.value = JSON.stringify(this.entries);
                }

                // Update display totals
                const creditDisplay = document.getElementById('creditSalesTotalDisplay');
                if (creditDisplay) {
                    creditDisplay.textContent = this.formatCurrency(creditTotal);
                }

                const recoveryDisplay = document.getElementById('creditRecoveryTotalDisplay');
                if (recoveryDisplay) {
                    recoveryDisplay.textContent = this.formatCurrency(recoveryTotal);
                }

                // Update summary fields
                const summaryCredit = document.getElementById('summary_credit');
                if (summaryCredit) {
                    summaryCredit.value = creditTotal.toFixed(2);
                }

                const summaryRecovery = document.getElementById('summary_recovery');
                if (summaryRecovery) {
                    summaryRecovery.value = recoveryTotal.toFixed(2);
                }

                if (typeof updateSalesSummary === 'function') {
                    updateSalesSummary();
                }
            },

            customerName(id) {
                const found = this.customers.find(customer => Number(customer.id) === Number(id));
                return found ? found.name : 'Unknown Customer';
            },

            formatCurrency(value) {
                const numericValue = parseFloat(value) || 0;
                return '₨ ' + numericValue.toLocaleString('en-PK', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            },

            get creditTotal() {
                return this.entries.reduce((sum, entry) => {
                    const amount = parseFloat(entry.sale_amount);
                    return sum + (isNaN(amount) ? 0 : amount);
                }, 0);
            },

            get recoveryTotal() {
                return this.entries.reduce((sum, entry) => {
                    const amount = parseFloat(entry.payment_received);
                    return sum + (isNaN(amount) ? 0 : amount);
                }, 0);
            },
        };
    }
</script>
@endpush
@endonce
