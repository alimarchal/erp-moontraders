<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
                Add Items to Goods Issue: {{ $goodsIssue->issue_number }}
            </h2>
            <a href="{{ route('goods-issues.show', $goodsIssue->id) }}"
                class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 mt-4 shadow-md" />

            @if ($draftSettlement)
                <div class="mb-4 rounded-md border-l-4 border-amber-400 bg-amber-50 p-4 shadow">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-semibold text-amber-800">Draft Settlement Exists</h3>
                            <p class="mt-1 text-sm text-amber-700">
                                A draft settlement <strong>{{ $draftSettlement->settlement_number }}</strong> already exists for this Goods Issue.
                                After adding items here, you must <a href="{{ route('sales-settlements.edit', $draftSettlement->id) }}" class="font-semibold underline">edit that draft settlement</a>
                                manually to include the new items, otherwise they will be excluded from the reconciliation.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($goodsIssue->status === 'issued')
                <div class="mb-4 rounded-md border-l-4 border-blue-400 bg-blue-50 p-4 shadow">
                    <p class="text-sm text-blue-800">
                        <strong>Note:</strong> This Goods Issue has already been posted. New items added here will be posted automatically (a separate journal entry will be created for the supplementary stock movement).
                    </p>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <x-validation-errors class="mb-4 mt-4" />

                    {{-- Read-only GI info --}}
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6 p-4 bg-gray-50 rounded-md border border-gray-200">
                        <div>
                            <div class="text-xs uppercase text-gray-500 font-semibold">Supplier</div>
                            <div class="text-sm font-medium text-gray-900">{{ $goodsIssue->supplier->supplier_name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 font-semibold">Issue Date</div>
                            <div class="text-sm font-medium text-gray-900">{{ $goodsIssue->issue_date->format('Y-m-d') }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 font-semibold">Warehouse</div>
                            <div class="text-sm font-medium text-gray-900">{{ $goodsIssue->warehouse->warehouse_name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 font-semibold">Salesman</div>
                            <div class="text-sm font-medium text-gray-900">{{ $goodsIssue->employee->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-gray-500 font-semibold">Vehicle</div>
                            <div class="text-sm font-medium text-gray-900">{{ $goodsIssue->vehicle->vehicle_number ?? '-' }} <span class="text-xs text-gray-500">({{ ucfirst($goodsIssue->status) }})</span></div>
                        </div>
                    </div>

                    {{-- Existing items (read-only) --}}
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Existing Items on this GI</h3>
                    <div class="overflow-x-auto mb-6">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-200 text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Line</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Product</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-700">Qty Issued</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">UOM</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-700">Unit Cost</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-700">Total Value</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-700">Type</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($goodsIssue->items as $existingItem)
                                    <tr>
                                        <td class="px-3 py-2">{{ $existingItem->line_no }}</td>
                                        <td class="px-3 py-2">{{ $existingItem->product->product_name ?? '-' }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($existingItem->quantity_issued, 2) }}</td>
                                        <td class="px-3 py-2">{{ $existingItem->uom->uom_name ?? '-' }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($existingItem->unit_cost, 2) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($existingItem->total_value, 2) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if ($existingItem->is_supplementary)
                                                <span class="inline-flex px-2 py-0.5 text-xs rounded bg-purple-100 text-purple-700 font-semibold">Supplementary</span>
                                            @else
                                                <span class="inline-flex px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-700">Original</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- New items form --}}
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Add New Items</h3>

                    <form method="POST" action="{{ route('goods-issues.store-appended-items', $goodsIssue->id) }}"
                        id="appendItemsForm" x-data="appendItemsForm()">
                        @csrf

                        <x-form-table title="Products to Add" :sticky-header="true" :headers="array_filter([
        ['label' => 'Product', 'align' => 'text-left', 'width' => '300px'],
        ['label' => 'Non-Promo<br>Only', 'align' => 'text-center', 'width' => '70px'],
        ['label' => 'Qty<br>Available', 'align' => 'text-center', 'width' => '110px'],
        $canEnterCartons ? ['label' => 'Carton', 'align' => 'text-center', 'width' => '90px'] : null,
        $canEnterCartons ? ['label' => 'Pieces', 'align' => 'text-center', 'width' => '90px'] : null,
        ['label' => 'Qty<br>Issued', 'align' => 'text-center', 'width' => '110px'],
        ['label' => 'UOM', 'align' => 'text-center', 'width' => '110px'],
        ['label' => 'Price<br>Breakdown', 'align' => 'text-left', 'width' => '200px'],
        ['label' => 'Total<br>Value', 'align' => 'text-right', 'width' => '130px'],
        ['label' => 'Action', 'align' => 'text-center', 'width' => '70px'],
    ])">
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="align-top">
                                        <td class="px-2 py-2 align-middle">
                                            <select :id="`product_${index}`" :name="`items[${index}][product_id]`"
                                                required
                                                class="product-select select2 border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                <option value="">Select Product</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 text-center align-middle">
                                            <input type="hidden" :name="`items[${index}][exclude_promotional]`" value="0">
                                            <input type="checkbox" :name="`items[${index}][exclude_promotional]`"
                                                x-model="item.exclude_promotional"
                                                @change="onExcludePromotionalChange(index)"
                                                value="1"
                                                :disabled="!item.product_id"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 h-5 w-5"
                                                title="Check to exclude promotional batches">
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <input type="text" :id="`available_qty_${index}`" readonly
                                                x-model="item.available_qty"
                                                :class="parseFloat(item.available_qty) <= 0 ? 'border-red-300 bg-red-50' : 'border-gray-300 bg-gray-100'"
                                                class="rounded-md shadow-sm text-sm w-full text-center font-semibold">
                                        </td>
                                        @if ($canEnterCartons)
                                            <td class="px-2 py-2 align-middle">
                                                <input type="number"
                                                    x-model="item.carton_qty"
                                                    @input="recalcFromCartonPieces(index)"
                                                    min="0" step="1"
                                                    :disabled="parseFloat(item.available_qty) <= 0 || !item.conversion_factor"
                                                    :class="(parseFloat(item.available_qty) <= 0 || !item.conversion_factor) ? 'bg-gray-200 cursor-not-allowed' : 'bg-white'"
                                                    class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full text-center"
                                                    placeholder="0">
                                            </td>
                                            <td class="px-2 py-2 align-middle">
                                                <input type="number"
                                                    x-model="item.pieces_qty"
                                                    @input="recalcFromCartonPieces(index)"
                                                    min="0" step="1"
                                                    :disabled="parseFloat(item.available_qty) <= 0"
                                                    :class="parseFloat(item.available_qty) <= 0 ? 'bg-gray-200 cursor-not-allowed' : 'bg-white'"
                                                    class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full text-center"
                                                    placeholder="0">
                                            </td>
                                        @endif
                                        <td class="px-2 py-2 align-middle">
                                            <input type="number" :name="`items[${index}][quantity_issued]`"
                                                x-model="item.quantity_issued"
                                                @input="onDirectQtyInput(index)" step="0.001"
                                                :max="item.available_qty" min="0.001"
                                                :disabled="parseFloat(item.available_qty) <= 0"
                                                :required="parseFloat(item.available_qty) > 0"
                                                :class="parseFloat(item.available_qty) <= 0 ? 'bg-gray-200 cursor-not-allowed' : 'bg-white'"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full"
                                                @if ($canEnterCartons) readonly title="Auto-calculated from Carton + Pieces" @endif>
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <select :name="`items[${index}][uom_id]`" x-model="item.uom_id"
                                                :disabled="parseFloat(item.available_qty) <= 0"
                                                :required="parseFloat(item.available_qty) > 0"
                                                :class="parseFloat(item.available_qty) <= 0 ? 'bg-gray-200 cursor-not-allowed' : 'bg-white'"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                <option value="">UOM</option>
                                                @foreach ($uoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->uom_name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            @if ($canEnterCartons)
                                                <div x-show="item.conversion_factor > 1" class="text-xs text-indigo-600 font-medium mb-1">
                                                    <span x-text="'1 Ctn = ' + item.conversion_factor + ' Pcs'"></span>
                                                </div>
                                            @endif
                                            <div :id="`batch_info_${index}`" class="text-xs text-gray-600 max-w-xs"></div>
                                            <div :id="`price_breakdown_${index}`" class="text-xs text-gray-700 max-w-xs"></div>
                                            <input type="hidden" :name="`items[${index}][unit_cost]`" x-model="item.unit_cost">
                                            <input type="hidden" :name="`items[${index}][selling_price]`" x-model="item.selling_price">
                                        </td>
                                        <td class="px-2 py-2 text-right text-sm font-semibold align-middle"
                                            x-text="formatNumber(item.total_value)"></td>
                                        <td class="px-2 py-2 text-center align-middle">
                                            <button type="button" @click="removeItem(index)"
                                                class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                                :class="(items.length === 1) ? 'opacity-40 cursor-not-allowed hover:bg-transparent hover:text-red-600 pointer-events-none' : ''"
                                                :disabled="items.length === 1" title="Remove Line">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr class="font-semibold bg-gray-100">
                                    <td class="px-2 py-2 text-right" colspan="{{ $canEnterCartons ? 5 : 3 }}">Totals:</td>
                                    <td class="px-2 py-2 text-right"
                                        x-text="formatNumber(items.reduce((sum, item) => sum + (parseFloat(item.quantity_issued) || 0), 0))">
                                    </td>
                                    <td class="px-2 py-2"></td>
                                    <td class="px-2 py-2"></td>
                                    <td class="px-2 py-2 text-right font-bold text-lg" x-text="formatNumber(grandTotal)"></td>
                                    <td class="px-2 py-2"></td>
                                </tr>
                                <tr>
                                    <td colspan="{{ $canEnterCartons ? 10 : 8 }}" class="px-2 py-2">
                                        <button type="button" @click="addItem()"
                                            class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Product
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </x-form-table>

                        <div class="flex items-center justify-end mt-6">
                            <x-button type="button" @click="validateAndSubmit()">
                                Append Items to {{ $goodsIssue->issue_number }}
                            </x-button>
                        </div>

                        {{-- Append-items submit confirmation lives inside the form's
                             x-data scope so it binds directly via Alpine reactivity. --}}
                        <div x-show="showConfirmModal"
                             x-cloak
                             x-on:keydown.escape.window="if (showConfirmModal && !isSubmittingAppend) { showConfirmModal = false }"
                             class="fixed inset-0 z-50"
                             style="display: none;">
                            <div x-show="showConfirmModal"
                                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 backdrop-blur-none" x-transition:enter-end="opacity-100 backdrop-blur-sm"
                                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 backdrop-blur-sm" x-transition:leave-end="opacity-0 backdrop-blur-none"
                                 class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all"
                                 @click="if (!isSubmittingAppend) { showConfirmModal = false }"></div>

                            <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
                                <div x-show="showConfirmModal"
                                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                                     class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
                                     @click.outside="if (!isSubmittingAppend) { showConfirmModal = false }">

                                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                        <div class="sm:flex sm:items-start">
                                            <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-purple-100 sm:mx-0 sm:size-10">
                                                <svg class="size-6 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                                <h3 class="text-lg font-medium leading-6 text-gray-900">Confirm Append</h3>
                                                <div class="mt-2 text-sm text-gray-600">
                                                    <p>You are about to append the following items to <strong x-text="confirmIssueNumber"></strong>:</p>
                                                    <ul class="mt-2 list-disc list-inside text-gray-700">
                                                        <li><strong x-text="confirmLineCount"></strong> new line(s)</li>
                                                        <li>Total quantity: <strong x-text="confirmTotalQty"></strong></li>
                                                        <li>Total value: ₨<strong x-text="confirmTotalValue"></strong></li>
                                                    </ul>
                                                    <p class="mt-3">These will be posted as supplementary stock movements against the same vehicle. Do you agree?</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-row justify-end gap-3 bg-gray-100 px-6 py-4">
                                        <button type="button" :disabled="isSubmittingAppend" @click="showConfirmModal = false"
                                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50">
                                            Cancel
                                        </button>
                                        <button type="button" :disabled="isSubmittingAppend" @click="confirmAndSubmit()"
                                                class="inline-flex items-center rounded-md border border-transparent bg-purple-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-purple-700 disabled:opacity-75">
                                            <span x-show="!isSubmittingAppend">Yes, Append Items</span>
                                            <span x-show="isSubmittingAppend">Processing...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            const APPEND_GI = {
                id: {{ $goodsIssue->id }},
                warehouseId: {{ $goodsIssue->warehouse_id }},
                supplierId: {{ $goodsIssue->supplier_id ?? 'null' }},
            };
            const canEnterCartons = @json($canEnterCartons);
            let allProducts = [];
            let productBatches = {};

            function appendItemsForm() {
                return {
                    items: [{
                        product_id: '',
                        uom_id: '',
                        quantity_issued: 0,
                        unit_cost: 0,
                        selling_price: 0,
                        total_value: 0,
                        available_qty: 0,
                        exclude_promotional: false,
                        carton_qty: 0,
                        pieces_qty: 0,
                        conversion_factor: 1,
                    }],

                    // Confirmation modal state (co-located with form scope so it
                    // doesn't depend on cross-component CustomEvents).
                    showConfirmModal: false,
                    isSubmittingAppend: false,
                    confirmIssueNumber: @json($goodsIssue->issue_number),
                    confirmLineCount: 0,
                    confirmTotalQty: '0.00',
                    confirmTotalValue: '0.00',

                    validateAndSubmit() {
                        const validItems = this.items.filter(item => {
                            const qty = parseFloat(item.quantity_issued) || 0;
                            return qty > 0 && item.product_id;
                        });

                        if (validItems.length === 0) {
                            window.dispatchEvent(new CustomEvent('open-alert-modal', {
                                detail: {
                                    title: 'Cannot Append!',
                                    message: '<p>No valid items to append.</p><p class="mt-2">Please add at least one product with a valid quantity.</p>'
                                }
                            }));
                            return false;
                        }

                        this.items = validItems;

                        // Compute summary stats for the confirmation modal
                        const totalLines = validItems.length;
                        const totalQty = validItems.reduce((s, it) => s + (parseFloat(it.quantity_issued) || 0), 0);
                        const totalValue = validItems.reduce((s, it) => s + (parseFloat(it.quantity_issued) || 0) * (parseFloat(it.selling_price) || 0), 0);

                        this.confirmLineCount = totalLines;
                        this.confirmTotalQty = totalQty.toFixed(2);
                        this.confirmTotalValue = totalValue.toFixed(2);
                        this.isSubmittingAppend = false;
                        this.showConfirmModal = true;
                    },

                    confirmAndSubmit() {
                        if (this.isSubmittingAppend) {
                            return;
                        }
                        this.isSubmittingAppend = true;
                        this.$nextTick(() => {
                            document.getElementById('appendItemsForm').submit();
                        });
                    },

                    addItem() {
                        if (allProducts.length === 0) {
                            window.dispatchEvent(new CustomEvent('open-alert-modal', {
                                detail: {
                                    title: 'Loading',
                                    message: '<p>Products are still loading. Please wait a moment and try again.</p>'
                                }
                            }));
                            return;
                        }

                        const newIndex = this.items.length;
                        this.items.push({
                            product_id: '',
                            uom_id: '',
                            quantity_issued: 0,
                            unit_cost: 0,
                            selling_price: 0,
                            total_value: 0,
                            available_qty: 0,
                            exclude_promotional: false,
                            carton_qty: 0,
                            pieces_qty: 0,
                            conversion_factor: 1,
                        });

                        this.$nextTick(() => {
                            initializeProductSelect2(newIndex);
                        });
                    },

                    removeItem(index) {
                        if (this.items.length > 1) {
                            const productId = this.items[index].product_id;
                            if (productId) {
                                delete productBatches[productId];
                                delete productBatches[`${productId}_np`];
                            }
                            this.items.splice(index, 1);
                            this.$nextTick(() => {
                                $('.product-select').each(function () {
                                    if ($(this).data('select2')) {
                                        $(this).select2('destroy');
                                    }
                                });
                                $('.product-select').each(function (idx) {
                                    initializeProductSelect2(idx);
                                });
                            });
                        }
                    },

                    updatePriceBasedOnQuantity(index) {
                        const item = this.items[index];
                        const productId = item.product_id;
                        const quantity = parseFloat(item.quantity_issued) || 0;
                        const availableQty = parseFloat(item.available_qty) || 0;
                        const excludePromo = item.exclude_promotional;
                        const batchKey = excludePromo ? `${productId}_np` : productId;

                        if (!productId || !productBatches[batchKey]) {
                            document.getElementById(`price_breakdown_${index}`).innerHTML = '';
                            document.getElementById(`batch_info_${index}`).innerHTML = '';
                            item.total_value = 0;
                            return;
                        }

                        if (quantity === 0) {
                            document.getElementById(`price_breakdown_${index}`).innerHTML = '<span class="text-gray-400">Enter quantity</span>';
                            document.getElementById(`batch_info_${index}`).innerHTML = '';
                            item.total_value = 0;
                            return;
                        }

                        if (quantity > availableQty) {
                            document.getElementById(`batch_info_${index}`).innerHTML = '<div class="text-red-600 font-bold">⚠️ Quantity exceeds available stock!</div>';
                            document.getElementById(`price_breakdown_${index}`).innerHTML =
                                `<div class="text-red-600">Entered: ${quantity.toFixed(0)}</div><div class="text-green-600">Available: ${availableQty.toFixed(0)}</div>`;
                            item.total_value = 0;
                            item.unit_cost = 0;
                            item.quantity_issued = availableQty;
                            this.updatePriceBasedOnQuantity(index);
                            return;
                        }

                        const batches = productBatches[batchKey];
                        let remainingQty = quantity;
                        let totalValue = 0;
                        let totalCost = 0;
                        let batchesUsed = [];

                        for (const batch of batches) {
                            if (remainingQty <= 0) break;
                            const qtyFromBatch = Math.min(remainingQty, batch.quantity);
                            const batchValue = qtyFromBatch * batch.selling_price;
                            const batchCost = qtyFromBatch * batch.unit_cost;
                            totalValue += batchValue;
                            totalCost += batchCost;
                            remainingQty -= qtyFromBatch;
                            if (qtyFromBatch > 0) {
                                batchesUsed.push({
                                    code: batch.batch_code,
                                    qty: qtyFromBatch,
                                    price: batch.selling_price,
                                    cost: batch.unit_cost,
                                    value: batchValue,
                                    is_promotional: batch.is_promotional
                                });
                            }
                        }

                        const batchInfoDiv = document.getElementById(`batch_info_${index}`);
                        if (batchesUsed.length > 0) {
                            let info = '<div class="text-blue-600 font-semibold mb-1">📦 Issuing from batches:</div>';
                            batchesUsed.forEach((b, bIndex) => {
                                const promo = b.is_promotional ? ' 🎁' : '';
                                info += `<div>Batch ${bIndex + 1}: ${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)}${promo}</div>`;
                            });
                            batchInfoDiv.innerHTML = info;
                        }

                        const priceBreakdownDiv = document.getElementById(`price_breakdown_${index}`);
                        if (batchesUsed.length === 1) {
                            const b = batchesUsed[0];
                            priceBreakdownDiv.innerHTML = `<div class="text-sm font-semibold text-green-700 mt-1">${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)} = ₨${b.value.toFixed(2)}</div>`;
                        } else {
                            let html = '<div class="mt-1 border-t border-gray-200 pt-1">';
                            batchesUsed.forEach((b, bIndex) => {
                                const promo = b.is_promotional ? ' 🎁' : '';
                                html += `<div class="text-sm">Batch ${bIndex + 1}: ${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)} = ₨${b.value.toFixed(2)}${promo}</div>`;
                            });
                            html += `<div class="font-bold text-green-700 border-t border-gray-300 pt-1 mt-1">Total: ₨${totalValue.toFixed(2)}</div>`;
                            html += '</div>';
                            priceBreakdownDiv.innerHTML = html;
                        }

                        item.total_value = totalValue;
                        item.unit_cost = quantity > 0 ? totalCost / quantity : 0;
                    },

                    async onExcludePromotionalChange(index) {
                        const item = this.items[index];
                        if (!item.product_id) return;
                        const savedQty = parseFloat(item.quantity_issued) || 0;
                        await onProductChange(index, item.product_id);
                        item.quantity_issued = savedQty;
                        if (savedQty > 0) {
                            this.updatePriceBasedOnQuantity(index);
                        }
                    },

                    recalcFromCartonPieces(index) {
                        if (!canEnterCartons) return;
                        const item = this.items[index];
                        const cartons = parseInt(item.carton_qty) || 0;
                        const pieces = parseInt(item.pieces_qty) || 0;
                        const factor = parseFloat(item.conversion_factor) || 1;
                        item.quantity_issued = (cartons * factor) + pieces;
                        this.updatePriceBasedOnQuantity(index);
                    },

                    onDirectQtyInput(index) {
                        if (canEnterCartons) return;
                        this.updatePriceBasedOnQuantity(index);
                    },

                    get grandTotal() {
                        return this.items.reduce((sum, item) => sum + (parseFloat(item.total_value) || 0), 0);
                    },

                    formatNumber(value) {
                        return parseFloat(value || 0).toLocaleString('en-PK', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                }
            }

            async function loadProductsForGi() {
                const params = new URLSearchParams();
                if (APPEND_GI.supplierId) {
                    params.append('supplier_ids[]', APPEND_GI.supplierId);
                }
                try {
                    const response = await fetch(`/api/products/by-suppliers?${params.toString()}`);
                    allProducts = await response.json();
                } catch (e) {
                    console.error('Error loading products', e);
                }
            }

            async function initializeProductSelect2(index) {
                const $select = $(`#product_${index}`);
                const alpineComponent = Alpine.$data($select.closest('form')[0]);

                $select.select2({
                    placeholder: 'Select Product',
                    allowClear: false,
                    width: '100%',
                    data: allProducts.map(p => ({
                        id: p.id,
                        text: `${p.product_code} - ${p.product_name}`
                    }))
                });

                $select.on('change', async function () {
                    const productId = $(this).val();
                    if (alpineComponent && alpineComponent.items && alpineComponent.items[index]) {
                        alpineComponent.items[index].product_id = productId;
                        if (productId) {
                            await onProductChange(index, productId);
                        }
                    }
                });
            }

            async function onProductChange(index, productId) {
                if (!productId) return;
                const alpineComponent = Alpine.$data(document.querySelector('[x-data="appendItemsForm()"]'));

                // Block duplicates within the new items list
                const isDuplicate = alpineComponent.items.some((item, idx) => idx !== index && String(item.product_id) === String(productId));
                if (isDuplicate) {
                    window.dispatchEvent(new CustomEvent('open-alert-modal', {
                        detail: {
                            title: 'Duplicate Product',
                            message: '<p>This product is already added to the new items list.</p>'
                        }
                    }));
                    $(`#product_${index}`).val('').trigger('change');
                    alpineComponent.items[index].product_id = '';
                    return;
                }

                try {
                    const excludePromo = alpineComponent.items[index].exclude_promotional ? 1 : 0;
                    const response = await fetch(`/api/warehouses/${APPEND_GI.warehouseId}/products/${productId}/stock?exclude_promotional=${excludePromo}`);
                    const data = await response.json();

                    const batchKey = excludePromo ? `${productId}_np` : productId;
                    productBatches[batchKey] = data.batches || [];

                    alpineComponent.items[index].available_qty = parseFloat(data.available_quantity || 0).toFixed(2);
                    alpineComponent.items[index].uom_id = data.stock_uom_id || '';

                    if (canEnterCartons) {
                        alpineComponent.items[index].conversion_factor = parseFloat(data.conversion_factor) || 1;
                        alpineComponent.items[index].carton_qty = 0;
                        alpineComponent.items[index].pieces_qty = 0;
                    }

                    if (data.batches && data.batches.length > 0) {
                        alpineComponent.items[index].selling_price = parseFloat(data.batches[0].selling_price || 0);
                    } else {
                        alpineComponent.items[index].selling_price = 0;
                    }

                    const batchInfoDiv = document.getElementById(`batch_info_${index}`);
                    if (data.batches && data.batches.length > 0) {
                        if (data.has_multiple_prices) {
                            let html = '<div class="text-orange-600 font-semibold">⚠️ Multiple batch prices:</div>';
                            data.batches.forEach((b, idx) => {
                                const promo = b.is_promotional ? ' 🎁' : '';
                                html += `<div class="ml-2">Batch ${idx + 1}: ${b.quantity.toFixed(0)} @ ₨${b.selling_price.toFixed(2)}${promo}</div>`;
                            });
                            batchInfoDiv.innerHTML = html;
                        } else {
                            batchInfoDiv.innerHTML = `<div class="text-green-600">✓ Single price: ₨${data.batches[0].selling_price.toFixed(2)}</div>`;
                        }
                    } else {
                        batchInfoDiv.innerHTML = '';
                    }

                    if (alpineComponent.items[index].quantity_issued === 0) {
                        alpineComponent.items[index].total_value = 0;
                        alpineComponent.items[index].unit_cost = 0;
                        document.getElementById(`price_breakdown_${index}`).innerHTML = '';
                    }
                } catch (e) {
                    console.error('Error fetching product stock', e);
                }
            }

            (function init() {
                if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                    setTimeout(init, 100);
                    return;
                }
                $(document).ready(async function () {
                    await loadProductsForGi();
                    initializeProductSelect2(0);
                });
            })();
        </script>
    @endpush

    <x-alpine-alert-modal
        event-name="open-alert-modal"
        title="Alert"
        button-text="OK"
        button-class="bg-red-600 hover:bg-red-700"
        icon-bg-class="bg-red-100"
        icon-color-class="text-red-600"
    />
</x-app-layout>
