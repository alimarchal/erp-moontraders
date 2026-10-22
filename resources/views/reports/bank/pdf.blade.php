<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111827; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #1e2a5a; }
        .sub { color: #6b7280; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #166534; color: #fff; padding: 4px 5px; text-align: left; font-size: 8px; text-transform: uppercase; }
        td { padding: 3px 5px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; }
        .total td { font-weight: bold; background: #f3f4f6; border-top: 1px solid #6b7280; }
        .note { margin-top: 6px; padding: 4px 6px; background: #fefce8; border: 1px solid #fde68a; }
        .foot { margin-top: 8px; color: #9ca3af; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $data['title'] }}</h1>
    <div class="sub">{{ $data['subtitle'] }}</div>
    <table>
        <thead><tr>@foreach ($data['headings'] as $i => $heading)<th class="{{ in_array($i, $data['numeric'], true) ? 'num' : '' }}">{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($data['rows'] as $row)
                <tr>@foreach ($row as $i => $cell)<td class="{{ in_array($i, $data['numeric'], true) ? 'num' : '' }}">{{ is_float($cell) || (is_int($cell) && in_array($i, $data['numeric'], true) && $i > 1) ? number_format($cell, 2) : $cell }}</td>@endforeach</tr>
            @endforeach
            @if ($data['totals'])
                <tr class="total">@foreach ($data['totals'] as $i => $cell)<td class="{{ in_array($i, $data['numeric'], true) ? 'num' : '' }}">{{ is_float($cell) || (is_int($cell) && in_array($i, $data['numeric'], true) && $i > 1) ? number_format($cell, 2) : $cell }}</td>@endforeach</tr>
            @endif
        </tbody>
    </table>
    @foreach ($data['notes'] as $note)<div class="note">{{ $note }}</div>@endforeach
    <div class="foot">Generated {{ $generatedAt->format('d-m-Y H:i') }}</div>
</body>
</html>
