{{-- Daily Business Summary as an A4 PDF (dompdf), portrait unless landscape was asked for. Plain black-and-white
     bordered tables in the management sheet's order: headline figures, then Investment Position with the Ledger
     Amount | Category-wise Sales with the groups | TTS Summary, then the salesman table (its header repeats on every page). --}}
@php
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $money = fn ($value) => abs((float) $value) < 0.005 ? '-' : number_format((float) $value, 2);
    $amount = fn ($value) => number_format((float) $value, 2);
    $signed = fn ($value) => (float) $value < 0 ? '('.number_format(abs((float) $value), 2).')' : number_format((float) $value, 2);
    $reportDate = \Carbon\Carbon::parse($date);
    $period = \Carbon\Carbon::parse($report['month_start'])->format('d M').' - '.$reportDate->format('d M');
    $tts = $report['tts'];
    $investment = $report['investment'];
    $totals = $report['totals'];
    $investmentRows = [
        'Powder Expiry' => $investment['powder_expiry'],
        'Liquid Expiry' => $investment['liquid_expiry'],
        'Claim Amount' => $investment['claims'],
        'Stock Amount' => $investment['stock'],
        'Credit Amount' => $investment['credit'],
        'Ledger Amount' => $investment['ledger'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Business Summary - {{ $companyLabel }} - {{ $reportDate->format('d.m.Y') }}</title>
    <style>
        @page { margin: 10mm 8mm 14mm 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $isLandscape ? '8.4px' : '7.2px' }}; color: #000; }
        table { border-collapse: collapse; }
        .title { width: 100%; border: 1.2pt solid #000; margin-bottom: 5px; }
        .title td { text-align: center; padding: 3px; }
        .org { font-size: 9px; font-weight: bold; }
        .name { font-size: 14px; font-weight: bold; letter-spacing: .5px; }
        .meta { font-size: 8.5px; }
        .kpis { width: 100%; margin-bottom: 5px; }
        .kpis td { width: 25%; border: 0.7pt solid #000; text-align: center; padding: 3px 2px; }
        .kpis .lbl { font-size: 7.5px; font-weight: bold; border-bottom: 0.5pt solid #000; padding-bottom: 2px; }
        .kpis .val { font-size: {{ $isLandscape ? '12px' : '10.5px' }}; font-weight: bold; padding-top: 2px; }
        .cols { width: 100%; margin-bottom: 5px; }
        .cols > tbody > tr > td { width: 33.33%; vertical-align: top; padding: 0; }
        .cols > tbody > tr > td.mid { padding: 0 4px; }
        table.grid { width: 100%; margin-bottom: 4px; }
        table.grid th, table.grid td { border: 0.6pt solid #000; padding: 2px 3px; }
        table.grid .cap th { font-size: {{ $isLandscape ? '8.6px' : '7.6px' }}; text-transform: uppercase; text-align: center; padding: 3px; }
        table.grid .hd th { font-weight: bold; background: #ececec; }
        .idx { width: 12px; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .left { text-align: left; }
        .sub td { font-weight: bold; }
        .tot td { font-weight: bold; border-top: 1.2pt solid #000; background: #f2f2f2; }
        .ledger { width: 100%; border: 1.2pt solid #000; margin-bottom: 4px; }
        .ledger td { padding: 5px 6px; font-weight: bold; }
        .ledger .lv { text-align: right; font-size: {{ $isLandscape ? '13px' : '11px' }}; }
        .salesmen thead { display: table-header-group; }
        .salesmen tr { page-break-inside: avoid; }
        .foot { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7px; color: #444; text-align: center; }
    </style>
</head>
<body>
    <div class="foot">Daily Business Summary · {{ $companyLabel }} · {{ $reportDate->format('d.m.Y') }} · printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</div>

    <table class="title">
        <tr><td>
            <div class="org">SHAHZAIN TRADERS - MUZAFFARABAD</div>
            <div class="name">DAILY BUSINESS SUMMARY</div>
            <div class="meta">Distributor: <b>{{ $companyLabel }}</b> &nbsp;&nbsp; Date: <b>{{ $reportDate->format('d.m.Y') }}</b>@if ($designation !== '') &nbsp;&nbsp; Designation: <b>{{ $designation }}</b>@endif</div>
        </td></tr>
    </table>

    <table class="kpis">
        <tr>
            <td><div class="lbl">TOTAL SALES (MONTH TO DATE)</div><div class="val">{{ $amount($report['total_sales_mtd']) }}</div></td>
            <td><div class="lbl">TODAY'S CASH</div><div class="val">{{ $amount($totals['cash']) }}</div></td>
            <td><div class="lbl">TOTAL CREDIT</div><div class="val">{{ $amount($totals['credit']) }}</div></td>
            <td><div class="lbl">TOTAL DISCOUNT (TODAY)</div><div class="val">{{ $amount($totals['discount']) }}</div></td>
        </tr>
    </table>

    <table class="cols">
        <tr>
            <td>
                <table class="grid">
                    <tr class="cap"><th colspan="3">Investment Position</th></tr>
                    <tr class="hd"><th class="idx">#</th><th class="left">Head</th><th class="num">Amount</th></tr>
                    @foreach ($investmentRows as $label => $value)
                        <tr><td class="idx">{{ $loop->iteration }}</td><td>{{ $label }}</td><td class="num">{{ $amount($value) }}</td></tr>
                    @endforeach
                    <tr class="tot"><td></td><td>Total Investment</td><td class="num">{{ $amount($investment['total']) }}</td></tr>
                </table>
                <table class="ledger">
                    <tr><td>LEDGER AMOUNT</td><td class="lv">{{ $signed($investment['ledger']) }}</td></tr>
                </table>
            </td>
            <td class="mid">
                <table class="grid">
                    <tr class="cap"><th colspan="3">Category-wise Sales ({{ $period }})</th></tr>
                    <tr class="hd"><th class="idx">#</th><th class="left">Category</th><th class="num">Sales</th></tr>
                    @forelse ($report['categories'] as $category)
                        <tr><td class="idx">{{ $loop->iteration }}</td><td>{{ $category->name }}</td><td class="num">{{ $amount($category->amount) }}</td></tr>
                    @empty
                        <tr><td colspan="3" style="text-align:center">No sales this month</td></tr>
                    @endforelse
                    <tr class="tot"><td></td><td>Total Sales</td><td class="num">{{ $amount($report['categories']->sum('amount')) }}</td></tr>
                </table>
                <table class="grid">
                    <tr class="cap"><th colspan="3">Category Group Summary</th></tr>
                    <tr class="hd"><th class="idx">#</th><th class="left">Group</th><th class="num">Sales</th></tr>
                    @foreach ($report['category_groups'] as $group => $value)
                        <tr><td class="idx">{{ $loop->iteration }}</td><td>{{ $group }}</td><td class="num">{{ $amount($value) }}</td></tr>
                    @endforeach
                    <tr class="tot"><td></td><td>Total</td><td class="num">{{ $amount(array_sum($report['category_groups'])) }}</td></tr>
                </table>
            </td>
            <td>
                <table class="grid">
                    <tr class="cap"><th colspan="3">TTS Summary (Month to date)</th></tr>
                    <tr class="hd"><th class="idx">#</th><th class="left">Head</th><th class="num">Amount</th></tr>
                    <tr><td class="idx">1</td><td>TTS Received</td><td class="num">{{ $amount($tts['tts_received']) }}</td></tr>
                    <tr><td class="idx">2</td><td>Promo Received</td><td class="num">{{ $amount($tts['promo_received']) }}</td></tr>
                    <tr class="sub"><td></td><td>Total Received</td><td class="num">{{ $amount($tts['total_received']) }}</td></tr>
                    <tr><td class="idx">3</td><td>TTS Passed</td><td class="num">{{ $amount($tts['tts_passed']) }}</td></tr>
                    <tr><td class="idx">4</td><td>Promo Passed</td><td class="num">{{ $amount($tts['promo_passed']) }}</td></tr>
                    <tr class="sub"><td></td><td>Total Scheme Passed</td><td class="num">{{ $amount($tts['total_passed']) }}</td></tr>
                    <tr><td class="idx">5</td><td>Percentage Passed</td><td class="num">{{ $amount($tts['percentage_passed']) }}</td></tr>
                    <tr class="tot"><td></td><td>Balance</td><td class="num">{{ $signed($tts['balance']) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="grid salesmen">
        <thead>
            <tr class="cap"><th colspan="{{ $allCompanies ? 11 : 10 }}">Daily Salesman-wise Performance &amp; Credit and Cash Detail</th></tr>
            <tr class="hd">
                <th class="idx">#</th>
                <th class="left">Salesman Name</th>
                @if ($allCompanies)
                    <th class="left">Company</th>
                @endif
                <th class="num">Sales (PKR)</th>
                <th class="num">%age</th>
                <th class="num">Scheme</th>
                <th class="num">Total Discount</th>
                <th class="num">Expiry Liquid</th>
                <th class="num">Expiry Powder</th>
                <th class="num">Credit (PKR)</th>
                <th class="num">Amount (PKR)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['salesmen'] as $row)
                <tr>
                    <td class="idx">{{ $loop->iteration }}</td>
                    <td>{{ $row->name }}</td>
                    @if ($allCompanies)
                        <td>{{ $row->company }}</td>
                    @endif
                    <td class="num">{{ $money($row->sales) }}</td>
                    <td class="num">{{ $money($row->percentage) }}</td>
                    <td class="num">{{ $money($row->scheme) }}</td>
                    <td class="num">{{ $money($row->discount) }}</td>
                    <td class="num">{{ $money($row->expiry_liquid) }}</td>
                    <td class="num">{{ $money($row->expiry_powder) }}</td>
                    <td class="num">{{ $money($row->credit) }}</td>
                    <td class="num">{{ $money($row->cash) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $allCompanies ? 11 : 10 }}" style="text-align:center">No salesman data for this date</td></tr>
            @endforelse
            <tr class="tot">
                <td></td>
                <td colspan="{{ $allCompanies ? 2 : 1 }}">TOTAL</td>
                <td class="num">{{ $amount($totals['sales']) }}</td>
                <td class="num">{{ $amount($totals['percentage']) }}</td>
                <td class="num">{{ $amount($totals['scheme']) }}</td>
                <td class="num">{{ $amount($totals['discount']) }}</td>
                <td class="num">{{ $amount($totals['expiry_liquid']) }}</td>
                <td class="num">{{ $amount($totals['expiry_powder']) }}</td>
                <td class="num">{{ $amount($totals['credit']) }}</td>
                <td class="num">{{ $amount($totals['cash']) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
