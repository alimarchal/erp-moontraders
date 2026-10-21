@php
    $canEdit = ! $entry->isLocked();
    $rules = app(\App\Services\BankRegister\BankApprovalRules::class);
    $level = $rules->requiredLevel($entry);
@endphp
<div class="p-6 space-y-5 text-sm">
    {{-- Heading --}}
    <div class="pe-10">
        <div class="flex items-center gap-2 flex-wrap">
            <x-bank.status-badge :status="$entry->status" />
            @if ($entry->needs_review)<span class="rounded-full bg-orange-100 text-orange-800 px-2 py-0.5 text-xs font-semibold">Needs review</span>@endif
            @if ($entry->receipt_no)<span class="font-mono text-xs text-emerald-700">{{ $entry->receipt_no }}</span>@endif
        </div>
        <p class="mt-2 text-3xl font-bold tabular-nums {{ $entry->direction === 'credit' ? 'text-emerald-700' : 'text-red-700' }}">
            {{ $entry->direction === 'credit' ? '+' : '−' }} PKR {{ number_format($entry->amount, 2) }}
        </p>
        <p class="text-gray-500">{{ $entry->direction === 'credit' ? 'Received' : 'Paid' }} on {{ $entry->posting_date->format('d F Y') }} · {{ $entry->bankAccount->account_name }}</p>
    </div>

    {{-- Facts --}}
    <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
        <div><dt class="text-xs uppercase text-gray-500">Party on statement</dt><dd class="font-semibold" dir="auto">{{ $entry->party_name_in_statement ?? '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500">Company</dt><dd class="font-semibold" dir="auto">{{ $entry->company?->company_name ?? '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500">Category</dt><dd class="font-semibold">{{ $entry->category?->name ?? '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500">Reference</dt><dd class="font-semibold break-all">{{ $entry->reference ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-xs uppercase text-gray-500">Narration</dt><dd dir="auto" class="text-gray-700">{{ $entry->narration ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-xs uppercase text-gray-500">Note</dt><dd dir="auto" class="text-gray-700">{{ $entry->description ?: '—' }}</dd></div>
        @if ($entry->source_statement)<div class="col-span-2 text-xs text-gray-500">{{ $entry->source_statement }}@if ($entry->pdf_page) · PDF page {{ $entry->pdf_page }}@endif</div>@endif
    </dl>

    {{-- Journal --}}
    @if ($entry->journalEntry)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs uppercase text-emerald-800">Journal entry</p>
            <a href="{{ route('journal-entries.show', $entry->journalEntry) }}" class="font-semibold text-emerald-900 hover:underline">#{{ $entry->journalEntry->id }} · {{ $entry->journalEntry->reference }}</a>
            <span class="text-xs text-emerald-800">posted {{ $entry->posted_at?->format('d-m-Y H:i') }} by {{ $entry->poster?->name }}</span>
        </div>
    @endif

    {{-- Approval --}}
    <div class="rounded-lg border border-gray-200 p-3">
        <p class="text-xs uppercase text-gray-500 mb-1">Approval</p>
        @if ($level === 0)
            <p class="text-gray-700">Below the approval limit (PKR {{ number_format(config('bank.maker_checker.level1_threshold')) }}) — the maker can post it directly.</p>
        @else
            <p class="text-gray-700">Needs <b>{{ $level === 2 ? 'checker + senior approver' : 'a checker' }}</b>
                ({{ $level === 2 ? 'from PKR '.number_format(config('bank.maker_checker.level2_threshold')) : 'from PKR '.number_format(config('bank.maker_checker.level1_threshold')) }}).</p>
        @endif
        @if ($entry->rejection_reason)
            <p class="mt-2 rounded bg-red-50 border border-red-200 p-2 text-red-800"><b>Rejected</b> by {{ $entry->rejecter?->name }}: {{ $entry->rejection_reason }}</p>
        @endif
        @if ($entry->approvals->isNotEmpty())
            <ol class="mt-2 space-y-1 border-s-2 border-gray-200 ps-3">
                @foreach ($entry->approvals as $step)
                    <li class="text-xs text-gray-700">
                        <b>{{ ucfirst(str_replace('_', ' ', $step->action)) }}</b>@if ($step->level) (level {{ $step->level }})@endif
                        — {{ $step->user?->name ?? 'System' }}, {{ $step->acted_at->format('d-m-Y H:i') }}
                        @if ($step->comment)<span class="text-gray-500" dir="auto"> · “{{ $step->comment }}”</span>@endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    {{-- Actions --}}
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('bank-statement-entries.show', $entry) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">Open full page</a>
        @can('bank-statement-entry-edit')
            @if ($canEdit)<a href="{{ route('bank-statement-entries.edit', $entry) }}" class="rounded-md bg-blue-950 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900">Edit / classify</a>@endif
        @endcan
        @can('bank-statement-entry-submit')
            @if (in_array($entry->status, ['classified', 'rejected', 'reversed'], true))
                <form method="POST" action="{{ route('bank-statement-entries.submit', $entry) }}">@csrf
                    <button class="rounded-md bg-green-800 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900">{{ $level === 0 ? 'Post now' : 'Submit for approval' }}</button>
                </form>
            @endif
        @endcan
        @can('bank-statement-entry-approve')
            @if ($entry->status === 'pending_approval')
                <form method="POST" action="{{ route('bank-approvals.approve', $entry) }}">@csrf
                    <button class="rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-emerald-800">Approve</button>
                </form>
            @endif
        @endcan
        @can('bank-statement-entry-classify')
            <form method="POST" action="{{ route('bank-statement-entries.flag', $entry) }}">@csrf
                <button class="rounded-md border border-orange-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-orange-800 hover:bg-orange-50">{{ $entry->needs_review ? 'Clear review flag' : 'Flag for review' }}</button>
            </form>
        @endcan
    </div>

    @can('bank-statement-entry-reject')
        @if ($entry->status === 'pending_approval')
            <form method="POST" action="{{ route('bank-approvals.reject', $entry) }}" class="flex gap-2">@csrf
                <input name="reason" required maxlength="500" dir="auto" placeholder="Reason for rejecting" class="flex-1 rounded-md border-gray-300 text-sm">
                <button class="rounded-md bg-red-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-800">Reject</button>
            </form>
        @endif
    @endcan

    @can('bank-statement-entry-reverse')
        @if ($entry->status === 'posted')
            <form method="POST" action="{{ route('bank-statement-entries.reverse', $entry) }}" class="flex gap-2" onsubmit="return confirm('Reverse this entry? A reversing journal entry will be posted.')">@csrf
                <input name="reason" required maxlength="500" dir="auto" placeholder="Reason for reversing" class="flex-1 rounded-md border-gray-300 text-sm">
                <button class="rounded-md border border-purple-400 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-purple-800 hover:bg-purple-50">Reverse</button>
            </form>
        @endif
    @endcan

    {{-- Attachments --}}
    <div>
        <p class="text-xs uppercase text-gray-500 mb-1">Receipt &amp; attachments ({{ $entry->attachments->count() }})</p>
        @forelse ($entry->attachments as $attachment)
            <div class="flex items-center justify-between gap-2 border-b py-1.5">
                <div class="min-w-0">
                    <a href="{{ route('bank-attachments.download', ['attachment' => $attachment, 'inline' => 1]) }}" target="_blank" class="font-semibold text-blue-700 hover:underline truncate block">{{ $attachment->file_name }}</a>
                    <p class="text-xs text-gray-500" dir="auto">{{ \App\Models\Attachment::LABELS[$attachment->label] ?? $attachment->label }} · {{ number_format(($attachment->file_size ?? 0) / 1024, 1) }} KB · {{ $attachment->uploader?->name }}@if ($attachment->description) · {{ $attachment->description }}@endif</p>
                </div>
                @can('bank-statement-entry-attach')
                    <form method="POST" action="{{ route('bank-attachments.destroy', $attachment) }}" onsubmit="return confirm('Remove this file?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Remove</button></form>
                @endcan
            </div>
        @empty
            <p class="text-gray-500">No receipt or file attached yet.</p>
        @endforelse

        @can('bank-statement-entry-attach')
            @if ($entry->status !== 'reconciled')
                <form method="POST" action="{{ route('bank-statement-entries.attachments.store', $entry) }}" enctype="multipart/form-data" class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 space-y-2">
                    @csrf
                    <input type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" class="block w-full text-xs">
                    <div class="flex gap-2">
                        <select name="label" class="rounded-md border-gray-300 text-sm py-1.5">
                            @foreach (\App\Models\Attachment::LABELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        <input name="description" maxlength="500" dir="auto" placeholder="Description (optional)" class="flex-1 rounded-md border-gray-300 text-sm py-1.5">
                        <button class="rounded-md bg-blue-950 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900">Attach</button>
                    </div>
                </form>
            @endif
        @endcan
    </div>

    {{-- Audit --}}
    <details>
        <summary class="cursor-pointer text-xs uppercase text-gray-500">History ({{ $entry->auditLogs->count() }})</summary>
        <ul class="mt-2 space-y-1 text-xs text-gray-700">
            @foreach ($entry->auditLogs as $log)
                <li><b>{{ ucfirst(str_replace('_', ' ', $log->action)) }}</b> — {{ $log->user?->name ?? 'System' }}, {{ $log->created_at->format('d-m-Y H:i') }}</li>
            @endforeach
        </ul>
    </details>
</div>
