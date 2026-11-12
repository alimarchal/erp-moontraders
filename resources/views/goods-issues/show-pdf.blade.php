{{-- One goods issue as a clean A4 issue note (dompdf): portrait unless landscape was asked for. --}}
@php
    $gi = $goodsIssue;
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $rs = fn ($value) => number_format((float) $value, 2);
    $sellingValue = (float) $gi->items->sum(fn ($item) => $item->calculated_total ?? $item->total_value);
    $totalPieces = (float) $gi->items->sum('quantity_issued');
    $statusLabels = ['draft' => 'DRAFT', 'issued' => 'ISSUED', 'cancelled' => 'REVERSED'];
    $statusColors = ['draft' => '#6b7280', 'issued' => '#15803d', 'cancelled' => '#b91c1c'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Goods Issue {{ $gi->issue_number }}</title>
    <style>
        @page { margin: 12mm 11mm 16mm 11mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $isLandscape ? '9.5px' : '8.8px' }}; color: #1f2937; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }

        .top td { vertical-align: top; padding: 0; }
        .brand { font-size: 18px; font-weight: bold; color: #0b1f44; letter-spacing: .3px; }
        .brand-sub { font-size: 8px; color: #6b7280; margin-top: 2px; }
        .doc { text-align: right; }
        .doc-title { font-size: 15px; font-weight: bold; color: #0b1f44; letter-spacing: 1px; }
        .doc-no { font-size: 11px; font-weight: bold; margin-top: 3px; }
        .badge { display: inline-block; margin-top: 4px; padding: 2px 8px; border: 1pt solid; border-radius: 3px; font-size: 7.5px; font-weight: bold; letter-spacing: .8px; }
        .rule { height: 2.5pt; background: #0b1f44; margin: 8px 0 10px; }

        .info td { width: 25%; padding: 6px 8px; vertical-align: top; background: #f3f5f9; border: 3pt solid #fff; }
        .info .lbl { font-size: 7px; text-transform: uppercase; letter-spacing: .7px; color: #6b7280; }
        .info .val { font-size: 9.5px; font-weight: bold; color: #111827; margin-top: 2px; }
        .info .sub { font-size: 7.5px; color: #6b7280; margin-top: 1px; }

        .grid { margin-top: 10px; }
        .grid thead th { background: #0b1f44; color: #fff; font-size: 7.8px; text-transform: uppercase; letter-spacing: .5px; padding: 5px 5px; text-align: left; }
        .grid td { padding: 5px 5px; border-bottom: 0.5pt solid #d9dee7; vertical-align: top; }
        .grid tbody tr:nth-child(even) td { background: #f8f9fb; }
        .num { text-align: right !important; white-space: nowrap; }
        .c { text-align: center !important; color: #6b7280; }
        .pname { font-weight: bold; color: #111827; }
        .muted { color: #6b7280; font-size: .9em; }
        .tag { font-size: 7px; font-weight: bold; color: #7c2d12; }
        .batches { font-size: .9em; color: #374151; }
        .grid tfoot td { padding: 6px 5px; font-weight: bold; border-top: 1.2pt solid #0b1f44; background: #eef1f7; }

        .summary { width: 45%; margin: 10px 0 0 auto; }
        .summary td { padding: 4px 8px; border-bottom: 0.5pt solid #d9dee7; }
        .summary tr.total td { font-size: 11px; font-weight: bold; color: #0b1f44; border-bottom: 0; border-top: 1.2pt solid #0b1f44; }

        .notes { margin-top: 10px; padding: 6px 8px; background: #fff8e6; border-left: 2.5pt solid #f59e0b; font-size: 8.5px; }
        .sign { margin-top: 46px; }
        .sign td { width: 33.3%; text-align: center; padding: 0 16px; font-size: 8px; color: #374151; }
        .sign span { display: block; border-top: 0.8pt solid #111827; padding-top: 4px; }
        .foot { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 7px; color: #9ca3af; border-top: 0.5pt solid #e5e7eb; padding-top: 3px; }
        .foot .r { float: right; }
    </style>
</head>
<body>
    <div class="foot">Moon Traders · Goods Issue {{ $gi->issue_number }}<span class="r">Printed {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</span></div>

    <table class="top">
        <tr>
            <td>
                <div class="brand">MOON TRADERS</div>
                <div class="brand-sub">Distribution · Stock issued from warehouse to sales vehicle</div>
            </td>
            <td class="doc">
                <div class="doc-title">GOODS ISSUE NOTE</div>
                <div class="doc-no">{{ $gi->issue_number }}</div>
                <span class="badge" style="color: {{ $statusColors[$gi->status] ?? '#374151' }}; border-color: {{ $statusColors[$gi->status] ?? '#374151' }}">{{ $statusLabels[$gi->status] ?? strtoupper($gi->status) }}</span>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="info">
        <tr>
            <td><div class="lbl">Issue date</div><div class="val">{{ $gi->issue_date?->format('d M Y') }}</div><div class="sub">{{ $gi->posted_at ? 'Posted '.$gi->posted_at->format('d M Y, h:i A') : 'Not posted' }}</div></td>
            <td><div class="lbl">Salesman</div><div class="val">{{ $gi->employee->name ?? '—' }}</div><div class="sub">{{ $gi->employee->employee_code ?? '' }}</div></td>
            <td><div class="lbl">Vehicle</div><div class="val">{{ $gi->vehicle->vehicle_number ?? '—' }}</div><div class="sub">{{ $gi->vehicle->vehicle_type ?? '' }}</div></td>
            <td><div class="lbl">From warehouse</div><div class="val">{{ $gi->warehouse->warehouse_name ?? '—' }}</div><div class="sub">{{ $gi->supplier->supplier_name ?? 'No supplier' }}</div></td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 5%; text-align:center">#</th>
                <th>Product</th>
                <th style="width: 11%; text-align:right">Qty (pcs)</th>
                <th style="width: 31%">Batch &times; rate</th>
                <th style="width: 14%; text-align:right">Value (Rs)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($gi->items as $item)
                <tr>
                    <td class="c">{{ $item->line_no ?? $loop->iteration }}</td>
                    <td>
                        <span class="pname">{{ $item->product->product_name }}</span>
                        @if (mb_strtoupper(trim($item->product->product_code)) !== mb_strtoupper(trim($item->product->product_name)))<br><span class="muted">{{ $item->product->product_code }}</span>@endif
                        @if ($item->exclude_promotional) <span class="tag">NON-PROMO</span>@endif
                        @if ($item->is_supplementary) <span class="tag">SUPPLEMENTARY</span>@endif
                    </td>
                    <td class="num">{{ number_format((float) $item->quantity_issued, 2) }}</td>
                    <td class="batches">
                        @forelse ($item->batch_breakdown ?? [] as $b)
                            <div>{{ number_format($b['quantity'], 0) }} &times; {{ $rs($b['selling_price']) }}@if (($b['batch_code'] ?? 'N/A') !== 'N/A') <span class="muted">· {{ $b['batch_code'] }}</span>@endif @if ($b['is_promotional'])<span class="tag">PROMO</span>@endif</div>
                        @empty
                            <span class="muted">Avg cost {{ $rs($item->unit_cost) }}</span>
                        @endforelse
                    </td>
                    <td class="num">{{ $rs($item->calculated_total ?? $item->total_value) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Total · {{ $gi->items->count() }} {{ \Illuminate\Support\Str::plural('line', $gi->items->count()) }}</td>
                <td class="num">{{ number_format($totalPieces, 2) }}</td>
                <td></td>
                <td class="num">{{ $rs($sellingValue) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="summary">
        <tr><td>Products</td><td class="num">{{ $gi->items->count() }}</td></tr>
        <tr><td>Quantity</td><td class="num">{{ number_format($totalPieces, 2) }} pcs</td></tr>
        <tr class="total"><td>Value issued</td><td class="num">Rs {{ $rs($sellingValue) }}</td></tr>
    </table>

    @if ($gi->notes)
        <div class="notes"><b>Notes:</b> {{ $gi->notes }}</div>
    @endif

    <table class="sign">
        <tr><td><span>Issued by<br>{{ $gi->issuedBy->name ?? '' }}</span></td><td><span>Received by (salesman)<br>{{ $gi->employee->name ?? '' }}</span></td><td><span>Store keeper<br>&nbsp;</span></td></tr>
    </table>
</body>
</html>
