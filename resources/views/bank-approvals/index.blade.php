<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Approvals — waiting for a checker" :showSearch="false" :showRefresh="true" backRoute="bank-statement-entries.index" />
    </x-slot>

    <div class="py-6" x-data="{ ids: [] }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-status-message />

            <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-sm text-blue-900">
                <b>Approval limits:</b>
                @if ($thresholds['enabled'])
                    from PKR {{ number_format($thresholds['level1_threshold']) }} a checker must approve;
                    from PKR {{ number_format($thresholds['level2_threshold']) }} a senior approver must approve as well.
                    @unless ($thresholds['allow_self_approval']) You cannot approve an entry you submitted yourself. @endunless
                @else
                    Maker-checker is switched off — entries are posted by the maker.
                @endif
            </div>

            <form method="POST" action="{{ route('bank-approvals.bulk-approve') }}">
                @csrf
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-gray-600">{{ $entries->total() }} entries waiting</p>
                    @can('bank-statement-entry-approve')
                        <button x-show="ids.length" x-cloak class="rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-emerald-800" onclick="return confirm('Approve the ticked entries?')">Approve <span x-text="ids.length"></span> selected</button>
                    @endcan
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    @forelse ($entries as $entry)
                        @php($approved = $approvedCounts[$entry->id])
                        @php($actionable = $canAct($entry))
                        <div class="border-b last:border-0 p-4 flex flex-wrap items-start gap-4">
                            <div class="pt-1">
                                @if ($actionable)<input type="checkbox" name="ids[]" value="{{ $entry->id }}" x-model="ids" class="rounded border-gray-300">@else<span class="inline-block w-4"></span>@endif
                            </div>
                            <div class="flex-1 min-w-[16rem]">
                                <a href="{{ route('bank-statement-entries.show', $entry) }}" class="font-semibold text-blue-800 hover:underline" dir="auto">{{ $entry->company?->company_name ?? $entry->party_name_in_statement }}</a>
                                <p class="text-xs text-gray-500">{{ $entry->posting_date->format('d-m-Y') }} · {{ $entry->reference }} · {{ $entry->category?->name }} · {{ $entry->bankAccount->account_name }}</p>
                                <p class="text-xs text-gray-500">Submitted by <b>{{ $entry->submitter?->name }}</b> {{ $entry->submitted_at?->diffForHumans() }} · {{ $entry->attachments->count() }} file(s)</p>
                                @if ($entry->attachments->isNotEmpty())
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        @foreach ($entry->attachments as $attachment)
                                            <a href="{{ route('bank-attachments.download', ['attachment' => $attachment, 'inline' => 1]) }}" target="_blank" class="inline-flex items-center gap-1 rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs text-blue-800 hover:bg-blue-50" title="{{ $attachment->description }}">
                                                @if (str_starts_with((string) $attachment->file_type, 'image/'))<img src="{{ route('bank-attachments.download', ['attachment' => $attachment, 'inline' => 1]) }}" alt="" class="h-6 w-6 rounded object-cover">@else 📄 @endif
                                                {{ \Illuminate\Support\Str::limit($attachment->file_name, 28) }}
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mt-1 text-xs text-amber-700">No receipt or file attached.</p>
                                @endif
                                <div class="mt-1 flex gap-1 text-xs">
                                    <span class="rounded px-2 py-0.5 {{ $approved->count() >= 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">Checker {{ $approved->count() >= 1 ? '✓ '.$approved->first()->user?->name : 'pending' }}</span>
                                    @if ($entry->approval_level_required === 2)
                                        <span class="rounded px-2 py-0.5 {{ $approved->count() >= 2 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">Senior {{ $approved->count() >= 2 ? '✓' : 'pending' }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-end">
                                <p class="text-xl font-bold tabular-nums {{ $entry->direction === 'credit' ? 'text-emerald-700' : 'text-red-700' }}">{{ $entry->direction === 'credit' ? '+' : '−' }} {{ number_format($entry->amount, 2) }}</p>
                                <p class="text-xs text-gray-500">{{ $entry->direction === 'credit' ? 'Received' : 'Paid' }}</p>
                            </div>
                            <div class="flex flex-col gap-1.5 min-w-[13rem]">
                                @if ($actionable)
                                    @can('bank-statement-entry-approve')
                                        <button type="submit" formaction="{{ route('bank-approvals.approve', $entry) }}" class="rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-emerald-800">Approve{{ $entry->approval_level_required === 2 && $approved->count() >= 1 ? ' (senior)' : '' }}</button>
                                    @endcan
                                    @can('bank-statement-entry-reject')
                                        <div class="flex gap-1">
                                            <input name="reason_{{ $entry->id }}" dir="auto" placeholder="Reason to reject" class="flex-1 min-w-0 rounded-md border-gray-300 text-xs py-1">
                                            <button type="button" class="rounded-md bg-red-700 px-2 text-xs font-semibold text-white hover:bg-red-800"
                                                onclick="const f=this.closest('form'); const r=f.querySelector('[name=reason_{{ $entry->id }}]').value; if(!r){alert('Write the reason first.');return;} const h=document.createElement('input'); h.type='hidden'; h.name='reason'; h.value=r; f.appendChild(h); f.action='{{ route('bank-approvals.reject', $entry) }}'; f.submit();">Reject</button>
                                        </div>
                                    @endcan
                                @else
                                    <p class="text-xs text-gray-500">{{ $entry->submitted_by === auth()->id() ? 'You submitted this entry — someone else must approve it.' : 'Waiting for another approver.' }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-gray-600 py-10">Nothing is waiting for approval. 🎉</p>
                    @endforelse
                </div>
                @if ($entries->hasPages())<div class="mt-3">{{ $entries->links() }}</div>@endif
            </form>
        </div>
    </div>
</x-app-layout>
