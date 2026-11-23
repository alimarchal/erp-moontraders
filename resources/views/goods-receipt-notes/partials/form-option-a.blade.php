{{--
    GRN form, Option A: every column in one grid, grouped, with the product pinned on the left
    and the line total on the right, a status mark per line, filters and a totals bar.
    Field names and the Select2 product pickers are the classic ones, so grnForm() does the maths.

    $grn: the GRN being edited, or null on create. $submitLabel: the save button's text.
--}}
@php
    $grn = $grn ?? null;
    $cell = 'block h-8 w-full rounded-md border-gray-300 px-2 text-right text-sm tabular-nums shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $calc = 'block h-8 w-full rounded-md border-transparent bg-transparent px-2 text-right text-sm tabular-nums text-gray-600 shadow-none';
    $th = 'whitespace-nowrap px-2 py-2 text-right text-[11px] font-bold uppercase tracking-wide text-gray-600';
    $td = 'px-1.5 py-1';
@endphp

<div class="space-y-4 px-3 sm:px-0">
    @include('goods-receipt-notes.partials.modern-header-fields', ['grn' => $grn])

    <section aria-labelledby="grn-grid-heading" class="overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200">
        <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-2.5">
            <h3 id="grn-grid-heading" class="mr-1 text-sm font-bold text-gray-900">Lines <span class="font-semibold text-gray-500" x-text="'· ' + items.length"></span></h3>
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter lines">
                <button type="button" @click="lineFilter = 'all'" :aria-pressed="lineFilter === 'all'"
                    :class="lineFilter === 'all' ? 'bg-gray-900 text-white ring-gray-900' : 'bg-white text-gray-700 ring-gray-200'"
                    class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1">All</button>
                <button type="button" x-show="issueCounts.error > 0" @click="lineFilter = 'error'" :aria-pressed="lineFilter === 'error'"
                    :class="lineFilter === 'error' ? 'bg-red-700 text-white ring-red-700' : 'bg-red-50 text-red-800 ring-red-200'"
                    class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"><span x-text="issueCounts.error"></span> errors</button>
                <button type="button" x-show="issueCounts.warning > 0" @click="lineFilter = 'warning'" :aria-pressed="lineFilter === 'warning'"
                    :class="lineFilter === 'warning' ? 'bg-amber-600 text-white ring-amber-600' : 'bg-amber-50 text-amber-900 ring-amber-200'"
                    class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"><span x-text="issueCounts.warning"></span> warnings</button>
            </div>
            <div x-show="items.length > 6" class="w-56">
                <label for="grn-grid-search" class="sr-only">Find a line</label>
                <input id="grn-grid-search" type="search" x-model="lineSearch" placeholder="Find product or line no."
                    class="block h-8 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="ml-auto inline-flex gap-0.5 rounded-lg bg-gray-100 p-0.5" role="group" aria-label="Columns to show">
                <button type="button" @click="columnSet = 'all'" :aria-pressed="columnSet === 'all'" :class="columnSet === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'" class="rounded-md px-3 py-1 text-xs font-semibold">All columns</button>
                <button type="button" @click="columnSet = 'entry'" :aria-pressed="columnSet === 'entry'" :class="columnSet === 'entry' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'" class="rounded-md px-3 py-1 text-xs font-semibold">Qty &amp; price</button>
                <button type="button" @click="columnSet = 'tax'" :aria-pressed="columnSet === 'tax'" :class="columnSet === 'tax' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'" class="rounded-md px-3 py-1 text-xs font-semibold">Taxes</button>
            </div>
        </div>

        <div class="max-h-[calc(100vh-16rem)] min-h-[16rem] overflow-auto" @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); moveDown($event); }">
            <table class="w-full border-separate border-spacing-0 text-sm">
                <thead class="sticky top-0 z-20">
                    <tr class="bg-gray-100 text-[11px] font-bold text-gray-700">
                        <th colspan="3" class="sticky left-0 z-30 bg-gray-100 px-2 py-1 text-left"></th>
                        <th x-show="groupSpan('qty') > 0" :colspan="groupSpan('qty')" class="border-l-2 border-gray-300 px-2 py-1 text-left">Quantity</th>
                        <th x-show="groupSpan('price') > 0" :colspan="groupSpan('price')" class="border-l-2 border-gray-300 px-2 py-1 text-left">Price &amp; discount</th>
                        <th x-show="groupSpan('tax') > 0" :colspan="groupSpan('tax')" class="border-l-2 border-gray-300 px-2 py-1 text-left">Taxes &amp; charges</th>
                        <th x-show="groupSpan('cost') > 0" :colspan="groupSpan('cost')" class="border-l-2 border-gray-300 px-2 py-1 text-left">Cost &amp; selling</th>
                        <th colspan="2" class="sticky right-0 z-30 bg-gray-100"></th>
                    </tr>
                    <tr class="bg-gray-50">
                        <th class="sticky left-0 z-30 w-8 min-w-8 border-b border-gray-200 bg-gray-50 px-2 py-2"><span class="sr-only">Status</span></th>
                        <th class="sticky left-8 z-30 w-10 min-w-10 border-b border-gray-200 bg-gray-50 px-2 py-2 text-left text-[11px] font-bold uppercase text-gray-600">#</th>
                        <th class="sticky left-[4.5rem] z-30 min-w-[16rem] border-b border-gray-200 bg-gray-50 px-2 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-gray-600 shadow-[1px_0_0_#e5e7eb]">Product</th>
                        <th x-show="colVisible('carton')" class="{{ $th }} min-w-[15rem] border-b border-gray-200 text-center">Cartons · Loose · Rate/TH</th>
                        <th x-show="colVisible('uom')" class="{{ $th }} min-w-[7.5rem] border-b border-gray-200 text-left">Purchase UOM</th>
                        <th x-show="colVisible('qtyPurchase')" class="{{ $th }} min-w-[6rem] border-b border-gray-200">Qty</th>
                        <th x-show="colVisible('factor')" class="{{ $th }} min-w-[5.5rem] border-b border-gray-200">Factor</th>
                        <th x-show="colVisible('qtyStock')" class="{{ $th }} min-w-[6rem] border-b border-gray-200">Stock qty</th>
                        <th x-show="colVisible('received')" class="{{ $th }} min-w-[6rem] border-b border-gray-200">Received</th>
                        <th x-show="colVisible('price')" class="{{ $th }} min-w-[7rem] border-b border-gray-200">Price / case</th>
                        <th x-show="colVisible('extended')" class="{{ $th }} min-w-[7rem] border-b border-gray-200">Value</th>
                        <th x-show="colVisible('discount')" class="{{ $th }} min-w-[6.5rem] border-b border-gray-200">Discount</th>
                        <th x-show="colVisible('fmr')" class="{{ $th }} min-w-[6rem] border-b border-gray-200">FMR</th>
                        <th x-show="colVisible('beforeTax')" class="{{ $th }} min-w-[7rem] border-b border-gray-200">Before tax</th>
                        <th x-show="colVisible('excise')" class="{{ $th }} min-w-[6rem] border-b border-gray-200">Excise</th>
                        <th x-show="colVisible('salesTax')" class="{{ $th }} min-w-[6.5rem] border-b border-gray-200">Sales tax</th>
                        <th x-show="colVisible('advanceTax')" class="{{ $th }} min-w-[6.5rem] border-b border-gray-200">Adv. inc. tax</th>
                        <th x-show="colVisible('other')" class="{{ $th }} min-w-[6rem] border-b border-gray-200">Other</th>
                        <th x-show="colVisible('withholding')" class="{{ $th }} min-w-[6rem] border-b border-gray-200">WHT</th>
                        <th x-show="colVisible('unitCost')" class="{{ $th }} min-w-[6.5rem] border-b border-gray-200">Unit cost</th>
                        <th x-show="colVisible('selling')" class="{{ $th }} min-w-[6.5rem] border-b border-gray-200">Selling</th>
                        <th class="{{ $th }} sticky right-20 z-30 min-w-[8rem] border-b border-gray-200 bg-gray-50 shadow-[-1px_0_0_#e5e7eb]">Line total</th>
                        <th class="sticky right-0 z-30 w-20 min-w-20 border-b border-gray-200 bg-gray-50"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, index) in items" :key="index">
                        <tr :id="'grn-line-row-' + index" :data-row="index" x-show="lineMatches(item, index)" :title="lineIssue(item).text"
                            :class="{ 'bg-red-50': lineIssue(item).level === 'error', 'bg-amber-50': lineIssue(item).level === 'warning', 'bg-white': ['ok', 'new'].includes(lineIssue(item).level) }"
                            class="[&>td]:border-b [&>td]:border-gray-100">
                            <td class="sticky left-0 z-10 bg-inherit px-2 py-1 text-center">
                                <span class="inline-block h-2.5 w-2.5 rounded-full" role="img" :aria-label="lineIssue(item).level === 'ok' ? 'OK' : lineIssue(item).text"
                                    :class="{ 'bg-red-600': lineIssue(item).level === 'error', 'bg-amber-500': lineIssue(item).level === 'warning', 'bg-green-500': lineIssue(item).level === 'ok', 'bg-blue-300': lineIssue(item).level === 'new' }"></span>
                            </td>
                            <td class="sticky left-8 z-10 bg-inherit px-2 py-1 text-xs font-semibold tabular-nums text-gray-500" x-text="index + 1"></td>
                            <td class="sticky left-[4.5rem] z-10 bg-inherit px-1.5 py-1 shadow-[1px_0_0_#e5e7eb]" data-col="product"><div class="relative">
                                <select :id="`product_${index}`" :name="`items[${index}][product_id]`" required :aria-label="'Product, line ' + (index + 1)"
                                    class="product-select select2 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">Select Product</option>
                                </select>
                                <p class="mt-0.5 truncate text-[11px]" x-show="lineIssue(item).level === 'error' || lineIssue(item).level === 'warning'"
                                    :class="lineIssue(item).level === 'error' ? 'text-red-700' : 'text-amber-800'" x-text="lineIssue(item).text"></p>
                            </div></td>
                            <td class="{{ $td }}" x-show="colVisible('carton')" data-col="carton">
                                <div class="flex gap-1">
                                    <input type="number" :name="`items[${index}][entered_cartons]`" x-model="item.entered_cartons" @input="calculateFromCarton(index)" step="0.001" min="0" placeholder="Ctn" :aria-label="'Cartons, line ' + (index + 1)" class="{{ $cell }}">
                                    <input type="number" :name="`items[${index}][entered_loose_pieces]`" x-model="item.entered_loose_pieces" @input="calculateFromCarton(index)" step="1" min="0" placeholder="Loose" :aria-label="'Loose pieces, line ' + (index + 1)" class="{{ $cell }}">
                                    <input type="number" :name="`items[${index}][rate_per_th]`" x-model="item.rate_per_th" @input="calculateFromCarton(index)" step="0.0001" min="0" placeholder="Rate/TH" :aria-label="'Rate per TH, line ' + (index + 1)" class="{{ $cell }}">
                                </div>
                                <p class="mt-0.5 text-center text-[11px] text-gray-500" x-text="cartonSummary(item)"></p>
                            </td>
                            <td class="{{ $td }}" x-show="colVisible('uom')" data-col="uom">
                                <select :name="`items[${index}][purchase_uom_id]`" x-model="item.purchase_uom_id" @change="updateConversionFactor(index)" :aria-label="'Purchase UOM, line ' + (index + 1)"
                                    class="block h-8 w-full rounded-md border-gray-300 py-0 text-sm shadow-sm">
                                    <option value="">UOM</option>
                                    @foreach ($uoms as $uom)
                                        <option value="{{ $uom->id }}">{{ $uom->uom_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="{{ $td }}" x-show="colVisible('qtyPurchase')" data-col="qtyPurchase"><input type="number" :name="`items[${index}][qty_in_purchase_uom]`" x-model="item.qty_in_purchase_uom" @input="calculateFromPurchaseQty(index)" :readonly="isCartonMode" :class="isCartonMode ? '{{ $calc }}' : '{{ $cell }}'" step="0.0001" min="0" :aria-label="'Qty in purchase UOM, line ' + (index + 1)"></td>
                            <td class="{{ $td }}" x-show="colVisible('factor')" data-col="factor"><input type="number" :name="`items[${index}][uom_conversion_factor]`" x-model="item.uom_conversion_factor" @input="calculateFromPurchaseQty(index)" :readonly="isCartonMode" :class="isCartonMode ? '{{ $calc }}' : '{{ $cell }}'" step="0.0001" min="0" :aria-label="'Conversion factor, line ' + (index + 1)"></td>
                            <td class="{{ $td }}" x-show="colVisible('qtyStock')" data-col="qtyStock">
                                <input type="number" :name="`items[${index}][qty_in_stock_uom]`" x-model="item.qty_in_stock_uom" readonly tabindex="-1" class="{{ $calc }} font-semibold" :aria-label="'Qty in stock UOM, line ' + (index + 1)">
                                <input type="hidden" :name="`items[${index}][stock_uom_id]`" x-model="item.stock_uom_id">
                            </td>
                            <td class="{{ $td }}" x-show="colVisible('received')" data-col="received">
                                <input type="number" :name="`items[${index}][quantity_received]`" x-model="item.quantity_received" readonly tabindex="-1" class="{{ $calc }}" :aria-label="'Qty received, line ' + (index + 1)">
                                <input type="hidden" :name="`items[${index}][quantity_accepted]`" x-model="item.quantity_received">
                            </td>
                            <td class="{{ $td }}" x-show="colVisible('price')" data-col="price"><input type="number" :name="`items[${index}][unit_price_per_case]`" x-model="item.unit_price_per_case" @input="calculateFromPurchaseQty(index)" :readonly="isCartonMode" :class="isCartonMode ? '{{ $calc }}' : '{{ $cell }}'" step="0.0001" min="0" :aria-label="'Price per case, line ' + (index + 1)"></td>
                            <td class="{{ $td }} text-right tabular-nums text-gray-600" x-show="colVisible('extended')" x-text="formatNumber(item.extended_value)"></td>
                            <td class="{{ $td }}" x-show="colVisible('discount')" data-col="discount"><input type="number" :name="`items[${index}][discount_value]`" x-model="item.discount_value" @input="calculateTaxes(index)" step="0.0001" min="0" class="{{ $cell }}" :aria-label="'Discount, line ' + (index + 1)"></td>
                            <td class="{{ $td }}" x-show="colVisible('fmr')" data-col="fmr"><input type="number" :name="`items[${index}][fmr_allowance]`" x-model="item.fmr_allowance" @input="calculateTaxes(index)" step="0.0001" min="0" class="{{ $cell }}" :aria-label="'FMR allowance, line ' + (index + 1)"></td>
                            <td class="{{ $td }} text-right tabular-nums text-gray-600" x-show="colVisible('beforeTax')" x-text="formatNumber(item.discounted_value_before_tax)"></td>
                            <td class="{{ $td }}" x-show="colVisible('excise')" data-col="excise"><input type="number" :name="`items[${index}][excise_duty]`" x-model="item.excise_duty" @input="calculateTaxes(index)" step="0.0001" min="0" class="{{ $cell }}" :aria-label="'Excise duty, line ' + (index + 1)"></td>
                            <td class="{{ $td }}" x-show="colVisible('salesTax')" data-col="salesTax"><input type="number" :name="`items[${index}][sales_tax_value]`" x-model="item.sales_tax_value" @input="onSalesTaxManualEdit(index)" step="0.0001" min="0" class="{{ $cell }}" :aria-label="'Sales tax, line ' + (index + 1)"></td>
                            <td class="{{ $td }}" x-show="colVisible('advanceTax')" data-col="advanceTax"><input type="number" :name="`items[${index}][advance_income_tax]`" x-model="item.advance_income_tax" @input="calculateTaxes(index)" step="0.0001" min="0" class="{{ $cell }}" :aria-label="'Advance income tax, line ' + (index + 1)"></td>
                            <td class="{{ $td }}" x-show="colVisible('other')" data-col="other"><input type="number" :name="`items[${index}][other_charges]`" x-model="item.other_charges" @input="calculateTaxes(index)" step="0.0001" min="0" class="{{ $cell }}" :aria-label="'Other charges, line ' + (index + 1)"></td>
                            <td class="{{ $td }}" x-show="colVisible('withholding')" data-col="withholding"><input type="number" :name="`items[${index}][withholding_tax]`" x-model="item.withholding_tax" @input="onWithholdingTaxManualEdit(index)" step="0.0001" min="0" class="{{ $cell }}" :aria-label="'Withholding tax, line ' + (index + 1)"></td>
                            <td class="{{ $td }} text-right tabular-nums text-gray-600" x-show="colVisible('unitCost')">
                                <span x-text="formatUnitCost(item.unit_cost)"></span>
                                <input type="hidden" :name="`items[${index}][unit_cost]`" x-model="item.unit_cost">
                            </td>
                            <td class="{{ $td }}" x-show="colVisible('selling')" data-col="selling"><input type="number" :name="`items[${index}][selling_price]`" x-model="item.selling_price" @input="validateSellingPrice(index)" step="0.0001" min="0" :aria-label="'Selling price, line ' + (index + 1)"
                                :class="lineIssue(item).level === 'warning' ? '{{ $cell }} border-amber-500' : '{{ $cell }}'"></td>
                            <td class="sticky right-20 z-10 bg-inherit px-2 py-1 text-right font-bold tabular-nums text-gray-900 shadow-[-1px_0_0_#e5e7eb]" x-text="formatNumber(item.total_value_with_taxes)"></td>
                            <td class="sticky right-0 z-10 bg-inherit px-1 py-1">
                                <div class="flex justify-end gap-0.5">
                                    <button type="button" @click="openPromoModal(index)" :aria-label="'Promo and batch details, line ' + (index + 1)" title="Promo & batch details"
                                        :class="(item.promotional_price > 0 || item.batch_number || item.expiry_date) ? 'text-indigo-600' : 'text-gray-400'"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md hover:bg-gray-200 hover:text-gray-700">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z" /><path d="M7 7h.01" /></svg>
                                    </button>
                                    <button type="button" @click="removeItem(index)" :disabled="items.length === 1" :aria-label="'Remove line ' + (index + 1)" title="Remove line"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-100 hover:text-red-700 disabled:opacity-30">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                    </button>
                                </div>
                                <input type="hidden" :name="`items[${index}][promotional_price]`" x-model="item.promotional_price">
                                <input type="hidden" :name="`items[${index}][selling_strategy]`" x-model="item.selling_strategy">
                                <input type="hidden" :name="`items[${index}][priority_order]`" x-model="item.priority_order">
                                <input type="hidden" :name="`items[${index}][must_sell_before]`" x-model="item.must_sell_before">
                                <input type="hidden" :name="`items[${index}][manufacturing_date]`" x-model="item.manufacturing_date">
                                <input type="hidden" :name="`items[${index}][expiry_date]`" x-model="item.expiry_date">
                                <input type="hidden" :name="`items[${index}][batch_number]`" x-model="item.batch_number">
                                <input type="hidden" :name="`items[${index}][lot_number]`" x-model="item.lot_number">
                                <input type="hidden" :name="`items[${index}][storage_location]`" x-model="item.storage_location">
                                <input type="hidden" :name="`items[${index}][quality_status]`" x-model="item.quality_status">
                                <input type="hidden" :name="`items[${index}][extended_value]`" x-model="item.extended_value">
                                <input type="hidden" :name="`items[${index}][discounted_value_before_tax]`" x-model="item.discounted_value_before_tax">
                                <input type="hidden" :name="`items[${index}][total_value_with_taxes]`" x-model="item.total_value_with_taxes">
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot class="sticky bottom-0 z-20">
                    <tr class="bg-gray-100 font-bold tabular-nums [&>td]:border-t [&>td]:border-gray-300">
                        <td colspan="3" class="sticky left-0 z-30 bg-gray-100 px-3 py-2 text-left">Total · <span x-text="items.length"></span> lines</td>
                        <td x-show="colVisible('carton')" class="px-2 py-2"></td>
                        <td x-show="colVisible('uom')" class="px-2 py-2"></td>
                        <td x-show="colVisible('qtyPurchase')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('qty_in_purchase_uom'))"></td>
                        <td x-show="colVisible('factor')" class="px-2 py-2"></td>
                        <td x-show="colVisible('qtyStock')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('qty_in_stock_uom'))"></td>
                        <td x-show="colVisible('received')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('quantity_received'))"></td>
                        <td x-show="colVisible('price')" class="px-2 py-2"></td>
                        <td x-show="colVisible('extended')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('extended_value'))"></td>
                        <td x-show="colVisible('discount')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('discount_value'))"></td>
                        <td x-show="colVisible('fmr')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('fmr_allowance'))"></td>
                        <td x-show="colVisible('beforeTax')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('discounted_value_before_tax'))"></td>
                        <td x-show="colVisible('excise')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('excise_duty'))"></td>
                        <td x-show="colVisible('salesTax')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('sales_tax_value'))"></td>
                        <td x-show="colVisible('advanceTax')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('advance_income_tax'))"></td>
                        <td x-show="colVisible('other')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('other_charges'))"></td>
                        <td x-show="colVisible('withholding')" class="px-2 py-2 text-right" x-text="formatNumber(sumOf('withholding_tax'))"></td>
                        <td x-show="colVisible('unitCost')" class="px-2 py-2"></td>
                        <td x-show="colVisible('selling')" class="px-2 py-2"></td>
                        <td class="sticky right-20 z-30 bg-gray-100 px-2 py-2 text-right shadow-[-1px_0_0_#d1d5db]" x-text="formatNumber(grandTotal)"></td>
                        <td class="sticky right-0 z-30 bg-gray-100"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 bg-gray-50 px-4 py-2.5">
            <button type="button" @click="addItems()"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-sm font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                Add
            </button>
            <label for="grn-add-count" class="sr-only">How many lines to add</label>
            <input id="grn-add-count" type="number" x-model.number="addLineCount" min="1" class="h-9 w-16 rounded-lg border-gray-300 text-center text-sm shadow-sm">
            <span class="text-xs text-gray-600">lines</span>
            <span class="ml-auto text-xs text-gray-500"><kbd class="rounded border border-gray-300 bg-white px-1">Enter</kbd> moves down a line in the same column</span>
        </div>
    </section>

    <section aria-labelledby="grn-notes-heading" class="rounded-2xl bg-white p-4 ring-1 ring-gray-200">
        <label id="grn-notes-heading" for="notes" class="mb-1 block text-sm font-bold text-gray-900">Notes</label>
        <textarea id="notes" name="notes" rows="2" placeholder="Anything the store team should know about this delivery"
            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $grn?->notes) }}</textarea>
    </section>

    @include('goods-receipt-notes.partials.modern-totals-bar', ['submitLabel' => $submitLabel])
</div>

@include('goods-receipt-notes.partials.modern-form-script')
