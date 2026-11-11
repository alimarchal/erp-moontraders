@php
    $transactionTypeOptions = \App\Models\ClaimRegister::transactionTypeOptions();
    $transactionTypeLabel = $transactionTypeOptions[$claimRegister->transaction_type] ?? ucfirst($claimRegister->transaction_type);
    $isPosted = $claimRegister->isPosted();
    $isRecovery = $claimRegister->transaction_type === 'recovery';
    $amount = max((float) $claimRegister->debit, (float) $claimRegister->credit);
    $title = 'Claim '.($claimRegister->reference_number ?? '#'.$claimRegister->id);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$title" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="claim-registers.index">
            <x-slot name="actions">
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: '{{ $isRecovery ? 'col-credit' : 'col-debit' }}' })" aria-haspopup="dialog" title="What this entry means, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                    <span class="sr-only">Help</span>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="claims" :has-supplier="true" />

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 pb-16">
        @if (session('success'))
            <div class="rg-flash rg-flash-ok mb-4" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rg-flash rg-flash-err mb-4" role="alert">{{ session('error') }}</div>
        @endif

        {{-- The entry at a glance --}}
        <div class="rg-card rg-hero bg-white sm:rounded-lg">
            <div class="rg-hero-top">
                <span class="rg-capsule {{ $isRecovery ? 'rg-capsule-green' : 'rg-capsule-blue' }}">{{ $isRecovery ? 'CR' : 'DR' }} · {{ $transactionTypeLabel }}</span>
                <span class="rg-capsule {{ $isPosted ? 'rg-capsule-green' : 'rg-capsule-grey' }}">{{ $isPosted ? 'Posted' : 'Draft' }}</span>
            </div>
            <p class="rg-hero-amount">Rs {{ number_format($amount, 2) }}</p>
            <p class="rg-hero-sub">{{ $claimRegister->supplier?->supplier_name ?? '—' }} · {{ $claimRegister->transaction_date?->format('d M Y') }}</p>
            <p class="rg-hero-note">
                @if ($isRecovery)
                    Recorded here only. The Supplier Ledger Register's claim adjustment books this recovery, so it is never taken off twice.
                @else
                    Posting books it to Pending Claims (1112) against claim income (4230).
                @endif
            </p>

            @if (! $isPosted)
                <div class="rg-hero-actions no-print">
                    @can('claim-register-edit')
                        <a href="{{ route('claim-registers.edit', $claimRegister) }}" class="rg-pill rg-pill-grey inline-flex items-center">Edit</a>
                    @endcan
                    @can('claim-register-delete')
                        <form action="{{ route('claim-registers.destroy', $claimRegister->id) }}" method="POST" class="inline-block"
                            onsubmit="return confirm('Delete this claim entry? It has not been posted, so nothing reaches the books.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rg-pill rg-pill-danger">Delete</button>
                        </form>
                    @endcan
                    @can('claim-register-post')
                        <form id="postClaimForm" action="{{ route('claim-registers.post', $claimRegister->id) }}" method="POST" class="inline-block"
                            onsubmit="window.showPasswordModal('postClaimModal'); return false;">
                            @csrf
                            <input type="hidden" id="post_password" name="password" value="">
                            <button type="submit" class="rg-pill rg-pill-green">{{ $isRecovery ? 'Record recovery' : 'Post claim' }}</button>
                        </form>
                    @endcan
                </div>
            @else
                <p class="rg-hero-posted">
                    Posted by {{ $claimRegister->postedByUser?->name ?? 'N/A' }} on {{ $claimRegister->posted_at?->format('d M Y, h:i A') }}
                    @if ($claimRegister->journal_entry_id && $claimRegister->journalEntry)
                        · journal {{ $claimRegister->journalEntry->reference ?? '#'.$claimRegister->journal_entry_id }}
                    @endif
                </p>
            @endif
        </div>

        {{-- Details --}}
        <div class="rg-card bg-white sm:rounded-lg mt-4">
            <dl class="rg-details">
                <dt>Supplier</dt><dd>{{ $claimRegister->supplier?->supplier_name ?? '—' }}</dd>
                <dt>Entry type</dt><dd>{{ $transactionTypeLabel }}</dd>
                <dt>Date</dt><dd>{{ $claimRegister->transaction_date?->format('d M Y') ?? '—' }}</dd>
                <dt>Reference</dt><dd>{{ $claimRegister->reference_number ?? '—' }}</dd>
                <dt>Description</dt><dd>{{ $claimRegister->description ?? '—' }}</dd>
                <dt>Claim month</dt><dd>{{ $claimRegister->claim_month ?? '—' }}</dd>
                <dt>Date of dispatch</dt><dd>{{ $claimRegister->date_of_dispatch?->format('d M Y') ?? '—' }}</dd>
                <dt>Claim (debit)</dt><dd class="tabular-nums">{{ number_format((float) $claimRegister->debit, 2) }}</dd>
                <dt>Recovery (credit)</dt><dd class="tabular-nums">{{ number_format((float) $claimRegister->credit, 2) }}</dd>
                <dt>Notes</dt><dd>{{ $claimRegister->notes ?: '—' }}</dd>
                <dt>Created</dt><dd>{{ $claimRegister->created_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                <dt>Last updated</dt><dd>{{ $claimRegister->updated_at?->format('d M Y, h:i A') ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    @if (! $isPosted)
        <div class="rg-confirm">
        <x-password-confirm-modal id="postClaimModal" :title="$isRecovery ? 'Record this recovery' : 'Post this claim'"
            :message="$isRecovery ? 'The recovery is recorded and locked. No journal is made here; the Supplier Ledger Register books it.' : 'A journal is made: Pending Claims against claim income. The entry is then locked.'"
            confirmButtonText="Confirm" confirmButtonClass="bg-emerald-600 hover:bg-emerald-700" />
        </div>

        <script>
            document.addEventListener('passwordConfirmed', function (event) {
                const { modalId, password } = event.detail;

                if (modalId === 'postClaimModal') {
                    document.getElementById('post_password').value = password;
                    document.getElementById('postClaimForm').submit();
                }
            });
        </script>
    @endif
</x-app-layout>
