<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Edit Product: {{ $product->product_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('products.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
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
            <x-status-message class="mb-4 mt-4 shadow-md" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 space-y-6">
                    <x-validation-errors class="mb-4 mt-4" />
                    <div x-data="productEditForm()">
                        <form method="POST" action="{{ route('products.update', $product) }}" id="edit-product-form"
                            x-on:submit.prevent="openConfirm()">
                            @csrf
                            @method('PUT')

                            @include('products.partials.form-fields', [
                                'product' => $product,
                                'supplierOptions' => $supplierOptions,
                                'uomOptions' => $uomOptions,
                                'valuationMethods' => $valuationMethods,
                            ])

                            <div class="flex items-center justify-end mt-6">
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 ml-4">
                                    Update Product
                                </button>
                            </div>
                        </form>

                        {{-- Confirmation Modal --}}
                        <div x-show="showConfirm" x-cloak x-on:keydown.escape.window="showConfirm = false"
                            class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
                            <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" @click="showConfirm = false"></div>
                            <div class="relative bg-white rounded-lg shadow-xl sm:w-full sm:max-w-lg"
                                x-transition:enter="ease-out duration-300"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="ease-in duration-200"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                @click.outside="showConfirm = false">
                                <div class="px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                    <div class="sm:flex sm:items-start">
                                        <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-blue-100 sm:mx-0 sm:size-10">
                                            <svg class="size-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </div>
                                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                            <h3 class="text-lg font-medium leading-6 text-gray-900">Confirm Update</h3>
                                            <div class="mt-2">
                                                <template x-if="changes.length === 0">
                                                    <p class="text-sm text-gray-500 italic">No changes detected.</p>
                                                </template>
                                                <template x-if="changes.length > 0">
                                                    <div>
                                                        <p class="text-sm text-gray-600 mb-3">The following fields will be updated for <strong>{{ $product->product_name }}</strong>:</p>
                                                        <div class="border border-gray-200 rounded-md overflow-hidden">
                                                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                                                <thead class="bg-gray-50">
                                                                    <tr>
                                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Field</th>
                                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Old</th>
                                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">New</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="bg-white divide-y divide-gray-100">
                                                                    <template x-for="change in changes" :key="change.field">
                                                                        <tr>
                                                                            <td class="px-3 py-2 font-medium text-gray-700" x-text="change.label"></td>
                                                                            <td class="px-3 py-2 text-red-600 line-through" x-text="change.old"></td>
                                                                            <td class="px-3 py-2 text-green-700 font-semibold" x-text="change.new"></td>
                                                                        </tr>
                                                                    </template>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <template x-if="hasPriceChange">
                                                            <p class="text-xs text-amber-600 mt-2">⚠ Selling price change will cascade to all active stock batches.</p>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-row justify-end gap-3 bg-gray-100 px-6 py-4">
                                    <button type="button" @click="showConfirm = false"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="button" :disabled="isSubmitting || changes.length === 0"
                                        @click="isSubmitting = true; showConfirm = false; document.getElementById('edit-product-form').submit()"
                                        class="inline-flex items-center rounded-md border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition bg-gray-800 hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span x-show="!isSubmitting">Confirm Update</span>
                                        <span x-show="isSubmitting">Processing...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
     </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                $('.select2').select2({
                    width: '100%',
                    placeholder: 'Select an option',
                    allowClear: false,
                });
            });

            function productEditForm() {
                const original = {
                    product_code:           '{{ addslashes($product->product_code) }}',
                    product_name:           '{{ addslashes($product->product_name) }}',
                    description:            '{{ addslashes($product->description ?? '') }}',
                    supplier_id:            '{{ $product->supplier_id ?? '' }}',
                    category_id:            '{{ $product->category_id ?? '' }}',
                    valuation_method:       '{{ $product->valuation_method }}',
                    uom_id:                 '{{ $product->uom_id }}',
                    sales_uom_id:           '{{ $product->sales_uom_id ?? '' }}',
                    uom_conversion_factor:  '{{ $product->uom_conversion_factor }}',
                    brand:                  '{{ addslashes($product->brand ?? '') }}',
                    barcode:                '{{ addslashes($product->barcode ?? '') }}',
                    pack_size:              '{{ addslashes($product->pack_size ?? '') }}',
                    weight:                 '{{ $product->weight ?? '' }}',
                    unit_sell_price:        '{{ $product->unit_sell_price ?? '' }}',
                    expiry_price:           '{{ $product->expiry_price ?? '' }}',
                    reorder_level:          '{{ $product->reorder_level ?? '' }}',
                    is_active:              '{{ $product->is_active ? '1' : '0' }}',
                    is_powder:              '{{ $product->is_powder ? '1' : '0' }}',
                };

                const labels = {
                    product_code:           'Product Code',
                    product_name:           'Product Name',
                    description:            'Description',
                    supplier_id:            'Supplier',
                    category_id:            'Category',
                    valuation_method:       'Valuation Method',
                    uom_id:                 'Base UOM',
                    sales_uom_id:           'Sales UOM',
                    uom_conversion_factor:  'Conversion Factor',
                    brand:                  'Brand',
                    barcode:                'Barcode',
                    pack_size:              'Pack Size',
                    weight:                 'Weight (kg)',
                    unit_sell_price:        'Selling Price',
                    expiry_price:           'Expiry Price',
                    reorder_level:          'Reorder Level',
                    is_active:              'Active Status',
                    is_powder:              'Is Powder',
                };

                const displayValue = (field, value) => {
                    if (field === 'is_active') return value === '1' ? 'Active' : 'Inactive';
                    if (field === 'is_powder') return value === '1' ? 'Yes' : 'No';
                    return value === '' || value === null || value === undefined ? '—' : value;
                };

                const normalise = (value) => (value ?? '').toString().trim();

                return {
                    showConfirm: false,
                    isSubmitting: false,
                    changes: [],
                    hasPriceChange: false,

                    openConfirm() {
                        const form = document.getElementById('edit-product-form');
                        const data = new FormData(form);
                        const detected = [];

                        for (const [field, oldVal] of Object.entries(original)) {
                            // For checkboxes, hidden + checkbox both submit — take the last value
                            const allVals = data.getAll(field);
                            let newVal = normalise(allVals.length > 0 ? allVals[allVals.length - 1] : '');
                            const old = normalise(oldVal);

                            // Normalise numeric fields to avoid 950.00 vs 950 mismatch
                            const numericFields = ['uom_conversion_factor', 'weight', 'unit_sell_price', 'expiry_price', 'reorder_level'];
                            let normOld = old, normNew = newVal;
                            if (numericFields.includes(field)) {
                                normOld = old !== '' ? parseFloat(old).toString() : '';
                                normNew = newVal !== '' ? parseFloat(newVal).toString() : '';
                            }

                            if (normOld !== normNew) {
                                detected.push({
                                    field,
                                    label: labels[field] ?? field,
                                    old: displayValue(field, old),
                                    new: displayValue(field, newVal),
                                });
                            }
                        }

                        this.changes = detected;
                        this.hasPriceChange = detected.some(c => c.field === 'unit_sell_price');
                        this.showConfirm = true;
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>