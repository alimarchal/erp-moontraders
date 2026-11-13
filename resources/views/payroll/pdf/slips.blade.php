<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $run->run_number }} salary slips</title>@include('payroll.pdf._style')</head>
<body>
@php
    $fmt = fn ($v) => number_format((float) $v, 2);
    $slipStatus = $run->status === 'paid' && $run->paid_on ? 'Paid on '.$run->paid_on->format('d-M-Y') : $run->statusLabel();
@endphp
@foreach($slips as $slip)
    @php
        $earn = $slip->lines->where('kind', 'earning')->values();
        $ded = $slip->lines->where('kind', 'deduction')->values();
        $rows = max($earn->count(), $ded->count(), 1);
    @endphp
    <div class="slip {{ $loop->iteration % 3 === 0 && ! $loop->last ? 'brk' : '' }}">
        <table class="head"><tr>
            <td><h1>{{ $slip->employee?->supplier?->supplier_name ?? $run->supplier?->supplier_name ?? $run->company?->company_name ?? 'Moon Traders' }}</h1><div class="sub">Salary slip · {{ $run->period_month->format('F Y') }}</div></td>
            <td class="ref">{{ $run->run_number }}<div class="sub">Status: {{ $slipStatus }}</div></td>
        </tr></table>
        <table class="meta"><tr>
            <td><b>Employee</b> {{ $slip->employee?->name }}</td><td><b>Code</b> {{ $slip->employee?->employee_code }}</td><td><b>Designation</b> {{ $slip->employee?->designation ?: '—' }}</td>
            <td><b>Type</b> {{ $slip->salary_type === 'fixed' ? 'Fixed' : 'Basic' }}</td><td><b>Days</b> {{ $slip->days_in_month }}</td><td><b>Absent</b> {{ rtrim(rtrim(number_format((float) $slip->absent_days, 2), '0'), '.') ?: '0' }}</td>
        </tr></table>
        <table class="grid">
            <thead><tr><th style="width:32%">Earnings</th><th class="n" style="width:18%">Rs</th><th style="width:32%">Deductions</th><th class="n" style="width:18%">Rs</th></tr></thead>
            <tbody>
                @for($i = 0; $i < $rows; $i++)
                    <tr><td>{{ $earn[$i]->label ?? '' }}</td><td class="n">{{ isset($earn[$i]) ? $fmt($earn[$i]->amount) : '' }}</td><td>{{ $ded[$i]->label ?? '' }}</td><td class="n">{{ isset($ded[$i]) ? $fmt($ded[$i]->amount) : '' }}</td></tr>
                @endfor
            </tbody>
            <tfoot>
                <tr><td>Total earnings</td><td class="n">{{ $fmt($slip->total_earnings) }}</td><td>Total deductions</td><td class="n">{{ $fmt($slip->total_deductions) }}</td></tr>
                <tr class="net"><td colspan="2">Net pay</td><td colspan="2" class="n">Rs {{ $fmt($slip->net_pay) }}</td></tr>
            </tfoot>
        </table>
        <table class="sign"><tr><td>Prepared by {{ $run->creator?->name }}</td><td class="sp"></td><td>Approved by {{ $run->checker?->name }}</td><td class="sp"></td><td>Employee signature</td></tr></table>
    </div>
@endforeach
</body></html>
