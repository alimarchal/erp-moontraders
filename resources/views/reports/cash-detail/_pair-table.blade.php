{{-- Two salesman lists side by side in ONE table, so long lists break across pages row by row. --}}
@php
    [$left, $right] = $pair;
    $count = max($left['rows']->count(), $right['rows']->count(), 1);
@endphp
<table class="p2 p2-twin">
    <colgroup>
        <col style="width: 4%"><col style="width: 32%"><col style="width: 13%"><col style="width: 2%"><col style="width: 4%"><col style="width: 32%"><col style="width: 13%">
    </colgroup>
    <thead>
        <tr class="cap">
            @foreach ([$left, $right] as $side)
                <th colspan="3">{{ $side['no'] }} - {{ $side['heading'] }} <small>{{ $side['explain'] }} - {{ $side['rows']->where('amount', '>', 0)->count() }} of {{ $side['rows']->count() }} salesmen</small></th>
                @if ($loop->first)<th class="sp"></th>@endif
            @endforeach
        </tr>
        <tr>
            @foreach ([$left, $right] as $side)
                <th class="c" style="width: 4%">#</th>
                <th>Salesman</th>
                <th class="n">Amount (Rs.)</th>
                @if ($loop->first)<th class="sp"></th>@endif
            @endforeach
        </tr>
    </thead>
    <tbody>
        @for ($i = 0; $i < $count; $i++)
            <tr>
                @foreach ([$left, $right] as $side)
                    @php $row = $side['rows']->get($i); @endphp
                    @if ($row)
                        <td class="c">{{ $i + 1 }}</td>
                        <td>{{ $row->salesman_name }}</td>
                        <td class="n b">{{ $cell($row->amount) }}</td>
                    @elseif ($i === 0)
                        <td colspan="3" class="p2-none">{{ $side['emptyText'] }}</td>
                    @else
                        <td class="blank">&nbsp;</td><td class="blank"></td><td class="blank"></td>
                    @endif
                    @if ($loop->first)<td class="sp"></td>@endif
                @endforeach
            </tr>
        @endfor
        <tr class="tot">
            @foreach ([$left, $right] as $side)
                <td colspan="2" class="n">Total {{ $side['heading'] }}</td>
                <td class="n">{{ $money($side['rows']->sum('amount')) }}</td>
                @if ($loop->first)<td class="sp"></td>@endif
            @endforeach
        </tr>
    </tbody>
</table>
