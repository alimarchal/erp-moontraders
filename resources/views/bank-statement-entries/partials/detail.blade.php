@php
    $canEdit = ! $entry->isLocked();
    $rules = app(\App\Services\BankRegister\BankApprovalRules::class);
    $level = $rules->requiredLevel($entry);
    $isIn = $entry->direction === 'credit';
    $isOwnSubmission = (int) $entry->submitted_by === (int) auth()->id() && ! config('bank.maker_checker.allow_self_approval');
    $canDelete = $canEdit && $entry->status !== 'pending_approval';
    $canAttach = auth()->user()->can('bank-statement-entry-attach');
    $fileSize = fn (?int $bytes): string => ($bytes ?? 0) >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : number_format(($bytes ?? 0) / 1024, 1).' KB';
@endphp
<div class="bk-stack" data-bk-title="Transaction #{{ $entry->id }}@if ($entry->receipt_no) · {{ $entry->receipt_no }}@endif">
    {{-- Heading --}}
    <div class="bk-hero">
        <div class="bk-row">
            <x-bank.status-badge :status="$entry->status" />
            @if ($entry->needs_review)<span class="ak-status ak-status-amber"><i aria-hidden="true"></i>Needs review</span>@endif
            @if ($entry->receipt_no)<span class="ak-mono"><b>{{ $entry->receipt_no }}</b></span>@endif
        </div>
        <p class="bk-amount {{ $isIn ? 'bk-in' : 'bk-out' }}">{{ $isIn ? '+' : '−' }} PKR {{ number_format($entry->amount, 2) }}</p>
        <p class="ak-muted">{{ $isIn ? 'Received' : 'Paid out' }} on {{ $entry->posting_date->format('d F Y') }} · {{ $entry->bankAccount->account_name }}</p>
    </div>

    {{-- Bordered sheets, as on the Cash Collection report: the same tables on screen, in the pop-up and on paper --}}
    <table class="bk-t">
        <thead><tr class="cap"><th colspan="4">1 - Entry <small>{{ $entry->source_statement }}@if ($entry->pdf_page) · PDF page {{ $entry->pdf_page }}@endif</small></th></tr></thead>
        <tbody>
            <tr><th>Date</th><td>{{ $entry->posting_date->format('d-m-Y') }}</td><th>Account</th><td>{{ $entry->bankAccount->account_name }}@if ($entry->bankAccount->account_number) ({{ $entry->bankAccount->account_number }})@endif</td></tr>
            <tr><th>{{ $isIn ? 'Received' : 'Paid out' }} (PKR)</th><td class="b">{{ number_format($entry->amount, 2) }}</td><th>Receipt no.</th><td class="b">{{ $entry->receipt_no ?? '—' }}</td></tr>
            <tr><th>Party on statement</th><td dir="auto">{{ $entry->party_name_in_statement ?? '—' }}</td><th>Company / principal</th><td dir="auto">{{ $entry->company?->supplier_name ?? '—' }}</td></tr>
            <tr><th>Category</th><td>{{ $entry->category?->name ?? '—' }}</td><th>Reference</th><td>{{ $entry->reference ?? '—' }}</td></tr>
            <tr><th>Narration</th><td colspan="3" dir="auto">{{ $entry->narration ?? '—' }}</td></tr>
            @if ($entry->salesSettlement)
                <tr><th>Raised from</th><td colspan="3"><a href="{{ route('sales-settlements.show', $entry->salesSettlement) }}" class="ak-primary-link">Sales Settlement {{ $entry->salesSettlement->settlement_number }}</a>
                    · {{ \App\Services\SettlementAttachmentService::SECTION_LABELS[$entry->settlement_section] ?? $entry->settlement_section }} · settlement is {{ $entry->salesSettlement->status }}</td></tr>
            @endif
            <tr><th>Note</th><td colspan="3" dir="auto">{{ $entry->description ?: '—' }}</td></tr>
            @if ($entry->journalEntry)
                <tr><th>Journal entry</th><td><a href="{{ route('journal-entries.show', $entry->journalEntry) }}" class="ak-primary-link">#{{ $entry->journalEntry->id }} · {{ $entry->journalEntry->reference }}</a></td>
                    <th>Posted</th><td>{{ $entry->posted_at?->format('d-m-Y H:i') }} · {{ $entry->poster?->name }}</td></tr>
            @endif
        </tbody>
    </table>

    <table class="bk-t">
        <thead>
            <tr class="cap"><th colspan="5">2 - Approval
                <small>@if ($level === 0)Below the approval limit (PKR {{ number_format(config('bank.maker_checker.level1_threshold')) }}): the maker can post it directly.@else Needs {{ $level === 2 ? 'a checker and a senior approver' : 'a checker' }} (from PKR {{ number_format($level === 2 ? config('bank.maker_checker.level2_threshold') : config('bank.maker_checker.level1_threshold')) }}).@endif</small></th></tr>
            <tr><th>Step</th><th class="c">Level</th><th>By</th><th>When</th><th>Comment</th></tr>
        </thead>
        <tbody>
            @forelse ($entry->approvals as $step)
                <tr><td class="b">{{ ucfirst(str_replace('_', ' ', $step->action)) }}</td><td class="c">{{ $step->level ?: '—' }}</td>
                    <td>{{ $step->user?->name ?? 'System' }}</td><td>{{ $step->acted_at->format('d-m-Y H:i') }}</td><td dir="auto">{{ $step->comment ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="5" class="bk-none">Not submitted yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="bk-t bk-files">
        <thead>
            <tr class="cap"><th colspan="6">3 - Receipt &amp; attachments <small>{{ $entry->attachments->count() }} {{ \Illuminate\Support\Str::plural('file', $entry->attachments->count()) }}</small></th></tr>
            <tr><th>File</th><th>Kind</th><th class="n">Size</th><th>Added by</th><th>Description</th><th class="c bank-noprint">Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($entry->attachments as $attachment)
                @php
                    $extension = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));
                    $opensInBrowser = in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                    $viewUrl = route('bank-attachments.download', ['attachment' => $attachment, 'inline' => 1]);
                    $downloadUrl = route('bank-attachments.download', $attachment);
                @endphp
                <tr>
                    <td>
                        <a href="{{ $opensInBrowser ? $viewUrl : $downloadUrl }}" @if ($opensInBrowser) target="_blank" rel="noopener" @endif class="bk-file-name">
                            <span class="bk-file-type" aria-hidden="true">{{ $extension ?: 'file' }}</span><span dir="auto">{{ $attachment->file_name }}</span>
                        </a>
                    </td>
                    <td>{{ \App\Models\Attachment::LABELS[$attachment->label] ?? $attachment->label }}</td>
                    <td class="n">{{ $fileSize($attachment->file_size) }}</td>
                    <td>{{ $attachment->uploader?->name ?? '—' }}</td>
                    <td dir="auto">{{ $attachment->description ?? '—' }}</td>
                    <td class="c bank-noprint">
                        <div class="bk-file-actions">
                            @if ($opensInBrowser)
                                <a href="{{ $viewUrl }}" target="_blank" rel="noopener" class="ak-icon ak-icon-view" title="View file" aria-label="View {{ $attachment->file_name }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 14.75a2.75 2.75 0 1 0 0-5.5 2.75 2.75 0 0 0 0 5.5Z" /></svg>
                                </a>
                            @endif
                            <a href="{{ $downloadUrl }}" class="ak-icon bk-icon-soft" title="Download file" aria-label="Download {{ $attachment->file_name }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            </a>
                            @if ($canAttach)
                                <form method="POST" action="{{ route('bank-attachments.destroy', $attachment) }}" onsubmit="return confirm('Remove this file from the entry?')">@csrf @method('DELETE')
                                    <button class="ak-icon bk-icon-danger" title="Remove file" aria-label="Remove {{ $attachment->file_name }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="bk-none">No receipt or file attached yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    @can('bank-statement-entry-attach')
        @if ($entry->status !== 'reconciled')
            <form method="POST" action="{{ route('bank-statement-entries.attachments.store', $entry) }}" enctype="multipart/form-data" class="bank-noprint bk-attach">
                @csrf
                <div class="bk-attach-file"><label for="attach-files-{{ $entry->id }}">Add a receipt, slip or cheque copy</label>
                    <input id="attach-files-{{ $entry->id }}" type="file" name="files[]" data-compress multiple required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv"></div>
                <div><label for="attach-label-{{ $entry->id }}">Kind of file</label>
                    <select id="attach-label-{{ $entry->id }}" name="label" class="select2 bank-select" data-allow-clear="false" data-width="100%">
                        @foreach (\App\Models\Attachment::LABELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select></div>
                <div class="bk-attach-desc"><label for="attach-desc-{{ $entry->id }}">Description (optional)</label>
                    <input id="attach-desc-{{ $entry->id }}" name="description" maxlength="500" dir="auto"></div>
                <button class="ak-btn ak-btn-primary">Attach</button>
            </form>
        @endif
    @endcan

    <table class="bk-t">
        <thead>
            <tr class="cap"><th colspan="3">4 - History <small>{{ $entry->auditLogs->count() }} {{ \Illuminate\Support\Str::plural('event', $entry->auditLogs->count()) }}, newest first</small></th></tr>
            <tr><th>Action</th><th>By</th><th>When</th></tr>
        </thead>
        <tbody>
            @foreach ($entry->auditLogs as $log)
                <tr><td class="b">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</td><td>{{ $log->user?->name ?? 'System' }}</td><td>{{ $log->created_at->format('d-m-Y H:i') }}</td></tr>
            @endforeach
        </tbody>
    </table>

    @if ($entry->rejection_reason)
        <p class="bk-note"><b>Rejected</b> by {{ $entry->rejecter?->name }}: {{ $entry->rejection_reason }}</p>
    @endif

    {{-- Actions --}}
    <div class="bank-noprint bk-stack bk-actions">
        <div class="bk-row">
            @can('bank-statement-entry-edit')
                @if ($canEdit)<a href="{{ route('bank-statement-entries.edit', $entry) }}" class="ak-btn ak-btn-outline">Edit / classify</a>@endif
            @endcan
            @can('bank-statement-entry-submit')
                @if (in_array($entry->status, ['classified', 'rejected', 'reversed'], true))
                    <form method="POST" action="{{ route('bank-statement-entries.submit', $entry) }}">@csrf
                        <button class="ak-btn ak-btn-success">{{ $level === 0 ? 'Post now' : 'Submit for approval' }}</button>
                    </form>
                @endif
            @endcan
            @can('bank-statement-entry-approve')
                @if ($entry->status === 'pending_approval' && ! $isOwnSubmission)
                    <form method="POST" action="{{ route('bank-approvals.approve', $entry) }}">@csrf
                        <button class="ak-btn ak-btn-success">Approve</button>
                    </form>
                @endif
            @endcan
            @can('bank-statement-entry-classify')
                <form method="POST" action="{{ route('bank-statement-entries.flag', $entry) }}">@csrf
                    <button class="ak-btn ak-btn-outline">{{ $entry->needs_review ? 'Clear review flag' : 'Flag for review' }}</button>
                </form>
            @endcan

            <span class="bk-spacer" aria-hidden="true"></span>

            @can('bank-statement-entry-reject')
                @if ($entry->status === 'pending_approval')
                    <x-bank.action-modal title="Reject entry" sub="It goes back to the maker to be corrected and submitted again."
                        :action="route('bank-approvals.reject', $entry)" trigger="Reject" confirm="Reject entry">
                        @include('bank-statement-entries.partials.summary')
                        <div><label for="reject-reason-{{ $entry->id }}">Reason for rejecting <span class="uf-req">*</span></label>
                            <textarea id="reject-reason-{{ $entry->id }}" name="reason" rows="3" required maxlength="500" dir="auto" placeholder="What has to be corrected"></textarea></div>
                    </x-bank.action-modal>
                @endif
            @endcan

            @can('bank-statement-entry-reverse')
                @if ($entry->status === 'posted' && ! $rules->reversalNeedsSecondPerson($entry, auth()->user()))
                    <x-bank.action-modal title="Reverse entry" sub="A reversing journal entry is posted. The line can then be corrected and posted again."
                        :action="route('bank-statement-entries.reverse', $entry)" trigger="Reverse entry" confirm="Reverse entry">
                        @include('bank-statement-entries.partials.summary')
                        <div><label for="reverse-reason-{{ $entry->id }}">Reason for reversing <span class="uf-req">*</span></label>
                            <textarea id="reverse-reason-{{ $entry->id }}" name="reason" rows="3" required maxlength="500" dir="auto" placeholder="Why this entry is being taken back"></textarea></div>
                    </x-bank.action-modal>
                @endif
            @endcan

            @can('bank-statement-entry-delete')
                @if ($canDelete)
                    <x-bank.action-modal title="Delete entry" sub="Use this for an entry that was typed in by mistake."
                        :action="route('bank-statement-entries.destroy', $entry)" method="DELETE" trigger="Delete entry" confirm="Delete entry">
                        @include('bank-statement-entries.partials.summary')
                        <p>This removes the line from the register. It cannot be brought back from this screen.</p>
                    </x-bank.action-modal>
                @endif
            @endcan
        </div>

        @if ($entry->status === 'pending_approval' && $isOwnSubmission)
            <p class="ak-muted">You submitted this entry, so a different person has to approve it.</p>
        @endif

        @can('bank-statement-entry-reverse')
            @if ($entry->status === 'posted' && $rules->reversalNeedsSecondPerson($entry, auth()->user()))
                <p class="ak-muted">This entry needed approval, so a different person has to reverse it.</p>
            @endif
        @endcan
    </div>
</div>
