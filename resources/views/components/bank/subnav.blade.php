@props(['inside' => false])

@php
    $user = auth()->user();
    $pendingApprovals = $user?->can('bank-statement-entry-approve') ? \App\Models\BankStatementEntry::where('status', 'pending_approval')->count() : 0;
    $pendingScans = $user?->can('bank-receipt-scan-list') ? \App\Models\BankReceiptScan::where('status', 'pending')->count() : 0;
    $items = [
        ['label' => 'Register', 'route' => 'bank-statement-entries.index', 'active' => ['bank-statement-entries.*', 'bank-statement-imports.*'], 'can' => 'bank-statement-entry-list'],
        ['label' => 'Approvals', 'route' => 'bank-approvals.index', 'active' => ['bank-approvals.*'], 'can' => 'bank-statement-entry-approve', 'badge' => $pendingApprovals, 'badgeClass' => 'bg-amber-500'],
        ['label' => 'Receipt scans', 'route' => 'bank-receipt-scans.index', 'active' => ['bank-receipt-scans.*'], 'can' => 'bank-receipt-scan-list', 'badge' => $pendingScans, 'badgeClass' => 'bg-sky-600'],
        ['label' => 'Reconciliation', 'route' => 'bank-reconciliations.index', 'active' => ['bank-reconciliations.*'], 'can' => 'bank-reconciliation-list'],
        ['label' => 'Reports', 'route' => 'reports.bank.index', 'active' => ['reports.bank.*'], 'canAny' => \App\Services\BankRegister\BankRegisterPermissions::REPORTS],
        ['label' => 'Ledger accounts', 'route' => 'bank-ledger-accounts.index', 'active' => ['bank-ledger-accounts.*'], 'can' => 'bank-ledger-account-list'],
        ['label' => 'Categories & rules', 'route' => 'bank-entry-categories.index', 'active' => ['bank-entry-categories.*', 'bank-party-aliases.*', 'bank-entry-rules.*', 'bank-cheques.*'], 'can' => 'bank-entry-category-list'],
    ];
@endphp

<nav aria-label="Bank Register sections" class="bank-noprint {{ $inside ? '' : 'ak-page' }}" style="{{ $inside ? '' : 'padding-bottom:0;padding-top:16px' }}">
    <div class="bk-tabs-nav">
        @foreach ($items as $item)
            @continue(isset($item['can']) && ! $user?->can($item['can']))
            @continue(isset($item['canAny']) && ! $user?->canAny($item['canAny']))
            @php($active = request()->routeIs(...$item['active']))
            <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif class="{{ $active ? 'is-active' : '' }}">
                {{ $item['label'] }}
                @if (($item['badge'] ?? 0) > 0)<span class="bk-badge">{{ $item['badge'] }}</span>@endif
            </a>
        @endforeach
        <a href="{{ route('bank-register.help') }}" target="_blank" rel="noopener" class="bk-help-link">? Help <span dir="rtl" lang="ur">(اردو)</span></a>
    </div>
</nav>
