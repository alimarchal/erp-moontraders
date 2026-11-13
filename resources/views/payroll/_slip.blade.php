{{-- One payslip: earnings on the left, deductions on the right. Used on screen, for one slip and for the whole run. --}}
@php
    $earnings = $slip->lines->where('kind', 'earning');
    $deductions = $slip->lines->where('kind', 'deduction');
    $rows = max($earnings->count(), $deductions->count());
    $earningRows = $earnings->values();
    $deductionRows = $deductions->values();
    $slipStatus = $run->status === 'paid' && $run->paid_on ? 'Paid on '.$run->paid_on->format('d-M-Y') : $run->statusLabel();
@endphp
<article class="ps-slip">
    <header class="ps-head">
        <div>
            <div class="ps-company">{{ $run->supplier?->supplier_name ?? $run->company?->company_name ?? 'Moon Traders' }}</div>
            <div class="ps-title">Salary slip · {{ $run->period_month->format('F Y') }}</div>
        </div>
        <div class="ps-ref">{{ $run->run_number }}<div class="ps-status">Status: {{ $slipStatus }}</div></div>
    </header>
    <dl class="ps-emp">
        <div><dt>Employee</dt><dd>{{ $slip->employee?->name }}</dd></div>
        <div><dt>Code</dt><dd>{{ $slip->employee?->employee_code }}</dd></div>
        <div><dt>Designation</dt><dd>{{ $slip->employee?->designation ?: '—' }}</dd></div>
        <div><dt>Salary type</dt><dd>{{ $slip->salary_type === 'fixed' ? 'Fixed' : 'Basic' }}</dd></div>
        <div><dt>Days in month</dt><dd>{{ $slip->days_in_month }}</dd></div>
        <div><dt>Absent days</dt><dd>{{ rtrim(rtrim(number_format((float) $slip->absent_days, 2), '0'), '.') ?: '0' }}</dd></div>
    </dl>
    <table class="ps-table">
        <thead><tr><th>Earnings</th><th class="n">Rs</th><th class="gap"></th><th>Deductions</th><th class="n">Rs</th></tr></thead>
        <tbody>
            @for($i = 0; $i < max($rows, 1); $i++)
                <tr>
                    <td>{{ $earningRows[$i]->label ?? '' }}</td><td class="n">{{ isset($earningRows[$i]) ? number_format((float) $earningRows[$i]->amount, 2) : '' }}</td>
                    <td class="gap"></td>
                    <td>{{ $deductionRows[$i]->label ?? '' }}</td><td class="n">{{ isset($deductionRows[$i]) ? number_format((float) $deductionRows[$i]->amount, 2) : '' }}</td>
                </tr>
            @endfor
        </tbody>
        <tfoot>
            <tr><td>Total earnings</td><td class="n">{{ number_format((float) $slip->total_earnings, 2) }}</td><td class="gap"></td><td>Total deductions</td><td class="n">{{ number_format((float) $slip->total_deductions, 2) }}</td></tr>
            <tr class="net"><td colspan="3">Net pay</td><td colspan="2" class="n">Rs {{ number_format((float) $slip->net_pay, 2) }}</td></tr>
        </tfoot>
    </table>
    <div class="ps-sign"><div>Prepared by</div><div>Employee signature</div></div>
</article>
