<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $run->run_number }}</title>@include('payroll.pdf._style')</head>
<body>
@php
    $fmt = fn ($v) => number_format((float) $v, 2);
    $slips = $run->slips->sortBy(fn ($s) => $s->employee?->name);
    $groups = $run->supplier_id ? collect(['' => $slips]) : $slips->groupBy(fn ($s) => $s->employee?->supplier?->supplier_name ?? 'Moon Traders (head office)')->sortKeys();
@endphp
<table class="head"><tr>
    <td><h1>{{ $run->ownerLabel() }}</h1><div class="sub">Payroll register · {{ $run->period_month->format('F Y') }}</div></td>
    <td class="ref">{{ $run->run_number }}<div class="sub">{{ $run->statusLabel() }}</div></td>
</tr></table>
<table class="meta"><tr>
    <td><b>Prepared by</b> {{ $run->creator?->name ?? '—' }}</td>
    <td><b>Checked by</b> {{ $run->checker?->name ?? '—' }}</td>
    <td><b>Paid</b> {{ $run->paid_on ? $run->paid_on->format('d-M-Y').' ('.($run->paid_method === 'bank' ? 'bank' : 'cash').')' : '—' }}</td>
    <td class="n"><b>Employees</b> {{ $slips->count() }}</td>
</tr></table>
<table class="grid">
    <thead><tr><th>#</th><th>Code</th><th>Employee</th><th>Designation</th><th class="n">Absent</th><th class="n">Earnings</th><th class="n">Deductions</th><th class="n">Net pay</th></tr></thead>
    <tbody>
        @php $i = 0; @endphp
        @foreach($groups as $group => $rows)
            @if($group !== '')
                <tr class="grp"><td colspan="5">{{ $group }} · {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('employee', $rows->count()) }}</td><td class="n">{{ $fmt($rows->sum('total_earnings')) }}</td><td class="n">{{ $fmt($rows->sum('total_deductions')) }}</td><td class="n">{{ $fmt($rows->sum('net_pay')) }}</td></tr>
            @endif
            @foreach($rows as $slip)
                <tr><td>{{ ++$i }}</td><td>{{ $slip->employee?->employee_code }}</td><td>{{ $slip->employee?->name }}</td><td>{{ $slip->employee?->designation }}</td><td class="n">{{ rtrim(rtrim(number_format((float) $slip->absent_days, 2), '0'), '.') ?: '0' }}</td><td class="n">{{ $fmt($slip->total_earnings) }}</td><td class="n">{{ $fmt($slip->total_deductions) }}</td><td class="n">{{ $fmt($slip->net_pay) }}</td></tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot><tr><td colspan="5">Total</td><td class="n">{{ $fmt($run->total_earnings) }}</td><td class="n">{{ $fmt($run->total_deductions) }}</td><td class="n">{{ $fmt($run->total_net) }}</td></tr></tfoot>
</table>
<table class="sign"><tr><td>Prepared by</td><td class="sp"></td><td>Checked by</td><td class="sp"></td><td>Approved by</td></tr></table>
</body></html>
