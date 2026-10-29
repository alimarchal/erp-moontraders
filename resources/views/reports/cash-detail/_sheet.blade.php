@php
    $money = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
    $cell = fn ($value) => $value > 0 ? number_format((float) $value, 2) : '-';
    $landscape = ($orientation ?? 'portrait') === 'landscape';
    $grandTotal = array_sum($totals);
    $denominationRows = collect([5000, 1000, 500, 100, 50, 20, 10])->map(fn ($note) => [
        'label' => number_format($note),
        'qty' => (int) ($denominations[$note] ?? 0),
        'value' => (int) ($denominations[$note] ?? 0) * $note,
    ]);
    $coins = (float) ($denominations['coins'] ?? 0);
    $cashByDenomination = $denominationRows->sum('value') + $coins;
    $sources = [
        ['Salesman Cash', $salesmanData, 'No cash collected for the selected filters.', 'Cash handed in by each salesman'],
        ['Bank Slips', $bankSlipsData, 'No bank slips for the selected filters.', 'Cash deposited in the bank, per salesman'],
        ['Bank Transfers', $bankTransfersData, 'No bank transfers for the selected filters.', 'Customer payments received by bank transfer, per salesman'],
        ['Cheques', $chequesData, 'No cheques for the selected filters.', 'Cheques received from customers, per salesman'],
    ];
    $sectionNo = 0;
@endphp
<main>
        <table class="lay p2-head"><tr>
            <td>
                <div class="p2-title">MOON TRADERS</div>
                <div class="p2-subtitle">Cash Detail Report</div>
            </td>
            <td class="p2-no">{{ \Carbon\Carbon::parse($date)->format('d-M-Y') }}<div class="p2-subtitle">{{ \Carbon\Carbon::parse($date)->format('l') }}</div></td>
        </tr></table>

        <table class="p2-meta">
            <tr>
                <td><b>Company:</b> {{ $companyLabel }}</td>
                <td><b>Designation:</b> {{ $designation ?: 'All designations' }}</td>
                <td><b>Salesmen:</b> {{ empty($employeeIds) ? 'All' : $allEmployees->whereIn('id', $employeeIds)->pluck('name')->implode(', ') }}</td>
            </tr>
        </table>

        <table class="p2-kpis">
            <tr>
                <td><small>Salesman cash (notes + coins handed in)</small><b>{{ $money($totals['totalCash']) }}</b></td>
                <td><small>Bank slips (deposited in bank)</small><b>{{ $money($totals['totalBankSlips']) }}</b></td>
                <td><small>Bank transfers (online / IBFT)</small><b>{{ $money($totals['totalBankTransfers']) }}</b></td>
                <td><small>Cheques received</small><b>{{ $money($totals['totalCheques']) }}</b></td>
                <td class="hl"><small>Overall total (cash + slips + transfers + cheques)</small><b>{{ $money($grandTotal) }}</b></td>
            </tr>
        </table>

        <div class="p2-gap"></div>
        @include('reports.cash-detail._pair-table', ['pair' => [['no' => 1, 'heading' => $sources[0][0], 'rows' => $sources[0][1], 'emptyText' => $sources[0][2], 'explain' => $sources[0][3]], ['no' => 2, 'heading' => $sources[1][0], 'rows' => $sources[1][1], 'emptyText' => $sources[1][2], 'explain' => $sources[1][3]]]])
        <div class="p2-gap"></div>
        @include('reports.cash-detail._pair-table', ['pair' => [['no' => 3, 'heading' => $sources[2][0], 'rows' => $sources[2][1], 'emptyText' => $sources[2][2], 'explain' => $sources[2][3]], ['no' => 4, 'heading' => $sources[3][0], 'rows' => $sources[3][1], 'emptyText' => $sources[3][2], 'explain' => $sources[3][3]]]])
        <div class="p2-gap"></div>
        @include('reports.cash-detail._denominations-totals')

        <div class="p2-note">Amounts cover every settlement of the chosen date for the selected company, designation and salesmen. Cash uses the denomination count where one was entered, otherwise the cash collected on the settlement.</div>

        <p class="p2-foot">Printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name ?? '' }}</p>

        <table class="p2-sign"><tr>
            <td>Prepared By</td>
            <td>Checked By</td>
            <td>Authorized Signature</td>
        </tr></table>
    </main>
