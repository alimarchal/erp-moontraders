<!doctype html>
<html><head><meta charset="utf-8"><title>Salary sheet {{ $run->period_month->format('F Y') }}</title>
@include('payroll.pdf._style')
<style>
    @page { margin: 22px 20px 32px; }
    body { font-size: 7.6px; }
    table.grid.sheet th { font-size: 6.8px; padding: 4px 3px; text-transform: none; vertical-align: bottom; line-height: 1.15; }
    table.grid.sheet td { padding: 3px 3px; white-space: nowrap; }
    table.grid.sheet .c-no { width: 18px; text-align: center; }
    table.grid.sheet td.c-name { font-weight: bold; }
    table.grid.sheet .code { font-weight: normal; color: #64748b; font-size: 6.4px; }
    table.grid.sheet .c-job { color: #334155; }
    table.grid.sheet .tot { background: #f1f5f9; font-weight: bold; }
    table.grid.sheet .net { background: #e0f2fe; font-weight: bold; }
    table.grid.sheet tr.bands th { text-align: center; font-size: 7.4px; letter-spacing: .04em; text-transform: uppercase; border-bottom: 0; }
    table.grid.sheet tr.bands th:first-child { background: #fff; border: 0; }
    .band-fixed { background: #dcfce7 !important; color: #14532d; } .band-var { background: #fef3c7 !important; color: #78350f; }
    .band-ded { background: #fee2e2 !important; color: #7f1d1d; } .band-gross, .band-net { background: #fff !important; border: 0 !important; }
    table.grid.sheet tr.sub td { background: #f8fafc; font-weight: bold; }
    table.grid.sheet tbody tr:nth-child(even) td { background-color: #fbfdff; }
    .summary { width: 100%; margin: 10px 0 0; border-collapse: collapse; } .summary td { padding: 4px 8px; border: 1px solid #cbd5e1; font-size: 8.5px; }
    .summary b { display: block; font-size: 11px; color: #0f172a; } .summary td { color: #475569; }
</style>
</head>
<body>
<table class="head"><tr>
    <td><h1>Employees salary sheet · {{ $run->ownerLabel() }}</h1><div class="sub">Month: {{ $run->period_month->format('F Y') }} · {{ $run->run_number }} · {{ $run->statusLabel() }}{{ $run->paid_on ? ' on '.$run->paid_on->format('d-M-Y') : '' }}</div></td>
    <td class="ref">{{ config('app.name') }}<div class="sub">Printed {{ now()->format('d-M-Y H:i') }}</div></td>
</tr></table>

@include('payroll.pdf._sheet-table', ['sheet' => $sheet])

<table class="summary"><tr>
    <td>Employees<b>{{ $sheet['rows']->count() }}</b></td>
    <td>Fixed part<b>Rs {{ number_format($sheet['totals']['fixed'], 0) }}</b></td>
    <td>Variable part<b>Rs {{ number_format($sheet['totals']['variable'], 0) }}</b></td>
    <td>Gross salary<b>Rs {{ number_format($sheet['totals']['earnings'], 0) }}</b></td>
    <td>Deductions<b>Rs {{ number_format($sheet['totals']['deductions'], 0) }}</b></td>
    <td>Net salary payable<b>Rs {{ number_format($sheet['totals']['net'], 0) }}</b></td>
</tr></table>

<table class="sign"><tr>
    <td>Prepared by{{ $run->creator ? ': '.$run->creator->name : '' }}</td><td class="sp"></td>
    <td>Checked by{{ $run->checker ? ': '.$run->checker->name : '' }}</td><td class="sp"></td>
    <td>Approved by</td><td class="sp"></td><td>Accounts</td>
</tr></table>
</body></html>
