<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
            Payment: {{ $supplierPayment->payment_number }}
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            @if ($supplierPayment->status === 'draft')
            <a href="{{ route('supplier-payments.edit', $supplierPayment->id) }}"
                class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </a>
            <form id="postPaymentForm" action="{{ route('supplier-payments.post', $supplierPayment->id) }}"
                method="POST" onsubmit="return confirmPostPayment();" class="inline-block">
                @csrf
                <input type="hidden" id="post_password" name="password" value="">
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                    <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Post Payment
                </button>
            </form>
            <form action="{{ route('supplier-payments.destroy', $supplierPayment->id) }}" method="POST"
                onsubmit="return confirm('Are you sure you want to delete this payment?');" class="inline-block">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                    <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Delete
                </button>
            </form>
            @elseif ($supplierPayment->status === 'posted')
            <form action="{{ route('supplier-payments.reverse', $supplierPayment->id) }}" method="POST"
                onsubmit="return confirmReverse();" class="inline-block">
                @csrf
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 transition">
                    <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                    Reverse Payment
                </button>
                <input type="hidden" name="password" id="reversePassword">
            </form>
            @endif
            <a href="{{ route('supplier-payments.index') }}"
                class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md" />

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Payment
                                Number
                            </h3>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{
                                $supplierPayment->payment_number }}
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Payment
                                Date
                            </h3>
                            <p class="text-lg text-gray-900 dark:text-gray-100">
                                {{ \Carbon\Carbon::parse($supplierPayment->payment_date)->format('d M Y') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</h3>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full 
                                {{ $supplierPayment->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                {{ $supplierPayment->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                {{ $supplierPayment->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $supplierPayment->status === 'bounced' ? 'bg-orange-100 text-orange-700' : '' }}
                                {{ $supplierPayment->status === 'reversed' ? 'bg-purple-100 text-purple-700' : '' }}">
                                {{ ucfirst($supplierPayment->status) }}
                            </span>
                        </div>
                    </div>

                    <hr class="my-6 border-gray-200 dark:border-gray-700">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                                Supplier
                            </h3>
                            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ $supplierPayment->supplier->supplier_name }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                                Payment
                                Method</h3>
                            <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ ucfirst(str_replace('_', ' ', $supplierPayment->payment_method)) }}</p>
                        </div>
                        @if ($supplierPayment->bankAccount)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Bank
                                Account</h3>
                            <p class="text-base text-gray-900 dark:text-gray-100">
                                {{ $supplierPayment->bankAccount->account_name }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $supplierPayment->bankAccount->account_number }}</p>
                        </div>
                        @endif
                        @if ($supplierPayment->reference_number)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                                Reference
                                Number</h3>
                            <p class="text-base text-gray-900 dark:text-gray-100">{{
                                $supplierPayment->reference_number
                                }}</p>
                        </div>
                        @endif
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Amount
                            </h3>
                            <p class="text-2xl font-bold text-emerald-600">₨ {{
                                number_format($supplierPayment->amount,
                                2) }}
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                                Created By
                            </h3>
                            <p class="text-base text-gray-900 dark:text-gray-100">{{
                                $supplierPayment->createdBy->name
                                ?? 'N/A'
                                }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $supplierPayment->created_at->format('d M Y H:i') }}
                            </p>
                        </div>
                        @if ($supplierPayment->posted_at)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Posted
                                By
                            </h3>
                            <p class="text-base text-gray-900 dark:text-gray-100">{{
                                $supplierPayment->postedBy->name ??
                                'N/A'
                                }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $supplierPayment->posted_at->format('d M Y H:i') }}
                            </p>
                        </div>
                        @endif
                        @if ($supplierPayment->reversed_at)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                                Reversed
                                By
                            </h3>
                            <p class="text-base text-gray-900 dark:text-gray-100">{{
                                $supplierPayment->reversedBy->name
                                ??
                                'N/A'
                                }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $supplierPayment->reversed_at->format('d M Y H:i')
                                }}
                            </p>
                        </div>
                        @endif
                    </div>

                    @if ($supplierPayment->description)
                    <div class="mb-6">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">
                            Description
                        </h3>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">
                            {{ $supplierPayment->description }}</p>
                    </div>
                    @endif

                    <hr class="my-6 border-gray-200 dark:border-gray-700">

                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">GRN Allocations</h3>

                    @if ($supplierPayment->grnAllocations->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th
                                        class="py-1 px-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        GRN Number</th>
                                    <th
                                        class="py-1 px-2 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        GRN Date</th>
                                    <th
                                        class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        GRN Amount</th>
                                    <th
                                        class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Allocated Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($supplierPayment->grnAllocations as $allocation)
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <td class="py-1 px-2">
                                        <a href="{{ route('goods-receipt-notes.show', $allocation->grn_id) }}"
                                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-semibold">
                                            {{ $allocation->grn->grn_number }}
                                        </a>
                                    </td>
                                    <td class="py-1 px-2 text-center">
                                        {{ \Carbon\Carbon::parse($allocation->grn->receipt_date)->format('d M Y') }}
                                    </td>
                                    <td class="py-1 px-2 text-right">
                                        ₨ {{ number_format($allocation->grn->grand_total, 2) }}
                                    </td>
                                    <td class="py-1 px-2 text-right font-semibold">
                                        ₨ {{ number_format($allocation->allocated_amount, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-900">
                                <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                                    <td colspan="3" class="py-1 px-2 text-right font-bold">Total Allocated:</td>
                                    <td class="py-1 px-2 text-right font-bold">
                                        ₨ {{
                                        number_format($supplierPayment->grnAllocations->sum('allocated_amount'), 2)
                                        }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-8">
                        <p class="text-gray-500 dark:text-gray-400">No GRN allocations found for this payment.</p>
                    </div>
                    @endif

                    @if ($supplierPayment->journalEntry)
                    <hr class="my-6 border-gray-200 dark:border-gray-700">

                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Journal Entry Details
                    </h3>

                    <div class="mb-4">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Journal Entry Number:</span>
                        <a href="{{ route('journal-entries.show', $supplierPayment->journalEntry->id) }}"
                            class="text-blue-600 hover:text-blue-800 font-semibold ml-2">
                            {{ $supplierPayment->journalEntry->entry_number }}
                        </a>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-4">Date:</span>
                        <span class="text-sm text-gray-900 dark:text-gray-100 ml-2">
                            {{ \Carbon\Carbon::parse($supplierPayment->journalEntry->entry_date)->format('d M Y') }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th
                                        class="py-1 px-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Account</th>
                                    <th
                                        class="py-1 px-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Description</th>
                                    <th
                                        class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Debit</th>
                                    <th
                                        class="py-1 px-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($supplierPayment->journalEntry->details->sortBy('line_no') as $detail)
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <td class="py-1 px-2">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $detail->account->account_code }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{
                                            $detail->account->account_name }}</div>
                                    </td>
                                    <td class="py-1 px-2 text-gray-700 dark:text-gray-300">
                                        {{ $detail->description }}
                                    </td>
                                    <td class="py-1 px-2 text-right font-semibold">
                                        @if ($detail->debit > 0)
                                        ₨ {{ number_format($detail->debit, 2) }}
                                        @else
                                        —
                                        @endif
                                    </td>
                                    <td class="py-1 px-2 text-right font-semibold">
                                        @if ($detail->credit > 0)
                                        ₨ {{ number_format($detail->credit, 2) }}
                                        @else
                                        —
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-900">
                                <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                                    <td colspan="2" class="py-1 px-2 text-right font-semibold">Totals:</td>
                                    <td class="py-1 px-2 text-right font-bold">
                                        ₨ {{ number_format($supplierPayment->journalEntry->details->sum('debit'), 2)
                                        }}
                                    </td>
                                    <td class="py-1 px-2 text-right font-bold">
                                        ₨ {{ number_format($supplierPayment->journalEntry->details->sum('credit'),
                                        2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmPostPayment() {
            if (!confirm('Are you sure you want to POST this payment? This will create accounting journal entries and cannot be undone.')) {
                return false;
            }

            // Use a simple password prompt (note: browser prompt doesn't support masking)
            // For better security, user should ensure no one is watching their screen
            const password = prompt('🔒 Enter your password to confirm payment posting:\n\n(Note: Ensure no one is watching your screen)');
            
            if (!password || password.trim() === '') {
                alert('Password is required to post payment.');
                return false;
            }

            document.getElementById('post_password').value = password;
            return true;
        }

        function confirmReverse() {
            // Use a simple password prompt (note: browser prompt doesn't support masking)
            // For better security, user should ensure no one is watching their screen
            const password = prompt('⚠️ WARNING: This will reverse the payment and create a reversing journal entry.\n\n🔒 Enter your password to confirm:\n\n(Note: Ensure no one is watching your screen)');
            
            if (password === null || password.trim() === '') {
                return false;
            }
            
            document.getElementById('reversePassword').value = password;
            return true;
        }
    </script>
</x-app-layout>