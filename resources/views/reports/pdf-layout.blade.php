{{--
    Shared A4 report layout for the PDFs: banner, coloured figure tiles, chart cards and the table.
    Child views pass: $title, $subtitle (lines under the title), $meta (right side of the banner),
    $tiles [label, value, note, color], $charts [title, subtitle, svg(callable width), wide].
    DomPDF has no flex or grid, so everything is laid out with tables.
--}}
@php
    $landscape = ($orientation ?? 'portrait') === 'landscape';
    $perRow = $landscape ? 3 : 2;
    $gap = 8;
    $cardPt = (int) floor(($contentWidth - $gap * ($perRow - 1)) / $perRow);
    $rows = [];
    $current = [];
    foreach ($charts ?? [] as $card) {
        $wide = ($card['wide'] ?? false) && ! $landscape;
        if ($wide) {
            if ($current) {
                $rows[] = $current;
                $current = [];
            }
            $rows[] = [$card + ['span' => $perRow]];
            continue;
        }
        $current[] = $card + ['span' => 1];
        if (count($current) === $perRow) {
            $rows[] = $current;
            $current = [];
        }
    }
    if ($current) {
        $rows[] = $current;
    }
    $tileCount = max(1, count($tiles ?? []));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @font-face { font-family: 'Lato'; font-style: normal; font-weight: normal; src: url('{{ resource_path('fonts/Lato-Regular.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Lato'; font-style: normal; font-weight: bold; src: url('{{ resource_path('fonts/Lato-Bold.ttf') }}') format('truetype'); }
        @page { margin: 36pt; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: {{ $landscape ? '8.5pt' : '8pt' }}; color: #0f172a; }
        table { border-collapse: collapse; }
        .banner { width: 100%; background: #0b2a6f; color: #ffffff; }
        .banner td { padding: 10pt 14pt; vertical-align: middle; }
        .brand { font-size: 7.5pt; letter-spacing: 1.4pt; color: #93c5fd; font-weight: bold; }
        .title { font-size: 17pt; font-weight: bold; margin-top: 2pt; }
        .sub { font-size: 8.5pt; color: #dbeafe; margin-top: 3pt; }
        .meta { text-align: right; font-size: 8pt; color: #dbeafe; line-height: 1.6; }
        .meta b { color: #ffffff; }
        .stripe { width: 100%; height: 4pt; }
        .stripe td { height: 4pt; padding: 0; }
        .tiles { width: 100%; margin-top: 8pt; }
        .tile { color: #ffffff; padding: 8pt 10pt; vertical-align: top; }
        .tile-label { font-size: 7pt; font-weight: bold; letter-spacing: .6pt; text-transform: uppercase; opacity: .9; }
        .tile-value { font-size: {{ $landscape ? '15pt' : '13pt' }}; font-weight: bold; margin-top: 3pt; }
        .tile-note { font-size: 7pt; margin-top: 2pt; }
        .cards { width: 100%; margin-top: 8pt; }
        .cards td { vertical-align: top; padding: 0; }
        .card { border: 1px solid #cbd5e1; padding: 6pt 8pt 4pt; background: #ffffff; }
        .card-title { font-size: 9pt; font-weight: bold; color: #0b2a6f; border-left: 3pt solid #2563eb; padding-left: 5pt; }
        .card-sub { font-size: 7pt; color: #64748b; padding-left: 8pt; margin-bottom: 3pt; }
        .data { width: 100%; margin-top: 9pt; }
        .data th { background: #0b2a6f; color: #ffffff; font-weight: bold; padding: 4pt 5pt; border: 1px solid #0b2a6f; font-size: {{ $landscape ? '8pt' : '7.5pt' }}; }
        .data td { padding: 3.5pt 5pt; border: 1px solid #e2e8f0; vertical-align: top; }
        .data thead { display: table-header-group; }
        .data tr { page-break-inside: avoid; }
        .data tbody tr:nth-child(even) td { background: #f8fafc; }
        .data tfoot td { background: #dbeafe; font-weight: bold; border: 1px solid #93c5fd; color: #0b2a6f; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .muted { color: #64748b; }
        .small { font-size: 0.86em; }
        .bold { font-weight: bold; }
        .note { margin-top: 6pt; font-size: 7pt; color: #64748b; }
        @yield('styles')
    </style>
</head>
<body>
    <table class="banner">
        <tr>
            <td>
                <div class="brand">MOON TRADERS ERP</div>
                <div class="title">{{ $title }}</div>
                @foreach ($subtitle ?? [] as $line)
                    <div class="sub">{{ $line }}</div>
                @endforeach
            </td>
            <td class="meta">
                @foreach ($meta ?? [] as $label => $value)
                    {{ $label }}: <b>{{ $value }}</b><br>
                @endforeach
            </td>
        </tr>
    </table>
    <table class="stripe"><tr>
        @foreach (['#2563eb', '#16a34a', '#f97316', '#7c3aed', '#e11d48'] as $stripe)
            <td style="background: {{ $stripe }}"></td>
        @endforeach
    </tr></table>

    @if (! empty($tiles))
        <table class="tiles">
            <tr>
                @foreach ($tiles as $tile)
                    <td class="tile" style="background: {{ $tile['color'] }}; width: {{ round(100 / $tileCount, 2) }}%; {{ $loop->first ? '' : 'border-left: 4pt solid #ffffff;' }}">
                        <div class="tile-label">{{ $tile['label'] }}</div>
                        <div class="tile-value">{{ $tile['value'] }}</div>
                        <div class="tile-note">{{ $tile['note'] ?? '' }}&nbsp;</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @foreach ($rows as $row)
        <table class="cards">
            <tr>
                @foreach ($row as $card)
                    @php
                        $spanPt = $cardPt * $card['span'] + $gap * ($card['span'] - 1);
                        $innerPt = $spanPt - 18;
                    @endphp
                    <td style="width: {{ round(100 * $card['span'] / $perRow, 2) }}%; {{ $loop->first ? '' : 'padding-left: '.$gap.'pt;' }}">
                        <div class="card">
                            <div class="card-title" style="border-left-color: {{ $card['accent'] ?? '#2563eb' }}">{{ $card['title'] }}</div>
                            <div class="card-sub">{{ $card['subtitle'] ?? '' }}&nbsp;</div>
                            {!! \App\Helpers\SvgChart::img($card['svg']($card['span'] * 330 + ($card['span'] - 1) * 20), $innerPt) !!}
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endforeach

    @yield('content')
</body>
</html>
