@php
    $isPending = ($filters['status'] ?? 'pending') === 'pending';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Receipt scans" sub="Scan or photograph every receipt the day it arrives. Link it to its bank line later." :crumbs="['Receipt scans' => null]">
            @can('bank-statement-entry-create')
                <a href="{{ route('bank-statement-entries.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add entry</a>
            @endcan
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <x-bank.subnav inside />
        <x-bank.print-title title="Receipt Scan Inbox" :subtitle="$isPending ? 'Waiting to be linked' : 'Linked scans'" />
        <x-status-message />

        @can('bank-receipt-scan-create')
            <form method="POST" action="{{ route('bank-receipt-scans.store') }}" enctype="multipart/form-data" x-data="{ names: [], over: false }" class="uf-card bank-noprint">
                @csrf
                <div class="uf-card-head"><h2 class="uf-card-title">Add scans to the inbox</h2></div>
                <div class="uf-body">
                    <x-validation-errors />
                    <div class="uf-fields" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
                        <label class="bk-box" style="grid-row: span 2; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; cursor:pointer; border-style:dashed"
                            :style="over ? 'background:#e0e7ff' : ''"
                            @dragover.prevent="over = true" @dragleave.prevent="over = false"
                            @drop.prevent="over = false; $refs.files.files = $event.dataTransfer.files; names = [...$event.dataTransfer.files].map(f => f.name)">
                            <b>Drop scans or photos here</b>
                            <span class="ak-muted">or click to choose: PDF, JPG, PNG (up to 30 files)</span>
                            <input x-ref="files" type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" class="sr-only" @change="names = [...$event.target.files].map(f => f.name)">
                            <ul class="ak-muted" style="margin:8px 0 0; padding:0; list-style:none; max-height:96px; overflow:auto"><template x-for="name in names" :key="name"><li x-text="name"></li></template></ul>
                        </label>
                        <div class="uf-field"><label for="scan_date">Receipt date <span class="uf-req">*</span></label><input id="scan_date" name="scan_date" type="date" max="{{ now()->toDateString() }}" required value="{{ old('scan_date', now()->toDateString()) }}"></div>
                        <div class="uf-field"><label for="bank_account_id">Bank account (if known)</label>
                            <select data-placeholder="Not sure yet" id="bank_account_id" name="bank_account_id" class="select2 bank-select">
                                <option value="">Not sure yet</option>
                                @foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected((int) old('bank_account_id') === $account->id)>{{ $account->account_name }}</option>@endforeach
                            </select></div>
                        <div class="uf-field"><label for="amount">Amount (one file only)</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" placeholder="helps matching"></div>
                        <div class="uf-field"><label for="direction">Money</label>
                            <select data-placeholder="—" id="direction" name="direction" class="select2 bank-select">
                                <option value="">—</option>
                                <option value="credit" @selected(old('direction') === 'credit')>Received</option>
                                <option value="debit" @selected(old('direction') === 'debit')>Paid out</option>
                            </select></div>
                        <div class="uf-field"><label for="note">Note</label><input id="note" name="note" type="text" dir="auto" maxlength="500" value="{{ old('note') }}" placeholder="e.g. Raja Brothers cheque slip"></div>
                        <div class="uf-field" style="align-self:end"><button class="ak-btn ak-btn-primary">Save to inbox</button></div>
                    </div>
                </div>
            </form>
        @endcan

        <section class="ak-card">
            <div class="ak-tabs" role="tablist">
                <a href="{{ route('bank-receipt-scans.index', ['filter' => ['status' => 'pending']]) }}" role="tab" aria-selected="{{ $isPending ? 'true' : 'false' }}" class="ak-tab {{ $isPending ? 'is-active' : '' }}">Waiting <span class="ak-count">{{ $pendingCount }}</span></a>
                <a href="{{ route('bank-receipt-scans.index', ['filter' => ['status' => 'linked']]) }}" role="tab" aria-selected="{{ ! $isPending ? 'true' : 'false' }}" class="ak-tab {{ ! $isPending ? 'is-active' : '' }}">Linked</a>
            </div>

            @if ($scans->count())
                <div style="display:grid; gap:16px; padding:16px; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr))">
                    @foreach ($scans as $scan)
                        <article class="bk-box bank-keep" style="padding:0; overflow:hidden; display:flex; flex-direction:column">
                            <a href="{{ route('bank-receipt-scans.file', $scan) }}" target="_blank" rel="noopener" style="display:grid; place-items:center; height:150px; background:var(--ak-slate-bg); overflow:hidden">
                                @if ($scan->isImage())
                                    <img src="{{ route('bank-receipt-scans.file', $scan) }}" alt="{{ $scan->file_name }}" style="height:100%; width:100%; object-fit:cover" loading="lazy">
                                @else
                                    <span class="ak-strong">{{ strtoupper(pathinfo($scan->file_name, PATHINFO_EXTENSION)) }} file</span>
                                @endif
                            </a>
                            <div style="padding:12px; flex:1">
                                <p class="ak-strong" style="margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap" title="{{ $scan->file_name }}">{{ $scan->file_name }}</p>
                                <p class="ak-muted" style="margin:2px 0 0">{{ $scan->scan_date->format('d-m-Y') }} · {{ $scan->bankAccount?->account_name ?? 'account not set' }} · by {{ $scan->uploader?->name }}</p>
                                @if ($scan->amount)<p style="margin:6px 0 0"><b class="{{ $scan->direction === 'debit' ? 'bk-out' : 'bk-in' }}">PKR {{ number_format($scan->amount, 2) }}</b></p>@endif
                                @if ($scan->note)<p dir="auto" style="margin:4px 0 0">{{ $scan->note }}</p>@endif

                                @if ($isPending)
                                    @foreach ($suggestions[$scan->id] ?? [] as $entry)
                                        @can('bank-receipt-scan-link')
                                            <form method="POST" action="{{ route('bank-receipt-scans.link', $scan) }}" class="bk-box bank-noprint" style="margin-top:8px; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:6px 10px">
                                                @csrf
                                                <input type="hidden" name="bank_statement_entry_id" value="{{ $entry->id }}">
                                                <span class="ak-muted">Looks like: {{ $entry->posting_date->format('d-m') }} · {{ $entry->party_name_in_statement ?: $entry->reference }}@if ($entry->attachments_count) · has {{ $entry->attachments_count }} file(s)@endif</span>
                                                <button class="ak-btn ak-btn-success ak-btn-sm">Attach here</button>
                                            </form>
                                        @endcan
                                    @endforeach
                                @elseif ($scan->entry)
                                    <p style="margin:8px 0 0"><a href="{{ route('bank-statement-entries.show', $scan->entry) }}" data-entry="{{ route('bank-statement-entries.drawer', $scan->entry) }}" class="ak-primary-link">Linked to entry #{{ $scan->entry->id }}</a></p>
                                @endif
                            </div>
                            @if ($isPending)
                                <div class="bank-noprint bk-row" style="padding:10px 12px; border-top:1px solid var(--ak-line); background:var(--ak-soft); justify-content:space-between">
                                    @can('bank-statement-entry-create')
                                        <a href="{{ route('bank-statement-entries.create', ['scan_id' => $scan->id]) }}" class="ak-btn ak-btn-outline ak-btn-sm">Create entry</a>
                                    @endcan
                                    @can('bank-receipt-scan-link')
                                        <form method="POST" action="{{ route('bank-receipt-scans.link', $scan) }}" class="bk-row" style="gap:4px">
                                            @csrf
                                            <input name="bank_statement_entry_id" type="number" min="1" required placeholder="Entry #" aria-label="Entry number" style="width:84px; height:32px; padding:0 8px; border:1px solid var(--ak-border-strong); border-radius:6px">
                                            <button class="ak-btn ak-btn-outline ak-btn-sm">Link</button>
                                        </form>
                                    @endcan
                                    @can('bank-receipt-scan-delete')
                                        <form method="POST" action="{{ route('bank-receipt-scans.destroy', $scan) }}" onsubmit="return confirm('Delete this scan?')">@csrf @method('DELETE')<button class="ak-btn ak-btn-danger-outline ak-btn-sm">Delete</button></form>
                                    @endcan
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
                @if ($scans->hasPages())<div class="ak-pager"><div class="ak-pager-right"><div>{{ $scans->links() }}</div></div></div>@endif
            @else
                <div class="ak-empty"><h2>{{ $isPending ? 'No receipts waiting' : 'Nothing linked yet' }}</h2><p>{{ $isPending ? 'Everything scanned has been linked to its entry.' : 'Linked scans appear here.' }}</p></div>
            @endif
        </section>
    </div>
</x-app-layout>
