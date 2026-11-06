<!doctype html>
<html><head><meta charset="utf-8"><title>Payroll register</title>@include('payroll.pdf._style')</head>
<body>
@php $fmt = fn ($v) => number_format((float) $v, 2); @endphp
<table class="head"><tr>
    <td><h1>Payroll register</h1><div class="sub">{{ $subtitle }}</div></td>
    <td class="ref">{{ now()->format('d-M-Y H:i') }}<div class="sub">{{ $runs->count() }} {{ \Illuminate\Support\Str::plural('payroll', $runs->count()) }}</div></td>
</tr></table>
<table class="grid">
    <thead><tr><th>Run</th><th>Month</th><th>Company</th><th class="n">Staff</th><th class="n">Earnings</th><th class="n">Deductions</th><th class="n">Net pay</th><th>Status</th></tr></thead>
    <tbody>
        @forelse($runs as $run)
            <tr><td>{{ $run->run_number }}</td><td>{{ $run->period_month->format('M Y') }}</td><td>{{ $run->ownerLabel() }}</td><td class="n">{{ $run->slips_count }}</td><td class="n">{{ $fmt($run->total_earnings) }}</td><td class="n">{{ $fmt($run->total_deductions) }}</td><td class="n">{{ $fmt($run->total_net) }}</td><td>{{ $run->statusLabel() }}</td></tr>
        @empty
            <tr><td colspan="8" style="text-align:center; padding:16px">No payroll found.</td></tr>
        @endforelse
    </tbody>
    <tfoot><tr><td colspan="4">Total</td><td class="n">{{ $fmt($runs->sum('total_earnings')) }}</td><td class="n">{{ $fmt($runs->sum('total_deductions')) }}</td><td class="n">{{ $fmt($runs->sum('total_net')) }}</td><td></td></tr></tfoot>
</table>
</body></html>
