{{--
    The totals bar at the bottom of the Option A and Option B GRN forms: the money, the checks
    still open, and the save button. It sticks to the bottom of the window while the lines scroll.
--}}
<div class="sticky bottom-3 z-20 rounded-2xl bg-slate-900/90 px-4 py-3 text-white shadow-[0_12px_40px_rgba(15,23,42,0.28)] ring-1 ring-white/10 backdrop-blur-xl backdrop-saturate-150 supports-[not(backdrop-filter:blur(1px))]:bg-slate-900 motion-reduce:backdrop-blur-none">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
        <dl class="flex flex-wrap gap-x-6 gap-y-1 tabular-nums">
            <div><dt class="text-[11px] font-semibold text-slate-400" x-text="isCartonMode ? 'Cartons' : 'Cases'"></dt><dd class="font-bold" x-text="formatNumber(totalCases())"></dd></div>
            <div><dt class="text-[11px] font-semibold text-slate-400">Pieces</dt><dd class="font-bold" x-text="formatNumber(sumOf('qty_in_stock_uom'))"></dd></div>
            <div><dt class="text-[11px] font-semibold text-slate-400">Value</dt><dd class="font-bold" x-text="formatNumber(sumOf('extended_value'))"></dd></div>
            <div><dt class="text-[11px] font-semibold text-slate-400">Discount + FMR</dt><dd class="font-bold text-red-300" x-text="'− ' + formatNumber(sumOf('discount_value') + sumOf('fmr_allowance'))"></dd></div>
            <div><dt class="text-[11px] font-semibold text-slate-400">Taxes &amp; charges</dt><dd class="font-bold" x-text="formatNumber(sumOf('excise_duty') + sumOf('sales_tax_value') + sumOf('advance_income_tax') + sumOf('other_charges') + sumOf('withholding_tax'))"></dd></div>
        </dl>

        <button type="button" @click="jumpToFirstProblem()" class="inline-flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-800"
            :class="blockingCount > 0 ? 'text-red-300' : (issueCounts.warning > 0 ? 'text-amber-200' : 'text-emerald-300')" aria-live="polite">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v4M12 16h.01" /></svg>
            <span x-text="blockingCount > 0
                ? 'Fix ' + blockingCount + (blockingCount === 1 ? ' line' : ' lines') + ' to save' + (issueCounts.warning > 0 ? ' · ' + issueCounts.warning + ' warnings can stay' : '')
                : (issueCounts.warning > 0 ? issueCounts.warning + (issueCounts.warning === 1 ? ' warning' : ' warnings') + ' can stay' : 'All lines look right')"></span>
        </button>

        <div class="ml-auto flex items-center gap-4">
            <div class="text-right">
                <div class="text-[11px] font-semibold text-slate-400">Grand total · <span x-text="items.length"></span> lines</div>
                <div class="text-xl font-bold tabular-nums" x-text="formatCurrency(grandTotal)"></div>
            </div>
            <button type="submit"
                :class="blockingCount > 0 ? 'bg-slate-700 text-slate-300' : 'bg-blue-500 text-white hover:bg-blue-400'"
                class="inline-flex h-11 items-center rounded-xl px-6 text-sm font-bold transition-transform duration-100 active:scale-[0.97] focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 focus:ring-offset-slate-900">
                {{ $submitLabel }}
            </button>
        </div>
    </div>
</div>
