@props([
    'customers' => collect(),
    'triggerEvent' => 'open-advance-tax-modal',
    'inputId' => 'expense_advance_tax',
    'entriesInputId' => 'advance_tax_entries',
    'initialEntries' => [],
])

<div x-data="advanceTaxModal({
        customers: @js($customers->map(fn($customer) => [
            'id' => $customer->id,
            'name' => $customer->customer_name . ' (' . $customer->customer_code . ')',
        ])->values()),
        inputId: '{{ $inputId }}',
        entriesInputId: '{{ $entriesInputId }}',
        initialEntries: @js($initialEntries),
    })" x-on:{{ $triggerEvent }}.window="openModal()" x-cloak>
    <input type="hidden" name="{{ $entriesInputId }}" id="{{ $entriesInputId }}" :value="JSON.stringify(entries)">

    <div x-show="show" x-on:keydown.escape.window="if (show) { closeModal() }" class="fixed inset-0 z-50" style="display: none;">
        {{-- Backdrop with blur --}}
        <div x-show="show"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 backdrop-blur-none" x-transition:enter-end="opacity-100 backdrop-blur-sm"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 backdrop-blur-sm" x-transition:leave-end="opacity-0 backdrop-blur-none"
             class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all" @click="closeModal()">
        </div>

        <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4" @click.self="closeModal()">
        <div class="relative w-full max-w-5xl bg-white rounded-lg shadow-xl overflow-hidden transform transition-all flex flex-col max-h-[90vh]"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" @click.stop>
            <div
                class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-green-600 to-green-700 shrink-0">
                <div>
                    <h3 class="text-lg font-semibold text-white">Advance Tax (1161)</h3>
                    <p class="text-xs text-green-100">Add per-customer advance tax deductions.</p>
                </div>
                <button type="button" @click="closeModal()" class="rounded-lg p-1 text-white/80 hover:bg-white/20 hover:text-white transition">
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
                        <select id="advance_tax_customer_select"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500">
                            <option value="">Select Customer</option>
                            <template x-for="customer in customers" :key="customer.id">
                                <option :value="customer.id" x-text="customer.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Invoice #</label>
                        <input type="text" x-model="form.invoice_number" readonly
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 bg-gray-100 text-center font-mono font-semibold"
                            placeholder="Auto" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Tax Amount (₨)</label>
                        <input type="number" min="0" step="0.01" x-model="form.tax_amount"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500 text-right"
                            placeholder="0.00" @keydown.enter.prevent="addEntry()" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="button" @click="addEntry()"
                        class="px-6 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 shadow-sm">
                        Add Entry
                    </button>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left text-gray-700">S.No</th>
                                <th class="px-3 py-2 text-left text-gray-700">Customer Name</th>
                                <th class="px-3 py-2 text-center text-gray-700">Invoice #</th>
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
                                    <td class="px-3 py-2 text-center">
                                        <span class="font-mono text-sm font-semibold text-green-700"
                                            x-text="entry.invoice_number"></span>
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold text-green-700"
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
                        <tfoot class="bg-green-50 border-t-2 border-green-200">
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-right font-bold text-green-900">Grand Total</td>
                                <td class="px-3 py-2 text-right font-bold text-green-900"
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
                    class="px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 shadow-sm">
                    Save
                </button>
            </div>
        </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function advanceTaxModal({ customers, inputId, entriesInputId, initialEntries }) {
                return {
                    show: false,
                    customers,
                    form: {
                        customer_id: '',
                        invoice_number: '',
                        tax_amount: '',
                    },
                    entries: initialEntries || [],
                    invoiceCounter: 1,
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

                    openModal() {
                        this.show = true;
                        this.form.invoice_number = 'ATI-' + this.getDateCode() + '-' + String(this.invoiceCounter).padStart(5, '0');

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
                        const invoiceNumber = this.form.invoice_number;

                        this.entries.push({
                            customer_id: customerId,
                            customer_name: customerName,
                            invoice_number: invoiceNumber,
                            tax_amount: parseFloat(taxAmount.toFixed(2)),
                        });

                        this.invoiceCounter++;

                        // Reset form
                        this.form.customer_id = '';
                        this.form.invoice_number = 'ATI-' + this.getDateCode() + '-' + String(this.invoiceCounter).padStart(5, '0');
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

                        // Dispatch event for Alpine.js expense manager
                        window.dispatchEvent(new CustomEvent('advance-tax-updated', {
                            detail: { total: total }
                        }));

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

                    init() {
                        if (this.entries.length > 0) {
                            this.invoiceCounter = this.entries.length + 1;
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce