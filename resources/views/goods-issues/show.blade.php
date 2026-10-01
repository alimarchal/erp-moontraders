{{--
    Goods Issues -> View (/goods-issues/{goodsIssue}).
    Same layout as Settings -> Users -> View: header with actions, KPI cards,
    the issued items on the left and details, settlement, accounting,
    timeline and related links on the right. Prints as a formal issue note.
--}}
@php
    $gi = $goodsIssue;
    $isDraft = $gi->status === 'draft';
    $isReversed = $gi->isReversed();
    $sellingValue = (float) $gi->items->sum(fn ($item) => $item->calculated_total ?? $item->total_value);
    $estimatedCost = (float) $gi->items->sum(fn ($item) => (float) $item->quantity_issued * (float) $item->unit_cost);
    $postedCost = (float) $journalEntries->reject(fn ($entry) => str_starts_with((string) $entry->reference, 'REV-'))->sum('total_debit');
    $cost = $postedCost > 0 ? $postedCost : $estimatedCost;
    $margin = $sellingValue - $cost;
    $totalPieces = (float) $gi->items->sum('quantity_issued');
    $supplementary = $gi->items->where('is_supplementary', true);
    $qty = fn ($item) => \App\Services\GoodsIssueStockCheck::formatQuantity((float) $item->quantity_issued, (float) ($item->product->uom_conversion_factor ?? 1) ?: 1.0);
    $rs = fn ($value) => number_format((float) $value, 2);
    $since = '2020-01-01';
    $today = now()->toDateString();
    $settlements = $gi->settlement;
    $settlementTone = fn (?string $status) => match ($status) {
        'posted' => 'ak-status-green',
        'verified' => 'ak-status-green',
        'draft' => 'ak-status-amber',
        default => 'ak-status-red',
    };

    // Timeline: what happened to this issue, oldest first.
    $timeline = collect([
        ['at' => $gi->created_at, 'text' => 'Created as draft'.($gi->issuedBy ? ' by '.$gi->issuedBy->name : '')],
        $gi->posted_at ? ['at' => $gi->posted_at, 'text' => 'Posted: stock moved from '.($gi->warehouse->warehouse_name ?? 'warehouse').' to vehicle '.($gi->vehicle->vehicle_number ?? '')] : null,
    ])->filter()
        ->merge($supplementary->groupBy(fn ($item) => optional($item->supplementary_posted_at)->format('Y-m-d H:i'))->map(fn ($items) => [
            'at' => $items->first()->supplementary_posted_at ?? $items->first()->created_at,
            'text' => $items->count().' supplementary '.\Illuminate\Support\Str::plural('line', $items->count()).' added',
        ])->values())
        ->push($isReversed ? ['at' => $gi->reversed_at, 'text' => 'Reversed'.($gi->reversedBy ? ' by '.$gi->reversedBy->name : '').': stock returned to '.($gi->warehouse->warehouse_name ?? 'warehouse').($gi->replacement ? ', lines copied to '.$gi->replacement->issue_number : '')] : null)
        ->filter()
        ->merge($settlements->map(fn ($settlement) => [
            'at' => $settlement->created_at,
            'text' => 'Settlement '.$settlement->settlement_number.' '.($settlement->status === 'draft' ? 'started (draft)' : $settlement->status),
        ]))
        ->filter(fn ($event) => $event['at'])
        ->sortBy('at')
        ->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('goods-issues.index') }}">Goods Issues</a><span aria-hidden="true">›</span>
                    <span>{{ $gi->issue_number }}</span>
                </nav>
                <div class="ak-person" style="align-items:center; margin-top:6px">
                    <span class="ak-avatar" style="width:48px; height:48px" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.38a1.13 1.13 0 0 1-1.13-1.13V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.13c.62 0 1.13-.5 1.09-1.12a17.9 17.9 0 0 0-3.21-9.06 2.29 2.29 0 0 0-1.89-.95H14.25M16.5 18.75h-2.25m0-11.18v-.96c0-.57-.42-1.05-.98-1.12a48.6 48.6 0 0 0-10.04 0 1.13 1.13 0 0 0-.98 1.12v7.64m12 0v-6.68" /></svg>
                    </span>
                    <div>
                        <h1 class="ak-title" style="margin:0">Goods Issue {{ $gi->issue_number }}</h1>
                        <p class="ak-sub" style="margin-top:2px">
                            {{ $gi->issue_date?->format('l, d M Y') }} &middot; {{ $gi->supplier->supplier_name ?? 'No supplier' }}
                            &middot;
                            @if ($isDraft)
                                <span class="ak-status ak-status-amber"><i aria-hidden="true"></i>Draft</span>
                            @elseif ($isReversed)
                                <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Reversed</span>
                            @else
                                <span class="ak-status ak-status-green"><i aria-hidden="true"></i>{{ \Illuminate\Support\Str::headline($gi->status) }}</span>
                            @endif
                            @if ($supplementary->isNotEmpty()) <span class="ak-pill" style="margin:0">{{ $supplementary->count() }} supplementary</span> @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('goods-issues.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Goods Issues</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @if ($isDraft)
                    @can('goods-issue-edit')
                        <a href="{{ route('goods-issues.edit', $gi->id) }}" class="ak-btn ak-btn-outline">Edit</a>
                    @endcan
                    @can('goods-issue-post')
                        <button type="button" x-data class="ak-btn ak-btn-success"
                            @click="$dispatch('open-post-gi-modal', { url: '{{ route('goods-issues.post', $gi->id) }}' })">Post Issue</button>
                    @endcan
                @endif
                @if ($gi->status === 'issued')
                    @can('goods-issue-reverse')
                        <button type="button" x-data class="ak-btn ak-btn-outline" style="color:#b91c1c; border-color:#fca5a5"
                            @if ($reversalRefusal) disabled title="{{ $reversalRefusal }}" @endif
                            @click="$dispatch('open-reverse-gi-modal')">Reverse &amp; Re-issue</button>
                    @endcan
                @endif
                @if ($gi->canAcceptSupplementaryItems())
                    @can('goods-issue-edit')
                        <button type="button" x-data class="ak-btn ak-btn-primary"
                            @click="$dispatch('open-append-items-confirm', { url: '{{ route('goods-issues.append-items', $gi->id) }}', issueNumber: '{{ $gi->issue_number }}', existingLineCount: {{ $gi->items->count() }} })">+ Add More Items</button>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>
        .gi-grid { display: grid; gap: 20px; grid-template-columns: 1fr; }
        @media (min-width: 1024px) { .gi-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
        .gi-side { display: flex; flex-direction: column; gap: 20px; }
        .gi-list { margin: 0; display: flex; flex-direction: column; font-size: 14px; }
        .gi-list > div { display: flex; justify-content: space-between; gap: 16px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; }
        .gi-list > div:last-child { border-bottom: 0; }
        .gi-list dt { color: var(--ak-muted); flex: none; }
        .gi-list dd { margin: 0; font-weight: 600; color: var(--ak-text); text-align: right; word-break: break-word; }
        .gi-list dd small { display: block; font-weight: 400; color: var(--ak-muted); font-size: 12px; }
        .gi-list a, .gi-links a, .gi-rows a { color: var(--ak-navy); text-decoration: none; }
        .gi-list a:hover, .gi-links a:hover, .gi-rows a:hover { text-decoration: underline; }
        /* Compact rows on screen, like the old issue note: one line per product. */
        .gi-items { min-width: 760px !important; font-size: 13px; }
        .gi-items th, .gi-items td { padding: 6px 10px; vertical-align: middle; line-height: 1.35; }
        .gi-items thead th { padding-top: 8px; padding-bottom: 8px; }
        .gi-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .gi-tools .ak-search { width: 230px; }
        .gi-tools .ak-field input, .gi-tools .ak-field select { height: 34px; font-size: 13px; }
        .gi-tools .ak-search svg { top: 8px; }
        .gi-found { margin: 0; padding: 8px 18px; font-size: 13px; color: var(--ak-muted); background: #eef2ff; border-top: 1px solid #c7d2fe; }
        .gi-found b { color: var(--ak-text); }
        .gi-found button { margin-left: 8px; border: 0; background: none; color: var(--ak-navy); font-weight: 600; cursor: pointer; }
        .gi-none { margin: 0; padding: 18px; text-align: center; color: var(--ak-muted); font-size: 13px; }
        .gi-items .gi-sub { margin-left: 6px; font-size: 11.5px; color: var(--ak-muted); font-weight: 400; }
        .gi-items tfoot td { font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
        .gi-batches { font-size: 12px; color: var(--ak-muted); line-height: 1.5; }
        .gi-batches b { color: var(--ak-text); font-weight: 600; }
        .gi-tag { display: inline-block; margin: 2px 4px 0 0; padding: 0 6px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .gi-tag-promo { background: #ffedd5; color: #9a3412; }
        .gi-tag-np { background: #e0e7ff; color: #3730a3; }
        .gi-tag-supp { background: #f3e8ff; color: #6b21a8; }
        .gi-rows { margin: 0; padding: 0; list-style: none; }
        .gi-rows li { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .gi-rows li:last-child { border-bottom: 0; }
        .gi-rows small { display: block; color: var(--ak-muted); font-size: 12px; }
        .gi-feed { margin: 0; padding: 0; list-style: none; }
        .gi-feed li { display: flex; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .gi-feed li:last-child { border-bottom: 0; }
        .gi-feed i { flex: none; width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; background: var(--ak-navy); }
        .gi-feed small { display: block; color: var(--ak-muted); font-size: 12px; }
        .gi-links { display: grid; grid-template-columns: 1fr; gap: 2px; font-size: 13.5px; }
        .gi-links a { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .gi-links a:last-child { border-bottom: 0; }
        .gi-check { border-left: 4px solid; padding: 12px 16px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; font-size: 13.5px; }
        .gi-check-red { border-color: #ef4444; background: #fef2f2; }
        .gi-check-amber { border-color: #f59e0b; background: #fffbeb; }
        .gi-check-green { border-color: #22c55e; background: #f0fdf4; }
        @media print {
            /* No fixed size: the print dialog offers Portrait and Landscape, and the table fits either. */
            @page { margin: 10mm; }
            .gi-grid { display: block; }
            .ak-page, .uf-card, .ak-dt-scroll { overflow: visible !important; max-width: none !important; width: 100% !important; }
            .gi-items { min-width: 0 !important; width: 100% !important; table-layout: auto; }
            .gi-items th, .gi-items td { white-space: normal !important; word-break: break-word; }
            .gi-items .ak-num { white-space: nowrap !important; }
            .gi-batches { font-size: 9px; }
            .gi-side, .gi-no-print, #stock-check { display: none !important; }
            .uf-card { border: 0 !important; box-shadow: none !important; }
            .uf-card-head { display: none !important; }
            .gi-items { font-size: 9.5px; }
            .gi-tag { border: 1px solid #555; background: none !important; color: #000 !important; }
            .gi-sign { display: flex !important; }
        }
        .gi-sign { display: none; justify-content: space-between; margin-top: 40px; font-size: 10px; }
        .gi-sign span { border-top: 1px solid #000; padding-top: 4px; width: 28%; text-align: center; }
    </style>

    <div class="ak-page">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Goods Issue Note</div>
            <table class="ak-print-meta">
                <tr><th>Issue No.</th><td>{{ $gi->issue_number }}</td><th>Date</th><td>{{ $gi->issue_date?->format('d-M-Y') }}</td></tr>
                <tr><th>Status</th><td>{{ \Illuminate\Support\Str::headline($gi->status) }}{{ $gi->posted_at ? ' ('.$gi->posted_at->format('d-M-Y h:i A').')' : '' }}</td><th>Warehouse</th><td>{{ $gi->warehouse->warehouse_name ?? '—' }}</td></tr>
                <tr><th>Salesman</th><td>{{ $gi->employee->name ?? '—' }} ({{ $gi->employee->employee_code ?? '' }})</td><th>Vehicle</th><td>{{ $gi->vehicle->vehicle_number ?? '—' }} ({{ $gi->vehicle->vehicle_type ?? '' }})</td></tr>
                <tr><th>Supplier</th><td>{{ $gi->supplier->supplier_name ?? 'N/A' }}</td><th>Printed</th><td>{{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</td></tr>
                @if ($gi->notes)
                    <tr><th>Notes</th><td colspan="3">{{ $gi->notes }}</td></tr>
                @endif
            </table>
        </div>

        <x-status-message />

        @if ($isReversed)
            <div class="gi-check gi-check-red" role="status">
                <span>
                    <b>Reversed</b> on {{ $gi->reversed_at->format('d M Y, h:i A') }}{{ $gi->reversedBy ? ' by '.$gi->reversedBy->name : '' }}.
                    Stock went back to {{ $gi->warehouse->warehouse_name ?? 'the warehouse' }} and the journal entries were offset on {{ $gi->issue_date?->format('d M Y') }}.
                    <br><span class="ak-muted">Reason: {{ $gi->reversal_reason }}</span>
                </span>
                @if ($gi->replacement)
                    <a href="{{ route('goods-issues.show', $gi->replacement) }}" class="ak-btn ak-btn-outline ak-btn-sm gi-no-print">Replaced by {{ $gi->replacement->issue_number }} →</a>
                @endif
            </div>
        @endif
        @if ($gi->replaces)
            <div class="gi-check gi-check-amber gi-no-print" role="status">
                <span>Copied from reversed <b>{{ $gi->replaces->issue_number }}</b>{{ $isDraft ? '. Correct the salesman, vehicle or quantities, then post it. Until then this draft holds the lock on vehicle '.($gi->vehicle->vehicle_number ?? '').'.' : '.' }}</span>
                <a href="{{ route('goods-issues.show', $gi->replaces) }}" class="ak-btn ak-btn-outline ak-btn-sm">View {{ $gi->replaces->issue_number }} →</a>
            </div>
        @endif

        {{-- KPI cards --}}
        <section class="ak-kpis" aria-label="Issue summary">
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.63 10.63a2.25 2.25 0 0 1-2.24 2.12H6.62a2.25 2.25 0 0 1-2.24-2.12L3.75 7.5M10 11.25h4M3.38 7.5h17.25c.62 0 1.12-.5 1.12-1.13v-1.5c0-.62-.5-1.12-1.12-1.12H3.38c-.63 0-1.13.5-1.13 1.13v1.5c0 .62.5 1.12 1.13 1.12Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Items issued</span>
                    <span class="ak-kpi-value">{{ number_format($gi->items->count()) }} {{ \Illuminate\Support\Str::plural('line', $gi->items->count()) }}</span>
                    <span class="ak-kpi-hint">{{ rtrim(rtrim(number_format($totalPieces, 3), '0'), '.') }} pcs in total{{ $supplementary->isNotEmpty() ? ' · '.$supplementary->count().' supplementary' : '' }}</span>
                </span>
            </div>
            <div class="ak-kpi" title="Rs {{ $rs($sellingValue) }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.8 2.1c.73.2 1.45-.34 1.45-1.1V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.38c0-.62.5-1.12 1.13-1.12H20.25M2.25 6v9m18-10.5v.75c0 .41.34.75.75.75h.75m-1.5-1.5h.38c.62 0 1.12.5 1.12 1.13v9.75c0 .62-.5 1.12-1.12 1.12h-.38m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.38a1.13 1.13 0 0 1-1.12-1.12V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Selling value</span>
                    <span class="ak-kpi-value">Rs {{ number_format($sellingValue) }}</span>
                    <span class="ak-kpi-hint">cost Rs {{ number_format($cost) }}{{ $postedCost > 0 ? '' : ' (estimate)' }} &middot; margin Rs {{ number_format($margin) }}</span>
                </span>
            </div>
            @php $employeeUrl = $gi->employee && auth()->user()->can('employee-list') ? route('employees.show', $gi->employee) : null; @endphp
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0A17.93 17.93 0 0 1 12 21.75c-2.68 0-5.22-.58-7.5-1.63Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Salesman</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $gi->employee->name ?? '—' }}</span>
                    <span class="ak-kpi-hint">{{ $gi->employee->employee_code ?? '' }}</span>
                </span>
            </div>
            @php $vehicleUrl = $gi->vehicle && auth()->user()->can('vehicle-list') ? route('vehicles.show', $gi->vehicle) : null; @endphp
            <div class="ak-kpi">
                <span class="ak-kpi-icon ak-tone-amber" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.38a1.13 1.13 0 0 1-1.13-1.13V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.13c.62 0 1.13-.5 1.09-1.12a17.9 17.9 0 0 0-3.21-9.06 2.29 2.29 0 0 0-1.89-.95H14.25M16.5 18.75h-2.25m0-11.18v-.96c0-.57-.42-1.05-.98-1.12a48.6 48.6 0 0 0-10.04 0 1.13 1.13 0 0 0-.98 1.12v7.64m12 0v-6.68" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Vehicle</span>
                    <span class="ak-kpi-value ak-kpi-value-sm">{{ $gi->vehicle->vehicle_number ?? '—' }}</span>
                    <span class="ak-kpi-hint">{{ $gi->vehicle->vehicle_type ?? '' }} &middot; from {{ $gi->warehouse->warehouse_name ?? '—' }}</span>
                </span>
            </div>
        </section>

        @if ($isDraft && $stockPositions->isNotEmpty())
            @php
                $shortPositions = $stockPositions->filter(fn ($p) => $p['short'] > 0.001 || $p['short_non_promotional'] > 0.001);
                $contestedPositions = $stockPositions->filter(fn ($p) => $p['short'] <= 0.001 && $p['short_non_promotional'] <= 0.001 && $p['required'] > $p['free'] + 0.001);
                $problemPositions = $shortPositions->merge($contestedPositions);
                $qty = fn (float $quantity, array $p) => \App\Services\GoodsIssueStockCheck::formatQuantity($quantity, $p['conversion_factor']);
            @endphp
            <section id="stock-check" class="uf-card" style="padding:0; overflow:hidden" aria-label="Stock check">
                <div @class([
                    'gi-check',
                    'gi-check-red' => $shortPositions->isNotEmpty(),
                    'gi-check-amber' => $shortPositions->isEmpty() && $contestedPositions->isNotEmpty(),
                    'gi-check-green' => $problemPositions->isEmpty(),
                ])>
                    <b>Stock Check before posting</b>
                    <span>
                        @if ($shortPositions->isNotEmpty())
                            <span class="font-semibold text-red-700">{{ $shortPositions->count() }} {{ Str::plural('product', $shortPositions->count()) }} short — this issue cannot be posted until they are reduced or removed.</span>
                        @elseif ($contestedPositions->isNotEmpty())
                            <span class="font-semibold text-amber-700">All in stock, but other drafts also need {{ $contestedPositions->count() }} of these {{ Str::plural('product', $contestedPositions->count()) }}. Whichever is posted first takes the stock.</span>
                        @else
                            <span class="font-semibold text-green-700">All {{ $stockPositions->count() }} products are in stock.</span>
                        @endif
                    </span>
                </div>
                    @if ($problemPositions->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Product</th>
                                        <th class="px-3 py-2 text-right">This issue needs</th>
                                        <th class="px-3 py-2 text-right">In stock now</th>
                                        <th class="px-3 py-2 text-left">Also in other drafts</th>
                                        <th class="px-3 py-2 text-left">Already issued {{ $goodsIssue->issue_date?->format('d-M') }}</th>
                                        <th class="px-3 py-2 text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($problemPositions as $position)
                                        @php $isShort = $position['short'] > 0.001 || $position['short_non_promotional'] > 0.001; @endphp
                                        <tr @class(['align-top', 'bg-red-50' => $isShort, 'bg-amber-50' => ! $isShort])>
                                            <td class="px-3 py-2">
                                                <div class="font-semibold text-gray-900">{{ $position['product_name'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $position['product_code'] }}</div>
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $qty($position['required'], $position) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $qty($position['on_hand'], $position) }}</td>
                                            <td class="px-3 py-2 text-xs">
                                                @forelse ($position['other_drafts'] as $draft)
                                                    <div>{{ $draft['issue_number'] }}@if ($draft['vehicle']) ({{ $draft['vehicle'] }})@endif: {{ $qty($draft['quantity'], $position) }}</div>
                                                @empty
                                                    <span class="text-gray-400">—</span>
                                                @endforelse
                                            </td>
                                            <td class="px-3 py-2 text-xs">
                                                @forelse ($position['issued_same_day'] as $issued)
                                                    <div>{{ $issued['issue_number'] }}@if ($issued['vehicle']) ({{ $issued['vehicle'] }})@endif: {{ $qty($issued['quantity'], $position) }}</div>
                                                @empty
                                                    <span class="text-gray-400">—</span>
                                                @endforelse
                                            </td>
                                            <td class="px-3 py-2 text-xs font-semibold">
                                                @if ($position['short'] > 0.001)
                                                    <span class="text-red-700">Short {{ $qty($position['short'], $position) }}</span>
                                                @elseif ($position['short_non_promotional'] > 0.001)
                                                    <span class="text-red-700">Short {{ $qty($position['short_non_promotional'], $position) }} of non-promotional stock</span>
                                                @else
                                                    <span class="text-amber-700">Other drafts need more than is left</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
            </section>
            @php $qty = fn ($item) => \App\Services\GoodsIssueStockCheck::formatQuantity((float) $item->quantity_issued, (float) ($item->product->uom_conversion_factor ?? 1) ?: 1.0); @endphp
        @endif

        <div class="gi-grid">
            {{-- Items --}}
            <section class="uf-card" style="padding:0; overflow:hidden" aria-labelledby="gi-items" x-data="giItems()" @beforeprint.window="search = ''">
                <header class="uf-card-head" style="padding:16px 18px 10px; flex-wrap:wrap; gap:10px">
                    <div>
                        <h2 class="uf-card-title" id="gi-items">Items issued</h2>
                        <p class="uf-card-sub" style="margin-left:0">{{ $isDraft ? 'Draft: batches and rates are what posting would take now (oldest / urgent first).' : 'Batches and rates actually moved to the van.' }}</p>
                    </div>
                    @if ($gi->items->count() > 1)
                        <div class="gi-tools gi-no-print">
                            <div class="ak-search ak-field">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                                <input type="search" x-model="search" x-ref="search" placeholder="Find product or batch" aria-label="Find product" @keydown.escape="search = ''">
                            </div>
                            <div class="ak-field">
                                <select x-model="sort" aria-label="Sort items">
                                    <option value="line">Sort: line #</option>
                                    <option value="value">Value: high → low</option>
                                    <option value="qty">Quantity: high → low</option>
                                    <option value="name">Name: A → Z</option>
                                </select>
                            </div>
                        </div>
                    @endif
                </header>
                <p class="gi-found gi-no-print" x-show="search.trim() !== ''" x-cloak>
                    <span x-text="shown + ' of {{ $gi->items->count() }} lines'"></span> &middot; Rs <b x-text="shownValue"></b>
                    <button type="button" @click="search = ''">Clear</button>
                </p>
                <div class="ak-dt-scroll" style="max-height:none">
                    <table class="ak-dt gi-items">
                        <thead>
                            <tr>
                                <th scope="col" class="ak-c" style="width:44px">#</th>
                                <th scope="col">Product</th>
                                <th scope="col" class="ak-num">Quantity</th>
                                <th scope="col">Batches &times; rate</th>
                                <th scope="col" class="ak-num">Value (Rs)</th>
                            </tr>
                        </thead>
                        <tbody x-ref="rows">
                            @foreach ($gi->items as $item)
                                @php
                                    $lineValue = $item->calculated_total ?? $item->total_value;
                                    $haystack = mb_strtolower($item->product->product_name.' '.$item->product->product_code.' '.collect($item->batch_breakdown ?? [])->pluck('batch_code')->implode(' '));
                                @endphp
                                <tr data-line="{{ $item->line_no ?? $loop->iteration }}" data-value="{{ (float) $lineValue }}" data-qty="{{ (float) $item->quantity_issued }}"
                                    data-name="{{ mb_strtolower($item->product->product_name) }}" data-find="{{ $haystack }}">
                                    <td class="ak-c ak-muted">{{ $item->line_no }}</td>
                                    <td>
                                        <span class="ak-strong">{{ $item->product->product_name }}</span>
                                        @if (mb_strtoupper(trim($item->product->product_code)) !== mb_strtoupper(trim($item->product->product_name)))
                                            <span class="gi-sub">{{ $item->product->product_code }}</span>
                                        @endif
                                        @if ($item->exclude_promotional)<span class="gi-tag gi-tag-np">Non-Promo</span>@endif
                                        @if ($item->is_supplementary)<span class="gi-tag gi-tag-supp">Supplementary{{ $item->supplementary_posted_at ? ' · '.$item->supplementary_posted_at->format('d M') : '' }}</span>@endif
                                    </td>
                                    <td class="ak-num">
                                        @php $cartonsText = ($item->product->uom_conversion_factor ?? 1) > 1 ? \Illuminate\Support\Str::before($qty($item), ' (') : null; @endphp
                                        @if ($cartonsText && ! str_starts_with($cartonsText, '0 ctn'))
                                            <span class="gi-sub" style="margin:0 6px 0 0">{{ $cartonsText }}</span>
                                        @endif
                                        <span class="ak-strong" title="{{ $cartonsText ?? '' }}">{{ number_format((float) $item->quantity_issued, 2) }}</span>
                                    </td>
                                    <td class="gi-batches">
                                        @forelse ($item->batch_breakdown ?? [] as $b)
                                            <div><b>{{ number_format($b['quantity'], 0) }}</b> &times; {{ $rs($b['selling_price']) }}{{ count($item->batch_breakdown) > 1 ? ' = '.$rs($b['value']) : '' }}
                                                @if (($b['batch_code'] ?? 'N/A') !== 'N/A')<span>&middot; {{ $b['batch_code'] }}</span>@endif
                                                @if ($b['is_promotional'])<span class="gi-tag gi-tag-promo">Promo</span>@endif
                                            </div>
                                        @empty
                                            <span>Avg cost {{ $rs($item->unit_cost) }}{{ $isDraft ? ' · no stock to take from' : '' }}</span>
                                        @endforelse
                                    </td>
                                    <td class="ak-num ak-strong">{{ $rs($lineValue) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">Total &middot; {{ $gi->items->count() }} {{ \Illuminate\Support\Str::plural('line', $gi->items->count()) }}</td>
                                <td class="ak-num">{{ number_format($totalPieces, 2) }}</td>
                                <td></td>
                                <td class="ak-num">{{ $rs($sellingValue) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    <p class="gi-none gi-no-print" x-show="search.trim() !== '' && shown === 0" x-cloak>No product matches “<span x-text="search"></span>”.</p>
                </div>
                @if ($gi->notes)
                    <p class="ak-muted gi-no-print" style="margin:0; padding:12px 18px; border-top:1px solid var(--ak-line)"><b>Notes:</b> {{ $gi->notes }}</p>
                @endif
                <div class="gi-sign"><span>Issued by</span><span>Salesman</span><span>Store keeper</span></div>
            </section>

            <div class="gi-side">
                {{-- Details --}}
                <section class="uf-card" aria-labelledby="gi-details">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gi-details">Details</h2></header>
                    <div class="uf-body" style="padding-top:4px; padding-bottom:4px">
                        <dl class="gi-list">
                            <div><dt>Issue number</dt><dd>{{ $gi->issue_number }}</dd></div>
                            <div><dt>Issue date</dt><dd>{{ $gi->issue_date?->format('d M Y') }}</dd></div>
                            <div><dt>Status</dt><dd>{{ \Illuminate\Support\Str::headline($gi->status) }}@if ($gi->posted_at)<small>posted {{ $gi->posted_at->format('d M Y, h:i A') }}</small>@endif</dd></div>
                            <div><dt>Supplier</dt><dd>{{ $gi->supplier->supplier_name ?? 'N/A' }}</dd></div>
                            <div><dt>From warehouse</dt><dd>{{ $gi->warehouse->warehouse_name ?? '—' }}</dd></div>
                            <div><dt>Salesman</dt><dd>@if ($employeeUrl)<a href="{{ $employeeUrl }}">{{ $gi->employee->name }}</a>@else{{ $gi->employee->name ?? '—' }}@endif<small>{{ $gi->employee->employee_code ?? '' }}</small></dd></div>
                            <div><dt>Vehicle</dt><dd>@if ($vehicleUrl)<a href="{{ $vehicleUrl }}">{{ $gi->vehicle->vehicle_number }}</a>@else{{ $gi->vehicle->vehicle_number ?? '—' }}@endif<small>{{ $gi->vehicle->vehicle_type ?? '' }}</small></dd></div>
                            <div><dt>Created by</dt><dd>{{ $gi->issuedBy->name ?? '—' }}<small>{{ $gi->created_at?->format('d M Y, h:i A') }}</small></dd></div>
                            <div><dt>Last updated</dt><dd>{{ $gi->updated_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </section>

                {{-- Settlement --}}
                <section class="uf-card" aria-labelledby="gi-settlement">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gi-settlement">Settlement</h2></header>
                    <div class="uf-body" style="padding-top:4px; padding-bottom:4px">
                        @if ($settlements->isEmpty())
                            <p class="ak-muted" style="padding:10px 0; margin:0">
                                @if ($isDraft)
                                    Post this issue first; the settlement is made when the van comes back.
                                @elseif ($isReversed)
                                    Reversed: nothing to settle.
                                @else
                                    No settlement yet. The vehicle stays locked until this issue is settled.
                                @endif
                            </p>
                            @if ($gi->status === 'issued')
                                @can('sales-settlement-create')
                                    <a href="{{ route('sales-settlements.create') }}" class="ak-btn ak-btn-outline ak-btn-sm" style="margin-bottom:10px">Start settlement</a>
                                @endcan
                            @endif
                        @else
                            <ul class="gi-rows">
                                @foreach ($settlements as $settlement)
                                    <li>
                                        <span>
                                            @can('sales-settlement-list')
                                                <a href="{{ route('sales-settlements.show', $settlement) }}" class="ak-strong">{{ $settlement->settlement_number }}</a>
                                            @else
                                                <span class="ak-strong">{{ $settlement->settlement_number }}</span>
                                            @endcan
                                            <small>{{ $settlement->settlement_date?->format('d M Y') }} &middot; sales Rs {{ number_format((float) $settlement->total_sales_amount) }}</small>
                                        </span>
                                        <span class="ak-status {{ $settlementTone($settlement->status) }}"><i aria-hidden="true"></i>{{ \Illuminate\Support\Str::headline($settlement->status) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>

                {{-- Accounting --}}
                <section class="uf-card" aria-labelledby="gi-accounting">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gi-accounting">Accounting</h2></header>
                    <div class="uf-body" style="padding-top:4px; padding-bottom:4px">
                        @if ($journalEntries->isEmpty())
                            <p class="ak-muted" style="padding:10px 0; margin:0">{{ $isDraft ? 'Posting moves the cost from Stock in Hand to Van Stock with a journal entry.' : 'No journal entry found for this issue.' }}</p>
                        @else
                            <ul class="gi-rows">
                                @foreach ($journalEntries as $entry)
                                    <li>
                                        <span>
                                            @can('journal-entry-list')
                                                <a href="{{ route('journal-entries.show', $entry->id) }}" class="ak-strong">JE #{{ $entry->id }}</a>
                                            @else
                                                <span class="ak-strong">JE #{{ $entry->id }}</span>
                                            @endcan
                                            <small>{{ $entry->reference }} &middot; {{ $entry->entry_date?->format('d M Y') }} &middot; {{ \Illuminate\Support\Str::headline($entry->status) }}</small>
                                        </span>
                                        <b>Rs {{ number_format((float) $entry->total_debit) }}</b>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <dl class="gi-list" style="border-top:1px solid #f1f5f9">
                            <div><dt>Credit (from)</dt><dd>{{ $gi->stockInHandAccount ? $gi->stockInHandAccount->account_code.' · '.$gi->stockInHandAccount->account_name : 'Stock in Hand' }}</dd></div>
                            <div><dt>Debit (to)</dt><dd>{{ $gi->vanStockAccount ? $gi->vanStockAccount->account_code.' · '.$gi->vanStockAccount->account_name : 'Van Stock' }}</dd></div>
                        </dl>
                    </div>
                </section>

                {{-- Timeline --}}
                <section class="uf-card" aria-labelledby="gi-timeline">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gi-timeline">Timeline</h2></header>
                    <div class="uf-body" style="padding-top:4px; padding-bottom:4px">
                        <ul class="gi-feed">
                            @foreach ($timeline as $event)
                                <li><i aria-hidden="true"></i><div>{{ $event['text'] }}<small>{{ $event['at']->format('d M Y, h:i A') }} &middot; {{ $event['at']->diffForHumans() }}</small></div></li>
                            @endforeach
                        </ul>
                    </div>
                </section>

                {{-- Related --}}
                @php
                    $user = auth()->user();
                    $links = array_filter([
                        $gi->vehicle ? ['Issues to vehicle '.$gi->vehicle->vehicle_number, route('goods-issues.index', ['filter' => ['vehicle_id' => $gi->vehicle_id, 'issue_date_from' => $since, 'issue_date_to' => $today]])] : null,
                        $gi->employee ? ['Issues to '.$gi->employee->name, route('goods-issues.index', ['filter' => ['employee_id' => $gi->employee_id, 'issue_date_from' => $since, 'issue_date_to' => $today]])] : null,
                        $user->can('sales-settlement-list') && $gi->vehicle ? ['Settlements of this vehicle', route('sales-settlements.index', ['filter' => ['vehicle_id' => $gi->vehicle_id, 'settlement_date_from' => $since, 'settlement_date_to' => $today]])] : null,
                        $user->can('report-inventory-van-stock-ledger') && $gi->vehicle ? ['Van stock ledger', route('reports.van-stock-ledger.vehicle-ledger', $gi->vehicle_id)] : null,
                        $user->can('report-inventory-van-stock-batch') && $gi->vehicle ? ['Van stock by batch', route('reports.van-stock-batch.index', ['filter' => ['vehicle_id' => $gi->vehicle_id]])] : null,
                        $user->can('report-sales-goods-issue') ? ['Goods issue report (this day)', route('reports.goods-issue.index', ['filter' => array_filter(['start_date' => $gi->issue_date?->toDateString(), 'end_date' => $gi->issue_date?->toDateString(), 'employee_id' => $gi->employee_id ? [$gi->employee_id] : null])])] : null,
                        $user->can('report-audit-creditors-ledger') && $gi->employee ? ['Credit with '.$gi->employee->name.'\'s customers', route('reports.creditors-ledger.index', ['filter' => ['employee_id' => $gi->employee_id, 'has_balance' => 'yes']])] : null,
                    ]);
                @endphp
                <section class="uf-card" aria-labelledby="gi-related">
                    <header class="uf-card-head"><h2 class="uf-card-title" id="gi-related">Related</h2></header>
                    <div class="uf-body" style="padding-top:4px; padding-bottom:4px">
                        <nav class="gi-links">
                            @foreach ($links as [$label, $url])
                                <a href="{{ $url }}">{{ $label }} <span aria-hidden="true">→</span></a>
                            @endforeach
                        </nav>
                    </div>
                </section>
            </div>
        </div>
    </div>


    <script>
        /** Client-side find and sort for the issued items (rows stay server-rendered). */
        function giItems() {
            return {
                search: '',
                sort: 'line',
                shown: 0,
                shownValue: '0',
                init() {
                    this.apply();
                    this.$watch('search', () => this.apply());
                    this.$watch('sort', () => this.apply());
                },
                apply() {
                    const body = this.$refs.rows;
                    if (! body) { return; }
                    const rows = Array.from(body.querySelectorAll('tr'));
                    const terms = this.search.trim().toLowerCase().split(/\s+/).filter(Boolean);
                    const by = {
                        line: (a, b) => a.dataset.line - b.dataset.line,
                        value: (a, b) => b.dataset.value - a.dataset.value,
                        qty: (a, b) => b.dataset.qty - a.dataset.qty,
                        name: (a, b) => a.dataset.name.localeCompare(b.dataset.name),
                    }[this.sort];
                    let shown = 0;
                    let value = 0;
                    rows.sort(by).forEach((row) => {
                        const match = terms.every((term) => row.dataset.find.includes(term));
                        row.style.display = match ? '' : 'none';
                        if (match) { shown++; value += parseFloat(row.dataset.value) || 0; }
                        body.appendChild(row);
                    });
                    this.shown = shown;
                    this.shownValue = value.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            };
        }
    </script>

    @if ($gi->status === 'issued')
        @can('goods-issue-reverse')
            {{-- Reverse & Re-issue: reason and password are both required; the server re-checks every condition. --}}
            <div x-data="{ show: {{ $errors->has('reason') || $errors->has('password') ? 'true' : 'false' }} }"
                 x-on:open-reverse-gi-modal.window="show = true"
                 x-on:keydown.escape.window="show = false"
                 x-show="show" x-cloak class="fixed inset-0 z-50" style="display: none;">
                <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" @click="show = false"></div>
                <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
                    <form method="POST" action="{{ route('goods-issues.reverse', $gi->id) }}" role="dialog" aria-modal="true" aria-labelledby="reverse-gi-title"
                          class="relative w-full max-w-lg overflow-hidden rounded-lg bg-white text-left shadow-xl" @click.outside="show = false">
                        @csrf
                        <div class="px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 id="reverse-gi-title" class="text-lg font-medium leading-6 text-gray-900">Reverse {{ $gi->issue_number }} &amp; re-issue</h3>
                            <div class="mt-2 space-y-2 text-sm text-gray-600">
                                <p>This cancels the posted issue. Nothing is deleted:</p>
                                <ul class="list-disc pl-5">
                                    <li>Stock goes back to {{ $gi->warehouse->warehouse_name ?? 'the warehouse' }}, batch by batch, at the cost it left at.</li>
                                    <li>Vehicle {{ $gi->vehicle->vehicle_number ?? '' }} is emptied of it. The new draft holds its lock until you change the draft's vehicle, post it or delete it.</li>
                                    <li>Offsetting journal entries (REV-{{ $gi->issue_number }}) are posted on {{ $gi->issue_date?->format('d M Y') }}.</li>
                                    <li>A new <b>draft</b> with the same items opens, so you can set the right salesman, vehicle or quantities and post it.</li>
                                </ul>
                            </div>
                            <div class="mt-4">
                                <label for="reverse_reason" class="block text-sm font-medium text-gray-700">Reason</label>
                                <textarea id="reverse_reason" name="reason" rows="2" required minlength="5" maxlength="500" autocomplete="off"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                    placeholder="e.g. Posted to the wrong salesman and vehicle">{{ old('reason') }}</textarea>
                                @error('reason')<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                            </div>
                            {{-- Gives the browser's password manager a username field, so it does not fill the reason with the email. --}}
                            <input type="text" name="username" value="{{ auth()->user()->email }}" autocomplete="username" hidden readonly tabindex="-1">
                            <div class="mt-3">
                                <label for="reverse_password" class="block text-sm font-medium text-gray-700">Your password</label>
                                <input id="reverse_password" type="password" name="password" required autocomplete="current-password"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                                @error('password')<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="flex flex-row justify-end gap-3 bg-gray-100 px-6 py-4">
                            <button type="button" @click="show = false"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">Cancel</button>
                            <button type="submit"
                                class="inline-flex items-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-red-700">Reverse &amp; open draft</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif

    <x-alpine-confirmation-modal event-name="open-post-gi-modal" title="Post Goods Issue"
        message="Are you sure you want to post this Goods Issue? This will transfer inventory from warehouse to vehicle, lock the vehicle until settlement, and create the journal entries. Do you agree?"
        confirm-button-text="Yes, Post Issue" confirm-button-class="bg-emerald-600 hover:bg-emerald-700"
        icon-bg-class="bg-emerald-100" icon-color-class="text-emerald-600" icon-path="M5 13l4 4L19 7" />

    {{-- Append-items navigation confirmation: clicking "Add More Items" opens this modal first
         so the user explicitly agrees they intend to add items to *this* GI (rather than create a new one). --}}
    <div x-data="{ show: false, navigateUrl: '', issueNumber: '', existingLineCount: 0 }"
         x-on:open-append-items-confirm.window="show = true; navigateUrl = $event.detail.url; issueNumber = $event.detail.issueNumber; existingLineCount = $event.detail.existingLineCount"
         x-on:keydown.escape.window="if (show) { show = false }"
         x-show="show"
         x-cloak
         class="fixed inset-0 z-50"
         style="display: none;">
        <div x-show="show"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 backdrop-blur-none" x-transition:enter-end="opacity-100 backdrop-blur-sm"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 backdrop-blur-sm" x-transition:leave-end="opacity-0 backdrop-blur-none"
             class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all" @click="show = false"></div>

        <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
            <div x-show="show"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
                 @click.outside="show = false">

                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-purple-100 sm:mx-0 sm:size-10">
                            <svg class="size-6 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Add Items to Existing Goods Issue</h3>
                            <div class="mt-2 text-sm text-gray-600">
                                <p>You are about to add supplementary items to <strong x-text="issueNumber"></strong>, which already has <strong x-text="existingLineCount"></strong> line(s).</p>
                                <p class="mt-2">New items will be appended to <em>this same</em> Goods Issue and posted as a separate stock movement. Do you agree?</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-row justify-end gap-3 bg-gray-100 px-6 py-4">
                    <button type="button" @click="show = false"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" @click="window.location.href = navigateUrl"
                        class="inline-flex items-center rounded-md border border-transparent bg-purple-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-purple-700">
                        Yes, Add More Items
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
