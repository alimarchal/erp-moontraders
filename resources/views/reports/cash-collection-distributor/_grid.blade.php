{{--
    One table of the sheet. Only the rows that exist are drawn (no padding rows). With $collapse, a table whose
    amounts are all zero is replaced by a single line, and with $hideZeroRows a row whose amounts are all zero is left out.
--}}
@php
    $isZero = fn ($cell) => in_array(trim((string) $cell), ['', '-', '0', '0.00'], true);
    $amountColumns = collect($left['cols'])->keys()->filter(fn ($c) => $left['cols'][$c][1] === 'n')->all();
    $rowIsZero = fn (array $row) => collect($amountColumns)->every(fn ($c) => $isZero($row[$c] ?? ''));
    $rows = collect($left['rows']);
    $allZero = ($collapse ?? false) && $rows->every($rowIsZero) && collect($left['total']['cells'])->every($isZero);
    if (! empty($hideZeroRows)) {
        $rows = $rows->reject($rowIsZero)->values();
    }
    $numbered = ($left['cols'][0][0] ?? '') === '#';
    $none = $left['empty'] !== '' ? $left['empty'] : ($emptyText ?? 'Nothing recorded for this day.');
@endphp
<table class="p2 p2-twin">
    @if ($allZero || ($rows->isEmpty() && $left['empty'] !== ''))
        <thead>
            <tr class="cap">
                <th>{{ $left['no'] }} - {{ $left['title'] }} <small>{{ $left['explain'] }}</small></th>
            </tr>
        </thead>
        <tbody>
            <tr><td class="p2-none">{{ $none }}</td></tr>
        </tbody>
    @else
    <colgroup>
        @foreach ($left['cols'] as $col)
            <col style="width: {{ $col[2] }}%">
        @endforeach
    </colgroup>
    <thead>
        <tr class="cap">
            <th colspan="{{ count($left['cols']) }}">{{ $left['no'] }} - {{ $left['title'] }} <small>{{ $left['explain'] }}</small></th>
        </tr>
        <tr>
            @foreach ($left['cols'] as $col)
                <th class="{{ $col[1] }}">{!! nl2br(e($col[0])) !!}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $i => $row)
            <tr>
                @foreach ($left['cols'] as $c => $col)
                    <td class="{{ $col[1] }} {{ in_array($c, $left['bold'], true) ? 'b' : '' }}">{{ $numbered && $c === 0 ? $i + 1 : $row[$c] }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr class="tot">
            <td colspan="{{ $left['total']['span'] }}" class="n">{{ $left['total']['label'] }}</td>
            @foreach ($left['total']['cells'] as $cell)<td class="n">{{ $cell }}</td>@endforeach
        </tr>
    </tbody>
    @endif
</table>
