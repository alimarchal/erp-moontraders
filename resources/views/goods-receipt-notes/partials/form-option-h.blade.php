{{--
    GRN form, Option H: enterprise object page (in the spirit of SAP Fiori / Oracle). A header
    with the key figures, a section bar (General · Items · Summary · Notes), a dense items table
    and an item detail panel for the selected row. Every line keeps its inputs on the page.

    $grn: the GRN being edited, or null on create. $submitLabel: the save button's text.
--}}
@php
    $grn = $grn ?? null;
    $sections = ['general' => 'General', 'items' => 'Items', 'summary' => 'Summary', 'notes' => 'Notes'];
@endphp

<div class="space-y-0 px-3 sm:px-0">
    {{-- Object header --}}
    <section class="rounded-t-xl border border-gray-300 bg-white">
        <div class="flex flex-wrap items-start gap-4 px-5 py-4">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Goods receipt note</p>
                <h3 class="text-xl font-semibold tracking-tight text-gray-900">{{ $grn ? $grn->grn_number : 'New goods receipt' }}</h3>
                <p class="mt-0.5 truncate text-sm text-gray-600" x-text="headerSummary()"></p>
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="text-xs text-gray-500">Status</dt><dd><span class="inline-flex items-center gap-1.5 font-semibold text-amber-700"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Draft</span></dd></div>
                <div><dt class="text-xs text-gray-500">Items</dt><dd class="font-semibold tabular-nums" x-text="items.length"></dd></div>
                <div><dt class="text-xs text-gray-500">Quantity</dt><dd class="font-semibold tabular-nums" x-text="formatNumber(sumOf('qty_in_stock_uom'))"></dd></div>
                <div><dt class="text-xs text-gray-500">Net value</dt><dd class="font-semibold tabular-nums" x-text="formatCurrency(grandTotal)"></dd></div>
            </dl>
        </div>
        {{-- Section bar --}}
        <nav aria-label="Sections" class="sticky top-0 z-10 flex gap-1 overflow-x-auto border-t border-gray-200 bg-gray-50 px-3">
            @foreach ($sections as $key => $label)
                <a href="#grn-h-{{ $key }}" class="whitespace-nowrap border-b-2 border-transparent px-3 py-2.5 text-sm font-semibold text-gray-600 hover:border-blue-600 hover:text-blue-700">{{ $label }}</a>
            @endforeach
            <span class="ml-auto flex items-center gap-2 py-1.5 text-xs" aria-live="polite">
                <span :class="blockingCount > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'" class="rounded px-2 py-0.5 font-semibold" x-text="blockingCount > 0 ? blockingCount + ' error(s)' : 'No errors'"></span>
                <span x-show="issueCounts.warning > 0" class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-900" x-text="issueCounts.warning + ' warning(s)'"></span>
            </span>
        </nav>
    </section>

    <div class="space-y-4 rounded-b-xl border border-t-0 border-gray-300 bg-gray-100 p-4">
        {{-- General --}}
        <section id="grn-h-general" class="scroll-mt-16">
            <h4 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">General information</h4>
            @include('goods-receipt-notes.partials.modern-header-fields', ['grn' => $grn])
        </section>

        {{-- Items --}}
        <section id="grn-h-items" class="scroll-mt-16 overflow-hidden rounded-lg border border-gray-300 bg-white">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2">
                <h4 class="text-sm font-semibold text-gray-800">Items (<span x-text="items.length"></span>)</h4>
                <div class="ml-2 flex gap-1" role="group" aria-label="Filter items">
                    <button type="button" @click="lineFilter = 'all'" :class="lineFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-300'" class="rounded px-2 py-0.5 text-xs font-semibold">All</button>
                    <button type="button" x-show="issueCounts.error > 0" @click="lineFilter = 'error'" :class="lineFilter === 'error' ? 'bg-red-700 text-white' : 'bg-white text-red-700 ring-1 ring-red-200'" class="rounded px-2 py-0.5 text-xs font-semibold">Errors</button>
                    <button type="button" x-show="issueCounts.warning > 0" @click="lineFilter = 'warning'" :class="lineFilter === 'warning' ? 'bg-amber-600 text-white' : 'bg-white text-amber-800 ring-1 ring-amber-200'" class="rounded px-2 py-0.5 text-xs font-semibold">Warnings</button>
                </div>
                <label for="grn-h-search" class="sr-only">Find an item</label>
                <input id="grn-h-search" type="search" x-model="lineSearch" placeholder="Search items" class="h-8 w-48 rounded border-gray-300 text-sm">
                <div class="ml-auto flex items-center gap-1">
                    <button type="button" @click="addLinesAndSelect(1)" class="h-8 rounded border border-gray-300 bg-white px-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">Add</button>
                    <button type="button" @click="if (selected >= 0) { removeLine(selected) }" :disabled="items.length <= 1 || selected < 0" class="h-8 rounded border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40">Delete</button>
                    <select aria-label="Add several items" class="h-8 rounded border-gray-300 py-0 text-sm" @change="if ($event.target.value) { addLinesAndSelect($event.target.value); $event.target.value = ''; }">
                        <option value="">Add more…</option><option value="5">5</option><option value="10">10</option><option value="25">25</option>
                    </select>
                </div>
            </div>
            <div class="max-h-80 overflow-auto">
                <table class="w-full min-w-[52rem] text-[13px]">
                    <thead class="sticky top-0 bg-gray-100 text-xs font-semibold text-gray-600">
                        <tr class="[&>th]:border-b [&>th]:border-gray-300 [&>th]:px-2 [&>th]:py-1.5">
                            <th class="w-8"><span class="sr-only">Status</span></th><th class="w-10 text-left">Item</th><th class="text-left">Material</th>
                            <th class="text-right">Qty</th><th class="text-right">Pcs</th><th class="text-right">Price</th><th class="text-right">Net value</th><th class="text-right">Tax</th><th class="text-right">Gross</th><th class="text-right">Unit cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in items" :key="'row-' + index">
                            <tr :id="'grn-line-row-' + index" x-show="lineMatches(item, index)" @click="selectLine(index)"
                                :class="selected === index ? 'bg-blue-100' : (index % 2 ? 'bg-gray-50 hover:bg-blue-50' : 'bg-white hover:bg-blue-50')"
                                class="cursor-pointer tabular-nums [&>td]:border-b [&>td]:border-gray-200 [&>td]:px-2 [&>td]:py-1.5">
                                <td class="text-center">
                                    <span class="inline-block h-2.5 w-2.5 rounded-sm" role="img" :aria-label="lineIssue(item).level === 'ok' ? 'OK' : lineIssue(item).text"
                                        :class="{ 'bg-red-600': lineIssue(item).level === 'error', 'bg-amber-500': lineIssue(item).level === 'warning', 'bg-green-600': lineIssue(item).level === 'ok', 'bg-gray-300': lineIssue(item).level === 'new' }"></span>
                                </td>
                                <td class="text-gray-600" x-text="String((index + 1) * 10).padStart(4, '0')"></td>
                                <td><span class="font-semibold text-gray-900" x-text="productLabel(item)"></span> <span class="text-gray-500" x-text="productCode(item)"></span></td>
                                <td class="text-right" x-text="formatNumber(item.qty_in_purchase_uom)"></td>
                                <td class="text-right" x-text="formatNumber(item.qty_in_stock_uom)"></td>
                                <td class="text-right" x-text="formatNumber(item.unit_price_per_case)"></td>
                                <td class="text-right" x-text="formatNumber(item.discounted_value_before_tax)"></td>
                                <td class="text-right" x-text="formatNumber((parseFloat(item.total_value_with_taxes) || 0) - (parseFloat(item.discounted_value_before_tax) || 0))"></td>
                                <td class="text-right font-semibold" x-text="formatNumber(item.total_value_with_taxes)"></td>
                                <td class="text-right" x-text="formatUnitCost(item.unit_cost)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Item detail panel --}}
            <template x-for="(item, index) in items" :key="index">
                <div x-show="selected === index" class="border-t-2 border-blue-600 bg-gray-50"
                    @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); nextLine(); }">
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-white px-3 py-2">
                        <h4 class="text-sm font-semibold text-gray-800">Item <span x-text="String((index + 1) * 10).padStart(4, '0')"></span> details</h4>
                        <span x-show="lineIssue(item).level === 'error' || lineIssue(item).level === 'warning'" class="text-xs font-semibold"
                            :class="lineIssue(item).level === 'error' ? 'text-red-700' : 'text-amber-800'" x-text="lineIssue(item).text"></span>
                        <div class="ml-auto flex gap-1">
                            <button type="button" @click="prevLine()" :disabled="index === 0" class="h-8 rounded border border-gray-300 bg-white px-2 text-sm disabled:opacity-40" aria-label="Previous item">‹</button>
                            <button type="button" @click="nextLine()" class="h-8 rounded border border-blue-600 bg-blue-600 px-3 text-sm font-semibold text-white hover:bg-blue-700">Next item ›</button>
                        </div>
                    </div>
                    <div class="space-y-3 p-3">
                        @include('goods-receipt-notes.partials.line-fields', ['sectionsGrid' => 'grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4'])
                    </div>
                </div>
            </template>
        </section>

        {{-- Summary --}}
        <section id="grn-h-summary" class="scroll-mt-16 rounded-lg border border-gray-300 bg-white">
            <h4 class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-800">Summary</h4>
            <dl class="grid grid-cols-1 gap-x-10 gap-y-1.5 p-4 text-sm tabular-nums sm:grid-cols-2 lg:grid-cols-3">
                <div class="flex justify-between border-b border-dotted border-gray-200 py-1"><dt class="text-gray-600">Gross amount</dt><dd x-text="formatNumber(sumOf('extended_value'))"></dd></div>
                <div class="flex justify-between border-b border-dotted border-gray-200 py-1"><dt class="text-gray-600">Discount</dt><dd x-text="formatNumber(sumOf('discount_value'))"></dd></div>
                <div class="flex justify-between border-b border-dotted border-gray-200 py-1"><dt class="text-gray-600">FMR allowance</dt><dd x-text="formatNumber(sumOf('fmr_allowance'))"></dd></div>
                <div class="flex justify-between border-b border-dotted border-gray-200 py-1"><dt class="text-gray-600">Net value before tax</dt><dd x-text="formatNumber(sumOf('discounted_value_before_tax'))"></dd></div>
                <div class="flex justify-between border-b border-dotted border-gray-200 py-1"><dt class="text-gray-600">Sales tax</dt><dd x-text="formatNumber(sumOf('sales_tax_value'))"></dd></div>
                <div class="flex justify-between border-b border-dotted border-gray-200 py-1"><dt class="text-gray-600">Excise duty</dt><dd x-text="formatNumber(sumOf('excise_duty'))"></dd></div>
                <div class="flex justify-between border-b border-dotted border-gray-200 py-1"><dt class="text-gray-600">Advance income tax</dt><dd x-text="formatNumber(sumOf('advance_income_tax'))"></dd></div>
                <div class="flex justify-between border-b border-dotted border-gray-200 py-1"><dt class="text-gray-600">Other charges</dt><dd x-text="formatNumber(sumOf('other_charges'))"></dd></div>
                <div class="flex justify-between border-b border-dotted border-gray-200 py-1"><dt class="text-gray-600">Withholding tax</dt><dd x-text="formatNumber(sumOf('withholding_tax'))"></dd></div>
                <div class="flex justify-between py-1 font-semibold sm:col-span-2 lg:col-span-3"><dt>Total with taxes</dt><dd x-text="formatCurrency(grandTotal)"></dd></div>
            </dl>
        </section>

        {{-- Notes --}}
        <section id="grn-h-notes" class="scroll-mt-16 rounded-lg border border-gray-300 bg-white p-3">
            <label for="notes" class="mb-1 block text-sm font-semibold text-gray-800">Notes</label>
            <textarea id="notes" name="notes" rows="2" class="block w-full rounded border-gray-300 text-sm">{{ old('notes', $grn?->notes) }}</textarea>
        </section>

        @include('goods-receipt-notes.partials.modern-totals-bar', ['submitLabel' => $submitLabel])
    </div>
</div>

@include('goods-receipt-notes.partials.modern-form-script')
