{{--
    GRN form, Option F: laid out like the supplier's paper invoice. Each line is an invoice row;
    clicking a row opens all of its fields right under it. Totals sit bottom-right as on an invoice.
    Every line keeps its inputs on the page; closed rows only hide them.

    $grn: the GRN being edited, or null on create. $submitLabel: the save button's text.
--}}
@php
    $grn = $grn ?? null;
@endphp

<div class="space-y-4 px-3 sm:px-0">
    <article class="overflow-hidden rounded-3xl bg-white shadow-[0_1px_2px_rgba(15,23,42,0.06),0_12px_32px_rgba(15,23,42,0.06)] ring-1 ring-gray-200">
        {{-- Letterhead --}}
        <header class="flex flex-wrap items-end justify-between gap-4 border-b border-dashed border-gray-300 px-5 py-5 sm:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">{{ config('app.name') }}</p>
                <h3 class="mt-1 text-2xl font-semibold tracking-tight text-gray-900">Goods receipt note</h3>
                <p class="text-sm text-gray-500">{{ $grn ? $grn->grn_number : 'Number is given when you save' }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Amount due to supplier</p>
                <p class="text-2xl font-semibold tabular-nums tracking-tight text-gray-900" x-text="formatCurrency(grandTotal)"></p>
            </div>
        </header>

        <div class="px-5 pt-5 sm:px-8">
            @include('goods-receipt-notes.partials.modern-header-fields', ['grn' => $grn])
        </div>

        {{-- Invoice rows --}}
        <div class="px-5 pt-5 sm:px-8">
            <div class="mb-2">
                @include('goods-receipt-notes.partials.line-filters', ['heading' => 'Items', 'searchId' => 'f'])
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem] border-separate border-spacing-0 text-sm">
                    <thead class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <tr class="[&>th]:border-b-2 [&>th]:border-gray-900 [&>th]:px-2 [&>th]:py-2">
                            <th class="w-10 text-left">#</th>
                            <th class="text-left">Description</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Rate</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Less</th>
                            <th class="text-right">Taxes</th>
                            <th class="text-right">Total</th>
                            <th class="w-8"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <template x-for="(item, index) in items" :key="index">
                        <tbody :id="'grn-line-row-' + index" x-show="lineMatches(item, index) || selected === index" class="scroll-mt-4">
                            <tr @click="selected === index ? (selected = -1) : selectLine(index)" class="cursor-pointer tabular-nums transition-colors [&>td]:border-b [&>td]:border-gray-100 [&>td]:px-2 [&>td]:py-2.5"
                                :class="selected === index ? 'bg-blue-50/70' : 'hover:bg-gray-50'">
                                <td class="text-gray-500" x-text="index + 1"></td>
                                <td>
                                    <span class="block font-semibold text-gray-900" x-text="productLabel(item)"></span>
                                    <span class="block text-xs" :class="{ 'text-red-700': lineIssue(item).level === 'error', 'text-amber-800': lineIssue(item).level === 'warning', 'text-blue-700': lineIssue(item).level === 'new', 'text-gray-500': lineIssue(item).level === 'ok' }"
                                        x-text="lineIssue(item).level === 'ok' ? (productCode(item) + ' · ' + lineSummary(item)) : lineIssue(item).text"></span>
                                </td>
                                <td class="text-right" x-text="formatNumber(item.qty_in_purchase_uom)"></td>
                                <td class="text-right" x-text="formatNumber(item.unit_price_per_case)"></td>
                                <td class="text-right" x-text="formatNumber(item.extended_value)"></td>
                                <td class="text-right text-red-700" x-text="formatNumber((parseFloat(item.discount_value) || 0) + (parseFloat(item.fmr_allowance) || 0))"></td>
                                <td class="text-right" x-text="formatNumber((parseFloat(item.total_value_with_taxes) || 0) - (parseFloat(item.discounted_value_before_tax) || 0))"></td>
                                <td class="text-right font-semibold text-gray-900" x-text="formatNumber(item.total_value_with_taxes)"></td>
                                <td class="text-gray-400">
                                    <svg class="h-4 w-4 transition-transform duration-200" :class="selected === index ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
                                </td>
                            </tr>
                            <tr x-show="selected === index">
                                <td colspan="9" class="border-b border-blue-100 bg-gray-50 p-0" @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); nextLine(); }">
                                    <div class="space-y-4 p-4">
                                        @include('goods-receipt-notes.partials.line-fields', ['sectionsGrid' => 'grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4'])
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 bg-white px-4 py-2.5">
                                        <button type="button" x-show="items.length > 1" @click="removeLine(index)" class="h-9 rounded-xl px-3 text-sm font-semibold text-red-700 ring-1 ring-gray-300 hover:bg-red-50">Remove item</button>
                                        <button type="button" @click="nextLine()" class="ml-auto inline-flex h-9 items-center rounded-xl bg-blue-600 px-4 text-sm font-bold text-white transition-transform duration-100 hover:bg-blue-700 active:scale-[0.97]">
                                            <span x-text="index === items.length - 1 ? 'Save & add next item' : 'Save & next item'"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                </table>
            </div>
            <button type="button" @click="addLinesAndSelect(1)" class="mt-2 inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                Add item
            </button>
        </div>

        {{-- Remarks and totals --}}
        <div class="grid gap-6 px-5 py-6 sm:px-8 md:grid-cols-[1fr_20rem]">
            <div>
                <label for="notes" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Remarks</label>
                <textarea id="notes" name="notes" rows="4" placeholder="Anything the store team should know about this delivery"
                    class="block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $grn?->notes) }}</textarea>
                <div class="mt-6 hidden grid-cols-3 gap-6 text-center text-xs text-gray-500 md:grid" aria-hidden="true">
                    <span class="border-t border-gray-400 pt-1">Received by</span>
                    <span class="border-t border-gray-400 pt-1">Checked by</span>
                    <span class="border-t border-gray-400 pt-1">Store keeper</span>
                </div>
            </div>
            <dl class="space-y-1.5 text-sm tabular-nums">
                <div class="flex justify-between"><dt class="text-gray-600">Amount</dt><dd x-text="formatNumber(sumOf('extended_value'))"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">Discount</dt><dd class="text-red-700" x-text="'− ' + formatNumber(sumOf('discount_value'))"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">FMR allowance</dt><dd class="text-red-700" x-text="'− ' + formatNumber(sumOf('fmr_allowance'))"></dd></div>
                <div class="flex justify-between border-t border-gray-200 pt-1.5 font-semibold"><dt>Before tax</dt><dd x-text="formatNumber(sumOf('discounted_value_before_tax'))"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">Sales tax</dt><dd x-text="formatNumber(sumOf('sales_tax_value'))"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">Excise duty</dt><dd x-text="formatNumber(sumOf('excise_duty'))"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">Advance income tax</dt><dd x-text="formatNumber(sumOf('advance_income_tax'))"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">Other charges</dt><dd x-text="formatNumber(sumOf('other_charges'))"></dd></div>
                <div class="flex justify-between" x-show="isUnileverPakistan"><dt class="text-gray-600">Withholding tax</dt><dd x-text="formatNumber(sumOf('withholding_tax'))"></dd></div>
                <div class="flex justify-between border-t-2 border-gray-900 pt-2 text-base font-semibold"><dt>Grand total</dt><dd x-text="formatCurrency(grandTotal)"></dd></div>
            </dl>
        </div>
    </article>

    @include('goods-receipt-notes.partials.modern-totals-bar', ['submitLabel' => $submitLabel])
</div>

@include('goods-receipt-notes.partials.modern-form-script')
