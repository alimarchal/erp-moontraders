@extends('reports.pdf-layout')

@section('content')
    @php
        $money = fn ($value) => number_format((float) $value, 0);
        $barColor = fn ($pct) => $pct >= 100 ? '#16a34a' : ($pct >= 70 ? '#f59e0b' : '#e11d48');
    @endphp
    <table class="data">
        <thead>
            <tr>
                <th class="center" style="width: 5%">#</th>
                <th style="text-align: left">Salesman</th>
                <th style="text-align: left">Channel</th>
                <th class="num">Target</th>
                <th class="num">Achieved</th>
                <th class="num">Remaining / Day</th>
                <th class="num" style="width: 11%">%age</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($summary['rows'] as $row)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td class="bold">{{ $row['employee'] }}</td>
                    <td>{{ $row['label'] }}</td>
                    <td class="num">{{ $money($row['total']) }}</td>
                    <td class="num">{{ $money($row['achieved']) }}</td>
                    <td class="num">{{ $money($row['remaining_per_day']) }}</td>
                    <td class="num bold" style="color: {{ $barColor($row['percentage']) }}">{{ number_format($row['percentage'], 0) }}%</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center muted">No data found</td></tr>
            @endforelse
        </tbody>
        @if ($summary['rows']->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="3" class="num">Target</td>
                    <td class="num">{{ $money($summary['totals']['total']) }}</td>
                    <td class="num">{{ $money($summary['totals']['achieved']) }}</td>
                    <td class="num">{{ $money($summary['totals']['remaining_per_day']) }}</td>
                    <td class="num">{{ number_format($summary['totals']['percentage'], 2) }}%</td>
                </tr>
            </tfoot>
        @endif
    </table>
    <div class="note">Achieved is the sales of posted settlements in the period. Remaining / Day spreads what is left of the target over the days still to go. Percent: green is 100% or more, amber from 70%, red below.</div>
@endsection
