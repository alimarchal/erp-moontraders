@props([
    'customers' => collect(),
    'employeeId' => null,
    'triggerEvent' => 'open-credit-sales-modal',
    'creditInputId' => 'credit_sales_amount',
    'recoveryInputId' => 'credit_recoveries_total',
    'entriesInputId' => 'credit_sales_entries',
])

@php
    $customers = $customers instanceof \Illuminate\Support\Collection ? $customers : collect($customers);
@endphp

<div x-data="creditSalesModal({
        customers: @js($customers->map(fn($customer) => [
            'id' => $customer->id,
            'name' => $customer->customer_name,
        ])->values()),
        employeeId: {{ $employeeId ?? 'null' }},
        creditInputId: '{{ $creditInputId }}',
        recoveryInputId: '{{ $recoveryInputId }}',
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
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Customer Name</label>
                        <select id="credit_sales_customer_select" x-model="form.customer_id"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-orange-500 focus:ring-orange-500"
                            @change="onCustomerChange()">
                            <option value="">Select Customer</option>
                            <template x-for="customer in customers" :key="customer.id">
                                <option :value="customer.id" x-text="customer.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Invoice #</label>
                        <input type="text" x-model="form.invoice_number" readonly
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 bg-gray-100 text-center font-mono font-semibold"
                            placeholder="Auto" />
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
                    <div class="md:col-span-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">New Balance (₨)</label>
                        <input type="number" :value="calculateCurrentBalance()" readonly
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 bg-blue-50 text-right font-bold text-blue-700 text-xs"
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

                <div class="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-4 py-2 border-b border-orange-300">
                        <h4 class="text-sm font-bold text-white flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg>
                            Credit Sales Entries
                            <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"
                                x-text="entries.length + ' entries'"></span>
                        </h4>
                    </div>

                    <template x-if="entries.length === 0">
                        <div class="px-6 py-8 text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg>
                            <p class="text-gray-500 text-sm font-medium mb-1">No Credit Sales Entries</p>
                            <p class="text-gray-400 text-xs">Add customers and their credit sales above</p>
                        </div>
                    </template>

                    <template x-if="entries.length > 0">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-100 border-b border-gray-200">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-gray-700 font-semibold">#</th>
                                        <th class="px-3 py-2 text-left text-gray-700 font-semibold">Customer</th>
                                        <th class="px-3 py-2 text-center text-gray-700 font-semibold">Invoice #</th>
                                        <th class="px-3 py-2 text-right text-gray-700 font-semibold">Prev. Balance</th>
                                        <th class="px-3 py-2 text-right text-gray-700 font-semibold">Credit Sale</th>
                                        <th class="px-3 py-2 text-right text-gray-700 font-semibold">Recovery</th>
                                        <th class="px-3 py-2 text-right text-gray-700 font-semibold">New Balance</th>
                                        <th class="px-3 py-2 text-left text-gray-700 font-semibold">Notes</th>
                                        <th class="px-3 py-2 text-center text-gray-700 font-semibold">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-for="(entry, index) in entries" :key="index">
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-3 py-3">
                                                <span
                                                    class="inline-flex items-center justify-center w-6 h-6 bg-orange-100 text-orange-800 text-xs font-bold rounded-full"
                                                    x-text="index + 1"></span>
                                            </td>
                                            <td class="px-3 py-3">
                                                <div class="font-semibold text-gray-900" x-text="entry.customer_name">
                                                </div>
                                                <div class="text-xs text-gray-500">Customer ID: #<span
                                                        x-text="entry.customer_id"></span></div>
                                            </td>
                                            <td class="px-3 py-3 text-center">
                                                <span class="font-mono text-sm font-semibold text-orange-700"
                                                    x-text="entry.invoice_number"></span>
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <span class="font-semibold text-gray-700"
                                                    x-text="formatCurrency(entry.previous_balance)"></span>
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <span class="font-bold text-orange-700 bg-orange-50 px-2 py-1 rounded"
                                                    x-text="formatCurrency(entry.sale_amount)"></span>
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <span class="font-bold text-green-700 bg-green-50 px-2 py-1 rounded"
                                                    x-text="formatCurrency(entry.payment_received)"></span>
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <span class="font-bold text-blue-700 bg-blue-50 px-2 py-1 rounded"
                                                    x-text="formatCurrency(entry.new_balance)"></span>
                                            </td>
                                            <td class="px-3 py-3">
                                                <span class="text-gray-600 text-xs" x-text="entry.notes || '-'"></span>
                                            </td>
                                            <td class="px-3 py-3 text-center">
                                                <button type="button" @click="removeEntry(index)"
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-red-600 bg-red-50 rounded hover:bg-red-100 transition-colors">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                        </path>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot
                                    class="bg-gradient-to-r from-orange-50 to-orange-100 border-t-2 border-orange-200">
                                    <tr>
                                        <td colspan="3" class="px-3 py-3 text-right">
                                            <span
                                                class="text-sm font-bold text-orange-900 flex items-center justify-end gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                                                    </path>
                                                </svg>
                                                Total Credit Sales:
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            <span
                                                class="text-lg font-bold text-orange-800 bg-orange-200 px-3 py-1 rounded"
                                                x-text="formatCurrency(creditTotal)"></span>
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            <span
                                                class="text-sm font-bold text-green-900 flex items-center justify-end gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Total Recovery:
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            <span
                                                class="text-lg font-bold text-green-800 bg-green-200 px-3 py-1 rounded"
                                                x-text="formatCurrency(recoveryTotal)"></span>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </template>
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
            function creditSalesModal({ customers, employeeId, creditInputId, recoveryInputId, entriesInputId }) {
                return {
                    show: false,
                    customers,
                    employeeId,
                    loadingCustomers: false,
                    form: {
                        customer_id: '',
                        invoice_number: '',
                        previous_balance: 0,
                        sale_amount: '',
                        payment_received: '',
                        notes: '',
                    },
                    entries: [],
                    invoiceCounter: 1,
                    select2Initialized: false,

                    openModal() {
                        this.show = true;
                        this.form.invoice_number = 'CSI-' + String(this.invoiceCounter).padStart(5, '0');

                        // Initialize select2 after the modal is fully rendered
                        this.$nextTick(() => {
                            if (!this.select2Initialized) {
                                this.initializeSelect2();
                                this.select2Initialized = true;
                            }

                            // Load customers by employee if employeeId is set
                            if (this.employeeId) {
                                this.loadCustomersByEmployee();
                            }
                        });
                    },

                    async loadCustomersByEmployee() {
                        if (!this.employeeId) return;

                        this.loadingCustomers = true;
                        try {
                            const response = await fetch(`/api/v1/customers/by-employee/${this.employeeId}`);
                            if (response.ok) {
                                const data = await response.json();
                                this.customers = data.map(c => ({
                                    id: c.id,
                                    name: c.name,
                                    balance: c.balance
                                }));
                                this.rebuildSelect2Options();
                            }
                        } catch (error) {
                            // Silently handle error
                        } finally {
                            this.loadingCustomers = false;
                        }
                    },

                    rebuildSelect2Options() {
                        if (!this.select2Initialized) return;

                        const select = $('#credit_sales_customer_select');
                        select.empty();
                        select.append('<option value="">Select Customer</option>');

                        this.customers.forEach(customer => {
                            select.append(`<option value="${customer.id}">${customer.name}</option>`);
                        });

                        select.trigger('change');
                    },

                    setEmployeeId(id) {
                        this.employeeId = id;
                        if (this.show && this.employeeId) {
                            this.loadCustomersByEmployee();
                        }
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
                            const customerId = $(this).val();
                            self.form.customer_id = customerId;
                            self.onCustomerChange();
                        });
                    },

                    async onCustomerChange() {
                        // Get employeeId - check hidden input FIRST as it's most reliable
                        const hiddenInput = document.getElementById('current_settlement_employee_id');
                        const hiddenValue = hiddenInput ? hiddenInput.value : null;
                        const effectiveEmployeeId = hiddenValue || this.employeeId || window.currentSettlementEmployeeId;

                        if (!this.form.customer_id) {
                            this.form.previous_balance = 0;
                            return;
                        }

                        // Always call employee-specific API to get accurate balance using NEW SYSTEM
                        try {
                            let response;
                            if (effectiveEmployeeId && effectiveEmployeeId !== '') {
                                // NEW API ENDPOINT: customer-employee-accounts
                                response = await fetch(`/api/v1/customer-employee-accounts/${this.form.customer_id}/balance/${effectiveEmployeeId}`);
                            } else {
                                // Fallback: check customers array first
                                const customer = this.customers.find(c => Number(c.id) === Number(this.form.customer_id));
                                if (customer && customer.balance !== undefined) {
                                    this.form.previous_balance = parseFloat(customer.balance || 0);
                                    return;
                                }
                                response = await fetch(`/api/v1/customers/${this.form.customer_id}/balance`);
                            }

                            if (response.ok) {
                                const data = await response.json();
                                this.form.previous_balance = parseFloat(data.balance || 0);
                            } else {
                                this.form.previous_balance = 0;
                            }
                        } catch (error) {
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
                        const invoiceNumber = this.form.invoice_number;

                        this.entries.push({
                            customer_id: customerId,
                            customer_name: customerName,
                            invoice_number: invoiceNumber,
                            previous_balance: parseFloat(previousBalance.toFixed(2)),
                            sale_amount: parseFloat(saleAmount.toFixed(2)),
                            payment_received: parseFloat(paymentReceived.toFixed(2)),
                            new_balance: parseFloat(newBalance.toFixed(2)),
                            notes: this.form.notes.trim(),
                        });

                        this.invoiceCounter++;

                        // Reset form
                        this.form.customer_id = '';
                        this.form.invoice_number = 'CSI-' + String(this.invoiceCounter).padStart(5, '0');
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

                        // Dispatch update event for the display table
                        window.dispatchEvent(new CustomEvent('credit-sales-updated'));
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

                    init() {
                        // Listen for customer updates from goods issue selection
                        // The API /api/v1/customers/by-employee returns: {id, name, business_name, balance}
                        window.addEventListener('update-modal-customers', (event) => {
                            const rawCustomers = event.detail.customers || [];
                            // Also get employeeId from the same event
                            if (event.detail.employeeId) {
                                this.employeeId = event.detail.employeeId;
                            }
                            // Map to ensure consistent structure with balance
                            this.customers = rawCustomers.map(c => ({
                                id: c.id,
                                name: c.name || c.customer_name,
                                balance: c.balance ?? c.receivable_balance ?? 0
                            }));
                            this.rebuildSelect2Options();
                        });
                    },
                };
            }
        </script>
    @endpush
@endonce