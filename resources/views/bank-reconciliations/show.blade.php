@php
    $open = $reconciliation->status === 'in_progress';
    $balanced = abs($totals['difference']) < 0.01;
    $statusTone = ['completed' => 'ak-status-green', 'in_progress' => 'ak-status-amber', 'cancelled' => 'ak-status-slate'][$reconciliation->status] ?? 'ak-status-slate';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-bank.head :title="'Reconciliation: '.$reconciliation->bankAccount->account_name"
            :sub="$reconciliation->period_from->format('d-m-Y').' → '.$reconciliation->period_to->format('d-m-Y')"
            :crumbs="['Reconciliation' => route('bank-reconciliations.index'), $reconciliation->bankAccount->account_name => null]">
            @can('report-bank-reconciliation-statement')
                <a href="{{ route('reports.bank.show', ['report' => 'reconciliation-statement', 'filter' => ['reconciliation_id' => $reconciliation->id]]) }}" class="ak-btn ak-btn-outline">Statement report</a>
            @endcan
            <a href="{{ route('bank-reconciliations.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page">
        <x-bank.subnav inside />
        <x-bank.print-title title="Bank Reconciliation" :subtitle="$reconciliation->bankAccount->account_name.' · '.$reconciliation->period_from->format('d-m-Y').' to '.$reconciliation->period_to->format('d-m-Y')" />
        <x-status-message />

        <div class="bk-row">
            <span class="ak-status {{ $statusTone }}"><i aria-hidden="true"></i>{{ ucfirst(str_replace('_', ' ', $reconciliation->status)) }}</span>
            @if ($reconciliation->isLocked())<span class="ak-muted">Locked by {{ $reconciliation->completer?->name }} on {{ $reconciliation->completed_at?->format('d-m-Y H:i') }}</span>@endif
        </div>

        {{-- Difference meter --}}
        <section class="ak-kpis" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr))" aria-label="Reconciliation figures">
            @foreach ([
                ['Statement balance', $reconciliation->statement_balance, ''],
                ['+ Deposits in transit', $totals['deposits_in_transit'], 'bk-in'],
                ['− Outstanding payments', $totals['outstanding_payments'], 'bk-out'],
                ['Book balance (ledger)', $totals['book_balance'], ''],
            ] as [$label, $value, $color])
                <div class="ak-kpi"><span class="ak-kpi-body"><span class="ak-kpi-label">{{ $label }}</span><span class="ak-kpi-value ak-kpi-value-sm {{ $color }}"><x-bank.money :value="$value" /></span></span></div>
            @endforeach
            <div class="ak-kpi {{ $balanced ? '' : 'ak-kpi-action' }}"><span class="ak-kpi-body"><span class="ak-kpi-label">Difference</span><span class="ak-kpi-value ak-kpi-value-sm"><x-bank.money :value="$totals['difference']" /></span><span class="ak-kpi-hint"><b>{{ $balanced ? 'Balanced' : 'Not balanced yet' }}</b></span></span></div>
        </section>

        @if ($open)
            <div class="bk-row bank-noprint">
                @can('bank-reconciliation-edit')
                    <form method="POST" action="{{ route('bank-reconciliations.auto-match', $reconciliation) }}">@csrf
                        <button class="ak-btn ak-btn-outline">Auto-match posted lines</button>
                    </form>
                @endcan
                @can('bank-reconciliation-complete')
                    <form method="POST" action="{{ route('bank-reconciliations.complete', $reconciliation) }}" onsubmit="return confirm('Complete and lock this period?')">@csrf
                        <button class="ak-btn ak-btn-success" @disabled(! $balanced || $totals['unmatched_entries']->isNotEmpty())>Complete &amp; lock period</button>
                    </form>
                @endcan
                @can('bank-reconciliation-cancel')
                    <form method="POST" action="{{ route('bank-reconciliations.cancel', $reconciliation) }}" onsubmit="return confirm('Cancel this reconciliation?')" style="margin-left:auto">@csrf
                        <button class="ak-btn ak-btn-danger-outline">Cancel reconciliation</button>
                    </form>
                @endcan
            </div>
        @elseif ($reconciliation->status === 'completed')
            @can('bank-reconciliation-cancel')
                <form method="POST" action="{{ route('bank-reconciliations.cancel', $reconciliation) }}" onsubmit="return confirm('Undo this reconciliation? The period will be unlocked.')" class="bank-noprint">@csrf
                    <button class="ak-btn ak-btn-danger-outline">Undo reconciliation (unlock period)</button>
                </form>
            @endcan
        @endif

        <div style="display:grid; gap:20px; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); align-items:start">
            {{-- Left: statement lines still to match --}}
            <section class="ak-card">
                <div class="ak-dt-toolbar"><p><b>Statement lines not matched ({{ $totals['unmatched_entries']->count() }})</b></p></div>
                @forelse ($totals['unmatched_entries'] as $entry)
                    <div class="bk-file" style="padding:10px 16px; align-items:flex-start; flex-direction:column; gap:6px">
                        <div style="display:flex; justify-content:space-between; gap:12px; width:100%">
                            <div style="min-width:0">
                                <a href="{{ route('bank-statement-entries.show', $entry) }}" data-entry="{{ route('bank-statement-entries.drawer', $entry) }}" class="ak-primary-link" dir="auto">{{ $entry->party_name_in_statement ?? $entry->reference }}</a>
                                <div class="ak-muted">{{ $entry->posting_date->format('d-m-Y') }} · {{ $entry->reference }} · <x-bank.status-badge :status="$entry->status" /></div>
                            </div>
                            <b class="bk-num {{ $entry->direction === 'credit' ? 'bk-in' : 'bk-out' }}">{{ $entry->direction === 'credit' ? '+' : '−' }}{{ number_format($entry->amount, 2) }}</b>
                        </div>
                        @if ($open)
                            @can('bank-reconciliation-edit')
                                @forelse ($suggestions[$entry->id] ?? [] as $detail)
                                    <form method="POST" action="{{ route('bank-reconciliations.match', $reconciliation) }}" class="bk-box bank-noprint" style="width:100%; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:6px 10px">@csrf
                                        <input type="hidden" name="bank_statement_entry_id" value="{{ $entry->id }}">
                                        <input type="hidden" name="journal_entry_detail_id" value="{{ $detail->id }}">
                                        <span class="ak-muted">Suggested: JE #{{ $detail->journal_entry_id }} · {{ $detail->journalEntry->entry_date->format('d-m-Y') }} · {{ $detail->journalEntry->reference }}</span>
                                        <button class="ak-btn ak-btn-outline ak-btn-sm">Match</button>
                                    </form>
                                @empty
                                    <p class="ak-muted bank-noprint" style="margin:0">No matching book line. Post the entry from the register, or pick one on the right.</p>
                                @endforelse
                            @endcan
                        @endif
                    </div>
                @empty
                    <div class="ak-empty"><h2>Every statement line in the period is matched</h2></div>
                @endforelse
            </section>

            {{-- Right: book lines still to match --}}
            <section class="ak-card">
                <div class="ak-dt-toolbar"><p><b>Book lines not on the statement ({{ $totals['unmatched_book_lines']->count() }})</b></p></div>
                @forelse ($totals['unmatched_book_lines'] as $detail)
                    <div class="bk-file" style="padding:10px 16px">
                        <div style="min-width:0">
                            <a href="{{ route('journal-entries.show', $detail->journalEntry) }}" class="ak-primary-link">JE #{{ $detail->journal_entry_id }} · {{ $detail->journalEntry->reference }}</a>
                            <div class="ak-muted">{{ $detail->journalEntry->entry_date->format('d-m-Y') }} · {{ $detail->description }}</div>
                        </div>
                        @if ((float) $detail->debit > 0)
                            <b class="bk-num bk-in" title="Deposit in transit">+{{ number_format($detail->debit, 2) }}</b>
                        @else
                            <b class="bk-num bk-out" title="Outstanding payment">−{{ number_format($detail->credit, 2) }}</b>
                        @endif
                    </div>
                @empty
                    <div class="ak-empty"><h2>Nothing outstanding in the books</h2></div>
                @endforelse
            </section>
        </div>

        {{-- Matched --}}
        <section class="ak-card">
            <div class="ak-dt-toolbar"><p><b>Matched ({{ $reconciliation->items->count() }})</b></p></div>
            @if ($reconciliation->items->count())
                <div class="ak-dt-scroll" tabindex="0">
                    <table class="ak-dt">
                        <caption class="sr-only">Matched lines</caption>
                        <thead><tr><th scope="col">Date</th><th scope="col">Party</th><th scope="col">Receipt / journal</th><th scope="col">Matched by</th><th scope="col" class="ak-num">Amount</th><th scope="col" class="ak-c bank-noprint"></th></tr></thead>
                        <tbody>
                            @foreach ($reconciliation->items as $item)
                                <tr>
                                    <td class="ak-mono">{{ $item->entry->posting_date->format('d-m-Y') }}</td>
                                    <td dir="auto">{{ $item->entry->party_name_in_statement ?? $item->entry->reference }}</td>
                                    <td class="ak-muted">{{ $item->entry->receipt_no }} @if ($item->journalEntryDetail)· JE #{{ $item->journalEntryDetail->journal_entry_id }}@endif</td>
                                    <td><span class="ak-status ak-status-slate">{{ $item->match_type }}</span></td>
                                    <td class="ak-num {{ $item->entry->direction === 'credit' ? 'bk-in' : 'bk-out' }}">{{ $item->entry->direction === 'credit' ? '+' : '−' }}{{ number_format($item->entry->amount, 2) }}</td>
                                    <td class="ak-c bank-noprint">
                                        @if ($open)
                                            @can('bank-reconciliation-edit')
                                                <form method="POST" action="{{ route('bank-reconciliations.unmatch', [$reconciliation, $item]) }}">@csrf @method('DELETE')<button class="ak-btn ak-btn-danger-outline ak-btn-sm">Unmatch</button></form>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="ak-empty"><h2>Nothing matched yet</h2></div>
            @endif
        </section>
    </div>
</x-app-layout>
