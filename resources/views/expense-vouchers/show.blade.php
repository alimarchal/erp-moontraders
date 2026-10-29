<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">Expense Voucher {{ $voucher->voucher_number }}</h2>
        <div class="flex justify-center items-center float-right space-x-2">
            @if($voucher->isApproved())
                @can('expense-voucher-print')
                    <a href="{{ route('expense-vouchers.print', $voucher) }}" target="_blank" title="Print thermal slip (80mm)"
                        class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md text-white hover:bg-green-800 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                    </a>
                @endcan
            @endif
            <a href="{{ route('expense-vouchers.index') }}" title="Back to vouchers"
                class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md text-white hover:bg-green-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
        </div>
    </x-slot>

    @php($isMaker = (int) $voucher->created_by === auth()->id() || (int) $voucher->submitted_by === auth()->id())
    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-status-message class="shadow-md" />
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gray-500">Status</p>
                        <p class="text-lg font-bold">{{ $voucher->statusLabel() }}</p>
                    </div>
                    <p class="text-3xl font-bold font-mono">Rs {{ number_format($voucher->amount, 2) }}</p>
                </div>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 mt-6 text-sm">
                    <div><dt class="text-gray-500">Date</dt><dd class="font-semibold">{{ $voucher->voucher_date->format('l, d M Y') }}</dd></div>
                    <div><dt class="text-gray-500">Company</dt><dd class="font-semibold">{{ $voucher->supplier?->supplier_name }}</dd></div>
                    <div><dt class="text-gray-500">Party name</dt><dd class="font-semibold">{{ $voucher->party_name }}</dd></div>
                    <div><dt class="text-gray-500">Expense account</dt><dd class="font-semibold">{{ $voucher->expenseAccount?->account_code }} - {{ $voucher->expenseAccount?->account_name }}</dd></div>
                    <div class="md:col-span-2"><dt class="text-gray-500">Narration</dt><dd class="font-semibold">{{ $voucher->narration ?: '-' }}</dd></div>
                </dl>
                @if($voucher->status === 'rejected' && $voucher->rejection_reason)
                    <div class="mt-4 p-3 rounded-md bg-red-50 border border-red-200 text-sm text-red-800"><b>Rejected by {{ $voucher->checker?->name }}:</b> {{ $voucher->rejection_reason }}</div>
                @endif
            </div>

            <div class="bg-white shadow-xl sm:rounded-lg p-6 text-sm">
                <h3 class="font-bold mb-3">Maker and checker</h3>
                <ul class="space-y-1">
                    <li><span class="text-gray-500">Made by:</span> <b>{{ $voucher->creator?->name }}</b> on {{ $voucher->created_at?->format('d-M-Y h:i A') }}</li>
                    <li><span class="text-gray-500">Sent to checker:</span> {{ $voucher->submitted_at ? $voucher->submitter?->name.' on '.$voucher->submitted_at->format('d-M-Y h:i A') : 'Not yet' }}</li>
                    <li><span class="text-gray-500">Checked by:</span> {{ $voucher->checked_at ? $voucher->checker?->name.' on '.$voucher->checked_at->format('d-M-Y h:i A') : 'Not yet' }}</li>
                    @if($voucher->journalEntry)
                        <li><span class="text-gray-500">Ledger:</span> Journal entry #{{ $voucher->journalEntry->id }} (Dr expense account, Cr Cash)</li>
                    @endif
                </ul>
            </div>

            @if($voucher->isEditable() || $voucher->status === 'pending')
                <div class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-wrap gap-3 items-start">
                    @if($voucher->isEditable())
                        @can('expense-voucher-edit')
                            <a href="{{ route('expense-vouchers.edit', $voucher) }}" class="px-4 py-2 text-sm font-semibold border border-gray-300 rounded-md hover:bg-gray-100">Edit</a>
                        @endcan
                        @can('expense-voucher-create')
                            <form method="POST" action="{{ route('expense-vouchers.submit', $voucher) }}">@csrf
                                <button class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Send to checker</button>
                            </form>
                        @endcan
                        @can('expense-voucher-delete')
                            <form method="POST" action="{{ route('expense-vouchers.destroy', $voucher) }}" onsubmit="return confirm('Delete this voucher?');">@csrf @method('DELETE')
                                <button class="px-4 py-2 text-sm font-semibold text-red-700 border border-red-300 rounded-md hover:bg-red-50">Delete</button>
                            </form>
                        @endcan
                    @endif
                    @if($voucher->status === 'pending')
                        @can('expense-voucher-approve')
                            @if($isMaker)
                                <p class="text-sm text-gray-600">You made this voucher, so another user has to check it.</p>
                            @else
                                <form method="POST" action="{{ route('expense-vouchers.approve', $voucher) }}" onsubmit="return confirm('Approve and post this voucher to the ledger?');">@csrf
                                    <button class="px-4 py-2 text-sm font-semibold text-white bg-green-700 rounded-md hover:bg-green-800">Approve and post</button>
                                </form>
                                <form method="POST" action="{{ route('expense-vouchers.reject', $voucher) }}" class="flex gap-2 items-start">@csrf
                                    <input type="text" name="rejection_reason" required maxlength="500" placeholder="Reason for rejecting"
                                        class="border-gray-300 rounded-md shadow-sm text-sm w-64">
                                    <button class="px-4 py-2 text-sm font-semibold text-red-700 border border-red-300 rounded-md hover:bg-red-50">Reject</button>
                                </form>
                            @endif
                        @endcan
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
