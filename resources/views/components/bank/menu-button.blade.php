{{-- Opens the Bank Register menu panel (x-bank.subnav). The number is the scanned receipts not yet linked to an entry. --}}
@php
    $user = auth()->user();
    $waiting = $user?->can('bank-receipt-scan-list') ? \App\Models\BankReceiptScan::where('status', 'pending')->count() : 0;
@endphp
<button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('bk-toggle-menu')" aria-controls="bk-menu-panel" title="Receipt scans, reconciliation, cheques, reports and setup">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
    Menu @if ($waiting)<span class="ak-count ak-count-dark">{{ $waiting }}</span>@endif
</button>
