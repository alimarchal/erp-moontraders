{{-- Cash Detail PDF and printable sheet: the same sheet the report page shows (_sheet partial). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Cash Collection Distributor - {{ \Carbon\Carbon::parse($date)->format('d-M-Y') }}</title>
    <style>
        @page {
            margin: 8mm 8mm 13mm 8mm;
            @unless ($isPdf ?? true)
            @bottom-left { content: "Cash Collection Distributor - {{ $companyLabel }} - {{ \Carbon\Carbon::parse($date)->format('d-M-Y') }}"; font: 8px Arial, sans-serif; color: #000; }
            @bottom-right { content: "Page " counter(page) " of " counter(pages); font: 8px Arial, sans-serif; color: #000; }
            @endunless
        }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #000; font-family: Helvetica, Arial, sans-serif; font-size: 10px; line-height: 1.3; }
        .pdf-footer { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 8px; }
    </style>
    @include('reports.cash-detail._sheet-styles')
</head>
<body>
    @if ($isPdf ?? true)
        <table class="lay pdf-footer"><tr>
            <td>Cash Collection Distributor - {{ $companyLabel }} - {{ \Carbon\Carbon::parse($date)->format('d-M-Y') }}</td>
        </tr></table>
    @endif

    @include('reports.cash-collection-distributor._sheet')
</body>
</html>
