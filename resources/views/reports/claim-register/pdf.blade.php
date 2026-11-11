{{-- Claim Register as an A4 PDF (dompdf): the whole period for one supplier, portrait unless landscape was asked for. --}}
@php
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $money = fn ($value) => number_format((float) $value, 2);
    $balance = (float) $openingBalance;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Claim Register - {{ $selectedSupplier->supplier_name }}</title>
    <style>
        @page { margin: 14mm 10mm 16mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $isLandscape ? '9.5px' : '8.5px' }}; color: #111; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .head td { vertical-align: bottom; padding: 0; }
        .brand { font-size: 16px; font-weight: bold; }
        .title { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .meta { text-align: right; color: #444; line-height: 1.5; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 0.6pt solid #000; padding: 3px 4px; }
        table.grid th { background: #e6e6e6; font-weight: bold; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .c { text-align: center; }
        .opening td { background: #f6f6f6; font-weight: bold; }
        tfoot td { font-weight: bold; background: #f0f0f0; }
        .neg { color: #b00020; }
        .summary { width: 45%; margin: 10px 0 0 auto; border-collapse: collapse; }
        .summary td { border: 0.6pt solid #000; padding: 4px 6px; }
        .summary td:first-child { font-weight: bold; background: #f6f6f6; }
        .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="foot">Moon Traders · Claim Register · {{ $selectedSupplier->supplier_name }} · printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</div>

    <table class="head">
        <tr>
            <td>
                <div class="brand">Moon Traders</div>
                <div class="title">Claim Register · {{ $selectedSupplier->supplier_name }}</div>
            </td>
            <td class="meta">
                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}<br>
                Entries: {{ $claims->count() }}
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 4%">#</th>
                <th style="width: 9%">Date</th>
                <th style="width: 13%">Reference</th>
                <th>Description</th>
                <th style="width: 11%">Claim month</th>
                <th style="width: 5%">Type</th>
                <th style="width: 12%">Claim (debit)</th>
                <th style="width: 12%">Recovery (credit)</th>
                <th style="width: 12%">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening">
                <td colspan="8">Opening balance (before {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }})</td>
                <td class="num {{ $balance < 0 ? 'neg' : '' }}">{{ $money($balance) }}</td>
            </tr>
            @forelse ($claims as $claim)
                @php($balance += (float) $claim->debit - (float) $claim->credit)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td class="c">{{ $claim->transaction_date->format('d-m-Y') }}</td>
                    <td>{{ $claim->reference_number ?? '-' }}</td>
                    <td>{{ $claim->description ?? '-' }}</td>
                    <td class="c">{{ $claim->claim_month ?? '-' }}</td>
                    <td class="c">{{ $claim->transaction_type === 'claim' ? 'DR' : 'CR' }}</td>
                    <td class="num">{{ (float) $claim->debit > 0 ? $money($claim->debit) : '-' }}</td>
                    <td class="num">{{ (float) $claim->credit > 0 ? $money($claim->credit) : '-' }}</td>
                    <td class="num {{ $balance < 0 ? 'neg' : '' }}">{{ $money($balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="c">No entries in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="num">Period totals</td>
                <td class="num">{{ $money($totals['debit']) }}</td>
                <td class="num">{{ $money($totals['credit']) }}</td>
                <td class="num {{ $closingBalance < 0 ? 'neg' : '' }}">{{ $money($closingBalance) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="summary">
        <tr><td>Claims (debit)</td><td class="num">{{ $money($totals['debit']) }}</td></tr>
        <tr><td>Recoveries (credit)</td><td class="num">{{ $money($totals['credit']) }}</td></tr>
        <tr><td>Closing balance</td><td class="num {{ $closingBalance < 0 ? 'neg' : '' }}">{{ $money($closingBalance) }}</td></tr>
    </table>
</body>
</html>
