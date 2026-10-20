{{-- Plain bordered table in the style of the Investment Summary report; shared by the screen and the PDF. --}}
@php
    $money = fn ($value) => number_format((float) $value, 0);
    $percent = fn ($value, $decimals = 0) => number_format((float) $value, $decimals).'%';
@endphp
<table class="report-table tabular-nums">
    <thead>
        <tr>
            <th class="text-center w-10 px-1 py-0.5">#</th>
            <th class="text-left px-1 py-0.5">Salesman</th>
            <th class="text-left px-1 py-0.5">Channel</th>
            <th class="text-right px-1 py-0.5">Target</th>
            <th class="text-right px-1 py-0.5">Achieved</th>
            <th class="text-right px-1 py-0.5">Remaining/Day</th>
            <th class="text-right px-1 py-0.5">Remaining Target Per Day<div class="text-xs font-normal">÷ {{ $summary['month_days_left'] }} working days</div></th>
            <th class="text-right px-1 py-0.5">%age</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($summary['rows'] as $row)
            <tr>
                <td class="text-center px-1 py-0.5">{{ $loop->iteration }}</td>
                <td class="px-1 py-0.5">{{ $row['employee'] }}</td>
                <td class="px-1 py-0.5">{{ $row['label'] }}</td>
                <td class="text-right font-mono px-1 py-0.5">{{ $money($row['total']) }}</td>
                <td class="text-right font-mono px-1 py-0.5">{{ $money($row['achieved']) }}</td>
                <td class="text-right font-mono px-1 py-0.5">{{ $money($row['remaining_per_day']) }}</td>
                <td class="text-right font-mono px-1 py-0.5">{{ $money($row['remaining_per_day_share']) }}</td>
                <td class="text-right font-mono px-1 py-0.5">{{ $percent($row['percentage']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center italic text-gray-500 px-1 py-0.5">No data found</td>
            </tr>
        @endforelse
    </tbody>
    @if ($summary['rows']->isNotEmpty())
        <tfoot class="font-bold">
            <tr>
                <td colspan="3" class="text-right px-1 py-0.5 border-t border-black">Target:</td>
                <td class="text-right font-mono px-1 py-0.5 border-t border-black">{{ $money($summary['totals']['total']) }}</td>
                <td class="text-right font-mono px-1 py-0.5 border-t border-black">{{ $money($summary['totals']['achieved']) }}</td>
                <td class="text-right font-mono px-1 py-0.5 border-t border-black">{{ $money($summary['totals']['remaining_per_day']) }}</td>
                <td class="text-right font-mono px-1 py-0.5 border-t border-black">{{ $money($summary['totals']['remaining_per_day_share']) }}</td>
                <td class="text-right font-mono px-1 py-0.5 border-t border-black">{{ $percent($summary['totals']['percentage'], 2) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
