{{-- Target vs achieved table shared by the screen and the PDF (inline styles so DomPDF renders it). --}}
@php
    $money = fn ($value) => number_format((float) $value, 0);
    $percent = fn ($value, $decimals = 0) => number_format((float) $value, $decimals).'%';
    $head = 'background:#1f2937;color:#fff;padding:8px 10px;font-size:12px;text-align:right;border:1px solid #111827;';
    $cell = 'padding:7px 10px;font-size:12px;border:1px solid #d1d5db;';
@endphp
<table style="width:100%;border-collapse:collapse;font-family:'DejaVu Sans',Arial,sans-serif;">
    <thead>
        <tr>
            <th style="{{ $head }}text-align:center;width:36px;">#</th>
            <th style="{{ $head }}text-align:left;">Salesman</th>
            <th style="{{ $head }}text-align:left;">Target</th>
            <th style="{{ $head }}">Total</th>
            <th style="{{ $head }}">Achieved</th>
            <th style="{{ $head }}">Remaining/Day</th>
            <th style="{{ $head }}">%age</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($summary['rows'] as $row)
            <tr style="background:{{ $loop->odd ? '#e8f0fb' : '#ffffff' }};">
                <td style="{{ $cell }}text-align:center;">{{ $loop->iteration }}</td>
                <td style="{{ $cell }}font-weight:bold;">{{ $row['employee'] }}</td>
                <td style="{{ $cell }}">{{ $row['label'] }}</td>
                <td style="{{ $cell }}text-align:right;">{{ $money($row['total']) }}</td>
                <td style="{{ $cell }}text-align:right;">{{ $money($row['achieved']) }}</td>
                <td style="{{ $cell }}text-align:right;">{{ $money($row['remaining_per_day']) }}</td>
                <td style="{{ $cell }}text-align:right;">{{ $percent($row['percentage']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="{{ $cell }}text-align:center;color:#6b7280;padding:24px;">No targets or sales found for these filters.</td></tr>
        @endforelse
    </tbody>
    @if ($summary['rows']->isNotEmpty())
        <tfoot>
            <tr style="background:#111827;color:#fff;font-weight:bold;">
                <td colspan="3" style="{{ $cell }}border-color:#111827;color:#fca5a5;">Total:-</td>
                <td style="{{ $cell }}border-color:#111827;text-align:right;color:#fca5a5;">{{ $money($summary['totals']['total']) }}</td>
                <td style="{{ $cell }}border-color:#111827;text-align:right;color:#fca5a5;">{{ $money($summary['totals']['achieved']) }}</td>
                <td style="{{ $cell }}border-color:#111827;text-align:right;color:#fca5a5;">{{ $money($summary['totals']['remaining_per_day']) }}</td>
                <td style="{{ $cell }}border-color:#111827;text-align:right;color:#fca5a5;">{{ $percent($summary['totals']['percentage'], 2) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
