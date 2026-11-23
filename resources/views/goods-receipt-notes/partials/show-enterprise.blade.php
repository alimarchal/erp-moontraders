{{--
    GRN view for Option H: enterprise object page. Header with key facts, a section bar, then
    General, Items (dense table with every column), Financials, Accounting and History.
    Included by show.blade.php after its data block.
--}}
@php
    $statusDot = ['draft' => 'bg-amber-500 text-amber-700', 'posted' => 'bg-green-600 text-green-700', 'reversed' => 'bg-red-600 text-red-700'];
    $sections = ['general' => 'General', 'items' => 'Items', 'financials' => 'Financials', 'accounting' => 'Accounting', 'history' => 'History'];
    $field = fn (string $label, ?string $value) => ['label' => $label, 'value' => filled($value) ? $value : '—'];
    $general = [
        $field('GRN number', $grn->grn_number),
        $field('Supplier', $grn->supplier->supplier_name),
        $field('Warehouse', $grn->warehouse->warehouse_name),
        $field('Receipt date', $grn->receipt_date?->format('d M Y')),
        $field('Supplier invoice', $grn->supplier_invoice_number),
        $field('Invoice date', $grn->supplier_invoice_date?->format('d M Y')),
        $field('Received by', $grn->receivedBy->name ?? null),
        $field('Verified by', $grn->verifiedBy->name ?? null),
        $field('Type', $grn->is_opening_stock ? 'Opening stock' : 'Purchase'),
        $field('Created', $grn->created_at?->format('d M Y, h:i A')),
        $field('Posted', $grn->posted_at?->format('d M Y, h:i A')),
        $field('Reversed', $grn->reversed_at?->format('d M Y, h:i A')),
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <nav aria-label="Breadcrumb" class="flex gap-1.5 text-sm text-gray-500">
                <a href="{{ route('goods-receipt-notes.index') }}" class="text-blue-700 hover:underline">Goods receipt notes</a><span aria-hidden="true">›</span><span class="text-gray-900">{{ $grn->grn_number }}</span>
            </nav>
            <div class="ml-auto flex flex-wrap items-center gap-2 print:hidden">
                @include('goods-receipt-notes.partials.show-header-actions')
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-3 py-5 sm:px-6 lg:px-8">
        <x-status-message class="mb-3 print:hidden" />

        <section class="rounded-t-xl border border-gray-300 bg-white">
            <div class="flex flex-wrap items-start gap-6 px-5 py-4">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Goods receipt note</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">{{ $grn->grn_number }}</h1>
                    <p class="text-sm text-gray-600">{{ $grn->supplier->supplier_name }} · {{ $grn->warehouse->warehouse_name }}</p>
                </div>
                <dl class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs text-gray-500">Status</dt><dd class="flex items-center gap-1.5 font-semibold {{ \Illuminate\Support\Str::after($statusDot[$grn->status] ?? $statusDot['draft'], ' ') }}"><span class="h-2 w-2 rounded-full {{ \Illuminate\Support\Str::before($statusDot[$grn->status] ?? $statusDot['draft'], ' ') }}"></span>{{ ucfirst($grn->status) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Ledger</dt><dd class="font-semibold">{{ $ledgerStatus ? ucfirst($ledgerStatus) : '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Items</dt><dd class="font-semibold tabular-nums">{{ number_format($grn->items->count()) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Total with taxes</dt><dd class="font-semibold tabular-nums">Rs {{ $rs($itemsTotalWithTax) }}</dd></div>
                </dl>
            </div>
            <nav aria-label="Sections" class="sticky top-0 z-10 flex gap-1 overflow-x-auto border-t border-gray-200 bg-gray-50 px-3 print:hidden">
                @foreach ($sections as $key => $label)
                    <a href="#grn-e-{{ $key }}" class="whitespace-nowrap border-b-2 border-transparent px-3 py-2.5 text-sm font-semibold text-gray-600 hover:border-blue-600 hover:text-blue-700">{{ $label }}</a>
                @endforeach
            </nav>
        </section>

        <div class="space-y-4 rounded-b-xl border border-t-0 border-gray-300 bg-gray-100 p-4">
            <section id="grn-e-general" class="scroll-mt-16 rounded-lg border border-gray-300 bg-white">
                <h2 class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-800">General</h2>
                <dl class="grid grid-cols-1 gap-x-10 p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($general as $row)
                        <div class="grid grid-cols-[9rem_1fr] gap-2 border-b border-dotted border-gray-200 py-1.5"><dt class="text-gray-600">{{ $row['label'] }}:</dt><dd class="font-medium text-gray-900">{{ $row['value'] }}</dd></div>
                    @endforeach
                </dl>
                @if ($grn->notes)
                    <p class="border-t border-gray-200 px-4 py-2 text-sm"><span class="text-gray-600">Notes:</span> {{ $grn->notes }}</p>
                @endif
            </section>

            <section id="grn-e-items" class="scroll-mt-16 overflow-hidden rounded-lg border border-gray-300 bg-white">
                <h2 class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-800">Items ({{ $grn->items->count() }})</h2>
                @if ($grn->items->count() > 0)
                    <div class="max-h-[32rem] overflow-auto">
                        <table class="w-full min-w-[72rem] text-[13px] tabular-nums">
                            <thead class="sticky top-0 bg-gray-100 text-xs font-semibold text-gray-600">
                                <tr class="[&>th]:border-b [&>th]:border-gray-300 [&>th]:px-2 [&>th]:py-1.5 [&>th]:text-right">
                                    <th class="!text-left">Item</th><th class="!text-left">Material</th><th>Qty</th><th>Price</th><th>Gross</th><th>Discount</th><th>FMR</th><th>Net</th><th>Excise</th><th>Sales tax</th><th>Adv. IT</th><th>Other</th>@if ($isUnilever)<th>WHT</th>@endif<th>Received</th><th>Unit cost</th><th>Selling</th><th>Total</th><th class="!text-left">Batch / expiry</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grn->items as $item)
                                    <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }} [&>td]:border-b [&>td]:border-gray-200 [&>td]:px-2 [&>td]:py-1.5 [&>td]:text-right">
                                        <td class="!text-left text-gray-600">{{ str_pad((string) ($item->line_no * 10), 4, '0', STR_PAD_LEFT) }}</td>
                                        <td class="!text-left"><span class="font-semibold text-gray-900">{{ $item->product->product_name ?? '—' }}</span> <span class="text-gray-500">{{ $item->product->product_code ?? '' }}</span></td>
                                        <td>{{ number_format((float) $item->qty_in_purchase_uom, 2) }}</td>
                                        <td>{{ number_format((float) $item->unit_price_per_case, 2) }}</td>
                                        <td>{{ number_format((float) $item->extended_value, 2) }}</td>
                                        <td>{{ number_format((float) $item->discount_value, 2) }}</td>
                                        <td>{{ number_format((float) $item->fmr_allowance, 2) }}</td>
                                        <td>{{ number_format((float) $item->discounted_value_before_tax, 2) }}</td>
                                        <td>{{ number_format((float) $item->excise_duty, 2) }}</td>
                                        <td>{{ number_format((float) $item->sales_tax_value, 2) }}</td>
                                        <td>{{ number_format((float) $item->advance_income_tax, 2) }}</td>
                                        <td>{{ number_format((float) $item->other_charges, 2) }}</td>
                                        @if ($isUnilever)<td>{{ number_format((float) $item->withholding_tax, 2) }}</td>@endif
                                        <td>{{ number_format((float) $item->quantity_received, 2) }}@if ($item->quantity_rejected > 0)<span class="block text-red-700">-{{ number_format((float) $item->quantity_rejected, 2) }}</span>@endif</td>
                                        <td>{{ number_format((float) $item->unit_cost, 2) }}</td>
                                        <td>{{ $item->selling_price ? number_format((float) $item->selling_price, 2) : '—' }}</td>
                                        <td class="font-semibold">{{ number_format((float) ($item->total_value_with_taxes ?? $item->total_cost), 2) }}</td>
                                        <td class="!text-left text-gray-600">{{ collect([$item->batch_number, $item->lot_number, $item->expiry_date ? \Carbon\Carbon::parse($item->expiry_date)->format('d M Y') : null, $item->is_promotional ? 'promo' : null])->filter()->implode(' · ') ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="sticky bottom-0 bg-gray-100 font-semibold">
                                <tr class="[&>td]:border-t [&>td]:border-gray-300 [&>td]:px-2 [&>td]:py-1.5 [&>td]:text-right">
                                    <td colspan="2" class="!text-left">Total</td>
                                    <td>{{ $rs($totals['qty_in_purchase_uom']) }}</td><td></td>
                                    <td>{{ $rs($totals['extended_value']) }}</td><td>{{ $rs($totals['discount_value']) }}</td><td>{{ $rs($totals['fmr_allowance']) }}</td>
                                    <td>{{ $rs($totals['discounted_value_before_tax']) }}</td><td>{{ $rs($totals['excise_duty']) }}</td><td>{{ $rs($totals['sales_tax_value']) }}</td>
                                    <td>{{ $rs($totals['advance_income_tax']) }}</td><td>{{ $rs($totals['other_charges']) }}</td>@if ($isUnilever)<td>{{ $rs($totals['withholding_tax']) }}</td>@endif
                                    <td>{{ $rs($totals['quantity_received']) }}</td><td></td><td></td><td>{{ $rs($totals['total_value_with_taxes']) }}</td><td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <p class="px-4 py-8 text-center text-sm text-gray-600">No items on this GRN</p>
                @endif
            </section>

            <section id="grn-e-financials" class="scroll-mt-16 rounded-lg border border-gray-300 bg-white">
                <h2 class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-800">Financials</h2>
                <dl class="grid grid-cols-1 gap-x-10 p-4 text-sm tabular-nums sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (array_filter(['Gross amount' => $totals['extended_value'], 'Discount' => $totals['discount_value'], 'FMR allowance' => $totals['fmr_allowance'], 'Net before tax' => $totals['discounted_value_before_tax'], 'Sales tax' => $totals['sales_tax_value'], 'Excise duty' => $totals['excise_duty'], 'Advance income tax' => $totals['advance_income_tax'], 'Other charges' => $totals['other_charges'], 'Withholding tax' => $isUnilever ? $totals['withholding_tax'] : null], fn ($value) => $value !== null) as $label => $value)
                        <div class="flex justify-between border-b border-dotted border-gray-200 py-1.5"><dt class="text-gray-600">{{ $label }}</dt><dd>{{ $rs($value) }}</dd></div>
                    @endforeach
                    <div class="flex justify-between py-1.5 font-semibold sm:col-span-2 lg:col-span-3"><dt>Total with taxes</dt><dd>Rs {{ $rs($itemsTotalWithTax) }}</dd></div>
                </dl>
            </section>

            <section id="grn-e-accounting" class="scroll-mt-16 space-y-4">
                @include('goods-receipt-notes.partials.show-corrections')
                <div id="grn-e-history" class="grid scroll-mt-16 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @include('goods-receipt-notes.partials.show-side-cards')
                </div>
            </section>
        </div>
    </div>

    @include('goods-receipt-notes.partials.show-actions')
</x-app-layout>
