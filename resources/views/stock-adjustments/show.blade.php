@php
    $adjustment = $stockAdjustment;
    $isDraft = $adjustment->status === 'draft';
    $statusTone = ['draft' => 'ak-status-amber', 'posted' => 'ak-status-green', 'cancelled' => 'ak-status-red'][$adjustment->status] ?? 'ak-status-slate';
    $money = fn ($value) => number_format((float) $value, 2);
    $signed = fn ($value) => ((float) $value > 0 ? '+' : '').number_format((float) $value, 2);
    $totalValue = (float) $adjustment->items->sum('adjustment_value') + (float) collect($costChanges)->sum('value');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('stock-adjustments.index') }}">Stock Adjustments</a><span aria-hidden="true">›</span><span>{{ $adjustment->adjustment_number }}</span>
                </nav>
                <h1 class="ak-title" style="margin:0">Stock Adjustment {{ $adjustment->adjustment_number }}</h1>
                <p class="ak-sub">{{ $adjustment->adjustment_date->format('d M Y') }} &middot; {{ \Illuminate\Support\Str::headline($adjustment->adjustment_type) }} &middot;
                    <span class="ak-status {{ $statusTone }}"><i aria-hidden="true"></i>{{ ucfirst($adjustment->status) }}</span></p>
            </div>
            <div class="ak-head-actions no-print">
                <a href="{{ route('stock-adjustments.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to adjustments</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @if ($isDraft)
                    @can('stock-adjustment-edit')
                        <a href="{{ route('stock-adjustments.edit', $adjustment) }}" class="ak-btn ak-btn-primary">Edit</a>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <style>
        .ev-grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
        @media (min-width: 900px) { .ev-grid { grid-template-columns: 3fr 2fr; } }
        .ev-dl { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; margin: 0; }
        .ev-dl dt { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); }
        .ev-dl dd { margin: 2px 0 0; font-weight: 600; word-break: break-word; }
        .ev-pad { padding: 18px 20px; }
        .sa-stat { font-size: 26px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .sa-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .sa-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .sa-table th { text-align: left; padding: 9px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); background: #f8fafc; border-bottom: 1px solid var(--ak-border); white-space: nowrap; }
        .sa-table td { padding: 10px 12px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
        .sa-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .sa-up { color: #047857; } .sa-down { color: #b91c1c; }
        .sa-cost { display: inline-flex; align-items: center; gap: 6px; padding: 2px 8px; border-radius: 6px; background: #fef3c7; color: #92400e; font-weight: 600; }
        .sa-cost s { color: #b45309; font-weight: 400; }
        .sa-hint { display: block; margin-top: 3px; font-size: 12px; color: var(--ak-muted); }
    </style>

    <div class="ak-page">
        <x-status-message />
        <div class="ev-grid">
            <div style="display:flex; flex-direction:column; gap:16px">
                <section class="ak-card" aria-label="Adjusted items">
                    <div class="ev-pad" style="padding-bottom:6px"><h2 style="margin:0; font-size:15px; font-weight:700">Items ({{ $adjustment->items->count() }})</h2></div>
                    <div style="overflow-x:auto">
                        <table class="sa-table">
                            <thead>
                                <tr>
                                    <th>Product / batch</th><th class="num">System qty</th><th class="num">Actual qty</th><th class="num">Difference</th>
                                    <th class="num">Unit cost</th><th class="num">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($adjustment->items as $item)
                                    @php
                                        $change = $costChanges[$item->id] ?? null;
                                        $lineValue = (float) $item->adjustment_value + (float) ($change['value'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td><b>{{ $item->product->product_name ?? '—' }}</b><span class="sa-hint">{{ $item->stockBatch->batch_code ?? '' }}{{ $item->uom ? ' · '.$item->uom->uom_name : '' }}</span></td>
                                        <td class="num">{{ number_format((float) $item->system_quantity, 3) }}</td>
                                        <td class="num">{{ number_format((float) $item->actual_quantity, 3) }}</td>
                                        <td class="num {{ $item->adjustment_quantity > 0 ? 'sa-up' : ($item->adjustment_quantity < 0 ? 'sa-down' : '') }}">{{ $signed($item->adjustment_quantity) }}</td>
                                        <td class="num">
                                            @if ($change)
                                                <span class="sa-cost" title="Unit cost changed by this adjustment"><s>{{ $money($change['old']) }}</s> → {{ $money($change['new']) }}</span>
                                                <span class="sa-hint">cost changed</span>
                                            @else
                                                {{ $money($item->unit_cost) }}
                                            @endif
                                        </td>
                                        <td class="num {{ $lineValue < 0 ? 'sa-down' : 'sa-up' }}"><b>{{ $signed($lineValue) }}</b>
                                            @if ($change)<span class="sa-hint">{{ $signed($change['value']) }} from cost</span>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td colspan="5" style="padding:10px 12px"><b>Total value</b></td><td class="num" style="padding:10px 12px"><b>{{ $signed($totalValue) }}</b></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                @if ($isDraft)
                    @can('stock-adjustment-post')
                        <section class="ak-card ev-pad no-print" aria-label="Post adjustment">
                            <h2 style="margin:0 0 8px; font-size:15px; font-weight:700">Post this adjustment</h2>
                            <p class="ak-muted" style="margin:0 0 10px; font-size:13px">Posting moves stock and creates the journal entry. Enter your password to confirm.</p>
                            <form method="POST" action="{{ route('stock-adjustments.post', $adjustment) }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center">
                                @csrf
                                <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Password" style="border:1px solid var(--ak-border); border-radius:8px; padding:8px 12px; min-width:220px">
                                <button type="submit" class="ak-btn ak-btn-success">Post Adjustment</button>
                            </form>
                            @error('password')<p class="sa-down" style="margin:8px 0 0; font-size:13px">{{ $message }}</p>@enderror
                        </section>
                    @endcan
                @endif
            </div>

            <div style="display:flex; flex-direction:column; gap:16px">
                <section class="ak-card ev-pad" aria-label="Adjustment details">
                    <dl class="ev-dl">
                        <div><dt>Number</dt><dd>{{ $adjustment->adjustment_number }}</dd></div>
                        <div><dt>Date</dt><dd>{{ $adjustment->adjustment_date->format('d M Y') }}</dd></div>
                        <div><dt>Type</dt><dd>{{ \Illuminate\Support\Str::headline($adjustment->adjustment_type) }}</dd></div>
                        <div><dt>Warehouse</dt><dd>{{ $adjustment->warehouse->warehouse_name ?? '—' }}</dd></div>
                        <div style="grid-column: 1 / -1"><dt>Reason</dt><dd>{{ $adjustment->reason ?: '—' }}</dd></div>
                        @if ($adjustment->notes)<div style="grid-column: 1 / -1"><dt>Notes</dt><dd>{{ $adjustment->notes }}</dd></div>@endif
                        <div><dt>Created by</dt><dd>{{ $adjustment->createdBy->name ?? '—' }}</dd></div>
                        <div><dt>Created</dt><dd>{{ $adjustment->created_at?->format('d M Y h:i A') ?? '—' }}</dd></div>
                        @if ($adjustment->posted_at)
                            <div><dt>Posted by</dt><dd>{{ $adjustment->postedBy->name ?? '—' }}</dd></div>
                            <div><dt>Posted</dt><dd>{{ $adjustment->posted_at->format('d M Y h:i A') }}</dd></div>
                        @endif
                        @if ($adjustment->journalEntry)
                            <div style="grid-column: 1 / -1"><dt>Journal entry</dt><dd>
                                @can('journal-entry-list')
                                    <a href="{{ route('journal-entries.show', $adjustment->journalEntry) }}" style="color:var(--ak-navy)">{{ $adjustment->journalEntry->reference ?: '#'.$adjustment->journalEntry->id }}</a>
                                @else
                                    {{ $adjustment->journalEntry->reference ?: '#'.$adjustment->journalEntry->id }}
                                @endcan
                            </dd></div>
                        @endif
                    </dl>
                </section>

                <section class="ak-card ev-pad" aria-label="Summary">
                    <div class="sa-stats">
                        <div><div class="ak-muted" style="font-size:12px; text-transform:uppercase">Lines</div><div class="sa-stat">{{ $adjustment->items->count() }}</div></div>
                        <div><div class="ak-muted" style="font-size:12px; text-transform:uppercase">Net value</div><div class="sa-stat {{ $totalValue < 0 ? 'sa-down' : 'sa-up' }}">{{ $signed($totalValue) }}</div></div>
                        <div><div class="ak-muted" style="font-size:12px; text-transform:uppercase">Cost changes</div><div class="sa-stat">{{ count($costChanges) }}</div></div>
                        <div><div class="ak-muted" style="font-size:12px; text-transform:uppercase">Qty change</div><div class="sa-stat">{{ $signed($adjustment->items->sum('adjustment_quantity')) }}</div></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
