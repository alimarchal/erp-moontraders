@props([
    'customers' => collect(),
    'bankAccounts' => collect(),
    'employeeId' => null,
    'triggerEvent' => 'open-recoveries-modal',
    'recoveryInputId' => 'credit_recoveries_total',
    'entriesInputId' => 'recoveries_entries',
])

@php
    $customers = $customers instanceof \Illuminate\Support\Collection ? $customers : collect($customers);
    $bankAccounts = $bankAccounts instanceof \Illuminate\Support\Collection ? $bankAccounts : collect($bankAccounts);
@endphp

<div x-data="recoveriesModal({
        customers: @js($customers->map(fn($customer) => [
            'id' => $customer->id,
            'name' => $customer->customer_name . ' (' . $customer->customer_code . ')',
        ])->values()),
        bankAccounts: @js($bankAccounts->map(fn($bank) => [
            'id' => $bank->id,
            'name' => $bank->bank_name . ' - ' . $bank->account_name . ' (' . $bank->account_number . ')',
        ])->values()),
        employeeId: {{ $employeeId ?? 'null' }},
        recoveryInputId: '{{ $recoveryInputId }}',
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
                class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-green-600 to-green-700 shrink-0">
                <div>
                    <h3 class="text-lg font-semibold text-white">Customer Recoveries</h3>
                    <p class="text-xs text-green-100">Record payments received from customers against previous balances.</p>
                </div>
                <button type="button" @click="closeModal()" class="text-white hover:text-green-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-4 flex-grow overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Customer Name</label>
                        <select id="recoveries_customer_select" x-model="form.customer_id"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500"
                            @change="onCustomerChange()">
                            <option value="">Select Customer</option>
                            <template x-for="customer in customers" :key="customer.id">
                                <option :value="customer.id" x-text="customer.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Recovery #</label>
                        <input type="text" x-model="form.recovery_number" readonly
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 bg-gray-100 text-center font-mono font-semibold"
                            placeholder="Auto" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Previous Balance (₨)</label>
                        <input type="number" x-model="form.previous_balance" readonly
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 bg-gray-100 text-right font-semibold"
                            placeholder="0.00" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Recovery Amount (₨)</label>
                        <input type="number" min="0" step="0.01" x-model="form.amount"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500 text-right"
                            placeholder="0.00" @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Payment Method *</label>
                        <select x-model="form.payment_method"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Online / Bank Transfer</option>
                        </select>
                    </div>
                    <div x-show="form.payment_method === 'bank_transfer'">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Bank Account *</label>
                        <select x-model="form.bank_account_id"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500">
                            <option value="">Select Bank Account</option>
                            <template x-for="bank in bankAccounts" :key="bank.id">
                                <option :value="bank.id" x-text="bank.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">New Balance (₨)</label>
                        <input type="number" :value="calculateCurrentBalance()" readonly
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 bg-blue-50 text-right font-bold text-blue-700"
                            placeholder="0.00" />
                    </div>
                    <div class="flex items-end md:col-span-2">
                        <button type="button" @click="addEntry()"
                            class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition font-semibold text-sm">
                            Add Recovery
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-12">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Notes (Optional)</label>
                        <input type="text" x-model="form.notes"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500"
                            placeholder="Any additional details..." />
                    </div>
                </div>

                <div class="mt-6">
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-1 text-left text-xs font-bold text-gray-500 uppercase">Customer</th>
                                    <th class="px-2 py-1 text-center text-xs font-bold text-gray-500 uppercase">Ref #</th>
                                    <th class="px-2 py-1 text-center text-xs font-bold text-gray-500 uppercase">Method</th>
                                    <th class="px-2 py-1 text-left text-xs font-bold text-gray-500 uppercase">Bank Account</th>
                                    <th class="px-2 py-1 text-right text-xs font-bold text-gray-500 uppercase">Prev. Bal</th>
                                    <th class="px-2 py-1 text-right text-xs font-bold text-gray-500 uppercase">Recovery</th>
                                    <th class="px-2 py-1 text-right text-xs font-bold text-gray-500 uppercase">New Bal</th>
                                    <th class="px-2 py-1 text-center text-xs font-bold text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(entry, index) in entries" :key="index">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-2 py-1.5 text-sm text-gray-900" x-text="entry.customer_name"></td>
                                        <td class="px-2 py-1.5 text-sm text-gray-500 text-center font-mono" x-text="entry.recovery_number"></td>
                                        <td class="px-2 py-1.5 text-sm text-gray-500 text-center">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                                :class="entry.payment_method === 'cash' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'"
                                                x-text="entry.payment_method === 'cash' ? 'Cash' : 'Bank'"></span>
                                        </td>
                                        <td class="px-2 py-1.5 text-sm text-gray-700" x-text="bankAccountName(entry.bank_account_id) || '—'"></td>
                                        <td class="px-2 py-1.5 text-sm text-gray-900 text-right" x-text="formatCurrency(entry.previous_balance)"></td>
                                        <td class="px-2 py-1.5 text-sm text-green-600 font-bold text-right" x-text="formatCurrency(entry.amount)"></td>
                                        <td class="px-2 py-1.5 text-sm text-blue-600 font-bold text-right" x-text="formatCurrency(entry.new_balance)"></td>
                                        <td class="px-2 py-1.5 text-sm text-center">
                                            <button type="button" @click="removeEntry(index)" class="text-red-600 hover:text-red-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="entries.length === 0">
                                    <tr>
                                        <td colspan="7" class="px-2 py-4 text-center text-gray-500 italic">
                                            No recoveries added yet.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50 font-bold">
                                <tr>
                                    <td colspan="3" class="px-2 py-2 text-right text-xs text-gray-700 uppercase">Grand Totals:</td>
                                    <td class="px-2 py-2 text-right text-sm text-gray-700" x-text="formatCurrency(previousBalanceTotal)"></td>
                                    <td class="px-2 py-2 text-right text-sm text-green-700 bg-green-50" x-text="formatCurrency(recoveryTotal)"></td>
                                    <td class="px-2 py-2 text-right text-sm text-blue-700 bg-blue-50" x-text="formatCurrency(newBalanceTotal)"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-between items-center shrink-0">
                <div class="text-sm text-gray-600">
                    Total Recoveries: <span class="font-bold text-green-700" x-text="formatCurrency(recoveryTotal)"></span>
                </div>
                <div class="flex space-x-3">
                    <button type="button" @click="closeModal()"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="button" @click="saveEntries()"
                        class="px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-semibold text-white hover:bg-green-700 transition">
                        Save Recoveries
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function recoveriesModal(config) {
                const recoveryInputId = config.recoveryInputId;
                const entriesInputId = config.entriesInputId;

                return {
                    show: false,
                    customers: config.customers || [],
                    bankAccounts: config.bankAccounts || [],
                    employeeId: config.employeeId,
                    entries: [],
                    recoveryCounter: 1,
                    loadingCustomers: false,
                    select2Initialized: false,

                    getDateCode() {
                        const dateInput = document.getElementById('settlement_date');
                        if (dateInput && dateInput.value) {
                            const d = new Date(dateInput.value + 'T00:00:00');
                            const yy = String(d.getFullYear()).slice(-2);
                            const mm = String(d.getMonth() + 1).padStart(2, '0');
                            const dd = String(d.getDate()).padStart(2, '0');
                            return yy + mm + dd;
                        }
                        const now = new Date();
                        return String(now.getFullYear()).slice(-2) + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
                    },

                    form: {
                        customer_id: '',
                        recovery_number: 'REC-00001',
                        payment_method: 'cash',
                        bank_account_id: '',
                        previous_balance: 0,
                        amount: '',
                        notes: '',
                    },

                    openModal() {
                        this.show = true;
                        this.form.recovery_number = 'REC-' + this.getDateCode() + '-' + String(this.recoveryCounter).padStart(5, '0');
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
                            const response = await fetch(`/api/v1/customer-employee-accounts/by-employee/${this.employeeId}`);
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

                        const select = $('#recoveries_customer_select');
                        select.empty();
                        select.append('<option value="">Select Customer</option>');

                        this.customers.forEach(customer => {
                            select.append(`<option value="${customer.id}">${customer.name}</option>`);
                        });

                        select.trigger('change');
                    },

                    closeModal() {
                        this.show = false;
                    },

                    initializeSelect2() {
                        const self = this;
                        $('#recoveries_customer_select').select2({
                            width: '100%',
                            placeholder: 'Select Customer',
                            allowClear: true,
                            dropdownParent: $('#recoveries_customer_select').parent()
                        });

                        // Handle select2 change event
                        $('#recoveries_customer_select').on('change', function() {
                            const customerId = $(this).val();
                            self.form.customer_id = customerId;
                            self.onCustomerChange();
                        });
                    },

                    async onCustomerChange() {
                        const hiddenInput = document.getElementById('current_settlement_employee_id');
                        const hiddenValue = hiddenInput ? hiddenInput.value : null;
                        const effectiveEmployeeId = hiddenValue || this.employeeId || window.currentSettlementEmployeeId;

                        if (!this.form.customer_id) {
                            this.form.previous_balance = 0;
                            return;
                        }

                        try {
                            let response;
                            if (effectiveEmployeeId && effectiveEmployeeId !== '') {
                                response = await fetch(`/api/v1/customer-employee-accounts/${this.form.customer_id}/balance/${effectiveEmployeeId}`);
                            } else {
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
                        const recovery = parseFloat(this.form.amount) || 0;
                        return (previous - recovery).toFixed(2);
                    },

                    addEntry() {
                        const customerId = this.form.customer_id;
                        const amount = parseFloat(this.form.amount) || 0;

                        if (!customerId) {
                            alert('Please select a customer.');
                            return;
                        }

                        if (amount <= 0) {
                            alert('Please enter a recovery amount.');
                            return;
                        }

                        if (this.form.payment_method === 'bank_transfer' && !this.form.bank_account_id) {
                            alert('Please select a bank account for bank transfer.');
                            return;
                        }

                        const customerName = this.customerName(customerId);
                        const previousBalance = parseFloat(this.form.previous_balance) || 0;
                        const newBalance = previousBalance - amount;
                        const recoveryNumber = this.form.recovery_number;
                        const bankAccountName = this.bankAccountName(this.form.bank_account_id);

                        this.entries.push({
                            customer_id: customerId,
                            customer_name: customerName,
                            recovery_number: recoveryNumber,
                            payment_method: this.form.payment_method,
                            bank_account_id: this.form.payment_method === 'bank_transfer' ? this.form.bank_account_id : null,
                            bank_account_name: bankAccountName,
                            previous_balance: parseFloat(previousBalance.toFixed(2)),
                            amount: parseFloat(amount.toFixed(2)),
                            new_balance: parseFloat(newBalance.toFixed(2)),
                            notes: this.form.notes.trim(),
                        });

                        this.recoveryCounter++;

                        // Reset form
                        this.form.customer_id = '';
                        this.form.recovery_number = 'REC-' + this.getDateCode() + '-' + String(this.recoveryCounter).padStart(5, '0');
                        this.form.payment_method = 'cash';
                        this.form.bank_account_id = '';
                        this.form.previous_balance = 0;
                        this.form.amount = '';
                        this.form.notes = '';

                        // Reset select2 dropdown
                        $('#recoveries_customer_select').val(null).trigger('change');
                    },

                    removeEntry(index) {
                        this.entries.splice(index, 1);
                    },

                    saveEntries() {
                        this.syncTotals();
                        this.closeModal();
                    },

                    syncTotals() {
                        const recoveryTotal = this.recoveryTotal;

                        const recoveryInput = document.getElementById(recoveryInputId);
                        if (recoveryInput) {
                            recoveryInput.value = recoveryTotal.toFixed(2);
                        }

                        const entriesInput = document.getElementById(entriesInputId);
                        if (entriesInput) {
                            entriesInput.value = JSON.stringify(this.entries);
                        }

                        const recoveryDisplay = document.getElementById('creditRecoveryTotalDisplay');
                        if (recoveryDisplay) {
                            recoveryDisplay.textContent = this.formatCurrency(recoveryTotal);
                        }

                        const summaryRecovery = document.getElementById('summary_recovery');
                        if (summaryRecovery) {
                            summaryRecovery.value = recoveryTotal.toFixed(2);
                        }

                        if (typeof updateSalesSummary === 'function') {
                            updateSalesSummary();
                        }

                        window.dispatchEvent(new CustomEvent('recoveries-updated'));
                    },

                    customerName(id) {
                        const found = this.customers.find(customer => Number(customer.id) === Number(id));
                        return found ? found.name : 'Unknown Customer';
                    },

                    bankAccountName(id) {
                        if (!id) {
                            return null;
                        }

                        const found = this.bankAccounts.find(bank => Number(bank.id) === Number(id));
                        return found ? found.name : null;
                    },

                    formatCurrency(value) {
                        const numericValue = parseFloat(value) || 0;
                        return '₨ ' + numericValue.toLocaleString('en-PK', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    },

                    get recoveryTotal() {
                        return this.entries.reduce((sum, entry) => {
                            const amount = parseFloat(entry.amount);
                            return sum + (isNaN(amount) ? 0 : amount);
                        }, 0);
                    },

                    get previousBalanceTotal() {
                        return this.entries.reduce((sum, entry) => sum + (parseFloat(entry.previous_balance) || 0), 0);
                    },

                    get newBalanceTotal() {
                        return this.entries.reduce((sum, entry) => sum + (parseFloat(entry.new_balance) || 0), 0);
                    },

                    parseEntries(rawValue) {
                        if (!rawValue) {
                            return [];
                        }

                        let normalized = rawValue.trim();
                        if (
                            (normalized.startsWith("'") && normalized.endsWith("'")) ||
                            (normalized.startsWith('"') && normalized.endsWith('"'))
                        ) {
                            normalized = normalized.slice(1, -1);
                        }

                        if (normalized.includes('&quot;') || normalized.includes('&#039;') || normalized.includes('&amp;')) {
                            normalized = normalized
                                .replace(/&quot;/g, '"')
                                .replace(/&#039;/g, "'")
                                .replace(/&amp;/g, '&');
                        }

                        try {
                            const parsed = JSON.parse(normalized);
                            return Array.isArray(parsed) ? parsed : [];
                        } catch (error) {
                            console.error('Error parsing recoveries entries:', error);
                            return [];
                        }
                    },

                    init() {
                        // Initialize entries from the hidden input if it has a value
                        const entriesInput = document.getElementById(entriesInputId);
                        if (entriesInput && entriesInput.value) {
                            this.entries = this.parseEntries(entriesInput.value);
                        }

                        if (this.entries.length > 0) {
                            this.recoveryCounter = this.entries.length + 1;
                        }
                        this.form.recovery_number = 'REC-' + this.getDateCode() + '-' + String(this.recoveryCounter).padStart(5, '0');

                        window.addEventListener('update-modal-customers', (event) => {
                            const rawCustomers = event.detail.customers || [];
                            if (event.detail.employeeId) {
                                this.employeeId = event.detail.employeeId;
                            }
                            this.customers = rawCustomers.map(c => ({
                                id: c.id,
                                name: c.name,
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
