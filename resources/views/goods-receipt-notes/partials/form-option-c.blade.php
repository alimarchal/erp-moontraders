{{--
    GRN form, Option C: an entry bar on top with every field of the line being entered, and the
    lines already added in a compact table below (Edit loads a line back into the bar).
    Every line keeps its inputs on the page; only the line in the bar is shown.

    $grn: the GRN being edited, or null on create. $submitLabel: the save button's text.
--}}
@php
    $grn = $grn ?? null;
@endphp

<div class="space-y-4 px-3 sm:px-0">
    @include('goods-receipt-notes.partials.modern-header-fields', ['grn' => $grn])

    {{-- Entry bar --}}
    <section id="grn-entry-bar" aria-labelledby="grn-entry-heading" class="scroll-mt-4 overflow-hidden rounded-2xl bg-white ring-2 ring-blue-600">
        <template x-for="(item, index) in items" :key="index">
            <div x-show="selected === index" @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); nextLine(); }">
                <div class="flex flex-wrap items-center gap-2 border-b border-blue-100 bg-blue-50 px-4 py-2.5">
                    <h3 id="grn-entry-heading" class="text-sm font-bold text-blue-900">
                        <span x-text="isBlankLine(item) ? 'Add a line' : 'Line ' + (index + 1)"></span>
                        <span class="font-semibold text-blue-700" x-text="'· ' + (index + 1) + ' of ' + items.length"></span>
                    </h3>
                    <span x-show="lineIssue(item).level === 'error' || lineIssue(item).level === 'warning'"
                        :class="lineIssue(item).level === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-amber-50 text-amber-900 ring-amber-200'"
                        class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" x-text="lineIssue(item).text"></span>
                    <span class="ml-auto hidden text-xs text-blue-800 sm:inline">Fill with <kbd class="rounded border border-blue-300 bg-white px-1">Tab</kbd> · <kbd class="rounded border border-blue-300 bg-white px-1">Enter</kbd> adds it to the list</span>
                </div>
                <div class="space-y-4 p-4">
                    @include('goods-receipt-notes.partials.line-fields', ['sectionsGrid' => 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4'])
                </div>
                <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-sm tabular-nums text-gray-600">
                        <span>Line total</span>
                        <b class="ml-1 text-lg text-gray-900" x-text="formatNumber(item.total_value_with_taxes)"></b>
                    </div>
                    <button type="button" x-show="items.length > 1" @click="removeLine(index)"
                        class="inline-flex h-10 items-center rounded-lg px-3 text-sm font-semibold text-red-700 ring-1 ring-gray-300 hover:bg-red-50">Remove</button>
                    <button type="button" @click="nextLine()"
                        class="ml-auto inline-flex h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <span x-text="index === items.length - 1 ? 'Add to list' : 'Save & next line'"></span>
                        <kbd class="rounded border border-blue-300 px-1.5 text-[11px] font-semibold text-blue-100">Enter</kbd>
                    </button>
                </div>
            </div>
        </template>
    </section>

    {{-- Lines already added --}}
    <section aria-label="Added lines" class="overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200">
        <div class="border-b border-gray-200 px-4 py-2.5">
            @include('goods-receipt-notes.partials.line-filters', ['heading' => 'Added lines', 'searchId' => 'c'])
        </div>
        <div class="max-h-[60vh] overflow-auto">
            <table class="w-full min-w-[44rem] border-separate border-spacing-0 text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-600">
                    <tr class="[&>th]:border-b [&>th]:border-gray-200 [&>th]:px-3 [&>th]:py-2">
                        <th class="w-8"><span class="sr-only">Status</span></th>
                        <th class="w-10 text-left">#</th>
                        <th class="text-left">Product</th>
                        <th class="text-right">Pcs</th>
                        <th class="text-right">Value</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">Taxes</th>
                        <th class="text-right">Line total</th>
                        <th class="w-16"><span class="sr-only">Edit</span></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, index) in items" :key="'row-' + index">
                        <tr :id="'grn-line-row-' + index" x-show="lineMatches(item, index)" @click="selectLine(index)"
                            :class="selected === index ? 'bg-blue-50' : (lineIssue(item).level === 'error' ? 'bg-red-50' : (lineIssue(item).level === 'warning' ? 'bg-amber-50' : 'bg-white hover:bg-gray-50'))"
                            class="cursor-pointer tabular-nums [&>td]:border-b [&>td]:border-gray-100 [&>td]:px-3 [&>td]:py-2">
                            <td class="text-center"><span class="inline-block h-2.5 w-2.5 rounded-full" role="img" :aria-label="lineIssue(item).level === 'ok' ? 'OK' : lineIssue(item).text"
                                :class="{ 'bg-red-600': lineIssue(item).level === 'error', 'bg-amber-500': lineIssue(item).level === 'warning', 'bg-green-500': lineIssue(item).level === 'ok', 'bg-blue-300': lineIssue(item).level === 'new' }"></span></td>
                            <td class="text-xs font-semibold text-gray-500" x-text="index + 1"></td>
                            <td class="max-w-[18rem]">
                                <span class="block truncate font-semibold text-gray-900" x-text="productLabel(item)"></span>
                                <span class="block truncate text-xs" :class="lineIssue(item).level === 'error' ? 'text-red-700' : (lineIssue(item).level === 'warning' ? 'text-amber-800' : 'text-gray-500')" x-text="lineIssue(item).level === 'ok' ? productCode(item) : lineIssue(item).text"></span>
                            </td>
                            <td class="text-right" x-text="formatNumber(item.qty_in_stock_uom)"></td>
                            <td class="text-right" x-text="formatNumber(item.extended_value)"></td>
                            <td class="text-right" x-text="formatNumber((parseFloat(item.discount_value) || 0) + (parseFloat(item.fmr_allowance) || 0))"></td>
                            <td class="text-right" x-text="formatNumber((parseFloat(item.total_value_with_taxes) || 0) - (parseFloat(item.discounted_value_before_tax) || 0))"></td>
                            <td class="text-right font-bold text-gray-900" x-text="formatNumber(item.total_value_with_taxes)"></td>
                            <td class="text-right"><button type="button" @click.stop="selectLine(index)" class="rounded-md px-2 py-1 text-sm font-semibold text-blue-700 hover:bg-blue-100" :aria-label="'Edit line ' + (index + 1)">Edit</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 bg-gray-50 px-4 py-2.5">
            <label for="grn-add-many-c" class="text-xs font-semibold text-gray-600">Add empty lines</label>
            <select id="grn-add-many-c" class="h-9 w-32 rounded-lg border-gray-300 text-sm shadow-sm"
                @change="if ($event.target.value) { addLinesAndSelect($event.target.value); $event.target.value = ''; }">
                <option value="">Choose…</option>
                <option value="1">1 line</option>
                <option value="5">5 lines</option>
                <option value="10">10 lines</option>
                <option value="25">25 lines</option>
            </select>
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
