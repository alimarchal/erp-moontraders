{{--
    GRN form, Option D: one card per line. A closed card shows the product, quantity, status
    and total; the open card shows every field. One card is open at a time, which suits phones
    and tablets. Every line keeps its inputs on the page; closed cards only hide them.

    $grn: the GRN being edited, or null on create. $submitLabel: the save button's text.
--}}
@php
    $grn = $grn ?? null;
@endphp

<div class="space-y-4 px-3 sm:px-0">
    @include('goods-receipt-notes.partials.modern-header-fields', ['grn' => $grn])

    <section aria-label="Lines" class="space-y-3">
        <div class="rounded-2xl bg-white px-4 py-2.5 ring-1 ring-gray-200">
            @include('goods-receipt-notes.partials.line-filters', ['heading' => 'Lines', 'searchId' => 'd'])
        </div>

        <template x-for="(item, index) in items" :key="index">
            <article :id="'grn-line-row-' + index" x-show="lineMatches(item, index) || selected === index"
                :class="selected === index ? 'ring-2 ring-blue-600' : (lineIssue(item).level === 'error' ? 'ring-1 ring-red-300' : (lineIssue(item).level === 'warning' ? 'ring-1 ring-amber-300' : 'ring-1 ring-gray-200'))"
                class="scroll-mt-4 overflow-hidden rounded-2xl bg-white">
                <button type="button" @click="selected === index ? (selected = -1) : selectLine(index)" :aria-expanded="selected === index"
                    class="grid w-full grid-cols-[20px_minmax(0,1fr)_auto_20px] items-center gap-3 px-4 py-3 text-left hover:bg-gray-50">
                    <span aria-hidden="true" class="inline-block h-3 w-3 justify-self-center rounded-full"
                        :class="{ 'bg-red-600': lineIssue(item).level === 'error', 'bg-amber-500': lineIssue(item).level === 'warning', 'bg-green-500': lineIssue(item).level === 'ok', 'bg-blue-300': lineIssue(item).level === 'new' }"></span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-gray-900">
                            <span class="mr-1 text-gray-500" x-text="(index + 1) + ' ·'"></span><span x-text="productLabel(item)"></span>
                        </span>
                        <span class="block truncate text-xs tabular-nums"
                            :class="{ 'text-red-700': lineIssue(item).level === 'error', 'text-amber-800': lineIssue(item).level === 'warning', 'text-blue-700': lineIssue(item).level === 'new', 'text-gray-500': lineIssue(item).level === 'ok' }"
                            x-text="lineSummary(item)"></span>
                    </span>
                    <span class="text-right">
                        <span class="block text-sm font-bold tabular-nums text-gray-900" x-text="formatNumber(item.total_value_with_taxes)"></span>
                        <span class="block text-[11px] text-gray-500">line total</span>
                    </span>
                    <svg class="h-4 w-4 text-gray-400 transition-transform" :class="selected === index ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
                </button>

                <div x-show="selected === index" class="border-t border-gray-200 bg-gray-50"
                    @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); nextLine(); }">
                    <div class="space-y-4 p-4">
                        @include('goods-receipt-notes.partials.line-fields', ['sectionsGrid' => 'grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4'])
                    </div>
                    <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 bg-white px-4 py-3">
                        <button type="button" x-show="items.length > 1" @click="removeLine(index)"
                            class="inline-flex h-10 items-center rounded-lg px-3 text-sm font-semibold text-red-700 ring-1 ring-gray-300 hover:bg-red-50">Remove line</button>
                        <button type="button" @click="selected = -1"
                            class="inline-flex h-10 items-center rounded-lg px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Close</button>
                        <button type="button" @click="nextLine()"
                            class="ml-auto inline-flex h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <span x-text="index === items.length - 1 ? 'Save & add next line' : 'Save & next line'"></span>
                            <kbd class="hidden rounded border border-blue-300 px-1.5 text-[11px] font-semibold text-blue-100 sm:inline">Enter</kbd>
                        </button>
                    </div>
                </div>
            </article>
        </template>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="addLinesAndSelect(1)"
                class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-blue-300 bg-white text-sm font-bold text-blue-700 hover:bg-blue-50 sm:flex-none sm:px-6">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                Add line
            </button>
            <label for="grn-add-many-d" class="sr-only">Add several lines</label>
            <select id="grn-add-many-d" class="h-11 w-36 rounded-xl border-gray-300 text-sm shadow-sm"
                @change="if ($event.target.value) { addLinesAndSelect($event.target.value); $event.target.value = ''; }">
                <option value="">Add more…</option>
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
