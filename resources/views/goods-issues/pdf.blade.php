{{-- Goods Issues as an A4 PDF (dompdf): the filtered list, portrait unless landscape was asked for. --}}
@php
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $statusLabels = ['draft' => 'Draft', 'issued' => 'Issued', 'cancelled' => 'Reversed'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Goods Issues</title>
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
        .muted { color: #555; }
        tfoot td { font-weight: bold; background: #f0f0f0; }
        .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="foot">Moon Traders · Goods Issues · printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</div>

    <table class="head">
        <tr>
            <td>
                <div class="brand">Moon Traders</div>
                <div class="title">Goods Issues · stock loaded onto vans</div>
            </td>
            <td class="meta">
                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}<br>
                Issues: {{ $goodsIssues->count() }}
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 4%">#</th>
                <th style="width: 10%">Issue</th>
                <th style="width: 10%">Date</th>
                <th>Salesman</th>
                <th style="width: 11%">Vehicle</th>
                <th style="width: 13%">Supplier</th>
                <th style="width: 10%">Warehouse</th>
                <th style="width: 6%">Lines</th>
                <th style="width: 13%">Value (Rs)</th>
                <th style="width: 9%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($goodsIssues as $gi)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $gi->issue_number }}</td>
                    <td class="c">{{ $gi->issue_date?->format('d-m-Y') }}</td>
                    <td>{{ $gi->employee->name ?? '—' }}</td>
                    <td>{{ $gi->vehicle->vehicle_number ?? '—' }}</td>
                    <td>{{ $gi->supplier->supplier_name ?? '—' }}</td>
                    <td>{{ $gi->warehouse->warehouse_name ?? '—' }}</td>
                    <td class="c">{{ $gi->items_count ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $gi->total_value, 2) }}</td>
                    <td class="c">{{ $statusLabels[$gi->status] ?? \Illuminate\Support\Str::headline($gi->status) }}@if ($gi->status === 'issued' && ! $gi->settlement)<br><span class="muted">not settled</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="10" class="c">No goods issues match these filters.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" class="num">Total</td>
                <td class="num">{{ number_format((float) $goodsIssues->sum('total_value'), 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
