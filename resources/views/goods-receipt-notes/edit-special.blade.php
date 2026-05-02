<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Special Edit — GRN: {{ $grn->grn_number }}
            </h2>
            <div class="flex items-center space-x-2">
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
                        <p class="font-semibold mb-1">This action corrects inventory quantities across 8 tables
                            simultaneously.</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            <li>Only change the <strong>UOM Conversion Factor</strong> per line.</li>
                            <li><strong>Total Cost</strong> (invoice amount) stays unchanged.</li>
                            <li><strong>Unit Cost</strong> is recalculated as: Total Cost ÷ New Qty.</li>
                            <li>Journal entries are NOT affected.</li>
                            <li>Lines with no factor change are skipped automatically.</li>
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
                        <p class="font-medium">{{ $grn->receipt_date }}</p>
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
                                            }" class="hover:bg-gray-50" :class="changed ? 'bg-amber-50' : ''">
                                        <td class="px-3 py-2 text-gray-500">{{ $item->line_no }}</td>
                                        <td class="px-3 py-2">
                                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                            <p class="font-medium text-gray-900">
                                                {{ $item->product->product_name ?? 'Product #' . $item->product_id }}</p>
                                            <p class="text-xs text-gray-400">{{ $item->product->product_code ?? '' }}</p>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700">
                                            {{ number_format((float) $item->qty_in_purchase_uom, 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700">
                                            {{ number_format((float) $item->uom_conversion_factor, 4) }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" name="items[{{ $index }}][uom_conversion_factor]"
                                                x-model="newFactor" step="0.0001" min="0.0001"
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

                <div class="mt-4 flex justify-end space-x-3">
                    <a href="{{ route('goods-receipt-notes.show', $grn->id) }}"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit"
                        onclick="return confirm('Apply these inventory corrections? This cannot be undone.\n\nOnly lines with a changed factor will be updated.')"
                        class="inline-flex items-center px-6 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Apply Corrections
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function grnSpecialEdit() {
                return {
                    init() { }
                };
            }
        </script>
    @endpush
</x-app-layout>