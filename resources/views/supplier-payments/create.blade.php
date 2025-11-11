<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Create Supplier Payment
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('supplier-payments.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
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
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4" />

                    <form method="POST" action="{{ route('supplier-payments.store') }}" id="paymentForm"
                        x-data="paymentForm()">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <x-label for="payment_date" value="Payment Date *" />
                                <x-input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full"
                                    :value="old('payment_date', date('Y-m-d'))" required
                                    x-model="formData.payment_date" />
                            </div>

                            <div>
                                <x-label for="supplier_id" value="Supplier *" />
                                <select id="supplier_id" name="supplier_id" required x-model="formData.supplier_id"
                                    @change="loadUnpaidGrns()"
                                    class="select2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ old('supplier_id')==$supplier->id ?
                                        'selected' : '' }}
                                        data-balance="{{ $supplier->id == ($selectedSupplier->id ?? null) ?
                                        $supplierBalance
                                        : 0 }}">
                                        {{ $supplier->supplier_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="payment_method" value="Payment Method *" />
                                <select id="payment_method" name="payment_method" required
                                    x-model="formData.payment_method"
                                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Method</option>
                                    <option value="cash" {{ old('payment_method')=='cash' ? 'selected' : '' }}>Cash
                                    </option>
                                    <option value="bank_transfer" {{ old('payment_method')=='bank_transfer' ? 'selected'
                                        : '' }}>Bank Transfer</option>
                                    <option value="cheque" {{ old('payment_method')=='cheque' ? 'selected' : '' }}>
                                        Cheque</option>
                                    <option value="online" {{ old('payment_method')=='online' ? 'selected' : '' }}>
                                        Online</option>
                                </select>
                            </div>

                            <div x-show="formData.payment_method !== 'cash' && formData.payment_method !== ''">
                                <x-label for="bank_account_id" value="Bank Account" />
                                <select id="bank_account_id" name="bank_account_id"
                                    class="select2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Bank Account</option>
                                    @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" {{ old('bank_account_id')==$account->id ?
                                        'selected' : '' }}>
                                        {{ $account->account_name }} ({{ $account->account_number }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="reference_number" value="Reference Number" />
                                <x-input id="reference_number" name="reference_number" type="text"
                                    class="mt-1 block w-full" :value="old('reference_number')"
                                    placeholder="Cheque/Transaction Number" />
                            </div>

                            <div>
                                <x-label for="amount" value="Amount *" />
                                <x-input id="amount" name="amount" type="number" step="0.01" class="mt-1 block w-full"
                                    :value="old('amount')" required x-model="formData.amount"
                                    @input="calculateRemaining()" />
                            </div>
                        </div>

                        <div class="mb-6" x-show="formData.supplier_id">
                            <x-label for="description" value="Description" />
                            <textarea id="description" name="description" rows="2"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">{{ old('description') }}</textarea>
                        </div>

                        <div x-show="formData.supplier_id && unpaidGrns.length > 0">
                            <hr class="my-6 border-gray-200 dark:border-gray-700">

                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Allocate to Unpaid GRNs
                                </h3>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-semibold">Supplier Balance:</span>
                                    <span class="text-red-600 font-bold"
                                        x-text="'₨ ' + supplierBalance.toFixed(2)"></span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                <input type="checkbox" @change="toggleAllGrns($event.target.checked)"
                                                    class="rounded">
                                            </th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                GRN Number</th>
                                            <th
                                                class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                                                GRN Date</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                GRN Amount</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                Paid Amount</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                Balance</th>
                                            <th
                                                class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                Allocate Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <template x-for="(grn, index) in unpaidGrns" :key="grn.id">
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <input type="checkbox" x-model="grn.selected"
                                                        @change="toggleGrn(index)" class="rounded">
                                                </td>
                                                <td class="px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100"
                                                    x-text="grn.grn_number"></td>
                                                <td class="px-3 py-2 text-center text-sm" x-text="grn.receipt_date">
                                                </td>
                                                <td class="px-3 py-2 text-right text-sm"
                                                    x-text="'₨ ' + parseFloat(grn.grand_total).toFixed(2)"></td>
                                                <td class="px-3 py-2 text-right text-sm"
                                                    x-text="'₨ ' + parseFloat(grn.paid_amount).toFixed(2)"></td>
                                                <td class="px-3 py-2 text-right text-sm font-semibold text-red-600"
                                                    x-text="'₨ ' + parseFloat(grn.balance).toFixed(2)"></td>
                                                <td class="px-3 py-2">
                                                    <input type="number" step="0.01" x-model="grn.allocate_amount"
                                                        :name="'grn_allocations[' + grn.id + ']'"
                                                        @input="calculateRemaining()" :max="grn.balance"
                                                        :disabled="!grn.selected"
                                                        class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-right text-sm">
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <td colspan="6" class="px-3 py-2 text-right font-semibold">Total
                                                Allocated:</td>
                                            <td class="px-3 py-2 text-right font-bold text-lg"
                                                x-text="'₨ ' + totalAllocated.toFixed(2)"></td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="px-3 py-2 text-right font-semibold">Payment Amount:
                                            </td>
                                            <td class="px-3 py-2 text-right font-bold text-lg"
                                                x-text="'₨ ' + parseFloat(formData.amount || 0).toFixed(2)"></td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="px-3 py-2 text-right font-semibold">Remaining:</td>
                                            <td class="px-3 py-2 text-right font-bold text-lg"
                                                :class="remainingAmount < 0 ? 'text-red-600' : 'text-green-600'"
                                                x-text="'₨ ' + remainingAmount.toFixed(2)"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-semibold">Note:</span> Check GRNs and enter allocation amounts. The
                                total allocated cannot exceed the payment amount.
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4 mt-6">
                            <a href="{{ route('supplier-payments.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                                Create Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function paymentForm() {
            return {
                formData: {
                    payment_date: '{{ old('payment_date', date('Y-m-d')) }}',
                    supplier_id: '{{ old('supplier_id', $selectedSupplier->id ?? '') }}',
                    payment_method: '{{ old('payment_method') }}',
                    amount: {{ old('amount', 0) }}
                },
                unpaidGrns: @json($unpaidGrns),
                supplierBalance: {{ $supplierBalance }},
                totalAllocated: 0,
                remainingAmount: 0,

                init() {
                    this.calculateRemaining();
                    // Initialize Select2
                    $('.select2').select2({
                        theme: 'classic',
                        width: '100%'
                    });
                    
                    // Sync Alpine with Select2 changes
                    $('#supplier_id').on('change', (e) => {
                        this.formData.supplier_id = e.target.value;
                        this.loadUnpaidGrns();
                    });
                },

                loadUnpaidGrns() {
                    if (!this.formData.supplier_id) {
                        this.unpaidGrns = [];
                        this.supplierBalance = 0;
                        return;
                    }

                    fetch(`/api/suppliers/${this.formData.supplier_id}/unpaid-grns`)
                        .then(response => response.json())
                        .then(data => {
                            this.unpaidGrns = data.unpaid_grns.map(grn => ({
                                ...grn,
                                selected: false,
                                allocate_amount: 0
                            }));
                            this.supplierBalance = data.balance;
                            this.calculateRemaining();
                        })
                        .catch(error => console.error('Error loading unpaid GRNs:', error));
                },

                toggleAllGrns(checked) {
                    this.unpaidGrns.forEach((grn, index) => {
                        grn.selected = checked;
                        if (checked) {
                            grn.allocate_amount = parseFloat(grn.balance);
                        } else {
                            grn.allocate_amount = 0;
                        }
                    });
                    this.calculateRemaining();
                },

                toggleGrn(index) {
                    const grn = this.unpaidGrns[index];
                    if (grn.selected) {
                        grn.allocate_amount = parseFloat(grn.balance);
                    } else {
                        grn.allocate_amount = 0;
                    }
                    this.calculateRemaining();
                },

                calculateRemaining() {
                    this.totalAllocated = this.unpaidGrns.reduce((sum, grn) => {
                        return sum + (parseFloat(grn.allocate_amount) || 0);
                    }, 0);
                    this.remainingAmount = (parseFloat(this.formData.amount) || 0) - this.totalAllocated;
                }
            }
        }
    </script>
    @endpush
</x-app-layout>