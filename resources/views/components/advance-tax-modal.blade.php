@props([
'customers' => collect(),
'triggerEvent' => 'open-advance-tax-modal',
'inputId' => 'expense_advance_tax',
'entriesInputId' => 'advance_tax_entries',
])

<div x-data="advanceTaxModal({
        customers: @js($customers->map(fn ($customer) => [
            'id' => $customer->id,
            'name' => $customer->customer_name,
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
                class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-indigo-600 to-indigo-700">
                <div>
                    <h3 class="text-lg font-semibold text-white">Advance Tax (1171)</h3>
                    <p class="text-xs text-indigo-100">Add per-customer advance tax deductions.</p>
                </div>
                <button type="button" @click="closeModal()" class="text-white hover:text-indigo-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-6">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Customer Name</label>
                        <select id="advance_tax_customer_select"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select Customer</option>
                            <template x-for="customer in customers" :key="customer.id">
                                <option :value="customer.id" x-text="customer.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-5">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Advance Tax Deduction Amount
                            (₨)</label>
                        <input type="number" min="0" step="0.01" x-model="form.tax_amount"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="0.00" @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="button" @click="addEntry()"
                            class="w-full md:w-auto inline-flex items-center justify-center px-3 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700 shadow-sm">
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
                                <th class="px-3 py-2 text-right text-gray-700">Advance Tax Deduction Amount (₨)</th>
                                <th class="px-3 py-2 text-center text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="entries.length === 0">
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-center text-gray-500 italic">
                                        No advance tax entries added yet.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(entry, index) in entries" :key="index">
                                <tr class="border-t border-gray-200">
                                    <td class="px-3 py-2 font-semibold text-gray-700" x-text="index + 1"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.customer_name"></td>
                                    <td class="px-3 py-2 text-right font-semibold text-indigo-700"
                                        x-text="formatCurrency(entry.tax_amount)"></td>
                                    <td class="px-3 py-2 text-center">
                                        <button type="button" @click="removeEntry(index)"
                                            class="text-red-600 hover:text-red-800 text-xs font-semibold">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-indigo-50 border-t-2 border-indigo-200">
                            <tr>
                                <td colspan="2" class="px-3 py-2 text-right font-bold text-indigo-900">Grand Total</td>
                                <td class="px-3 py-2 text-right font-bold text-indigo-900"
                                    x-text="formatCurrency(total)"></td>
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
                    class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700 shadow-sm">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    function advanceTaxModal({ customers, inputId, entriesInputId }) {
        return {
            show: false,
            customers,
            form: {
                customer_id: '',
                tax_amount: '',
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
                $('#advance_tax_customer_select').select2({
                    width: '100%',
                    placeholder: 'Select Customer',
                    allowClear: true,
                    dropdownParent: $('#advance_tax_customer_select').parent()
                });

                // Handle select2 change event
                $('#advance_tax_customer_select').on('change', function() {
                    self.form.customer_id = $(this).val();
                });
            },

            addEntry() {
                const customerId = this.form.customer_id;
                const taxAmount = parseFloat(this.form.tax_amount);

                if (!customerId) {
                    alert('Please select a customer.');
                    return;
                }

                if (isNaN(taxAmount) || taxAmount <= 0) {
                    alert('Please enter a valid advance tax amount greater than zero.');
                    return;
                }

                const customerName = this.customerName(customerId);

                this.entries.push({
                    customer_id: customerId,
                    customer_name: customerName,
                    tax_amount: parseFloat(taxAmount.toFixed(2)),
                });

                // Reset form
                this.form.customer_id = '';
                this.form.tax_amount = '';

                // Reset select2 dropdown
                $('#advance_tax_customer_select').val(null).trigger('change');
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

                if (typeof updateExpensesTotal === 'function') {
                    updateExpensesTotal();
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

            get total() {
                return this.entries.reduce((sum, entry) => {
                    const taxAmount = parseFloat(entry.tax_amount);
                    return sum + (isNaN(taxAmount) ? 0 : taxAmount);
                }, 0);
            },
        };
    }
</script>
@endpush
@endonce