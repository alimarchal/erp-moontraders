{{--
    GRN view for the Option A and Option B layouts. It is included by show.blade.php after its
    data block, so $grn, $totals, $ledger, $ledgerMatches, $timeline, $links and the rest are the
    classic page's own values. Same actions, password confirmations and sections as the classic view.
--}}
@php
    $statusChip = ['draft' => 'bg-amber-100 text-amber-900', 'posted' => 'bg-green-100 text-green-800', 'reversed' => 'bg-red-100 text-red-800'];
    $lineRows = $grn->items->map(function ($item) use ($isUnilever) {
        $taxes = (float) $item->excise_duty + (float) $item->sales_tax_value + (float) $item->advance_income_tax + (float) $item->other_charges + ($isUnilever ? (float) $item->withholding_tax : 0);

        return [
            'item' => $item,
            'taxes' => $taxes,
            'details' => array_filter([
                'Code' => $item->product->product_code ?? null,
                'Batch' => $item->batch_number,
                'Lot' => $item->lot_number,
                'Manufactured' => $item->manufacturing_date ? \Carbon\Carbon::parse($item->manufacturing_date)->format('d M Y') : null,
                'Expiry' => $item->expiry_date ? \Carbon\Carbon::parse($item->expiry_date)->format('d M Y') : null,
                'Must sell before' => $item->must_sell_before ? \Carbon\Carbon::parse($item->must_sell_before)->format('d M Y') : null,
                'Storage' => $item->storage_location,
                'Quality' => $item->quality_status ? ucfirst($item->quality_status) : null,
                'Selling strategy' => $item->selling_strategy ? strtoupper(str_replace('_', ' ', $item->selling_strategy)) : null,
                'Promotional' => $item->is_promotional ? 'Yes'.($item->promotionalCampaign ? ' · '.$item->promotionalCampaign->campaign_name : '').($item->promotional_price ? ' · Rs '.number_format((float) $item->promotional_price, 2) : '') : null,
                'Cartons' => $item->product?->formatCartons($item->quantity_received),
                'Rejected' => $item->quantity_rejected > 0 ? number_format((float) $item->quantity_rejected, 2) : null,
            ], fn ($value) => filled($value)),
        ];
    });
    /*
     * The price, deduction and tax columns in the supplier's order (Settings → Suppliers → Edit).
     * Value follows the price; Before tax follows the last of price, discount and FMR. A column the
     * supplier hid still shows when this GRN has an amount in it. Layout only: every figure is the line's own.
     */
    $columnTotals = $totals + ['unit_price_per_case' => (float) $grn->items->sum('unit_price_per_case')];
    $supplierColumns = collect($grn->supplier->grnLineColumns())
        ->filter(fn ($column) => $column['key'] !== 'withholding_tax' || $isUnilever)
        ->filter(fn ($column) => $column['visible'] || abs($columnTotals[$column['key']] ?? 0) > 0)
        ->values();
    $columnSpec = [
        'unit_price_per_case' => ['UP / case', "view === 'all'"],
        'discount_value' => ['Discount', "view !== 'tax'"],
        'fmr_allowance' => ['FMR', "view === 'all'"],
        'excise_duty' => ['Excise', "view !== 'summary'"],
        'sales_tax_value' => ['Sales tax', "view !== 'summary'"],
        'advance_income_tax' => ['Adv. IT', "view !== 'summary'"],
        'other_charges' => ['Other', "view !== 'summary'"],
        'withholding_tax' => ['WHT', "view !== 'summary'"],
    ];
    $lastDeduction = $supplierColumns->keys()->filter(fn ($at) => in_array($supplierColumns[$at]['key'], ['unit_price_per_case', 'discount_value', 'fmr_allowance'], true))->max() ?? -1;
    $amountCols = [];
    if ($lastDeduction < 0) {
        $amountCols[] = ['label' => 'Value', 'show' => "view !== 'tax'", 'field' => 'extended_value'];
        $amountCols[] = ['label' => 'Before tax', 'show' => "view !== 'summary'", 'field' => 'discounted_value_before_tax'];
    }
    foreach ($supplierColumns as $at => $column) {
        [$label, $show] = $columnSpec[$column['key']];
        $amountCols[] = ['label' => $label, 'show' => $show, 'field' => $column['key']];
        if ($column['key'] === 'unit_price_per_case') {
            $amountCols[] = ['label' => 'Value', 'show' => "view !== 'tax'", 'field' => 'extended_value'];
        }
        if ($at === $lastDeduction) {
            if (! collect($amountCols)->contains('field', 'extended_value')) {
                $amountCols[] = ['label' => 'Value', 'show' => "view !== 'tax'", 'field' => 'extended_value'];
            }
            $amountCols[] = ['label' => 'Before tax', 'show' => "view !== 'summary'", 'field' => 'discounted_value_before_tax'];
        }
    }

    // The totals strip follows the same order.
    $breakdownSpec = [
        'extended_value' => ['Value', 1, ''],
        'discount_value' => ['Discount', -1, 'text-red-700'],
        'fmr_allowance' => ['FMR', -1, 'text-red-700'],
        'discounted_value_before_tax' => ['Before tax', 1, ''],
        'excise_duty' => ['Excise', 1, ''],
        'sales_tax_value' => ['Sales tax', 1, ''],
        'advance_income_tax' => ['Adv. income tax', 1, ''],
        'other_charges' => ['Other charges', 1, ''],
        'withholding_tax' => ['WHT', 1, ''],
    ];
    $breakdown = [];
    foreach ($amountCols as $col) {
        if (isset($breakdownSpec[$col['field']])) {
            [$label, $sign, $tone] = $breakdownSpec[$col['field']];
            $breakdown[$label] = [$sign * $totals[$col['field']], $tone];
        }
    }
    $totalPcs = (float) $grn->items->sum('qty_in_stock_uom');
    $itemsGap = abs($itemsTotalWithTax - (float) $grn->grand_total);
    $today0 = now()->startOfDay();
    $noQty = $grn->items->filter(fn ($i) => (float) $i->qty_in_purchase_uom <= 0);
    $noPrice = $grn->items->filter(fn ($i) => ! $i->is_promotional && (float) $i->unit_price_per_case <= 0);
    $shortReceived = $grn->items->filter(fn ($i) => abs((float) $i->quantity_received - (float) $i->qty_in_stock_uom) > 0.001 || (float) $i->quantity_rejected > 0);
    $expired = $grn->items->filter(fn ($i) => $i->expiry_date && \Carbon\Carbon::parse($i->expiry_date)->lt($today0));
    $expiringSoon = $grn->items->filter(fn ($i) => $i->expiry_date && ! \Carbon\Carbon::parse($i->expiry_date)->lt($today0) && \Carbon\Carbon::parse($i->expiry_date)->lte($today0->copy()->addDays(90)));
    $noBatch = $grn->items->filter(fn ($i) => blank($i->batch_number));
    $lineNos = fn ($lines) => 'line '.$lines->pluck('line_no')->take(6)->implode(', ').($lines->count() > 6 ? ' and '.($lines->count() - 6).' more' : '');
    // level: ok | warn | error
    $checks = [
        ['Items to be received', $grn->items->count() > 0 ? 'ok' : 'error', $grn->items->count() > 0 ? number_format($totals['qty_in_purchase_uom'], 2).' cases · '.number_format($totalPcs, 2).' pcs on '.$grn->items->count().' lines' : 'There are no items on this GRN'],
        ['Quantity on every line', $noQty->isEmpty() ? 'ok' : 'error', $noQty->isEmpty() ? 'Every line has a quantity' : 'No quantity on '.$lineNos($noQty)],
        ['Price on every line', $noPrice->isEmpty() ? 'ok' : 'warn', $noPrice->isEmpty() ? 'Every line has a price per case (promotional lines excepted)' : 'No price on '.$lineNos($noPrice)],
        ['Received equals invoiced', $shortReceived->isEmpty() ? 'ok' : 'warn', $shortReceived->isEmpty() ? 'Pieces received match pieces invoiced, nothing rejected' : 'Received differs from invoiced, or rejected pieces, on '.$lineNos($shortReceived)],
        ['Totals add up', $itemsGap < 1 ? 'ok' : 'error', $itemsGap < 1 ? 'Line totals match the grand total of Rs '.$rs($grn->grand_total) : 'Line totals Rs '.$rs($itemsTotalWithTax).' differ from the GRN grand total Rs '.$rs($grn->grand_total)],
        ['Supplier invoice', filled($grn->supplier_invoice_number) && $grn->supplier_invoice_date ? 'ok' : 'warn', filled($grn->supplier_invoice_number) ? ($grn->supplier_invoice_date ? 'Invoice '.$grn->supplier_invoice_number.' dated '.$grn->supplier_invoice_date->format('d M Y') : 'Invoice '.$grn->supplier_invoice_number.' has no date') : 'No supplier invoice number entered'],
        ['Expiry dates', $expired->isNotEmpty() ? 'error' : ($expiringSoon->isNotEmpty() ? 'warn' : 'ok'), $expired->isNotEmpty() ? 'Already expired: '.$lineNos($expired) : ($expiringSoon->isNotEmpty() ? 'Expires within 90 days: '.$lineNos($expiringSoon) : 'No expired or soon-to-expire stock')],
        ['Batch numbers', $noBatch->isEmpty() ? 'ok' : 'warn', $noBatch->isEmpty() ? 'Every line has a batch number' : 'No batch number on '.$lineNos($noBatch)],
        ['Supplier ledger', $ledgerStatus ? 'ok' : 'warn', $ledgerStatus ? 'Invoice entry is '.$ledgerStatus.': Rs '.$rs($ledger->invoice_amount) : 'No supplier ledger entry yet; it is raised when the GRN is posted'],
    ];
    $checkErrors = collect($checks)->where(1, 'error')->count();
    $checkWarns = collect($checks)->where(1, 'warn')->count();
    $cardShadow = 'shadow-[0_1px_2px_rgba(15,23,42,0.06),0_12px_32px_rgba(15,23,42,0.09)] print:shadow-none';
    $th = 'whitespace-nowrap px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wide text-gray-600';
    $td = 'whitespace-nowrap px-3 py-2 text-right tabular-nums';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-4">
            <div class="min-w-0">
                <nav aria-label="Breadcrumb" class="flex gap-1.5 text-xs text-gray-500">
                    <a href="{{ route('goods-receipt-notes.index') }}" class="hover:underline">Goods receipt notes</a><span aria-hidden="true">/</span><span>{{ $grn->grn_number }}</span>
                </nav>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-bold text-gray-900">GRN {{ $grn->grn_number }}</h1>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $statusChip[$grn->status] ?? $statusChip['draft'] }}">{{ ucfirst($grn->status) }}</span>
                    @if ($ledgerStatus)
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ledgerStatus === 'posted' ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-900' }}">Ledger {{ $ledgerStatus }}</span>
                    @endif
                    @if ($grn->is_opening_stock)
                        <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-800">Opening stock</span>
                    @endif
                </div>
                <p class="mt-0.5 text-sm text-gray-600">{{ $grn->supplier->supplier_name }} · {{ $grn->warehouse->warehouse_name }} · received {{ $grn->receipt_date?->format('d M Y') }}</p>
            </div>
            <div class="ml-auto flex flex-wrap items-center gap-2 print:hidden">
                @include('goods-receipt-notes.partials.show-header-actions')
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4 px-3 py-5 sm:px-6 lg:px-8">
        <x-status-message class="print:hidden" />

        <div role="status" class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl px-4 py-3 text-sm ring-1 {{ $isPosted ? 'bg-green-50 text-green-900 ring-green-200' : ($isDraft ? 'bg-amber-50 text-amber-900 ring-amber-200' : 'bg-red-50 text-red-900 ring-red-200') }}">
            @if ($isPosted)
                <b>Posted to inventory</b><span>{{ $grn->posted_at ? 'on '.$grn->posted_at->format('d M Y \a\t h:i A').'.' : '' }} Stock and the supplier ledger were updated.</span>
            @elseif ($isDraft)
                <b>Draft — stock has not moved yet.</b><span>Check the lines, then post to inventory. Everything can still be edited.</span>
            @else
                <b>Reversed</b><span>{{ $grn->reversed_at ? 'on '.$grn->reversed_at->format('d M Y \a\t h:i A') : '' }}{{ $grn->reversedBy ? ' by '.$grn->reversedBy->name : '' }}. The received stock was taken back out.</span>
            @endif
        </div>

        <div class="flex flex-col gap-4 lg:flex-row">
            <section aria-label="Receipt details" class="grid flex-1 grid-cols-2 gap-4 rounded-2xl bg-white p-5 ring-1 ring-gray-200 {{ $cardShadow }} sm:grid-cols-3 xl:grid-cols-6">
                <div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Supplier</div><div class="mt-1 text-sm font-semibold">{{ $grn->supplier->supplier_name }}</div></div>
                <div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Warehouse</div><div class="mt-1 text-sm font-semibold">{{ $grn->warehouse->warehouse_name }}</div></div>
                <div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Receipt date</div><div class="mt-1 text-sm font-semibold">{{ $grn->receipt_date?->format('d M Y') }}</div></div>
                <div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Supplier invoice</div><div class="mt-1 text-sm font-semibold">{{ $grn->supplier_invoice_number ?? '—' }}</div><div class="text-xs text-gray-500">{{ $grn->supplier_invoice_date ? 'dated '.$grn->supplier_invoice_date->format('d M Y') : 'no invoice date' }}</div></div>
                <div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Received by</div><div class="mt-1 text-sm font-semibold">{{ $grn->receivedBy->name ?? 'N/A' }}</div>@if ($grn->verifiedBy)<div class="text-xs text-gray-500">verified by {{ $grn->verifiedBy->name }}</div>@endif</div>
                <div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Lines</div><div class="mt-1 text-sm font-semibold">{{ number_format($grn->items->count()) }} · {{ number_format($totals['qty_in_purchase_uom'], 2) }} cases</div><div class="text-xs text-gray-500">{{ number_format($totalPcs, 2) }} pcs · {{ number_format($totals['quantity_received'], 2) }} received</div>@if ($rejected > 0 || $promoLines)<div class="text-xs text-gray-500">{{ $rejected > 0 ? number_format($rejected, 2).' rejected' : '' }}{{ $rejected > 0 && $promoLines ? ' · ' : '' }}{{ $promoLines ? $promoLines.' promotional' : '' }}</div>@endif</div>
            </section>
            <section aria-label="Grand total" class="rounded-2xl bg-slate-900 p-5 text-white shadow-[0_12px_32px_rgba(15,23,42,0.25)] lg:w-80 print:shadow-none">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Grand total with taxes</div>
                <div class="mt-1 text-3xl font-bold tabular-nums">Rs {{ $rs($itemsTotalWithTax) }}</div>
                <div class="mt-1 text-sm tabular-nums text-slate-300">before tax Rs {{ $rs($totals['discounted_value_before_tax']) }} · taxes Rs {{ $rs($taxTotal) }}</div>
            </section>
        </div>

        <section aria-label="Total breakdown" class="grid grid-cols-2 gap-3 rounded-2xl bg-white px-5 py-4 ring-1 ring-gray-200 {{ $cardShadow }} sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-9">
            @foreach ($breakdown as $label => [$value, $tone])
                <div><div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</div><div class="mt-1 text-sm font-bold tabular-nums {{ $tone }}">{{ $value < 0 ? '− '.$rs(abs($value)) : $rs($value) }}</div></div>
            @endforeach
        </section>

        <section aria-labelledby="grn-checks-heading" class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 {{ $cardShadow }}">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="grn-checks-heading" class="text-base font-bold text-gray-900">{{ $isDraft ? 'Check before posting' : 'Checks on this GRN' }}</h2>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $checkErrors ? 'bg-red-100 text-red-800' : ($checkWarns ? 'bg-amber-100 text-amber-900' : 'bg-green-100 text-green-800') }}">
                    {{ $checkErrors ? $checkErrors.' to fix' : ($checkWarns ? $checkWarns.' to review' : 'All clear') }}
                </span>
                <span class="text-xs text-gray-500">{{ $checkErrors ? 'Fix the red items before posting.' : ($checkWarns ? 'Amber items will not stop posting, but look at them first.' : 'Nothing needs your attention.') }}</span>
            </div>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($checks as [$checkLabel, $checkLevel, $checkText])
                    <li class="flex items-start gap-2.5 rounded-xl px-3 py-2 ring-1 {{ ['ok' => 'bg-green-50/60 ring-green-100', 'warn' => 'bg-amber-50 ring-amber-200', 'error' => 'bg-red-50 ring-red-200'][$checkLevel] }}">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white {{ ['ok' => 'bg-green-600', 'warn' => 'bg-amber-500', 'error' => 'bg-red-600'][$checkLevel] }}" aria-hidden="true">{{ ['ok' => '✓', 'warn' => '!', 'error' => '×'][$checkLevel] }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-900">{{ $checkLabel }} <span class="sr-only">— {{ ['ok' => 'OK', 'warn' => 'review', 'error' => 'fix'][$checkLevel] }}</span></span>
                            <span class="block text-xs text-gray-700">{{ $checkText }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="grn-items-heading" class="overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200 {{ $cardShadow }}"
            x-data="{ view: 'summary', open: null, find: '' }">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3">
                <h2 id="grn-items-heading" class="text-base font-bold text-gray-900">Items received <span class="font-semibold text-gray-500">· {{ $grn->items->count() }}</span></h2>
                @if ($grn->items->count() > 6)
                    <label for="grn-show-find" class="sr-only">Find an item</label>
                    <input id="grn-show-find" type="search" x-model="find" placeholder="Find product or line no." class="h-8 w-56 rounded-lg border-gray-300 text-sm shadow-sm print:hidden">
                @endif
                <div class="ml-auto inline-flex gap-0.5 rounded-lg bg-gray-100 p-0.5 print:hidden" role="group" aria-label="Columns to show">
                    <button type="button" @click="view = 'summary'" :aria-pressed="view === 'summary'" :class="view === 'summary' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'" class="rounded-md px-3 py-1 text-xs font-semibold">Summary</button>
                    <button type="button" @click="view = 'tax'" :aria-pressed="view === 'tax'" :class="view === 'tax' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'" class="rounded-md px-3 py-1 text-xs font-semibold">Taxes</button>
                    <button type="button" @click="view = 'all'" :aria-pressed="view === 'all'" :class="view === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'" class="rounded-md px-3 py-1 text-xs font-semibold">All columns</button>
                </div>
            </div>
            @if ($grn->items->count() > 0)
                <div class="max-h-[70vh] overflow-auto print:max-h-none print:overflow-visible">
                    <table class="w-full border-separate border-spacing-0 text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-50">
                            <tr class="[&>th]:border-b [&>th]:border-gray-200">
                                <th class="sticky left-0 z-20 bg-gray-50 px-3 py-2 text-left text-[11px] font-bold uppercase text-gray-600">#</th>
                                <th class="sticky left-10 z-20 min-w-[14rem] bg-gray-50 px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-gray-600">Product</th>
                                <th x-show="view !== 'tax'" class="{{ $th }}">Cases</th>
                                    <th x-show="view !== 'tax'" class="{{ $th }}">Pcs</th>
                                @foreach ($amountCols as $col)
                                    <th x-show="{{ $col['show'] }}" class="{{ $th }}">{{ $col['label'] }}</th>
                                @endforeach
                                <th x-show="view === 'summary'" class="{{ $th }}">Taxes</th>
                                <th x-show="view !== 'tax'" class="{{ $th }}">Pcs received</th>
                                <th x-show="view === 'all'" class="{{ $th }}">Unit cost</th>
                                <th x-show="view === 'all'" class="{{ $th }}">Sell price</th>
                                <th class="{{ $th }}">Line total</th>
                                <th class="w-10 print:hidden"><span class="sr-only">Details</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lineRows as $row)
                                @php($item = $row['item'])
                                <tr x-show="!find || '{{ $item->line_no }}' === find.trim() || {{ Js::from(\Illuminate\Support\Str::lower(($item->product->product_code ?? '').' '.($item->product->product_name ?? ''))) }}.includes(find.trim().toLowerCase())"
                                    @click="open = open === {{ $loop->index }} ? null : {{ $loop->index }}"
                                    :class="open === {{ $loop->index }} ? 'bg-blue-50' : 'bg-white hover:bg-gray-50'" class="cursor-pointer [&>td]:border-b [&>td]:border-gray-100">
                                    <td class="sticky left-0 bg-inherit px-3 py-2 text-xs font-semibold tabular-nums text-gray-500">{{ $item->line_no }}</td>
                                    <td class="sticky left-10 bg-inherit px-3 py-2">
                                        <div class="font-semibold text-gray-900">{{ $item->product->product_name ?? '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $item->product->product_code ?? '' }}@if ($item->is_promotional) · <span class="font-semibold text-orange-700">Promotional</span>@endif</div>
                                    </td>
                                    <td x-show="view !== 'tax'" class="{{ $td }} font-semibold">{{ number_format((float) $item->qty_in_purchase_uom, 2) }}</td>
                                        <td x-show="view !== 'tax'" class="{{ $td }}">{{ number_format((float) $item->qty_in_stock_uom, 2) }}</td>
                                    @foreach ($amountCols as $col)
                                        <td x-show="{{ $col['show'] }}" class="{{ $td }}{{ $col['field'] === 'discounted_value_before_tax' ? ' font-semibold' : '' }}">{{ number_format((float) $item->{$col['field']}, 2) }}</td>
                                    @endforeach
                                    <td x-show="view === 'summary'" class="{{ $td }}">{{ number_format($row['taxes'], 2) }}</td>
                                    <td x-show="view !== 'tax'" class="{{ $td }}">{{ number_format((float) $item->quantity_received, 2) }}</td>
                                    <td x-show="view === 'all'" class="{{ $td }}">{{ number_format((float) $item->unit_cost, 2) }}</td>
                                    <td x-show="view === 'all'" class="{{ $td }}">{{ $item->selling_price ? number_format((float) $item->selling_price, 2) : '—' }}</td>
                                    <td class="{{ $td }} font-bold text-gray-900">{{ number_format((float) ($item->total_value_with_taxes ?? $item->total_cost), 2) }}</td>
                                    <td class="px-2 text-center text-gray-400 print:hidden">
                                        <button type="button" :aria-expanded="open === {{ $loop->index }}" aria-label="Details for line {{ $item->line_no }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md hover:bg-gray-200">
                                            <svg class="h-4 w-4 transition-transform" :class="open === {{ $loop->index }} ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
                                        </button>
                                    </td>
                                </tr>
                                <tr x-show="open === {{ $loop->index }}" x-cloak>
                                    <td colspan="20" class="border-b border-blue-100 bg-blue-50/50 px-4 py-3">
                                        @if (count($row['details']) > 0)
                                            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                                                @foreach ($row['details'] as $label => $value)
                                                    <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</dt><dd class="mt-0.5 text-sm font-semibold tabular-nums text-gray-900">{{ $value }}</dd></div>
                                                @endforeach
                                                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Unit cost</dt><dd class="mt-0.5 text-sm font-semibold tabular-nums">{{ number_format((float) $item->unit_cost, 2) }}</dd></div>
                                                <div><dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Selling price</dt><dd class="mt-0.5 text-sm font-semibold tabular-nums">{{ $item->selling_price ? number_format((float) $item->selling_price, 2) : '—' }}</dd></div>
                                            </dl>
                                        @else
                                            <p class="text-sm text-gray-600">No batch, expiry or promotion details on this line.</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="sticky bottom-0 z-10 bg-gray-100 font-bold">
                            <tr class="[&>td]:border-t [&>td]:border-gray-300">
                                <td colspan="2" class="sticky left-0 bg-gray-100 px-3 py-2 text-left">Totals · {{ $grn->items->count() }} {{ \Illuminate\Support\Str::plural('line', $grn->items->count()) }}</td>
                                <td x-show="view !== 'tax'" class="{{ $td }}">{{ $rs($totals['qty_in_purchase_uom']) }}</td>
                                    <td x-show="view !== 'tax'" class="{{ $td }}">{{ $rs($totalPcs) }}</td>
                                @foreach ($amountCols as $col)
                                    @if ($col['field'] === 'unit_price_per_case')
                                        <td x-show="{{ $col['show'] }}"></td>
                                    @else
                                        <td x-show="{{ $col['show'] }}" class="{{ $td }}">{{ $rs($totals[$col['field']] ?? 0) }}</td>
                                    @endif
                                @endforeach
                                <td x-show="view === 'summary'" class="{{ $td }}">{{ $rs($taxTotal) }}</td>
                                <td x-show="view !== 'tax'" class="{{ $td }}">{{ $rs($totals['quantity_received']) }}</td>
                                <td x-show="view === 'all'"></td>
                                <td x-show="view === 'all'"></td>
                                <td class="{{ $td }}">Rs {{ $rs($totals['total_value_with_taxes']) }}</td>
                                <td class="print:hidden"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500 print:hidden">Values as on the supplier invoice. Click a line for batch, expiry, promotion and cost details.</p>
            @else
                <p class="px-4 py-10 text-center text-sm font-semibold text-gray-600">No items on this GRN</p>
            @endif
        </section>


        @if ($grn->notes)
            <section aria-labelledby="grn-notes-heading" class="rounded-2xl bg-white p-5 ring-1 ring-gray-200 {{ $cardShadow }}">
                <h2 id="grn-notes-heading" class="mb-1 text-base font-bold text-gray-900">Notes</h2>
                <p class="text-sm text-gray-700">{{ $grn->notes }}</p>
            </section>
        @endif

        @include('goods-receipt-notes.partials.show-corrections')

        {{-- Ledger, timeline, record and related links side by side, so no column is left empty. --}}
        <aside class="grid grid-cols-1 items-start gap-4 md:grid-cols-2 xl:grid-cols-4">
            @include('goods-receipt-notes.partials.show-side-cards')
        </aside>

        <div class="hidden justify-between pt-10 text-xs print:flex">
            <span class="w-1/4 border-t border-black pt-1 text-center">Received by</span>
            <span class="w-1/4 border-t border-black pt-1 text-center">Checked by</span>
            <span class="w-1/4 border-t border-black pt-1 text-center">Store keeper</span>
        </div>
    </div>

    @include('goods-receipt-notes.partials.show-actions')
</x-app-layout>
