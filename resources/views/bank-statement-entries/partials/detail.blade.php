@php
    $canEdit = ! $entry->isLocked();
    $rules = app(\App\Services\BankRegister\BankApprovalRules::class);
    $level = $rules->requiredLevel($entry);
    $isIn = $entry->direction === 'credit';
@endphp
<div class="bk-stack">
    {{-- Heading --}}
    <div>
        <div class="bk-row">
            <x-bank.status-badge :status="$entry->status" />
            @if ($entry->needs_review)<span class="ak-status ak-status-amber"><i aria-hidden="true"></i>Needs review</span>@endif
            @if ($entry->receipt_no)<span class="ak-mono"><b>{{ $entry->receipt_no }}</b></span>@endif
        </div>
        <p class="bk-amount {{ $isIn ? 'bk-in' : 'bk-out' }}">{{ $isIn ? '+' : '−' }} PKR {{ number_format($entry->amount, 2) }}</p>
        <p class="ak-muted">{{ $isIn ? 'Received' : 'Paid out' }} on {{ $entry->posting_date->format('d F Y') }} · {{ $entry->bankAccount->account_name }}</p>
    </div>

    {{-- Facts --}}
    <dl class="bk-dl">
        <div><dt>Party on statement</dt><dd dir="auto">{{ $entry->party_name_in_statement ?? '—' }}</dd></div>
        <div><dt>Company / principal</dt><dd dir="auto">{{ $entry->company?->supplier_name ?? '—' }}</dd></div>
        <div><dt>Category</dt><dd>{{ $entry->category?->name ?? '—' }}</dd></div>
        <div><dt>Reference</dt><dd>{{ $entry->reference ?? '—' }}</dd></div>
        <div class="bk-wide"><dt>Narration</dt><dd dir="auto">{{ $entry->narration ?? '—' }}</dd></div>
        <div class="bk-wide"><dt>Note</dt><dd dir="auto">{{ $entry->description ?: '—' }}</dd></div>
        @if ($entry->source_statement)<div class="bk-wide ak-muted">{{ $entry->source_statement }}@if ($entry->pdf_page) · PDF page {{ $entry->pdf_page }}@endif</div>@endif
    </dl>

    {{-- Journal --}}
    @if ($entry->journalEntry)
        <div class="bk-box">
            <h4>Journal entry</h4>
            <p><a href="{{ route('journal-entries.show', $entry->journalEntry) }}" class="ak-primary-link">#{{ $entry->journalEntry->id }} · {{ $entry->journalEntry->reference }}</a>
                <span class="ak-muted">posted {{ $entry->posted_at?->format('d-m-Y H:i') }} by {{ $entry->poster?->name }}</span></p>
        </div>
    @endif

    {{-- Approval --}}
    <div class="bk-box">
        <h4>Approval</h4>
        @if ($level === 0)
            <p>Below the approval limit (PKR {{ number_format(config('bank.maker_checker.level1_threshold')) }}): the maker can post it directly.</p>
        @else
            <p>Needs <b>{{ $level === 2 ? 'a checker and a senior approver' : 'a checker' }}</b>
                (from PKR {{ number_format($level === 2 ? config('bank.maker_checker.level2_threshold') : config('bank.maker_checker.level1_threshold')) }}).</p>
        @endif
        @if ($entry->rejection_reason)
            <p style="margin-top:8px"><b>Rejected</b> by {{ $entry->rejecter?->name }}: {{ $entry->rejection_reason }}</p>
        @endif
        @if ($entry->approvals->isNotEmpty())
            <ol style="margin:8px 0 0; padding-left:18px; font-size:13px">
                @foreach ($entry->approvals as $step)
                    <li><b>{{ ucfirst(str_replace('_', ' ', $step->action)) }}</b>@if ($step->level) (level {{ $step->level }})@endif
                        — {{ $step->user?->name ?? 'System' }}, {{ $step->acted_at->format('d-m-Y H:i') }}@if ($step->comment) · “{{ $step->comment }}”@endif</li>
                @endforeach
            </ol>
        @endif
    </div>

    {{-- Attachments --}}
    <div class="bk-box">
        <h4>Receipt &amp; attachments ({{ $entry->attachments->count() }})</h4>
        @forelse ($entry->attachments as $attachment)
            <div class="bk-file">
                <div style="min-width:0">
                    <a href="{{ route('bank-attachments.download', ['attachment' => $attachment, 'inline' => 1]) }}" target="_blank" rel="noopener">{{ $attachment->file_name }}</a>
                    <div class="ak-muted" dir="auto">{{ \App\Models\Attachment::LABELS[$attachment->label] ?? $attachment->label }} · {{ number_format(($attachment->file_size ?? 0) / 1024, 1) }} KB · {{ $attachment->uploader?->name }}@if ($attachment->description) · {{ $attachment->description }}@endif</div>
                </div>
                @can('bank-statement-entry-attach')
                    <form method="POST" class="bank-noprint" action="{{ route('bank-attachments.destroy', $attachment) }}" onsubmit="return confirm('Remove this file?')">@csrf @method('DELETE')<button class="ak-btn ak-btn-danger-outline ak-btn-sm">Remove</button></form>
                @endcan
            </div>
        @empty
            <p class="ak-muted">No receipt or file attached yet.</p>
        @endforelse

        @can('bank-statement-entry-attach')
            @if ($entry->status !== 'reconciled')
                <form method="POST" action="{{ route('bank-statement-entries.attachments.store', $entry) }}" enctype="multipart/form-data" class="bank-noprint bk-stack" style="margin-top:12px">
                    @csrf
                    <input type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv">
                    <div class="bk-row">
                        <select data-allow-clear="false" data-width="170px" name="label" class="select2 bank-select">
                            @foreach (\App\Models\Attachment::LABELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        <input name="description" maxlength="500" dir="auto" placeholder="Description (optional)" style="flex:1; min-width:140px; height:38px; padding:0 12px; border:1px solid var(--ak-border-strong); border-radius:8px">
                        <button class="ak-btn ak-btn-primary">Attach</button>
                    </div>
                </form>
            @endif
        @endcan
    </div>

    {{-- Actions --}}
    <div class="bank-noprint bk-stack">
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
                @if ($entry->status === 'pending_approval')
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
        </div>

        @can('bank-statement-entry-reject')
            @if ($entry->status === 'pending_approval')
                <form method="POST" action="{{ route('bank-approvals.reject', $entry) }}" class="bk-inline-form">@csrf
                    <input name="reason" required maxlength="500" dir="auto" placeholder="Reason for rejecting">
                    <button class="ak-btn ak-btn-danger-outline">Reject</button>
                </form>
            @endif
        @endcan

        @can('bank-statement-entry-reverse')
            @if ($entry->status === 'posted')
                <form method="POST" action="{{ route('bank-statement-entries.reverse', $entry) }}" class="bk-inline-form" onsubmit="return confirm('Reverse this entry? A reversing journal entry will be posted.')">@csrf
                    <input name="reason" required maxlength="500" dir="auto" placeholder="Reason for reversing">
                    <button class="ak-btn ak-btn-danger-outline">Reverse</button>
                </form>
            @endif
        @endcan
    </div>

    {{-- Audit --}}
    <details>
        <summary class="ak-muted" style="cursor:pointer">History ({{ $entry->auditLogs->count() }})</summary>
        <ul style="margin:8px 0 0; padding-left:18px; font-size:13px">
            @foreach ($entry->auditLogs as $log)
                <li><b>{{ ucfirst(str_replace('_', ' ', $log->action)) }}</b> — {{ $log->user?->name ?? 'System' }}, {{ $log->created_at->format('d-m-Y H:i') }}</li>
            @endforeach
        </ul>
    </details>
</div>
