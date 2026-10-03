{{--
    Goods Receipt Notes -> View (/goods-receipt-notes/{grn}).
    Same layout as the Goods Issue view: header with actions, KPI cards, the
    received items (every column of the old screen), then details, payments,
    accounting, timeline and related links. Prints as a formal GRN.
--}}
@php
    $isUnilever = $grn->supplier->supplier_name === 'Unilever Pakistan';
    $isDraft = $grn->status === 'draft';
    $isPosted = $grn->status === 'posted';
    $statusTone = ['draft' => 'ak-status-amber', 'posted' => 'ak-status-green', 'reversed' => 'ak-status-red'];
    $payTone = ['unpaid' => 'ak-status-red', 'partial' => 'ak-status-amber', 'paid' => 'ak-status-green'];
    $payments = $grn->payments()->orderBy('payment_date')->get();
    $paymentStatus = $isPosted ? $grn->payment_status : null;
    $totalPaid = $isPosted ? (float) $grn->total_paid : 0.0;
    $balance = $isPosted ? (float) $grn->balance : 0.0;
    $itemsTotalWithTax = (float) ($grn->items->sum('total_value_with_taxes') ?: $grn->grand_total);
    $rs = fn ($value) => number_format((float) $value, 2);
    $user = auth()->user();
    $since = '2020-01-01';
    $today = now()->toDateString();

    $totals = [
        'qty_in_purchase_uom' => 0, 'extended_value' => 0, 'discount_value' => 0, 'fmr_allowance' => 0,
        'discounted_value_before_tax' => 0, 'excise_duty' => 0, 'sales_tax_value' => 0, 'advance_income_tax' => 0,
        'other_charges' => 0, 'withholding_tax' => 0, 'quantity_received' => 0, 'total_value_with_taxes' => 0,
    ];
    foreach ($grn->items as $item) {
        foreach (array_keys($totals) as $key) {
            $value = $key === 'total_value_with_taxes' ? ($item->total_value_with_taxes ?? $item->total_cost ?? 0) : ($item->{$key} ?? 0);
            $totals[$key] += round((float) $value, 2);
        }
    }
    $rejected = (float) $grn->items->sum('quantity_rejected');
    $promoLines = $grn->items->where('is_promotional', true)->count();
    $taxTotal = $totals['excise_duty'] + $totals['sales_tax_value'] + $totals['advance_income_tax'] + $totals['other_charges'] + ($isUnilever ? $totals['withholding_tax'] : 0);

    $timeline = collect([
        ['at' => $grn->created_at, 'text' => 'Created as draft'.($grn->receivedBy ? ' (received by '.$grn->receivedBy->name.')' : '')],
        $grn->posted_at ? ['at' => $grn->posted_at, 'text' => 'Posted to inventory at '.($grn->warehouse->warehouse_name ?? 'warehouse')] : null,
        $grn->reversed_at ? ['at' => $grn->reversed_at, 'text' => 'Reversed'.($grn->reversedBy ? ' by '.$grn->reversedBy->name : '')] : null,
    ])->filter()
        ->merge($payments->map(fn ($payment) => [
            'at' => $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date) : $payment->created_at,
            'text' => 'Payment '.$payment->payment_number.' ('.ucfirst($payment->status).'): Rs '.number_format((float) $payment->pivot->allocated_amount, 2),
        ]))
        ->filter(fn ($event) => $event['at'])
        ->sortBy('at')
        ->values();

    $links = array_filter([
        ['GRNs from '.$grn->supplier->supplier_name, route('goods-receipt-notes.index', ['filter' => ['supplier_id' => $grn->supplier_id, 'receipt_date_from' => $since, 'receipt_date_to' => $today]])],
        $isPosted ? ['Unpaid GRNs of this supplier', route('goods-receipt-notes.index', ['filter' => ['supplier_id' => $grn->supplier_id, 'payment_status' => 'unpaid', 'receipt_date_from' => $since, 'receipt_date_to' => $today]])] : null,
        $user->can('supplier-payment-list') ? ['Payments to this supplier', route('supplier-payments.index', ['filter' => ['supplier_id' => $grn->supplier_id, 'payment_date_from' => $since, 'payment_date_to' => $today]])] : null,
        $user->can('report-audit-ledger-register') ? ['Supplier ledger register', route('reports.ledger-register.index', ['filter' => ['supplier_id' => $grn->supplier_id, 'date_from' => $grn->receipt_date?->copy()->startOfMonth()->toDateString(), 'date_to' => $grn->receipt_date?->copy()->endOfMonth()->toDateString()]])] : null,
        $grn->journal_entry_id && $user->can('journal-entry-list') ? ['Journal entry #'.$grn->journal_entry_id, route('journal-entries.show', $grn->journal_entry_id)] : null,
    ]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('goods-receipt-notes.index') }}">Goods Receipt Notes</a><span aria-hidden="true">›</span>
                    <span>{{ $grn->grn_number }}</span>
                </nav>
                <div class="ak-person" style="align-items:center; margin-top:6px">
                    <span class="ak-avatar" style="width:48px; height:48px" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.63 10.63a2.25 2.25 0 0 1-2.24 2.12H6.62a2.25 2.25 0 0 1-2.24-2.12L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.38 7.5h17.25c.62 0 1.12-.5 1.12-1.13v-1.5c0-.62-.5-1.12-1.12-1.12H3.38c-.63 0-1.13.5-1.13 1.13v1.5c0 .62.5 1.12 1.13 1.12Z" /></svg>
                    </span>
                    <div>
                        <h1 class="ak-title" style="margin:0">GRN {{ $grn->grn_number }}</h1>
                        <p class="ak-sub" style="margin-top:2px">
                            {{ $grn->receipt_date?->format('l, d M Y') }} &middot; {{ $grn->supplier->supplier_name }}
                            &middot; <span class="ak-status {{ $statusTone[$grn->status] ?? 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ ucfirst($grn->status) }}</span>
                            @if ($paymentStatus) <span class="ak-status {{ $payTone[$paymentStatus] ?? '' }}"><i aria-hidden="true"></i>{{ ucfirst($paymentStatus) }}</span> @endif
                            @if ($grn->is_opening_stock) <span class="ak-pill" style="margin:0">Opening stock</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('goods-receipt-notes.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to GRNs</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @if ($isDraft)
                    @can('goods-receipt-note-edit')
                        <a href="{{ route('goods-receipt-notes.edit', $grn->id) }}" class="ak-btn ak-btn-outline">Edit</a>
                    @endcan
                    @can('goods-receipt-note-post')
                        <button type="button" class="ak-btn ak-btn-success" onclick="window.showPasswordModal('postGrnPasswordModal')">Post to Inventory</button>
                    @endcan
                @endif
                @if ($isPosted)
                    @role('super-admin')
                        <a href="{{ route('goods-receipt-notes.edit-special', $grn->id) }}" class="ak-btn ak-btn-outline" style="color:#b45309; border-color:#fcd34d">Special Edit</a>
                    @endrole
                @endif
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>
        .gr-wrap { display: flex; flex-direction: column; gap: 20px; }
        .gr-cards { display: grid; gap: 20px; grid-template-columns: 1fr; }
        @media (min-width: 1024px) { .gr-cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .gr-list { margin: 0; display: flex; flex-direction: column; font-size: 14px; }
        .gr-list > div { display: flex; justify-content: space-between; gap: 16px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; }
        .gr-list > div:last-child { border-bottom: 0; }
        .gr-list dt { color: var(--ak-muted); flex: none; }
        .gr-list dd { margin: 0; font-weight: 600; color: var(--ak-text); text-align: right; word-break: break-word; }
        .gr-list dd small { display: block; font-weight: 400; color: var(--ak-muted); font-size: 12px; }
        .gr-items { min-width: 1500px !important; font-size: 13px; }
        .gr-items th, .gr-items td { padding: 6px 8px; vertical-align: middle; line-height: 1.35; }
        .gr-items thead th { white-space: nowrap; }
        .gr-items tfoot td { font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); white-space: nowrap; }
        .gr-items .ak-num { white-space: nowrap; }
        .gr-sub { display: block; font-size: 11.5px; color: var(--ak-muted); }
        .gr-tag { display: inline-block; margin: 2px 4px 0 0; padding: 0 6px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #ffedd5; color: #9a3412; }
        .gr-rows { margin: 0; padding: 0; list-style: none; }
        .gr-rows li { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .gr-rows li:last-child { border-bottom: 0; }
        .gr-rows small { display: block; color: var(--ak-muted); font-size: 12px; }
        .gr-rows a, .gr-links a { color: var(--ak-navy); text-decoration: none; }
        .gr-rows a:hover, .gr-links a:hover { text-decoration: underline; }
        .gr-feed { margin: 0; padding: 0; list-style: none; }
        .gr-feed li { display: flex; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .gr-feed li:last-child { border-bottom: 0; }
        .gr-feed i { flex: none; width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; background: var(--ak-navy); }
        .gr-feed small { display: block; color: var(--ak-muted); font-size: 12px; }
        .gr-links a { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; }
        .gr-links a:last-child { border-bottom: 0; }
        .gr-pay { width: 100%; border-collapse: collapse; font-size: 13px; }
        .gr-pay th { text-align: left; font-size: 11px; text-transform: uppercase; color: var(--ak-muted); padding: 6px 4px; border-bottom: 1px solid var(--ak-line); }
        .gr-pay td { padding: 7px 4px; border-bottom: 1px solid #f1f5f9; }
        .gr-sign { display: none; justify-content: space-between; margin-top: 40px; font-size: 11px; }
        .gr-sign span { border-top: 1px solid #000; padding-top: 4px; width: 28%; text-align: center; }
        @media print {
            @page { margin: 10mm; }
            .gr-cards > .gr-no-print, .gr-no-print { display: none !important; }
            .gr-cards { display: block; }
            .uf-card { border: 0 !important; box-shadow: none !important; margin-bottom: 8px; }
            .uf-card-head { padding: 4px 0 !important; background: none !important; border: 0 !important; }
            .ak-page, .uf-card, .ak-dt-scroll { overflow: visible !important; max-width: none !important; width: 100% !important; }
            .gr-items { min-width: 0 !important; width: 100% !important; font-size: 8.5px !important; table-layout: auto; }
            .gr-items th, .gr-items td { padding: 2px 3px !important; white-space: normal !important; }
            .gr-items thead th { font-size: 8px !important; }
            .gr-items .gr-sub { font-size: 7.5px; }
            .gr-items th:nth-child(2), .gr-items td:nth-child(2) { width: 17%; min-width: 110px; }
            .gr-items tbody tr { break-inside: avoid; }
            .gr-cards .uf-body { padding: 4px 0 !important; }
            .gr-tag { border: 1px solid #555; background: none !important; color: #000 !important; }
            .gr-pay { font-size: 10px; }
            .gr-sign { display: flex !important; }
            .min-h-screen { min-height: 0 !important; }
            .fixed, [x-cloak] { display: none !important; }
            .ak-page { padding-bottom: 0 !important; }
        }
    </style>

    <div class="ak-page gr-wrap">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Goods Receipt Note</div>
            <table class="ak-print-meta">
                <tr><th>GRN Number</th><td>{{ $grn->grn_number }}</td><th>Warehouse</th><td>{{ $grn->warehouse->warehouse_name }}</td></tr>
                <tr><th>Supplier</th><td>{{ $grn->supplier->supplier_name }}</td><th>Receipt Date</th><td>{{ $grn->receipt_date?->format('d M Y') }}</td></tr>
                <tr><th>Invoice #</th><td>{{ $grn->supplier_invoice_number ?? '—' }}</td><th>Invoice Date</th><td>{{ $grn->supplier_invoice_date ? $grn->supplier_invoice_date->format('d M Y') : '—' }}</td></tr>
                <tr><th>Received By</th><td>{{ $grn->receivedBy->name ?? 'N/A' }}</td><th>Status</th><td>{{ ucfirst($grn->status) }}{{ $paymentStatus ? ' · '.ucfirst($paymentStatus) : '' }}</td></tr>
                @if ($isPosted)
                    <tr><th>Grand Total</th><td>Rs {{ $rs($itemsTotalWithTax) }}</td><th>Paid / Balance</th><td>Rs {{ $rs($totalPaid) }} / Rs {{ $rs($balance) }}</td></tr>
                @endif
                <tr><th>Printed</th><td colspan="3">{{ now()->format('d-M-Y h:i A') }} by {{ $user->name }}</td></tr>
            </table>
        </div>

        <x-status-message class="no-print" />

        {{-- KPI cards --}}
        <section class="ak-kpis" aria-label="GRN summary">
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.63 10.63a2.25 2.25 0 0 1-2.24 2.12H6.62a2.25 2.25 0 0 1-2.24-2.12L3.75 7.5M10 11.25h4M3.38 7.5h17.25c.62 0 1.12-.5 1.12-1.13v-1.5c0-.62-.5-1.12-1.12-1.12H3.38c-.63 0-1.13.5-1.13 1.13v1.5c0 .62.5 1.12 1.13 1.12Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Items received</span>
                    <span class="ak-kpi-value">{{ number_format($grn->items->count()) }} {{ \Illuminate\Support\Str::plural('line', $grn->items->count()) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($totals['quantity_received'], 2) }} received{{ $rejected > 0 ? ' · '.number_format($rejected, 2).' rejected' : '' }}{{ $promoLines ? ' · '.$promoLines.' promotional' : '' }}</span>
                </span>
            </div>
            <div class="ak-kpi" title="Rs {{ $rs($itemsTotalWithTax) }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.8 2.1c.73.2 1.45-.34 1.45-1.1V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.38c0-.62.5-1.12 1.13-1.12H20.25M2.25 6v9m18-10.5v.75c0 .41.34.75.75.75h.75m-1.5-1.5h.38c.62 0 1.12.5 1.12 1.13v9.75c0 .62-.5 1.12-1.12 1.12h-.38m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.38a1.13 1.13 0 0 1-1.12-1.12V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Total with taxes</span>
                    <span class="ak-kpi-value">Rs {{ number_format($itemsTotalWithTax) }}</span>
                    <span class="ak-kpi-hint">before tax Rs {{ number_format($totals['discounted_value_before_tax']) }} &middot; taxes &amp; charges Rs {{ number_format($taxTotal) }}</span>
                </span>
            </div>
            <div class="ak-kpi">
                <span class="ak-kpi-icon {{ $paymentStatus === 'paid' ? 'ak-tone-green' : 'ak-tone-amber' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Balance due</span>
                    @if ($isPosted)
                        <span class="ak-kpi-value {{ $balance > 0 ? 'db-down' : '' }}" style="{{ $balance > 0 ? 'color:#b91c1c' : '' }}">Rs {{ number_format($balance) }}</span>
                        <span class="ak-kpi-hint">paid Rs {{ number_format($totalPaid) }} of Rs {{ number_format((float) $grn->grand_total) }} &middot; {{ ucfirst($paymentStatus) }}</span>
                    @else
                        <span class="ak-kpi-value ak-kpi-value-sm">Not posted yet</span>
                        <span class="ak-kpi-hint">payments start after posting</span>
                    @endif
                </span>
            </div>
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.63a3.38 3.38 0 0 0-3.38-3.37h-1.5A1.13 1.13 0 0 1 13.5 7.13v-1.5a3.38 3.38 0 0 0-3.38-3.38H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.63c-.62 0-1.13.5-1.13 1.13v17.25c0 .62.5 1.12 1.13 1.12h12.75c.62 0 1.12-.5 1.12-1.12V11.25a9 9 0 0 0-9-9Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Supplier invoice</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $grn->supplier_invoice_number ?? '—' }}</span>
                    <span class="ak-kpi-hint">{{ $grn->supplier_invoice_date ? 'dated '.$grn->supplier_invoice_date->format('d M Y') : 'no invoice date' }} &middot; {{ $grn->warehouse->warehouse_name }}</span>
                </span>
            </div>
        </section>

        {{-- Items: every column of the old screen --}}
        <section class="uf-card" style="padding:0; overflow:hidden" aria-labelledby="gr-items">
            <header class="uf-card-head" style="padding:14px 18px 10px">
                <div>
                    <h2 class="uf-card-title" id="gr-items">Items received</h2>
                    <p class="uf-card-sub" style="margin-left:0">Values as on the supplier invoice. Scroll sideways for all columns.</p>
                </div>
            </header>
            @if ($grn->items->count() > 0)
                <div class="ak-dt-scroll" style="max-height:none">
                    <table class="ak-dt gr-items">
                        <thead>
                            <tr>
                                <th scope="col" class="ak-c">#</th>
                                <th scope="col">Product</th>
                                <th scope="col" class="ak-num">Qty</th>
                                <th scope="col" class="ak-num">UP/Case</th>
                                <th scope="col" class="ak-num">Ext. Value</th>
                                <th scope="col" class="ak-num">Discount</th>
                                <th scope="col" class="ak-num">FMR</th>
                                <th scope="col" class="ak-num">Before Tax</th>
                                <th scope="col" class="ak-num">Excise</th>
                                <th scope="col" class="ak-num">Sales Tax</th>
                                <th scope="col" class="ak-num">Adv. IT</th>
                                <th scope="col" class="ak-num">Other Chg</th>
                                @if ($isUnilever)
                                    <th scope="col" class="ak-num">WHT</th>
                                @endif
                                <th scope="col" class="ak-num">Qty Rec</th>
                                <th scope="col" class="ak-num">Unit Cost</th>
                                <th scope="col" class="ak-num">Sell Price</th>
                                <th scope="col" class="ak-num">Total W/Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grn->items as $item)
                                <tr>
                                    <td class="ak-c ak-muted">{{ $item->line_no }}</td>
                                    <td>
                                        <span class="ak-strong">{{ $item->product->product_code }}</span>
                                        <span class="gr-sub">{{ $item->product->product_name }}</span>
                                        @if ($item->is_promotional)
                                            <span class="gr-tag">Promotional{{ $item->promotionalCampaign ? ' · '.$item->promotionalCampaign->campaign_name : '' }}</span>
                                        @endif
                                        @if ($item->batch_number || $item->lot_number)
                                            <span class="gr-sub">@if ($item->batch_number)Batch: {{ $item->batch_number }}@endif @if ($item->lot_number)| Lot: {{ $item->lot_number }}@endif</span>
                                        @endif
                                        @if ($item->expiry_date)
                                            <span class="gr-sub">Exp: {{ \Carbon\Carbon::parse($item->expiry_date)->format('d M Y') }}</span>
                                        @endif
                                    </td>
                                    <td class="ak-num">{{ number_format($item->qty_in_purchase_uom ?? 0, 2) }}</td>
                                    <td class="ak-num">{{ number_format($item->unit_price_per_case ?? 0, 2) }}</td>
                                    <td class="ak-num">{{ number_format($item->extended_value ?? 0, 2) }}</td>
                                    <td class="ak-num">{{ number_format($item->discount_value ?? 0, 2) }}</td>
                                    <td class="ak-num">{{ number_format($item->fmr_allowance ?? 0, 2) }}</td>
                                    <td class="ak-num ak-strong">{{ number_format($item->discounted_value_before_tax ?? 0, 2) }}</td>
                                    <td class="ak-num">{{ number_format($item->excise_duty ?? 0, 2) }}</td>
                                    <td class="ak-num">{{ number_format($item->sales_tax_value ?? 0, 2) }}</td>
                                    <td class="ak-num">{{ number_format($item->advance_income_tax ?? 0, 2) }}</td>
                                    <td class="ak-num">{{ number_format($item->other_charges ?? 0, 2) }}</td>
                                    @if ($isUnilever)
                                        <td class="ak-num">{{ number_format($item->withholding_tax ?? 0, 2) }}</td>
                                    @endif
                                    <td class="ak-num">
                                        {{ number_format($item->quantity_received, 2) }}
                                        @if ($item->quantity_rejected > 0)
                                            <span class="gr-sub" style="color:#b91c1c">(Rej: {{ number_format($item->quantity_rejected, 2) }})</span>
                                        @endif
                                    </td>
                                    <td class="ak-num">{{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="ak-num">{!! $item->selling_price ? number_format($item->selling_price, 2) : '<span class="ak-muted">—</span>' !!}</td>
                                    <td class="ak-num ak-strong">{{ number_format($item->total_value_with_taxes ?? $item->total_cost, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">Totals &middot; {{ $grn->items->count() }} {{ \Illuminate\Support\Str::plural('line', $grn->items->count()) }}</td>
                                <td class="ak-num">{{ $rs($totals['qty_in_purchase_uom']) }}</td>
                                <td></td>
                                <td class="ak-num">{{ $rs($totals['extended_value']) }}</td>
                                <td class="ak-num">{{ $rs($totals['discount_value']) }}</td>
                                <td class="ak-num">{{ $rs($totals['fmr_allowance']) }}</td>
                                <td class="ak-num">{{ $rs($totals['discounted_value_before_tax']) }}</td>
                                <td class="ak-num">{{ $rs($totals['excise_duty']) }}</td>
                                <td class="ak-num">{{ $rs($totals['sales_tax_value']) }}</td>
                                <td class="ak-num">{{ $rs($totals['advance_income_tax']) }}</td>
                                <td class="ak-num">{{ $rs($totals['other_charges']) }}</td>
                                @if ($isUnilever)
                                    <td class="ak-num">{{ $rs($totals['withholding_tax']) }}</td>
                                @endif
                                <td class="ak-num">{{ $rs($totals['quantity_received']) }}</td>
                                <td></td>
                                <td></td>
                                <td class="ak-num">Rs {{ $rs($totals['total_value_with_taxes']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="ak-empty"><h2>No items on this GRN</h2></div>
            @endif
            @if ($grn->notes)
                <p style="margin:0; padding:12px 18px; border-top:1px solid var(--ak-line); font-size:13.5px"><b>Notes:</b> {{ $grn->notes }}</p>
            @endif
        </section>

        <div class="gr-cards">
            {{-- Details (the printed header already carries these) --}}
            <section class="uf-card gr-no-print" aria-labelledby="gr-details">
                <header class="uf-card-head"><h2 class="uf-card-title" id="gr-details">Details</h2></header>
                <div class="uf-body" style="padding-top:4px; padding-bottom:4px">
                    <dl class="gr-list">
                        <div><dt>GRN number</dt><dd>{{ $grn->grn_number }}</dd></div>
                        <div><dt>Receipt date</dt><dd>{{ $grn->receipt_date?->format('d M Y') }}</dd></div>
                        <div><dt>Supplier</dt><dd>{{ $grn->supplier->supplier_name }}</dd></div>
                        <div><dt>Warehouse</dt><dd>{{ $grn->warehouse->warehouse_name }}</dd></div>
                        <div><dt>Invoice #</dt><dd>{{ $grn->supplier_invoice_number ?? '—' }}<small>{{ $grn->supplier_invoice_date ? $grn->supplier_invoice_date->format('d M Y') : '—' }}</small></dd></div>
                        <div><dt>Status</dt><dd>{{ ucfirst($grn->status) }}@if ($grn->posted_at)<small>posted {{ $grn->posted_at->format('d M Y, h:i A') }}</small>@endif</dd></div>
                        <div><dt>Received by</dt><dd>{{ $grn->receivedBy->name ?? 'N/A' }}</dd></div>
                        @if ($grn->verifiedBy)
                            <div><dt>Verified by</dt><dd>{{ $grn->verifiedBy->name }}</dd></div>
                        @endif
                        @if ($grn->reversed_at)
                            <div><dt>Reversed</dt><dd>{{ $grn->reversed_at->format('d M Y, h:i A') }}<small>{{ $grn->reversedBy->name ?? '' }}</small></dd></div>
                        @endif
                        <div><dt>Created</dt><dd>{{ $grn->created_at?->format('d M Y, h:i A') }}</dd></div>
                        <div><dt>Last updated</dt><dd>{{ $grn->updated_at?->format('d M Y, h:i A') }}</dd></div>
                    </dl>
                </div>
            </section>

            {{-- Payments --}}
            <section class="uf-card" aria-labelledby="gr-payments">
                <header class="uf-card-head"><h2 class="uf-card-title" id="gr-payments">Payments</h2></header>
                <div class="uf-body" style="padding-top:4px; padding-bottom:8px">
                    @if ($isPosted)
                        <dl class="gr-list">
                            <div><dt>Payment status</dt><dd><span class="ak-status {{ $payTone[$paymentStatus] ?? '' }}"><i aria-hidden="true"></i>{{ ucfirst($paymentStatus) }}</span></dd></div>
                            <div><dt>Total amount</dt><dd>Rs {{ $rs($grn->grand_total) }}</dd></div>
                            <div><dt>Total paid</dt><dd style="color:#047857">Rs {{ $rs($totalPaid) }}</dd></div>
                            <div><dt>Balance due</dt><dd style="{{ $balance > 0 ? 'color:#b91c1c' : '' }}">Rs {{ $rs($balance) }}</dd></div>
                        </dl>
                    @endif
                    @if ($payments->isNotEmpty())
                        <table class="gr-pay" style="margin-top:8px">
                            <thead><tr><th>Payment #</th><th>Date</th><th>Method</th><th>Status</th><th style="text-align:right">Allocated</th></tr></thead>
                            <tbody>
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td>@can('supplier-payment-list')<a href="{{ route('supplier-payments.show', $payment->id) }}" style="color:var(--ak-navy)">{{ $payment->payment_number }}</a>@else{{ $payment->payment_number }}@endcan</td>
                                        <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                                        <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                        <td><span class="ak-status {{ $payment->status === 'posted' ? 'ak-status-green' : 'ak-status-amber' }}"><i aria-hidden="true"></i>{{ ucfirst($payment->status) }}</span></td>
                                        <td style="text-align:right; font-weight:600">{{ $rs($payment->pivot->allocated_amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @elseif (! $isPosted)
                        <p class="ak-muted" style="margin:10px 0 0">Payments can be made once this GRN is posted.</p>
                    @else
                        <p class="ak-muted" style="margin:10px 0 0">No payment made against this GRN yet.</p>
                    @endif
                </div>
            </section>

            <div class="gr-no-print" style="display:flex; flex-direction:column; gap:20px">
                {{-- Timeline --}}
                <section class="uf-card" aria-labelledby="gr-timeline">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gr-timeline">Timeline</h2></header>
                    <div class="uf-body" style="padding-top:4px; padding-bottom:4px">
                        <ul class="gr-feed">
                            @foreach ($timeline as $event)
                                <li><i aria-hidden="true"></i><div>{{ $event['text'] }}<small>{{ $event['at']->format('d M Y, h:i A') }} &middot; {{ $event['at']->diffForHumans() }}</small></div></li>
                            @endforeach
                        </ul>
                    </div>
                </section>

                {{-- Related --}}
                <section class="uf-card" aria-labelledby="gr-related">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gr-related">Related</h2></header>
                    <div class="uf-body" style="padding-top:4px; padding-bottom:4px">
                        <nav class="gr-links">
                            @foreach ($links as [$label, $url])
                                <a href="{{ $url }}">{{ $label }} <span aria-hidden="true">→</span></a>
                            @endforeach
                        </nav>
                    </div>
                </section>
            </div>
        </div>

        <div class="gr-sign"><span>Received by</span><span>Checked by</span><span>Store keeper</span></div>
    </div>

    <!-- Password Modal -->
    <x-password-confirm-modal id="reverseGrnModal" title="Confirm GRN Reversal"
        message="WARNING: This will reverse all stock entries and draft payments. This action cannot be undone."
        warningClass="text-red-600" confirmButtonText="Confirm Reverse"
        confirmButtonClass="bg-red-600 hover:bg-red-700" />

    <x-password-confirm-modal id="postGrnPasswordModal" title="Confirm GRN Posting"
        message="Are you sure you want to post GRN {{ $grn->grn_number }} to inventory? This action cannot be undone."
        secondaryMessage="GRN نمبر: {{ $grn->grn_number }}
مصنوعات کی تفصیلات: {{ $grn->items->count() }} | کل رقم: Rs. {{ number_format($grn->items->sum('total_value_with_taxes') ?: $grn->grand_total, 2) }}"
        secondaryMessageDirection="rtl"
        errorKey="password"
        confirmButtonText="Post to Inventory" confirmButtonClass="bg-emerald-600 hover:bg-emerald-700" />

    <form id="postGrnForm" action="{{ route('goods-receipt-notes.post', $grn->id) }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" id="post_grn_password" name="password">
    </form>

    <script>
        function confirmReverseGrn() {
            if (!confirm('Are you sure you want to REVERSE this GRN? All stock entries and draft payments will be reversed. This action cannot be undone.')) {
                return false;
            }

            window.showPasswordModal('reverseGrnModal');
            return false;
        }

        // Listen for password confirmation event
        document.addEventListener('passwordConfirmed', function (event) {
            const { modalId, password } = event.detail;

            if (modalId === 'reverseGrnModal') {
                document.getElementById('password').value = password;
                document.getElementById('reverseGrnForm').submit();
            } else if (modalId === 'postGrnPasswordModal') {
                document.getElementById('post_grn_password').value = password;
                document.getElementById('postGrnForm').submit();
            }
        });
    </script>
</x-app-layout>
