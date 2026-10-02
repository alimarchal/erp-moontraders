<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('goods-issues.index') }}">Goods Issues</a><span aria-hidden="true">›</span>
                    <a href="{{ route('goods-issues.show', $goodsIssue) }}">{{ $goodsIssue->issue_number }}</a><span aria-hidden="true">›</span><span>Edit</span>
                </nav>
                <h1 class="ak-title">Edit Goods Issue {{ $goodsIssue->issue_number }}</h1>
                <p class="ak-sub">
                    <span class="ak-status ak-status-amber"><i aria-hidden="true"></i>Draft</span>
                    &middot; created {{ $goodsIssue->created_at?->format('d M Y, h:i A') }}{{ $goodsIssue->issuedBy ? ' by '.$goodsIssue->issuedBy->name : '' }}
                    &middot; stock moves only when it is posted
                </p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('goods-issues.show', $goodsIssue) }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to {{ $goodsIssue->issue_number }}</a>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('goods-issues.partials.form-style')

    <div class="ak-page gf-page">
        <x-status-message class="mb-4 mt-4 shadow-md" />
        <x-validation-errors class="mb-4" />

                    <form method="POST" action="{{ route('goods-issues.update', $goodsIssue) }}" id="goodsIssueForm"
                        x-data="goodsIssueForm()">
                        @csrf
                        @method('PUT')

                        <section class="uf-card gf-card" aria-labelledby="gf-details">
                            <header class="uf-card-head">
                                <div>
                                    <h2 class="uf-card-title" id="gf-details"><span class="uf-step">1</span> Issue details</h2>
                                    <p class="uf-card-sub">Pick the supplier first: its salesmen appear, then the salesman's vehicle.</p>
                                </div>
                            </header>
                            <div class="uf-body">
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <x-label for="supplier_ids" value="Supplier *" />
                                {{-- For multiple suppliers, uncomment this and remove the single select below:
                                <select id="supplier_ids" name="supplier_ids[]" multiple required
                                    class="select2-multi border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                --}}
                                <select id="supplier_ids" name="supplier_ids[]" required
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ (is_array(old('supplier_ids')) && in_array($supplier->id, old('supplier_ids')))
                                                || (!old('supplier_ids') && $goodsIssue->supplier_id == $supplier->id) ? 'selected' : '' }}>
                                            {{ $supplier->supplier_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="issue_date" value="Issue Date *" />
                                <x-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full"
                                    :value="old('issue_date', $goodsIssue->issue_date ? $goodsIssue->issue_date->format('Y-m-d') : '')"
                                    required />
                            </div>

                            <div>
                                <x-label for="warehouse_id" value="Warehouse *" />
                                <select id="warehouse_id" name="warehouse_id" required
                                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ old('warehouse_id', $goodsIssue->
                                        warehouse_id)==$warehouse->id ?
                                        'selected' : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="employee_id" value="Salesman *" />
                                <select id="employee_id" name="employee_id" required
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Salesman</option>
                                </select>
                            </div>

                            <div>
                                <x-label for="vehicle_id" value="Vehicle *" />
                                <select id="vehicle_id" name="vehicle_id" required
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">Select Vehicle</option>
                                </select>
                            </div>
                        </div>
                            </div>
                        </section>

                        <section class="uf-card gf-card" aria-labelledby="gf-products">
                            <header class="uf-card-head">
                                <div>
                                    <h2 class="uf-card-title" id="gf-products"><span class="uf-step">2</span> Products to issue</h2>
                                    <p class="uf-card-sub">Stock is taken from the oldest / soonest-expiring batches first. Lines in red have less stock than needed.</p>
                                </div>
                                <div class="gf-head-stats">
                                    <span class="ak-pill" x-text="items.filter(i => i.product_id).length + ' of ' + items.length + ' lines filled'"></span>
                                    <span class="ak-pill ak-pill-red" x-show="items.some(i => i.product_id && i.stock_short)" x-cloak
                                        x-text="items.filter(i => i.product_id && i.stock_short).length + ' short of stock'"></span>
                                </div>
                            </header>
                            <div class="gf-table">
                        <x-form-table :title="null" :sticky-header="true" :headers="array_filter([
                            ['label' => 'Product', 'align' => 'text-left', 'width' => '300px'],
                            ['label' => 'Non-Promo<br>Only', 'align' => 'text-center', 'width' => '70px'],
                            ['label' => 'Qty<br>Available', 'align' => 'text-center', 'width' => '110px'],
                            $canEnterCartons ? ['label' => 'Carton', 'align' => 'text-center', 'width' => '90px'] : null,
                            $canEnterCartons ? ['label' => 'Pieces', 'align' => 'text-center', 'width' => '90px'] : null,
                            ['label' => 'Qty<br>Issued', 'align' => 'text-center', 'width' => '110px'],
                            ['label' => 'UOM', 'align' => 'text-center', 'width' => '110px'],
                            ['label' => 'Price<br>Breakdown', 'align' => 'text-left', 'width' => '250px'],
                            ['label' => 'Total<br>Value', 'align' => 'text-right', 'width' => '130px'],
                            ['label' => 'Action', 'align' => 'text-center', 'width' => '70px'],
                        ])">
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(item, index) in items" :key="item.uid">
                                    <tr class="align-top">
                                        <td class="px-2 py-2 align-middle">
                                            <select :id="`product_${item.uid}`" :name="`items[${index}][product_id]`"
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
                                            <input type="text" :id="`available_qty_${item.uid}`" readonly
                                                x-model="item.available_qty"
                                                :class="parseFloat(item.available_qty) <= 0 ? 'border-red-300 bg-red-50' : 'border-gray-300 bg-gray-100'"
                                                class="rounded-md shadow-sm text-sm w-full text-center font-semibold">
                                            <div x-show="item.in_other_drafts > 0" x-cloak class="mt-1 text-xs leading-tight text-center"
                                                :class="draftsExceedFree(item) ? 'text-amber-700 font-semibold' : 'text-gray-500'"
                                                :title="otherDraftsTitle(item)">
                                                <span x-text="'In drafts: ' + formatQty(item.in_other_drafts)"></span>
                                            </div>
                                        </td>
                                        @if($canEnterCartons)
                                        <td class="px-2 py-2 align-middle">
                                            <input type="number"
                                                x-model="item.carton_qty"
                                                @input="recalcFromCartonPieces(index)"
                                                min="0" step="1"
                                                :disabled="(parseFloat(item.available_qty) <= 0 && !item.stock_short) || !item.conversion_factor"
                                                :class="((parseFloat(item.available_qty) <= 0 && !item.stock_short) || !item.conversion_factor) ? 'bg-gray-200 cursor-not-allowed' : (item.stock_short ? 'bg-red-50 border-red-400' : 'bg-white')"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full text-center"
                                                placeholder="0">
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <input type="number"
                                                x-model="item.pieces_qty"
                                                @input="recalcFromCartonPieces(index)"
                                                min="0" step="1"
                                                :disabled="parseFloat(item.available_qty) <= 0 && !item.stock_short"
                                                :class="(parseFloat(item.available_qty) <= 0 && !item.stock_short) ? 'bg-gray-200 cursor-not-allowed' : (item.stock_short ? 'bg-red-50 border-red-400' : 'bg-white')"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full text-center"
                                                placeholder="0">
                                        </td>
                                        @endif
                                        <td class="px-2 py-2 align-middle">
                                            <input type="number" :name="`items[${index}][quantity_issued]`"
                                                x-model="item.quantity_issued"
                                                @input="onDirectQtyInput(index)" step="0.001"
                                                :max="item.available_qty" min="0.001"
                                                :disabled="parseFloat(item.available_qty) <= 0 && !item.stock_short"
                                                :required="parseFloat(item.available_qty) > 0 || item.stock_short"
                                                :class="(parseFloat(item.available_qty) <= 0 && !item.stock_short) ? 'bg-gray-200 cursor-not-allowed' : (item.stock_short ? 'bg-red-50 border-red-400' : 'bg-white')"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full"
                                                @if($canEnterCartons) readonly title="Auto-calculated from Carton + Pieces" @endif>
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <select :name="`items[${index}][uom_id]`" x-model="item.uom_id"
                                                :disabled="parseFloat(item.available_qty) <= 0 && !item.stock_short"
                                                :required="parseFloat(item.available_qty) > 0 || item.stock_short"
                                                :class="(parseFloat(item.available_qty) <= 0 && !item.stock_short) ? 'bg-gray-200 cursor-not-allowed' : (item.stock_short ? 'bg-red-50 border-red-400' : 'bg-white')"
                                                class="border-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm w-full">
                                                <option value="">Select UOM</option>
                                                @foreach ($uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->uom_name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            @if($canEnterCartons)
                                            <div x-show="item.conversion_factor > 1" class="text-xs text-indigo-600 font-medium mb-1">
                                                <span x-text="'1 Ctn = ' + item.conversion_factor + ' Pcs'"></span>
                                            </div>
                                            @endif
                                            <div :id="`batch_info_${item.uid}`" class="text-xs text-gray-600 max-w-xs">
                                            </div>
                                            <div :id="`price_breakdown_${item.uid}`"
                                                class="text-xs text-gray-700 max-w-xs"></div>
                                            <input type="hidden" :name="`items[${index}][unit_cost]`"
                                                x-model="item.unit_cost">
                                            <input type="hidden" :name="`items[${index}][selling_price]`"
                                                x-model="item.selling_price">
                                        </td>
                                        <td class="px-2 py-2 text-right text-sm font-semibold align-middle"
                                            x-text="formatNumber(item.total_value)"></td>
                                        <td class="px-2 py-2 text-center align-middle">
                                            <button type="button" @click="removeItem(index)"
                                                class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                                :class="(index === 0 || items.length === 1) ? 'opacity-40 cursor-not-allowed hover:bg-transparent hover:text-red-600 pointer-events-none' : ''"
                                                :disabled="index === 0 || items.length === 1" title="Remove Line">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
                                    <td class="px-2 py-2 text-right font-bold text-lg"
                                        x-text="formatNumber(grandTotal)">
                                    </td>
                                    <td class="px-2 py-2"></td>
                                </tr>
                                <tr>
                                    <td colspan="{{ $canEnterCartons ? 10 : 8 }}" class="px-2 py-2">
                                        <div class="flex items-center gap-2">
                                            <input type="number" x-model.number="addProductCount" min="1"
                                                class="w-20 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                            <span class="text-xs text-gray-500">lines</span>
                                            <button type="button" @click="addItems()"
                                                class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Product
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </x-form-table>
                            </div>
                        </section>

                        <section class="uf-card gf-card" aria-labelledby="gf-notes">
                            <header class="uf-card-head">
                                <h2 class="uf-card-title" id="gf-notes"><span class="uf-step">3</span> Notes <span class="ak-muted" style="font-weight:400; font-size:12.5px">(optional)</span></h2>
                            </header>
                            <div class="uf-body">
                                <x-label for="notes" value="Notes" />
                                <textarea id="notes" name="notes" rows="2"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">{{ old('notes', $goodsIssue->notes) }}</textarea>
                            </div>
                        </section>

                        <div class="uf-actions gf-actions">
                            <div class="gf-totals" aria-live="polite">
                                <span><b x-text="items.filter(i => i.product_id).length"></b> products</span>
                                <span><b x-text="formatNumber(items.reduce((sum, item) => sum + (parseFloat(item.quantity_issued) || 0), 0))"></b> total qty</span>
                                <span>Value Rs <b x-text="formatNumber(grandTotal)"></b></span>
                                <span class="gf-warn" x-show="items.some(i => i.product_id && i.stock_short)" x-cloak>Reduce or remove the red lines before saving</span>
                            </div>
                            <div>
                                <a href="{{ route('goods-issues.show', $goodsIssue) }}" class="ak-btn ak-btn-outline">Cancel</a>
                                <x-button type="button" @click="validateAndSubmit()"
                                class="!bg-green-600 hover:!bg-green-700 focus:!bg-green-700 active:!bg-green-800 focus:!ring-green-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                Update Goods Issue
                            </x-button>
                            </div>
                        </div>
                    </form>
    </div>

    @push('header')
        <style>
            .select2-container .select2-selection--multiple {
                min-height: 42px !important;
                border-color: #d1d5db !important;
                border-radius: 0.375rem !important;
                padding: 4px !important;
                display: flex !important;
                align-items: center !important;
                flex-wrap: wrap;
            }

            .select2-container--default.select2-container--focus .select2-selection--multiple {
                border-color: #6366f1 !important;
                box-shadow: 0 0 0 1px #6366f1 !important;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__rendered {
                padding-left: 0 !important;
                margin: 0 !important;
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__choice {
                margin-top: 0 !important;
                margin-bottom: 0 !important;
            }

            .select2-search__field {
                margin-top: 0 !important;
                height: 24px !important;
            }
        </style>
    @endpush

    @push('scripts')
    <script>
        let allProducts = [];
        let productBatches = {};

        let nextRowUid = 1;
        let pendingShortages = [];
        let shortageAlertTimer = null;

        /**
         * Rows are identified by a uid that never changes, not by their position. Removing a row
         * used to re-bind every row below it by index, so the product, stock and quantities of
         * neighbouring lines could end up on the wrong row.
         */
        function makeRow(values = {}) {
            return Object.assign({
                uid: nextRowUid++,
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
                in_other_drafts: 0,
                other_drafts: [],
                stock_short: false,
            }, values);
        }

        function formComponent() {
            return Alpine.$data(document.getElementById('goodsIssueForm'));
        }

        function findRow(uid) {
            return formComponent().items.find(row => row.uid === uid);
        }

        function rowElement(item, part) {
            return document.getElementById(`${part}_${item.uid}`);
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
        }

        function productLabel(productId) {
            const product = allProducts.find(p => String(p.id) === String(productId));
            return product ? `${product.product_code} - ${product.product_name}` : `Product #${productId}`;
        }

        function formatStockQty(quantity, factor) {
            const pieces = Number((parseFloat(quantity) || 0).toFixed(3));
            if (!canEnterCartons || !(factor > 1)) {
                return `${pieces} pcs`;
            }
            const cartons = Math.floor((pieces + 0.0001) / factor);
            const loose = Number((pieces - cartons * factor).toFixed(3));
            return `${cartons} ctn + ${loose} pcs (${pieces} pcs)`;
        }

        function otherDraftsHtml(item) {
            if (!item.other_drafts || item.other_drafts.length === 0) {
                return '';
            }
            const factor = parseFloat(item.conversion_factor) || 1;
            const lines = item.other_drafts
                .map(d => `<div>${escapeHtml(d.issue_number)}${d.vehicle ? ' (' + escapeHtml(d.vehicle) + ')' : ''}: ${formatStockQty(d.quantity, factor)}</div>`)
                .join('');
            return `<div class="mt-1 border-t border-amber-300 pt-1 text-amber-700"><div class="font-semibold">Also in other drafts:</div>${lines}</div>`;
        }

        /**
         * A draft is opened after other issues were posted: every line stock no longer covers is
         * gathered into one alert, instead of one alert per line where only the last one shows.
         */
        function queueShortageAlert(entry) {
            pendingShortages.push(entry);
            clearTimeout(shortageAlertTimer);
            shortageAlertTimer = setTimeout(() => {
                const rows = pendingShortages
                    .map(s => `<li><span class="font-semibold">${escapeHtml(s.label)}</span>: in draft ${formatStockQty(s.quantity, s.factor)}, in stock ${formatStockQty(s.available, s.factor)}</li>`)
                    .join('');
                pendingShortages = [];
                window.dispatchEvent(new CustomEvent('open-alert-modal', {
                    detail: {
                        title: 'Not Enough Stock',
                        message: `<p>Stock has gone down since this was saved. These lines are marked in red:</p><ul class="mt-2 list-disc pl-5 text-left">${rows}</ul><p class="mt-2 text-gray-700">Their quantities were kept. Reduce or remove them before saving.</p>`
                    }
                }));
            }, 400);
        }

        const oldItems = @json(old('items', []));
        const existingItems = @json($goodsIssue->items);
        const existingEmployeeId = @json(old('employee_id', $goodsIssue->employee_id));
        const existingVehicleId = @json(old('vehicle_id', $goodsIssue->vehicle_id));
        const canEnterCartons = @json($canEnterCartons);
        const goodsIssueId = @json($goodsIssue->id);

        function goodsIssueForm() {
            return {
                items: (oldItems.length > 0 ? oldItems : existingItems).map(item => makeRow({
                    product_id: item.product_id || '',
                    uom_id: item.uom_id || '',
                    quantity_issued: parseFloat(item.quantity_issued) || 0,
                    unit_cost: parseFloat(item.unit_cost) || 0,
                    selling_price: parseFloat(item.selling_price) || 0,
                    total_value: parseFloat(item.total_value) || 0,
                    exclude_promotional: item.exclude_promotional == 1 || item.exclude_promotional === true || false,
                })),

                addProductCount: 1,

                addItems() {
                    const count = parseInt(this.addProductCount) || 1;
                    for (let i = 0; i < count; i++) {
                        this.addItem();
                    }
                },

                validateAndSubmit() {
                    const shortItems = this.items.filter(item => item.product_id && item.stock_short);
                    if (shortItems.length > 0) {
                        const names = shortItems
                            .map(item => `<li>${escapeHtml(productLabel(item.product_id))}</li>`)
                            .join('');
                        window.dispatchEvent(new CustomEvent('open-alert-modal', {
                            detail: {
                                title: 'Not Enough Stock',
                                message: `<p>These lines need more than is in stock:</p><ul class="mt-2 list-disc pl-5 text-left">${names}</ul><p class="mt-2">Reduce or remove them, then save again.</p>`
                            }
                        }));
                        return false;
                    }

                    const validItems = this.items.filter(item => {
                        const qty = parseFloat(item.quantity_issued) || 0;
                        return qty > 0 && item.product_id;
                    });

                    if (validItems.length === 0) {
                        window.dispatchEvent(new CustomEvent('open-alert-modal', {
                            detail: {
                                title: 'Cannot Update!',
                                message: '<p>No valid items to update.</p><p class="mt-2">Please add at least one product with a valid quantity.</p>'
                            }
                        }));
                        return false;
                    }

                    this.items = validItems;

                    this.$nextTick(() => {
                        document.getElementById('goodsIssueForm').submit();
                    });
                },

                addItem() {
                    const warehouseId = document.getElementById('warehouse_id').value;
                    if (!warehouseId) {
                        window.dispatchEvent(new CustomEvent('open-alert-modal', {
                            detail: {
                                title: 'Warehouse Required',
                                message: '<p>Please select a warehouse first.</p>'
                            }
                        }));
                        return;
                    }

                    if (allProducts.length === 0) {
                        window.dispatchEvent(new CustomEvent('open-alert-modal', {
                            detail: {
                                title: 'Supplier Required',
                                message: '<p>Please select a supplier first to load products.</p>'
                            }
                        }));
                        return;
                    }

                    const row = makeRow();
                    this.items.push(row);

                    this.$nextTick(() => {
                        initializeProductSelect2(row.uid);
                    });
                },

                removeItem(index) {
                    if (this.items.length <= 1) {
                        return;
                    }

                    const item = this.items[index];
                    const $select = $(`#product_${item.uid}`);
                    if ($select.data('select2')) {
                        $select.select2('destroy');
                    }

                    // Only this row goes; every other row keeps its own element, dropdown and figures.
                    this.items.splice(index, 1);
                },

                indexOfRow(uid) {
                    return this.items.findIndex(row => row.uid === uid);
                },

                formatQty(quantity) {
                    return Number((parseFloat(quantity) || 0).toFixed(3)).toLocaleString('en-PK');
                },

                draftsExceedFree(item) {
                    const free = (parseFloat(item.available_qty) || 0) - (parseFloat(item.in_other_drafts) || 0);
                    return (parseFloat(item.quantity_issued) || 0) > free;
                },

                otherDraftsTitle(item) {
                    return (item.other_drafts || [])
                        .map(d => `${d.issue_number}${d.vehicle ? ' (' + d.vehicle + ')' : ''}: ${Number(d.quantity)}`)
                        .join('\n');
                },

                updatePriceBasedOnQuantity(index, restoring = false) {
                    const item = this.items[index];
                    if (!item) {
                        return;
                    }

                    const productId = item.product_id;
                    const quantity = parseFloat(item.quantity_issued) || 0;
                    const availableQty = parseFloat(item.available_qty) || 0;
                    const excludePromo = item.exclude_promotional;
                    const batchKey = excludePromo ? `${productId}_np` : productId;
                    const batchInfoDiv = rowElement(item, 'batch_info');
                    const priceBreakdownDiv = rowElement(item, 'price_breakdown');

                    if (!batchInfoDiv || !priceBreakdownDiv) {
                        return;
                    }

                    if (!productId || !productBatches[batchKey]) {
                        priceBreakdownDiv.innerHTML = '';
                        batchInfoDiv.innerHTML = '';
                        item.total_value = 0;
                        item.stock_short = false;
                        return;
                    }

                    if (quantity === 0) {
                        priceBreakdownDiv.innerHTML = '<span class="text-gray-400">Enter quantity</span>';
                        batchInfoDiv.innerHTML = '';
                        item.total_value = 0;
                        item.stock_short = false;
                        return;
                    }

                    if (quantity > availableQty) {
                        const factor = parseFloat(item.conversion_factor) || 1;
                        const label = productLabel(productId);

                        batchInfoDiv.innerHTML = `
                            <div class="text-red-600 font-bold">⚠️ Not enough stock for ${escapeHtml(label)}</div>
                        `;
                        priceBreakdownDiv.innerHTML = `
                            <div class="text-red-600 font-semibold">${restoring ? 'In draft' : 'Entered'}: ${formatStockQty(quantity, factor)}</div>
                            <div class="text-green-600 font-semibold">In stock: ${formatStockQty(availableQty, factor)}</div>
                            <div class="text-red-600 font-bold border-t border-red-300 pt-1 mt-1">Short: ${formatStockQty(quantity - availableQty, factor)}</div>
                            ${otherDraftsHtml(item)}
                        `;
                        item.total_value = 0;
                        item.unit_cost = 0;

                        if (restoring) {
                            // A saved line keeps its quantity: the user decides what to cut, not the form.
                            item.stock_short = true;
                            queueShortageAlert({ label, quantity, available: availableQty, factor });
                            return;
                        }

                        item.stock_short = false;
                        item.quantity_issued = availableQty;
                        if (canEnterCartons && factor > 1) {
                            item.carton_qty = Math.floor(availableQty / factor);
                            item.pieces_qty = Math.round(availableQty % factor);
                        }
                        this.updatePriceBasedOnQuantity(index);

                        setTimeout(() => {
                            const alertMsg = `<p class="font-semibold text-gray-900">${escapeHtml(label)}</p><p class="font-semibold text-red-600">You entered: ${formatStockQty(quantity, factor)}</p><p class="font-semibold text-green-600">Available stock: ${formatStockQty(availableQty, factor)}</p>${otherDraftsHtml(item)}<p class="mt-2 text-gray-700">Quantity has been reset to maximum available.</p>`;
                            window.dispatchEvent(new CustomEvent('open-alert-modal', {
                                detail: { title: 'Not Enough Stock', message: alertMsg }
                            }));
                        }, 100);
                        return;
                    }

                    item.stock_short = false;

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

                    if (remainingQty > 0) {
                        batchInfoDiv.innerHTML = `
                            <div class="text-red-600 font-bold">⚠️ Insufficient stock!</div>
                        `;
                        priceBreakdownDiv.innerHTML = `
                            <div class="text-sm">Available: ${(quantity - remainingQty).toFixed(0)}</div>
                            <div class="text-sm text-red-600">Short: ${remainingQty.toFixed(0)}</div>
                        `;
                        item.total_value = 0;
                        return;
                    }

                    if (batchesUsed.length > 0) {
                        let info = '<div class="text-blue-600 font-semibold mb-1">📦 Issuing from batches:</div>';
                        batchesUsed.forEach((b, bIndex) => {
                            const promo = b.is_promotional ? ' 🎁' : '';
                            info += `<div>Batch ${bIndex + 1}: ${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)}${promo}</div>`;
                        });
                        batchInfoDiv.innerHTML = info;
                    }

                    if (batchesUsed.length === 1) {
                        const b = batchesUsed[0];
                        priceBreakdownDiv.innerHTML = `
                            <div class="text-sm font-semibold text-green-700 mt-1">
                                ${b.qty.toFixed(0)} × ₨${b.price.toFixed(2)} = ₨${b.value.toFixed(2)}
                            </div>
                        `;
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
                    if (!item || !item.product_id) return;

                    const uid = item.uid;
                    const savedQty = parseFloat(item.quantity_issued) || 0;

                    const batchInfoDiv = rowElement(item, 'batch_info');
                    const priceDiv = rowElement(item, 'price_breakdown');
                    if (batchInfoDiv) batchInfoDiv.innerHTML = '<div class="text-gray-400">Loading...</div>';
                    if (priceDiv) priceDiv.innerHTML = '';

                    const warehouseId = document.getElementById('warehouse_id').value;
                    if (warehouseId && item.product_id) {
                        await onProductChange(uid, item.product_id, warehouseId);

                        item.quantity_issued = savedQty;
                        const currentIndex = this.indexOfRow(uid);
                        if (savedQty > 0 && currentIndex !== -1) {
                            this.updatePriceBasedOnQuantity(currentIndex);
                        }
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

        async function loadEmployeesBySuppliers(supplierIds, preselectId = null) {
            if (!supplierIds || supplierIds.length === 0) {
                resetEmployeeDropdown();
                return;
            }

            const params = new URLSearchParams();
            supplierIds.forEach(id => params.append('supplier_ids[]', id));

            try {
                const response = await fetch(`/api/employees/by-suppliers?${params.toString()}`);
                const employees = await response.json();

                const $employee = $('#employee_id');
                if ($employee.data('select2')) {
                    $employee.select2('destroy');
                }

                $employee.empty().append('<option value="">Select Salesman</option>');
                employees.forEach(emp => {
                    const label = emp.supplier_id ? '' : ' [Unassigned]';
                    const selected = preselectId && String(emp.id) === String(preselectId) ? ' selected' : '';
                    $employee.append(`<option value="${emp.id}"${selected}>${emp.name} (${emp.employee_code})${label}</option>`);
                });

                $employee.prop('disabled', false);
                $employee.select2({ placeholder: 'Select Salesman', allowClear: false, width: '100%' });

                if (preselectId) {
                    $employee.val(preselectId).trigger('change.select2');
                }
            } catch (error) {
                console.error('Error loading employees:', error);
            }
        }

        async function loadVehiclesBySuppliers(supplierIds, preselectId = null) {
            if (!supplierIds || supplierIds.length === 0) {
                resetVehicleDropdown();
                return;
            }

            const params = new URLSearchParams();
            supplierIds.forEach(id => params.append('supplier_ids[]', id));

            try {
                const response = await fetch(`{{ url('api/vehicles/by-suppliers') }}?${params.toString()}`);
                const vehicles = await response.json();

                const $vehicle = $('#vehicle_id');
                if ($vehicle.data('select2')) {
                    $vehicle.select2('destroy');
                }

                $vehicle.empty().append('<option value="">Select Vehicle</option>');

                vehicles.forEach(v => {
                    const selected = preselectId && String(v.id) === String(preselectId) ? ' selected' : '';
                    $vehicle.append(`<option value="${v.id}"${selected}>${v.vehicle_number} (${v.vehicle_type || 'N/A'})</option>`);
                });

                $vehicle.prop('disabled', false);
                $vehicle.select2({ placeholder: 'Select Vehicle', allowClear: false, width: '100%' });

                if (preselectId) {
                    $vehicle.val(preselectId).trigger('change.select2');
                }
            } catch (error) {
                console.error('Error loading vehicles:', error);
            }
        }

        async function loadProductsBySuppliers(supplierIds) {
            if (!supplierIds || supplierIds.length === 0) {
                allProducts = [];
                return;
            }

            const params = new URLSearchParams();
            supplierIds.forEach(id => params.append('supplier_ids[]', id));

            try {
                const response = await fetch(`/api/products/by-suppliers?${params.toString()}`);
                allProducts = await response.json();
            } catch (error) {
                console.error('Error loading products:', error);
            }
        }

        function refreshAllProductSelects() {
            const alpineComponent = formComponent();
            if (!alpineComponent) return;

            const validProductIds = new Set(allProducts.map(p => String(p.id)));

            $('.product-select').each(function () {
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }
            });

            alpineComponent.items.forEach(item => {
                if (item.product_id && !validProductIds.has(String(item.product_id))) {
                    delete productBatches[item.product_id];
                    item.product_id = '';
                    item.available_qty = 0;
                    item.quantity_issued = 0;
                    item.unit_cost = 0;
                    item.selling_price = 0;
                    item.total_value = 0;
                    item.stock_short = false;
                    item.other_drafts = [];
                    item.in_other_drafts = 0;
                }
            });

            alpineComponent.items.forEach(item => initializeProductSelect2(item.uid));
        }

        function resetEmployeeDropdown() {
            const $employee = $('#employee_id');
            if ($employee.data('select2')) {
                $employee.select2('destroy');
            }
            $employee.empty().append('<option value="">Select Supplier First</option>');
            $employee.prop('disabled', true);
            resetVehicleDropdown();
        }

        function resetVehicleDropdown() {
            const $vehicle = $('#vehicle_id');
            if ($vehicle.data('select2')) {
                $vehicle.select2('destroy');
            }
            $vehicle.empty().append('<option value="">Select Salesman First</option>');
            $vehicle.prop('disabled', true);
        }

        async function initializeProductSelect2(uid) {
            const $select = $(`#product_${uid}`);
            if (!$select.length) return;

            const alpineComponent = formComponent();

            // select2 appends its data as <option>s, so clear the previous ones before re-initialising.
            $select.find('option').not('[value=""]').remove();
            $select.select2({
                placeholder: 'Select Product',
                allowClear: false,
                width: '100%',
                data: allProducts.map(p => ({
                    id: p.id,
                    text: `${p.product_code} - ${p.product_name}`
                }))
            });

            // Bound once per element and tied to the row's uid, so a handler can never write
            // into a row that has since moved to this position.
            $select.off('change.goodsIssueRow').on('change.goodsIssueRow', async function () {
                const item = findRow(uid);
                if (!item) return;

                const productId = $(this).val();
                const warehouseId = $('#warehouse_id').val();
                item.product_id = productId;
                item.stock_short = false;

                if (productId && warehouseId) {
                    await onProductChange(uid, productId, warehouseId);
                }
            });

            const item = alpineComponent.items.find(row => row.uid === uid);
            if (item && item.product_id) {
                const savedQuantity = parseFloat(item.quantity_issued) || 0;

                $select.val(item.product_id).trigger('change.select2');

                const warehouseId = $('#warehouse_id').val();
                if (warehouseId) {
                    await onProductChange(uid, item.product_id, warehouseId);

                    if (savedQuantity > 0) {
                        item.quantity_issued = savedQuantity;
                        setTimeout(() => {
                            const index = alpineComponent.indexOfRow(uid);
                            if (index !== -1) {
                                alpineComponent.updatePriceBasedOnQuantity(index, true);
                            }
                        }, 100);
                    }
                }
            }
        }

        async function onProductChange(uid, productId, warehouseId) {
            if (!productId || !warehouseId) {
                return;
            }

            const alpineComponent = formComponent();
            const item = alpineComponent.items.find(row => row.uid === uid);
            if (!item) return;

            const isDuplicate = alpineComponent.items.some(row => row.uid !== uid && String(row.product_id) === String(productId));

            if (isDuplicate) {
                window.dispatchEvent(new CustomEvent('open-alert-modal', {
                    detail: {
                        title: 'Duplicate Product!',
                        message: `<p><span class="font-semibold">${escapeHtml(productLabel(productId))}</span> is already added to the list.</p><p class="mt-2">Please adjust the quantity in the existing row instead of adding it again.</p>`
                    }
                }));
                $(`#product_${uid}`).val('').trigger('change.select2');
                item.product_id = '';
                return;
            }

            try {
                const excludePromo = item.exclude_promotional ? 1 : 0;
                const response = await fetch(`/api/warehouses/${warehouseId}/products/${productId}/stock?exclude_promotional=${excludePromo}&goods_issue_id=${goodsIssueId}`);
                const data = await response.json();

                // The row may have been removed, or given another product, while this was loading.
                if (!alpineComponent.items.some(row => row.uid === uid) || String(item.product_id) !== String(productId)) {
                    return;
                }

                const batchKey = excludePromo ? `${productId}_np` : productId;
                productBatches[batchKey] = data.batches || [];

                item.available_qty = parseFloat(data.available_quantity || 0).toFixed(2);
                item.uom_id = data.stock_uom_id || '';
                item.other_drafts = data.other_drafts || [];
                item.in_other_drafts = parseFloat(data.in_other_drafts || 0);

                if (canEnterCartons) {
                    const factor = parseFloat(data.conversion_factor) || 1;
                    item.conversion_factor = factor;
                    const totalQty = parseFloat(item.quantity_issued) || 0;
                    if (totalQty > 0 && factor > 1) {
                        item.carton_qty = Math.floor(totalQty / factor);
                        item.pieces_qty = Math.round(totalQty % factor);
                    } else {
                        item.carton_qty = 0;
                        item.pieces_qty = 0;
                    }
                }

                if (data.batches && data.batches.length > 0) {
                    item.selling_price = parseFloat(data.batches[0].selling_price || 0);
                } else {
                    item.selling_price = 0;
                }

                displayBatchInfo(uid, data.batches, data.has_multiple_prices, !!excludePromo);

                if (item.quantity_issued === 0 || item.quantity_issued === null || item.quantity_issued === undefined) {
                    item.total_value = 0;
                    item.unit_cost = 0;
                    const priceDiv = rowElement(item, 'price_breakdown');
                    if (priceDiv) priceDiv.innerHTML = '';
                }

            } catch (error) {
                console.error('Error fetching product stock:', error);
                window.dispatchEvent(new CustomEvent('open-alert-modal', {
                    detail: {
                        title: 'Error',
                        message: '<p>Error loading product stock data.</p>'
                    }
                }));
            }
        }

        function displayBatchInfo(uid, batches, hasMultiplePrices, excludePromo = false) {
            const batchInfoDiv = document.getElementById(`batch_info_${uid}`);
            if (!batchInfoDiv) return;

            if (!batches || batches.length === 0) {
                batchInfoDiv.innerHTML = excludePromo
                    ? '<div class="text-amber-600 font-semibold text-xs">🚫 No non-promotional stock available</div>'
                    : '';
                return;
            }

            let filterBadge = excludePromo ? '<div class="text-indigo-600 font-semibold text-xs mb-1">🔒 Non-Promo Only</div>' : '';

            if (hasMultiplePrices) {
                let batchHtml = filterBadge + '<div class="text-orange-600 font-semibold mt-1">⚠️ Multiple batch prices:</div>';
                batches.forEach((batch, idx) => {
                    const promo = batch.is_promotional ? ' 🎁' : '';
                    batchHtml += `<div class="ml-2">Batch ${idx + 1}: ${batch.quantity.toFixed(0)} @ ₨${batch.selling_price.toFixed(2)}${promo}</div>`;
                });
                batchInfoDiv.innerHTML = batchHtml;
            } else {
                batchInfoDiv.innerHTML = filterBadge + `<div class="text-green-600">✓ Single price: ₨${batches[0].selling_price.toFixed(2)}</div>`;
            }
        }

        function initializeGoodsIssueForm() {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                setTimeout(initializeGoodsIssueForm, 100);
                return;
            }

            $(document).ready(async function() {
                // Initialize warehouse Select2
                $('#warehouse_id').select2({
                    placeholder: 'Select Warehouse',
                    allowClear: false,
                    width: '100%'
                });

                // Initialize supplier select
                $('#supplier_ids').select2({
                    placeholder: 'Select Supplier',
                    allowClear: true,
                    width: '100%'
                });

                // Supplier change → load employees, vehicles + products (for user-initiated changes)
                $('#supplier_ids').on('change', function () {
                    const val = $(this).val();
                    const supplierIds = val ? (Array.isArray(val) ? val : [val]) : [];

                    if (!$(this).data('initializing')) {
                        loadEmployeesBySuppliers(supplierIds);
                        loadVehiclesBySuppliers(supplierIds);
                        loadProductsBySuppliers(supplierIds).then(() => {
                            refreshAllProductSelects();
                        });
                    }
                });

                // Initial load for edit: populate cascade with existing values
                const initialVal = $('#supplier_ids').val();
                const initialSupplierIds = initialVal ? (Array.isArray(initialVal) ? initialVal : [initialVal]) : [];
                if (initialSupplierIds.length > 0) {
                    $('#supplier_ids').data('initializing', true);
                    $('#employee_id').data('initializing', true);

                    await loadProductsBySuppliers(initialSupplierIds);
                    await loadEmployeesBySuppliers(initialSupplierIds, existingEmployeeId);
                    await loadVehiclesBySuppliers(initialSupplierIds, existingVehicleId);

                    $('#supplier_ids').data('initializing', false);
                    $('#employee_id').data('initializing', false);

                    // Initialize product selects with existing items
                    formComponent().items.forEach(item => initializeProductSelect2(item.uid));
                }
            });
        }

        initializeGoodsIssueForm();
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