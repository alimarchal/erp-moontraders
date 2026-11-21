{{--
    Stock Adjustments -> View (/stock-adjustments/{stockAdjustment}), in the Goods Issue page layout:
    a hero card (status, net value, warehouse and date, summary figures, actions), the adjusted lines
    full width, then Details, Accounting and Timeline side by side. On a phone the lines become cards.
    Posting still asks for the user's password (POST stock-adjustments.post with "password").
--}}
@php
    $adjustment = $stockAdjustment;
    $isDraft = $adjustment->status === 'draft';
    $types = ['damage' => 'Damage', 'theft' => 'Theft', 'count_variance' => 'Count variance', 'expiry' => 'Expiry', 'recall' => 'Recall', 'other' => 'Other'];
    $typeLabel = $types[$adjustment->adjustment_type] ?? \Illuminate\Support\Str::headline($adjustment->adjustment_type);
    [$stageText, $stageTone] = match ($adjustment->status) {
        'draft' => ['Draft', 'grey'],
        'posted' => ['Posted', 'green'],
        'cancelled' => ['Cancelled', 'red'],
        default => [\Illuminate\Support\Str::headline($adjustment->status), 'amber'],
    };
    $money = fn ($value) => number_format((float) $value, 2);
    $signed = fn ($value, int $decimals = 2) => ((float) $value > 0 ? '+' : '').number_format((float) $value, $decimals);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
    $signedQty = fn ($n) => ((float) $n > 0 ? '+' : '').$qty($n);
    $totalValue = (float) $adjustment->items->sum('adjustment_value') + (float) collect($costChanges)->sum('value');
    $totalQty = (float) $adjustment->items->sum('adjustment_quantity');
    $supplierName = $adjustment->items->first()?->product?->supplier?->supplier_name;
    $pendingCostCount = collect($pending)->filter(fn ($p) => $p['cost'] ?? null)->count();
    $pendingSellingCount = collect($pending)->filter(fn ($p) => $p['selling'] ?? null)->count();
    $canPost = $isDraft && auth()->user()->can('stock-adjustment-post');
    $canEdit = $isDraft && auth()->user()->can('stock-adjustment-edit');
    $canDelete = $isDraft && auth()->user()->can('stock-adjustment-delete');

    // Timeline: what happened to this adjustment, oldest first.
    $timeline = collect([
        ['at' => $adjustment->created_at, 'text' => 'Created as draft'.($adjustment->createdBy ? ' by '.$adjustment->createdBy->name : ''), 'tone' => 'grey'],
        $adjustment->updated_at && $adjustment->created_at && $adjustment->updated_at->gt($adjustment->created_at->copy()->addMinute()) && ! $adjustment->posted_at
            ? ['at' => $adjustment->updated_at, 'text' => 'Draft last changed', 'tone' => 'amber'] : null,
        $adjustment->posted_at ? ['at' => $adjustment->posted_at, 'text' => 'Posted'.($adjustment->postedBy ? ' by '.$adjustment->postedBy->name : '').': stock, valuation and the journal updated', 'tone' => 'green'] : null,
    ])->filter()->filter(fn ($event) => $event['at'])->sortBy('at')->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Stock Adjustment '.$adjustment->adjustment_number" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="stock-adjustments.index">
            <x-slot name="actions">
                <a href="{{ route('stock-adjustments.help') }}" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition ease-in-out duration-150 no-print"
                    title="How it works, in English and Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                    <span class="sr-only">How it works</span>
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <style>
        .gs-wrap { max-width: 80rem; margin: 0 auto; padding: 20px 16px 64px; display: flex; flex-direction: column; gap: 16px; }
        @media (min-width: 640px) { .gs-wrap { padding-left: 24px; padding-right: 24px; } }
        .gs-wrap, .gs-wrap * { min-width: 0; }
        .gs-card { background: #fff; border-radius: 20px; padding: 20px; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.05), 0 8px 24px -8px rgba(0,0,0,.14); }
        .gs-card-title { margin: 0 0 4px; font-size: 17px; font-weight: 600; letter-spacing: -.01em; color: #1d1d1f; }
        .gs-card-sub { margin: 0; font-size: 13px; color: #6e6e73; }
        .gs-muted { color: #6e6e73; }
        .gs-wrap a { color: #0066cc; text-decoration: none; }
        .gs-wrap a:hover { text-decoration: underline; }

        /* Status capsule, as on the list */
        .gs-stage { display: inline-flex; align-items: center; gap: 6px; padding: 3px 11px; border-radius: 980px; font-size: 12.5px; font-weight: 600; white-space: nowrap; }
        .gs-stage::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .gs-stage.gs-plain::before { display: none; }
        .gs-green { background: rgba(52,199,89,.14); color: #1f7a35; }
        .gs-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .gs-red { background: rgba(255,59,48,.12); color: #c4161c; }
        .gs-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
        .gs-blue { background: rgba(0,113,227,.12); color: #0058b0; }
        .sa-up { color: #1f7a35 !important; } .sa-down { color: #c4161c !important; }

        /* Hero */
        .gs-hero-top { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .gs-amount { margin: 14px 0 0; font-size: 40px; font-weight: 600; letter-spacing: -.03em; line-height: 1.05; color: #1d1d1f; font-variant-numeric: tabular-nums; }
        .gs-amount small { font-size: 20px; font-weight: 500; color: #6e6e73; margin-right: 4px; }
        .gs-who { margin: 8px 0 0; font-size: 15px; line-height: 1.5; color: #424245; }
        .gs-who b { font-weight: 600; color: #1d1d1f; }
        .gs-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin: 18px 0 0; border-radius: 14px; background: #f5f5f7; }
        .gs-stats > div { padding: 12px 14px; }
        .gs-stats > div + div { box-shadow: inset 1px 0 0 rgba(60,60,67,.12); }
        .gs-stats dt { margin: 0; font-size: 12px; color: #6e6e73; }
        .gs-stats dd { margin: 2px 0 0; font-size: 16px; font-weight: 600; color: #1d1d1f; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .gs-stats dd small { display: block; font-size: 11.5px; font-weight: 400; color: #6e6e73; }
        .gs-hero-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; }
        .gs-meta { margin: 0; font-size: 12.5px; color: #6e6e73; }
        .gs-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .gs-actions form { margin: 0; }
        .gs-pill { display: inline-flex; align-items: center; justify-content: center; height: 36px; padding: 0 16px; border: 0; border-radius: 980px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none !important; transition: transform .12s ease, background-color .15s ease; }
        .gs-pill:active:not(:disabled) { transform: scale(.97); }
        .gs-pill:disabled { opacity: .4; cursor: not-allowed; }
        .gs-pill-green { background: #34c759; color: #fff !important; }
        .gs-pill-grey { background: rgba(120,120,128,.14); color: #1d1d1f !important; }
        .gs-pill-danger { background: rgba(255,59,48,.12); color: #d70015 !important; }

        /* Banners */
        .gs-banner { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px 16px; padding: 14px 18px; border-radius: 16px; font-size: 14px; line-height: 1.45; color: #1d1d1f; box-shadow: 0 0 0 1px rgba(0,0,0,.03), 0 6px 18px -8px rgba(0,0,0,.12); }
        .gs-banner > span { display: flex; gap: 10px; flex: 1 1 280px; }
        .gs-banner > span::before { content: ''; flex: none; width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; }
        .gs-banner-amber { background: #fff8eb; } .gs-banner-amber > span::before { background: #ff9f0a; }

        /* Lines table: fits the card, never scrolls sideways */
        .gs-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; line-height: 1.35; table-layout: auto; }
        .gs-table th { padding: 8px; font-size: 11.5px; font-weight: 600; color: #6e6e73; text-align: left; border-bottom: 1px solid rgba(60,60,67,.18); white-space: nowrap; }
        .gs-table td { padding: 9px 8px; border-bottom: 1px solid rgba(60,60,67,.1); vertical-align: top; color: #1d1d1f; overflow-wrap: anywhere; }
        .gs-table tbody tr:hover td { background: #fafafc; }
        .gs-table .gs-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .gs-table .gs-c { text-align: center; color: #6e6e73; width: 36px; }
        .gs-table tfoot td { border-bottom: 0; border-top: 1px solid rgba(60,60,67,.18); font-weight: 600; }
        .gs-name { font-weight: 600; }
        .gs-code { margin-left: 6px; font-size: 12px; color: #6e6e73; font-weight: 400; }
        .gs-hint { display: block; margin-top: 3px; font-size: 12px; color: #6e6e73; font-weight: 400; }
        .sa-cost { display: inline-flex; align-items: center; gap: 6px; padding: 2px 8px; border-radius: 980px; background: rgba(255,159,10,.16); color: #8a4b00; font-weight: 600; }
        .sa-cost s { color: #b45309; font-weight: 400; }

        .gs-items-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
        .gs-tools { display: flex; flex-wrap: wrap; gap: 8px; }
        .gs-tools input, .gs-tools select { height: 34px; border: 0 !important; border-radius: 10px !important; background-color: rgba(118,118,128,.12) !important; font-size: 13.5px; color: #1d1d1f; box-shadow: none !important; }
        .gs-tools input { width: 220px; max-width: 100%; padding: 0 12px; }
        .gs-tools select { padding: 0 32px 0 12px; }
        .gs-tools input:focus, .gs-tools select:focus { outline: none; background-color: #fff !important; box-shadow: 0 0 0 4px rgba(0,113,227,.22), 0 0 0 1px #0071e3 !important; }
        .gs-found { margin: 0 0 8px; padding: 8px 12px; border-radius: 10px; background: rgba(0,113,227,.08); font-size: 13px; color: #424245; }
        .gs-found button { margin-left: 8px; border: 0; background: none; color: #0066cc; font-weight: 600; cursor: pointer; }
        .gs-none { margin: 0; padding: 18px; text-align: center; color: #6e6e73; font-size: 13px; }
        .gs-notes { margin: 12px 0 0; padding: 10px 14px; border-radius: 12px; background: #f5f5f7; font-size: 13.5px; color: #424245; }

        /* Details, Accounting, Timeline */
        .gs-trio { display: grid; gap: 16px; grid-template-columns: 1fr; }
        @media (min-width: 768px) { .gs-trio { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1100px) { .gs-trio { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .gs-rows { margin: 10px 0 0; padding: 0; list-style: none; }
        .gs-rows li { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid rgba(60,60,67,.1); font-size: 14px; }
        .gs-rows li:last-child { border-bottom: 0; }
        .gs-rows li > span:last-child { text-align: right; font-weight: 600; color: #1d1d1f; }
        .gs-rows small { display: block; margin-top: 2px; font-size: 12.5px; color: #6e6e73; font-weight: 400; }
        .gs-gl { margin: 10px 0 0; padding: 10px 12px; border-radius: 12px; background: #f5f5f7; font-size: 13px; line-height: 1.6; }
        .gs-empty { margin: 10px 0 0; font-size: 14px; color: #6e6e73; }
        .gs-feed { position: relative; margin: 12px 0 0; padding: 0; list-style: none; }
        .gs-feed li { position: relative; padding: 0 0 14px 22px; font-size: 13.5px; line-height: 1.4; }
        .gs-feed li:last-child { padding-bottom: 0; }
        .gs-feed li::before { content: ''; position: absolute; left: 5px; top: 14px; bottom: -2px; width: 2px; background: rgba(60,60,67,.12); }
        .gs-feed li:last-child::before { display: none; }
        .gs-feed i { position: absolute; left: 0; top: 3px; width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 0 3px #fff; }
        .gs-feed i.gs-grey { background: #aeaeb2; } .gs-feed i.gs-green { background: #34c759; } .gs-feed i.gs-amber { background: #ff9f0a; }
        .gs-feed small { display: block; margin-top: 2px; font-size: 12px; color: #6e6e73; }

        /* Phone: hero stats in two rows, each line becomes a card */
        @media (max-width: 640px) {
            .gs-wrap { padding-top: 12px; gap: 12px; }
            .gs-card { padding: 16px; border-radius: 16px; }
            .gs-amount { font-size: 32px; }
            .gs-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .gs-stats > div:nth-child(3) { box-shadow: inset 0 1px 0 rgba(60,60,67,.12); }
            .gs-stats > div:nth-child(4) { box-shadow: inset 1px 0 0 rgba(60,60,67,.12), inset 0 1px 0 rgba(60,60,67,.12); }
            .gs-actions { width: 100%; }
            .gs-actions > *, .gs-actions form { flex: 1 1 calc(50% - 4px); }
            .gs-actions form .gs-pill { width: 100%; }
            .gs-tools, .gs-tools > * { width: 100%; }
            .gs-tools input { width: 100%; }
            .gs-cards thead { display: none; }
            .gs-cards, .gs-cards tbody, .gs-cards tr, .gs-cards td, .gs-cards tfoot { display: block; width: 100%; }
            .gs-cards tbody tr { padding: 10px 0; border-bottom: 1px solid rgba(60,60,67,.12); }
            .gs-cards tbody tr:hover td { background: transparent; }
            .gs-cards td { padding: 2px 0; border: 0; text-align: left !important; }
            .gs-cards td[data-label]:not([data-label=""])::before { content: attr(data-label); display: inline-block; min-width: 92px; font-size: 12px; color: #6e6e73; }
            .gs-cards td.gs-num { display: flex; justify-content: space-between; white-space: normal; }
            .gs-cards td.gs-c { display: none; }
            .gs-cards tfoot tr { display: flex; justify-content: space-between; padding-top: 10px; }
            .gs-cards tfoot td { width: auto; border: 0; }
            .gs-cards tfoot td:empty { display: none; }
        }

        @media print {
            @page { margin: 10mm; }
            .gs-wrap { max-width: none; padding: 0; }
            .gs-hero, .gs-trio, .gs-no-print, .gs-tools, .gs-found { display: none !important; }
            .gs-card { box-shadow: none; padding: 0; border-radius: 0; }
            .gs-items-head { display: none; }
            .gs-items { font-size: 9.5px; }
            .gs-items th, .gs-items td { border: 1px solid #000; padding: 3px 5px; color: #000; }
            .sa-cost { background: none; color: #000; padding: 0; }
            .gs-sign { display: flex !important; }
        }
        .gs-sign { display: none; justify-content: space-between; margin-top: 40px; font-size: 10px; }
        .gs-sign span { border-top: 1px solid #000; padding-top: 4px; width: 28%; text-align: center; }
    </style>

    <div class="gs-wrap" x-data="{ confirmDelete: false }">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Stock Adjustment</div>
            <table class="ak-print-meta">
                <tr><th>Adjustment No.</th><td>{{ $adjustment->adjustment_number }}</td><th>Date</th><td>{{ $adjustment->adjustment_date?->format('d-M-Y') }}</td></tr>
                <tr><th>Status</th><td>{{ $stageText }}{{ $adjustment->posted_at ? ' ('.$adjustment->posted_at->format('d-M-Y h:i A').')' : '' }}</td><th>Warehouse</th><td>{{ $adjustment->warehouse->warehouse_name ?? '—' }}</td></tr>
                <tr><th>Type</th><td>{{ $typeLabel }}</td><th>Supplier</th><td>{{ $supplierName ?? '—' }}</td></tr>
                <tr><th>Reason</th><td colspan="3">{{ $adjustment->reason ?: '—' }}</td></tr>
                <tr><th>Printed</th><td colspan="3">{{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</td></tr>
            </table>
        </div>

        <x-status-message />
        @error('password')
            <div class="ak-alert ak-alert-error" role="alert">{{ $message }}</div>
        @enderror

        {{-- The adjustment at a glance --}}
        <section class="gs-card gs-hero" aria-label="Adjustment summary">
            <div class="gs-hero-top">
                <span class="gs-stage gs-{{ $stageTone }}">{{ $stageText }}</span>
                <span class="gs-stage gs-grey gs-plain">{{ $typeLabel }}</span>
                @if ($supplierName)
                    <span class="gs-stage gs-blue gs-plain">{{ $supplierName }}</span>
                @endif
            </div>

            <p class="gs-amount {{ $totalValue < 0 ? 'sa-down' : '' }}" title="Rs {{ $signed($totalValue) }}"><small>Rs</small>{{ $signed($totalValue) }}</p>
            <p class="gs-who">
                <b>{{ $adjustment->warehouse->warehouse_name ?? '—' }}</b> &middot; {{ $adjustment->adjustment_date?->format('l, d M Y') }}
                <br>{{ $adjustment->reason ?: '—' }}
            </p>

            <dl class="gs-stats">
                <div><dt>Lines</dt><dd>{{ number_format($adjustment->items->count()) }}<small>products and batches</small></dd></div>
                <div><dt>Qty change</dt><dd class="{{ $totalQty < 0 ? 'sa-down' : ($totalQty > 0 ? 'sa-up' : '') }}">{{ $signedQty($totalQty) }}<small>units</small></dd></div>
                <div><dt>Cost changes</dt><dd>{{ $isDraft ? $pendingCostCount : count($costChanges) }}<small>{{ $isDraft ? 'on posting' : 'batches revalued' }}</small></dd></div>
                <div><dt>Selling cost changes</dt><dd>{{ $isDraft ? $pendingSellingCount : count($sellingChanges) }}<small>{{ $isDraft ? 'on posting' : 'prices changed' }}</small></dd></div>
            </dl>

            <div class="gs-hero-foot">
                <p class="gs-meta">
                    Created by {{ $adjustment->createdBy->name ?? '—' }} {{ $adjustment->created_at?->format('d M Y, h:i A') }}
                    @if ($adjustment->posted_at) &middot; posted by {{ $adjustment->postedBy->name ?? '—' }} {{ $adjustment->posted_at->format('d M Y, h:i A') }} @endif
                    &middot; updated {{ $adjustment->updated_at?->diffForHumans() }}
                </p>
                <div class="gs-actions no-print">
                    @if ($canEdit)
                        <a href="{{ route('stock-adjustments.edit', $adjustment) }}" class="gs-pill gs-pill-grey">Edit</a>
                    @endif
                    @if ($canDelete)
                        <button type="button" class="gs-pill gs-pill-danger" @click="confirmDelete = true">Delete draft</button>
                    @endif
                    @if ($canPost)
                        <button type="button" class="gs-pill gs-pill-green" onclick="window.showPasswordModal('saPostModal')">Post Adjustment</button>
                    @endif
                </div>
            </div>
        </section>

        @if ($isDraft)
            <div class="gs-banner gs-banner-amber gs-no-print" role="status">
                <span><span><b>Draft.</b> Nothing has moved yet. Posting changes stock, the inventory ledger, valuation and the journal{{ $pendingCostCount || $pendingSellingCount ? ', and applies the cost changes marked “on posting” below' : '' }}.</span></span>
            </div>
        @endif

        {{-- Lines --}}
        <section class="gs-card" aria-labelledby="sa-items" x-data="saItems()" @beforeprint.window="search = ''">
            <div class="gs-items-head">
                <div>
                    <h2 class="gs-card-title" id="sa-items">Items adjusted</h2>
                    <p class="gs-card-sub">{{ $isDraft ? 'Draft: what posting will change. Selling cost is the price it sells at; unit cost is what it was bought at.' : 'What this adjustment changed. Selling cost is the price it sells at; unit cost is what it was bought at.' }}</p>
                </div>
                @if ($adjustment->items->count() > 1)
                    <div class="gs-tools gs-no-print">
                        <input type="search" x-model="search" placeholder="Find product or batch" aria-label="Find product" @keydown.escape="search = ''">
                        <select x-model="sort" aria-label="Sort items">
                            <option value="line">Sort: line #</option>
                            <option value="value">Value: largest change</option>
                            <option value="qty">Quantity: largest change</option>
                            <option value="name">Name: A → Z</option>
                        </select>
                    </div>
                @endif
            </div>
            <p class="gs-found gs-no-print" x-show="search.trim() !== ''" x-cloak>
                <span x-text="shown + ' of {{ $adjustment->items->count() }} lines'"></span> &middot; Rs <b x-text="shownValue"></b>
                <button type="button" @click="search = ''">Clear</button>
            </p>
            <table class="gs-table gs-items gs-cards">
                <thead>
                    <tr>
                        <th scope="col" class="gs-c">#</th>
                        <th scope="col">Product / batch</th>
                        <th scope="col" class="gs-num">System qty</th>
                        <th scope="col" class="gs-num">Actual qty</th>
                        <th scope="col" class="gs-num">Difference</th>
                        <th scope="col" class="gs-num">Selling cost</th>
                        <th scope="col" class="gs-num">Unit cost</th>
                        <th scope="col" class="gs-num">Value (Rs)</th>
                    </tr>
                </thead>
                <tbody x-ref="rows">
                    @foreach ($adjustment->items as $item)
                        @php
                            $change = $costChanges[$item->id] ?? null;
                            $lineValue = (float) $item->adjustment_value + (float) ($change['value'] ?? 0);
                            $sellChange = $sellingChanges[$item->id] ?? null;
                            $pend = $pending[$item->id] ?? null;
                            $haystack = mb_strtolower(($item->product->product_name ?? '').' '.($item->product->product_code ?? '').' '.($item->stockBatch->batch_code ?? ''));
                        @endphp
                        <tr data-line="{{ $loop->iteration }}" data-value="{{ abs($lineValue) }}" data-signed="{{ $lineValue }}" data-qty="{{ abs((float) $item->adjustment_quantity) }}"
                            data-name="{{ mb_strtolower($item->product->product_name ?? '') }}" data-find="{{ $haystack }}">
                            <td class="gs-c">{{ $loop->iteration }}</td>
                            <td data-label="">
                                <span class="gs-name">{{ $item->product->product_name ?? '—' }}</span>
                                @if ($item->product && mb_strtoupper(trim((string) $item->product->product_code)) !== mb_strtoupper(trim((string) $item->product->product_name)))
                                    <span class="gs-code">{{ $item->product->product_code }}</span>
                                @endif
                                <span class="gs-hint">{{ $item->stockBatch->batch_code ?? 'no batch' }}{{ $item->uom ? ' · '.$item->uom->uom_name : '' }}</span>
                            </td>
                            <td class="gs-num" data-label="System qty">{{ number_format((float) $item->system_quantity, 3) }}</td>
                            <td class="gs-num" data-label="Actual qty">{{ number_format((float) $item->actual_quantity, 3) }}</td>
                            <td class="gs-num {{ $item->adjustment_quantity > 0 ? 'sa-up' : ($item->adjustment_quantity < 0 ? 'sa-down' : '') }}" data-label="Difference"><b>{{ $signed($item->adjustment_quantity, 3) }}</b></td>
                            <td class="gs-num" data-label="Selling cost">
                                <span>
                                    @if ($pend && $pend['selling'])
                                        <span class="sa-cost" title="Applied when this adjustment is posted"><s>{{ $money($pend['selling']['old']) }}</s> → {{ $money($pend['selling']['new']) }}</span>
                                        <span class="gs-hint">on posting{{ $pend['selling']['all'] ? ' · all batches' : '' }}</span>
                                    @elseif ($sellChange)
                                        <span class="sa-cost" title="Selling cost changed with this adjustment"><s>{{ $money($sellChange['old']) }}</s> → {{ $money($sellChange['new']) }}</span>
                                        <span class="gs-hint">selling cost changed</span>
                                    @else
                                        {{ $money($sellingPrices[$item->stock_batch_id] ?? 0) }}
                                        <span class="gs-hint">current</span>
                                    @endif
                                </span>
                            </td>
                            <td class="gs-num" data-label="Unit cost">
                                <span>
                                    @if ($pend && $pend['cost'])
                                        <span class="sa-cost" title="Applied when this adjustment is posted"><s>{{ $money($pend['cost']['old']) }}</s> → {{ $money($pend['cost']['new']) }}</span>
                                        <span class="gs-hint">on posting</span>
                                    @elseif ($change)
                                        <span class="sa-cost" title="Unit cost changed by this adjustment"><s>{{ $money($change['old']) }}</s> → {{ $money($change['new']) }}</span>
                                        <span class="gs-hint">cost changed</span>
                                    @else
                                        {{ $money($item->unit_cost) }}
                                    @endif
                                </span>
                            </td>
                            <td class="gs-num {{ $lineValue < 0 ? 'sa-down' : ($lineValue > 0 ? 'sa-up' : '') }}" data-label="Value (Rs)">
                                <span><b>{{ $signed($lineValue) }}</b>@if ($change)<span class="gs-hint">{{ $signed($change['value']) }} from cost</span>@endif</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Total &middot; {{ $adjustment->items->count() }} {{ \Illuminate\Support\Str::plural('line', $adjustment->items->count()) }}</td>
                        <td class="gs-num {{ $totalQty < 0 ? 'sa-down' : '' }}">{{ $signed($totalQty, 3) }}</td>
                        <td></td>
                        <td></td>
                        <td class="gs-num {{ $totalValue < 0 ? 'sa-down' : '' }}">{{ $signed($totalValue) }}</td>
                    </tr>
                </tfoot>
            </table>
            <p class="gs-none gs-no-print" x-show="search.trim() !== '' && shown === 0" x-cloak>No product matches “<span x-text="search"></span>”.</p>
            @if ($adjustment->notes)
                <p class="gs-notes gs-no-print"><b>Notes:</b> {{ $adjustment->notes }}</p>
            @endif
            <div class="gs-sign"><span>Prepared by</span><span>Store keeper</span><span>Approved by</span></div>
        </section>

        <div class="gs-trio">
            {{-- Details --}}
            <section class="gs-card" aria-labelledby="sa-details">
                <h2 class="gs-card-title" id="sa-details">Details</h2>
                <ul class="gs-rows">
                    <li><span class="gs-muted">Number</span><span>{{ $adjustment->adjustment_number }}</span></li>
                    <li><span class="gs-muted">Date</span><span>{{ $adjustment->adjustment_date?->format('d M Y') }}</span></li>
                    <li><span class="gs-muted">Type</span><span>{{ $typeLabel }}</span></li>
                    <li><span class="gs-muted">Warehouse</span><span>{{ $adjustment->warehouse->warehouse_name ?? '—' }}</span></li>
                    <li><span class="gs-muted">Reason</span><span>{{ $adjustment->reason ?: '—' }}</span></li>
                    @if ($adjustment->notes)
                        <li><span class="gs-muted">Notes</span><span>{{ $adjustment->notes }}</span></li>
                    @endif
                </ul>
            </section>

            {{-- Accounting --}}
            <section class="gs-card" aria-labelledby="sa-accounting">
                <h2 class="gs-card-title" id="sa-accounting">Accounting</h2>
                @if ($adjustment->journalEntry)
                    <ul class="gs-rows">
                        <li>
                            <span>
                                @can('journal-entry-list')
                                    <a href="{{ route('journal-entries.show', $adjustment->journalEntry) }}"><b>JE #{{ $adjustment->journalEntry->id }}</b></a>
                                @else
                                    <b>JE #{{ $adjustment->journalEntry->id }}</b>
                                @endcan
                                <small>{{ $adjustment->journalEntry->reference }} &middot; {{ $adjustment->journalEntry->entry_date?->format('d M Y') }} &middot; {{ \Illuminate\Support\Str::headline((string) $adjustment->journalEntry->status) }}</small>
                            </span>
                            <span style="white-space:nowrap; font-variant-numeric:tabular-nums">Rs {{ number_format((float) $adjustment->journalEntry->total_debit) }}</span>
                        </li>
                    </ul>
                @else
                    <p class="gs-empty">{{ $isDraft ? 'Posting writes a journal entry between Stock in Hand and the stock loss / gain account for the type.' : 'No journal entry is linked to this adjustment (for example a selling-cost-only change).' }}</p>
                @endif
                <div class="gs-gl">
                    A shortage credits Stock in Hand; an excess or a higher unit cost debits it. Selling cost changes move no money.
                </div>
            </section>

            {{-- Timeline --}}
            <section class="gs-card" aria-labelledby="sa-timeline">
                <h2 class="gs-card-title" id="sa-timeline">Timeline</h2>
                <ul class="gs-feed">
                    @foreach ($timeline as $event)
                        <li><i class="gs-{{ $event['tone'] }}" aria-hidden="true"></i>{{ $event['text'] }}<small>{{ $event['at']->format('d M Y, h:i A') }} &middot; {{ $event['at']->diffForHumans() }}</small></li>
                    @endforeach
                </ul>
            </section>
        </div>

        @if ($canDelete)
            {{-- Delete draft confirmation --}}
            <div class="uf-modal" x-show="confirmDelete" x-cloak style="display:none" @keydown.escape.window="confirmDelete = false" role="dialog" aria-modal="true" aria-labelledby="sa-delete-title">
                <div class="uf-modal-bg" x-show="confirmDelete" x-transition.opacity @click="confirmDelete = false"></div>
                <div class="uf-modal-box" x-show="confirmDelete" x-transition>
                    <div class="uf-modal-body">
                        <span class="uf-modal-icon ak-pill-red" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                        </span>
                        <div>
                            <h3 id="sa-delete-title">Delete draft {{ $adjustment->adjustment_number }}?</h3>
                            <p style="margin:8px 0 0; font-size:14px; color:#334155">The draft and its lines are removed. Nothing had moved yet, so stock and the journal are unchanged.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('stock-adjustments.destroy', $adjustment) }}" class="uf-modal-foot">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="ak-btn ak-btn-outline" @click="confirmDelete = false">Cancel</button>
                        <button type="submit" class="ak-btn ak-btn-danger-outline">Delete draft</button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    @if ($canPost)
        {{-- Posting asks for the password; the confirmed password is sent as "password" to stock-adjustments.post. --}}
        <form method="POST" action="{{ route('stock-adjustments.post', $adjustment) }}" id="sa-post-form" class="hidden">
            @csrf
            <input type="hidden" name="password" id="sa-post-password">
        </form>
        <x-password-confirm-modal id="saPostModal" title="Post Stock Adjustment {{ $adjustment->adjustment_number }}"
            message="Posting moves stock, writes the inventory ledger and valuation, applies any cost changes and creates the journal entry. It cannot be edited afterwards."
            error-key="password" confirm-button-text="Post Adjustment" />
        <script>
            document.addEventListener('passwordConfirmed', function (event) {
                if (event.detail.modalId !== 'saPostModal') { return; }
                document.getElementById('sa-post-password').value = event.detail.password;
                document.getElementById('sa-post-form').submit();
            });
        </script>
    @endif

    <script>
        /** Client-side find and sort for the adjusted lines (rows stay server-rendered). */
        function saItems() {
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
                        if (match) { shown++; value += parseFloat(row.dataset.signed) || 0; }
                        body.appendChild(row);
                    });
                    this.shown = shown;
                    this.shownValue = value.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
</x-app-layout>
