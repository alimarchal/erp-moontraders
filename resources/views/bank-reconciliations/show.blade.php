@php
    $open = $reconciliation->status === 'in_progress';
    $balanced = abs($totals['difference']) < 0.01;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reconciliation — {{ $reconciliation->bankAccount->account_name }}</h2>
            <div class="flex gap-2">
                @can('report-bank-reconciliation-statement')
                    <a href="{{ route('reports.bank.show', ['report' => 'reconciliation-statement', 'filter' => ['reconciliation_id' => $reconciliation->id]]) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">Print statement</a>
                @endcan
                <a href="{{ route('bank-reconciliations.index') }}" class="rounded-md bg-blue-950 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
    <x-bank.print-styles />
    <x-bank.subnav inside />
    <x-bank.print-title title="Bank Reconciliation" />
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-status-message />

            <div class="flex items-center gap-3">
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ ['completed' => 'bg-emerald-100 text-emerald-700', 'in_progress' => 'bg-amber-100 text-amber-800', 'cancelled' => 'bg-gray-200 text-gray-600'][$reconciliation->status] }}">{{ ucfirst(str_replace('_', ' ', $reconciliation->status)) }}</span>
                <span class="text-sm text-gray-600">{{ $reconciliation->period_from->format('d-m-Y') }} → {{ $reconciliation->period_to->format('d-m-Y') }}</span>
                @if ($reconciliation->isLocked())<span class="text-sm text-emerald-700">🔒 locked by {{ $reconciliation->completer?->name }} on {{ $reconciliation->completed_at?->format('d-m-Y H:i') }}</span>@endif
            </div>

            {{-- Difference meter --}}
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                @foreach ([
                    ['Statement balance', $reconciliation->statement_balance, 'text-gray-900'],
                    ['+ Deposits in transit', $totals['deposits_in_transit'], 'text-emerald-700'],
                    ['− Outstanding payments', $totals['outstanding_payments'], 'text-red-700'],
                    ['Book balance (ledger)', $totals['book_balance'], 'text-gray-900'],
                ] as [$label, $value, $color])
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="text-lg font-bold {{ $color }}"><x-bank.money :value="$value" /></p>
                    </div>
                @endforeach
                <div class="rounded-lg shadow p-4 {{ $balanced ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' }}">
                    <p class="text-xs uppercase tracking-wide opacity-80">Difference</p>
                    <p class="text-lg font-bold"><x-bank.money :value="$totals['difference']" /></p>
                    <p class="text-xs opacity-90">{{ $balanced ? 'Balanced' : 'Not balanced yet' }}</p>
                </div>
            </div>

            @if ($open)
                <div class="flex flex-wrap items-center gap-2">
                    @can('bank-reconciliation-edit')
                        <form method="POST" action="{{ route('bank-reconciliations.auto-match', $reconciliation) }}">@csrf
                            <button class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-blue-900 hover:bg-blue-50">⚡ Auto-match posted lines</button>
                        </form>
                    @endcan
                    @can('bank-reconciliation-complete')
                        <form method="POST" action="{{ route('bank-reconciliations.complete', $reconciliation) }}" onsubmit="return confirm('Complete and lock this period?')">@csrf
                            <button class="rounded-md bg-green-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900 disabled:opacity-40" @disabled(! $balanced || $totals['unmatched_entries']->isNotEmpty())>Complete &amp; lock period</button>
                        </form>
                    @endcan
                    @can('bank-reconciliation-cancel')
                        <form method="POST" action="{{ route('bank-reconciliations.cancel', $reconciliation) }}" onsubmit="return confirm('Cancel this reconciliation?')" class="ms-auto">@csrf
                            <button class="text-sm text-red-600 hover:underline">Cancel reconciliation</button>
                        </form>
                    @endcan
                </div>
            @elseif ($reconciliation->status === 'completed')
                @can('bank-reconciliation-cancel')
                    <form method="POST" action="{{ route('bank-reconciliations.cancel', $reconciliation) }}" onsubmit="return confirm('Undo this reconciliation? The period will be unlocked.')">@csrf
                        <button class="text-sm text-red-600 hover:underline">Undo reconciliation (unlock period)</button>
                    </form>
                @endcan
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Left: statement lines still to match --}}
                <div class="bg-white shadow-xl sm:rounded-lg overflow-hidden">
                    <p class="font-semibold bg-gray-50 border-b px-4 py-2">Statement lines not matched ({{ $totals['unmatched_entries']->count() }})</p>
                    @forelse ($totals['unmatched_entries'] as $entry)
                        <div class="border-b px-4 py-2 text-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('bank-statement-entries.show', $entry) }}" class="font-semibold text-blue-800 hover:underline truncate block" dir="auto">{{ $entry->party_name_in_statement ?? $entry->reference }}</a>
                                    <p class="text-xs text-gray-500">{{ $entry->posting_date->format('d-m-Y') }} · {{ $entry->reference }} · <x-bank.status-badge :status="$entry->status" /></p>
                                </div>
                                <span class="font-semibold tabular-nums {{ $entry->direction === 'credit' ? 'text-emerald-700' : 'text-red-700' }}">{{ $entry->direction === 'credit' ? '+' : '−' }}{{ number_format($entry->amount, 2) }}</span>
                            </div>
                            @if ($open)
                                @can('bank-reconciliation-edit')
                                    @forelse ($suggestions[$entry->id] ?? [] as $detail)
                                        <form method="POST" action="{{ route('bank-reconciliations.match', $reconciliation) }}" class="mt-1 flex items-center justify-between gap-2 rounded bg-blue-50 px-2 py-1">@csrf
                                            <input type="hidden" name="bank_statement_entry_id" value="{{ $entry->id }}">
                                            <input type="hidden" name="journal_entry_detail_id" value="{{ $detail->id }}">
                                            <span class="text-xs text-blue-900">Suggested: JE #{{ $detail->journal_entry_id }} · {{ $detail->journalEntry->entry_date->format('d-m-Y') }} · {{ $detail->journalEntry->reference }}</span>
                                            <button class="text-xs font-semibold text-blue-800 hover:underline">Match</button>
                                        </form>
                                    @empty
                                        <p class="text-xs text-gray-400 mt-1">No matching book line. Post the entry from the register, or pick one on the right.</p>
                                    @endforelse
                                @endcan
                            @endif
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-emerald-700">Every statement line in the period is matched. ✓</p>
                    @endforelse
                </div>

                {{-- Right: book lines still to match --}}
                <div class="bg-white shadow-xl sm:rounded-lg overflow-hidden">
                    <p class="font-semibold bg-gray-50 border-b px-4 py-2">Book lines not on the statement ({{ $totals['unmatched_book_lines']->count() }})</p>
                    @forelse ($totals['unmatched_book_lines'] as $detail)
                        <div class="border-b px-4 py-2 text-sm flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('journal-entries.show', $detail->journalEntry) }}" class="font-semibold text-blue-800 hover:underline">JE #{{ $detail->journal_entry_id }} · {{ $detail->journalEntry->reference }}</a>
                                <p class="text-xs text-gray-500">{{ $detail->journalEntry->entry_date->format('d-m-Y') }} · {{ $detail->description }}</p>
                            </div>
                            @if ((float) $detail->debit > 0)
                                <span class="font-semibold tabular-nums text-emerald-700" title="Deposit in transit">+{{ number_format($detail->debit, 2) }}</span>
                            @else
                                <span class="font-semibold tabular-nums text-red-700" title="Outstanding payment">−{{ number_format($detail->credit, 2) }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-emerald-700">Nothing outstanding in the books. ✓</p>
                    @endforelse
                </div>
            </div>

            {{-- Matched --}}
            <div class="bg-white shadow-xl sm:rounded-lg overflow-hidden">
                <p class="font-semibold bg-gray-50 border-b px-4 py-2">Matched ({{ $reconciliation->items->count() }})</p>
                <div class="max-h-96 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <tbody>
                            @foreach ($reconciliation->items as $item)
                                <tr class="border-b">
                                    <td class="px-4 py-1.5 whitespace-nowrap">{{ $item->entry->posting_date->format('d-m-Y') }}</td>
                                    <td class="px-2 py-1.5" dir="auto">{{ $item->entry->party_name_in_statement ?? $item->entry->reference }}</td>
                                    <td class="px-2 py-1.5 text-xs text-gray-500">{{ $item->entry->receipt_no }} @if ($item->journalEntryDetail)· JE #{{ $item->journalEntryDetail->journal_entry_id }}@endif</td>
                                    <td class="px-2 py-1.5 text-xs"><span class="rounded bg-gray-100 px-1.5 py-0.5">{{ $item->match_type }}</span></td>
                                    <td class="px-2 py-1.5 text-right tabular-nums {{ $item->entry->direction === 'credit' ? 'text-emerald-700' : 'text-red-700' }}">{{ $item->entry->direction === 'credit' ? '+' : '−' }}{{ number_format($item->entry->amount, 2) }}</td>
                                    <td class="px-4 py-1.5 text-right">
                                        @if ($open)
                                            @can('bank-reconciliation-edit')
                                                <form method="POST" action="{{ route('bank-reconciliations.unmatch', [$reconciliation, $item]) }}">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Unmatch</button></form>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
