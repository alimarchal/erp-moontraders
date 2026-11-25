{{--
    GRN form, Option I: the lines as a short, easy list; clicking a line (or Add line) opens a
    popup with every field of that line. Save & next moves the popup to the next line.
    Every line keeps its inputs on the page (inside its popup), so the form sends them all.

    $grn: the GRN being edited, or null on create. $submitLabel: the save button's text.
--}}
@php
    $grn = $grn ?? null;
@endphp

@include('goods-receipt-notes.partials.apple-style')

<div class="grn-apl space-y-4 px-3 sm:px-0" x-init="lineModal = true">
    @include('goods-receipt-notes.partials.modern-header-fields', ['grn' => $grn])

    <section aria-labelledby="grn-i-heading" class="grn-card overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200">
        <div class="border-b border-gray-200 px-4 py-3">
            @include('goods-receipt-notes.partials.line-filters', ['heading' => 'Lines', 'searchId' => 'i'])
        </div>

        <ul class="grn-lines divide-y divide-gray-100" id="grn-i-heading">
            <template x-for="(item, index) in items" :key="'row-' + index">
                <li :id="'grn-line-row-' + index" x-show="lineMatches(item, index)">
                    <button type="button" @click="openLine(index)" aria-haspopup="dialog" :aria-label="'Open line ' + (index + 1) + ', ' + productLabel(item)"
                        class="grid w-full grid-cols-[2rem_minmax(0,1fr)_auto_1.25rem] items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50 active:bg-gray-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl text-xs font-bold"
                            :class="{ 'bg-red-100 text-red-800': lineIssue(item).level === 'error', 'bg-amber-100 text-amber-900': lineIssue(item).level === 'warning', 'bg-green-100 text-green-800': lineIssue(item).level === 'ok', 'bg-blue-50 text-blue-800': lineIssue(item).level === 'new' }"
                            x-text="index + 1"></span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-gray-900" x-text="productLabel(item)"></span>
                            <span class="block truncate text-xs tabular-nums"
                                :class="{ 'text-red-700': lineIssue(item).level === 'error', 'text-amber-800': lineIssue(item).level === 'warning', 'text-blue-700': lineIssue(item).level === 'new', 'text-gray-500': lineIssue(item).level === 'ok' }"
                                x-text="lineSummary(item) + (lineIssue(item).level === 'ok' && hasBatchDetails(item) ? ' · ' + batchSummary(item) : '')"></span>
                        </span>
                        <span class="text-right">
                            <span class="block text-sm font-semibold tabular-nums text-gray-900" x-text="formatNumber(item.total_value_with_taxes)"></span>
                            <span class="block text-[11px] tabular-nums text-gray-500" x-text="'tax ' + formatNumber((parseFloat(item.total_value_with_taxes) || 0) - (parseFloat(item.discounted_value_before_tax) || 0))"></span>
                        </span>
                        <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                    </button>

                    {{-- The line's popup --}}
                    <div x-show="lineModalOpen && selected === index" x-cloak class="fixed inset-0 z-40 flex items-end justify-center sm:items-center sm:p-6"
                        role="dialog" aria-modal="true" :aria-labelledby="'line-title-' + index"
                        @keydown.escape.window="if (lineModalOpen && selected === index && batchFor === null) { lineModalOpen = false }"
                        @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); nextLine(); }">
                        <div class="grn-scrim absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="lineModalOpen = false"
                            x-show="lineModalOpen && selected === index" x-transition:enter="transition-opacity duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0"></div>
                        <div class="grn-sheet relative flex max-h-[94vh] w-full flex-col overflow-hidden rounded-t-3xl bg-gray-50 shadow-2xl ring-1 ring-black/5 sm:max-w-5xl sm:rounded-3xl"
                            x-show="lineModalOpen && selected === index"
                            x-transition:enter="transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-opacity motion-reduce:duration-150"
                            x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-2 sm:scale-[0.98] motion-reduce:translate-y-0 motion-reduce:scale-100">
                            <div class="mx-auto mt-2 h-1.5 w-10 rounded-full bg-gray-300 sm:hidden" aria-hidden="true"></div>
                            <div class="grn-sheet-bar flex items-start gap-3 border-b border-gray-200 bg-white px-5 pb-3 pt-3 sm:pt-5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-500">Line <span x-text="index + 1"></span> of <span x-text="items.length"></span></p>
                                    <h3 :id="'line-title-' + index" class="truncate text-lg font-semibold tracking-tight text-gray-900" x-text="productLabel(item)"></h3>
                                    <p x-show="lineIssue(item).level === 'error' || lineIssue(item).level === 'warning'" class="text-sm font-semibold"
                                        :class="lineIssue(item).level === 'error' ? 'text-red-700' : 'text-amber-800'" x-text="lineIssue(item).text"></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-semibold text-gray-500">Line total</p>
                                    <p class="text-lg font-semibold tabular-nums text-gray-900" x-text="formatNumber(item.total_value_with_taxes)"></p>
                                </div>
                                <button type="button" @click="lineModalOpen = false" aria-label="Close" class="grn-close inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition-transform duration-100 hover:bg-gray-200 active:scale-95">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                </button>
                            </div>
                            <div class="space-y-4 overflow-y-auto p-5">
                                @include('goods-receipt-notes.partials.line-fields', ['sectionsGrid' => 'grid grid-cols-1 gap-3 md:grid-cols-2'])
                            </div>
                            <div class="grn-sheet-bar flex flex-wrap items-center gap-2 border-t border-gray-200 bg-white px-5 py-3">
                                <button type="button" @click="prevLine()" :disabled="index === 0" class="grn-pill is-plain h-10 rounded-xl px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-40">‹ Previous</button>
                                <button type="button" x-show="items.length > 1" @click="removeLine(index); lineModalOpen = false" class="grn-pill is-danger h-10 rounded-xl px-3 text-sm font-semibold text-red-700 hover:bg-red-50">Remove line</button>
                                <div class="ml-auto flex gap-2">
                                    <button type="button" @click="lineModalOpen = false" class="grn-pill is-plain h-10 rounded-xl px-4 text-sm font-semibold text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50">Done</button>
                                    <button type="button" @click="nextLine()" class="grn-pill is-primary inline-flex h-10 items-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white transition-transform duration-100 hover:bg-blue-700 active:scale-[0.97]">
                                        <span x-text="index === items.length - 1 ? 'Save & add next line' : 'Save & next line'"></span>
                                        <kbd class="hidden rounded border border-blue-300 px-1.5 text-[11px] text-blue-100 sm:inline">Enter</kbd>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            </template>
        </ul>

        <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 bg-gray-50 px-4 py-3">
            <button type="button" @click="addLineInModal()" aria-haspopup="dialog"
                class="grn-pill is-primary inline-flex h-11 items-center gap-1.5 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white transition-transform duration-100 hover:bg-blue-700 active:scale-[0.97]">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                Add line
            </button>
            <span class="text-xs text-gray-500">Click a line to open it. In the popup, <kbd class="rounded border border-gray-300 bg-white px-1">Enter</kbd> saves and opens the next line.</span>
        </div>
    </section>

    <section class="grn-card rounded-2xl bg-white p-4 ring-1 ring-gray-200">
        <label for="notes" class="mb-1 block text-xs font-semibold text-gray-600">Notes</label>
        <textarea id="notes" name="notes" rows="2" placeholder="Anything the store team should know about this delivery"
            class="block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $grn?->notes) }}</textarea>
    </section>

    @include('goods-receipt-notes.partials.modern-totals-bar', ['submitLabel' => $submitLabel])
</div>

@include('goods-receipt-notes.partials.modern-form-script')
