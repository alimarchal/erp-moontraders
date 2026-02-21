@props([
    'products' => collect(),
    'title' => 'AMR Expense',
    'accountCode' => '',
    'triggerEvent' => 'open-amr-expense-modal',
    'inputId' => '',
    'entriesInputId' => '',
    'initialEntries' => [],
    'updatedEvent' => 'amr-expense-updated',
    'useBatchExpiry' => false,
])

<div x-data="amrExpenseModal({
        products: @js($products->map(fn($product) => [
            'id' => $product->id,
            'name' => $product->product_name . ' (' . $product->product_code . ')',
            'expiry_price' => (float) $product->expiry_price,
        ])->values()),
        inputId: '{{ $inputId }}',
        entriesInputId: '{{ $entriesInputId }}',
        initialEntries: @js($initialEntries),
        updatedEvent: '{{ $updatedEvent }}',
        selectId: 'select_{{ $entriesInputId }}',
        batchSelectId: 'batch_select_{{ $entriesInputId }}',
        useBatchExpiry: @js((bool) $useBatchExpiry),
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
                    <h3 class="text-lg font-semibold text-white">{{ $title }} ({{ $accountCode }})</h3>
                    <p class="text-xs text-green-100">Add per-product expense claims.</p>
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
                <div class="grid grid-cols-1 gap-4" :class="useBatchExpiry ? 'md:grid-cols-4' : 'md:grid-cols-3'">
                    <div class="md:col-span-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                        <select :id="selectId"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500">
                            <option value="">Select Product</option>
                            <template x-for="product in products" :key="product.id">
                                <option :value="product.id" x-text="product.name"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Batch dropdown (only when USE_BATCH_EXPIRY=true) --}}
                    <template x-if="useBatchExpiry">
                        <div class="md:col-span-1">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Batch <span class="text-red-500">*</span></label>
                            <select :id="batchSelectId"
                                :disabled="!form.product_id"
                                class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                <option value="">Select Batch</option>
                            </select>
                            <template x-if="loadingBatches">
                                <p class="text-xs text-gray-500 mt-1">Loading batches...</p>
                            </template>
                        </div>
                    </template>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Quantity</label>
                        <input type="number" min="0" step="0.01" x-model="form.quantity"
                            @input="calculateAmount()"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500 text-right"
                            placeholder="0.00" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Amount (₨)</label>
                        <input type="number" min="0" step="0.01" x-model="form.amount"
                            :readonly="unitPrice > 0"
                            class="w-full border-gray-300 rounded-md text-sm px-3 py-2 focus:border-green-500 focus:ring-green-500 text-right"
                            :class="unitPrice > 0 ? 'bg-gray-50 cursor-not-allowed' : ''"
                            placeholder="0.00" @keydown.enter.prevent="addEntry()" />
                        <template x-if="unitPrice > 0">
                            <p class="text-xs text-blue-600 mt-1 font-medium">
                                <span x-text="useBatchExpiry ? 'Batch' : 'Expiry'"></span> price: ₨ <span x-text="parseFloat(unitPrice).toFixed(2)"></span>
                            </p>
                        </template>
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
                                <th class="px-3 py-2 text-left text-gray-700">Product Name</th>
                                <template x-if="useBatchExpiry">
                                    <th class="px-3 py-2 text-left text-gray-700">Batch</th>
                                </template>
                                <th class="px-3 py-2 text-right text-gray-700">Quantity</th>
                                <th class="px-3 py-2 text-right text-gray-700">Unit Price</th>
                                <th class="px-3 py-2 text-right text-gray-700">Amount (₨)</th>
                                <th class="px-3 py-2 text-center text-gray-700">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="entries.length === 0">
                                <tr>
                                    <td :colspan="useBatchExpiry ? 7 : 6" class="px-3 py-4 text-center text-gray-500 italic">
                                        No entries added yet.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(entry, index) in entries" :key="index">
                                <tr class="border-t border-gray-200">
                                    <td class="px-3 py-2 font-semibold text-gray-700" x-text="index + 1"></td>
                                    <td class="px-3 py-2 text-gray-800" x-text="entry.product_name"></td>
                                    <template x-if="useBatchExpiry">
                                        <td class="px-3 py-2 text-gray-600 text-xs" x-text="entry.batch_code || '-'"></td>
                                    </template>
                                    <td class="px-3 py-2 text-right" x-text="parseFloat(entry.quantity).toFixed(2)"></td>
                                    <td class="px-3 py-2 text-right text-blue-600" x-text="entry.unit_price ? formatCurrency(entry.unit_price) : '-'"></td>
                                    <td class="px-3 py-2 text-right font-semibold text-green-700"
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
                        <tfoot class="bg-green-50 border-t-2 border-green-200">
                            <tr>
                                <td :colspan="useBatchExpiry ? 5 : 4" class="px-3 py-2 text-right font-bold text-green-900">Grand Total</td>
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
            function amrExpenseModal({ products, inputId, entriesInputId, initialEntries, updatedEvent, selectId, batchSelectId, useBatchExpiry }) {
                return {
                    show: false,
                    products,
                    useBatchExpiry,
                    form: {
                        product_id: '',
                        stock_batch_id: '',
                        batch_code: '',
                        quantity: '',
                        amount: '',
                    },
                    unitPrice: 0,
                    batches: [],
                    loadingBatches: false,
                    entries: initialEntries || [],
                    select2Initialized: false,
                    batchSelect2Initialized: false,
                    updatedEvent,
                    selectId,
                    batchSelectId,

                    openModal() {
                        this.show = true;

                        this.$nextTick(() => {
                            if (!this.select2Initialized) {
                                this.initializeSelect2();
                                this.select2Initialized = true;
                            }
                            if (this.useBatchExpiry && !this.batchSelect2Initialized) {
                                this.initializeBatchSelect2();
                                this.batchSelect2Initialized = true;
                            }
                        });
                    },

                    closeModal() {
                        this.show = false;
                    },

                    initializeSelect2() {
                        const self = this;
                        const $select = $('#' + this.selectId);
                        $select.select2({
                            width: '100%',
                            placeholder: 'Select Product',
                            allowClear: true,
                            dropdownParent: $select.parent()
                        });

                        $select.on('change', function() {
                            const newProductId = $(this).val();
                            self.form.product_id = newProductId;
                            self.onProductChange(newProductId);
                        });
                    },

                    initializeBatchSelect2() {
                        const self = this;
                        const $batchSelect = $('#' + this.batchSelectId);
                        $batchSelect.select2({
                            width: '100%',
                            placeholder: 'Select Batch',
                            allowClear: true,
                            dropdownParent: $batchSelect.parent()
                        });

                        $batchSelect.on('change', function() {
                            const batchId = $(this).val();
                            self.form.stock_batch_id = batchId;
                            self.onBatchChange(batchId);
                        });
                    },

                    onProductChange(productId) {
                        this.form.stock_batch_id = '';
                        this.form.batch_code = '';
                        this.unitPrice = 0;
                        this.batches = [];
                        this.form.amount = '';

                        if (!productId) {
                            if (this.useBatchExpiry) {
                                this.resetBatchSelect2();
                            }
                            return;
                        }

                        if (this.useBatchExpiry) {
                            this.fetchBatches(productId);
                        } else {
                            const product = this.products.find(p => Number(p.id) === Number(productId));
                            if (product) {
                                this.unitPrice = product.expiry_price || 0;
                                this.calculateAmount();
                            }
                        }
                    },

                    fetchBatches(productId) {
                        this.loadingBatches = true;
                        this.resetBatchSelect2();

                        fetch(`/api/products/${productId}/amr-batches`)
                            .then(response => response.json())
                            .then(data => {
                                this.batches = data;
                                this.loadingBatches = false;
                                this.populateBatchSelect2(data);
                            })
                            .catch(() => {
                                this.loadingBatches = false;
                            });
                    },

                    resetBatchSelect2() {
                        const $batchSelect = $('#' + this.batchSelectId);
                        $batchSelect.empty().append('<option value="">Select Batch</option>').val(null).trigger('change');
                        $batchSelect.prop('disabled', true);
                    },

                    populateBatchSelect2(batches) {
                        const $batchSelect = $('#' + this.batchSelectId);
                        $batchSelect.empty().append('<option value="">Select Batch</option>');

                        batches.forEach(batch => {
                            const expiryText = batch.expiry_date ? ` (Exp: ${batch.expiry_date})` : '';
                            const priceText = batch.selling_price ? ` - ₨ ${parseFloat(batch.selling_price).toFixed(2)}` : '';
                            const text = `${batch.batch_code}${expiryText}${priceText}`;
                            $batchSelect.append(new Option(text, batch.id, false, false));
                        });

                        $batchSelect.prop('disabled', false).trigger('change.select2');
                    },

                    onBatchChange(batchId) {
                        if (!batchId) {
                            this.unitPrice = 0;
                            this.form.batch_code = '';
                            this.form.amount = '';
                            return;
                        }

                        const batch = this.batches.find(b => Number(b.id) === Number(batchId));
                        if (batch) {
                            this.unitPrice = parseFloat(batch.selling_price) || 0;
                            this.form.batch_code = batch.batch_code;
                            this.calculateAmount();
                        }
                    },

                    calculateAmount() {
                        if (this.unitPrice > 0 && this.form.quantity) {
                            const qty = parseFloat(this.form.quantity) || 0;
                            this.form.amount = (qty * this.unitPrice).toFixed(2);
                        }
                    },

                    addEntry() {
                        const productId = this.form.product_id;
                        const quantity = parseFloat(this.form.quantity);
                        const amount = parseFloat(this.form.amount);

                        if (!productId) {
                            alert('Please select a product.');
                            return;
                        }

                        if (this.useBatchExpiry && !this.form.stock_batch_id) {
                            alert('Please select a batch.');
                            return;
                        }

                        if (isNaN(quantity) || quantity <= 0) {
                            alert('Please enter a valid quantity greater than zero.');
                            return;
                        }

                        if (isNaN(amount) || amount <= 0) {
                            alert('Please enter a valid amount greater than zero.');
                            return;
                        }

                        const productName = this.productName(productId);

                        const entry = {
                            product_id: productId,
                            product_name: productName,
                            quantity: quantity,
                            amount: parseFloat(amount.toFixed(2)),
                            unit_price: this.unitPrice || 0,
                        };

                        if (this.useBatchExpiry) {
                            entry.stock_batch_id = this.form.stock_batch_id;
                            entry.batch_code = this.form.batch_code;
                        }

                        this.entries.push(entry);

                        this.form.product_id = '';
                        this.form.stock_batch_id = '';
                        this.form.batch_code = '';
                        this.form.quantity = '';
                        this.form.amount = '';
                        this.unitPrice = 0;
                        this.batches = [];

                        $('#' + this.selectId).val(null).trigger('change');
                        if (this.useBatchExpiry) {
                            this.resetBatchSelect2();
                        }
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

                        const entriesInput = document.getElementById(entriesInputId);
                        if (entriesInput) {
                            entriesInput.value = JSON.stringify(this.entries);
                        }

                        window.dispatchEvent(new CustomEvent(this.updatedEvent, {
                            detail: { total: total }
                        }));

                        if (typeof updateExpensesTotal === 'function') {
                            updateExpensesTotal();
                        }
                    },

                    productName(id) {
                        const found = this.products.find(product => Number(product.id) === Number(id));
                        return found ? found.name : 'Unknown Product';
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
                            const amount = parseFloat(entry.amount);
                            return sum + (isNaN(amount) ? 0 : amount);
                        }, 0);
                    },
                };
            }
        </script>
    @endpush
@endonce
