{{--
    Every field of one GRN line, for the Option B, C and D forms. Used inside an Alpine
    x-for over items, so `item` and `index` are in scope. Field names and the Select2 product
    picker (#product_{index}) are the classic form's, so grnForm() does all the maths.

    $sectionsGrid: the CSS grid the field groups sit in (it differs per layout).
--}}
@php
    $sectionsGrid = $sectionsGrid ?? 'grid grid-cols-1 gap-4 md:grid-cols-2';
    $num = 'block w-full rounded-lg border-gray-300 text-right text-sm tabular-nums shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $calc = 'block w-full rounded-lg border-gray-200 bg-gray-100 text-right text-sm tabular-nums text-gray-700 shadow-none';
    $txt = 'block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $lbl = 'mb-1 flex justify-between gap-1 text-xs font-semibold text-gray-700';
    $sec = 'min-w-0 rounded-xl bg-white p-4 ring-1 ring-gray-200';
    $secTitle = 'mb-3 flex items-baseline justify-between text-xs font-bold uppercase tracking-wide text-blue-800';
@endphp
<div class="relative">
    <label :for="`product_${index}`" class="{{ $lbl }}">Product * <span class="font-normal text-gray-500">type name or code</span></label>
    <select :id="`product_${index}`" :name="`items[${index}][product_id]`" required
        class="product-select select2 w-full rounded-lg border-gray-300 text-sm shadow-sm">
        <option value="">Select Product</option>
    </select>
    <p class="mt-1 text-xs text-gray-500" x-show="productOf(item)" x-text="productCode(item) + (productOf(item) && productOf(item).pieces_per_carton ? ' · ' + productOf(item).pieces_per_carton + ' pcs per carton' : '')"></p>
</div>

<div class="{{ $sectionsGrid }} grid-flow-row-dense">
    {{-- Quantity --}}
    <div class="{{ $sec }}">
        <h4 class="{{ $secTitle }}">Quantity <span class="normal-case tracking-normal text-gray-600" x-text="(isCartonMode ? formatNumber(item.entered_cartons || 0) + ' ctn' : formatNumber(item.qty_in_purchase_uom) + ' cases') + ' · ' + formatNumber(item.qty_in_stock_uom) + ' pcs'"></span></h4>
        <div class="mb-3 grid grid-cols-3 gap-2" x-show="isCartonMode">
            <div><label :for="`ctn_${index}`" class="{{ $lbl }}">Cartons</label><input :id="`ctn_${index}`" type="number" :name="`items[${index}][entered_cartons]`" x-model="item.entered_cartons" @input="calculateFromCarton(index)" step="0.001" min="0" placeholder="0" class="{{ $num }}"></div>
            <div><label :for="`loose_${index}`" class="{{ $lbl }}">Loose pcs</label><input :id="`loose_${index}`" type="number" :name="`items[${index}][entered_loose_pieces]`" x-model="item.entered_loose_pieces" @input="calculateFromCarton(index)" step="1" min="0" placeholder="0" class="{{ $num }}"></div>
            <div><label :for="`rth_${index}`" class="{{ $lbl }}">Rate / TH</label><input :id="`rth_${index}`" type="number" :name="`items[${index}][rate_per_th]`" x-model="item.rate_per_th" @input="calculateFromCarton(index)" step="0.0001" min="0" placeholder="0" class="{{ $num }}"></div>
            <p class="col-span-3 text-xs text-gray-600" x-text="cartonSummary(item)"></p>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div><label :for="`puom_${index}`" class="{{ $lbl }}">Purchase UOM</label>
                <select :id="`puom_${index}`" :name="`items[${index}][purchase_uom_id]`" x-model="item.purchase_uom_id" @change="updateConversionFactor(index)" class="{{ $txt }}">
                    <option value="">Select UOM</option>
                    @foreach ($uoms as $uom)
                        <option value="{{ $uom->id }}">{{ $uom->uom_name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label :for="`qp_${index}`" class="{{ $lbl }}">Qty (purchase UOM)</label><input :id="`qp_${index}`" type="number" :name="`items[${index}][qty_in_purchase_uom]`" x-model="item.qty_in_purchase_uom" @input="calculateFromPurchaseQty(index)" :readonly="isCartonMode" :class="isCartonMode ? '{{ $calc }}' : '{{ $num }}'" step="0.0001" min="0" placeholder="0"></div>
            <div><label :for="`fac_${index}`" class="{{ $lbl }}">Conversion factor</label><input :id="`fac_${index}`" type="number" :name="`items[${index}][uom_conversion_factor]`" x-model="item.uom_conversion_factor" @input="calculateFromPurchaseQty(index)" :readonly="isCartonMode" :class="isCartonMode ? '{{ $calc }}' : '{{ $num }}'" step="0.0001" min="0"></div>
            <div><label :for="`qs_${index}`" class="{{ $lbl }}">Qty (stock UOM)</label><input :id="`qs_${index}`" type="number" :name="`items[${index}][qty_in_stock_uom]`" x-model="item.qty_in_stock_uom" readonly class="{{ $calc }}"></div>
            <div class="col-span-2"><label :for="`rec_${index}`" class="{{ $lbl }}">Qty received</label><input :id="`rec_${index}`" type="number" :name="`items[${index}][quantity_received]`" x-model="item.quantity_received" readonly class="{{ $calc }}"></div>
        </div>
        <input type="hidden" :name="`items[${index}][stock_uom_id]`" x-model="item.stock_uom_id">
        <input type="hidden" :name="`items[${index}][quantity_accepted]`" x-model="item.quantity_received">
    </div>

    {{-- Price, deductions and taxes: in the supplier's column order (Settings → Suppliers → Edit). Layout only. --}}
    <div class="{{ $sec }} col-span-full">
        <h4 class="{{ $secTitle }}">Price, deductions &amp; taxes
            <span class="normal-case tracking-normal text-gray-600" x-text="'before tax ' + formatNumber(item.discounted_value_before_tax) + ' · taxes ' + formatNumber((parseFloat(item.excise_duty) || 0) + (parseFloat(item.sales_tax_value) || 0) + (parseFloat(item.advance_income_tax) || 0) + (parseFloat(item.other_charges) || 0) + (parseFloat(item.withholding_tax) || 0))"></span></h4>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
            <div x-show="colShown(item, 'unit_price_per_case')" :style="{ order: colOrder('unit_price_per_case') }"><label :for="`up_${index}`" class="{{ $lbl }}">Price / case *</label><input :id="`up_${index}`" type="number" :name="`items[${index}][unit_price_per_case]`" x-model="item.unit_price_per_case" @input="calculateFromPurchaseQty(index)" :readonly="isCartonMode" :class="isCartonMode ? '{{ $calc }}' : '{{ $num }}'" step="0.0001" min="0" placeholder="0.00"></div>
            <div :style="{ order: colOrder('unit_price_per_case') + 1 }"><label class="{{ $lbl }}">Extended value</label><input type="text" :value="formatNumber(item.extended_value)" readonly tabindex="-1" aria-label="Extended value" class="{{ $calc }}"></div>
            <div x-show="colShown(item, 'discount_value')" :style="{ order: colOrder('discount_value') }"><label :for="`dv_${index}`" class="{{ $lbl }}">Discount</label><input :id="`dv_${index}`" type="number" :name="`items[${index}][discount_value]`" x-model="item.discount_value" @input="calculateTaxes(index)" step="0.0001" min="0" placeholder="0.00" class="{{ $num }}"></div>
            <div x-show="colShown(item, 'fmr_allowance')" :style="{ order: colOrder('fmr_allowance') }"><label :for="`fmr_${index}`" class="{{ $lbl }}">FMR allowance</label><input :id="`fmr_${index}`" type="number" :name="`items[${index}][fmr_allowance]`" x-model="item.fmr_allowance" @input="calculateTaxes(index)" step="0.0001" min="0" placeholder="0.00" class="{{ $num }}"></div>
            <div :style="{ order: beforeTaxOrder() }"><label class="{{ $lbl }}">Value before sales tax</label><input type="text" :value="formatNumber(item.discounted_value_before_tax)" readonly tabindex="-1" aria-label="Value before sales tax" class="{{ $calc }}"></div>
            <div x-show="colShown(item, 'excise_duty')" :style="{ order: colOrder('excise_duty') }"><label :for="`ex_${index}`" class="{{ $lbl }}">Excise duty</label><input :id="`ex_${index}`" type="number" :name="`items[${index}][excise_duty]`" x-model="item.excise_duty" @input="calculateTaxes(index)" step="0.0001" min="0" placeholder="0.00" class="{{ $num }}"></div>
            <div x-show="colShown(item, 'sales_tax_value')" :style="{ order: colOrder('sales_tax_value') }"><label :for="`st_${index}`" class="{{ $lbl }}">Sales tax <span class="font-normal text-gray-500" x-text="isCartonMode ? 'enter' : 'auto · ' + supplierSalesTaxRate + '%'"></span></label><input :id="`st_${index}`" type="number" :name="`items[${index}][sales_tax_value]`" x-model="item.sales_tax_value" @input="onSalesTaxManualEdit(index)" step="0.0001" min="0" placeholder="0.00" class="{{ $num }}"></div>
            <div x-show="colShown(item, 'advance_income_tax')" :style="{ order: colOrder('advance_income_tax') }"><label :for="`ait_${index}`" class="{{ $lbl }}">Advance income tax</label><input :id="`ait_${index}`" type="number" :name="`items[${index}][advance_income_tax]`" x-model="item.advance_income_tax" @input="calculateTaxes(index)" step="0.0001" min="0" placeholder="0.00" class="{{ $num }}"></div>
            <div x-show="colShown(item, 'other_charges')" :style="{ order: colOrder('other_charges') }"><label :for="`oc_${index}`" class="{{ $lbl }}">Other charges</label><input :id="`oc_${index}`" type="number" :name="`items[${index}][other_charges]`" x-model="item.other_charges" @input="calculateTaxes(index)" step="0.0001" min="0" placeholder="0.00" class="{{ $num }}"></div>
            <div x-show="colShown(item, 'withholding_tax')" :style="{ order: colOrder('withholding_tax') }"><label :for="`wht_${index}`" class="{{ $lbl }}">Withholding tax <span class="font-normal text-gray-500" x-text="'auto · ' + withholdingTaxRate + '%'"></span></label><input :id="`wht_${index}`" type="number" :name="`items[${index}][withholding_tax]`" x-model="item.withholding_tax" @input="onWithholdingTaxManualEdit(index)" step="0.0001" min="0" placeholder="0.00" class="{{ $num }}"></div>
        </div>
    </div>

    {{-- Cost and selling --}}
    <div class="{{ $sec }}">
        <h4 class="{{ $secTitle }}">Cost &amp; selling</h4>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="{{ $lbl }}">Unit cost</label><input type="text" :value="formatUnitCost(item.unit_cost)" readonly tabindex="-1" aria-label="Unit cost" class="{{ $calc }}"></div>
            <div><label :for="`sp_${index}`" class="{{ $lbl }}">Selling price</label><input :id="`sp_${index}`" type="number" :name="`items[${index}][selling_price]`" x-model="item.selling_price" @input="validateSellingPrice(index)" step="0.0001" min="0" placeholder="0.00"
                :class="lineIssue(item).level === 'warning' ? '{{ $num }} border-amber-500 ring-2 ring-amber-100' : '{{ $num }}'"></div>
            <div class="col-span-2"><label :for="`pp_${index}`" class="{{ $lbl }}">Promotional price <span class="font-normal text-gray-500">optional</span></label><input :id="`pp_${index}`" type="number" :name="`items[${index}][promotional_price]`" x-model="item.promotional_price" @change="if (parseFloat(item.promotional_price) > 0) { item.selling_price = parseFloat(item.promotional_price); }" step="0.0001" min="0" placeholder="Leave empty if not on promotion" class="{{ $num }}"></div>
        </div>
        <input type="hidden" :name="`items[${index}][unit_cost]`" x-model="item.unit_cost">
    </div>

    {{-- Batch and expiry: a summary here, the fields in a sheet (they stay inside the form) --}}
    <div class="{{ $sec }} col-span-full flex flex-wrap items-center gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700" aria-hidden="true">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z" /><path d="M7 7h.01" /></svg>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold tracking-tight text-gray-900">Batch &amp; expiry</span>
            <span class="block truncate text-xs text-gray-500" x-text="batchSummary(item)"></span>
        </span>
        <button type="button" @click="openBatch(index)" aria-haspopup="dialog"
            class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-white px-4 text-sm font-semibold text-indigo-700 ring-1 ring-indigo-200 transition-transform duration-100 hover:bg-indigo-50 active:scale-[0.97]">
            <span x-text="hasBatchDetails(item) ? 'Edit details' : 'Add details'"></span>
        </button>
    </div>

    <div x-show="batchFor === index" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6"
        role="dialog" aria-modal="true" :aria-labelledby="'batch-title-' + index"
        @keydown.escape.window="if (batchFor === index) { batchFor = null }"
        @keydown.enter.stop="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); batchFor = null; }">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="batchFor = null"
            x-show="batchFor === index" x-transition:enter="transition-opacity duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0" x-transition:leave="transition-opacity duration-150 motion-reduce:transition-none" x-transition:leave-end="opacity-0"></div>
        <div class="relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl ring-1 ring-black/5 sm:max-w-2xl sm:rounded-3xl"
            x-show="batchFor === index"
            x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-opacity motion-reduce:duration-150"
            x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-2 sm:scale-[0.97] motion-reduce:translate-y-0 motion-reduce:scale-100"
            x-transition:leave="transition duration-200 ease-in motion-reduce:transition-opacity"
            x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-2 sm:scale-[0.97] motion-reduce:translate-y-0 motion-reduce:scale-100">
            <div class="mx-auto mt-2 h-1.5 w-10 rounded-full bg-gray-300 sm:hidden" aria-hidden="true"></div>
            <div class="flex items-start gap-3 border-b border-gray-100 px-5 pb-3 pt-3 sm:pt-5">
                <div class="min-w-0 flex-1">
                    <h3 :id="'batch-title-' + index" class="text-lg font-semibold tracking-tight text-gray-900">Batch &amp; expiry</h3>
                    <p class="truncate text-sm text-gray-500" x-text="'Line ' + (index + 1) + ' · ' + productLabel(item)"></p>
                </div>
                <button type="button" @click="batchFor = null" aria-label="Close" class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition-transform duration-100 hover:bg-gray-200 active:scale-95">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="overflow-y-auto px-5 py-4">
                <p class="mb-3 text-xs text-gray-500">All optional. Batch and lot numbers are made for you if left empty.</p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
            <div><label :for="`bn_${index}`" class="{{ $lbl }}">Batch no.</label><input :id="`bn_${index}`" type="text" :name="`items[${index}][batch_number]`" x-model="item.batch_number" placeholder="Auto" class="{{ $txt }}"></div>
            <div><label :for="`ln_${index}`" class="{{ $lbl }}">Lot no.</label><input :id="`ln_${index}`" type="text" :name="`items[${index}][lot_number]`" x-model="item.lot_number" placeholder="Auto" class="{{ $txt }}"></div>
            <div><label :for="`loc_${index}`" class="{{ $lbl }}">Storage location</label><input :id="`loc_${index}`" type="text" :name="`items[${index}][storage_location]`" x-model="item.storage_location" placeholder="e.g. Rack A-1" class="{{ $txt }}"></div>
            <div><label :for="`mfg_${index}`" class="{{ $lbl }}">Manufacturing date</label><input :id="`mfg_${index}`" type="date" :name="`items[${index}][manufacturing_date]`" x-model="item.manufacturing_date" class="{{ $txt }}"></div>
            <div><label :for="`exp_${index}`" class="{{ $lbl }}">Expiry date</label><input :id="`exp_${index}`" type="date" :name="`items[${index}][expiry_date]`" x-model="item.expiry_date" class="{{ $txt }}"></div>
            <div><label :for="`msb_${index}`" class="{{ $lbl }}">Must sell before</label><input :id="`msb_${index}`" type="date" :name="`items[${index}][must_sell_before]`" x-model="item.must_sell_before" class="{{ $txt }}"></div>
            <div><label :for="`ss_${index}`" class="{{ $lbl }}">Selling strategy</label>
                <select :id="`ss_${index}`" :name="`items[${index}][selling_strategy]`" x-model="item.selling_strategy" class="{{ $txt }}">
                    <option value="fifo">FIFO (first in, first out)</option>
                    <option value="lifo">LIFO (last in, first out)</option>
                    <option value="priority">Priority (number below)</option>
                    <option value="expiry_first">Expiry date first</option>
                </select>
            </div>
            <div><label :for="`po_${index}`" class="{{ $lbl }}">Priority</label>
                <select :id="`po_${index}`" :name="`items[${index}][priority_order]`" x-model="item.priority_order" class="{{ $txt }}">
                    <option value="">None (normal FIFO)</option>
                    @for ($priority = 1; $priority <= 10; $priority++)
                        <option value="{{ $priority }}">{{ $priority }}{{ $priority === 1 ? ' - highest (sell first)' : ($priority === 10 ? ' - lowest' : '') }}</option>
                    @endfor
                </select>
            </div>
            <div><label :for="`qst_${index}`" class="{{ $lbl }}">Quality status</label>
                <select :id="`qst_${index}`" :name="`items[${index}][quality_status]`" x-model="item.quality_status" class="{{ $txt }}">
                    <option value="approved">Approved (ready to sell)</option>
                    <option value="pending">Pending QC</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>
            </div>
            <div class="flex items-center gap-2 border-t border-gray-100 bg-gray-50/80 px-5 py-3">
                <button type="button" @click="clearBatch(item)" class="h-10 rounded-xl px-4 text-sm font-semibold text-red-700 hover:bg-red-50">Clear</button>
                <button type="button" @click="batchFor = null" class="ml-auto inline-flex h-10 items-center rounded-xl bg-blue-600 px-6 text-sm font-bold text-white transition-transform duration-100 hover:bg-blue-700 active:scale-[0.97]">Done</button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" :name="`items[${index}][extended_value]`" x-model="item.extended_value">
<input type="hidden" :name="`items[${index}][discounted_value_before_tax]`" x-model="item.discounted_value_before_tax">
<input type="hidden" :name="`items[${index}][total_value_with_taxes]`" x-model="item.total_value_with_taxes">
