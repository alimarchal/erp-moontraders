@php
    $transactionTypeOptions = \App\Models\ClaimRegister::transactionTypeOptions();
    $transactionTypeLabel = $transactionTypeOptions[$claimRegister->transaction_type] ?? ucfirst($claimRegister->transaction_type);
    $isPosted = $claimRegister->isPosted();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Claim: {{ $claimRegister->reference_number ?? '#' . $claimRegister->id }}
        </h2>
        <div class="flex justify-center items-center float-right gap-2">
            @if (!$isPosted)
                @can('claim-register-edit')
                    <a href="{{ route('claim-registers.edit', $claimRegister) }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-4 h-4 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                        Edit
                    </a>
                @endcan

                @can('claim-register-post')
                    <form id="postClaimForm" action="{{ route('claim-registers.post', $claimRegister->id) }}" method="POST"
                        onsubmit="return confirmPostClaim();" class="inline-block">
                        @csrf
                        <input type="hidden" id="post_password" name="password" value="">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                            <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Post Claim
                        </button>
                    </form>
                @endcan

                @can('claim-register-delete')
                    <form action="{{ route('claim-registers.destroy', $claimRegister->id) }}" method="POST"
                        onsubmit="return confirm('Are you sure you want to delete this claim?');" class="inline-block">
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
                @endcan
            @endif

            <a href="{{ route('claim-registers.index') }}"
                class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 transition">
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
            <x-status-message class="mb-4 mt-4" />
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">

                <div class="p-6">
                    {{-- Posted badge --}}
                    @if ($isPosted)
                        <div class="mb-4 flex items-center gap-2">
                            <span
                                class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                Posted
                            </span>
                            <span class="text-sm text-gray-500">
                                by {{ $claimRegister->postedByUser?->name ?? 'N/A' }}
                                on {{ $claimRegister->posted_at?->format('d M Y H:i') }}
                            </span>
                            @if ($claimRegister->journalEntry)
                                <span class="text-sm text-gray-500">
                                    &mdash; JE
                                    #{{ $claimRegister->journalEntry->entry_number ?? $claimRegister->journal_entry_id }}
                                </span>
                            @endif
                        </div>
                    @else
                        <div class="mb-4">
                            <span
                                class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-gray-200 text-gray-700">
                                Draft
                            </span>
                        </div>
                    @endif

                    <form>
                        {{-- Row 1: Supplier, Transaction Type, Transaction Date --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-label for="supplier" value="Supplier" />
                                <x-input id="supplier" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$claimRegister->supplier?->supplier_name ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="transaction_type" value="Transaction Type" />
                                <x-input id="transaction_type" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$transactionTypeLabel" disabled readonly />
                            </div>
                            <div>
                                <x-label for="transaction_date" value="Transaction Date" />
                                <x-input id="transaction_date" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$claimRegister->transaction_date?->format('d-m-Y')" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 2: Reference, Description, Claim Month --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="reference_number" value="Reference Number" />
                                <x-input id="reference_number" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$claimRegister->reference_number ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="description" value="Description" />
                                <x-input id="description" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$claimRegister->description ?? '-'" disabled readonly />
                            </div>
                            <div>
                                <x-label for="claim_month" value="Claim Month" />
                                <x-input id="claim_month" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$claimRegister->claim_month ?? '-'" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 3: Date of Dispatch, Debit, Credit --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <x-label for="date_of_dispatch" value="Date of Dispatch" />
                                <x-input id="date_of_dispatch" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$claimRegister->date_of_dispatch?->format('d-m-Y') ?? '-'" disabled
                                    readonly />
                            </div>
                            <div>
                                <x-label for="debit" value="Debit" />
                                <x-input id="debit" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 font-bold"
                                    :value="number_format($claimRegister->debit, 2)" disabled readonly />
                            </div>
                            <div>
                                <x-label for="credit" value="Credit" />
                                <x-input id="credit" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 font-bold"
                                    :value="number_format($claimRegister->credit, 2)" disabled readonly />
                            </div>
                        </div>

                        {{-- Row 4: Notes --}}
                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <x-label for="notes" value="Notes" />
                                <textarea id="notes" rows="2"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100 border-gray-300 rounded-md"
                                    disabled readonly>{{ $claimRegister->notes ?? '-' }}</textarea>
                            </div>
                        </div>

                        {{-- Row 5: Timestamps --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-label for="created_at" value="Created At" />
                                <x-input id="created_at" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$claimRegister->created_at?->format('d-m-Y H:i:s') ?? '-'" disabled
                                    readonly />
                            </div>
                            <div>
                                <x-label for="updated_at" value="Last Updated" />
                                <x-input id="updated_at" type="text"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$claimRegister->updated_at?->format('d-m-Y H:i:s') ?? '-'" disabled
                                    readonly />
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Password Modal for Post --}}
    @if (!$isPosted)
        <x-password-confirm-modal id="postClaimModal" title="Confirm Claim Posting"
            message="This will create accounting journal entries and cannot be undone." confirmButtonText="Confirm Post"
            confirmButtonClass="bg-emerald-600 hover:bg-emerald-700" />

        <script>
            function confirmPostClaim() {
                if (!confirm('Are you sure you want to POST this claim? This will create accounting journal entries.')) {
                    return false;
                }

                window.showPasswordModal('postClaimModal');
                return false;
            }

            document.addEventListener('passwordConfirmed', function (event) {
                const { modalId, password } = event.detail;

                if (modalId === 'postClaimModal') {
                    document.getElementById('post_password').value = password;
                    document.getElementById('postClaimForm').submit();
                }
            });
        </script>
    @endif

    <style>
        input:disabled,
        textarea:disabled {
            cursor: not-allowed !important;
        }
    </style>
</x-app-layout>