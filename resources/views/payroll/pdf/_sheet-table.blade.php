{{-- The salary sheet table: used by the PDF and the Excel download. $sheet comes from PayrollSheetService::build(). --}}
@php
    // Excel ($raw) gets plain numbers so its cells add up; the PDF gets thousands separators.
    $raw = $raw ?? false;
    $fmt = fn ($value) => (float) $value == 0.0 ? '' : ($raw ? round((float) $value, 2) : number_format((float) $value, 0));
    $netFmt = fn ($value) => $raw ? round((float) $value, 2) : number_format((float) $value, 0);
    $fixed = $sheet['columns']['fixed'];
    $variable = $sheet['columns']['variable'];
    $deductions = $sheet['columns']['deduction'];
    $span = 3 + count($fixed) + 1 + count($variable) + ($variable ? 1 : 0) + 1 + count($deductions) + 1 + 1;
    $i = 0;
@endphp
<table class="grid sheet">
    <thead>
        <tr class="bands">
            <th colspan="3"></th>
            <th colspan="{{ count($fixed) + 1 }}" class="band band-fixed">Fixed part</th>
            @if ($variable)<th colspan="{{ count($variable) + 1 }}" class="band band-var">Variable part</th>@endif
            <th class="band band-gross"></th>
            <th colspan="{{ count($deductions) + 1 }}" class="band band-ded">Deductions</th>
            <th class="band band-net"></th>
        </tr>
        <tr>
            <th class="c-no">S.No.</th><th class="c-name">Employee name</th><th class="c-job">Job description</th>
            @foreach ($fixed as $column)<th class="n">{{ $column['label'] }}</th>@endforeach
            <th class="n tot">Total fixed</th>
            @foreach ($variable as $column)<th class="n">{{ $column['label'] }}</th>@endforeach
            @if ($variable)<th class="n tot">Total variable</th>@endif
            <th class="n tot">Gross salary</th>
            @foreach ($deductions as $column)<th class="n">{{ $column['label'] }}</th>@endforeach
            <th class="n tot">Total deductions</th>
            <th class="n net">Net salary</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($sheet['groups'] as $group => $rows)
            @if ($group !== '')
                <tr class="grp"><td colspan="{{ $span }}">{{ $group }} · {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('employee', $rows->count()) }}</td></tr>
            @endif
            @foreach ($rows as $row)
                <tr>
                    <td class="c-no">{{ ++$i }}</td>
                    <td class="c-name">{{ $row['slip']->employee?->name }}@unless ($raw)<span class="code"> {{ $row['slip']->employee?->employee_code }}</span>@endunless</td>
                    <td class="c-job">{{ $row['slip']->employee?->designation }}</td>
                    @foreach ($fixed as $column)<td class="n">{{ $fmt($row['amounts'][$column['key']] ?? 0) }}</td>@endforeach
                    <td class="n tot">{{ $fmt($row['fixed']) }}</td>
                    @foreach ($variable as $column)<td class="n">{{ $fmt($row['amounts'][$column['key']] ?? 0) }}</td>@endforeach
                    @if ($variable)<td class="n tot">{{ $fmt($row['variable']) }}</td>@endif
                    <td class="n tot">{{ $fmt($row['earnings']) }}</td>
                    @foreach ($deductions as $column)<td class="n">{{ $fmt($row['amounts'][$column['key']] ?? 0) }}</td>@endforeach
                    <td class="n tot">{{ $fmt($row['deductions']) }}</td>
                    <td class="n net">{{ $netFmt($row['net']) }}</td>
                </tr>
            @endforeach
            @if ($group !== '' && $sheet['groups']->count() > 1)
                <tr class="sub">
                    <td colspan="3">Subtotal {{ $group }}</td>
                    @foreach ($fixed as $column)<td class="n">{{ $fmt($rows->sum(fn ($r) => $r['amounts'][$column['key']] ?? 0)) }}</td>@endforeach
                    <td class="n tot">{{ $fmt($rows->sum('fixed')) }}</td>
                    @foreach ($variable as $column)<td class="n">{{ $fmt($rows->sum(fn ($r) => $r['amounts'][$column['key']] ?? 0)) }}</td>@endforeach
                    @if ($variable)<td class="n tot">{{ $fmt($rows->sum('variable')) }}</td>@endif
                    <td class="n tot">{{ $fmt($rows->sum('earnings')) }}</td>
                    @foreach ($deductions as $column)<td class="n">{{ $fmt($rows->sum(fn ($r) => $r['amounts'][$column['key']] ?? 0)) }}</td>@endforeach
                    <td class="n tot">{{ $fmt($rows->sum('deductions')) }}</td>
                    <td class="n net">{{ $netFmt($rows->sum('net')) }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3">Total · {{ $sheet['rows']->count() }} {{ \Illuminate\Support\Str::plural('employee', $sheet['rows']->count()) }}</td>
            @foreach ($fixed as $column)<td class="n">{{ $fmt($sheet['totals'][$column['key']]) }}</td>@endforeach
            <td class="n tot">{{ $fmt($sheet['totals']['fixed']) }}</td>
            @foreach ($variable as $column)<td class="n">{{ $fmt($sheet['totals'][$column['key']]) }}</td>@endforeach
            @if ($variable)<td class="n tot">{{ $fmt($sheet['totals']['variable']) }}</td>@endif
            <td class="n tot">{{ $fmt($sheet['totals']['earnings']) }}</td>
            @foreach ($deductions as $column)<td class="n">{{ $fmt($sheet['totals'][$column['key']]) }}</td>@endforeach
            <td class="n tot">{{ $fmt($sheet['totals']['deductions']) }}</td>
            <td class="n net">{{ $netFmt($sheet['totals']['net']) }}</td>
        </tr>
    </tfoot>
</table>
