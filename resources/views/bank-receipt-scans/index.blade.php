@php
    $isPending = ($filters['status'] ?? 'pending') === 'pending';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Receipt Scan Inbox</h2>
            <div class="flex gap-2">
                @can('bank-statement-entry-create')
                    <a href="{{ route('bank-statement-entries.create') }}" class="inline-flex items-center px-3 py-2 bg-green-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-900">＋ Add entry</a>
                @endcan
                <a href="{{ route('bank-statement-entries.index') }}" class="inline-flex items-center px-3 py-2 bg-blue-950 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-900">Register</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
    <x-bank.print-styles />
    <x-bank.subnav inside />
    <x-bank.print-title title="Receipt Scan Inbox" />
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-status-message />

            <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-sm text-blue-900">
                <b>Daily routine:</b> scan or photograph every receipt, slip and cheque copy the day it arrives and drop it here. When the bank line is imported or typed in, link the scan to it — nothing gets lost, and the register shows which entries still have no receipt.
            </div>

            @can('bank-receipt-scan-create')
                <form method="POST" action="{{ route('bank-receipt-scans.store') }}" enctype="multipart/form-data" x-data="{ names: [], over: false }"
                      class="bg-white shadow-xl sm:rounded-lg p-4">
                    @csrf
                    <x-validation-errors class="mb-3" />
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <label class="lg:row-span-2 flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-8 text-center cursor-pointer transition"
                               :class="over ? 'border-blue-500 bg-blue-50' : 'border-gray-300 bg-gray-50 hover:bg-gray-100'"
                               @dragover.prevent="over = true" @dragleave.prevent="over = false"
                               @drop.prevent="over = false; $refs.files.files = $event.dataTransfer.files; names = [...$event.dataTransfer.files].map(f => f.name)">
                            <span class="text-3xl">📎</span>
                            <span class="mt-1 font-semibold text-gray-800">Drop scans or photos here</span>
                            <span class="text-xs text-gray-500">or click to choose — PDF, JPG, PNG (up to 30 files)</span>
                            <input x-ref="files" type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" class="sr-only" @change="names = [...$event.target.files].map(f => f.name)">
                            <ul class="mt-2 text-xs text-gray-700 max-h-24 overflow-y-auto"><template x-for="name in names" :key="name"><li x-text="name"></li></template></ul>
                        </label>
                        <div>
                            <x-label for="scan_date" value="Receipt date" :required="true" />
                            <x-input id="scan_date" name="scan_date" type="date" max="{{ now()->toDateString() }}" class="mt-1 block w-full" required :value="old('scan_date', now()->toDateString())" />
                        </div>
                        <div>
                            <x-label for="bank_account_id" value="Bank account (if known)" />
                            <select id="bank_account_id" name="bank_account_id" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                                <option value="">Not sure yet</option>
                                @foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected((int) old('bank_account_id') === $account->id)>{{ $account->account_name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-label for="amount" value="Amount (one file only)" />
                                <x-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('amount')" placeholder="helps matching" />
                            </div>
                            <div>
                                <x-label for="direction" value="Money" />
                                <select id="direction" name="direction" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                                    <option value="">—</option>
                                    <option value="credit" @selected(old('direction') === 'credit')>Received</option>
                                    <option value="debit" @selected(old('direction') === 'debit')>Paid out</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <x-label for="note" value="Note" />
                            <x-input id="note" name="note" type="text" dir="auto" maxlength="500" class="mt-1 block w-full" :value="old('note')" placeholder="e.g. Raja Brothers cheque slip" />
                        </div>
                        <div class="flex items-end justify-end"><x-button class="bg-blue-950">Save to inbox</x-button></div>
                    </div>
                </form>
            @endcan

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('bank-receipt-scans.index', ['filter' => ['status' => 'pending']]) }}" class="rounded-full px-3 py-1 text-sm border {{ $isPending ? 'bg-blue-950 text-white border-blue-950' : 'bg-white text-gray-700 border-gray-300' }}">Waiting <b>{{ $pendingCount }}</b></a>
                <a href="{{ route('bank-receipt-scans.index', ['filter' => ['status' => 'linked']]) }}" class="rounded-full px-3 py-1 text-sm border {{ ! $isPending ? 'bg-blue-950 text-white border-blue-950' : 'bg-white text-gray-700 border-gray-300' }}">Linked</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @forelse ($scans as $scan)
                    <div class="bg-white shadow rounded-lg overflow-hidden flex flex-col">
                        <a href="{{ route('bank-receipt-scans.file', $scan) }}" target="_blank" class="block h-40 bg-gray-100 grid place-items-center overflow-hidden">
                            @if ($scan->isImage())
                                <img src="{{ route('bank-receipt-scans.file', $scan) }}" alt="{{ $scan->file_name }}" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <span class="text-4xl">📄</span>
                            @endif
                        </a>
                        <div class="p-3 text-sm flex-1">
                            <p class="font-semibold truncate" title="{{ $scan->file_name }}">{{ $scan->file_name }}</p>
                            <p class="text-xs text-gray-500">{{ $scan->scan_date->format('d-m-Y') }} · {{ $scan->bankAccount?->account_name ?? 'account not set' }} · by {{ $scan->uploader?->name }}</p>
                            @if ($scan->amount)<p class="mt-1 font-bold tabular-nums {{ $scan->direction === 'debit' ? 'text-red-700' : 'text-emerald-700' }}">PKR {{ number_format($scan->amount, 2) }}</p>@endif
                            @if ($scan->note)<p class="text-gray-700" dir="auto">{{ $scan->note }}</p>@endif

                            @if ($isPending)
                                @foreach ($suggestions[$scan->id] ?? [] as $entry)
                                    @can('bank-receipt-scan-link')
                                        <form method="POST" action="{{ route('bank-receipt-scans.link', $scan) }}" class="mt-2 flex items-center justify-between gap-2 rounded bg-emerald-50 border border-emerald-200 px-2 py-1">
                                            @csrf
                                            <input type="hidden" name="bank_statement_entry_id" value="{{ $entry->id }}">
                                            <span class="text-xs text-emerald-900 truncate">Looks like: {{ $entry->posting_date->format('d-m') }} · {{ $entry->party_name_in_statement ?: $entry->reference }}@if ($entry->attachments_count) · has {{ $entry->attachments_count }} file(s)@endif</span>
                                            <button class="text-xs font-semibold text-emerald-800 hover:underline shrink-0">Attach here</button>
                                        </form>
                                    @endcan
                                @endforeach
                            @elseif ($scan->entry)
                                <a href="{{ route('bank-statement-entries.show', $scan->entry) }}" class="mt-2 inline-block text-blue-800 hover:underline">Linked to entry #{{ $scan->entry->id }}</a>
                            @endif
                        </div>
                        @if ($isPending)
                            <div class="border-t bg-gray-50 px-3 py-2 flex flex-wrap items-center justify-between gap-2">
                                @can('bank-statement-entry-create')
                                    <a href="{{ route('bank-statement-entries.create', ['scan_id' => $scan->id]) }}" class="text-xs font-semibold text-blue-800 hover:underline">Create entry from this scan</a>
                                @endcan
                                @can('bank-receipt-scan-link')
                                    <form method="POST" action="{{ route('bank-receipt-scans.link', $scan) }}" class="flex items-center gap-1">
                                        @csrf
                                        <input name="bank_statement_entry_id" type="number" min="1" required placeholder="Entry #" class="w-20 rounded border-gray-300 text-xs py-1">
                                        <button class="text-xs font-semibold text-gray-700 hover:underline">Link</button>
                                    </form>
                                @endcan
                                @can('bank-receipt-scan-delete')
                                    <form method="POST" action="{{ route('bank-receipt-scans.destroy', $scan) }}" onsubmit="return confirm('Delete this scan?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Delete</button></form>
                                @endcan
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="col-span-full text-center text-gray-600 py-10">{{ $isPending ? 'No receipts waiting. Everything scanned has been linked. 🎉' : 'Nothing linked yet.' }}</p>
                @endforelse
            </div>
            @if ($scans->hasPages())<div>{{ $scans->links() }}</div>@endif
        </div>
    </div>
</x-app-layout>
