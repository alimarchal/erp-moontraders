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
    @include('reports.cash-collection-distributor._grid', ['left' => $tables['cash'], 'right' => $tables['denominations']])
    <div class="p2-gap"></div>
    @include('reports.cash-collection-distributor._grid', ['left' => $tables['bank'], 'right' => $tables['cheques']])
    <div class="p2-gap"></div>
    @include('reports.cash-collection-distributor._grid', ['left' => $tables['slips']])
    <div class="p2-gap"></div>
    @include('reports.cash-collection-distributor._grid', ['left' => $tables['expenses'], 'right' => $tables['summary']])
    @isset($tables['salesmen'])
        <div class="p2-gap"></div>
        @include('reports.cash-collection-distributor._grid', ['left' => $tables['salesmen']])
    @endisset

    <div class="p2-note">Cash is the denomination count where one was entered, otherwise the cash collected on the settlement. Cheque clearing is the cash a salesman brings back on the day a cheque is cleared; it is not part of a settlement and is counted with the notes. Total Collection = cash + bank slips + bank transfers + cheques of the day's settlements. Grand total = cash received (including cheque clearing) + bank slips + bank transfers + cheques; expenses paid by voucher come out of the cash received.</div>

    <p class="p2-foot">Printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name ?? '' }}</p>

    <table class="p2-sign"><tr>
        <td>Prepared By</td>
        <td>Checked By</td>
        <td>Authorized Signature</td>
    </tr></table>
</main>
