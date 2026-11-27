{{-- Shop Directory as an A4 PDF (dompdf), portrait unless landscape was asked for: a title block with the filters and
     totals, then one black-and-white bordered row per shop with the columns the user picked (the header repeats on
     every page). --}}
@php
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $labels = \App\Http\Controllers\Reports\ShopListController::COLUMNS;
    $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d.m.Y') : '-';
    $cell = function (string $column, $customer) use ($date) {
        $balance = $customer->balance !== null ? (float) $customer->balance : null;

        return match ($column) {
            'area' => collect([$customer->sub_locality, $customer->city])->filter()->implode(', ') ?: '-',
            'channel' => $customer->channel_type.' / '.$customer->customer_category,
            'phone' => $customer->phone ?: '-',
            'balance' => $balance === null || abs($balance) < 0.005 ? '-' : ($balance < 0 ? '('.number_format(abs($balance)).')' : number_format($balance)),
            'last_sale' => $date($customer->last_sale),
            'last_recovery' => $date($customer->last_recovery),
            'status' => $customer->is_active ? 'Active' : 'Inactive',
            'address' => $customer->address ?: '-',
            'owner_cnic' => $customer->owner_cnic ?: '-',
            'ntn' => $customer->ntn ?: '-',
            'it_status' => $customer->it_status ? 'Filer' : 'Non-filer',
            'sales_rep' => $customer->salesRep?->name ?? '-',
            'credit_limit' => (float) $customer->credit_limit > 0 ? number_format((float) $customer->credit_limit) : '-',
            'accounts' => $customer->accounts ?: '-',
            'business_name' => $customer->business_name ?: '-',
            'email' => $customer->email ?: '-',
            'payment_terms' => $customer->payment_terms !== null ? $customer->payment_terms.' d' : '-',
            default => '',
        };
    };
    $numeric = ['balance', 'credit_limit', 'accounts', 'payment_terms'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Shop Directory - {{ now()->format('d.m.Y') }}</title>
    <style>
        @page { margin: 10mm 8mm 14mm 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $isLandscape ? '8px' : '7.2px' }}; color: #000; }
        table { border-collapse: collapse; }
        .title { width: 100%; border: 1.2pt solid #000; margin-bottom: 5px; }
        .title td { text-align: center; padding: 3px; }
        .org { font-size: 9px; font-weight: bold; }
        .name { font-size: 14px; font-weight: bold; letter-spacing: .5px; }
        .meta { font-size: 8px; }
        .kpis { width: 100%; margin-bottom: 5px; }
        .kpis td { width: 33.33%; border: 0.7pt solid #000; text-align: center; padding: 3px 2px; }
        .kpis .lbl { font-size: 7.5px; font-weight: bold; border-bottom: 0.5pt solid #000; padding-bottom: 2px; }
        .kpis .val { font-size: {{ $isLandscape ? '12px' : '10.5px' }}; font-weight: bold; padding-top: 2px; }
        table.grid { width: 100%; }
        table.grid th, table.grid td { border: 0.6pt solid #000; padding: 2px 3px; vertical-align: top; }
        table.grid thead { display: table-header-group; }
        table.grid th { background: #ececec; font-weight: bold; text-align: left; }
        table.grid tr { page-break-inside: avoid; }
        .idx { width: 14px; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: {{ $isLandscape ? '7.4px' : '6.8px' }}; }
        .foot { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7px; color: #444; text-align: center; }
    </style>
</head>
<body>
    <div class="foot">Shop Directory · {{ $filterSummary }} · printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name }}</div>

    <table class="title">
        <tr><td class="org">SHAHZAIN TRADERS - MUZAFFARABAD</td></tr>
        <tr><td class="name">SHOP DIRECTORY</td></tr>
        <tr><td class="meta">{{ $filterSummary }} · {{ now()->format('d.m.Y') }}</td></tr>
    </table>

    <table class="kpis">
        <tr>
            <td><div class="lbl">SHOPS</div><div class="val">{{ number_format($totals->shops) }}</div></td>
            <td><div class="lbl">OWED TO US (RS)</div><div class="val">{{ number_format((float) $totals->owed) }}</div></td>
            <td><div class="lbl">PAID IN ADVANCE (RS)</div><div class="val">{{ number_format(abs((float) $totals->advance)) }}</div></td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th class="idx">#</th>
                <th>Code</th>
                <th>Shop</th>
                @foreach ($columns as $column)
                    <th class="{{ in_array($column, $numeric, true) ? 'num' : '' }}">{{ $labels[$column][0] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td class="idx">{{ $loop->iteration }}</td>
                    <td class="code">{{ $customer->customer_code }}</td>
                    <td>{{ $customer->customer_name }}</td>
                    @foreach ($columns as $column)
                        <td class="{{ in_array($column, $numeric, true) ? 'num' : '' }} {{ in_array($column, ['owner_cnic', 'ntn'], true) ? 'code' : '' }}">{{ $cell($column, $customer) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 3 }}" style="text-align:center; padding:8px">No shops match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
