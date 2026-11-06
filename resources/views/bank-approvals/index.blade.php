<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Approvals" sub="Entries waiting for a checker or a senior approver. Open the receipt before you approve." :crumbs="['Approvals' => null]">
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16" x-data="{ ids: [] }">
        <x-bank.subnav inside />
        <x-bank.print-title title="Pending Approvals" :subtitle="$entries->total().' entries waiting'" />
        <x-status-message />
        @if ($errors->any())<div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>@endif

        <div class="ak-alert ak-alert-warn bank-noprint" role="note" style="background:#eff6ff; border-color:#bfdbfe; color:#1e3a8a">
            <b>Approval limits:</b>
            @if ($thresholds['enabled'])
                from PKR {{ number_format($thresholds['level1_threshold']) }} a checker must approve;
                from PKR {{ number_format($thresholds['level2_threshold']) }} a senior approver must approve as well.
                @unless ($thresholds['allow_self_approval']) You cannot approve an entry you submitted yourself. @endunless
            @else
                Maker-checker is switched off: entries are posted by the maker.
            @endif
        </div>

        <form method="POST" action="{{ route('bank-approvals.bulk-approve') }}">
            @csrf
            <section class="ak-card" aria-label="Pending approvals">
                <div class="ak-dt-toolbar">
                    <p><b>{{ number_format($entries->total()) }}</b> {{ \Illuminate\Support\Str::plural('entry', $entries->total()) }} waiting</p>
                    @can('bank-statement-entry-approve')
                        <button x-show="ids.length" x-cloak class="ak-btn ak-btn-success ak-btn-sm bank-noprint" onclick="return confirm('Approve the ticked entries?')">Approve <span x-text="ids.length"></span> selected</button>
                    @endcan
                </div>

                @if ($entries->count())
                    <div class="ak-dt-scroll" tabindex="0" aria-label="Approvals table">
                        <table class="ak-dt">
                            <caption class="sr-only">Entries waiting for approval</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c bank-noprint"></th>
                                    <th scope="col">Entry</th>
                                    <th scope="col" class="ak-num">Amount (PKR)</th>
                                    <th scope="col">Receipt</th>
                                    <th scope="col">Approvals</th>
                                    <th scope="col" class="bank-noprint">Decision</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $entry)
                                    @php($approved = $approvedCounts[$entry->id])
                                    @php($actionable = $canAct($entry))
                                    <tr>
                                        <td class="ak-c bank-noprint" data-label="">
                                            @if ($actionable)<input type="checkbox" name="ids[]" value="{{ $entry->id }}" x-model="ids" aria-label="Select entry {{ $entry->id }}">@endif
                                        </td>
                                        <td data-label="Entry">
                                            <a href="{{ route('bank-statement-entries.show', $entry) }}" data-entry="{{ route('bank-statement-entries.drawer', $entry) }}" class="ak-primary-link" dir="auto">{{ $entry->company?->supplier_name ?? $entry->party_name_in_statement }}</a>
                                            <div class="ak-muted">{{ $entry->posting_date->format('d-m-Y') }} · {{ $entry->reference }} · {{ $entry->category?->name }}</div>
                                            <div class="ak-muted">{{ $entry->bankAccount->account_name }} · submitted by <b>{{ $entry->submitter?->name }}</b> {{ $entry->submitted_at?->diffForHumans() }}</div>
                                        </td>
                                        <td class="ak-num" data-label="Amount">
                                            <b class="{{ $entry->direction === 'credit' ? 'bk-in' : 'bk-out' }}">{{ $entry->direction === 'credit' ? '+' : '−' }} {{ number_format($entry->amount, 2) }}</b>
                                            <div class="ak-muted">{{ $entry->direction === 'credit' ? 'Received' : 'Paid out' }}</div>
                                        </td>
                                        <td data-label="Receipt">
                                            @forelse ($entry->attachments as $attachment)
                                                <div><a href="{{ route('bank-attachments.download', ['attachment' => $attachment, 'inline' => 1]) }}" target="_blank" rel="noopener" class="ak-primary-link">
                                                    @if (str_starts_with((string) $attachment->file_type, 'image/'))<img src="{{ route('bank-attachments.download', ['attachment' => $attachment, 'inline' => 1]) }}" alt="" style="height:28px; width:28px; object-fit:cover; border-radius:4px; vertical-align:middle; margin-right:4px">@endif
                                                    {{ \Illuminate\Support\Str::limit($attachment->file_name, 26) }}</a></div>
                                            @empty
                                                <span class="ak-status ak-status-amber"><i aria-hidden="true"></i>No receipt</span>
                                            @endforelse
                                        </td>
                                        <td data-label="Approvals">
                                            <div><span class="ak-status {{ $approved->count() >= 1 ? 'ak-status-green' : 'ak-status-slate' }}"><i aria-hidden="true"></i>Checker {{ $approved->count() >= 1 ? '✓ '.$approved->first()->user?->name : 'pending' }}</span></div>
                                            @if ($entry->approval_level_required === 2)
                                                <div style="margin-top:4px"><span class="ak-status {{ $approved->count() >= 2 ? 'ak-status-green' : 'ak-status-slate' }}"><i aria-hidden="true"></i>Senior {{ $approved->count() >= 2 ? '✓' : 'pending' }}</span></div>
                                            @endif
                                        </td>
                                        <td class="bank-noprint" data-label="Decision" style="min-width:14rem">
                                            @if ($actionable)
                                                @can('bank-statement-entry-approve')
                                                    <button type="submit" formaction="{{ route('bank-approvals.approve', $entry) }}" class="ak-btn ak-btn-success ak-btn-sm" style="width:100%">Approve{{ $entry->approval_level_required === 2 && $approved->count() >= 1 ? ' (senior)' : '' }}</button>
                                                @endcan
                                                @can('bank-statement-entry-reject')
                                                    <div class="bk-inline-form" style="margin-top:6px">
                                                        <input name="reason_{{ $entry->id }}" dir="auto" placeholder="Reason to reject" aria-label="Reason to reject entry {{ $entry->id }}">
                                                        <button type="button" class="ak-btn ak-btn-danger-outline ak-btn-sm"
                                                            onclick="const f=this.closest('form'); const r=f.querySelector('[name=reason_{{ $entry->id }}]').value; if(!r){alert('Write the reason first.');return;} const h=document.createElement('input'); h.type='hidden'; h.name='reason'; h.value=r; f.appendChild(h); f.action='{{ route('bank-approvals.reject', $entry) }}'; f.submit();">Reject</button>
                                                    </div>
                                                @endcan
                                            @else
                                                <span class="ak-muted">{{ $entry->submitted_by === auth()->id() ? 'You submitted this entry: someone else must approve it.' : 'Waiting for another approver.' }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($entries->hasPages())<div class="ak-pager"><p>Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</p><div class="ak-pager-right"><div>{{ $entries->onEachSide(1)->links() }}</div></div></div>@endif
                @else
                    <div class="ak-empty"><h2>Nothing is waiting for approval</h2><p>Every submitted entry has been decided.</p></div>
                @endif
            </section>
        </form>
    </div>
</x-app-layout>
