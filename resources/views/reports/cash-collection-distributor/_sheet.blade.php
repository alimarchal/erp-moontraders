<main>
    <table class="lay p2-head"><tr>
        <td>
            <div class="p2-title">MOON TRADERS</div>
            <div class="p2-subtitle">Cash Collection Distributor</div>
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
            @foreach ($kpis as $kpi)
                <td style="width: {{ round(100 / count($kpis), 2) }}%" class="{{ ! empty($kpi[2]) ? 'hl' : '' }}"><small>{{ $kpi[0] }}</small><b>{{ $kpi[1] }}</b></td>
            @endforeach
        </tr>
    </table>

    <div class="p2-gap"></div>
    @include('reports.cash-collection-distributor._grid', ['left' => $tables['collection'], 'collapse' => true, 'hideZeroRows' => true])
    <div class="p2-gap"></div>
    @include('reports.cash-collection-distributor._grid', ['left' => $tables['slips'], 'collapse' => true])
    <div class="p2-gap"></div>
    @include('reports.cash-collection-distributor._grid', ['left' => $tables['denominations'], 'collapse' => true, 'emptyText' => 'No cash counted for this day.',
        'hideZeroLabels' => ['Coins', 'Group clearing (other salesmen)', 'Cash not counted', 'Paid out, no note count'],
        'rowTitles' => ['Group clearing (other salesmen)' => 'Cheques cleared in a group; the notes were counted with other salesmen', 'Cash not counted' => 'Cash received that no note count covers (negative: notes counted above the cash received)', 'Paid out, no note count' => 'Cash paid out whose notes were not counted by the approver']]))
    <div class="p2-gap"></div>
    @include('reports.cash-collection-distributor._grid', ['left' => $tables['expenses'], 'collapse' => true])
    <div class="p2-gap"></div>
    @include('reports.cash-collection-distributor._grid', ['left' => $tables['summary']])
    @isset($tables['salesmen'])
        <div class="p2-gap"></div>
        @include('reports.cash-collection-distributor._grid', ['left' => $tables['salesmen'], 'collapse' => true, 'hideZeroRows' => true])
    @endisset

    <p class="p2-foot">Printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name ?? '' }}</p>

    <table class="p2-sign"><tr>
        <td>Prepared By</td>
        <td>Checked By</td>
        <td>Authorized Signature</td>
    </tr></table>
</main>
