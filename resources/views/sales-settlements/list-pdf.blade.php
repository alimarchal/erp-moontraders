{{-- Sales Settlements as an A4 PDF (dompdf): the filtered list, portrait unless landscape was asked for. --}}
@php
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $money = fn ($value) => number_format((float) $value);
    $totalSales = $settlements->sum(fn ($s) => $s->calculated_total_sales_amount);
    $totalCogs = $settlements->sum(fn ($s) => $s->calculated_total_cogs);
    $totalExpenses = $settlements->sum(fn ($s) => $s->calculated_total_expenses);
    $totalNet = $settlements->sum(fn ($s) => $s->calculated_net_profit);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Settlements</title>
    <style>
        @page { margin: 14mm 9mm 16mm 9mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $isLandscape ? '8.5px' : '7.5px' }}; color: #111; }
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
        .loss { color: #b00020; }
        tfoot td { font-weight: bold; background: #f0f0f0; }
        .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="foot">Moon Traders · Sales Settlements · printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</div>

    <table class="head">
        <tr>
            <td>
                <div class="brand">Moon Traders</div>
                <div class="title">Sales Settlements · what each van sold, collected and spent</div>
            </td>
            <td class="meta">
                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}<br>
                Settlements: {{ $settlements->count() }}
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 4%">#</th>
                <th style="width: 11%">Settlement</th>
                <th style="width: 9%">Date</th>
                <th>Salesman · vehicle</th>
                <th style="width: 10%">Goods issue</th>
                <th style="width: 11%">Sales (Rs)</th>
                <th style="width: 11%">COGS (Rs)</th>
                <th style="width: 9%">Expenses (Rs)</th>
                <th style="width: 10%">Net profit (Rs)</th>
                <th style="width: 7%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($settlements as $settlement)
                @php $net = $settlement->calculated_net_profit; @endphp
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $settlement->settlement_number }}</td>
                    <td class="c">{{ $settlement->settlement_date?->format('d-m-Y') }}</td>
                    <td>{{ $settlement->employee->name ?? '—' }}<br><span class="muted">{{ $settlement->vehicle->vehicle_number ?? '—' }}</span></td>
                    <td>{{ $settlement->goodsIssue->issue_number ?? '—' }}</td>
                    <td class="num">{{ $money($settlement->calculated_total_sales_amount) }}</td>
                    <td class="num">{{ $money($settlement->calculated_total_cogs) }}</td>
                    <td class="num">{{ $money($settlement->calculated_total_expenses) }}</td>
                    <td class="num {{ $net < 0 ? 'loss' : '' }}">{{ $money($net) }}</td>
                    <td class="c">{{ ucfirst((string) $settlement->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="c">No settlements match these filters.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="num">Total</td>
                <td class="num">{{ $money($totalSales) }}</td>
                <td class="num">{{ $money($totalCogs) }}</td>
                <td class="num">{{ $money($totalExpenses) }}</td>
                <td class="num {{ $totalNet < 0 ? 'loss' : '' }}">{{ $money($totalNet) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
