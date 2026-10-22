@php($preview = $import->preview ?? [])
<x-app-layout>
    <x-slot name="header">
        <x-bank.head :title="'Import summary: '.$import->file_name" :sub="$import->status === 'imported' ? 'Step 3 of 3: imported.' : 'Step 2 of 3: check the summary, then confirm.'"
            :crumbs="['Statement imports' => route('bank-statement-imports.index'), $import->file_name => null]">
            <a href="{{ route('bank-statement-imports.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page">
        <x-bank.subnav inside />
        <x-bank.print-title title="Statement Import" :subtitle="$import->file_name" />
        <x-status-message />

        <section class="ak-kpis" aria-label="Import figures">
            @foreach ([
                ['Rows read', $preview['valid_rows'] ?? 0, ''],
                ['New rows', $preview['new_rows'] ?? 0, 'bk-in'],
                ['Already in system', $preview['duplicate_rows'] ?? 0, ''],
                ['Rows with problems', $preview['error_rows'] ?? 0, ($preview['error_rows'] ?? 0) > 0 ? 'bk-out' : ''],
            ] as [$label, $value, $color])
                <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ $label }}</span><span class="ak-kpi-value {{ $color }}">{{ number_format($value) }}</span></span></div>
            @endforeach
        </section>

        <section class="ak-card" style="padding:18px 20px">
            <dl class="bk-dl" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
                <div><dt>Bank account</dt><dd>{{ $import->bankAccount->account_name }}</dd></div>
                <div><dt>Dates in file</dt><dd>{{ $preview['date_from'] ?? '—' }} → {{ $preview['date_to'] ?? '—' }}</dd></div>
                <div><dt>Need review (unidentified)</dt><dd>{{ number_format($preview['needs_review_rows'] ?? 0) }}</dd></div>
                <div><dt>Total debits in file</dt><dd><x-bank.money :value="$preview['total_debit'] ?? 0" /></dd></div>
                <div><dt>Total credits in file</dt><dd><x-bank.money :value="$preview['total_credit'] ?? 0" /></dd></div>
            </dl>
        </section>

        <section class="ak-card" style="padding:18px 20px">
            <h2 class="uf-card-title" style="margin-bottom:8px">Checks against the printed statement</h2>
            @forelse ($preview['checks'] ?? [] as $check)
                <div class="bk-file">
                    <span>{{ $check['label'] }}</span>
                    <span class="bk-num">expected <b>{{ number_format($check['expected'], 2) }}</b> · file <b>{{ number_format($check['actual'], 2) }}</b>
                        <span class="ak-status {{ $check['ok'] ? 'ak-status-green' : 'ak-status-red' }}" style="margin-left:8px"><i aria-hidden="true"></i>{{ $check['ok'] ? 'Tallies' : 'Does not tally' }}</span></span>
                </div>
            @empty
                <p class="ak-muted">No control figures were entered, so nothing was compared. You can still import.</p>
            @endforelse
        </section>

        @if (! empty($preview['errors']))
            <div class="ak-alert ak-alert-error" role="alert">
                <b>Rows that will be skipped</b>
                <ul style="margin:6px 0 0; padding-left:18px">@foreach ($preview['errors'] as $error)<li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="ak-card">
            <div class="ak-dt-toolbar"><p><b>First rows</b></p></div>
            <div class="ak-dt-scroll" tabindex="0">
                <table class="ak-dt">
                    <caption class="sr-only">First rows of the file</caption>
                    <thead><tr><th scope="col">Row</th><th scope="col">Date</th><th scope="col">Party</th><th scope="col">Reference</th><th scope="col" class="ak-num">Debit</th><th scope="col" class="ak-num">Credit</th></tr></thead>
                    <tbody>
                        @foreach ($preview['sample'] ?? [] as $row)
                            <tr>
                                <td>{{ $row['row'] }}</td><td class="ak-mono">{{ $row['posting_date'] }}</td><td dir="auto">{{ $row['party'] }}@if ($row['needs_review']) <b class="ak-muted">· review</b>@endif</td><td>{{ $row['reference'] }}</td>
                                <td class="ak-num"><x-bank.money :value="$row['debit']" /></td><td class="ak-num"><x-bank.money :value="$row['credit']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($import->status === 'previewed')
            <div class="bk-row bank-noprint" style="justify-content:space-between">
                <form method="POST" action="{{ route('bank-statement-imports.destroy', $import) }}" onsubmit="return confirm('Discard this import?')">@csrf @method('DELETE')<button class="ak-btn ak-btn-danger-outline">Discard</button></form>
                <form method="POST" action="{{ route('bank-statement-imports.confirm', $import) }}" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <button class="ak-btn ak-btn-success" x-bind:disabled="busy" @disabled(! ($preview['checks_ok'] ?? false))>Confirm import of {{ number_format($preview['new_rows'] ?? 0) }} new rows</button>
                </form>
            </div>
            @unless ($preview['checks_ok'] ?? false)
                <p class="ak-muted bank-noprint" style="text-align:right; color:#991b1b">The figures do not tally, so the import is blocked. Correct the figures or the file and upload again.</p>
            @endunless
        @else
            <a href="{{ route('bank-statement-entries.index', ['filter' => ['import_id' => $import->id]]) }}" class="ak-btn ak-btn-outline bank-noprint" style="align-self:flex-start">View the {{ number_format($import->imported_rows) }} imported entries →</a>
        @endif
    </div>
</x-app-layout>
