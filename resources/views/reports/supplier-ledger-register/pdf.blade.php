{{-- Supplier Ledger Register as an A4 PDF (dompdf): the whole period for one supplier, portrait unless landscape was asked for. --}}
@php
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $money = fn ($value) => (float) $value != 0 ? number_format((float) $value, 2) : '-';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Supplier Ledger - {{ $selectedSupplier->supplier_name }}</title>
    <style>
        @page { margin: 14mm 8mm 16mm 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $isLandscape ? '9px' : '7.6px' }}; color: #111; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .head td { vertical-align: bottom; padding: 0; }
        .brand { font-size: 16px; font-weight: bold; }
        .title { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .meta { text-align: right; color: #444; line-height: 1.5; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 0.6pt solid #000; padding: 3px 3px; }
        table.grid th { background: #e6e6e6; font-weight: bold; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .c { text-align: center; }
        .opening td { background: #f6f6f6; font-weight: bold; }
        tfoot td { font-weight: bold; background: #f0f0f0; }
        .neg { color: #b00020; }
        .summary { width: 45%; margin: 10px 0 0 auto; border-collapse: collapse; }
        .summary td { border: 0.6pt solid #000; padding: 4px 6px; }
        .summary td:first-child { font-weight: bold; background: #f6f6f6; }
        .note { margin-top: 6px; color: #555; }
        .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="foot">Moon Traders · Supplier Ledger Register · {{ $selectedSupplier->supplier_name }} · printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</div>

    <table class="head">
        <tr>
            <td>
                <div class="brand">Moon Traders</div>
                <div class="title">Ledger to the company · {{ $selectedSupplier->supplier_name }}</div>
            </td>
            <td class="meta">
                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}<br>
                Entries: {{ $entries->count() }}
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 3.5%">#</th>
                <th style="width: 8%">Date</th>
                <th style="width: 4.5%">Type</th>
                <th>Document</th>
                <th style="width: 10%">Online</th>
                <th style="width: 10%">Opening</th>
                <th style="width: 10%">Invoice</th>
                <th style="width: 8%">Expenses</th>
                <th style="width: 7%">ZA 0.5%</th>
                <th style="width: 9%">Claim adjust</th>
                <th style="width: 11%">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening">
                <td colspan="10">Balance brought forward (before {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }})</td>
                <td class="num {{ $openingBalance < 0 ? 'neg' : '' }}">{{ number_format((float) $openingBalance, 2) }}</td>
            </tr>
            @forelse ($entries as $entry)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td class="c">{{ \Carbon\Carbon::parse($entry->transaction_date)->format('d-m-Y') }}</td>
                    <td class="c">{{ $entry->document_type?->value ?? '-' }}</td>
                    <td>{{ $entry->document_number ?? '-' }}</td>
                    <td class="num">{{ $money($entry->online_amount) }}</td>
                    <td class="num">{{ $money($entry->opening_balance) }}</td>
                    <td class="num">{{ $money($entry->invoice_amount) }}</td>
                    <td class="num">{{ $money($entry->expenses_amount) }}</td>
                    <td class="num">{{ $money($entry->za_point_five_percent_amount) }}</td>
                    <td class="num">{{ $money($entry->claim_adjust_amount) }}</td>
                    <td class="num {{ $entry->running_balance < 0 ? 'neg' : '' }}">{{ number_format((float) $entry->running_balance, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="c">No entries in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="num">Period totals</td>
                <td class="num">{{ $money($totals->total_online ?? 0) }}</td>
                <td class="num">{{ $money($totals->total_opening_balance ?? 0) }}</td>
                <td class="num">{{ $money($totals->total_invoice ?? 0) }}</td>
                <td class="num">{{ $money($totals->total_expenses ?? 0) }}</td>
                <td class="num">{{ $money($totals->total_za ?? 0) }}</td>
                <td class="num">{{ $money($totals->total_claim_adjust ?? 0) }}</td>
                <td class="num {{ $currentBalance < 0 ? 'neg' : '' }}">{{ number_format((float) $currentBalance, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="summary">
        <tr><td>Online payments</td><td class="num">{{ number_format((float) ($totals->total_online ?? 0), 2) }}</td></tr>
        <tr><td>Invoices</td><td class="num">{{ number_format((float) ($totals->total_invoice ?? 0), 2) }}</td></tr>
        <tr><td>Balance</td><td class="num {{ $currentBalance < 0 ? 'neg' : '' }}">{{ number_format((float) $currentBalance, 2) }}</td></tr>
    </table>
    <p class="note">A positive balance is what the company owes us (our advance); a negative balance is what we owe the company.</p>
</body>
</html>
