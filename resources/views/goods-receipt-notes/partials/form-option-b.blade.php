{{--
    GRN form, Option B: the lines on the left, the chosen line with every field on the right.
    Every line keeps its full set of inputs on the page (only the chosen one is shown), so the
    field names, the Select2 product pickers and grnForm()'s calculations are the classic ones.

    $grn: the GRN being edited, or null on create. $submitLabel: the save button's text.
--}}
@php
    $grn = $grn ?? null;
@endphp

<div class="space-y-4 px-3 sm:px-0">
    @include('goods-receipt-notes.partials.modern-header-fields', ['grn' => $grn])

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
        {{-- Lines list --}}
        <section aria-labelledby="grn-lines-heading"
            class="flex min-w-0 flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200 lg:sticky lg:top-4 lg:max-h-[calc(100vh-7rem)] lg:w-96 lg:shrink-0">
            <div class="space-y-2.5 border-b border-gray-200 p-3">
                <div class="flex items-center justify-between">
                    <h3 id="grn-lines-heading" class="text-sm font-bold text-gray-900">Lines <span class="font-semibold text-gray-500" x-text="'· ' + items.length"></span></h3>
                    <span class="text-xs tabular-nums text-gray-600" x-text="formatNumber(totalCases()) + (isCartonMode ? ' ctn · ' : ' cases · ') + formatNumber(sumOf('qty_in_stock_uom')) + ' pcs'"></span>
                </div>
                <div class="relative" x-show="items.length > 6">
                    <label for="grn-line-search" class="sr-only">Find a line</label>
                    <input id="grn-line-search" type="search" x-model="lineSearch" placeholder="Find by product, code or line no."
                        class="block w-full rounded-lg border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter lines">
                    <button type="button" @click="lineFilter = 'all'" :aria-pressed="lineFilter === 'all'"
                        :class="lineFilter === 'all' ? 'bg-gray-900 text-white ring-gray-900' : 'bg-white text-gray-700 ring-gray-200'"
                        class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1">All <span x-text="items.length"></span></button>
                    <button type="button" x-show="issueCounts.error > 0" @click="lineFilter = 'error'" :aria-pressed="lineFilter === 'error'"
                        :class="lineFilter === 'error' ? 'bg-red-700 text-white ring-red-700' : 'bg-red-50 text-red-800 ring-red-200'"
                        class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"><span x-text="issueCounts.error"></span> errors</button>
                    <button type="button" x-show="issueCounts.warning > 0" @click="lineFilter = 'warning'" :aria-pressed="lineFilter === 'warning'"
                        :class="lineFilter === 'warning' ? 'bg-amber-600 text-white ring-amber-600' : 'bg-amber-50 text-amber-900 ring-amber-200'"
                        class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1"><span x-text="issueCounts.warning"></span> warnings</button>
                </div>
            </div>

            <div class="max-h-80 min-h-0 flex-1 overflow-y-auto lg:max-h-none">
                <template x-for="(item, index) in items" :key="'list-' + index">
                    <button type="button" :id="'grn-line-row-' + index" x-show="lineMatches(item, index)" @click="selectLine(index)"
                        :class="selected === index ? 'bg-blue-50 shadow-[inset_3px_0_0_#2563eb]' : 'bg-white hover:bg-gray-50'"
                        class="grid w-full grid-cols-[18px_30px_minmax(0,1fr)_auto] items-center gap-2 border-b border-gray-100 px-3 py-2 text-left"
                        :aria-label="'Line ' + (index + 1) + ', ' + productLabel(item)" :aria-current="selected === index">
                        <span aria-hidden="true">
                            <template x-if="lineIssue(item).level === 'ok'"><svg class="h-4 w-4 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5" /></svg></template>
                            <template x-if="lineIssue(item).level === 'warning'"><svg class="h-4 w-4 text-amber-600" viewBox="0 0 24 24" fill="#FEF3C7" stroke="currentColor" stroke-width="2"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg></template>
                            <template x-if="lineIssue(item).level === 'error'"><svg class="h-4 w-4 text-red-600" viewBox="0 0 24 24" fill="#FEE2E2" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M12 8v4M12 16h.01" /></svg></template>
                            <template x-if="lineIssue(item).level === 'new'"><svg class="h-4 w-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" stroke-dasharray="3 3" /></svg></template>
                        </span>
                        <span class="text-xs font-semibold tabular-nums text-gray-500" x-text="index + 1"></span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-gray-900" x-text="productLabel(item)"></span>
                            <span class="block truncate text-xs tabular-nums"
                                :class="{ 'text-red-700': lineIssue(item).level === 'error', 'text-amber-800': lineIssue(item).level === 'warning', 'text-blue-700': lineIssue(item).level === 'new', 'text-gray-500': lineIssue(item).level === 'ok' }"
                                x-text="lineSummary(item)"></span>
                        </span>
                        <span class="text-sm font-semibold tabular-nums text-gray-900" x-text="formatNumber(item.total_value_with_taxes)"></span>
                    </button>
                </template>
            </div>

            <div class="flex items-center gap-2 border-t border-gray-200 bg-gray-50 p-3">
                <button type="button" @click="addLinesAndSelect(1)"
                    class="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg bg-blue-600 text-sm font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    Add line
                </button>
                <label for="grn-add-many" class="sr-only">Add several lines</label>
                <select id="grn-add-many" class="h-10 w-32 rounded-lg border-gray-300 text-sm shadow-sm"
                    @change="if ($event.target.value) { addLinesAndSelect($event.target.value); $event.target.value = ''; }">
                    <option value="">Add more…</option>
                    <option value="5">5 lines</option>
                    <option value="10">10 lines</option>
                    <option value="25">25 lines</option>
                </select>
            </div>
        </section>

        {{-- Line editor: one per line, only the chosen one is shown --}}
        <section aria-label="Line details" class="min-w-0 flex-1">
            <template x-for="(item, index) in items" :key="index">
                <div x-show="selected === index" data-grn-line-editor
                    @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); nextLine(); }"
                    class="overflow-hidden rounded-2xl bg-gray-50 ring-1 ring-gray-200">
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-white px-4 py-3">
                        <h3 class="text-sm font-bold text-gray-900">Line <span x-text="index + 1"></span> <span class="font-semibold text-gray-500">of <span x-text="items.length"></span></span></h3>
                        <span x-show="lineIssue(item).level === 'error'" class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-red-200" x-text="lineIssue(item).text"></span>
                        <span x-show="lineIssue(item).level === 'warning'" class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-200" x-text="lineIssue(item).text"></span>
                        <div class="ml-auto flex gap-1.5">
                            <button type="button" @click="prevLine()" :disabled="index === 0" aria-label="Previous line" title="Previous line"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg ring-1 ring-gray-200 hover:bg-gray-50 disabled:opacity-40">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 15l-6-6-6 6" /></svg>
                            </button>
                            <button type="button" @click="selectLine(index + 1)" :disabled="index === items.length - 1" aria-label="Next line" title="Next line"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg ring-1 ring-gray-200 hover:bg-gray-50 disabled:opacity-40">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
                            </button>
                            <button type="button" @click="removeLine(index)" :disabled="items.length === 1"
                                class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-red-700 ring-1 ring-gray-200 hover:bg-red-50 disabled:opacity-40">Remove</button>
                        </div>
                    </div>

                    <div class="space-y-4 p-4">
                        @include('goods-receipt-notes.partials.line-fields')
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 bg-white px-4 py-3">
                        <div>
                            <div class="text-xs font-semibold text-gray-600">Line total with taxes</div>
                            <div class="text-xl font-bold tabular-nums text-gray-900" x-text="formatNumber(item.total_value_with_taxes)"></div>
                        </div>
                        <div class="text-xs tabular-nums text-gray-600" x-text="'Value ' + formatNumber(item.extended_value) + ' − discount ' + formatNumber((parseFloat(item.discount_value) || 0) + (parseFloat(item.fmr_allowance) || 0)) + ' + taxes ' + formatNumber((parseFloat(item.total_value_with_taxes) || 0) - (parseFloat(item.discounted_value_before_tax) || 0))"></div>
                        <button type="button" @click="nextLine()"
                            class="ml-auto inline-flex h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <span x-text="index === items.length - 1 ? 'Save & add next line' : 'Save & next line'"></span>
                            <kbd class="rounded border border-blue-300 px-1.5 text-[11px] font-semibold text-blue-100">Enter</kbd>
                        </button>
                    </div>
                </div>
            </template>
        </section>
    </div>

    <section aria-labelledby="grn-notes-heading" class="rounded-2xl bg-white p-4 ring-1 ring-gray-200">
        <label id="grn-notes-heading" for="notes" class="mb-1 block text-sm font-bold text-gray-900">Notes</label>
        <textarea id="notes" name="notes" rows="2" placeholder="Anything the store team should know about this delivery"
            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $grn?->notes) }}</textarea>
    </section>

    @include('goods-receipt-notes.partials.modern-totals-bar', ['submitLabel' => $submitLabel])
</div>

@include('goods-receipt-notes.partials.modern-form-script')
