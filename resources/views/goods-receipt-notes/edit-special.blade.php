<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                GRN Correction — {{ $grn->grn_number }}
            </h2>
            <div class="flex items-center space-x-2">
                <button type="button" class="inline-flex items-center px-3 py-2 bg-blue-950 border border-transparent rounded-md text-white transition no-print"
                    x-data @click="$dispatch('rg-help', { topic: 'what' })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How a GRN Correction works, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
                <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                    Super Admin Only
                </span>
                <a href="{{ route('goods-receipt-notes.show', $grn->id) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md" />

            {{-- Warning Banner --}}
            <div class="mb-4 p-4 bg-amber-50 border border-amber-300 rounded-lg shadow">
                <div class="flex items-start space-x-3">
                    <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    <div class="text-sm text-amber-800">
                        <p class="font-semibold mb-1">A GRN Correction fixes a wrong conversion factor (or product) on a posted GRN.</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            <li><strong>Total Cost</strong> (invoice amount) stays the same; <strong>Unit Cost</strong> becomes Total Cost ÷ New Qty.</li>
                            <li>The quantity difference is posted as its own stock entry on the GRN's receipt date; nothing posted is overwritten.</li>
                            <li>Stock already issued, sold or returned from the batch is re-costed, and one journal dated today posts the cost difference.</li>
                            <li>The product can only be changed while none of its batch has been used.</li>
                            <li>Old and new figures, your reason and your name are saved in the GRN's correction history.</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- GRN Header Info --}}
            <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Supplier</span>
                        <p class="font-medium">{{ $grn->supplier->supplier_name ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Warehouse</span>
                        <p class="font-medium">{{ $grn->warehouse->warehouse_name ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Receipt Date</span>
                        <p class="font-medium">{{ $grn->receipt_date?->format('d M Y') }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Status</span>
                        <p class="font-medium capitalize">{{ $grn->status }}</p>
                    </div>
                </div>
            </div>

            {{-- Items Form --}}
            <form action="{{ route('goods-receipt-notes.update-special', $grn->id) }}" method="POST"
                x-data="grnSpecialEdit()" x-init="init()">
                @csrf

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        #</th>
                                    <th
                                        class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Product</th>
                                    <th
                                        class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Cartons<br><span class="normal-case font-normal">(qty_purchase)</span></th>
                                    <th
                                        class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Current<br>Factor</th>
                                    <th
                                        class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <span class="text-amber-600">New Factor</span>
                                    </th>
                                    <th
                                        class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Current Qty<br><span class="normal-case font-normal">(stock units)</span></th>
                                    <th
                                        class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <span class="text-emerald-600">New Qty</span>
                                    </th>
                                    <th
                                        class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Cost<br><span class="normal-case font-normal">(unchanged)</span></th>
                                    <th
                                        class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Current<br>Unit Cost</th>
                                    <th
                                        class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <span class="text-emerald-600">New Unit Cost</span>
                                    </th>
                                    <th
                                        class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Changed?</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($grn->items as $index => $item)
                                    <tr x-data="{
                                                            cartons: {{ (float) $item->qty_in_purchase_uom }},
                                                            newFactor: {{ (float) $item->uom_conversion_factor }},
                                                            origFactor: {{ (float) $item->uom_conversion_factor }},
                                                            totalCost: {{ (float) $item->total_cost }},
                                                            get newQty() { return Math.round(this.cartons * this.newFactor * 100) / 100; },
                                                            get newUnitCost() { return this.newQty > 0 ? Math.round((this.totalCost / this.newQty) * 1000000) / 1000000 : 0; },
                                                            get changed() { return Math.abs(this.newFactor - this.origFactor) >= 0.0001; }
                                                        }" class="hover:bg-gray-50 correction-row" :class="changed ? 'bg-amber-50' : ''"
                                        data-line="{{ $item->line_no }}" data-cartons="{{ (float) $item->qty_in_purchase_uom }}"
                                        data-factor="{{ (float) $item->uom_conversion_factor }}" data-qty="{{ (float) $item->quantity_accepted }}"
                                        data-cost="{{ (float) $item->unit_cost }}" data-total="{{ (float) $item->total_cost }}"
                                        data-product="{{ $item->product_id }}">
                                        <td class="px-3 py-2 text-gray-500">{{ $item->line_no }}</td>
                                        <td class="px-3 py-2 min-w-64">
                                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                            <select name="items[{{ $index }}][product_id]"
                                                class="product-select correction-product select2 border-gray-300 rounded-md shadow-sm text-sm w-full"
                                                required>
                                                @foreach ($products as $product)
                                                    <option value="{{ $product->id }}" {{ (int) $product->id === (int) $item->product_id ? 'selected' : '' }}>
                                                        {{ $product->product_code }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700">
                                            {{ number_format((float) $item->qty_in_purchase_uom, 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700">
                                            {{ number_format((float) $item->uom_conversion_factor, 4) }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" name="items[{{ $index }}][uom_conversion_factor]"
                                                x-model="newFactor" step="0.0001" min="0.0001" data-role="factor"
                                                class="w-24 text-right border-gray-300 rounded-md shadow-sm text-sm focus:ring-amber-500 focus:border-amber-500"
                                                :class="changed ? 'border-amber-400 bg-amber-50' : ''" required>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700">
                                            {{ number_format((float) $item->quantity_accepted, 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium"
                                            :class="changed ? 'text-emerald-700' : 'text-gray-700'">
                                            <span
                                                x-text="newQty.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700">
                                            {{ number_format((float) $item->total_cost, 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700">
                                            {{ number_format((float) $item->unit_cost, 6) }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium"
                                            :class="changed ? 'text-emerald-700' : 'text-gray-700'">
                                            <span
                                                x-text="newUnitCost.toLocaleString('en-US', {minimumFractionDigits: 6, maximumFractionDigits: 6})"></span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span x-show="changed"
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Yes</span>
                                            <span x-show="!changed" class="text-gray-400 text-xs">—</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 bg-white shadow-xl sm:rounded-lg p-4">
                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700">Reason for the correction <span class="text-red-600">*</span></label>
                        <textarea id="reason" name="reason" rows="2" required minlength="10" maxlength="1000"
                            class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-amber-500 focus:border-amber-500"
                            placeholder="e.g. Conversion factor entered as 1 instead of 12 pieces per carton">{{ old('reason') }}</textarea>
                        <x-input-error for="reason" class="mt-1" />
                        <x-input-error for="password" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end space-x-3">
                    <a href="{{ route('goods-receipt-notes.show', $grn->id) }}"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="button" @click="openConfirm()"
                        class="inline-flex items-center px-6 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Post Correction
                    </button>
                </div>

                {{-- Confirmation: what will change, and the password --}}
                <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="correction-confirm-title"
                    @keydown.escape.window="confirmOpen = false">
                    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" @click="confirmOpen = false"></div>
                    <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
                        <div class="relative w-full max-w-3xl overflow-hidden rounded-lg bg-white text-left shadow-xl">
                            <div class="px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-amber-100 sm:mx-0 sm:size-10">
                                        <svg class="size-6 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                        </svg>
                                    </div>
                                    <div class="mt-3 w-full text-center sm:ml-4 sm:mt-0 sm:text-left">
                                        <h3 id="correction-confirm-title" class="text-lg font-medium leading-6 text-gray-900">Post GRN Correction on {{ $grn->grn_number }}?</h3>

                                        <template x-if="changes.length === 0">
                                            <p class="mt-2 text-sm text-gray-600">No line has a new product or conversion factor, so there is nothing to correct.</p>
                                        </template>

                                        <template x-if="changes.length > 0">
                                            <div>
                                                <p class="mt-2 text-sm text-gray-600">These lines will be corrected. The total cost of each line stays the same.</p>
                                                <div class="mt-3 overflow-x-auto rounded-md border border-gray-200">
                                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left">#</th>
                                                                <th class="px-3 py-2 text-left">Product</th>
                                                                <th class="px-3 py-2 text-right">Factor</th>
                                                                <th class="px-3 py-2 text-right">Quantity</th>
                                                                <th class="px-3 py-2 text-right">Unit cost</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100">
                                                            <template x-for="change in changes" :key="change.line">
                                                                <tr>
                                                                    <td class="px-3 py-2 text-gray-500" x-text="change.line"></td>
                                                                    <td class="px-3 py-2">
                                                                        <span x-text="change.product"></span>
                                                                        <span x-show="change.productChanged" class="block text-xs text-amber-700">product changed</span>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right whitespace-nowrap" x-text="change.oldFactor + ' → ' + change.newFactor"></td>
                                                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                                                        <span x-text="money(change.oldQty) + ' → ' + money(change.newQty)"></span>
                                                                        <span class="block text-xs" :class="change.newQty >= change.oldQty ? 'text-emerald-700' : 'text-red-700'"
                                                                            x-text="(change.newQty >= change.oldQty ? '+' : '') + money(change.newQty - change.oldQty)"></span>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right whitespace-nowrap" x-text="money(change.oldCost) + ' → ' + money(change.newCost)"></td>
                                                                </tr>
                                                            </template>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <ul class="mt-3 list-disc list-inside space-y-0.5 text-sm text-gray-600">
                                                    <li>The quantity difference is posted on the GRN's receipt date ({{ $grn->receipt_date?->format('d M Y') }}).</li>
                                                    <li>Stock already issued or sold from these batches is re-costed; a journal dated today posts the difference.</li>
                                                </ul>
                                                <p class="mt-3 text-sm text-gray-700"><span class="font-medium">Reason:</span> <span x-text="reason"></span></p>
                                                <div class="mt-4">
                                                    <label for="correction_password" class="block text-sm font-medium text-gray-700">Enter your password to confirm:</label>
                                                    <input type="password" id="correction_password" name="password" x-ref="password" autocomplete="current-password"
                                                        class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" placeholder="Password">
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-row justify-end gap-3 bg-gray-100 px-6 py-4">
                                <button type="button" @click="confirmOpen = false"
                                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" x-show="changes.length > 0"
                                    class="inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-amber-700">
                                    Post Correction
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <x-register-apple-style />
    <x-register-help page="grn-correction" />

    @push('scripts')
        <script>
            function grnSpecialEdit() {
                return {
                    confirmOpen: false,
                    changes: [],
                    reason: '',
                    init() { },
                    money(value) {
                        return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    },
                    openConfirm() {
                        const reason = this.$root.querySelector('#reason');
                        if (!this.$root.reportValidity()) {
                            return;
                        }
                        this.reason = reason.value.trim();
                        this.changes = Array.from(this.$root.querySelectorAll('.correction-row')).map((row) => {
                            const select = row.querySelector('.correction-product');
                            const oldFactor = Number(row.dataset.factor);
                            const newFactor = Number(row.querySelector('[data-role=factor]').value);
                            const newQty = Math.round(Number(row.dataset.cartons) * newFactor * 100) / 100;
                            const productChanged = select.value !== row.dataset.product;
                            return {
                                line: row.dataset.line,
                                product: select.options[select.selectedIndex]?.text.trim() ?? '',
                                productChanged,
                                oldFactor,
                                newFactor,
                                oldQty: Number(row.dataset.qty),
                                newQty,
                                oldCost: Number(row.dataset.cost),
                                newCost: newQty > 0 ? Number(row.dataset.total) / newQty : 0,
                                changed: productChanged || Math.abs(newFactor - oldFactor) >= 0.0001,
                            };
                        }).filter((change) => change.changed);
                        this.confirmOpen = true;
                        this.$nextTick(() => this.$refs.password?.focus());
                    },
                };
            }

            function initializeSpecialEditSelect2() {
                if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                    setTimeout(initializeSpecialEditSelect2, 100);
                    return;
                }

                $(document).ready(function () {
                    $('.product-select').select2({
                        placeholder: 'Select Product',
                        allowClear: false,
                        width: '100%'
                    });
                });
            }

            initializeSpecialEditSelect2();
        </script>
    @endpush
</x-app-layout>