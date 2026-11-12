{{--
    Goods Issues -> View (/goods-issues/{goodsIssue}), in the Apple look of the Goods Issues list:
    a hero card (stage, value, who and where), the issued items full width, then Settlement,
    Accounting and Timeline side by side. Fits any width without sideways scrolling; on a phone
    the items become cards. Prints as a formal issue note; ?format=pdf downloads it as A4.
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
    $settlements = $gi->settlement;
    $settlementTone = fn (?string $status) => match ($status) {
        'posted', 'verified' => 'green',
        'draft' => 'amber',
        default => 'red',
    };

    // The same stage capsule as the Goods Issues list.
    $latestSettlement = $settlements->last();
    $isSettled = $settlements->contains(fn ($settlement) => in_array($settlement->status, ['verified', 'posted'], true));
    $daysOut = $gi->status === 'issued' && $settlements->isEmpty() ? (int) abs($gi->issue_date?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0) : null;
    [$stageText, $stageTone] = match (true) {
        $isDraft => ['Draft', 'grey'],
        $isReversed => ['Reversed', 'red'],
        $isSettled => ['Settled', 'green'],
        $settlements->isNotEmpty() => ['Settlement draft', 'amber'],
        default => ['On van · '.($daysOut === 0 ? 'today' : $daysOut.' '.\Illuminate\Support\Str::plural('day', $daysOut)), $daysOut >= 2 ? 'red' : 'amber'],
    };
    $helpTopic = match (true) {
        $isDraft => 'gd-draft',
        $isReversed => 'gd-reversed',
        default => 'gd-stage',
    };
    $employeeUrl = $gi->employee && auth()->user()->can('employee-list') ? route('employees.show', $gi->employee) : null;
    $vehicleUrl = $gi->vehicle && auth()->user()->can('vehicle-list') ? route('vehicles.show', $gi->vehicle) : null;

    // Timeline: what happened to this issue, oldest first.
    $timeline = collect([
        ['at' => $gi->created_at, 'text' => ($gi->replaces ? 'Copied as draft from reversed '.$gi->replaces->issue_number.($gi->replaces->reversedBy ? ' by '.$gi->replaces->reversedBy->name : '') : 'Created as draft').($gi->issuedBy ? ($gi->replaces ? ', owned by ' : ' by ').$gi->issuedBy->name : ''), 'tone' => 'grey'],
        $gi->posted_at ? ['at' => $gi->posted_at, 'text' => 'Posted: stock moved from '.($gi->warehouse->warehouse_name ?? 'warehouse').' to vehicle '.($gi->vehicle->vehicle_number ?? ''), 'tone' => 'blue'] : null,
    ])->filter()
        ->merge($supplementary->groupBy(fn ($item) => optional($item->supplementary_posted_at)->format('Y-m-d H:i'))->map(fn ($items) => [
            'at' => $items->first()->supplementary_posted_at ?? $items->first()->created_at,
            'text' => $items->count().' supplementary '.\Illuminate\Support\Str::plural('line', $items->count()).' added',
            'tone' => 'purple',
        ])->values())
        ->push($isReversed ? ['at' => $gi->reversed_at, 'text' => 'Reversed'.($gi->reversedBy ? ' by '.$gi->reversedBy->name : '').': stock returned to '.($gi->warehouse->warehouse_name ?? 'warehouse').($gi->replacement ? ', lines copied to '.$gi->replacement->issue_number : ''), 'tone' => 'red'] : null)
        ->filter()
        ->merge($gi->removals->map(fn ($removal) => [
            'at' => $removal->removed_at,
            'text' => 'Removed '.rtrim(rtrim(number_format((float) $removal->quantity, 3), '0'), '.').' of '.($removal->item->product->product_name ?? 'a line').': '.$removal->reason.($removal->removedBy ? ' ('.$removal->removedBy->name.')' : ''),
            'tone' => 'amber',
        ]))
        ->merge($settlements->map(fn ($settlement) => [
            'at' => $settlement->created_at,
            'text' => 'Settlement '.$settlement->settlement_number.' '.($settlement->status === 'draft' ? 'started (draft)' : $settlement->status),
            'tone' => $settlementTone($settlement->status),
        ]))
        ->filter(fn ($event) => $event['at'])
        ->sortBy('at')
        ->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Goods Issue '.$gi->issue_number" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="goods-issues.index">
            <x-slot name="actions">
                <x-register-header-actions page="goods-issue" :help="false" />
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: @js($helpTopic) })" aria-haspopup="dialog" aria-label="Help in Urdu" title="What this page shows, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="goods-issues" :has-supplier="true" />
    <style>
        .gs-wrap { max-width: 80rem; margin: 0 auto; padding: 20px 16px 64px; display: flex; flex-direction: column; gap: 16px; }
        @media (min-width: 640px) { .gs-wrap { padding-left: 24px; padding-right: 24px; } }
        .gs-wrap, .gs-wrap * { min-width: 0; }
        .gs-card { background: #fff; border-radius: 20px; padding: 20px; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.05), 0 8px 24px -8px rgba(0,0,0,.14); }
        .gs-banner { box-shadow: 0 0 0 1px rgba(0,0,0,.03), 0 6px 18px -8px rgba(0,0,0,.12); }
        #stock-check .gs-banner { box-shadow: none; }
        .gs-card-title { margin: 0 0 4px; font-size: 17px; font-weight: 600; letter-spacing: -.01em; color: #1d1d1f; }
        .gs-card-sub { margin: 0; font-size: 13px; color: #6e6e73; }
        .gs-muted { color: #6e6e73; }
        .gs-wrap a { color: #0066cc; text-decoration: none; }
        .gs-wrap a:hover { text-decoration: underline; }

        /* Stage capsule, as on the list */
        .gs-stage { display: inline-flex; align-items: center; gap: 6px; padding: 3px 11px; border-radius: 980px; font-size: 12.5px; font-weight: 600; white-space: nowrap; }
        .gs-stage::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .gs-green { background: rgba(52,199,89,.14); color: #1f7a35; }
        .gs-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .gs-red { background: rgba(255,59,48,.12); color: #c4161c; }
        .gs-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
        .gs-blue { background: rgba(0,113,227,.12); color: #0058b0; }
        .gs-purple { background: rgba(175,82,222,.14); color: #7a2fa3; }

        /* Hero */
        .gs-hero-top { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .gs-hero-top a.gs-stage { text-decoration: none; }
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
        .gs-pill { display: inline-flex; align-items: center; justify-content: center; height: 36px; padding: 0 16px; border: 0; border-radius: 980px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none !important; transition: transform .12s ease, background-color .15s ease; }
        .gs-pill:active:not(:disabled) { transform: scale(.97); }
        .gs-pill:disabled { opacity: .4; cursor: not-allowed; }
        .gs-pill-blue { background: #0071e3; color: #fff !important; }
        .gs-pill-blue:hover { background: #0077ed; }
        .gs-pill-green { background: #34c759; color: #fff !important; }
        .gs-pill-grey { background: rgba(120,120,128,.14); color: #1d1d1f !important; }
        .gs-pill-danger { background: rgba(255,59,48,.12); color: #d70015 !important; }
        .gs-pill-sm { height: 30px; padding: 0 12px; font-size: 13px; }

        /* Banners */
        .gs-banner { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px 16px; padding: 14px 18px; border-radius: 16px; font-size: 14px; line-height: 1.45; color: #1d1d1f; }
        .gs-banner > span { display: flex; gap: 10px; flex: 1 1 280px; }
        .gs-banner > span::before { content: ''; flex: none; width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; }
        .gs-banner-red { background: #fff1f0; } .gs-banner-red > span::before { background: #ff3b30; }
        .gs-banner-amber { background: #fff8eb; } .gs-banner-amber > span::before { background: #ff9f0a; }
        .gs-banner-green { background: #effbf2; } .gs-banner-green > span::before { background: #34c759; }

        /* Tables (items and stock check): fit the card, never scroll sideways */
        .gs-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; line-height: 1.35; table-layout: auto; }
        .gs-table th { padding: 8px; font-size: 11.5px; font-weight: 600; color: #6e6e73; text-align: left; border-bottom: 1px solid rgba(60,60,67,.18); white-space: nowrap; }
        .gs-table td { padding: 9px 8px; border-bottom: 1px solid rgba(60,60,67,.1); vertical-align: top; color: #1d1d1f; overflow-wrap: anywhere; }
        .gs-table tbody tr:hover td { background: #fafafc; }
        .gs-table .gs-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .gs-table .gs-c { text-align: center; color: #6e6e73; width: 36px; }
        .gs-table tfoot td { border-bottom: 0; border-top: 1px solid rgba(60,60,67,.18); font-weight: 600; }
        .gs-table tr.gs-row-red td { background: #fff5f4; }
        .gs-table tr.gs-row-amber td { background: #fffaf0; }
        .gs-name { font-weight: 600; }
        .gs-code { margin-left: 6px; font-size: 12px; color: #6e6e73; font-weight: 400; }
        .gs-ctn { display: block; font-size: 11.5px; color: #6e6e73; }
        .gs-batches { font-size: 12.5px; color: #6e6e73; line-height: 1.5; }
        .gs-batches b { color: #1d1d1f; font-weight: 600; }
        .gs-tag { display: inline-block; margin: 3px 4px 0 0; padding: 1px 8px; border-radius: 980px; font-size: 11px; font-weight: 600; }
        .gs-tag-promo { background: rgba(255,159,10,.16); color: #8a4b00; }
        .gs-tag-np { background: rgba(88,86,214,.13); color: #3f3cb0; }
        .gs-tag-supp { background: rgba(175,82,222,.14); color: #7a2fa3; }

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

        /* Settlement, Accounting, Timeline */
        .gs-trio { display: grid; gap: 16px; grid-template-columns: 1fr; }
        @media (min-width: 768px) { .gs-trio { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1100px) { .gs-trio { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .gs-rows { margin: 10px 0 0; padding: 0; list-style: none; }
        .gs-rows li { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid rgba(60,60,67,.1); font-size: 14px; }
        .gs-rows li:last-child { border-bottom: 0; }
        .gs-rows small { display: block; margin-top: 2px; font-size: 12.5px; color: #6e6e73; }
        .gs-gl { margin: 10px 0 0; padding: 10px 12px; border-radius: 12px; background: #f5f5f7; font-size: 13px; line-height: 1.6; }
        .gs-gl div { display: flex; gap: 8px; }
        .gs-gl b { flex: none; width: 22px; color: #6e6e73; font-weight: 600; }
        .gs-empty { margin: 10px 0 0; font-size: 14px; color: #6e6e73; }
        .gs-feed { position: relative; margin: 12px 0 0; padding: 0; list-style: none; }
        .gs-feed li { position: relative; padding: 0 0 14px 22px; font-size: 13.5px; line-height: 1.4; }
        .gs-feed li:last-child { padding-bottom: 0; }
        .gs-feed li::before { content: ''; position: absolute; left: 5px; top: 14px; bottom: -2px; width: 2px; background: rgba(60,60,67,.12); }
        .gs-feed li:last-child::before { display: none; }
        .gs-feed i { position: absolute; left: 0; top: 3px; width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 0 3px #fff; }
        .gs-feed i.gs-grey { background: #aeaeb2; } .gs-feed i.gs-blue { background: #0071e3; } .gs-feed i.gs-green { background: #34c759; }
        .gs-feed i.gs-amber { background: #ff9f0a; } .gs-feed i.gs-red { background: #ff3b30; } .gs-feed i.gs-purple { background: #af52de; }
        .gs-feed small { display: block; margin-top: 2px; font-size: 12px; color: #6e6e73; }

        /* Tablet: the batches column moves under the product name */
        @media (max-width: 900px) {
            .gs-items .gs-col-batches { display: none; }
            .gs-items .gs-batches-inline { display: block !important; margin-top: 4px; }
        }
        .gs-items .gs-batches-inline { display: none; }

        /* Phone: hero stats in two rows, each item and stock line becomes a card */
        @media (max-width: 640px) {
            .gs-wrap { padding-top: 12px; gap: 12px; }
            .gs-card { padding: 16px; border-radius: 16px; }
            .gs-amount { font-size: 32px; }
            .gs-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .gs-stats > div:nth-child(3) { box-shadow: inset 0 1px 0 rgba(60,60,67,.12); }
            .gs-stats > div:nth-child(4) { box-shadow: inset 1px 0 0 rgba(60,60,67,.12), inset 0 1px 0 rgba(60,60,67,.12); }
            .gs-actions { width: 100%; }
            .gs-actions > *, .gs-actions form { flex: 1 1 calc(50% - 4px); }
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
            .gs-items .gs-batches-inline { margin-left: 0; }
        }

        @media print {
            @page { margin: 10mm; }
            .gs-wrap { max-width: none; padding: 0; }
            .gs-hero, .gs-trio, .gs-no-print, #stock-check, .gs-tools, .gs-found { display: none !important; }
            .gs-card { box-shadow: none; padding: 0; border-radius: 0; }
            .gs-items-head { display: none; }
            .gs-items { font-size: 9.5px; }
            .gs-items th, .gs-items td { border: 1px solid #000; padding: 3px 5px; color: #000; }
            .gs-items .gs-col-batches { display: table-cell !important; }
            .gs-items .gs-batches-inline { display: none !important; }
            .gs-batches { font-size: 9px; color: #000; }
            .gs-tag { border: 1px solid #555; background: none !important; color: #000 !important; }
            .gs-sign { display: flex !important; }
        }
        .gs-sign { display: none; justify-content: space-between; margin-top: 40px; font-size: 10px; }
        .gs-sign span { border-top: 1px solid #000; padding-top: 4px; width: 28%; text-align: center; }
    </style>

    <div class="gs-wrap">
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

        {{-- The issue at a glance --}}
        <section class="gs-card gs-hero" aria-label="Issue summary">
            <div class="gs-hero-top">
                @if ($latestSettlement && auth()->user()->can('sales-settlement-list'))
                    <a href="{{ route('sales-settlements.show', $latestSettlement) }}" class="gs-stage gs-{{ $stageTone }}" title="Open {{ $latestSettlement->settlement_number }}">{{ $stageText }} · {{ $latestSettlement->settlement_number }}</a>
                @else
                    <span class="gs-stage gs-{{ $stageTone }}">{{ $stageText }}{{ $latestSettlement ? ' · '.$latestSettlement->settlement_number : '' }}</span>
                @endif
                <span class="gs-stage gs-blue" style="padding-left:11px">{{ $gi->supplier->supplier_name ?? 'No supplier' }}</span>
                @if ($supplementary->isNotEmpty())
                    <span class="gs-stage gs-purple">{{ $supplementary->count() }} supplementary</span>
                @endif
            </div>

            <p class="gs-amount" title="Rs {{ $rs($sellingValue) }}"><small>Rs</small>{{ number_format($sellingValue) }}</p>
            <p class="gs-who">
                @if ($employeeUrl)<a href="{{ $employeeUrl }}"><b>{{ $gi->employee->name }}</b></a>@else<b>{{ $gi->employee->name ?? '—' }}</b>@endif
                <span class="gs-muted">{{ $gi->employee->employee_code ?? '' }}</span>
                &middot; @if ($vehicleUrl)<a href="{{ $vehicleUrl }}">{{ $gi->vehicle->vehicle_number }}</a>@else{{ $gi->vehicle->vehicle_number ?? '—' }}@endif
                <span class="gs-muted">{{ $gi->vehicle->vehicle_type ?? '' }}</span>
                <br>{{ $gi->warehouse->warehouse_name ?? 'Warehouse' }} → van &middot; {{ $gi->issue_date?->format('l, d M Y') }}
            </p>

            <dl class="gs-stats">
                <div><dt>Lines</dt><dd>{{ number_format($gi->items->count()) }}<small>{{ $supplementary->isNotEmpty() ? $supplementary->count().' supplementary' : 'products' }}</small></dd></div>
                <div><dt>Quantity</dt><dd>{{ rtrim(rtrim(number_format($totalPieces, 3), '0'), '.') }}<small>pieces</small></dd></div>
                <div><dt>Cost</dt><dd>{{ number_format($cost) }}<small>{{ $postedCost > 0 ? 'as posted' : 'estimate' }}</small></dd></div>
                <div><dt>Margin</dt><dd>{{ number_format($margin) }}<small>{{ $sellingValue > 0 ? number_format($margin / $sellingValue * 100, 1).'% of value' : '—' }}</small></dd></div>
            </dl>

            <div class="gs-hero-foot">
                <p class="gs-meta">
                    Created by {{ $gi->issuedBy->name ?? '—' }} {{ $gi->created_at?->format('d M Y, h:i A') }}
                    @if ($gi->posted_at) &middot; posted {{ $gi->posted_at->format('d M Y, h:i A') }} @endif
                    &middot; updated {{ $gi->updated_at?->diffForHumans() }}
                </p>
                <div class="gs-actions no-print">
                    @if ($isDraft)
                        @can('goods-issue-edit')
                            <a href="{{ route('goods-issues.edit', $gi->id) }}" class="gs-pill gs-pill-grey">Edit</a>
                        @endcan
                        @can('goods-issue-post')
                            <button type="button" x-data class="gs-pill gs-pill-green"
                                @click="$dispatch('open-post-gi-modal', { url: '{{ route('goods-issues.post', $gi->id) }}' })">Post Issue</button>
                        @endcan
                    @endif
                    @if ($gi->status === 'issued')
                        @can('goods-issue-reverse')
                            <button type="button" x-data class="gs-pill gs-pill-danger"
                                @if ($reversalRefusal) disabled title="{{ $reversalRefusal }}" @endif
                                @click="$dispatch('open-reverse-gi-modal')">Reverse &amp; Re-issue</button>
                        @endcan
                    @endif
                    @if ($gi->canAcceptSupplementaryItems())
                        @canany(['goods-issue-edit', 'goods-issue-remove-items'])
                            <a href="{{ route('goods-issues.items', $gi) }}" class="gs-pill gs-pill-blue">Add or remove items</a>
                        @endcanany
                    @endif
                </div>
            </div>
        </section>

        @if ($isReversed)
            <div class="gs-banner gs-banner-red" role="status">
                <span>
                    <span>
                        <b>Reversed</b> on {{ $gi->reversed_at->format('d M Y, h:i A') }}{{ $gi->reversedBy ? ' by '.$gi->reversedBy->name : '' }}.
                        Stock went back to {{ $gi->warehouse->warehouse_name ?? 'the warehouse' }} and the journal entries were offset on {{ $gi->issue_date?->format('d M Y') }}.
                        <br><span class="gs-muted">Reason: {{ $gi->reversal_reason }}</span>
                    </span>
                </span>
                @if ($gi->replacement)
                    <a href="{{ route('goods-issues.show', $gi->replacement) }}" class="gs-pill gs-pill-sm gs-pill-grey gs-no-print">Replaced by {{ $gi->replacement->issue_number }} →</a>
                @endif
            </div>
        @endif
        @if ($gi->replaces)
            <div class="gs-banner gs-banner-amber gs-no-print" role="status">
                <span><span>Copied from reversed <b>{{ $gi->replaces->issue_number }}</b>{{ $isDraft ? '. Correct the salesman, vehicle or quantities, then post it. This draft holds the lock on vehicle '.($gi->vehicle->vehicle_number ?? '').' until its vehicle is changed or it is deleted.' : '.' }}</span></span>
                <a href="{{ route('goods-issues.show', $gi->replaces) }}" class="gs-pill gs-pill-sm gs-pill-grey">View {{ $gi->replaces->issue_number }} →</a>
            </div>
        @endif

        @if ($isDraft && $stockPositions->isNotEmpty())
            @php
                $shortPositions = $stockPositions->filter(fn ($p) => $p['short'] > 0.001 || $p['short_non_promotional'] > 0.001);
                $contestedPositions = $stockPositions->filter(fn ($p) => $p['short'] <= 0.001 && $p['short_non_promotional'] <= 0.001 && $p['required'] > $p['free'] + 0.001);
                $problemPositions = $shortPositions->merge($contestedPositions);
                $positionQty = fn (float $quantity, array $p) => \App\Services\GoodsIssueStockCheck::formatQuantity($quantity, $p['conversion_factor']);
                $stockTone = $shortPositions->isNotEmpty() ? 'red' : ($contestedPositions->isNotEmpty() ? 'amber' : 'green');
            @endphp
            <section id="stock-check" class="gs-card" style="padding:0; overflow:hidden" aria-label="Stock check">
                <div class="gs-banner gs-banner-{{ $stockTone }}" style="border-radius:0">
                    <span>
                        <span>
                            <b>Stock Check before posting</b><br>
                            @if ($shortPositions->isNotEmpty())
                                {{ $shortPositions->count() }} {{ Str::plural('product', $shortPositions->count()) }} short — this issue cannot be posted until they are reduced or removed.
                            @elseif ($contestedPositions->isNotEmpty())
                                All in stock, but other drafts also need {{ $contestedPositions->count() }} of these {{ Str::plural('product', $contestedPositions->count()) }}. Whichever is posted first takes the stock.
                            @else
                                All {{ $stockPositions->count() }} products are in stock.
                            @endif
                        </span>
                    </span>
                </div>
                @if ($problemPositions->isNotEmpty())
                    <div style="padding:4px 16px 12px">
                        <table class="gs-table gs-cards">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="gs-num">Needs</th>
                                    <th scope="col" class="gs-num">In stock</th>
                                    <th scope="col">Other drafts</th>
                                    <th scope="col">Issued {{ $goodsIssue->issue_date?->format('d-M') }}</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($problemPositions as $position)
                                    @php $isShort = $position['short'] > 0.001 || $position['short_non_promotional'] > 0.001; @endphp
                                    <tr class="{{ $isShort ? 'gs-row-red' : 'gs-row-amber' }}">
                                        <td data-label=""><span class="gs-name">{{ $position['product_name'] }}</span><span class="gs-code">{{ $position['product_code'] }}</span></td>
                                        <td class="gs-num" data-label="Needs">{{ $positionQty($position['required'], $position) }}</td>
                                        <td class="gs-num" data-label="In stock">{{ $positionQty($position['on_hand'], $position) }}</td>
                                        <td data-label="Other drafts" style="font-size:12.5px">
                                            @forelse ($position['other_drafts'] as $draft)
                                                <div>{{ $draft['issue_number'] }}@if ($draft['vehicle']) ({{ $draft['vehicle'] }})@endif: {{ $positionQty($draft['quantity'], $position) }}</div>
                                            @empty
                                                <span class="gs-muted">—</span>
                                            @endforelse
                                        </td>
                                        <td data-label="Issued" style="font-size:12.5px">
                                            @forelse ($position['issued_same_day'] as $issued)
                                                <div>{{ $issued['issue_number'] }}@if ($issued['vehicle']) ({{ $issued['vehicle'] }})@endif: {{ $positionQty($issued['quantity'], $position) }}</div>
                                            @empty
                                                <span class="gs-muted">—</span>
                                            @endforelse
                                        </td>
                                        <td data-label="Status" style="font-size:12.5px; font-weight:600">
                                            @if ($position['short'] > 0.001)
                                                <span style="color:#c4161c">Short {{ $positionQty($position['short'], $position) }}</span>
                                            @elseif ($position['short_non_promotional'] > 0.001)
                                                <span style="color:#c4161c">Short {{ $positionQty($position['short_non_promotional'], $position) }} of non-promotional stock</span>
                                            @else
                                                <span style="color:#8a4b00">Other drafts need more than is left</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        {{-- Items --}}
        <section class="gs-card" aria-labelledby="gi-items" x-data="giItems()" @beforeprint.window="search = ''">
            <div class="gs-items-head">
                <div>
                    <h2 class="gs-card-title" id="gi-items">Items issued</h2>
                    <p class="gs-card-sub">{{ $isDraft ? 'Draft: batches and rates are what posting would take now (oldest / urgent first).' : 'Batches and rates actually moved to the van.' }}</p>
                </div>
                @if ($gi->items->count() > 1)
                    <div class="gs-tools gs-no-print">
                        <input type="search" x-model="search" placeholder="Find product or batch" aria-label="Find product" @keydown.escape="search = ''">
                        <select x-model="sort" aria-label="Sort items">
                            <option value="line">Sort: line #</option>
                            <option value="value">Value: high → low</option>
                            <option value="qty">Quantity: high → low</option>
                            <option value="name">Name: A → Z</option>
                        </select>
                    </div>
                @endif
            </div>
            <p class="gs-found gs-no-print" x-show="search.trim() !== ''" x-cloak>
                <span x-text="shown + ' of {{ $gi->items->count() }} lines'"></span> &middot; Rs <b x-text="shownValue"></b>
                <button type="button" @click="search = ''">Clear</button>
            </p>
            <table class="gs-table gs-items gs-cards">
                <thead>
                    <tr>
                        <th scope="col" class="gs-c">#</th>
                        <th scope="col">Product</th>
                        <th scope="col" class="gs-num">Quantity</th>
                        <th scope="col" class="gs-col-batches">Batches &times; rate</th>
                        <th scope="col" class="gs-num">Value (Rs)</th>
                    </tr>
                </thead>
                <tbody x-ref="rows">
                    @foreach ($gi->items as $item)
                        @php
                            $lineValue = $item->calculated_total ?? $item->total_value;
                            $haystack = mb_strtolower($item->product->product_name.' '.$item->product->product_code.' '.collect($item->batch_breakdown ?? [])->pluck('batch_code')->implode(' '));
                            $cartonsText = ($item->product->uom_conversion_factor ?? 1) > 1 ? \Illuminate\Support\Str::before($qty($item), ' (') : null;
                        @endphp
                        <tr data-line="{{ $item->line_no ?? $loop->iteration }}" data-value="{{ (float) $lineValue }}" data-qty="{{ (float) $item->quantity_issued }}"
                            data-name="{{ mb_strtolower($item->product->product_name) }}" data-find="{{ $haystack }}">
                            <td class="gs-c">{{ $item->line_no }}</td>
                            <td data-label="">
                                <span class="gs-name">{{ $item->product->product_name }}</span>
                                @if (mb_strtoupper(trim($item->product->product_code)) !== mb_strtoupper(trim($item->product->product_name)))
                                    <span class="gs-code">{{ $item->product->product_code }}</span>
                                @endif
                                @if ($item->exclude_promotional)<span class="gs-tag gs-tag-np">Non-Promo</span>@endif
                                @if ($item->is_supplementary)<span class="gs-tag gs-tag-supp">Supplementary{{ $item->supplementary_posted_at ? ' · '.$item->supplementary_posted_at->format('d M') : '' }}</span>@endif
                                <div class="gs-batches gs-batches-inline">
                                    @foreach ($item->batch_breakdown ?? [] as $b)
                                        <div><b>{{ number_format($b['quantity'], 0) }}</b> &times; {{ $rs($b['selling_price']) }}@if (($b['batch_code'] ?? 'N/A') !== 'N/A') &middot; {{ $b['batch_code'] }}@endif @if ($b['is_promotional'])<span class="gs-tag gs-tag-promo">Promo</span>@endif</div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="gs-num" data-label="Quantity">
                                <span>
                                    <b>{{ number_format((float) $item->quantity_issued, 2) }}</b>
                                    @if ($cartonsText && ! str_starts_with($cartonsText, '0 ctn'))<span class="gs-ctn">{{ $cartonsText }}</span>@endif
                                </span>
                            </td>
                            <td class="gs-batches gs-col-batches">
                                @forelse ($item->batch_breakdown ?? [] as $b)
                                    <div><b>{{ number_format($b['quantity'], 0) }}</b> &times; {{ $rs($b['selling_price']) }}{{ count($item->batch_breakdown) > 1 ? ' = '.$rs($b['value']) : '' }}
                                        @if (($b['batch_code'] ?? 'N/A') !== 'N/A')<span>&middot; {{ $b['batch_code'] }}</span>@endif
                                        @if ($b['is_promotional'])<span class="gs-tag gs-tag-promo">Promo</span>@endif
                                    </div>
                                @empty
                                    <span>Avg cost {{ $rs($item->unit_cost) }}{{ $isDraft ? ' · no stock to take from' : '' }}</span>
                                @endforelse
                            </td>
                            <td class="gs-num" data-label="Value (Rs)"><b>{{ $rs($lineValue) }}</b></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Total &middot; {{ $gi->items->count() }} {{ \Illuminate\Support\Str::plural('line', $gi->items->count()) }}</td>
                        <td class="gs-num">{{ number_format($totalPieces, 2) }}</td>
                        <td class="gs-col-batches"></td>
                        <td class="gs-num">{{ $rs($sellingValue) }}</td>
                    </tr>
                </tfoot>
            </table>
            <p class="gs-none gs-no-print" x-show="search.trim() !== '' && shown === 0" x-cloak>No product matches “<span x-text="search"></span>”.</p>
            @if ($gi->notes)
                <p class="gs-notes gs-no-print"><b>Notes:</b> {{ $gi->notes }}</p>
            @endif
            <div class="gs-sign"><span>Issued by</span><span>Salesman</span><span>Store keeper</span></div>
        </section>

        <div class="gs-trio">
            {{-- Settlement --}}
            <section class="gs-card" aria-labelledby="gi-settlement">
                <h2 class="gs-card-title" id="gi-settlement">Settlement</h2>
                @if ($settlements->isEmpty())
                    <p class="gs-empty">
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
                            <a href="{{ route('sales-settlements.create') }}" class="gs-pill gs-pill-sm gs-pill-blue" style="margin-top:12px">Start settlement</a>
                        @endcan
                    @endif
                @else
                    <ul class="gs-rows">
                        @foreach ($settlements as $settlement)
                            <li>
                                <span>
                                    @can('sales-settlement-list')
                                        <a href="{{ route('sales-settlements.show', $settlement) }}"><b>{{ $settlement->settlement_number }}</b></a>
                                    @else
                                        <b>{{ $settlement->settlement_number }}</b>
                                    @endcan
                                    <small>{{ $settlement->settlement_date?->format('d M Y') }} &middot; sales Rs {{ number_format((float) $settlement->total_sales_amount) }}</small>
                                </span>
                                <span class="gs-stage gs-{{ $settlementTone($settlement->status) }}">{{ \Illuminate\Support\Str::headline($settlement->status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Accounting --}}
            <section class="gs-card" aria-labelledby="gi-accounting">
                <h2 class="gs-card-title" id="gi-accounting">Accounting</h2>
                @if ($journalEntries->isEmpty())
                    <p class="gs-empty">{{ $isDraft ? 'Posting moves the cost from Stock in Hand to Van Stock with a journal entry.' : 'No journal entry found for this issue.' }}</p>
                @else
                    <ul class="gs-rows">
                        @foreach ($journalEntries as $entry)
                            <li>
                                <span>
                                    @can('journal-entry-list')
                                        <a href="{{ route('journal-entries.show', $entry->id) }}"><b>JE #{{ $entry->id }}</b></a>
                                    @else
                                        <b>JE #{{ $entry->id }}</b>
                                    @endcan
                                    <small>{{ $entry->reference }} &middot; {{ $entry->entry_date?->format('d M Y') }} &middot; {{ \Illuminate\Support\Str::headline($entry->status) }}</small>
                                </span>
                                <b style="white-space:nowrap; font-variant-numeric:tabular-nums">Rs {{ number_format((float) $entry->total_debit) }}</b>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="gs-gl">
                    <div><b>Dr</b><span>{{ $gi->vanStockAccount ? $gi->vanStockAccount->account_code.' · '.$gi->vanStockAccount->account_name : 'Van Stock' }}</span></div>
                    <div><b>Cr</b><span>{{ $gi->stockInHandAccount ? $gi->stockInHandAccount->account_code.' · '.$gi->stockInHandAccount->account_name : 'Stock in Hand' }}</span></div>
                </div>
            </section>

            {{-- Timeline --}}
            <section class="gs-card" aria-labelledby="gi-timeline">
                <h2 class="gs-card-title" id="gi-timeline">Timeline</h2>
                <ul class="gs-feed">
                    @foreach ($timeline as $event)
                        <li><i class="gs-{{ $event['tone'] }}" aria-hidden="true"></i>{{ $event['text'] }}<small>{{ $event['at']->format('d M Y, h:i A') }} &middot; {{ $event['at']->diffForHumans() }}</small></li>
                    @endforeach
                </ul>
            </section>
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
                          class="relative w-full max-w-lg overflow-hidden rounded-lg bg-white text-left shadow-xl" @click.outside="show = false"
                          x-data="{ sending: false }" @submit="if (sending) { $event.preventDefault(); return; } sending = true">
                        @csrf
                        <input type="hidden" name="{{ \App\Support\FormOnce::FIELD }}" value="{{ \App\Support\FormOnce::token() }}">
                        <div class="px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 id="reverse-gi-title" class="text-lg font-medium leading-6 text-gray-900">Reverse {{ $gi->issue_number }} &amp; re-issue</h3>
                            <div class="mt-2 space-y-2 text-sm text-gray-600">
                                <p>This cancels the posted issue. Nothing is deleted:</p>
                                <ul class="list-disc pl-5">
                                    <li>Stock goes back to {{ $gi->warehouse->warehouse_name ?? 'the warehouse' }}, batch by batch, at the cost it left at.</li>
                                    <li>Vehicle {{ $gi->vehicle->vehicle_number ?? '' }} is emptied of it. The new draft takes over its lock: changing the draft's vehicle or deleting the draft frees it, while posting keeps it locked until the settlement is posted.</li>
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
                            <button type="submit" :disabled="sending"
                                class="inline-flex items-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-red-700 disabled:opacity-60"><span x-show="! sending">Reverse &amp; open draft</span><span x-show="sending" x-cloak>Reversing…</span></button>
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
