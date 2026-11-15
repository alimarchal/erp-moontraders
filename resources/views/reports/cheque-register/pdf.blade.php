{{-- Cheque Register as an A4 landscape PDF (dompdf): the filtered list with the same scope, status tab and sort as the screen. --}}
@php
    $filterLabels = [
        'status' => 'Status', 'employee_id' => 'Salesman', 'customer_id' => 'Customer', 'cheque_date_from' => 'Cheque date from', 'cheque_date_to' => 'Cheque date to',
        'entry_date_from' => 'Entry from', 'entry_date_to' => 'Entry to', 'cleared_date_from' => 'Cleared from', 'cleared_date_to' => 'Cleared to',
        'cleared_via' => 'Cleared via', 'deposit_bank_id' => 'Deposit bank', 'amount_min' => 'Amount from', 'amount_max' => 'Amount to',
        'bank_name' => 'Bank', 'cheque_number' => 'Cheque no.', 'settlement_number' => 'Settlement',
    ];
    $filterLine = collect(\Illuminate\Support\Arr::except($filters, ['supplier_id']))
        ->map(fn ($value, $key) => ($filterLabels[$key] ?? \Illuminate\Support\Str::headline($key)).': '.$value)
        ->implode(' · ');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Cheque Register</title>
    <style>
        @page { margin: 14mm 9mm 16mm 9mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; }
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
    <div class="foot">Moon Traders · Cheque Register · printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</div>

    <table class="head">
        <tr>
            <td>
                <div class="brand">Moon Traders</div>
                <div class="title">Cheque Register · {{ $companyName ?? 'All companies' }}</div>
            </td>
            <td class="meta">
                {{ $filterLine ?: 'No other filters' }}<br>
                Cheques: {{ $cheques->count() }}
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 3%">#</th>
                <th style="width: 8%">Cheque no.</th>
                <th style="width: 7%">Cheque date</th>
                <th style="width: 8%">Company</th>
                <th style="width: 10%">Salesman</th>
                <th>Customer</th>
                <th style="width: 8%">Bank</th>
                <th style="width: 9%">Settlement</th>
                <th style="width: 9%">Amount (Rs)</th>
                <th style="width: 7%">Status</th>
                <th style="width: 12%">Cleared</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cheques as $cheque)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $cheque->cheque_number }}</td>
                    <td class="c">{{ $cheque->cheque_date?->format('d-m-Y') ?? '—' }}</td>
                    <td>{{ $cheque->supplier_short_name ?: $cheque->supplier_name }}</td>
                    <td>{{ $cheque->employee_name ?? '—' }}</td>
                    <td>{{ $cheque->customer_name ?? '—' }}@if ($cheque->customer_address)<br><span class="muted">{{ $cheque->customer_address }}</span>@endif</td>
                    <td>{{ $cheque->bank_name }}</td>
                    <td>{{ $cheque->settlement_number }}</td>
                    <td class="num">{{ number_format((float) $cheque->amount, 2) }}</td>
                    <td class="c">{{ ucfirst($cheque->status) }}</td>
                    <td>
                        @if ($cheque->status === 'cleared')
                            {{ $cheque->cleared_date?->format('d-m-Y') }}<br>
                            <span class="muted">{{ $cheque->cleared_via === 'bank' ? 'By bank'.($cheque->deposit_bank_name ? ' · '.$cheque->deposit_bank_name : '') : 'Cash back' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="c">No cheques match these filters.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" class="num">Total</td>
                <td class="num">{{ number_format((float) $cheques->sum('amount'), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
