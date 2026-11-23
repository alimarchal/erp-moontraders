{{--
    GRN view for Option G: a large summary on top and one card per line, easy to read and tap on
    a tablet or phone. Search filters the cards. Ledger, corrections, timeline and related links
    follow. Included by show.blade.php after its data block.
--}}
@php
    $statusChip = ['draft' => 'bg-amber-100 text-amber-900', 'posted' => 'bg-green-100 text-green-800', 'reversed' => 'bg-red-100 text-red-800'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <div class="min-w-0">
                <nav aria-label="Breadcrumb" class="flex gap-1.5 text-xs text-gray-500">
                    <a href="{{ route('goods-receipt-notes.index') }}" class="hover:underline">Goods receipt notes</a><span aria-hidden="true">/</span><span>{{ $grn->grn_number }}</span>
                </nav>
                <h1 class="mt-0.5 text-xl font-semibold tracking-tight text-gray-900">GRN {{ $grn->grn_number }}</h1>
            </div>
            <div class="ml-auto flex flex-wrap items-center gap-2 print:hidden">
                @include('goods-receipt-notes.partials.show-header-actions')
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-5 px-3 py-6 sm:px-6 lg:px-8" x-data="{ find: '' }">
        <x-status-message class="print:hidden" />

        <section aria-label="Summary" class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 to-slate-800 p-6 text-white shadow-[0_24px_48px_rgba(15,23,42,0.18)] sm:p-8">
            <div class="flex flex-wrap items-start gap-6">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $statusChip[$grn->status] ?? $statusChip['draft'] }}">{{ ucfirst($grn->status) }}</span>
                        @if ($ledgerStatus)<span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold">Ledger {{ $ledgerStatus }}</span>@endif
                        @if ($grn->is_opening_stock)<span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold">Opening stock</span>@endif
                    </div>
                    <p class="mt-3 text-2xl font-semibold tracking-tight">{{ $grn->supplier->supplier_name }}</p>
                    <p class="text-sm text-slate-300">{{ $grn->warehouse->warehouse_name }} · received {{ $grn->receipt_date?->format('d M Y') }} · invoice {{ $grn->supplier_invoice_number ?? '—' }} · by {{ $grn->receivedBy->name ?? 'N/A' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total with taxes</p>
                    <p class="text-4xl font-semibold tabular-nums tracking-tight">Rs {{ $rs($itemsTotalWithTax) }}</p>
                </div>
            </div>
            <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10"><dt class="text-xs text-slate-400">Lines</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($grn->items->count()) }}</dd></div>
                <div class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10"><dt class="text-xs text-slate-400">Pieces received</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($totals['quantity_received'], 2) }}</dd></div>
                <div class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10"><dt class="text-xs text-slate-400">Before tax</dt><dd class="text-lg font-semibold tabular-nums">{{ $rs($totals['discounted_value_before_tax']) }}</dd></div>
                <div class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10"><dt class="text-xs text-slate-400">Taxes &amp; charges</dt><dd class="text-lg font-semibold tabular-nums">{{ $rs($taxTotal) }}</dd></div>
            </dl>
        </section>

        @if ($isDraft)
            <p role="status" class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200"><b>Draft — stock has not moved yet.</b> Check the lines, then post to inventory.</p>
        @endif

        <section aria-labelledby="grn-cards-heading" class="space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <h2 id="grn-cards-heading" class="text-lg font-semibold tracking-tight text-gray-900">Items received <span class="text-gray-500">· {{ $grn->items->count() }}</span></h2>
                @if ($grn->items->count() > 6)
                    <label for="grn-cards-find" class="sr-only">Find an item</label>
                    <input id="grn-cards-find" type="search" x-model="find" placeholder="Find product or line no." class="h-9 w-full rounded-xl border-gray-300 text-sm shadow-sm sm:ml-auto sm:w-64 print:hidden">
                @endif
            </div>

            @if ($grn->items->count() > 0)
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($grn->items as $item)
                        @php($lineTaxes = (float) $item->excise_duty + (float) $item->sales_tax_value + (float) $item->advance_income_tax + (float) $item->other_charges + ($isUnilever ? (float) $item->withholding_tax : 0))
                        <article x-show="!find || '{{ $item->line_no }}' === find.trim() || {{ Js::from(\Illuminate\Support\Str::lower(($item->product->product_code ?? '').' '.($item->product->product_name ?? ''))) }}.includes(find.trim().toLowerCase())"
                            class="flex flex-col rounded-2xl bg-white p-4 ring-1 ring-gray-200 transition-shadow hover:shadow-md">
                            <div class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-xs font-bold text-gray-700">{{ $item->line_no }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold leading-snug text-gray-900">{{ $item->product->product_name ?? '—' }}</p>
                                    <p class="text-xs text-gray-500">{{ $item->product->product_code ?? '' }}</p>
                                </div>
                                <p class="text-right text-base font-semibold tabular-nums text-gray-900">{{ number_format((float) ($item->total_value_with_taxes ?? $item->total_cost), 2) }}</p>
                            </div>
                            <dl class="mt-3 grid grid-cols-3 gap-2 text-xs tabular-nums">
                                <div class="rounded-xl bg-gray-50 p-2"><dt class="text-gray-500">Received</dt><dd class="font-semibold text-gray-900">{{ number_format((float) $item->quantity_received, 2) }}</dd></div>
                                <div class="rounded-xl bg-gray-50 p-2"><dt class="text-gray-500">Value</dt><dd class="font-semibold text-gray-900">{{ number_format((float) $item->extended_value, 2) }}</dd></div>
                                <div class="rounded-xl bg-gray-50 p-2"><dt class="text-gray-500">Taxes</dt><dd class="font-semibold text-gray-900">{{ number_format($lineTaxes, 2) }}</dd></div>
                                <div class="rounded-xl bg-gray-50 p-2"><dt class="text-gray-500">Discount</dt><dd class="font-semibold text-red-700">{{ number_format((float) $item->discount_value + (float) $item->fmr_allowance, 2) }}</dd></div>
                                <div class="rounded-xl bg-gray-50 p-2"><dt class="text-gray-500">Unit cost</dt><dd class="font-semibold text-gray-900">{{ number_format((float) $item->unit_cost, 2) }}</dd></div>
                                <div class="rounded-xl bg-gray-50 p-2"><dt class="text-gray-500">Selling</dt><dd class="font-semibold text-gray-900">{{ $item->selling_price ? number_format((float) $item->selling_price, 2) : '—' }}</dd></div>
                            </dl>
                            @if ($item->batch_number || $item->lot_number || $item->expiry_date || $item->is_promotional || $item->quantity_rejected > 0)
                                <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] font-semibold">
                                    @if ($item->batch_number)<span class="rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-800">Batch {{ $item->batch_number }}</span>@endif
                                    @if ($item->lot_number)<span class="rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-800">Lot {{ $item->lot_number }}</span>@endif
                                    @if ($item->expiry_date)<span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-700">Exp {{ \Carbon\Carbon::parse($item->expiry_date)->format('d M Y') }}</span>@endif
                                    @if ($item->is_promotional)<span class="rounded-full bg-orange-50 px-2 py-0.5 text-orange-800">Promotional</span>@endif
                                    @if ($item->quantity_rejected > 0)<span class="rounded-full bg-red-50 px-2 py-0.5 text-red-800">{{ number_format((float) $item->quantity_rejected, 2) }} rejected</span>@endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <p class="rounded-2xl bg-white py-10 text-center text-sm font-semibold text-gray-600 ring-1 ring-gray-200">No items on this GRN</p>
            @endif
        </section>

        @if ($grn->notes)
            <section class="rounded-2xl bg-white p-5 ring-1 ring-gray-200">
                <h2 class="mb-1 text-base font-semibold text-gray-900">Notes</h2>
                <p class="text-sm text-gray-700">Notes: {{ $grn->notes }}</p>
            </section>
        @endif

        @include('goods-receipt-notes.partials.show-corrections')

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @include('goods-receipt-notes.partials.show-side-cards')
        </div>
    </div>

    @include('goods-receipt-notes.partials.show-actions')
</x-app-layout>
