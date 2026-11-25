{{--
    GRN form, Option E: three steps. 1) receipt details, 2) the lines, 3) review every line and
    the checks, then save. Steps only hide parts of the page; every input stays in the form.

    $grn: the GRN being edited, or null on create. $submitLabel: the save button's text.
--}}
@php
    $grn = $grn ?? null;
    $steps = [1 => ['Receipt details', 'Supplier, warehouse, dates'], 2 => ['Lines', 'Products, quantities, prices'], 3 => ['Review & save', 'Check everything once']];
@endphp

<div class="space-y-4 px-3 sm:px-0" x-init="wizard = true; step = {{ $grn ? 2 : 1 }}">
    {{-- Step bar --}}
    <nav aria-label="Steps" class="rounded-2xl bg-white p-2 ring-1 ring-gray-200">
        <ol class="grid grid-cols-3 gap-1">
            @foreach ($steps as $number => [$title, $hint])
                <li>
                    <button type="button" @click="goStep({{ $number }})" :aria-current="step === {{ $number }} ? 'step' : null"
                        :class="step === {{ $number }} ? 'bg-blue-600 text-white' : (step > {{ $number }} ? 'bg-blue-50 text-blue-900' : 'text-gray-600 hover:bg-gray-50')"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left">
                        <span :class="step === {{ $number }} ? 'bg-white text-blue-700' : (step > {{ $number }} ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700')"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold">
                            <span x-show="step <= {{ $number }}">{{ $number }}</span>
                            <svg x-show="step > {{ $number }}" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-bold">{{ $title }}</span>
                            <span class="hidden truncate text-xs opacity-80 sm:block">{{ $hint }}</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- Step 1 --}}
    <div x-show="step === 1" class="space-y-4">
        @include('goods-receipt-notes.partials.modern-header-fields', ['grn' => $grn])
        <div class="flex justify-end">
            <button type="button" @click="goStep(2)" class="inline-flex h-11 items-center gap-2 rounded-xl bg-blue-600 px-6 text-sm font-bold text-white hover:bg-blue-700">
                Next: lines
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </button>
        </div>
    </div>

    {{-- Step 2 --}}
    <section x-show="step === 2" x-cloak aria-label="Lines" class="space-y-3">
        <div class="flex flex-wrap items-center gap-2 rounded-2xl bg-white px-4 py-2.5 ring-1 ring-gray-200">
            <span class="text-sm text-gray-600" x-text="headerSummary()"></span>
            <button type="button" @click="goStep(1)" class="text-sm font-semibold text-blue-700 hover:underline">Change</button>
        </div>
        <div class="rounded-2xl bg-white px-4 py-2.5 ring-1 ring-gray-200">
            @include('goods-receipt-notes.partials.line-filters', ['heading' => 'Lines', 'searchId' => 'e'])
        </div>

        <ol class="space-y-2">
            <template x-for="(item, index) in items" :key="index">
                <li :id="'grn-line-row-' + index" x-show="lineMatches(item, index) || selected === index" class="scroll-mt-4 overflow-hidden rounded-xl bg-white ring-1"
                    :class="selected === index ? 'ring-blue-600' : 'ring-gray-200'">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                            :class="{ 'bg-red-100 text-red-800': lineIssue(item).level === 'error', 'bg-amber-100 text-amber-900': lineIssue(item).level === 'warning', 'bg-green-100 text-green-800': lineIssue(item).level === 'ok', 'bg-blue-50 text-blue-800': lineIssue(item).level === 'new' }"
                            x-text="index + 1"></span>
                        <button type="button" @click="selected === index ? (selected = -1) : selectLine(index)" :aria-expanded="selected === index" class="min-w-0 flex-1 text-left">
                            <span class="block truncate text-sm font-semibold text-gray-900" x-text="productLabel(item)"></span>
                            <span class="block truncate text-xs tabular-nums"
                                :class="{ 'text-red-700': lineIssue(item).level === 'error', 'text-amber-800': lineIssue(item).level === 'warning', 'text-blue-700': lineIssue(item).level === 'new', 'text-gray-500': lineIssue(item).level === 'ok' }"
                                x-text="lineSummary(item)"></span>
                        </button>
                        <span class="hidden text-sm font-bold tabular-nums text-gray-900 sm:block" x-text="formatNumber(item.total_value_with_taxes)"></span>
                        <button type="button" @click="selected === index ? (selected = -1) : selectLine(index)"
                            class="rounded-lg px-3 py-1.5 text-sm font-semibold ring-1"
                            :class="selected === index ? 'bg-blue-600 text-white ring-blue-600' : 'text-blue-700 ring-blue-200 hover:bg-blue-50'"
                            x-text="selected === index ? 'Done' : 'Edit'"></button>
                    </div>
                    <div x-show="selected === index" class="border-t border-gray-200 bg-gray-50"
                        @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); nextLine(); }">
                        <div class="space-y-4 p-4">
                            @include('goods-receipt-notes.partials.line-fields', ['sectionsGrid' => 'grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4'])
                        </div>
                        <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 bg-white px-4 py-2.5">
                            <button type="button" x-show="items.length > 1" @click="removeLine(index)" class="h-9 rounded-lg px-3 text-sm font-semibold text-red-700 ring-1 ring-gray-300 hover:bg-red-50">Remove</button>
                            <button type="button" @click="nextLine()" class="ml-auto inline-flex h-9 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700">
                                <span x-text="index === items.length - 1 ? 'Save & add next line' : 'Save & next line'"></span>
                            </button>
                        </div>
                    </div>
                </li>
            </template>
        </ol>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="addLinesAndSelect(1)" class="inline-flex h-10 items-center gap-1.5 rounded-xl border-2 border-dashed border-blue-300 bg-white px-4 text-sm font-bold text-blue-700 hover:bg-blue-50">+ Add line</button>
            <label for="grn-add-many-e" class="sr-only">Add several lines</label>
            <select id="grn-add-many-e" class="h-10 w-36 rounded-xl border-gray-300 text-sm shadow-sm" @change="if ($event.target.value) { addLinesAndSelect($event.target.value); $event.target.value = ''; }">
                <option value="">Add more…</option><option value="5">5 lines</option><option value="10">10 lines</option><option value="25">25 lines</option>
            </select>
            <div class="ml-auto flex gap-2">
                <button type="button" @click="goStep(1)" class="h-11 rounded-xl px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Back</button>
                <button type="button" @click="goStep(3)" class="inline-flex h-11 items-center gap-2 rounded-xl bg-blue-600 px-6 text-sm font-bold text-white hover:bg-blue-700">Next: review</button>
            </div>
        </div>
    </section>

    {{-- Step 3 --}}
    <section x-show="step === 3" x-cloak aria-labelledby="grn-review-heading" class="space-y-4">
        <div class="rounded-2xl bg-white p-4 ring-1 ring-gray-200">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <h3 id="grn-review-heading" class="text-base font-bold text-gray-900">Review before saving</h3>
                <span class="text-sm text-gray-600" x-text="headerSummary()"></span>
            </div>
            <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div class="rounded-xl px-4 py-3 ring-1" :class="blockingCount > 0 ? 'bg-red-50 ring-red-200 text-red-900' : 'bg-green-50 ring-green-200 text-green-900'">
                    <div class="text-xs font-semibold">Errors</div>
                    <div class="text-lg font-bold" x-text="blockingCount > 0 ? blockingCount + ' to fix' : 'None'"></div>
                </div>
                <div class="rounded-xl px-4 py-3 ring-1" :class="issueCounts.warning > 0 ? 'bg-amber-50 ring-amber-200 text-amber-900' : 'bg-gray-50 ring-gray-200 text-gray-800'">
                    <div class="text-xs font-semibold">Warnings (can stay)</div>
                    <div class="text-lg font-bold" x-text="issueCounts.warning || 'None'"></div>
                </div>
                <div class="rounded-xl bg-slate-900 px-4 py-3 text-white">
                    <div class="text-xs font-semibold text-slate-300">Grand total</div>
                    <div class="text-lg font-bold tabular-nums" x-text="formatCurrency(grandTotal)"></div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="text-[11px] font-bold uppercase tracking-wide text-gray-600">
                        <tr class="border-b border-gray-200 [&>th]:px-2 [&>th]:py-2"><th class="text-left">#</th><th class="text-left">Product</th><th class="text-right" x-text="isCartonMode ? 'Ctn' : 'Cases'"></th><th class="text-right">Pcs</th><th class="text-right">Value</th><th class="text-right">Taxes</th><th class="text-right">Total</th><th class="text-left">Check</th></tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in items" :key="'review-' + index">
                            <tr class="border-b border-gray-100 tabular-nums [&>td]:px-2 [&>td]:py-2">
                                <td class="text-gray-500" x-text="index + 1"></td>
                                <td class="font-semibold" x-text="productLabel(item)"></td>
                                <td class="text-right" x-text="isCartonMode ? formatNumber(item.entered_cartons || 0) : formatNumber(item.qty_in_purchase_uom)"></td>
                                <td class="text-right" x-text="formatNumber(item.qty_in_stock_uom)"></td>
                                <td class="text-right" x-text="formatNumber(item.extended_value)"></td>
                                <td class="text-right" x-text="formatNumber((parseFloat(item.total_value_with_taxes) || 0) - (parseFloat(item.discounted_value_before_tax) || 0))"></td>
                                <td class="text-right font-bold" x-text="formatNumber(item.total_value_with_taxes)"></td>
                                <td>
                                    <button type="button" x-show="lineIssue(item).level !== 'ok'" @click="goStep(2); selectLine(index)"
                                        :class="lineIssue(item).level === 'error' ? 'text-red-700' : (lineIssue(item).level === 'warning' ? 'text-amber-800' : 'text-blue-700')"
                                        class="text-left text-xs font-semibold hover:underline" x-text="lineIssue(item).text"></button>
                                    <span x-show="lineIssue(item).level === 'ok'" class="text-xs font-semibold text-green-700">OK</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 ring-1 ring-gray-200">
            <label for="notes" class="mb-1 block text-sm font-bold text-gray-900">Notes</label>
            <textarea id="notes" name="notes" rows="2" placeholder="Anything the store team should know about this delivery"
                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $grn?->notes) }}</textarea>
        </div>

        <div class="flex justify-start">
            <button type="button" @click="goStep(2)" class="h-11 rounded-xl px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">Back to lines</button>
        </div>
    </section>

    @include('goods-receipt-notes.partials.modern-totals-bar', ['submitLabel' => $submitLabel])
</div>

@include('goods-receipt-notes.partials.modern-form-script')
