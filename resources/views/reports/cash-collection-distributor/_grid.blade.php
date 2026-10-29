{{-- One table, or two tables side by side in ONE table so long lists break across pages row by row. --}}
@php
    $sides = array_values(array_filter([$left, $right ?? null]));
    $scale = count($sides) === 2 ? 0.49 : 1;
    $rowCount = max(array_map(fn ($side) => count($side['rows']), $sides));
    $rowCount = max($rowCount, 1);
@endphp
<table class="p2 p2-twin">
    <colgroup>
        @foreach ($sides as $side)
            @foreach ($side['cols'] as $col)
                <col style="width: {{ round($col[2] * $scale, 2) }}%">
            @endforeach
            @if ($loop->first && count($sides) === 2)<col style="width: 2%">@endif
        @endforeach
    </colgroup>
    <thead>
        <tr class="cap">
            @foreach ($sides as $side)
                <th colspan="{{ count($side['cols']) }}">{{ $side['no'] }} - {{ $side['title'] }} <small>{{ $side['explain'] }}</small></th>
                @if ($loop->first && count($sides) === 2)<th class="sp"></th>@endif
            @endforeach
        </tr>
        <tr>
            @foreach ($sides as $side)
                @foreach ($side['cols'] as $col)
                    <th class="{{ $col[1] }}">{{ $col[0] }}</th>
                @endforeach
                @if ($loop->first && count($sides) === 2)<th class="sp"></th>@endif
            @endforeach
        </tr>
    </thead>
    <tbody>
        @for ($i = 0; $i < $rowCount; $i++)
            <tr>
                @foreach ($sides as $side)
                    @php $row = $side['rows'][$i] ?? null; @endphp
                    @if ($row)
                        @foreach ($side['cols'] as $c => $col)
                            <td class="{{ $col[1] }} {{ in_array($c, $side['bold'], true) ? 'b' : '' }}">{{ $row[$c] }}</td>
                        @endforeach
                    @elseif ($i === 0 && $side['rows'] === [])
                        <td colspan="{{ count($side['cols']) }}" class="p2-none">{{ $side['empty'] }}</td>
                    @else
                        @foreach ($side['cols'] as $col)<td class="blank">&nbsp;</td>@endforeach
                    @endif
                    @if ($loop->first && count($sides) === 2)<td class="sp"></td>@endif
                @endforeach
            </tr>
        @endfor
        <tr class="tot">
            @foreach ($sides as $side)
                <td colspan="{{ $side['total']['span'] }}" class="n">{{ $side['total']['label'] }}</td>
                @foreach ($side['total']['cells'] as $cell)<td class="n">{{ $cell }}</td>@endforeach
                @if ($loop->first && count($sides) === 2)<td class="sp"></td>@endif
            @endforeach
        </tr>
    </tbody>
</table>
