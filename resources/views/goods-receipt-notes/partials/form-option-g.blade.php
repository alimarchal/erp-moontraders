{{--
    GRN form, Option G: focus mode. The receipt details fold into one line, the lines become a
    strip of chips, and the chosen line fills the screen with large fields and big Previous /
    Next buttons. Made for tablets and for people who enter one line at a time.
    Every line keeps its inputs on the page; only the chosen one is shown.

    $grn: the GRN being edited, or null on create. $submitLabel: the save button's text.
--}}
@php
    $grn = $grn ?? null;
@endphp

<div class="space-y-4 px-3 sm:px-0" x-init="headerOpen = {{ $grn ? 'false' : 'true' }}">
    {{-- Receipt details, folded once filled --}}
    <div x-show="!headerOpen" class="flex flex-wrap items-center gap-3 rounded-2xl bg-white px-4 py-3 ring-1 ring-gray-200">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-gray-700" aria-hidden="true">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h18M3 12h18M3 17h12" /></svg>
        </span>
        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900" x-text="headerSummary()"></span>
        <button type="button" @click="headerOpen = true" class="rounded-xl px-3 py-1.5 text-sm font-semibold text-blue-700 ring-1 ring-blue-200 transition-transform duration-100 hover:bg-blue-50 active:scale-[0.97]">Change</button>
    </div>
    <div x-show="headerOpen" class="space-y-2">
        @include('goods-receipt-notes.partials.modern-header-fields', ['grn' => $grn])
        <div class="flex justify-end">
            <button type="button" @click="if ($('#supplier_id').val() && $('#warehouse_id').val()) { headerOpen = false } else { goStep(2) }"
                class="inline-flex h-10 items-center rounded-xl bg-gray-900 px-5 text-sm font-semibold text-white transition-transform duration-100 hover:bg-gray-800 active:scale-[0.97]">Done, start entering lines</button>
        </div>
    </div>

    {{-- Line chips --}}
    <nav aria-label="Lines" class="rounded-2xl bg-white p-2 ring-1 ring-gray-200">
        <div class="flex items-center gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]">
            <template x-for="(item, index) in items" :key="'chip-' + index">
                <button type="button" :id="'grn-line-row-' + index" @click="selectLine(index)" :aria-current="selected === index"
                    :class="selected === index ? 'bg-gray-900 text-white ring-gray-900' : 'bg-white text-gray-800 ring-gray-200 hover:bg-gray-50'"
                    class="inline-flex shrink-0 items-center gap-2 rounded-full py-1.5 pl-2 pr-3 text-sm font-semibold ring-1 transition-transform duration-100 active:scale-[0.97]">
                    <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-bold"
                        :class="{ 'bg-red-500 text-white': lineIssue(item).level === 'error', 'bg-amber-400 text-amber-950': lineIssue(item).level === 'warning', 'bg-green-500 text-white': lineIssue(item).level === 'ok', 'bg-blue-100 text-blue-800': lineIssue(item).level === 'new' }"
                        x-text="index + 1"></span>
                    <span class="max-w-[10rem] truncate" x-text="productLabel(item)"></span>
                </button>
            </template>
            <button type="button" @click="addLinesAndSelect(1)" aria-label="Add a line"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white transition-transform duration-100 hover:bg-blue-700 active:scale-95">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
            </button>
        </div>
    </nav>

    {{-- The chosen line --}}
    <template x-for="(item, index) in items" :key="index">
        <section x-show="selected === index" :aria-label="'Line ' + (index + 1)" class="overflow-hidden rounded-3xl bg-white shadow-[0_12px_40px_rgba(15,23,42,0.08)] ring-1 ring-gray-200"
            @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); nextLine(); }">
            <div class="flex flex-wrap items-center gap-3 px-5 pb-2 pt-5 sm:px-7">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Line <span x-text="index + 1"></span> of <span x-text="items.length"></span></p>
                    <h3 class="truncate text-2xl font-semibold tracking-tight text-gray-900" x-text="productLabel(item)"></h3>
                    <p x-show="lineIssue(item).level === 'error' || lineIssue(item).level === 'warning'" class="mt-1 text-sm font-semibold"
                        :class="lineIssue(item).level === 'error' ? 'text-red-700' : 'text-amber-800'" x-text="lineIssue(item).text"></p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Line total</p>
                    <p class="text-2xl font-semibold tabular-nums tracking-tight text-gray-900" x-text="formatNumber(item.total_value_with_taxes)"></p>
                </div>
            </div>
            <div class="space-y-4 px-5 pb-5 sm:px-7">
                @include('goods-receipt-notes.partials.line-fields', ['sectionsGrid' => 'grid grid-cols-1 gap-4 md:grid-cols-2'])
            </div>
            <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3 border-t border-gray-100 bg-gray-50/80 px-5 py-4 sm:px-7">
                <button type="button" @click="prevLine()" :disabled="index === 0"
                    class="inline-flex h-12 items-center gap-2 rounded-2xl bg-white px-4 text-sm font-semibold text-gray-800 ring-1 ring-gray-300 transition-transform duration-100 hover:bg-gray-50 active:scale-[0.97] disabled:opacity-40">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    Previous
                </button>
                <button type="button" x-show="items.length > 1" @click="removeLine(index)" class="justify-self-center text-sm font-semibold text-red-700 hover:underline">Remove this line</button>
                <span x-show="items.length <= 1"></span>
                <button type="button" @click="nextLine()"
                    class="inline-flex h-12 items-center gap-2 rounded-2xl bg-blue-600 px-6 text-sm font-bold text-white shadow-sm transition-transform duration-100 hover:bg-blue-700 active:scale-[0.97]">
                    <span x-text="index === items.length - 1 ? 'Save & add next' : 'Save & next'"></span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                </button>
            </div>
        </section>
    </template>

    <section class="rounded-2xl bg-white p-4 ring-1 ring-gray-200">
        <label for="notes" class="mb-1 block text-sm font-semibold text-gray-900">Notes</label>
        <textarea id="notes" name="notes" rows="2" placeholder="Anything the store team should know about this delivery"
            class="block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $grn?->notes) }}</textarea>
    </section>

    @include('goods-receipt-notes.partials.modern-totals-bar', ['submitLabel' => $submitLabel])
</div>

@include('goods-receipt-notes.partials.modern-form-script')
