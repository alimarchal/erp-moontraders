<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $voucher->voucher_number }}</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { width: 74mm; margin: 0 auto; padding: 2mm 0; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.35; color: #000; }
        h1 { margin: 0; font-size: 16px; text-align: center; letter-spacing: 1px; }
        .center { text-align: center; }
        .rule { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.l { width: 30%; }
        .amount { font-size: 20px; font-weight: bold; text-align: center; margin: 6px 0; }
        .sign { margin-top: 26px; display: flex; gap: 8px; }
        .sign div { flex: 1; border-top: 1px solid #000; text-align: center; padding-top: 2px; font-size: 10px; }
        @media screen { body { margin: 10px auto; padding: 8px; border: 1px solid #ccc; } }
    </style>
</head>
<body>
    <h1>MOON TRADERS</h1>
    <div class="center">Expense Voucher</div>
    <div class="rule"></div>
    <table>
        <tr><td class="l">Voucher</td><td><b>{{ $voucher->voucher_number }}</b></td></tr>
        <tr><td class="l">Date</td><td>{{ $voucher->voucher_date->format('d-M-Y') }}</td></tr>
        <tr><td class="l">Company</td><td>{{ $voucher->supplier?->short_name ?? $voucher->supplier?->supplier_name ?? $voucher->company?->company_name ?? 'Moon Traders' }}</td></tr>
        <tr><td class="l">Paid to</td><td><b>{{ $voucher->party_name }}</b></td></tr>
        <tr><td class="l">Account</td><td>{{ $voucher->expenseAccount?->account_name }}</td></tr>
        @if($voucher->narration)<tr><td class="l">Note</td><td>{{ $voucher->narration }}</td></tr>@endif
    </table>
    <div class="rule"></div>
    <div class="amount">Rs {{ number_format($voucher->amount, 2) }}</div>
    <div class="rule"></div>
    <table>
        <tr><td class="l">Made by</td><td>{{ $voucher->creator?->name }}</td></tr>
        <tr><td class="l">Checked</td><td>{{ $voucher->checker?->name }} {{ $voucher->checked_at?->format('d-M-y h:i A') }}</td></tr>
    </table>
    <div class="sign"><div>Receiver</div><div>Checker</div></div>
    <p class="center" style="font-size:10px">Printed {{ now()->format('d-M-Y h:i A') }}</p>
    @if($autoprint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
