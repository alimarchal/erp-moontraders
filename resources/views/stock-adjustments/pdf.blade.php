{{-- Stock Adjustments as an A4 PDF (dompdf): the filtered list, portrait unless landscape was asked for (like Goods Issues). --}}
@php
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $types = ['damage' => 'Damage', 'theft' => 'Theft', 'count_variance' => 'Count variance', 'expiry' => 'Expiry', 'recall' => 'Recall', 'other' => 'Other'];
    $lineValue = fn ($adjustment) => (float) $adjustment->items_sum_adjustment_value + (float) $adjustment->revaluation_value;
    $period = ($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') : 'start').' to '.($dateTo ? \Carbon\Carbon::parse($dateTo)->format('d-M-Y') : now()->format('d-M-Y'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stock Adjustments</title>
    <style>
        @page { margin: 14mm 9mm 16mm 9mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $isLandscape ? '9px' : '8px' }}; color: #111; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .head td { vertical-align: bottom; padding: 0; }
        .brand { font-size: 16px; font-weight: bold; }
        .title { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .meta { text-align: right; color: #444; line-height: 1.5; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 0.6pt solid #000; padding: 3px 4px; vertical-align: top; }
        table.grid th { background: #e6e6e6; font-weight: bold; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .c { text-align: center; }
        tfoot td { font-weight: bold; background: #f0f0f0; }
        .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="foot">{{ config('app.name') }} · Stock Adjustments · printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</div>

    <table class="head">
        <tr>
            <td>
                <div class="brand">{{ config('app.name') }}</div>
                <div class="title">Stock Adjustments · damage, theft, expiry, counts and cost corrections</div>
            </td>
            <td class="meta">
                Period: {{ $period }}<br>
                Adjustments: {{ $adjustments->count() }}
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 4%">#</th>
                <th style="width: 12%">Adjustment</th>
                <th style="width: 9%">Date</th>
                <th style="width: 10%">Type</th>
                <th style="width: 12%">Warehouse</th>
                <th>Reason</th>
                <th style="width: 6%">Lines</th>
                <th style="width: 9%">Qty change</th>
                <th style="width: 11%">Value (Rs)</th>
                <th style="width: 8%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($adjustments as $adjustment)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $adjustment->adjustment_number }}</td>
                    <td class="c">{{ $adjustment->adjustment_date?->format('d-m-Y') }}</td>
                    <td>{{ $types[$adjustment->adjustment_type] ?? \Illuminate\Support\Str::headline($adjustment->adjustment_type) }}</td>
                    <td>{{ $adjustment->warehouse->warehouse_name ?? '—' }}</td>
                    <td>{{ $adjustment->reason }}</td>
                    <td class="c">{{ $adjustment->items_count }}</td>
                    <td class="num">{{ number_format((float) $adjustment->items_sum_adjustment_quantity, 3) }}</td>
                    <td class="num">{{ number_format($lineValue($adjustment), 2) }}</td>
                    <td class="c">{{ ucfirst($adjustment->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="c">No stock adjustments match these filters.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="num">Total</td>
                <td class="num">{{ number_format((float) $adjustments->sum('items_sum_adjustment_quantity'), 3) }}</td>
                <td class="num">{{ number_format((float) $adjustments->sum(fn ($adjustment) => $lineValue($adjustment)), 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
