<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Edit Supplier Payment: {{ $payment->payment_number }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('supplier-payments.show', $payment) }}"
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
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4" />

                    <form method="POST" action="{{ route('supplier-payments.update', $payment) }}" id="paymentForm"
                        x-data="paymentForm()">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <x-label for="payment_date" value="Payment Date *" />
                                <x-input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full"
                                    :value="old('payment_date', $payment->payment_date->format('Y-m-d'))" required
                                    x-model="formData.payment_date" />
                            </div>

                            <div>
                                <x-label for="supplier_id" value="Supplier *" />
                                <select id="supplier_id" name="supplier_id" required x-model="formData.supplier_id"
                                    @change="loadUnpaidGrns()"
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ old('supplier_id', $payment->
                                        supplier_id)==$supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->supplier_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="payment_method" value="Payment Method *" />
                                <select id="payment_method" name="payment_method" required
                                    x-model="formData.payment_method"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Method</option>
                                    <option value="cash" {{ old('payment_method', $payment->payment_method)=='cash' ?
                                        'selected' : '' }}>Cash</option>
                                    <option value="bank_transfer" {{ old('payment_method', $payment->
                                        payment_method)=='bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                    <option value="cheque" {{ old('payment_method', $payment->payment_method)=='cheque'
                                        ? 'selected' : '' }}>Cheque</option>
                                    <option value="online" {{ old('payment_method', $payment->payment_method)=='online'
                                        ? 'selected' : '' }}>Online</option>
                                </select>
                            </div>

                            <div x-show="formData.payment_method !== 'cash' && formData.payment_method !== ''">
                                <x-label for="bank_account_id" value="Bank Account" />
                                <select id="bank_account_id" name="bank_account_id"
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Bank Account</option>
                                    @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" {{ old('bank_account_id', $payment->
                                        bank_account_id)==$account->id ? 'selected' : '' }}>
                                        {{ $account->account_name }} ({{ $account->account_number }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="reference_number" value="Reference Number" />
                                <x-input id="reference_number" name="reference_number" type="text"
                                    class="mt-1 block w-full"
                                    :value="old('reference_number', $payment->reference_number)"
                                    placeholder="Cheque/Transaction Number" />
                            </div>
                        </div>

                        <div class="mb-6">
                            <x-label for="description" value="Description" />
                            <textarea id="description" name="description" rows="2"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('description', $payment->description) }}</textarea>
                        </div>

                        <div x-show="formData.supplier_id && unpaidGrns.length > 0">
                            <hr class="my-6 border-gray-200">

                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    Allocate to Unpaid GRNs
                                </h3>
                                <div class="text-sm text-gray-600">
                                    <span class="font-semibold">Supplier Balance:</span>
                                    <span class="text-red-600 font-bold"
                                        x-text="'₨ ' + supplierBalance.toFixed(2)"></span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
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
                                        class="bg-white divide-y divide-gray-200">
                                        <template x-for="(grn, index) in unpaidGrns" :key="grn.id">
                                            <tr>
                                                <td class="px-3 py-2 whitespace-nowrap">
                                                    <input type="checkbox" x-model="grn.selected"
                                                        @change="toggleGrn(index)" class="rounded">
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap">
                                                    <span class="text-sm font-medium" x-text="grn.grn_number"></span>
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap text-center">
                                                    <span class="text-sm" x-text="grn.receipt_date"></span>
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap text-right">
                                                    <span class="text-sm font-semibold"
                                                        x-text="'₨ ' + parseFloat(grn.grand_total).toFixed(2)"></span>
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap text-right">
                                                    <span class="text-sm text-emerald-600"
                                                        x-text="'₨ ' + parseFloat(grn.paid_amount).toFixed(2)"></span>
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap text-right">
                                                    <span class="text-sm font-bold text-red-600"
                                                        x-text="'₨ ' + parseFloat(grn.balance).toFixed(2)"></span>
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap">
                                                    <input type="hidden"
                                                        :name="'grn_allocations[' + index + '][grn_id]'"
                                                        x-model="grn.id">
                                                    <input type="number" step="0.01" min="0" :max="grn.balance"
                                                        :name="'grn_allocations[' + index + '][amount]'"
                                                        x-model="grn.allocate_amount" @input="calculateTotal()"
                                                        :disabled="!grn.selected"
                                                        class="w-32 px-2 py-1 border-gray-300 rounded-md shadow-sm text-sm text-right">
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="6"
                                                class="px-3 py-2 text-right font-bold text-gray-900">
                                                Total Allocated:
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <span class="text-lg font-bold text-emerald-600"
                                                    x-text="'₨ ' + totalAllocated.toFixed(2)"></span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-4">
                            <a href="{{ route('supplier-payments.show', $payment) }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                                Update Payment
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
                    payment_date: '{{ old("payment_date", $payment->payment_date->format("Y-m-d")) }}',
                    supplier_id: '{{ old("supplier_id", $payment->supplier_id) }}',
                    payment_method: '{{ old("payment_method", $payment->payment_method) }}',
                },
                unpaidGrns: @json($unpaidGrns),
                supplierBalance: 0,
                totalAllocated: 0,

                init() {
                    // Initialize Select2
                    $('#supplier_id, #bank_account_id').select2({
                        width: '100%'
                    });

                    $('#supplier_id').on('change.select2', (e) => {
                        this.formData.supplier_id = e.target.value;
                        this.loadUnpaidGrns();
                    });

                    $('#bank_account_id').on('change.select2', (e) => {
                        this.formData.bank_account_id = e.target.value;
                    });

                    // Restore old allocations if validation failed
                    const oldAllocations = @json(old('grn_allocations', []));
                    if (oldAllocations && Object.keys(oldAllocations).length > 0) {
                        Object.values(oldAllocations).forEach(alloc => {
                            const grn = this.unpaidGrns.find(g => g.id == alloc.grn_id);
                            if (grn) {
                                grn.selected = true;
                                grn.allocate_amount = alloc.amount;
                            }
                        });
                    } else {
                        // Load existing allocations from payment
                        @json($payment->grnAllocations).forEach(alloc => {
                            const grn = this.unpaidGrns.find(g => g.id == alloc.grn_id);
                            if (grn) {
                                grn.selected = true;
                                grn.allocate_amount = alloc.allocated_amount;
                            }
                        });
                    }

                    this.calculateTotal();
                    
                    if (this.formData.supplier_id) {
                        this.loadUnpaidGrns();
                    }
                },

                async loadUnpaidGrns() {
                    if (!this.formData.supplier_id) return;

                    try {
                        const response = await fetch(`/supplier-payments/unpaid-grns/${this.formData.supplier_id}`);
                        const data = await response.json();
                        
                        // Preserve existing allocations
                        const currentAllocations = this.unpaidGrns
                            .filter(g => g.selected)
                            .map(g => ({ id: g.id, amount: g.allocate_amount }));

                        this.unpaidGrns = data.unpaid_grns.map(grn => ({
                            ...grn,
                            selected: false,
                            allocate_amount: 0
                        }));

                        // Restore allocations
                        currentAllocations.forEach(alloc => {
                            const grn = this.unpaidGrns.find(g => g.id == alloc.id);
                            if (grn) {
                                grn.selected = true;
                                grn.allocate_amount = alloc.amount;
                            }
                        });

                        this.supplierBalance = data.supplier_balance;
                        this.calculateTotal();
                    } catch (error) {
                        console.error('Error loading unpaid GRNs:', error);
                    }
                },

                toggleAllGrns(checked) {
                    this.unpaidGrns.forEach(grn => {
                        grn.selected = checked;
                        grn.allocate_amount = checked ? parseFloat(grn.balance) : 0;
                    });
                    this.calculateTotal();
                },

                toggleGrn(index) {
                    const grn = this.unpaidGrns[index];
                    if (grn.selected) {
                        grn.allocate_amount = parseFloat(grn.balance);
                    } else {
                        grn.allocate_amount = 0;
                    }
                    this.calculateTotal();
                },

                calculateTotal() {
                    this.totalAllocated = this.unpaidGrns
                        .filter(g => g.selected)
                        .reduce((sum, grn) => sum + parseFloat(grn.allocate_amount || 0), 0);
                }
            }
        }
    </script>
    @endpush
</x-app-layout>