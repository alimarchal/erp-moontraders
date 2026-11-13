<!doctype html>
<html><head><meta charset="utf-8"><title>Employee salaries</title>@include('payroll.pdf._style')</head>
<body>
@php $fmt = fn ($v) => number_format((float) $v, 0); @endphp
<table class="head"><tr>
    <td><h1>Employee salaries</h1><div class="sub">Standing monthly set-up · advances, settlement shorts and one-off items are added on the payslip</div></td>
    <td class="ref">{{ now()->format('d-M-Y H:i') }}<div class="sub">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('employee', $rows->count()) }}</div></td>
</tr></table>
<table class="grid">
    <thead><tr>
        <th>Employee</th><th>Type</th><th class="n">Basic</th>
        @foreach($components as $component)<th class="n">{{ $component->name }}{{ $component->isEarning() ? '' : ' (−)' }}</th>@endforeach
        <th class="n">Earnings</th><th class="n">Deductions</th><th class="n">Net</th>
    </tr></thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row['employee']->name }}<div class="sub">{{ $row['employee']->employee_code }}{{ $row['employee']->designation ? ' · '.$row['employee']->designation : '' }}</div></td>
                <td>{{ $row['employee']->salary ? ($row['employee']->salary->salary_type === 'fixed' ? 'Fixed' : 'Basic') : 'Not set' }}</td>
                <td class="n">{{ $fmt($row['basic']) }}</td>
                @foreach($components as $component)<td class="n">{{ isset($row['amounts'][$component->id]) ? $fmt($row['amounts'][$component->id]) : '' }}</td>@endforeach
                <td class="n">{{ $fmt($row['earnings']) }}</td><td class="n">{{ $fmt($row['deductions']) }}</td><td class="n">{{ $fmt($row['net']) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 6 + $components->count() }}" style="text-align:center; padding:16px">No employees found.</td></tr>
        @endforelse
    </tbody>
    <tfoot><tr>
        <td colspan="2">Total</td><td class="n">{{ $fmt($rows->sum('basic')) }}</td>
        @foreach($components as $component)<td class="n">{{ $fmt($rows->sum(fn ($row) => $row['amounts'][$component->id] ?? 0)) }}</td>@endforeach
        <td class="n">{{ $fmt($rows->sum('earnings')) }}</td><td class="n">{{ $fmt($rows->sum('deductions')) }}</td><td class="n">{{ $fmt($rows->sum('net')) }}</td>
    </tr></tfoot>
</table>
</body></html>
