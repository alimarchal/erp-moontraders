{{--
    GRN view for Option F: the GRN as a paper document (letterhead, meta block, invoice rows,
    totals bottom-right, remarks and signature lines), with the ledger, corrections, timeline and
    related links below it. Included by show.blade.php after its data block.
--}}
@php
    $stamp = ['draft' => 'text-amber-700 ring-amber-400', 'posted' => 'text-emerald-700 ring-emerald-500', 'reversed' => 'text-red-700 ring-red-500'];
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

    <div class="mx-auto max-w-7xl space-y-5 px-3 py-6 sm:px-6 lg:px-8">
        <x-status-message class="print:hidden" />

        <article class="relative mx-auto max-w-5xl overflow-hidden rounded-3xl bg-white shadow-[0_1px_2px_rgba(15,23,42,0.06),0_24px_48px_rgba(15,23,42,0.08)] ring-1 ring-gray-200 print:max-w-none print:rounded-none print:shadow-none print:ring-0">
            <span class="pointer-events-none absolute right-8 top-32 hidden -rotate-12 rounded-lg px-4 py-1.5 text-2xl font-black uppercase tracking-[0.2em] opacity-60 ring-4 sm:block {{ $stamp[$grn->status] ?? $stamp['draft'] }}" aria-hidden="true">{{ $grn->status }}</span>

            <header class="flex flex-wrap items-start justify-between gap-6 border-b border-dashed border-gray-300 px-6 py-6 sm:px-10">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">{{ config('app.name') }}</p>
                    <h2 class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">Goods receipt note</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $grn->grn_number }} · <span class="font-semibold">{{ ucfirst($grn->status) }}</span>@if ($ledgerStatus) · ledger {{ $ledgerStatus }}@endif @if ($grn->is_opening_stock) · opening stock @endif</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total with taxes</p>
                    <p class="text-3xl font-semibold tabular-nums tracking-tight text-gray-900">Rs {{ $rs($itemsTotalWithTax) }}</p>
                </div>
            </header>

            <dl class="grid grid-cols-2 gap-x-8 gap-y-4 px-6 py-6 text-sm sm:grid-cols-3 sm:px-10">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Supplier</dt><dd class="mt-0.5 font-semibold">{{ $grn->supplier->supplier_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Warehouse</dt><dd class="mt-0.5 font-semibold">{{ $grn->warehouse->warehouse_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Receipt date</dt><dd class="mt-0.5 font-semibold">{{ $grn->receipt_date?->format('d M Y') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Supplier invoice</dt><dd class="mt-0.5 font-semibold">{{ $grn->supplier_invoice_number ?? '—' }}<span class="block text-xs font-normal text-gray-500">{{ $grn->supplier_invoice_date ? 'dated '.$grn->supplier_invoice_date->format('d M Y') : 'no invoice date' }}</span></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Received by</dt><dd class="mt-0.5 font-semibold">{{ $grn->receivedBy->name ?? 'N/A' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $grn->posted_at ? 'Posted' : 'Created' }}</dt><dd class="mt-0.5 font-semibold">{{ ($grn->posted_at ?? $grn->created_at)?->format('d M Y, h:i A') }}</dd></div>
            </dl>

            <div class="overflow-x-auto px-6 sm:px-10">
                @if ($grn->items->count() > 0)
                    <table class="w-full min-w-[44rem] text-sm">
                        <thead class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <tr class="border-b-2 border-gray-900 [&>th]:px-2 [&>th]:py-2">
                                <th class="w-10 text-left">#</th><th class="text-left">Description</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Amount</th><th class="text-right">Less</th><th class="text-right">Taxes</th><th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grn->items as $item)
                                @php($lineTaxes = (float) $item->excise_duty + (float) $item->sales_tax_value + (float) $item->advance_income_tax + (float) $item->other_charges + ($isUnilever ? (float) $item->withholding_tax : 0))
                                <tr class="border-b border-gray-100 align-top tabular-nums [&>td]:px-2 [&>td]:py-2.5">
                                    <td class="text-gray-500">{{ $item->line_no }}</td>
                                    <td>
                                        <span class="block font-semibold text-gray-900">{{ $item->product->product_name ?? '—' }}</span>
                                        <span class="block text-xs text-gray-500">
                                            {{ $item->product->product_code ?? '' }} · {{ number_format((float) $item->quantity_received, 2) }} pcs received
                                            @if ($item->batch_number) · batch {{ $item->batch_number }}@endif
                                            @if ($item->expiry_date) · exp {{ \Carbon\Carbon::parse($item->expiry_date)->format('d M Y') }}@endif
                                            @if ($item->is_promotional) · <span class="font-semibold text-orange-700">promotional</span>@endif
                                            @if ($item->quantity_rejected > 0) · <span class="text-red-700">{{ number_format((float) $item->quantity_rejected, 2) }} rejected</span>@endif
                                        </span>
                                    </td>
                                    <td class="text-right">{{ number_format((float) $item->qty_in_purchase_uom, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $item->unit_price_per_case, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $item->extended_value, 2) }}</td>
                                    <td class="text-right text-red-700">{{ number_format((float) $item->discount_value + (float) $item->fmr_allowance, 2) }}</td>
                                    <td class="text-right">{{ number_format($lineTaxes, 2) }}</td>
                                    <td class="text-right font-semibold text-gray-900">{{ number_format((float) ($item->total_value_with_taxes ?? $item->total_cost), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="py-10 text-center text-sm font-semibold text-gray-600">No items on this GRN</p>
                @endif
            </div>

            <div class="grid gap-8 px-6 py-8 sm:px-10 md:grid-cols-[1fr_20rem]">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Remarks</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $grn->notes ?: 'No remarks.' }}</p>
                    <div class="mt-12 grid grid-cols-3 gap-6 text-center text-xs text-gray-500">
                        <span class="border-t border-gray-400 pt-1">Received by</span>
                        <span class="border-t border-gray-400 pt-1">Checked by</span>
                        <span class="border-t border-gray-400 pt-1">Store keeper</span>
                    </div>
                </div>
                <dl class="space-y-1.5 text-sm tabular-nums">
                    <div class="flex justify-between"><dt class="text-gray-600">Amount</dt><dd>{{ $rs($totals['extended_value']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Discount</dt><dd class="text-red-700">− {{ $rs($totals['discount_value']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">FMR allowance</dt><dd class="text-red-700">− {{ $rs($totals['fmr_allowance']) }}</dd></div>
                    <div class="flex justify-between border-t border-gray-200 pt-1.5 font-semibold"><dt>Before tax</dt><dd>{{ $rs($totals['discounted_value_before_tax']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Sales tax</dt><dd>{{ $rs($totals['sales_tax_value']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Excise duty</dt><dd>{{ $rs($totals['excise_duty']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Advance income tax</dt><dd>{{ $rs($totals['advance_income_tax']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Other charges</dt><dd>{{ $rs($totals['other_charges']) }}</dd></div>
                    @if ($isUnilever)
                        <div class="flex justify-between"><dt class="text-gray-600">Withholding tax</dt><dd>{{ $rs($totals['withholding_tax']) }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t-2 border-gray-900 pt-2 text-base font-semibold"><dt>Grand total</dt><dd>Rs {{ $rs($totals['total_value_with_taxes']) }}</dd></div>
                </dl>
            </div>
        </article>

        <div class="mx-auto max-w-5xl space-y-4 print:hidden">
            @include('goods-receipt-notes.partials.show-corrections')
            <div class="grid gap-4 md:grid-cols-2">
                @include('goods-receipt-notes.partials.show-side-cards')
            </div>
        </div>
    </div>

    @include('goods-receipt-notes.partials.show-actions')
</x-app-layout>
