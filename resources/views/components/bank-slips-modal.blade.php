@props([
    'bankAccounts' => null,
    'triggerEvent' => 'open-bank-slips-modal',
    'inputId' => 'total_bank_slips',
    'entriesInputId' => 'bank_slip_entries',
])

@php
    $bankAccounts = $bankAccounts ?? \App\Models\BankAccount::where('is_active', true)->orderBy('bank_name')->get();
    $bankAccounts = $bankAccounts instanceof \Illuminate\Support\Collection ? $bankAccounts : collect($bankAccounts);
@endphp

<div x-data="bankSlipsModal({
        bankAccounts: @js($bankAccounts->map(fn ($account) => [
            'id' => $account->id,
            'name' => $account->bank_name . ' - ' . $account->account_name . ' (' . $account->account_number . ')',
            'bank_name' => $account->bank_name,
        ])->values()),
        inputId: '{{ $inputId }}',
        entriesInputId: '{{ $entriesInputId }}',
    })" x-on:{{ $triggerEvent }}.window="openModal()" x-cloak>
    
    <!-- Hidden inputs to store data -->
    <input type="hidden" name="{{ $entriesInputId }}" id="{{ $entriesInputId }}" :value="JSON.stringify(entries)">
    <input type="hidden" name="{{ $inputId }}" id="{{ $inputId }}" :value="total.toFixed(2)">

    <div x-show="show" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-0">
        <div class="absolute inset-0 bg-gray-900 bg-opacity-70" @click="closeModal()"></div>

        <div class="relative w-full max-w-4xl bg-white rounded-lg shadow-xl overflow-hidden transform transition-all flex flex-col max-h-[90vh]"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" @click.stop>
            
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-purple-600 to-purple-700 flex-shrink-0">
                <div>
                    <h3 class="text-lg font-semibold text-white">Bank Slips / Deposits</h3>
                    <p class="text-xs text-purple-100">Record direct bank deposits made by the salesman.</p>
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Deposit Date</label>
                        <input type="date" x-model="form.deposit_date"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500"
                            @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Bank Account *</label>
                        <select id="bank_slips_account_select"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500">
                            <option value="">Select Bank Account</option>
                            <template x-for="account in bankAccounts" :key="account.id">
                                <option :value="account.id" x-text="account.name"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Slip Number / Reference</label>
                        <input type="text" x-model="form.reference_number"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500"
                            placeholder="e.g., SLIP-12345" @keydown.enter.prevent="addEntry()" />
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-grow">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Amount (₨) *</label>
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
                
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Note (Optional)</label>
                    <input type="text" x-model="form.note"
                        class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-purple-500 focus:ring-purple-500"
                        placeholder="Additional details..." @keydown.enter.prevent="addEntry()" />
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm mt-4">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-2 py-1 text-left text-gray-700">S.No</th>
                                <th class="px-2 py-1 text-left text-gray-700">Bank Account</th>
                                <th class="px-2 py-1 text-left text-gray-700">Date/Ref</th>
                                <th class="px-2 py-1 text-left text-gray-700">Note</th>
                                <th class="px-2 py-1 text-right text-gray-700">Amount (₨)</th>
                                <th class="px-2 py-1 text-center text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="entries.length === 0">
                                <tr class="bg-white">
                                    <td colspan="6" class="px-2 py-4 text-center text-gray-500 italic">
                                        No bank slips added yet.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(entry, index) in entries" :key="index">
                                <tr class="border-t border-gray-200 hover:bg-gray-50 transition-colors bg-white">
                                    <td class="px-2 py-1.5 font-semibold text-gray-700" x-text="index + 1"></td>
                                    <td class="px-2 py-1.5 text-gray-800" x-text="entry.bank_account_name"></td>
                                    <td class="px-2 py-1.5 text-gray-800">
                                        <div x-text="formatDate(entry.deposit_date)"></div>
                                        <div class="text-xs text-gray-500" x-text="entry.reference_number || 'No Ref'"></div>
                                    </td>
                                    <td class="px-2 py-1.5 text-gray-800" x-text="entry.note || '-'"></td>
                                    <td class="px-2 py-1.5 text-right font-semibold text-purple-700"
                                        x-text="formatCurrency(entry.amount)"></td>
                                    <td class="px-2 py-1.5 text-center">
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
                                <td colspan="4" class="px-2 py-2 text-right font-bold text-purple-900">Total Deposited</td>
                                <td class="px-2 py-2 text-right font-bold text-purple-900" x-text="formatCurrency(total)">
                                </td>
                                <td class="px-2 py-2"></td>
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
    function bankSlipsModal({ bankAccounts, inputId, entriesInputId }) {
        return {
            show: false,
            bankAccounts,
            form: {
                bank_account_id: '',
                deposit_date: new Date().toISOString().split('T')[0],
                reference_number: '',
                amount: '',
                note: ''
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
                $('#bank_slips_account_select').select2({
                    width: '100%',
                    placeholder: 'Select Bank Account',
                    allowClear: true,
                    dropdownParent: $('#bank_slips_account_select').parent()
                });

                $('#bank_slips_account_select').on('change', function() {
                    self.form.bank_account_id = $(this).val();
                });
            },

            addEntry() {
                const bankAccountId = this.form.bank_account_id;
                const amount = parseFloat(this.form.amount);
                const depositDate = this.form.deposit_date;

                if (!bankAccountId) {
                    alert('Please select a bank account.');
                    return;
                }

                if (!depositDate) {
                    alert('Please select a deposit date.');
                    return;
                }

                if (isNaN(amount) || amount <= 0) {
                    alert('Please enter a valid amount greater than zero.');
                    return;
                }

                const bankAccountName = this.bankAccountName(bankAccountId);

                this.entries.push({
                    bank_account_id: bankAccountId,
                    bank_account_name: bankAccountName,
                    deposit_date: depositDate,
                    reference_number: this.form.reference_number.trim(),
                    amount: parseFloat(amount.toFixed(2)),
                    note: this.form.note.trim()
                });

                // Reset form
                this.form.bank_account_id = '';
                this.form.deposit_date = new Date().toISOString().split('T')[0];
                this.form.reference_number = '';
                this.form.amount = '';
                this.form.note = '';

                // Reset select2 dropdowns
                $('#bank_slips_account_select').val(null).trigger('change');
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
                
                // Update hidden inputs
                const amountInput = document.getElementById(inputId);
                if (amountInput) {
                    amountInput.value = total.toFixed(2);
                    // Dispatch event to update parent totals calculation
                    amountInput.dispatchEvent(new Event('change'));
                }

                const entriesInput = document.getElementById(entriesInputId);
                if (entriesInput) {
                    entriesInput.value = JSON.stringify(this.entries);
                }

                // Call parent calculation function if it exists
                if (typeof updateSalesSummary === 'function') {
                    updateSalesSummary();
                }

                // Dispatch update event for listeners
                window.dispatchEvent(new CustomEvent('bank-slips-updated', { 
                    detail: { total: total, entries: this.entries } 
                }));
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

            init() {
                // Initialize entries from the hidden input if it has a value
                const entriesInput = document.getElementById(entriesInputId);
                if (entriesInput && entriesInput.value) {
                    try {
                        const parsed = JSON.parse(entriesInput.value);
                        if (Array.isArray(parsed)) {
                            this.entries = parsed;
                        }
                    } catch (e) {
                        console.error('Error parsing bank slip entries:', e);
                    }
                }
            }
        };
    }
</script>
@endpush
@endonce
