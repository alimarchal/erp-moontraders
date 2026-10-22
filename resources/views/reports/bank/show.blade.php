@php
    $uses = fn (string $field) => in_array($field, $definition['filters'], true);
    $exportQuery = ['filter' => $filters];
    $isNumeric = fn (int $i) => in_array($i, $data['numeric'], true);
    $format = fn ($cell, int $i) => is_float($cell) || (is_int($cell) && $isNumeric($i) && $i > 1) ? number_format($cell, 2) : $cell;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-bank.head :title="$data['title']" :sub="$data['subtitle']" :crumbs="['Reports' => route('reports.bank.index'), $data['title'] => null]">
            <a href="{{ route('reports.bank.export.excel', ['report' => $slug] + $exportQuery) }}" class="ak-btn ak-btn-outline">Excel</a>
            <a href="{{ route('reports.bank.export.pdf', ['report' => $slug] + $exportQuery) }}" class="ak-btn ak-btn-outline">PDF</a>
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
            <a href="{{ route('reports.bank.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> All reports</a>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page">
        <x-bank.subnav inside />
        <x-bank.print-title :title="$data['title']" :subtitle="$data['subtitle']" />

        <form method="GET" action="{{ route('reports.bank.show', $slug) }}" class="ak-card ak-filters bank-noprint" style="padding:16px">
            <div class="ak-filter-row" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); align-items:end">
                @if ($uses('type'))
                    <div class="ak-field"><label for="r_type">Money</label>
                        <select data-allow-clear="false" id="r_type" name="filter[type]" class="select2 bank-select">
                            <option value="credit" @selected(($filters['type'] ?? 'credit') === 'credit')>Received (credit)</option>
                            <option value="debit" @selected(($filters['type'] ?? '') === 'debit')>Paid (debit)</option>
                        </select></div>
                @endif
                @if ($uses('bank_account_id'))
                    <div class="ak-field"><label for="r_bank">Bank account</label>
                        <select data-placeholder="All" id="r_bank" name="filter[bank_account_id]" class="select2 bank-select">
                            <option value="">All</option>
                            @foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected((string) ($filters['bank_account_id'] ?? '') === (string) $account->id)>{{ $account->account_name }}</option>@endforeach
                        </select></div>
                @endif
                @if ($uses('company_id'))
                    <div class="ak-field"><label for="r_company">Company / principal</label>
                        <select data-placeholder="Choose…" id="r_company" name="filter[company_id]" class="select2 bank-select">
                            <option value="">Choose…</option>
                            @foreach ($companies as $company)<option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->supplier_name }}</option>@endforeach
                        </select></div>
                @endif
                @if ($uses('reconciliation_id'))
                    <div class="ak-field"><label for="r_rec">Reconciliation</label>
                        <select data-placeholder="Choose…" id="r_rec" name="filter[reconciliation_id]" class="select2 bank-select">
                            <option value="">Choose…</option>
                            @foreach ($reconciliations as $reconciliation)<option value="{{ $reconciliation->id }}" @selected((string) ($filters['reconciliation_id'] ?? '') === (string) $reconciliation->id)>{{ $reconciliation->bankAccount->account_name }} · {{ $reconciliation->period_from->format('d-m-Y') }} → {{ $reconciliation->period_to->format('d-m-Y') }}</option>@endforeach
                        </select></div>
                @endif
                @if ($uses('opening_balance'))
                    <div class="ak-field"><label for="r_open">Opening balance</label><input id="r_open" name="filter[opening_balance]" type="number" step="0.01" value="{{ $filters['opening_balance'] ?? '' }}" placeholder="0.00"></div>
                @endif
                @if ($uses('date_from'))
                    <div class="ak-field"><label for="r_from">From</label><input id="r_from" name="filter[date_from]" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
                    <div class="ak-field"><label for="r_to">To</label><input id="r_to" name="filter[date_to]" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
                @endif
                <div class="ak-filter-buttons"><button class="ak-btn ak-btn-primary">Show</button></div>
            </div>
        </form>

        <section class="ak-card">
            <div class="ak-dt-scroll" tabindex="0">
                <table class="ak-dt">
                    <caption class="sr-only">{{ $data['title'] }}</caption>
                    <thead>
                        <tr>@foreach ($data['headings'] as $i => $heading)<th scope="col" class="{{ $isNumeric($i) ? 'ak-num' : '' }}">{{ $heading }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @forelse ($data['rows'] as $row)
                            <tr>
                                @foreach ($row as $i => $cell)
                                    <td class="{{ $isNumeric($i) ? 'ak-num' : '' }}" @unless($isNumeric($i)) dir="auto" @endunless>{{ $format($cell, $i) }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($data['headings']) }}" class="ak-c ak-muted" style="padding:32px">No data for these filters.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($data['totals'] && count($data['rows']))
                        <tfoot>
                            <tr>@foreach ($data['totals'] as $i => $cell)<td class="{{ $isNumeric($i) ? 'ak-num' : '' }}">{{ $format($cell, $i) }}</td>@endforeach</tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @foreach ($data['notes'] as $note)
                <p class="ak-muted" style="margin:0; padding:10px 16px; border-top:1px solid var(--ak-line)">{{ $note }}</p>
            @endforeach
        </section>
    </div>
</x-app-layout>
